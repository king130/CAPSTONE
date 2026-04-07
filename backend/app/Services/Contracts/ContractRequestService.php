<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContractRequestService
{
    public function __construct(
        private readonly ContractTypeResolver $resolver
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, UploadedFile>  $files
     */
    public function create(User $actor, array $payload, array $files = []): Contract
    {
        $requesterRole = (string) $actor->effectiveAppRole();
        $requesterOrg = $actor->activeOrganization();
        $partner = $this->resolvePartner($actor, $payload);
        $partnerOrg = $partner->activeOrganization();

        $contractType = null;
        if (! empty($payload['contract_type_id'])) {
            $contractType = $this->resolveContractType($actor, (int) $payload['contract_type_id'], $requesterOrg, $partnerOrg);
        }

        $dynamicFields = $this->normalizeDynamicFields($payload['dynamic_fields'] ?? []);
        $this->validateDynamicFields($contractType, $dynamicFields);
        $this->assertNoPendingDuplicate($actor, $partner, $requesterRole);

        return DB::transaction(function () use ($actor, $partner, $requesterOrg, $partnerOrg, $requesterRole, $payload, $files, $dynamicFields, $contractType) {
            $contract = Contract::query()->create([
                'school_user_id' => $requesterRole === 'school' ? $this->organizationOwnerId($requesterOrg, $actor) : $partner->id,
                'school_name' => $requesterRole === 'school' ? $this->organizationName($requesterOrg, $actor) : $this->organizationName($partnerOrg, $partner),
                'company_user_id' => $requesterRole === 'company' ? $this->organizationOwnerId($requesterOrg, $actor) : $partner->id,
                'company_name' => $requesterRole === 'company' ? $this->organizationName($requesterOrg, $actor) : $this->organizationName($partnerOrg, $partner),
                'requester_organization_id' => $requesterOrg?->id,
                'partner_organization_id' => $partnerOrg?->id,
                'requested_by_user_id' => $actor->id,
                'partner_user_id' => $partner->id,
                'requested_by_role' => $requesterRole,
                'status' => 'pending',
                'subject' => (string) $payload['subject'],
                'contract_type_id' => $contractType?->id,
                'contract_type' => $contractType?->name ?? ($payload['contract_type_label'] ?? $payload['contractType'] ?? null),
                'moa_reference_no' => $payload['moa_reference_no'] ?? $payload['moaReferenceNo'] ?? null,
                'purpose' => $payload['purpose'] ?? null,
                'start_date' => $payload['start_date'] ?? $payload['startDate'] ?? null,
                'end_date' => $payload['end_date'] ?? $payload['endDate'] ?? null,
                'internship_slots' => $payload['internship_slots'] ?? $payload['internshipSlots'] ?? null,
                'student_programs' => $payload['student_programs'] ?? $payload['studentPrograms'] ?? null,
                'course_allocations' => $payload['course_allocations'] ?? $payload['courseAllocations'] ?? null,
                'company_responsibilities' => $payload['company_responsibilities'] ?? $payload['companyResponsibilities'] ?? null,
                'school_responsibilities' => $payload['school_responsibilities'] ?? $payload['schoolResponsibilities'] ?? null,
                'terms' => $payload['terms'] ?? null,
                'school_contact_name' => $payload['school_contact_name'] ?? $payload['schoolContactName'] ?? null,
                'school_contact_email' => $payload['school_contact_email'] ?? $payload['schoolContactEmail'] ?? null,
                'company_contact_name' => $payload['company_contact_name'] ?? $payload['companyContactName'] ?? null,
                'company_contact_email' => $payload['company_contact_email'] ?? $payload['companyContactEmail'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'dynamic_fields' => $dynamicFields,
                'schema_snapshot' => $contractType?->fields_schema,
                'metadata' => [
                    'requester_role' => $requesterRole,
                    'requester_org_type' => $requesterOrg?->type,
                    'partner_org_type' => $partnerOrg?->type,
                ],
            ]);

            $attachments = $this->storeAttachments($contract, $files, $payload['attachments'] ?? null);
            if ($attachments !== []) {
                $contract->attachments = $attachments;
                $contract->save();
            }

            return $contract->fresh();
        });
    }

    private function resolvePartner(User $actor, array $payload): User
    {
        $appRole = $actor->effectiveAppRole();
        $partnerId = $appRole === 'school'
            ? (int) ($payload['company_id'] ?? $payload['companyId'] ?? 0)
            : (int) ($payload['school_id'] ?? $payload['schoolId'] ?? 0);

        $partnerRole = $appRole === 'school' ? 'company' : 'school';

        $partner = User::query()
            ->with(['organizationMemberships.organization', 'school', 'company'])
            ->whereKey($partnerId)
            ->where('role', $partnerRole)
            ->where('is_active', true)
            ->first();

        if (! $partner) {
            throw ValidationException::withMessages([
                'partner' => 'The selected partner could not be found.',
            ]);
        }

        return $partner;
    }

    private function resolveContractType(User $actor, int $id, ?Organization $requester, ?Organization $partner): ContractType
    {
        $resolved = $this->resolver->forUser($actor, $requester, $partner)->firstWhere('id', $id);
        if (! $resolved) {
            throw ValidationException::withMessages([
                'contract_type_id' => 'The selected contract type is not available for the chosen partner.',
            ]);
        }

        return $resolved;
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function normalizeDynamicFields(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $dynamicFields
     */
    private function validateDynamicFields(?ContractType $contractType, array $dynamicFields): void
    {
        if (! $contractType) {
            return;
        }

        $rules = [];
        foreach ($contractType->fields_schema ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $fieldRules = [];
            $fieldRules[] = ! empty($field['required']) ? 'required' : 'nullable';

            $type = $field['type'] ?? 'text';
            if (in_array($type, ['textarea', 'text', 'select', 'multiselect'], true)) {
                $fieldRules[] = $type === 'multiselect' ? 'array' : 'string';
            } elseif ($type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($type === 'date') {
                $fieldRules[] = 'date';
            } elseif ($type === 'email') {
                $fieldRules[] = 'email';
            } elseif ($type === 'checkbox') {
                $fieldRules[] = 'boolean';
            } else {
                $fieldRules[] = 'string';
            }

            $rules[$key] = $fieldRules;
            // Per-item rules for multiselect: do not use comma-joined "in:" (breaks when option text contains commas).
            // Course/program fields are narrowed on the client to aligned school↔company courses; allow any string up to 255.
            if ($type === 'multiselect') {
                $rules["{$key}.*"] = ['string', 'max:255'];
            }
        }

        Validator::make($dynamicFields, $rules)->validate();
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  mixed  $existing
     * @return array<int, array<string, mixed>>
     */
    private function storeAttachments(Contract $contract, array $files, mixed $existing = null): array
    {
        $attachments = is_array($existing) ? $existing : [];

        foreach ($files as $file) {
            $path = $file->store("contracts/{$contract->id}", 'public');
            $attachments[] = [
                'name' => $file->getClientOriginalName(),
                'disk' => 'public',
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'size' => $file->getSize(),
                'type' => $file->getClientMimeType(),
            ];
        }

        return $attachments;
    }

    private function organizationOwnerId(?Organization $organization, User $fallback): int
    {
        return (int) ($organization?->owner_user_id ?? $fallback->id);
    }

    private function organizationName(?Organization $organization, User $fallback): string
    {
        return $organization?->name ?? $fallback->name;
    }

    private function assertNoPendingDuplicate(User $actor, User $partner, string $requesterRole): void
    {
        $schoolUserId = $requesterRole === 'school' ? $this->organizationOwnerId($actor->activeOrganization(), $actor) : $partner->id;
        $companyUserId = $requesterRole === 'company' ? $this->organizationOwnerId($actor->activeOrganization(), $actor) : $partner->id;

        $exists = Contract::query()
            ->where('school_user_id', $schoolUserId)
            ->where('company_user_id', $companyUserId)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'partner' => 'There is already a pending contract request between these organizations.',
            ]);
        }
    }
}

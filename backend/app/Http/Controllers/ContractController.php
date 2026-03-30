<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractRequestService $contractRequestService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();
        $schoolAccount = $user->organizationSchool() ?? $user->school;
        $companyAccount = $user->organizationCompany() ?? $user->company;
        $query = Contract::query()
            ->with(['contractType', 'requesterOrganization', 'partnerOrganization'])
            ->orderByDesc('created_at');

        if ($appRole === 'school' && $schoolAccount) {
            $query->where('school_user_id', $schoolAccount->user_id);
        } elseif ($appRole === 'company' && $companyAccount) {
            $query->where('company_user_id', $companyAccount->user_id);
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Contract $contract) => $this->serialize($contract)),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge($this->normalizeIncomingPayload($request));

        $user = $request->user();
        $appRole = $user->effectiveAppRole();
        if (! in_array($appRole, ['school', 'company'], true)) {
            return response()->json(['message' => 'Only school and company accounts can create contracts.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'requestedByRole' => ['required', 'in:school,company'],
            'subject' => ['required', 'string', 'max:255'],
            'contractType' => ['nullable', 'string', 'max:255'],
            'contract_type_id' => ['nullable', 'integer', 'exists:contract_types,id'],
            'contract_type_label' => ['nullable', 'string', 'max:255'],
            'moaReferenceNo' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'internshipSlots' => ['nullable', 'integer', 'min:1'],
            'studentPrograms' => ['nullable', 'string'],
            'courseAllocations' => ['nullable', 'array'],
            'courseAllocations.*.course' => ['required_with:courseAllocations', 'string', 'max:255'],
            'courseAllocations.*.slots' => ['required_with:courseAllocations', 'integer', 'min:1'],
            'schoolResponsibilities' => ['nullable', 'string'],
            'companyResponsibilities' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'schoolContactName' => ['nullable', 'string', 'max:255'],
            'schoolContactEmail' => ['nullable', 'email', 'max:255'],
            'companyContactName' => ['nullable', 'string', 'max:255'],
            'companyContactEmail' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.size' => ['nullable', 'integer', 'min:0'],
            'attachments.*.type' => ['nullable', 'string', 'max:255'],
            'dynamic_fields' => ['nullable'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            'schoolId' => ['nullable', 'integer', 'exists:users,id'],
            'companyId' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if ($data['requestedByRole'] !== $appRole) {
            return response()->json(['message' => 'Requester role must match the signed-in account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $contract = $this->contractRequestService->create(
            $user,
            $request->all(),
            $request->file('files', [])
        );

        return response()->json(['data' => $this->serialize($contract)], Response::HTTP_CREATED);
    }

    public function accept(Request $request, Contract $contract)
    {
        $user = $request->user();
        if (! $this->canRespond($contract, $user->id, (string) $user->effectiveAppRole())) {
            return response()->json(['message' => 'Only the receiving party can accept this contract request.'], Response::HTTP_FORBIDDEN);
        }
        if ($contract->status !== 'pending') {
            return response()->json(['message' => 'Only pending contracts can be accepted.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $contract->update([
            'status' => 'active',
            'rejected_reason' => null,
            'cancelled_reason' => null,
            'cancelled_at' => null,
            'cancelled_by_role' => null,
        ]);

        return response()->json(['data' => $this->serialize($contract->fresh())]);
    }

    public function reject(Request $request, Contract $contract)
    {
        $user = $request->user();
        if (! $this->canRespond($contract, $user->id, (string) $user->effectiveAppRole())) {
            return response()->json(['message' => 'Only the receiving party can reject this contract request.'], Response::HTTP_FORBIDDEN);
        }
        if ($contract->status !== 'pending') {
            return response()->json(['message' => 'Only pending contracts can be rejected.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $contract->update([
            'status' => 'rejected',
            'rejected_reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['data' => $this->serialize($contract->fresh())]);
    }

    public function cancel(Request $request, Contract $contract)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();
        if (! $this->isParticipant($contract, $user->id, (string) $appRole) && $appRole !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        if (! in_array($contract->status, ['pending', 'active'], true)) {
            return response()->json(['message' => 'Only pending or active contracts can be cancelled.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
            'cancelledByRole' => ['nullable', 'in:school,company'],
        ]);

        $contract->update([
            'status' => 'cancelled',
            'cancelled_reason' => $data['reason'] ?? null,
            'cancelled_at' => now(),
            'cancelled_by_role' => $data['cancelledByRole'] ?? ($appRole === 'admin' ? null : $appRole),
        ]);

        return response()->json(['data' => $this->serialize($contract->fresh())]);
    }

    private function canRespond(Contract $contract, int $userId, string $role): bool
    {
        if ($contract->requested_by_role === 'school') {
            return $role === 'company' && $contract->company_user_id === $userId;
        }

        return $role === 'school' && $contract->school_user_id === $userId;
    }

    private function isParticipant(Contract $contract, int $userId, string $role): bool
    {
        return ($role === 'school' && $contract->school_user_id === $userId)
            || ($role === 'company' && $contract->company_user_id === $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Contract $contract): array
    {
        return [
            'id' => (string) $contract->id,
            'companyId' => (string) $contract->company_user_id,
            'companyName' => $contract->company_name,
            'schoolId' => (string) $contract->school_user_id,
            'schoolName' => $contract->school_name,
            'requestedByRole' => $contract->requested_by_role,
            'status' => $contract->status,
            'subject' => $contract->subject,
            'contractType' => $contract->contract_type,
            'contractTypeId' => $contract->contract_type_id ? (string) $contract->contract_type_id : null,
            'moaReferenceNo' => $contract->moa_reference_no,
            'purpose' => $contract->purpose,
            'startDate' => $contract->start_date?->toDateString(),
            'endDate' => $contract->end_date?->toDateString(),
            'internshipSlots' => $contract->internship_slots,
            'studentPrograms' => $contract->student_programs,
            'courseAllocations' => $contract->course_allocations ?? [],
            'companyResponsibilities' => $contract->company_responsibilities,
            'schoolResponsibilities' => $contract->school_responsibilities,
            'terms' => $contract->terms,
            'schoolContactName' => $contract->school_contact_name,
            'schoolContactEmail' => $contract->school_contact_email,
            'companyContactName' => $contract->company_contact_name,
            'companyContactEmail' => $contract->company_contact_email,
            'notes' => $contract->notes,
            'dynamicFields' => $contract->dynamic_fields ?? [],
            'schemaSnapshot' => $contract->schema_snapshot ?? [],
            'metadata' => $contract->metadata ?? [],
            'attachments' => $contract->attachments ?? [],
            'rejectedReason' => $contract->rejected_reason,
            'cancelledReason' => $contract->cancelled_reason,
            'cancelledAt' => $contract->cancelled_at?->toIso8601String(),
            'cancelledByRole' => $contract->cancelled_by_role,
            'createdAt' => $contract->created_at?->toIso8601String(),
            'updatedAt' => $contract->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeIncomingPayload(Request $request): array
    {
        $payload = $request->all();

        foreach (['dynamic_fields', 'courseAllocations', 'attachments'] as $jsonField) {
            if (isset($payload[$jsonField]) && is_string($payload[$jsonField])) {
                $decoded = json_decode($payload[$jsonField], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$jsonField] = $decoded;
                }
            }
        }

        return $payload;
    }
}

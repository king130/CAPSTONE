<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationVerificationDocument;
use App\Models\OrganizationVerificationDocumentHistory;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Support\OrganizationVerificationRequirements;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationVerificationDocumentController extends Controller
{
    public function __construct(private readonly EmailOtpService $emailOtps)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $this->resolvableOrganization($user);
        if (! $organization) {
            return response()->json(['message' => 'Organization not found.'], Response::HTTP_NOT_FOUND);
        }

        $rows = OrganizationVerificationDocument::query()
            ->where('organization_id', $organization->id)
            ->with(['uploadedBy:id,name,email', 'reviewedBy:id,name,email', 'histories' => fn ($q) => $q->orderByDesc('id')])
            ->orderByDesc('created_at')
            ->get();

        $requirements = OrganizationVerificationRequirements::forOrganization($organization);
        $latestByType = [];
        foreach ($rows as $row) {
            if (! isset($latestByType[$row->document_type])) {
                $latestByType[$row->document_type] = $row;
            }
        }

        $checklist = array_map(function (array $req) use ($latestByType, $organization) {
            $doc = $latestByType[$req['type']] ?? null;
            $orgStatus = $this->organizationVerificationStatusFromOrg($organization);

            return [
                'type' => $req['type'],
                'label' => $req['label'],
                'required' => (bool) $req['required'],
                'why' => (string) ($req['why'] ?? ''),
                'example' => (string) ($req['example'] ?? ''),
                'checklistStatus' => $this->checklistStatus($doc, $orgStatus),
                'document' => $doc ? $this->serialize($doc) : null,
            ];
        }, $requirements);

        return response()->json([
            'data' => $rows->map(fn (OrganizationVerificationDocument $row) => $this->serialize($row))->values(),
            'meta' => [
                'organizationId' => (string) $organization->id,
                'organizationType' => $organization->type,
                'verificationStatus' => $this->organizationVerificationStatus($user, $organization),
                'allowedTypes' => OrganizationVerificationRequirements::allowedTypes($organization),
                'requirements' => $requirements,
                'checklist' => $checklist,
                'requiredComplete' => $this->requiredDocumentsComplete($organization, $latestByType),
                'canMutateDocuments' => $this->isOrganizationOwner($user, $organization),
                'limits' => [
                    'maxKilobytes' => OrganizationVerificationRequirements::maxKilobytes(),
                    'acceptedExtensions' => OrganizationVerificationRequirements::acceptedExtensions(),
                    'acceptedMimes' => OrganizationVerificationRequirements::acceptedMimes(),
                ],
                'documentsMissingNotice' => $this->missingDocsNotice($organization, $latestByType),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($blocked = $this->emailVerificationBlock($user)) {
            return $blocked;
        }
        $organization = $this->resolvableOrganization($user);
        if (! $organization) {
            return response()->json(['message' => 'Organization not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! $this->canMutateDocuments($user, $organization)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $allowedTypes = OrganizationVerificationRequirements::allowedTypes($organization);
        $maxKb = OrganizationVerificationRequirements::maxKilobytes();
        $data = $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', $allowedTypes)],
            'file' => ['required', 'file', 'max:'.$maxKb],
        ]);

        $stored = $this->storeValidatedFile($request, $organization);

        $existing = OrganizationVerificationDocument::query()
            ->where('organization_id', $organization->id)
            ->where('document_type', $data['document_type'])
            ->orderByDesc('id')
            ->first();

        if ($existing && $existing->status === OrganizationVerificationDocument::STATUS_APPROVED) {
            Storage::disk(OrganizationVerificationRequirements::disk())->delete($stored['path']);

            return response()->json(['message' => 'Approved documents cannot be replaced.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $previousPath = $existing?->file_path;

        $document = DB::transaction(function () use ($user, $organization, $data, $stored, $existing) {
            if ($existing) {
                $this->archiveCurrentVersion($existing);

                $existing->update([
                    'uploaded_by_user_id' => $user->id,
                    'file_path' => $stored['path'],
                    'file_name' => $stored['original_name'],
                    'file_mime' => $stored['mime'],
                    'file_size' => $stored['size'],
                    'status' => OrganizationVerificationDocument::STATUS_PENDING,
                    'rejection_reason' => null,
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                    'admin_viewed_at' => null,
                    'admin_viewed_by_user_id' => null,
                ]);

                return $existing->fresh(['uploadedBy:id,name,email', 'histories']);
            }

            return OrganizationVerificationDocument::query()->create([
                'organization_id' => $organization->id,
                'uploaded_by_user_id' => $user->id,
                'document_type' => $data['document_type'],
                'file_path' => $stored['path'],
                'file_name' => $stored['original_name'],
                'file_mime' => $stored['mime'],
                'file_size' => $stored['size'],
                'status' => OrganizationVerificationDocument::STATUS_PENDING,
            ])->load(['uploadedBy:id,name,email']);
        });

        $disk = OrganizationVerificationRequirements::disk();
        if ($previousPath && $previousPath !== $stored['path'] && Storage::disk($disk)->exists($previousPath)) {
            Storage::disk($disk)->delete($previousPath);
        }

        return response()->json(['data' => $this->serialize($document)], Response::HTTP_CREATED);
    }

    public function resubmit(Request $request, OrganizationVerificationDocument $organizationVerificationDocument)
    {
        $user = $request->user();
        if ($blocked = $this->emailVerificationBlock($user)) {
            return $blocked;
        }
        $organization = $this->resolvableOrganization($user);
        if (! $organization || (int) $organizationVerificationDocument->organization_id !== (int) $organization->id) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->canMutateDocuments($user, $organization)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (! in_array($organizationVerificationDocument->status, [
            OrganizationVerificationDocument::STATUS_REJECTED,
            OrganizationVerificationDocument::STATUS_NEEDS_FIX,
            OrganizationVerificationDocument::STATUS_PENDING,
        ], true)) {
            return response()->json(['message' => 'Only a non-approved document can be replaced.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $allowedTypes = OrganizationVerificationRequirements::allowedTypes($organization);
        $maxKb = OrganizationVerificationRequirements::maxKilobytes();
        $data = $request->validate([
            'document_type' => ['nullable', 'string', 'in:'.implode(',', $allowedTypes)],
            'file' => ['required', 'file', 'max:'.$maxKb],
        ]);

        $stored = $this->storeValidatedFile($request, $organization);
        $previousPath = $organizationVerificationDocument->file_path;

        $document = DB::transaction(function () use ($organizationVerificationDocument, $data, $stored, $user) {
            $this->archiveCurrentVersion($organizationVerificationDocument);

            $organizationVerificationDocument->update([
                'document_type' => $data['document_type'] ?? $organizationVerificationDocument->document_type,
                'file_path' => $stored['path'],
                'file_name' => $stored['original_name'],
                'file_mime' => $stored['mime'],
                'file_size' => $stored['size'],
                'status' => OrganizationVerificationDocument::STATUS_PENDING,
                'rejection_reason' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'admin_viewed_at' => null,
                'admin_viewed_by_user_id' => null,
                'uploaded_by_user_id' => $user->id,
            ]);

            return $organizationVerificationDocument->fresh(['uploadedBy:id,name,email', 'histories']);
        });

        $disk = OrganizationVerificationRequirements::disk();
        if ($previousPath && $previousPath !== $stored['path'] && Storage::disk($disk)->exists($previousPath)) {
            Storage::disk($disk)->delete($previousPath);
        }

        return response()->json(['data' => $this->serialize($document)]);
    }

    public function destroy(Request $request, OrganizationVerificationDocument $organizationVerificationDocument)
    {
        $user = $request->user();
        $organization = $this->resolvableOrganization($user);
        if (! $organization || (int) $organizationVerificationDocument->organization_id !== (int) $organization->id) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->canMutateDocuments($user, $organization)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ($organizationVerificationDocument->status === OrganizationVerificationDocument::STATUS_APPROVED) {
            return response()->json(['message' => 'Approved documents cannot be removed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->archiveCurrentVersion($organizationVerificationDocument);
        $organizationVerificationDocument->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    public function submitForReview(Request $request)
    {
        $user = $request->user();
        if ($blocked = $this->emailVerificationBlock($user)) {
            return $blocked;
        }
        $organization = $this->resolvableOrganization($user);
        if (! $organization) {
            return response()->json(['message' => 'Organization not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! $this->canMutateDocuments($user, $organization)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $latestByType = $this->latestDocumentsByType($organization);
        if (! $this->requiredDocumentsComplete($organization, $latestByType)) {
            return response()->json([
                'message' => 'Upload all required documents before submitting for review.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $current = $this->organizationVerificationStatus($user, $organization);
        if ($current === 'approved') {
            return response()->json([
                'message' => 'Organization is already verified.',
                'meta' => ['verificationStatus' => 'approved'],
            ]);
        }

        $this->markOrganizationUnderReview($user, $organization);

        return response()->json([
            'message' => 'Documents submitted for review.',
            'meta' => [
                'verificationStatus' => 'under_review',
            ],
        ]);
    }

    public function download(Request $request, OrganizationVerificationDocument $organizationVerificationDocument)
    {
        $user = $request->user();
        if (! $this->canAccessDocument($user, $organizationVerificationDocument)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $disk = OrganizationVerificationRequirements::disk();
        if (! Storage::disk($disk)->exists($organizationVerificationDocument->file_path)) {
            return response()->json(['message' => 'Document file not found.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk($disk)->download(
            $organizationVerificationDocument->file_path,
            $organizationVerificationDocument->file_name
        );
    }

    public function adminIndex(Request $request, User $user)
    {
        if (! $request->user()?->isPlatformAdmin() && $request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $organization = $user->activeOrganization()
            ?? ($user->school?->organization)
            ?? ($user->company?->organization);

        if (! $organization) {
            return response()->json(['data' => [], 'meta' => ['requirements' => []]]);
        }

        $rows = OrganizationVerificationDocument::query()
            ->where('organization_id', $organization->id)
            ->with(['uploadedBy:id,name,email', 'reviewedBy:id,name,email', 'histories' => fn ($q) => $q->orderByDesc('id')])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (OrganizationVerificationDocument $row) => $this->serialize($row))
            ->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'organizationType' => $organization->type,
                'requirements' => OrganizationVerificationRequirements::forOrganization($organization),
            ],
        ]);
    }

    public function markAdminViewed(Request $request, OrganizationVerificationDocument $organizationVerificationDocument)
    {
        if (! $request->user()?->isPlatformAdmin() && $request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $organizationVerificationDocument->forceFill([
            'admin_viewed_at' => now(),
            'admin_viewed_by_user_id' => $request->user()->id,
        ])->save();

        return response()->json([
            'data' => $this->serialize($organizationVerificationDocument->fresh([
                'uploadedBy:id,name,email',
                'reviewedBy:id,name,email',
                'histories' => fn ($q) => $q->orderByDesc('id'),
            ])),
        ]);
    }

    public function adminReview(Request $request, OrganizationVerificationDocument $organizationVerificationDocument)
    {
        if (! $request->user()?->isPlatformAdmin() && $request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,needs_fix'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $data['status'];
        $reason = trim((string) ($data['reason'] ?? ''));
        if (in_array($status, ['rejected', 'needs_fix'], true) && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required when rejecting or requesting a re-upload.'],
            ]);
        }

        $admin = $request->user();
        $organization = $organizationVerificationDocument->organization;

        DB::transaction(function () use ($organizationVerificationDocument, $status, $reason, $admin, $organization) {
            $organizationVerificationDocument->update([
                'status' => $status,
                'rejection_reason' => in_array($status, ['rejected', 'needs_fix'], true) ? $reason : null,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Never auto-verify the organization from document approval alone.
            if ($status === 'approved') {
                return;
            }

            $this->applyOrgNonVerifiedState($organization, $status, $reason, $admin);
        });

        return response()->json([
            'data' => $this->serialize($organizationVerificationDocument->fresh(['uploadedBy:id,name,email', 'reviewedBy:id,name,email', 'histories'])),
        ]);
    }

    private function applyOrgNonVerifiedState(Organization $organization, string $docStatus, string $reason, User $admin): void
    {
        $next = $docStatus === 'needs_fix' ? 'pending' : 'rejected';
        $school = $organization->type === 'school'
            ? \App\Models\School::query()->where('organization_id', $organization->id)->first()
            : null;
        $company = in_array($organization->type, ['company', 'municipal_hall'], true)
            ? \App\Models\Company::query()->where('organization_id', $organization->id)->first()
            : null;

        $current = strtolower(trim((string) ($school?->verification_status ?? $company?->verification_status ?? 'pending')));
        if ($current === 'approved') {
            // Existing approved orgs stay approved; document-level feedback only.
            return;
        }

        $school?->update(['verification_status' => $next]);
        $company?->update(['verification_status' => $next]);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings['verification_rejection_reason'] = $reason;
        $organization->settings = $settings;
        $organization->save();

        $ownerId = $organization->owner_user_id;
        if ($ownerId) {
            Notification::query()->create([
                'user_id' => $ownerId,
                'title' => $docStatus === 'needs_fix' ? 'Document needs a fix' : 'Document rejected',
                'body' => $reason,
                'channel' => 'in_app',
                'metadata' => [
                    'verificationStatus' => $next,
                    'documentStatus' => $docStatus,
                    'redirectTo' => '/organization-verification',
                ],
            ]);
        }
    }

    /**
     * @return array{path: string, original_name: string, mime: string, size: int}
     */
    private function storeValidatedFile(Request $request, Organization $organization): array
    {
        $file = $request->file('file');
        if (! $file) {
            throw ValidationException::withMessages(['file' => ['A file is required.']]);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $allowedExtensions = OrganizationVerificationRequirements::acceptedExtensions();
        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['Accepted types: '.strtoupper(implode(', ', $allowedExtensions)).'.'],
            ]);
        }

        $realMime = $this->detectMimeFromContent((string) $file->getRealPath());
        $allowedMimes = OrganizationVerificationRequirements::acceptedMimes();
        if (! in_array($realMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => ['File content does not match an allowed type (PDF, JPG, PNG).'],
            ]);
        }

        // Reject obvious executable masquerades by extension already; refuse empty MIME.
        if ($realMime === '' || str_contains($realMime, 'executable') || str_contains($realMime, 'octet-stream')) {
            throw ValidationException::withMessages([
                'file' => ['Executables and unrecognized binaries are not allowed.'],
            ]);
        }

        $disk = OrganizationVerificationRequirements::disk();
        $randomName = Str::uuid()->toString().'.'.$extension;
        $directory = 'organization-verification/'.$organization->id;
        $path = $file->storeAs($directory, $randomName, $disk);

        return [
            'path' => $path,
            'original_name' => $this->safeFileName((string) $file->getClientOriginalName()),
            'mime' => $realMime,
            'size' => (int) ($file->getSize() ?: 0),
        ];
    }

    private function detectMimeFromContent(string $absolutePath): string
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($absolutePath);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function archiveCurrentVersion(OrganizationVerificationDocument $document): void
    {
        if (! $document->file_path) {
            return;
        }

        $disk = OrganizationVerificationRequirements::disk();
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION) ?: 'bin';
        $historyPath = 'organization-verification/'.$document->organization_id.'/history/'.Str::uuid()->toString().'.'.$extension;

        if (Storage::disk($disk)->exists($document->file_path)) {
            Storage::disk($disk)->copy($document->file_path, $historyPath);
        } else {
            $historyPath = $document->file_path;
        }

        OrganizationVerificationDocumentHistory::query()->create([
            'organization_verification_document_id' => $document->id,
            'organization_id' => $document->organization_id,
            'uploaded_by_user_id' => $document->uploaded_by_user_id,
            'document_type' => $document->document_type,
            'file_path' => $historyPath,
            'file_name' => $document->file_name,
            'file_mime' => $document->file_mime,
            'file_size' => $document->file_size,
            'status' => $document->status,
            'rejection_reason' => $document->rejection_reason,
            'uploaded_at' => $document->updated_at ?? $document->created_at,
        ]);
    }

    /**
     * @return array<string, OrganizationVerificationDocument>
     */
    private function latestDocumentsByType(Organization $organization): array
    {
        $rows = OrganizationVerificationDocument::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->get();

        $latest = [];
        foreach ($rows as $row) {
            if (! isset($latest[$row->document_type])) {
                $latest[$row->document_type] = $row;
            }
        }

        return $latest;
    }

    /**
     * @param  array<string, OrganizationVerificationDocument>  $latestByType
     */
    private function requiredDocumentsComplete(Organization $organization, array $latestByType): bool
    {
        foreach (OrganizationVerificationRequirements::requiredTypes($organization) as $type) {
            $doc = $latestByType[$type] ?? null;
            if (! $doc) {
                return false;
            }
            if (in_array($doc->status, [
                OrganizationVerificationDocument::STATUS_REJECTED,
                OrganizationVerificationDocument::STATUS_NEEDS_FIX,
            ], true)) {
                return false;
            }
        }

        return true;
    }

    private function checklistStatus(?OrganizationVerificationDocument $doc, string $orgStatus): string
    {
        if (! $doc) {
            return 'missing';
        }

        return match ($doc->status) {
            OrganizationVerificationDocument::STATUS_APPROVED => 'approved',
            OrganizationVerificationDocument::STATUS_REJECTED,
            OrganizationVerificationDocument::STATUS_NEEDS_FIX => 'needs_fix',
            default => $orgStatus === 'under_review' ? 'under_review' : 'uploaded',
        };
    }

    /**
     * @param  array<string, OrganizationVerificationDocument>  $latestByType
     */
    private function missingDocsNotice(Organization $organization, array $latestByType): ?string
    {
        $status = $this->organizationVerificationStatusFromOrg($organization);
        if ($status !== 'approved') {
            return null;
        }

        $missing = [];
        foreach (OrganizationVerificationRequirements::requiredTypes($organization) as $type) {
            if (! isset($latestByType[$type])) {
                $missing[] = OrganizationVerificationRequirements::labelFor($type, $organization->type);
            }
        }

        if ($missing === []) {
            return null;
        }

        return 'Your organization is verified. Optional: upload missing verification documents ('.implode(', ', $missing).').';
    }

    private function organizationVerificationStatusFromOrg(Organization $organization): string
    {
        if ($organization->type === 'school') {
            return strtolower(trim((string) (
                \App\Models\School::query()->where('organization_id', $organization->id)->value('verification_status')
                ?? 'pending'
            )));
        }

        return strtolower(trim((string) (
            \App\Models\Company::query()->where('organization_id', $organization->id)->value('verification_status')
            ?? 'pending'
        )));
    }

    private function resolvableOrganization(User $user): ?Organization
    {
        return $user->activeOrganization()
            ?? $user->organizationSchool()?->organization
            ?? $user->organizationCompany()?->organization
            ?? $user->school?->organization
            ?? $user->company?->organization;
    }

    private function organizationVerificationStatus(User $user, Organization $organization): string
    {
        if ($organization->type === 'school') {
            return strtolower(trim((string) ($user->organizationSchool()?->verification_status
                ?? $user->school?->verification_status
                ?? 'pending')));
        }

        return strtolower(trim((string) ($user->organizationCompany()?->verification_status
            ?? $user->company?->verification_status
            ?? 'pending')));
    }

    private function markOrganizationUnderReview(User $user, Organization $organization): void
    {
        $current = $this->organizationVerificationStatus($user, $organization);
        if (in_array($current, ['approved'], true)) {
            return;
        }

        if ($organization->type === 'school') {
            $school = $user->organizationSchool() ?? $user->school;
            $school?->update(['verification_status' => 'under_review']);
        } else {
            $company = $user->organizationCompany() ?? $user->company;
            $company?->update(['verification_status' => 'under_review']);
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        unset($settings['verification_rejection_reason']);
        $organization->settings = $settings;
        $organization->save();
    }

    private function isOrganizationOwner(User $user, Organization $organization): bool
    {
        if ($organization->owner_user_id && (int) $organization->owner_user_id === (int) $user->id) {
            return true;
        }

        // Legacy single-profile owners without owner_user_id set.
        if ($organization->type === 'school' && $user->school && (int) $user->school->organization_id === (int) $organization->id) {
            return (int) $user->school->user_id === (int) $user->id;
        }

        if (in_array($organization->type, ['company', 'municipal_hall'], true)
            && $user->company
            && (int) $user->company->organization_id === (int) $organization->id) {
            return (int) $user->company->user_id === (int) $user->id;
        }

        return false;
    }

    private function canMutateDocuments(User $user, Organization $organization): bool
    {
        $role = $user->effectiveAppRole();
        if (! in_array($role, ['school', 'company', 'municipal_hall'], true)
            && ! in_array($user->role, ['school', 'company', 'municipal_hall'], true)) {
            return false;
        }

        return $this->isOrganizationOwner($user, $organization);
    }

    private function emailVerificationBlock(?User $user): ?\Illuminate\Http\JsonResponse
    {
        if (! $user || ! $this->emailOtps->needsEmailVerification($user)) {
            return null;
        }

        return response()->json([
            'message' => 'Verify your email with the one-time code before uploading or submitting documents.',
            'code' => 'email_verification_required',
            'errors' => [
                'email' => ['Verify your email with the one-time code before uploading or submitting documents.'],
            ],
        ], Response::HTTP_FORBIDDEN);
    }

    private function canAccessDocument(User $user, OrganizationVerificationDocument $document): bool
    {
        if ($user->isPlatformAdmin() || $user->effectiveAppRole() === 'admin') {
            return true;
        }

        $organization = $this->resolvableOrganization($user);

        return $organization && (int) $organization->id === (int) $document->organization_id;
    }

    private function safeFileName(string $name): string
    {
        $base = trim(str_replace(['\\', '/'], '', $name));
        $base = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $base) ?: 'verification-document';

        return mb_substr($base, 0, 180);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OrganizationVerificationDocument $row): array
    {
        return [
            'id' => (string) $row->id,
            'organizationId' => (string) $row->organization_id,
            'documentType' => $row->document_type,
            'documentTypeLabel' => OrganizationVerificationRequirements::labelFor(
                $row->document_type,
                $row->organization?->type
            ),
            'fileName' => $row->file_name,
            'fileMime' => $row->file_mime,
            'fileSize' => (int) $row->file_size,
            'status' => $row->status,
            'rejectionReason' => $row->rejection_reason,
            'uploadedBy' => $row->uploadedBy?->name ?? $row->uploadedBy?->email,
            'uploadedAt' => $row->created_at?->toIso8601String(),
            'reviewedBy' => $row->reviewedBy?->name ?? $row->reviewedBy?->email,
            'reviewedAt' => $row->reviewed_at?->toIso8601String(),
            'adminViewedAt' => $row->admin_viewed_at?->toIso8601String(),
            'adminViewedByUserId' => $row->admin_viewed_by_user_id
                ? (string) $row->admin_viewed_by_user_id
                : null,
            'history' => $row->relationLoaded('histories')
                ? $row->histories->map(fn (OrganizationVerificationDocumentHistory $h) => [
                    'id' => (string) $h->id,
                    'fileName' => $h->file_name,
                    'fileMime' => $h->file_mime,
                    'fileSize' => (int) $h->file_size,
                    'status' => $h->status,
                    'rejectionReason' => $h->rejection_reason,
                    'uploadedByUserId' => $h->uploaded_by_user_id ? (string) $h->uploaded_by_user_id : null,
                    'uploadedAt' => $h->uploaded_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }
}

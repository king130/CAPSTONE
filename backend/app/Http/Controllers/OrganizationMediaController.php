<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMedia;
use App\Models\User;
use App\Services\PermissionGate;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class OrganizationMediaController extends Controller
{
    private const MAX_BYTES = 3 * 1024 * 1024;

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    public function __construct(
        private readonly PermissionGate $permissions,
        private readonly SubscriptionPlanService $subscriptionPlans,
    ) {
    }

    public function index(Organization $organization)
    {
        $media = OrganizationMedia::query()
            ->where('organization_id', $organization->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OrganizationMedia $item) => $this->serialize($item))
            ->values();

        return response()->json(['data' => $media]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->activeOrganization();

        if (! $organization) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->canManageMedia($user, $organization)) {
            return response()->json(['message' => 'Missing permission to manage organization media.'], Response::HTTP_FORBIDDEN);
        }

        if ($this->subscriptionPlans->wouldExceedAfterIncrement($organization, 'organization.photos')) {
            $overage = $this->subscriptionPlans->getOverageForResource($organization, 'organization.photos') ?? [
                'key' => 'organization.photos',
                'message' => 'Your organization has reached the photo limit for the current subscription plan.',
            ];

            return response()->json([
                'message' => $overage['message'] ?? 'Photo limit reached for the current plan.',
                'overage' => $overage,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:3072'],
            'caption' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_cover' => ['nullable', 'boolean'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $mimeError = $this->validateImageUpload($file);
        if ($mimeError !== null) {
            return response()->json([
                'message' => $mimeError,
                'errors' => ['file' => [$mimeError]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = match ($this->detectMime($file)) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }

        $filename = Str::lower(Str::random(40)).'.'.$extension;
        $directory = 'organization-media/'.$organization->id;
        $path = $file->storeAs($directory, $filename, 'public');

        if (! $path) {
            return response()->json(['message' => 'Unable to store uploaded image.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $isCover = (bool) ($data['is_cover'] ?? false);
        if ($isCover) {
            OrganizationMedia::query()
                ->where('organization_id', $organization->id)
                ->where('is_cover', true)
                ->update(['is_cover' => false]);
        } elseif (! OrganizationMedia::query()->where('organization_id', $organization->id)->where('is_cover', true)->exists()) {
            $isCover = true;
        }

        $media = OrganizationMedia::query()->create([
            'organization_id' => $organization->id,
            'path' => $path,
            'caption' => $data['caption'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_cover' => $isCover,
        ]);

        $this->subscriptionPlans->syncOrganizationCompliance($organization);

        return response()->json(['data' => $this->serialize($media)], Response::HTTP_CREATED);
    }

    public function update(Request $request, OrganizationMedia $organizationMedia)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::query()->find($organizationMedia->organization_id);

        if (! $organization) {
            return response()->json(['message' => 'Organization not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! $this->canManageMedia($user, $organization)) {
            return response()->json(['message' => 'Missing permission to manage organization media.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string', 'max:160'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'is_cover' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_cover', $data) && $data['is_cover']) {
            OrganizationMedia::query()
                ->where('organization_id', $organization->id)
                ->where('id', '!=', $organizationMedia->id)
                ->where('is_cover', true)
                ->update(['is_cover' => false]);
        }

        $organizationMedia->fill($data);
        $organizationMedia->save();

        return response()->json(['data' => $this->serialize($organizationMedia->fresh())]);
    }

    public function destroy(Request $request, OrganizationMedia $organizationMedia)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::query()->find($organizationMedia->organization_id);

        if (! $organization) {
            return response()->json(['message' => 'Organization not found.'], Response::HTTP_NOT_FOUND);
        }

        $isAdmin = $user->isPlatformAdmin() || $user->effectiveAppRole() === 'admin';
        if (! $isAdmin && ! $this->canManageMedia($user, $organization)) {
            return response()->json(['message' => 'Missing permission to manage organization media.'], Response::HTTP_FORBIDDEN);
        }

        $wasCover = (bool) $organizationMedia->is_cover;
        $organizationMedia->delete();

        if ($wasCover) {
            $next = OrganizationMedia::query()
                ->where('organization_id', $organization->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
            if ($next) {
                $next->is_cover = true;
                $next->save();
            }
        }

        $this->subscriptionPlans->syncOrganizationCompliance($organization);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function canManageMedia(User $user, Organization $organization): bool
    {
        if ($user->isPlatformAdmin() || $user->effectiveAppRole() === 'admin') {
            return true;
        }

        $active = $user->activeOrganization();
        if (! $active || (int) $active->id !== (int) $organization->id) {
            return false;
        }

        return $this->permissions->userCanAny($user, ['org.manage_profile', 'manage_profile']);
    }

    private function validateImageUpload(UploadedFile $file): ?string
    {
        if ($file->getSize() !== false && $file->getSize() > self::MAX_BYTES) {
            return 'Images must be 3 MB or smaller.';
        }

        $originalExtension = strtolower((string) $file->getClientOriginalExtension());
        if ($originalExtension === 'svg' || str_contains(strtolower((string) $file->getClientMimeType()), 'svg')) {
            return 'SVG uploads are not allowed.';
        }

        if ($originalExtension !== '' && ! in_array($originalExtension, self::ALLOWED_EXTENSIONS, true)) {
            return 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
        }

        $detectedMime = $this->detectMime($file);
        if (! in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            return 'Only real JPG, JPEG, PNG, and WEBP images are allowed.';
        }

        return null;
    }

    private function detectMime(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path) ?: '';
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }

        return strtolower((string) ($file->getMimeType() ?: ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OrganizationMedia $media): array
    {
        return [
            'id' => (string) $media->id,
            'organizationId' => (string) $media->organization_id,
            'path' => $media->path,
            'url' => $media->publicUrl(),
            'caption' => $media->caption,
            'sortOrder' => (int) $media->sort_order,
            'isCover' => (bool) $media->is_cover,
            'createdAt' => $media->created_at?->toIso8601String(),
            'updatedAt' => $media->updated_at?->toIso8601String(),
        ];
    }
}

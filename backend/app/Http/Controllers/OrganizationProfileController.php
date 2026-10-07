<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMedia;
use App\Models\User;
use App\Services\PermissionGate;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class OrganizationProfileController extends Controller
{
    public function __construct(
        private readonly PermissionGate $permissions,
        private readonly SubscriptionPlanService $subscriptionPlans,
    ) {
    }

    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->activeOrganization();

        if (! $organization || ! in_array($organization->type, ['school', 'company'], true)) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $appRole = $user->effectiveAppRole();
        if (! in_array($appRole, ['school', 'company'], true)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['data' => $this->serialize($organization)]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->activeOrganization();

        if (! $organization || ! in_array($organization->type, ['school', 'company'], true)) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->canManageProfile($user, $organization)) {
            return response()->json(['message' => 'Missing permission to manage organization profile.'], Response::HTTP_FORBIDDEN);
        }

        // Reject attempts to target another organization id from the payload.
        if ($request->filled('organization_id') || $request->filled('organizationId')) {
            $requestedId = (int) ($request->input('organization_id') ?? $request->input('organizationId'));
            if ($requestedId > 0 && $requestedId !== (int) $organization->id) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }
        }

        $data = $request->validate([
            'tagline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === null || $value === '') {
                    return;
                }
                $url = trim((string) $value);
                if (! preg_match('#^https?://#i', $url)) {
                    $fail('The website must be a valid http or https URL.');

                    return;
                }
                if (! filter_var($url, FILTER_VALIDATE_URL)) {
                    $fail('The website must be a valid http or https URL.');
                }
            }],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'perks' => ['sometimes', 'nullable', 'array', 'max:10'],
            'perks.*' => ['string', 'max:40'],
        ]);

        $updates = [];
        if (array_key_exists('tagline', $data)) {
            $updates['tagline'] = $this->cleanText($data['tagline'], 120);
        }
        if (array_key_exists('description', $data)) {
            $updates['description'] = $this->cleanText($data['description'], 2000);
        }
        if (array_key_exists('address', $data)) {
            $updates['address'] = $this->cleanText($data['address'], 500);
        }
        if (array_key_exists('city', $data)) {
            $updates['city'] = $this->cleanText($data['city'], 255);
        }
        if (array_key_exists('website', $data)) {
            $updates['website'] = $this->cleanWebsite($data['website']);
        }
        if (array_key_exists('industry', $data)) {
            $updates['industry'] = $this->cleanText($data['industry'], 255);
        }
        if (array_key_exists('perks', $data)) {
            $updates['perks'] = $this->cleanPerks($data['perks']);
        }

        if ($updates !== []) {
            $organization->fill($updates);
            $organization->save();
        }

        return response()->json(['data' => $this->serialize($organization->fresh())]);
    }

    private function canManageProfile(User $user, Organization $organization): bool
    {
        $active = $user->activeOrganization();
        if (! $active || (int) $active->id !== (int) $organization->id) {
            return false;
        }

        return $this->permissions->userCanAny($user, ['org.manage_profile', 'manage_profile']);
    }

    private function cleanText(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = (string) $value;
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $text) ?? $text;
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') {
            return null;
        }

        return Str::limit($text, $max, '');
    }

    private function cleanWebsite(mixed $value): ?string
    {
        $text = $this->cleanText($value, 255);
        if ($text === null) {
            return null;
        }

        return $text;
    }

    /**
     * @param  array<int, mixed>|null  $perks
     * @return array<int, string>|null
     */
    private function cleanPerks(?array $perks): ?array
    {
        if ($perks === null) {
            return null;
        }

        $cleaned = [];
        foreach (array_slice($perks, 0, 10) as $perk) {
            $item = $this->cleanText($perk, 40);
            if ($item !== null) {
                $cleaned[] = $item;
            }
        }

        return $cleaned;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Organization $organization): array
    {
        $organization->loadMissing('subscription');
        $plan = $this->subscriptionPlans->getPlanForOrganization($organization);
        $photoCount = OrganizationMedia::query()->where('organization_id', $organization->id)->count();
        $photoLimit = (int) ($plan?->organization_photos_limit ?? 3);

        return [
            'id' => (string) $organization->id,
            'name' => $organization->name,
            'type' => $organization->type,
            'tagline' => $organization->tagline,
            'description' => $organization->description,
            'address' => $organization->address,
            'city' => $organization->city,
            'website' => $organization->website,
            'industry' => $organization->industry,
            'perks' => array_values($organization->perks ?? []),
            'photoCount' => $photoCount,
            'photo_count' => $photoCount,
            'photoLimit' => $photoLimit,
            'photo_limit' => $photoLimit,
            'photosUsedLabel' => "{$photoCount} of {$photoLimit} photos used",
        ];
    }
}

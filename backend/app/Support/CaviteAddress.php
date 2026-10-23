<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Organization;
use App\Models\School;

class CaviteAddress
{
    /**
     * Same city and municipality list used by the registration form.
     *
     * @var array<int, string>
     */
    public const LOCATIONS = [
        'Bacoor City',
        'Cavite City',
        'Dasmarinas City',
        'General Trias City',
        'Imus City',
        'Tagaytay City',
        'Trece Martires City',
        'Alfonso',
        'Amadeo',
        'Carmona',
        'General Emilio Aguinaldo',
        'General Mariano Alvarez',
        'Indang',
        'Kawit',
        'Magallanes',
        'Maragondon',
        'Mendez',
        'Naic',
        'Noveleta',
        'Rosario',
        'Silang',
        'Tanza',
        'Ternate',
    ];

    /**
     * @param  array<string, mixed>  $profile
     */
    public static function registrationViolation(array $profile): ?string
    {
        $city = self::firstString($profile, ['locationArea', 'addressCity', 'cityMunicipality']);
        $address = self::firstString($profile, ['companyAddress', 'schoolAddress', 'address']);

        if ($city === null && $address === null) {
            return null;
        }

        if ($city !== null && ! self::isKnownCity($city)) {
            return 'Organization address must use a city or municipality in Cavite, Philippines.';
        }

        if ($address !== null && ! self::mentionsCavite($address) && $city === null) {
            return 'Organization address must be in Cavite, Philippines.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{address?: string, city?: ?string, error?: string}
     */
    public static function canonicalFromProfile(array $profile, ?string $existingAddress): array
    {
        $hasInput = false;
        foreach (['companyAddress', 'schoolAddress', 'address', 'cityMunicipality', 'locationArea', 'addressCity', 'barangay', 'addressBarangay'] as $key) {
            if (array_key_exists($key, $profile)) {
                $hasInput = true;
                break;
            }
        }

        if (! $hasInput) {
            return [];
        }

        $street = self::firstString($profile, ['companyAddress', 'schoolAddress', 'address']);
        $barangay = self::firstString($profile, ['barangay', 'addressBarangay']);
        $city = self::firstString($profile, ['cityMunicipality', 'locationArea', 'addressCity']);

        if ($city !== null && ! self::isKnownCity($city)) {
            return ['error' => 'Organization address must use a city or municipality in Cavite, Philippines.'];
        }

        if ($street !== null && self::mentionsCavite($street)) {
            return [
                'address' => $street,
                'city' => $city ?? self::inferCity($street),
            ];
        }

        if ($city !== null) {
            $parts = array_values(array_filter([$street, $barangay, $city, 'Cavite'], fn ($part) => is_string($part) && $part !== ''));

            return [
                'address' => implode(', ', $parts),
                'city' => $city,
            ];
        }

        if (self::mentionsCavite($existingAddress)) {
            return [
                'address' => trim((string) $existingAddress),
                'city' => self::inferCity((string) $existingAddress),
            ];
        }

        if ($street !== null) {
            return ['error' => 'Organization address must be in Cavite, Philippines.'];
        }

        return [];
    }

    public static function rememberOnOrganization(Organization $organization, ?string $address, ?string $city): void
    {
        $address = is_string($address) ? trim($address) : '';
        $city = is_string($city) ? trim($city) : '';

        if ($address === '' && $city === '') {
            return;
        }

        $dirty = false;
        if ($address !== '' && $organization->address !== $address) {
            $organization->address = $address;
            $dirty = true;
        }
        if ($city !== '' && $organization->city !== $city) {
            $organization->city = $city;
            $dirty = true;
        }

        if ($dirty) {
            $organization->save();
        }
    }

    /**
     * @return array{address: ?string, city: ?string}
     */
    public static function displayFor(Organization $organization): array
    {
        $address = trim((string) $organization->address);
        $city = trim((string) $organization->city);

        if ($address === '') {
            if (\App\Support\OrganizationTypes::isHostType($organization->type)) {
                $address = trim((string) Company::query()->where('organization_id', $organization->id)->value('company_address'));
            } elseif ($organization->type === 'school') {
                $address = trim((string) School::query()->where('organization_id', $organization->id)->value('school_address'));
            }
        }

        if ($city === '' && $address !== '') {
            $city = (string) (self::inferCity($address) ?? '');
        }

        return [
            'address' => $address !== '' ? $address : null,
            'city' => $city !== '' ? $city : null,
        ];
    }

    public static function isKnownCity(string $city): bool
    {
        return self::resolveKnownCity($city) !== null;
    }

    /**
     * Resolve a user-entered city/municipality to the catalog label.
     * Accepts bare names for "X City" entries (e.g. Imus, Dasmariñas, Tagaytay).
     */
    public static function resolveKnownCity(string $city): ?string
    {
        $needle = self::normalize($city);
        foreach (self::LOCATIONS as $location) {
            $normalized = self::normalize($location);
            if ($normalized === $needle) {
                return $location;
            }
            if (str_ends_with($normalized, ' city') && substr($normalized, 0, -5) === $needle) {
                return $location;
            }
        }

        return null;
    }

    public static function mentionsCavite(?string $address): bool
    {
        return is_string($address) && preg_match('/\bCavite\b/i', $address) === 1;
    }

    public static function inferCity(string $address): ?string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $address)), fn ($part) => $part !== ''));
        foreach ($parts as $part) {
            $resolved = self::resolveKnownCity($part);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, string>  $keys
     */
    private static function firstString(array $profile, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $profile)) {
                continue;
            }
            $value = $profile[$key];
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['ñ', 'á', 'é', 'í', 'ó', 'ú'], ['n', 'a', 'e', 'i', 'o', 'u'], $value);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}

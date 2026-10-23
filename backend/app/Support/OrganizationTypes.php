<?php

namespace App\Support;

/**
 * Canonical organization.type values and helpers for host (employer) orgs.
 */
final class OrganizationTypes
{
    public const SCHOOL = 'school';

    public const COMPANY = 'company';

    public const MUNICIPAL_HALL = 'municipal_hall';

    /**
     * Org types that post opportunities / approve host-stage DTRs via the companies host profile.
     *
     * @return array<int, string>
     */
    public static function hostTypes(): array
    {
        return [self::COMPANY, self::MUNICIPAL_HALL];
    }

    public static function isHostType(?string $type): bool
    {
        return in_array((string) $type, self::hostTypes(), true);
    }

    /**
     * User.role values that count toward seat limits for an organization type.
     * Host staff may be stored as company or municipal_hall depending on provision path.
     *
     * @return array<int, string>
     */
    public static function accountSeatRolesForOrganizationType(?string $type): array
    {
        return match ((string) $type) {
            self::SCHOOL => [self::SCHOOL],
            self::COMPANY, self::MUNICIPAL_HALL => [self::COMPANY, self::MUNICIPAL_HALL],
            default => [],
        };
    }

    public static function isSchool(?string $type): bool
    {
        return (string) $type === self::SCHOOL;
    }

    /**
     * App workspace role used by router/dashboard (company workspace reused for municipal halls).
     */
    public static function appRoleForOrganizationType(?string $type): ?string
    {
        return match ((string) $type) {
            self::SCHOOL => 'school',
            self::COMPANY, self::MUNICIPAL_HALL => 'company',
            default => null,
        };
    }

    public static function label(?string $type): string
    {
        return match ((string) $type) {
            self::SCHOOL => 'School',
            self::COMPANY => 'Company',
            self::MUNICIPAL_HALL => 'Municipal Hall',
            default => 'Organization',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function lguTypes(): array
    {
        return ['municipality', 'city', 'other_office'];
    }

    public static function lguTypeLabel(?string $value): string
    {
        return match ((string) $value) {
            'municipality' => 'Municipality',
            'city' => 'City',
            'other_office' => 'Other office',
            default => (string) $value,
        };
    }
}

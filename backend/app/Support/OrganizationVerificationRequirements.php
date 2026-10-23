<?php

namespace App\Support;

use App\Models\Organization;

class OrganizationVerificationRequirements
{
    /**
     * Hardcoded catalog used when config/organization_verification.php is missing
     * or returns an empty requirements map (common on partial Hostinger deploys).
     *
     * @return array<string, list<array{type: string, label: string, required: bool, why: string, example: string}>>
     */
    public static function defaultRequirements(): array
    {
        return [
            'school' => [
                [
                    'type' => 'school_recognition_permit',
                    'label' => 'School recognition or permit to operate',
                    'required' => true,
                    'why' => 'Shows the school is authorized to operate (CHED, DepEd, TESDA, or equivalent).',
                    'example' => 'Example: ched_recognition_certificate.pdf',
                ],
                [
                    'type' => 'representative_government_id',
                    'label' => 'Government ID of the authorized representative',
                    'required' => true,
                    'why' => 'Confirms the person creating this account is a real, authorized school staff member.',
                    'example' => 'Example: maria_santos_national_id.jpg',
                ],
                [
                    'type' => 'authorization_letter',
                    'label' => 'Authorization letter or designation',
                    'required' => true,
                    'why' => 'Shows this person is allowed to manage OJT accounts for the school.',
                    'example' => 'Example: ojt_coordinator_authorization_letter.pdf',
                ],
            ],
            'company' => [
                [
                    'type' => 'business_registration',
                    'label' => 'Business registration (SEC or DTI)',
                    'required' => true,
                    'why' => 'Proves the company is a registered business that can host interns.',
                    'example' => 'Example: dti_or_sec_registration.pdf',
                ],
                [
                    'type' => 'mayors_business_permit',
                    'label' => "Mayor's / business permit",
                    'required' => true,
                    'why' => 'Shows the business is allowed to operate in its city or municipality.',
                    'example' => 'Example: mayors_business_permit_2026.pdf',
                ],
                [
                    'type' => 'representative_government_id',
                    'label' => 'Government ID of the authorized representative',
                    'required' => true,
                    'why' => 'Confirms the person creating this account is a real company representative.',
                    'example' => 'Example: juan_dela_cruz_national_id.jpg',
                ],
                [
                    'type' => 'authorization_letter',
                    'label' => 'Authorization letter',
                    'required' => true,
                    'why' => 'Shows this person is allowed to manage OJT hosting for the company.',
                    'example' => 'Example: hr_authorization_letter.pdf',
                ],
                [
                    'type' => 'bir_certificate',
                    'label' => 'BIR certificate (optional)',
                    'required' => false,
                    'why' => 'Optional tax registration proof. Helps speed up review but is not required.',
                    'example' => 'Example: bir_certificate_of_registration.pdf',
                ],
            ],
            'municipal_hall' => [
                [
                    'type' => 'lgu_authorization_letter',
                    'label' => 'Letter of authorization from the local chief executive',
                    'required' => true,
                    'why' => 'Shows the municipal hall / LGU office approved this OJT hosting account.',
                    'example' => 'Example: mayor_authorization_letter.pdf',
                ],
                [
                    'type' => 'representative_government_id',
                    'label' => 'Government-issued ID of the authorized representative',
                    'required' => true,
                    'why' => 'Confirms the person creating this account is a real LGU staff member.',
                    'example' => 'Example: representative_national_id.jpg',
                ],
                [
                    'type' => 'official_office_proof',
                    'label' => 'Official proof of the office (letter or certificate)',
                    'required' => true,
                    'why' => 'Confirms this account belongs to a real municipal hall or LGU office.',
                    'example' => 'Example: official_office_letterhead.pdf',
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<array{type: string, label: string, required: bool, why?: string, example?: string}>>
     */
    public static function catalog(): array
    {
        $fromConfig = config('organization_verification.requirements', []);
        if (is_array($fromConfig) && $fromConfig !== []) {
            return $fromConfig;
        }

        return self::defaultRequirements();
    }

    /**
     * @return list<array{type: string, label: string, required: bool, why?: string, example?: string}>
     */
    public static function forOrganizationType(?string $type): array
    {
        $key = strtolower(trim((string) $type));
        $all = self::catalog();

        return array_values($all[$key] ?? []);
    }

    /**
     * @return list<array{type: string, label: string, required: bool, why?: string, example?: string}>
     */
    public static function forOrganization(Organization $organization): array
    {
        return self::forOrganizationType($organization->type);
    }

    /**
     * @return list<string>
     */
    public static function allowedTypes(Organization $organization): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['type'],
            self::forOrganization($organization)
        ));
    }

    /**
     * @return list<string>
     */
    public static function requiredTypes(Organization $organization): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['type'],
            array_filter(self::forOrganization($organization), static fn (array $row): bool => (bool) ($row['required'] ?? false))
        ));
    }

    public static function labelFor(string $type, ?string $organizationType = null): string
    {
        foreach (self::forOrganizationType($organizationType) as $row) {
            if ($row['type'] === $type) {
                return $row['label'];
            }
        }

        foreach (self::catalog() as $rows) {
            foreach ($rows as $row) {
                if (($row['type'] ?? null) === $type) {
                    return (string) $row['label'];
                }
            }
        }

        return $type;
    }

    public static function maxKilobytes(): int
    {
        return (int) config('organization_verification.max_kilobytes', 10240);
    }

    /**
     * @return list<string>
     */
    public static function acceptedMimes(): array
    {
        return array_values(config('organization_verification.accepted_mimes', [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ]));
    }

    /**
     * @return list<string>
     */
    public static function acceptedExtensions(): array
    {
        return array_values(config('organization_verification.accepted_extensions', ['pdf', 'jpg', 'jpeg', 'png']));
    }

    public static function disk(): string
    {
        return (string) config('organization_verification.disk', 'local');
    }
}

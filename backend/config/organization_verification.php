<?php

/**
 * Organization verification document catalog and upload limits.
 * Single source for School, Company, and Municipal Hall requirements.
 */
return [
    'disk' => 'local',

    // Existing code already used 10MB; keep that limit (requirement allowed existing limits).
    'max_kilobytes' => 10240,

    'accepted_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],

    'accepted_mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ],

    /**
     * @var array<string, list<array{type: string, label: string, required: bool, why?: string, example?: string}>>
     */
    'requirements' => [
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
    ],
];

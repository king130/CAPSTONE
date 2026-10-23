<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Job postings require only the Endorsement letter as an application document.
 * Resume remains a profile field (optional for hosts), not a posting requirement.
 */
class InternshipDocumentRequirements
{
    public const ENDORSEMENT_LABEL = 'Endorsement';

    public const ENDORSEMENT_CATEGORY = 'application-endorsement';

    /**
     * Effective checklist shown to students / hosts regardless of legacy stored arrays.
     *
     * @return list<string>
     */
    public static function effectiveRequiredDocuments(): array
    {
        return [self::ENDORSEMENT_LABEL];
    }

    /**
     * Validate create/update payload: reject any requirement other than Endorsement.
     *
     * @param  mixed  $input
     * @return list<string>
     */
    public static function normalizeForStorage(mixed $input): array
    {
        if ($input === null || $input === '') {
            return self::effectiveRequiredDocuments();
        }

        if (! is_array($input)) {
            throw ValidationException::withMessages([
                'required_documents' => ['Required documents must be an array containing only Endorsement.'],
            ]);
        }

        $normalized = [];
        foreach ($input as $item) {
            $label = trim((string) $item);
            if ($label === '') {
                continue;
            }
            $normalized[] = $label;
        }

        if ($normalized === []) {
            return self::effectiveRequiredDocuments();
        }

        foreach ($normalized as $label) {
            if (! self::isEndorsementLabel($label)) {
                throw ValidationException::withMessages([
                    'required_documents' => [
                        'Only the Endorsement letter may be required on a job posting. Other document requirements are not allowed.',
                    ],
                ]);
            }
        }

        return self::effectiveRequiredDocuments();
    }

    public static function isEndorsementLabel(string $label): bool
    {
        $value = strtolower(trim($label));

        return in_array($value, [
            'endorsement',
            'endorsement letter',
            'endorsement (letter)',
            'school endorsement',
            'school endorsement letter',
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function endorsementDocumentCategories(): array
    {
        return [self::ENDORSEMENT_CATEGORY, 'endorsement', 'endorsement-letter'];
    }
}

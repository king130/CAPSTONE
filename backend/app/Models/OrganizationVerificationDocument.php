<?php

namespace App\Models;

use App\Support\OrganizationVerificationRequirements;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class OrganizationVerificationDocument extends Model
{
    public const TYPE_SCHOOL_RECOGNITION_PERMIT = 'school_recognition_permit';

    public const TYPE_BUSINESS_REGISTRATION = 'business_registration';

    public const TYPE_MAYORS_BUSINESS_PERMIT = 'mayors_business_permit';

    public const TYPE_AUTHORIZATION_LETTER = 'authorization_letter';

    public const TYPE_BIR_CERTIFICATE = 'bir_certificate';

    public const TYPE_LGU_AUTHORIZATION_LETTER = 'lgu_authorization_letter';

    public const TYPE_REPRESENTATIVE_GOVERNMENT_ID = 'representative_government_id';

    public const TYPE_OFFICIAL_OFFICE_PROOF = 'official_office_proof';

    /** @deprecated Kept for legacy rows uploaded before Phase A catalog. */
    public const TYPE_SCHOOL_AUTHORIZATION = 'school_authorization';

    /** @deprecated Kept for legacy rows uploaded before Phase A catalog. */
    public const TYPE_OTHER_PROOF = 'other_proof';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_NEEDS_FIX = 'needs_fix';

    protected $fillable = [
        'organization_id',
        'uploaded_by_user_id',
        'document_type',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'status',
        'rejection_reason',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (OrganizationVerificationDocument $document): void {
            $disk = OrganizationVerificationRequirements::disk();
            if ($document->file_path && Storage::disk($disk)->exists($document->file_path)) {
                Storage::disk($disk)->delete($document->file_path);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrganizationVerificationDocumentHistory::class);
    }

    public static function allowedTypesForOrganization(Organization $organization): array
    {
        return OrganizationVerificationRequirements::allowedTypes($organization);
    }

    public function isMutableByOwner(): bool
    {
        return ! in_array($this->status, [self::STATUS_APPROVED], true);
    }
}

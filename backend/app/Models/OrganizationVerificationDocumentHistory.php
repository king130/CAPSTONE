<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationVerificationDocumentHistory extends Model
{
    protected $fillable = [
        'organization_verification_document_id',
        'organization_id',
        'uploaded_by_user_id',
        'document_type',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'status',
        'rejection_reason',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(OrganizationVerificationDocument::class, 'organization_verification_document_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}

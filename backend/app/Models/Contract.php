<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    protected $fillable = [
        'school_user_id',
        'school_name',
        'company_user_id',
        'company_name',
        'requested_by_role',
        'requester_organization_id',
        'partner_organization_id',
        'requested_by_user_id',
        'partner_user_id',
        'status',
        'subject',
        'contract_type_id',
        'contract_type',
        'moa_reference_no',
        'purpose',
        'start_date',
        'end_date',
        'internship_slots',
        'student_programs',
        'course_allocations',
        'company_responsibilities',
        'school_responsibilities',
        'terms',
        'school_contact_name',
        'school_contact_email',
        'company_contact_name',
        'company_contact_email',
        'notes',
        'dynamic_fields',
        'schema_snapshot',
        'metadata',
        'attachments',
        'rejected_reason',
        'cancelled_reason',
        'cancelled_at',
        'cancelled_by_role',
    ];

    protected function casts(): array
    {
        return [
            'course_allocations' => 'array',
            'dynamic_fields' => 'array',
            'schema_snapshot' => 'array',
            'metadata' => 'array',
            'attachments' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'cancelled_at' => 'datetime',
            'internship_slots' => 'integer',
        ];
    }

    public function schoolUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'school_user_id');
    }

    public function companyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_user_id');
    }

    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }

    public function requesterOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'requester_organization_id');
    }

    public function partnerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'partner_organization_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function partnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }
}

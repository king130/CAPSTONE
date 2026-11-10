<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InternshipEligibilityService
{
    /**
     * Agreement statuses that continue to authorize student discovery/apply.
     * pending_amendment keeps access for a previously approved partnership while
     * the amendment awaits partner acceptance (new pending agreements never qualify).
     *
     * @var list<string>
     */
    public const STUDENT_VISIBLE_AGREEMENT_STATUSES = ['active', 'pending_amendment'];

    /**
     * Resolve the student's school using the same precedence as the student eligible listing.
     */
    public function resolveStudentSchool(Student $student): ?School
    {
        if ($student->school_id) {
            $school = School::query()->find($student->school_id);
            if ($school) {
                return $school;
            }
        }

        if ($student->organization_id) {
            $school = School::query()->where('organization_id', $student->organization_id)->first();
            if ($school) {
                return $school;
            }
        }

        if ($student->school_subscription_code) {
            $school = School::query()->where('subscription_code', $student->school_subscription_code)->first();
            if ($school) {
                return $school;
            }
        }

        if ($student->school_name) {
            return School::query()->where('institution_name', $student->school_name)->first();
        }

        return null;
    }

    public function resolveHostType(Internship $internship): string
    {
        if (filled($internship->host_type)) {
            return (string) $internship->host_type;
        }

        return $internship->school_id ? 'school' : 'company';
    }

    /**
     * Canonical active (student-visible) agreement check between a school and host company/municipal hall.
     */
    public function hasActiveAgreementBetween(School $school, Company $company): bool
    {
        return $this->agreementsBetweenQuery($school, $company)->exists();
    }

    /**
     * @return Builder<Contract>
     */
    public function agreementsBetweenQuery(School $school, Company $company): Builder
    {
        return $this->studentVisibleAgreementsQuery()
            ->where(function (Builder $query) use ($school, $company) {
                $this->constrainAgreementPair($query, $school, $company);
            });
    }

    /**
     * Base query for agreements that authorize student discovery/apply.
     *
     * @return Builder<Contract>
     */
    public function studentVisibleAgreementsQuery(): Builder
    {
        $today = now()->toDateString();

        return Contract::query()
            ->whereIn('status', self::STUDENT_VISIBLE_AGREEMENT_STATUSES)
            ->where(function (Builder $dates) use ($today) {
                $dates->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today);
            });
    }

    /**
     * @return Collection<int, int> company profile ids (companies.id) for partnered hosts
     */
    public function activePartnerCompanyIds(School $school): Collection
    {
        $companyUserIds = $this->activePartnerCompanyUserIds($school);
        $organizationIds = $this->activePartnerHostOrganizationIds($school);

        if ($companyUserIds->isEmpty() && $organizationIds->isEmpty()) {
            return collect();
        }

        return Company::query()
            ->where(function (Builder $query) use ($companyUserIds, $organizationIds) {
                if ($companyUserIds->isNotEmpty()) {
                    $query->whereIn('user_id', $companyUserIds);
                }
                if ($organizationIds->isNotEmpty()) {
                    $method = $companyUserIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('organization_id', $organizationIds);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function activePartnerHostOrganizationIds(School $school): Collection
    {
        if (! $school->organization_id && ! $school->user_id) {
            return collect();
        }

        $agreements = $this->studentVisibleAgreementsQuery()
            ->where(function (Builder $query) use ($school) {
                if ($school->user_id) {
                    $query->where('school_user_id', $school->user_id);
                }
                if ($school->organization_id) {
                    $method = $school->user_id ? 'orWhere' : 'where';
                    $query->{$method}(function (Builder $orgs) use ($school) {
                        $orgs->where('requester_organization_id', $school->organization_id)
                            ->orWhere('partner_organization_id', $school->organization_id);
                    });
                }
            })
            ->get([
                'company_user_id',
                'requester_organization_id',
                'partner_organization_id',
            ]);

        $organizationIds = collect();
        foreach ($agreements as $agreement) {
            foreach ([(int) $agreement->requester_organization_id, (int) $agreement->partner_organization_id] as $orgId) {
                if ($orgId > 0 && $orgId !== (int) $school->organization_id) {
                    $organizationIds->push($orgId);
                }
            }
        }

        return $organizationIds->unique()->values();
    }

    /**
     * @return array{allowed: bool, message: string, code: string}
     */
    public function evaluate(Student $student, Internship $internship): array
    {
        if (strtolower(trim((string) $internship->status)) !== 'active') {
            return [
                'allowed' => false,
                'message' => 'Only active internships can receive applications.',
                'code' => 'internship_not_active',
            ];
        }

        $school = $this->resolveStudentSchool($student);
        if (! $school) {
            return [
                'allowed' => false,
                'message' => 'Your student account is not linked to a school, so this internship is unavailable.',
                'code' => 'student_school_required',
            ];
        }

        $hostType = $this->resolveHostType($internship);

        if ($hostType === 'school') {
            if ((int) $internship->school_id !== (int) $school->id) {
                return [
                    'allowed' => false,
                    'message' => 'This school-hosted internship is not available to your school.',
                    'code' => 'school_internship_mismatch',
                ];
            }

            return [
                'allowed' => true,
                'message' => '',
                'code' => 'eligible',
            ];
        }

        $company = $internship->relationLoaded('company')
            ? $internship->company
            : $internship->company()->first();

        if (! $company) {
            return [
                'allowed' => false,
                'message' => 'This internship is not available to your school.',
                'code' => 'internship_unavailable',
            ];
        }

        if (! $this->hasActiveAgreementBetween($school, $company)) {
            return [
                'allowed' => false,
                'message' => 'This internship is not available because your school does not have an active agreement with the company.',
                'code' => 'agreement_required',
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'code' => 'eligible',
        ];
    }

    public function isEligible(Student $student, Internship $internship): bool
    {
        return $this->evaluate($student, $internship)['allowed'] === true;
    }

    /**
     * Apply the same eligibility rules used by application creation to a listing query.
     *
     * @return Builder<Internship>|null Null when the student has no resolvable school (empty result expected).
     */
    public function applyEligibleListingConstraints(Builder $query, Student $student): ?Builder
    {
        $school = $this->resolveStudentSchool($student);
        if (! $school) {
            return null;
        }

        $partnerCompanyIds = $this->activePartnerCompanyIds($school);

        $query->where('status', 'active')
            ->where(function ($builder) use ($school, $partnerCompanyIds) {
                $builder->where(function ($schoolBuilder) use ($school) {
                    $schoolBuilder
                        ->where(function ($typedSchool) use ($school) {
                            $typedSchool
                                ->where('host_type', 'school')
                                ->where('school_id', $school->id);
                        })
                        ->orWhere(function ($fallbackSchool) use ($school) {
                            $fallbackSchool
                                ->whereNull('host_type')
                                ->where('school_id', $school->id);
                        });
                });

                if ($partnerCompanyIds->isNotEmpty()) {
                    $builder->orWhere(function ($companyBuilder) use ($partnerCompanyIds) {
                        $companyBuilder
                            ->where(function ($companyHostBuilder) {
                                $companyHostBuilder
                                    ->whereIn('host_type', ['company', 'municipal_hall'])
                                    ->orWhere(function ($fallbackCompanyBuilder) {
                                        $fallbackCompanyBuilder
                                            ->whereNull('host_type')
                                            ->whereNotNull('company_id');
                                    });
                            })
                            ->whereIn('company_id', $partnerCompanyIds);
                    });
                }
            });

        return $query;
    }

    /**
     * @return Collection<int, int>
     */
    public function activePartnerCompanyUserIds(School $school): Collection
    {
        if (! $school->user_id && ! $school->organization_id) {
            return collect();
        }

        return $this->studentVisibleAgreementsQuery()
            ->where(function (Builder $query) use ($school) {
                if ($school->user_id) {
                    $query->where('school_user_id', $school->user_id);
                }
                if ($school->organization_id) {
                    $method = $school->user_id ? 'orWhere' : 'where';
                    $query->{$method}(function (Builder $orgs) use ($school) {
                        $orgs->where('requester_organization_id', $school->organization_id)
                            ->orWhere('partner_organization_id', $school->organization_id);
                    });
                }
            })
            ->pluck('company_user_id')
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Whether a student may discover a host organization (company or municipal hall).
     */
    public function studentCanDiscoverHostOrganization(Student $student, int $organizationId): bool
    {
        $school = $this->resolveStudentSchool($student);
        if (! $school) {
            return false;
        }

        $company = Company::query()->where('organization_id', $organizationId)->first();
        if (! $company) {
            return false;
        }

        return $this->hasActiveAgreementBetween($school, $company);
    }

    private function constrainAgreementPair(Builder $query, School $school, Company $company): void
    {
        $query->where(function (Builder $pair) use ($school, $company) {
            $matched = false;

            if ($school->user_id && $company->user_id) {
                $pair->where(function (Builder $users) use ($school, $company) {
                    $users
                        ->where('school_user_id', $school->user_id)
                        ->where('company_user_id', $company->user_id);
                });
                $matched = true;
            }

            if ($school->organization_id && $company->organization_id) {
                $method = $matched ? 'orWhere' : 'where';
                $pair->{$method}(function (Builder $orgs) use ($school, $company) {
                    $orgs
                        ->where('requester_organization_id', $school->organization_id)
                        ->where('partner_organization_id', $company->organization_id);
                })->orWhere(function (Builder $orgs) use ($school, $company) {
                    $orgs
                        ->where('requester_organization_id', $company->organization_id)
                        ->where('partner_organization_id', $school->organization_id);
                });
                $matched = true;
            }

            if (! $matched) {
                $pair->whereRaw('1 = 0');
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Grandfather existing org owners so the new email-OTP document gate does not lock them out.
        // Includes:
        // 1) organizations.owner_user_id for school/company/municipal_hall
        // 2) legacy schools.user_id / companies.user_id owners (isOrganizationOwner fallback)
        // Excludes students, staff-only memberships, admins (unless they also own an org).
        $ownerIds = DB::table('organizations')
            ->whereNotNull('owner_user_id')
            ->whereIn('type', ['school', 'company', 'municipal_hall'])
            ->pluck('owner_user_id');

        $schoolProfileOwnerIds = DB::table('schools')
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $companyProfileOwnerIds = DB::table('companies')
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $ids = $ownerIds
            ->merge($schoolProfileOwnerIds)
            ->merge($companyProfileOwnerIds)
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereIn('id', $ids)
            ->whereIn('role', ['school', 'company', 'municipal_hall'])
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // NO-OP: do not clear email_verified_at — users may have verified via real OTP after deploy.
    }
};

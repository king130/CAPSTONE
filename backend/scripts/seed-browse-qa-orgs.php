<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$counts = [
    'company' => 105,
    'school' => 25,
    'municipal_hall' => 11,
];

DB::transaction(function () use ($counts) {
    // Remove prior QA browse seed rows only.
    $qaOrgs = Organization::query()
        ->where('name', 'like', 'QA Browse %')
        ->get();

    foreach ($qaOrgs as $org) {
        Company::query()->where('organization_id', $org->id)->delete();
        School::query()->where('organization_id', $org->id)->delete();
        User::query()->where('id', $org->owner_user_id)->delete();
        $org->delete();
    }

    $cities = ['Imus City', 'Bacoor City', 'Dasmarinas City', 'Naic', 'Indang', 'Tanza'];

    for ($i = 1; $i <= $counts['company']; $i++) {
        $name = sprintf('QA Browse Company %03d', $i);
        $email = sprintf('qa.browse.company.%03d@test.local', $i);
        $user = User::query()->create([
            'name' => $name.' Owner',
            'email' => $email,
            'password' => 'TestPass1',
            'role' => 'company',
            'is_active' => true,
            'is_temporary' => false,
            'must_change_password' => false,
            'profile_setup_complete' => true,
        ]);
        $org = Organization::query()->create([
            'name' => $name,
            'type' => 'company',
            'owner_user_id' => $user->id,
            'is_active' => true,
            'city' => $cities[$i % count($cities)],
            'industry' => $i % 2 === 0 ? 'Software' : 'Healthcare',
            'address' => 'QA Street '.$i.', Cavite',
            'tagline' => 'QA company '.$i,
        ]);
        Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'company_name' => $name,
            'industry_type' => $org->industry,
            'company_address' => $org->address,
            'company_email' => $email,
            'verification_status' => 'approved',
        ]);
    }

    for ($i = 1; $i <= $counts['school']; $i++) {
        $name = sprintf('QA Browse School %03d', $i);
        $email = sprintf('qa.browse.school.%03d@test.local', $i);
        $user = User::query()->create([
            'name' => $name.' Admin',
            'email' => $email,
            'password' => 'TestPass1',
            'role' => 'school',
            'is_active' => true,
            'is_temporary' => false,
            'must_change_password' => false,
            'profile_setup_complete' => true,
        ]);
        $org = Organization::query()->create([
            'name' => $name,
            'type' => 'school',
            'owner_user_id' => $user->id,
            'is_active' => true,
            'city' => $cities[$i % count($cities)],
            'address' => 'QA Campus '.$i.', Cavite',
            'tagline' => 'QA school '.$i,
        ]);
        School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'institution_name' => $name,
            'school_address' => $org->address,
            'official_school_email' => $email,
            'verification_status' => 'approved',
            'subscription_code' => 'QA-SCH-'.Str::upper(Str::random(4)),
        ]);
    }

    for ($i = 1; $i <= $counts['municipal_hall']; $i++) {
        $name = sprintf('QA Browse Hall %03d', $i);
        $email = sprintf('qa.browse.hall.%03d@test.local', $i);
        $user = User::query()->create([
            'name' => $name.' Admin',
            'email' => $email,
            'password' => 'TestPass1',
            'role' => 'municipal_hall',
            'is_active' => true,
            'is_temporary' => false,
            'must_change_password' => false,
            'profile_setup_complete' => true,
        ]);
        $org = Organization::query()->create([
            'name' => $name,
            'type' => 'municipal_hall',
            'owner_user_id' => $user->id,
            'is_active' => true,
            'city' => $cities[$i % count($cities)],
            'industry' => 'Local Government',
            'address' => 'QA Hall '.$i.', Cavite',
            'tagline' => 'QA hall '.$i,
        ]);
        Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'company_name' => $name,
            'company_type' => 'municipal_hall',
            'lgu_type' => $i % 2 === 0 ? 'city' : 'municipality',
            'industry_type' => 'Local Government',
            'company_address' => $org->address,
            'company_email' => $email,
            'verification_status' => 'approved',
        ]);
    }
});

echo json_encode([
    'seeded' => $counts,
    'renameTarget' => 'QA Browse Company 001',
], JSON_PRETTY_PRINT), PHP_EOL;

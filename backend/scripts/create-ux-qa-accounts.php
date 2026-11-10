<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OrganizationMembership;
use App\Models\User;

$suffix = bin2hex(random_bytes(3));
$pw = 'TestPass1';

$demoCompany = User::where('email', 'company@techcorp.com')->first();
$demoSchool = User::where('email', 'school@cvsu.edu.ph')->first();
$demoStudent = User::where('email', 'student@cvsu.edu.ph')->first();
$demoAdmin = User::where('email', 'admin@ojtpath.com')->first();

function cloneUser(?User $src, string $email, string $name, string $pw): ?string
{
    if (! $src) {
        return null;
    }

    $u = $src->replicate(['email', 'name', 'password', 'remember_token']);
    $u->email = $email;
    $u->name = $name;
    $u->password = $pw;
    $u->must_change_password = false;
    $u->is_temporary = false;
    $u->profile_setup_complete = true;
    $u->save();

    foreach (OrganizationMembership::where('user_id', $src->id)->get() as $m) {
        $nm = $m->replicate(['user_id']);
        $nm->user_id = $u->id;
        $nm->save();
    }

    if ($src->student) {
        $st = $src->student->replicate(['user_id', 'intern_code', 'student_id_number']);
        $st->user_id = $u->id;
        $st->intern_code = strtoupper(substr(md5($email), 0, 8)).'-INT';
        $st->student_id_number = 'UX-'.strtoupper(substr(md5($email), 0, 6));
        $st->save();
    }

    return $u->email;
}

$emails = [
    'company' => cloneUser($demoCompany, "ux.company.$suffix@test.local", "UX Company $suffix", $pw),
    'school' => cloneUser($demoSchool, "ux.school.$suffix@test.local", "UX School $suffix", $pw),
    'student' => cloneUser($demoStudent, "ux.student.$suffix@test.local", "UX Student $suffix", $pw),
    'admin' => cloneUser($demoAdmin, "ux.admin.$suffix@test.local", "UX Admin $suffix", $pw),
];

$path = storage_path('app/ux-qa-accounts.json');
file_put_contents($path, json_encode(['password' => $pw, 'accounts' => $emails], JSON_PRETTY_PRINT));
echo json_encode(['password' => $pw, 'accounts' => $emails, 'path' => $path], JSON_PRETTY_PRINT), PHP_EOL;

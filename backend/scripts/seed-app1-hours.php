<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\OjtLog;

$a = Application::find(1);
if (! $a) {
    fwrite(STDERR, "Application 1 missing\n");
    exit(1);
}

OjtLog::query()->create([
    'application_id' => $a->id,
    'student_id' => $a->student_id,
    'internship_id' => $a->internship_id,
    'organization_id' => $a->organization_id,
    'hours' => 500,
    'status' => 'approved',
    'log_date' => now()->toDateString(),
    'tasks' => 'UX QA bulk certificate seed hours for app 1',
    'time_in' => '08:00',
    'time_out' => '17:00',
    'reviewed_at' => now(),
]);

echo "seeded application {$a->id}\n";

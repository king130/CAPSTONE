<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\OjtDtrSubmission;
use App\Models\OjtLog;

$application = Application::query()
    ->where('status', 'accepted')
    ->orderByDesc('updated_at')
    ->first();

if (! $application) {
    fwrite(STDERR, "No accepted application found\n");
    exit(1);
}

$required = 500;
$existing = (float) OjtDtrSubmission::query()
    ->where('application_id', $application->id)
    ->where('status', OjtDtrSubmission::STATUS_APPROVED)
    ->sum('submitted_hours');

$logHours = (float) OjtLog::query()
    ->where('application_id', $application->id)
    ->where('status', 'approved')
    ->sum('hours');

$needed = max(0, $required - max($existing, $logHours));
if ($needed > 0) {
    $studentUserId = $application->student?->user_id;
    OjtLog::query()->create([
        'application_id' => $application->id,
        'student_id' => $application->student_id,
        'internship_id' => $application->internship_id,
        'organization_id' => $application->organization_id,
        'hours' => $needed,
        'status' => 'approved',
        'log_date' => now()->toDateString(),
        'tasks' => 'UX QA bulk certificate seed hours',
        'time_in' => '08:00',
        'time_out' => '17:00',
        'reviewed_by_user_id' => $studentUserId,
        'reviewed_at' => now(),
    ]);
}

echo json_encode([
    'applicationId' => $application->id,
    'studentId' => $application->student_id,
    'seededHours' => $needed,
    'totalApproved' => $existing + $needed,
], JSON_PRETTY_PRINT), PHP_EOL;

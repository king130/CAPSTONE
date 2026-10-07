<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Certificate;
use App\Models\OjtLog;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        $query = Certificate::query()
            ->with([
                'student:id,user_id,school_name',
                'student.user:id,name,email',
                'application:id,internship_title,status,company_id',
                'organization:id,name',
                'issuedBy:id,name,email',
            ])
            ->orderByDesc('issued_at');

        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;

        if ($appRole === 'student' && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($appRole === 'company' && $company) {
            if (! $this->permissions->userCanAny($user, ['org.approve_certificates', 'org.view_reports', 'org.reports_view'])) {
                return response()->json(['message' => 'Missing permission to view certificates.'], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
        } elseif ($appRole === 'school' && $school) {
            if (! $this->permissions->userCanAny($user, ['org.approve_certificates', 'org.view_reports', 'org.reports_view'])) {
                return response()->json(['message' => 'Missing permission to view certificates.'], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('student', function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Certificate $certificate) => $this->serialize($certificate)),
        ]);
    }

    public function generate(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCan($user, 'org.approve_certificates')) {
            return response()->json(['message' => 'Missing permission to generate certificates.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
        ]);

        $application = Application::query()
            ->with(['student.user', 'company', 'internship'])
            ->findOrFail($data['application_id']);

        if (! $this->canManageApplication($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ($application->status !== 'accepted') {
            return response()->json(['message' => 'Certificate requires an accepted application.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $requiredHours = OjtLogController::requiredHoursForOrg($user->activeOrganization());
        $approvedHours = (float) OjtLog::query()
            ->where('student_id', $application->student_id)
            ->where('application_id', $application->id)
            ->where('status', 'approved')
            ->sum('hours');

        if ($approvedHours < $requiredHours) {
            return response()->json([
                'message' => "Student needs at least {$requiredHours} approved OJT hours (currently {$approvedHours}).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = Certificate::query()
            ->where('application_id', $application->id)
            ->where('student_id', $application->student_id)
            ->first();

        if ($existing) {
            return response()->json(['data' => $this->serialize($existing->load([
                'student:id,user_id,school_name',
                'student.user:id,name,email',
                'application:id,internship_title,status,company_id',
                'organization:id,name',
                'issuedBy:id,name,email',
            ]))]);
        }

        $organization = $user->activeOrganization();
        $certificateNumber = Certificate::generateCertificateNumber((int) $application->student_id);
        $studentName = $application->student?->user?->name ?? $application->student_name ?? 'Student';
        $hostName = $application->company?->company_name
            ?? $application->internship?->host_name
            ?? $application->internship?->company_name
            ?? 'Host Organization';
        $internshipTitle = $application->internship_title ?? $application->internship?->title ?? 'Internship';

        $html = $this->buildCertificateHtml(
            $certificateNumber,
            $studentName,
            $internshipTitle,
            $hostName,
            $approvedHours,
            $requiredHours
        );

        $relativePath = "certificates/{$certificateNumber}.html";
        Storage::disk('local')->put("private/{$relativePath}", $html);

        $certificate = Certificate::query()->create([
            'student_id' => $application->student_id,
            'application_id' => $application->id,
            'organization_id' => $organization?->id,
            'certificate_number' => $certificateNumber,
            'issued_by_user_id' => $user->id,
            'issued_at' => now(),
            'file_path' => "private/{$relativePath}",
            'metadata' => [
                'approved_hours' => $approvedHours,
                'required_hours' => $requiredHours,
                'format' => 'html',
                'student_name' => $studentName,
                'internship_title' => $internshipTitle,
                'host_name' => $hostName,
            ],
        ]);

        $certificate->load([
            'student:id,user_id,school_name',
            'student.user:id,name,email',
            'application:id,internship_title,status,company_id',
            'organization:id,name',
            'issuedBy:id,name,email',
        ]);

        return response()->json(['data' => $this->serialize($certificate)], Response::HTTP_CREATED);
    }

    public function download(Request $request, Certificate $certificate)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        $certificate->loadMissing(['student', 'application']);

        $allowed = $appRole === 'admin'
            || ($appRole === 'student' && $user->student && (int) $certificate->student_id === (int) $user->student->id)
            || ($appRole !== 'student' && $this->permissions->userCanAny($user, ['org.approve_certificates', 'org.view_reports', 'org.reports_view']) && $this->canManageApplication($user, $certificate->application));

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (! $certificate->file_path || ! Storage::disk('local')->exists($certificate->file_path)) {
            return response()->json(['message' => 'Certificate file not found.'], Response::HTTP_NOT_FOUND);
        }

        $contents = Storage::disk('local')->get($certificate->file_path);
        $filename = ($certificate->certificate_number ?: 'certificate').'.html';

        return response($contents, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function canManageApplication($user, ?Application $application): bool
    {
        if (! $application) {
            return false;
        }

        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }

        $company = $user->organizationCompany() ?? $user->company;
        if ($appRole === 'company' && $company) {
            return (int) $application->company_id === (int) $company->id;
        }

        $school = $user->organizationSchool() ?? $user->school;
        if ($appRole === 'school' && $school) {
            $application->loadMissing('student');
            if ($application->student && (int) $application->student->school_id === (int) $school->id) {
                return true;
            }
            if ($school->subscription_code && $application->student?->school_subscription_code === $school->subscription_code) {
                return true;
            }
        }

        return false;
    }

    private function buildCertificateHtml(
        string $certificateNumber,
        string $studentName,
        string $internshipTitle,
        string $hostName,
        float $approvedHours,
        int $requiredHours
    ): string {
        $issuedAt = now()->toFormattedDateString();
        $escapedName = e($studentName);
        $escapedTitle = e($internshipTitle);
        $escapedHost = e($hostName);
        $escapedNumber = e($certificateNumber);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Certificate {$escapedNumber}</title>
  <style>
    body { font-family: Georgia, serif; margin: 0; padding: 48px; background: #f8fafc; color: #0f172a; }
    .sheet { max-width: 800px; margin: 0 auto; background: #fff; border: 8px double #1e3a5f; padding: 56px 48px; text-align: center; }
    h1 { letter-spacing: 0.12em; font-size: 28px; margin: 0 0 8px; text-transform: uppercase; }
    .subtitle { color: #475569; margin-bottom: 32px; }
    .name { font-size: 36px; margin: 24px 0; border-bottom: 1px solid #cbd5e1; display: inline-block; padding: 0 16px 8px; }
    .meta { margin-top: 40px; font-size: 14px; color: #64748b; }
  </style>
</head>
<body>
  <div class="sheet">
    <h1>Certificate of Completion</h1>
    <p class="subtitle">This certifies that</p>
    <div class="name">{$escapedName}</div>
    <p>has successfully completed the internship program</p>
    <p><strong>{$escapedTitle}</strong></p>
    <p>at <strong>{$escapedHost}</strong></p>
    <p>with {$approvedHours} of {$requiredHours} required approved OJT hours.</p>
    <div class="meta">
      <div>Certificate No: {$escapedNumber}</div>
      <div>Issued: {$issuedAt}</div>
    </div>
  </div>
</body>
</html>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Certificate $certificate): array
    {
        return [
            'id' => (string) $certificate->id,
            'studentId' => (string) $certificate->student_id,
            'applicationId' => $certificate->application_id ? (string) $certificate->application_id : null,
            'organizationId' => $certificate->organization_id ? (string) $certificate->organization_id : null,
            'certificateNumber' => $certificate->certificate_number,
            'issuedByUserId' => (string) $certificate->issued_by_user_id,
            'issuedByName' => $certificate->issuedBy?->name ?? $certificate->issuedBy?->email,
            'issuedAt' => $certificate->issued_at?->toIso8601String(),
            'filePath' => $certificate->file_path,
            'metadata' => $certificate->metadata ?? [],
            'studentName' => $certificate->student?->user?->name ?? $certificate->metadata['student_name'] ?? null,
            'internshipTitle' => $certificate->application?->internship_title ?? $certificate->metadata['internship_title'] ?? null,
            'createdAt' => $certificate->created_at?->toIso8601String(),
        ];
    }
}

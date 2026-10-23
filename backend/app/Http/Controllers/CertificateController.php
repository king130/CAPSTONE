<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Certificate;
use App\Models\OjtDtrSubmission;
use App\Models\OjtLog;
use App\Services\PermissionGate;
use App\Services\RequiredOjtHoursAccessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    public function __construct(
        private readonly PermissionGate $permissions,
        private readonly RequiredOjtHoursAccessor $requiredOjtHours,
    ) {
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

        // Agreed hours-agreement wins via RequiredOjtHoursAccessor; else application snapshot; else org default.
        $requiredHours = (int) ($this->requiredOjtHours->forApplication($application)
            ?: OjtLogController::requiredHoursForOrg($user->activeOrganization()));

        $logHours = (float) OjtLog::query()
            ->where('student_id', $application->student_id)
            ->where('application_id', $application->id)
            ->where('status', 'approved')
            ->sum('hours');

        // DTR is the primary hours path for placements; keep ojt_logs for legacy entries.
        $dtrHours = (float) OjtDtrSubmission::query()
            ->where('application_id', $application->id)
            ->where('status', OjtDtrSubmission::STATUS_APPROVED)
            ->sum('submitted_hours');

        $approvedHours = max($logHours, $dtrHours);

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
        $issuedAt = now()->format('F j, Y');
        $escapedName = e($studentName);
        $escapedNumber = e($certificateNumber);
        // Internship/host/hours stay in certificate metadata; Canva visual uses brand copy only.
        unset($internshipTitle, $hostName, $approvedHours, $requiredHours);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Certificate {$escapedNumber}</title>
  <style>
    @page { size: landscape; margin: 0; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 24px;
      background: #dfe7f0;
      color: #0b2c4d;
      font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .sheet {
      position: relative;
      width: min(1100px, 100%);
      aspect-ratio: 11 / 8.5;
      margin: 0 auto;
      background: #ffffff;
      overflow: hidden;
      box-shadow: 0 16px 36px rgba(11, 44, 77, 0.16);
    }
    .corner {
      position: absolute;
      z-index: 0;
      width: 22%;
      height: 34%;
      pointer-events: none;
    }
    .corner--tl { top: 0; left: 0; }
    .corner--br { right: 0; bottom: 0; transform: rotate(180deg); }
    .corner svg { display: block; width: 100%; height: 100%; }
    .content {
      position: relative;
      z-index: 2;
      height: 100%;
      padding: 9% 14% 8%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      text-align: center;
    }
    .title {
      position: relative;
      z-index: 3;
      margin: 0 auto;
      max-width: 78%;
      font-size: clamp(26px, 3.6vw, 44px);
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #0b2c4d;
      line-height: 1.15;
    }
    .diamond {
      width: 12px;
      height: 12px;
      margin: 16px auto 20px;
      background: #c9a227;
      transform: rotate(45deg);
    }
    .presented {
      margin: 0;
      color: #0b2c4d;
      font-size: clamp(15px, 1.55vw, 19px);
      font-weight: 500;
    }
    .name {
      margin: 14px 0 12px;
      font-size: clamp(36px, 5.2vw, 58px);
      font-weight: 800;
      color: #1a6b7a;
      line-height: 1.1;
      max-width: 92%;
      text-transform: uppercase;
    }
    .body-copy {
      margin: 0;
      color: #0b2c4d;
      font-size: clamp(14px, 1.5vw, 18px);
      max-width: 68%;
      line-height: 1.45;
      font-weight: 500;
    }
    .ribbon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-top: 22px;
      min-width: min(380px, 62%);
      padding: 11px 48px;
      background: #0b2c4d;
      color: #fff;
      font-weight: 700;
      font-size: clamp(16px, 1.9vw, 21px);
      letter-spacing: 0.03em;
      border: 2px solid #c9a227;
      clip-path: polygon(22px 0, calc(100% - 22px) 0, 100% 50%, calc(100% - 22px) 100%, 22px 100%, 0 50%);
    }
    .date {
      margin: 18px 0 0;
      color: #0b2c4d;
      font-size: clamp(14px, 1.4vw, 17px);
      font-weight: 500;
    }
    .signatures {
      width: min(640px, 78%);
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 80px;
      margin-top: 36px;
    }
    .sig { text-align: center; }
    .sig-line {
      border-top: 1.5px solid #0b2c4d;
      margin: 0 8px 10px;
    }
    .sig-label {
      margin: 0;
      font-size: 14px;
      font-weight: 600;
      color: #0b2c4d;
    }
    .meta {
      position: absolute;
      left: 50%;
      bottom: 14px;
      transform: translateX(-50%);
      font-size: 10px;
      color: #94a3b8;
    }
    @media print {
      body { background: #fff; padding: 0; }
      .sheet { box-shadow: none; width: 100%; height: 100vh; aspect-ratio: auto; }
      .meta { display: none; }
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="corner corner--tl" aria-hidden="true">
      <svg viewBox="0 0 360 420" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
        <polygon points="0,0 250,0 0,280" fill="#0b2c4d"/>
        <polygon points="0,0 168,0 0,210" fill="#1a6b7a"/>
        <polygon points="0,0 250,0 0,280" fill="none" stroke="#c9a227" stroke-width="5"/>
      </svg>
    </div>
    <div class="corner corner--br" aria-hidden="true">
      <svg viewBox="0 0 360 420" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
        <polygon points="0,0 250,0 0,280" fill="#0b2c4d"/>
        <polygon points="0,0 168,0 0,210" fill="#1a6b7a"/>
        <polygon points="0,0 250,0 0,280" fill="none" stroke="#c9a227" stroke-width="5"/>
      </svg>
    </div>
    <div class="content">
      <div>
        <h1 class="title">Certificate of Completion</h1>
        <div class="diamond" aria-hidden="true"></div>
        <p class="presented">This certificate is presented to</p>
        <div class="name">{$escapedName}</div>
        <p class="body-copy">for successfully completing the OJT Intern Path internship program</p>
        <div class="ribbon">OJT Intern Path</div>
        <p class="date">Date: {$issuedAt}</p>
      </div>
      <div class="signatures">
        <div class="sig">
          <div class="sig-line"></div>
          <p class="sig-label">Project Adviser</p>
        </div>
        <div class="sig">
          <div class="sig-line"></div>
          <p class="sig-label">Program Chair</p>
        </div>
      </div>
    </div>
    <div class="meta">Certificate No: {$escapedNumber}</div>
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

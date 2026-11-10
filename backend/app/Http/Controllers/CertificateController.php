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
                $q->where(function ($inner) use ($school) {
                    $inner->where('school_id', $school->id);
                    if ($school->subscription_code) {
                        $inner->orWhere('school_subscription_code', $school->subscription_code);
                    }
                });
            });
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Certificate $certificate) => $this->serialize($certificate)),
        ]);
    }

    public function eligible(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCan($user, 'org.approve_certificates')) {
            return response()->json(['message' => 'Missing permission to view certificate eligibility.'], Response::HTTP_FORBIDDEN);
        }

        $school = $user->organizationSchool() ?? $user->school;
        if ($appRole === 'school' && ! $school) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $query = Application::query()
            ->with(['student.user', 'company', 'internship'])
            ->whereIn('status', ['accepted', 'placement_ended'])
            ->orderByDesc('updated_at');

        if ($appRole === 'school' && $school) {
            $query->whereHas('student', function ($studentQuery) use ($school) {
                $studentQuery->where(function ($inner) use ($school) {
                    $inner->where('school_id', $school->id);
                    if ($school->subscription_code) {
                        $inner->orWhere('school_subscription_code', $school->subscription_code);
                    }
                });
            });
        } elseif ($appRole === 'company') {
            $company = $user->organizationCompany() ?? $user->company;
            if (! $company) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }
            $query->where('company_id', $company->id);
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        $rows = $query->get()->map(function (Application $application) use ($user) {
            $hours = $this->hoursForApplication($application, $user);
            $existing = Certificate::query()
                ->where('application_id', $application->id)
                ->where('student_id', $application->student_id)
                ->first();
            $eligible = $hours['approved'] >= $hours['required'] && ! $existing;

            return [
                'applicationId' => (string) $application->id,
                'studentId' => (string) $application->student_id,
                'studentName' => $application->student?->user?->name ?? $application->student_name ?? 'Student',
                'internshipTitle' => $application->internship_title ?? $application->internship?->title ?? 'Internship',
                'hostName' => $application->company?->company_name
                    ?? $application->internship?->host_name
                    ?? $application->internship?->company_name
                    ?? 'Host Organization',
                'approvedHours' => $hours['approved'],
                'requiredHours' => $hours['required'],
                'eligible' => $eligible,
                'alreadyIssued' => (bool) $existing,
                'certificateId' => $existing ? (string) $existing->id : null,
                'skipReason' => $existing
                    ? 'Certificate already issued.'
                    : ($hours['approved'] < $hours['required']
                        ? "Needs {$hours['required']} approved hours (currently {$hours['approved']})."
                        : null),
            ];
        })->values();

        return response()->json(['data' => $rows]);
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

        $result = $this->issueForApplication($user, (int) $data['application_id']);
        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response()->json([
            'data' => $result['data'],
            'created' => $result['created'],
        ], $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function bulkGenerate(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCan($user, 'org.approve_certificates')) {
            return response()->json(['message' => 'Missing permission to generate certificates.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'application_ids' => ['required', 'array', 'min:1', 'max:100'],
            'application_ids.*' => ['integer', 'exists:applications,id'],
        ]);

        $issued = [];
        $skipped = [];
        $failed = [];

        foreach (array_values(array_unique($data['application_ids'])) as $applicationId) {
            $result = $this->issueForApplication($user, (int) $applicationId);
            if (isset($result['error'])) {
                if (($result['status'] ?? 422) === Response::HTTP_UNPROCESSABLE_ENTITY || ($result['status'] ?? 0) === Response::HTTP_FORBIDDEN) {
                    $skipped[] = [
                        'applicationId' => (string) $applicationId,
                        'reason' => $result['error'],
                    ];
                } else {
                    $failed[] = [
                        'applicationId' => (string) $applicationId,
                        'reason' => $result['error'],
                    ];
                }
                continue;
            }

            if (! empty($result['created'])) {
                $issued[] = $result['data'];
            } else {
                $skipped[] = [
                    'applicationId' => (string) $applicationId,
                    'reason' => 'Certificate already issued.',
                    'certificateId' => $result['data']['id'] ?? null,
                ];
            }
        }

        return response()->json([
            'data' => [
                'issued' => $issued,
                'skipped' => $skipped,
                'failed' => $failed,
            ],
        ]);
    }

    public function download(Request $request, Certificate $certificate)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        $certificate->loadMissing(['student.user', 'application', 'application.company', 'application.internship']);

        $allowed = $appRole === 'admin'
            || ($appRole === 'student' && $user->student && (int) $certificate->student_id === (int) $user->student->id)
            || ($appRole !== 'student' && $this->permissions->userCanAny($user, ['org.approve_certificates', 'org.view_reports', 'org.reports_view']) && $this->canManageApplication($user, $certificate->application));

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        // Always render the current printable HTML template from certificate data so
        // view/print stay consistent even when older stored files used a prior layout.
        $contents = $this->renderCertificateHtml($certificate);
        if ($certificate->file_path) {
            Storage::disk('local')->put($certificate->file_path, $contents);
        }

        $filename = ($certificate->certificate_number ?: 'certificate').'.html';

        return response($contents, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{approved: float, required: int}
     */
    private function hoursForApplication(Application $application, $user): array
    {
        $requiredHours = (int) ($this->requiredOjtHours->forApplication($application)
            ?: OjtLogController::requiredHoursForOrg($user->activeOrganization()));

        $logHours = (float) OjtLog::query()
            ->where('student_id', $application->student_id)
            ->where('application_id', $application->id)
            ->where('status', 'approved')
            ->sum('hours');

        $dtrHours = (float) OjtDtrSubmission::query()
            ->where('application_id', $application->id)
            ->where('status', OjtDtrSubmission::STATUS_APPROVED)
            ->sum('submitted_hours');

        return [
            'approved' => max($logHours, $dtrHours),
            'required' => $requiredHours,
        ];
    }

    /**
     * @return array{data?: array<string, mixed>, created?: bool, error?: string, status?: int}
     */
    private function issueForApplication($user, int $applicationId): array
    {
        $application = Application::query()
            ->with(['student.user', 'company', 'internship'])
            ->find($applicationId);

        if (! $application) {
            return ['error' => 'Application not found.', 'status' => Response::HTTP_NOT_FOUND];
        }

        if (! $this->canManageApplication($user, $application)) {
            return ['error' => 'Forbidden.', 'status' => Response::HTTP_FORBIDDEN];
        }

        if (! in_array((string) $application->status, ['accepted', 'placement_ended'], true)) {
            return [
                'error' => 'Certificate requires an accepted or completed placement.',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            ];
        }

        $hours = $this->hoursForApplication($application, $user);
        if ($hours['approved'] < $hours['required']) {
            return [
                'error' => "Student needs at least {$hours['required']} approved OJT hours (currently {$hours['approved']}).",
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            ];
        }

        $existing = Certificate::query()
            ->where('application_id', $application->id)
            ->where('student_id', $application->student_id)
            ->first();

        if ($existing) {
            return [
                'data' => $this->serialize($existing->load([
                    'student:id,user_id,school_name',
                    'student.user:id,name,email',
                    'application:id,internship_title,status,company_id',
                    'organization:id,name',
                    'issuedBy:id,name,email',
                ])),
                'created' => false,
            ];
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
            $hours['approved'],
            $hours['required']
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
                'approved_hours' => $hours['approved'],
                'required_hours' => $hours['required'],
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

        return [
            'data' => $this->serialize($certificate),
            'created' => true,
        ];
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

    private function renderCertificateHtml(Certificate $certificate): string
    {
        $meta = is_array($certificate->metadata) ? $certificate->metadata : [];
        $studentName = (string) (
            $certificate->student?->user?->name
            ?? $meta['student_name']
            ?? 'Student'
        );
        $internshipTitle = (string) (
            $certificate->application?->internship_title
            ?? $meta['internship_title']
            ?? 'Internship'
        );
        $hostName = (string) (
            $meta['host_name']
            ?? $certificate->application?->company?->company_name
            ?? $certificate->application?->internship?->host_name
            ?? $certificate->application?->internship?->company_name
            ?? 'Host Organization'
        );
        $approvedHours = (float) ($meta['approved_hours'] ?? 0);
        $requiredHours = (int) ($meta['required_hours'] ?? 0);
        $issuedAt = $certificate->issued_at?->format('F j, Y') ?? now()->format('F j, Y');

        return $this->buildCertificateHtml(
            (string) ($certificate->certificate_number ?: 'certificate'),
            $studentName,
            $internshipTitle,
            $hostName,
            $approvedHours,
            $requiredHours,
            $issuedAt
        );
    }

    private function buildCertificateHtml(
        string $certificateNumber,
        string $studentName,
        string $internshipTitle,
        string $hostName,
        float $approvedHours,
        int $requiredHours,
        ?string $issuedAt = null
    ): string {
        $issuedAt = $issuedAt ?: now()->format('F j, Y');
        $escapedName = e($studentName);
        $escapedNumber = e($certificateNumber);
        $escapedTitle = e($internshipTitle);
        $escapedHost = e($hostName);
        $hoursLabel = $requiredHours > 0
            ? e(rtrim(rtrim(number_format($approvedHours, 1, '.', ''), '0'), '.').' / '.$requiredHours.' hours')
            : e(rtrim(rtrim(number_format($approvedHours, 1, '.', ''), '0'), '.').' hours');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Certificate {$escapedNumber}</title>
  <style>
    @page { size: landscape; margin: 0.4in; }
    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      padding: 0;
      background: #f4f7fb;
      color: #1e3a5f;
      font-family: Georgia, "Times New Roman", Times, serif;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .toolbar {
      position: sticky;
      top: 0;
      z-index: 5;
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      padding: 12px 16px;
      background: rgba(255,255,255,0.96);
      border-bottom: 1px solid #d7e0ea;
    }
    .toolbar button {
      font-family: Georgia, "Times New Roman", Times, serif;
      font-size: 14px;
      padding: 8px 14px;
      border-radius: 6px;
      border: 1px solid #1e3a5f;
      background: #1e3a5f;
      color: #fff;
      cursor: pointer;
    }
    .toolbar button.secondary {
      background: #fff;
      color: #1e3a5f;
    }
    .page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .sheet {
      width: min(1050px, 100%);
      aspect-ratio: 11 / 8.5;
      background: #fff;
      border: 3px double #1e3a5f;
      outline: 1px solid #1e3a5f;
      outline-offset: 8px;
      padding: 48px 56px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      text-align: center;
      box-shadow: 0 12px 28px rgba(30, 58, 95, 0.12);
    }
    .brand {
      margin: 0;
      font-size: 14px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: #1e3a5f;
    }
    .title {
      margin: 18px 0 0;
      font-size: clamp(28px, 4vw, 42px);
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #1e3a5f;
    }
    .rule {
      width: 120px;
      height: 2px;
      margin: 18px auto;
      background: #1e3a5f;
    }
    .presented {
      margin: 0;
      font-size: clamp(15px, 1.6vw, 18px);
    }
    .name {
      margin: 14px 0 10px;
      font-size: clamp(34px, 5vw, 52px);
      font-weight: 700;
      color: #1e3a5f;
      line-height: 1.15;
      max-width: 92%;
    }
    .body-copy {
      margin: 0 auto;
      max-width: 70%;
      font-size: clamp(14px, 1.5vw, 18px);
      line-height: 1.5;
    }
    .meta-line {
      margin: 10px 0 0;
      font-size: clamp(13px, 1.3vw, 16px);
      color: #334155;
    }
    .date {
      margin: 18px 0 0;
      font-size: clamp(14px, 1.4vw, 17px);
    }
    .signatures {
      width: min(620px, 84%);
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 64px;
      margin-top: 28px;
    }
    .sig-line {
      border-top: 1.5px solid #1e3a5f;
      margin: 0 8px 10px;
    }
    .sig-label {
      margin: 0;
      font-size: 14px;
      font-weight: 600;
    }
    .footer-meta {
      margin-top: 18px;
      font-size: 11px;
      color: #64748b;
    }
    @media print {
      html, body { background: #fff; }
      .toolbar { display: none !important; }
      .page {
        min-height: auto;
        padding: 0;
        align-items: stretch;
        justify-content: stretch;
      }
      .sheet {
        width: 100%;
        height: calc(100vh - 0.8in);
        max-height: 100%;
        aspect-ratio: auto;
        box-shadow: none;
        outline-offset: 6px;
        page-break-inside: avoid;
        break-inside: avoid;
      }
    }
  </style>
</head>
<body>
  <div class="toolbar no-print">
    <button type="button" class="secondary" onclick="window.close()">Close</button>
    <button type="button" onclick="window.print()">Print Certificate</button>
  </div>
  <div class="page">
    <div class="sheet">
      <div>
        <p class="brand">OJT Intern Path</p>
        <h1 class="title">Certificate of Completion</h1>
        <div class="rule" aria-hidden="true"></div>
        <p class="presented">This certificate is presented to</p>
        <div class="name">{$escapedName}</div>
        <p class="body-copy">
          for successfully completing the internship
          <strong>{$escapedTitle}</strong>
          at
          <strong>{$escapedHost}</strong>.
        </p>
        <p class="meta-line">Approved OJT hours: {$hoursLabel}</p>
        <p class="date">Issued {$issuedAt}</p>
      </div>
      <div>
        <div class="signatures">
          <div>
            <div class="sig-line"></div>
            <p class="sig-label">Project Adviser</p>
          </div>
          <div>
            <div class="sig-line"></div>
            <p class="sig-label">Program Chair</p>
          </div>
        </div>
        <div class="footer-meta">Certificate No: {$escapedNumber}</div>
      </div>
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
        $metadata = $certificate->metadata;
        if (! is_array($metadata)) {
            $metadata = [];
        }

        return [
            'id' => (string) $certificate->id,
            'studentId' => (string) $certificate->student_id,
            'applicationId' => $certificate->application_id ? (string) $certificate->application_id : null,
            'organizationId' => $certificate->organization_id ? (string) $certificate->organization_id : null,
            'certificateNumber' => $certificate->certificate_number,
            'issuedByUserId' => $certificate->issued_by_user_id !== null ? (string) $certificate->issued_by_user_id : null,
            'issuedByName' => $certificate->issuedBy?->name ?? $certificate->issuedBy?->email,
            'issuedAt' => $certificate->issued_at?->toIso8601String(),
            'filePath' => $certificate->file_path,
            'metadata' => $metadata,
            'studentName' => $certificate->student?->user?->name ?? $metadata['student_name'] ?? null,
            'internshipTitle' => $certificate->application?->internship_title ?? $metadata['internship_title'] ?? null,
            'createdAt' => $certificate->created_at?->toIso8601String(),
        ];
    }
}

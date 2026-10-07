<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractTypeController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\KpiReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OjtLogController;
use App\Http\Controllers\OrganizationAccessController;
use App\Http\Controllers\OrganizationMediaController;
use App\Http\Controllers\OrganizationProfileController;
use App\Http\Controllers\OrganizationPublicController;
use App\Http\Controllers\SavedInternshipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SchoolStudentController;
use App\Http\Controllers\SchoolReportController;
use App\Http\Controllers\StudentProgressController;
use App\Http\Controllers\TenantRbacController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('account-setup/validate', [AuthController::class, 'validateAccountSetupToken']);
    Route::post('account-setup/complete', [AuthController::class, 'completeAccountSetup']);
});

Route::middleware('auth.optional.sanctum')->group(function () {
    Route::get('internships', [InternshipController::class, 'index']);
    Route::get('internships/{internship}', [InternshipController::class, 'show'])->whereNumber('internship');
    Route::get('organizations/{organization}', [OrganizationPublicController::class, 'show'])->whereNumber('organization');
    Route::get('organizations/{organization}/internships', [OrganizationPublicController::class, 'internships'])->whereNumber('organization');
    Route::get('organizations/{organization}/media', [OrganizationMediaController::class, 'index'])->whereNumber('organization');
});
Route::get('profile/avatar/{user}', [ProfileAvatarController::class, 'show']);
Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    // Logout remains available so a disabled account can still revoke its current token.
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Authenticated application APIs reject disabled users (including existing tokens).
    Route::middleware('user.active')->group(function () {
    // Always available to authenticated active users (including pending org verification).
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/password', [AuthController::class, 'updatePassword']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/avatar', [ProfileAvatarController::class, 'store']);
    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents', [DocumentController::class, 'store']);
    Route::get('internships/eligible', [InternshipController::class, 'eligibleForStudent']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);

    // Student application create remains available without org verification gate.
    Route::post('applications', [ApplicationController::class, 'store']);

    // Student wishlist / saved internships (not org-gated).
    Route::get('saved-internships', [SavedInternshipController::class, 'index']);
    Route::post('saved-internships', [SavedInternshipController::class, 'store']);
    Route::delete('saved-internships/{internshipId}', [SavedInternshipController::class, 'destroy'])->whereNumber('internshipId');

    // Platform admin routes are not organization-gated.
    Route::get('admin/users', [AdminController::class, 'users']);
    Route::patch('admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::patch('admin/subscription-plans/{planSlug}', [SubscriptionPlanController::class, 'update']);

    // Consequential school/company organization actions require approved + active org.
    Route::middleware('org.verified')->group(function () {
        Route::post('subscriptions/checkout', [SubscriptionCheckoutController::class, 'create']);
        Route::post('subscriptions/verify', [SubscriptionCheckoutController::class, 'verify']);
        Route::post('subscriptions/free', [SubscriptionCheckoutController::class, 'switchToFree']);
        Route::post('subscriptions/cancel-pending', [SubscriptionCheckoutController::class, 'cancelPending']);

        Route::get('school-students', [SchoolStudentController::class, 'index']);
        Route::get('school-students/export', [SchoolStudentController::class, 'export']);
        Route::post('school-students', [SchoolStudentController::class, 'store']);
        Route::post('school-students/{student}/resend-setup-link', [SchoolStudentController::class, 'resendSetupLink']);
        Route::patch('school-students/{student}', [SchoolStudentController::class, 'update']);
        Route::delete('school-students/{student}', [SchoolStudentController::class, 'destroy']);
        Route::get('school-reports', [SchoolReportController::class, 'index']);
        Route::post('school-reports', [SchoolReportController::class, 'store']);

        Route::post('internships', [InternshipController::class, 'store']);
        Route::patch('internships/{internship}', [InternshipController::class, 'update']);
        Route::delete('internships/{internship}', [InternshipController::class, 'destroy']);

        Route::get('applications', [ApplicationController::class, 'index']);
        Route::patch('applications/{application}/status', [ApplicationController::class, 'updateStatus']);

        // Legacy /contracts routes (kept during rename transition)
        Route::get('contracts', [ContractController::class, 'index']);
        Route::get('contract-types', [ContractTypeController::class, 'index']);
        Route::get('contract-types/manage', [ContractTypeController::class, 'manage']);
        Route::post('contract-types', [ContractTypeController::class, 'store']);
        Route::patch('contract-types/{contractType}', [ContractTypeController::class, 'update']);
        Route::delete('contract-types/{contractType}', [ContractTypeController::class, 'destroy']);
        Route::post('contracts', [ContractController::class, 'store']);
        Route::patch('contracts/{contract}/accept', [ContractController::class, 'accept']);
        Route::patch('contracts/{contract}/reject', [ContractController::class, 'reject']);
        Route::patch('contracts/{contract}/cancel', [ContractController::class, 'cancel']);
        Route::patch('contracts/{contract}/amend', [ContractController::class, 'amend']);

        // Canonical /agreements routes
        Route::get('agreements', [ContractController::class, 'index']);
        Route::get('agreement-types', [ContractTypeController::class, 'index']);
        Route::get('agreement-types/manage', [ContractTypeController::class, 'manage']);
        Route::post('agreement-types', [ContractTypeController::class, 'store']);
        Route::patch('agreement-types/{contractType}', [ContractTypeController::class, 'update']);
        Route::delete('agreement-types/{contractType}', [ContractTypeController::class, 'destroy']);
        Route::post('agreements', [ContractController::class, 'store']);
        Route::patch('agreements/{contract}/accept', [ContractController::class, 'accept']);
        Route::patch('agreements/{contract}/reject', [ContractController::class, 'reject']);
        Route::patch('agreements/{contract}/cancel', [ContractController::class, 'cancel']);
        Route::patch('agreements/{contract}/amend', [ContractController::class, 'amend']);

        Route::get('interviews', [InterviewController::class, 'index']);
        Route::post('interviews', [InterviewController::class, 'store']);
        Route::patch('interviews/{interview}/confirm', [InterviewController::class, 'confirm']);
        Route::patch('interviews/{interview}/cancel', [InterviewController::class, 'cancel']);

        Route::get('ojt-logs/progress', [OjtLogController::class, 'progress']);
        Route::get('ojt-logs/required-hours', [OjtLogController::class, 'requiredHoursSetting']);
        Route::patch('ojt-logs/required-hours', [OjtLogController::class, 'updateRequiredHoursSetting']);
        Route::get('ojt-logs', [OjtLogController::class, 'index']);
        Route::post('ojt-logs', [OjtLogController::class, 'store']);
        Route::patch('ojt-logs/{ojtLog}/status', [OjtLogController::class, 'updateStatus']);
        Route::patch('ojt-logs/{ojtLog}', [OjtLogController::class, 'update']);
        Route::delete('ojt-logs/{ojtLog}', [OjtLogController::class, 'destroy']);

        Route::get('assessments', [AssessmentController::class, 'index']);
        Route::post('assessments', [AssessmentController::class, 'store']);

        Route::get('student-progress', [StudentProgressController::class, 'index']);

        Route::get('reports/kpi', [KpiReportController::class, 'index']);
        Route::get('reports/export', [ReportExportController::class, 'export']);

        Route::get('certificates', [CertificateController::class, 'index']);
        Route::post('certificates/generate', [CertificateController::class, 'generate']);
        Route::get('certificates/{certificate}/download', [CertificateController::class, 'download']);

        Route::get('directory/{role}', [DirectoryController::class, 'index']);
        Route::get('organization/access', [OrganizationAccessController::class, 'index']);
        Route::post('organization/access/members', [OrganizationAccessController::class, 'storeMember']);
        Route::patch('organization/access/members/{membership}', [OrganizationAccessController::class, 'updateMember']);

        Route::get('organization-profile', [OrganizationProfileController::class, 'show']);
        Route::patch('organization-profile', [OrganizationProfileController::class, 'update']);

        Route::post('organization-media', [OrganizationMediaController::class, 'store']);
        Route::patch('organization-media/{organizationMedia}', [OrganizationMediaController::class, 'update']);
        Route::delete('organization-media/{organizationMedia}', [OrganizationMediaController::class, 'destroy']);
        Route::get('rbac/tenants', [TenantRbacController::class, 'tenants']);
        Route::post('rbac/tenants', [TenantRbacController::class, 'storeTenant']);
        Route::get('rbac/tenants/{tenant}/roles', [TenantRbacController::class, 'index']);
        Route::post('rbac/tenants/{tenant}/members', [TenantRbacController::class, 'storeMember']);
        Route::post('rbac/tenants/{tenant}/roles', [TenantRbacController::class, 'storeRole']);
        Route::delete('rbac/tenants/{tenant}/roles/{role}', [TenantRbacController::class, 'destroyRole']);
        Route::put('rbac/tenants/{tenant}/roles/{role}/permissions', [TenantRbacController::class, 'syncPermissions']);
        Route::patch('rbac/tenants/{tenant}/members/{membership}', [TenantRbacController::class, 'updateMember']);

        Route::get('conversations', [ChatController::class, 'index']);
        Route::post('conversations', [ChatController::class, 'store']);
        Route::get('conversations/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
        Route::patch('conversations/{conversation}/read', [ChatController::class, 'markRead']);
    });
    });
});

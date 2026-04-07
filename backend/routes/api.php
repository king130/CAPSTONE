<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\ContractTypeController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationAccessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\SchoolStudentController;
use App\Http\Controllers\SchoolReportController;
use App\Http\Controllers\TenantRbacController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('account-setup/validate', [AuthController::class, 'validateAccountSetupToken']);
    Route::post('account-setup/complete', [AuthController::class, 'completeAccountSetup']);
});

Route::get('internships', [InternshipController::class, 'index']);
Route::get('profile/avatar/{user}', [ProfileAvatarController::class, 'show']);
Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/password', [AuthController::class, 'updatePassword']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/avatar', [ProfileAvatarController::class, 'store']);
    Route::post('subscriptions/checkout', [SubscriptionCheckoutController::class, 'create']);
    Route::post('subscriptions/verify', [SubscriptionCheckoutController::class, 'verify']);
    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents', [DocumentController::class, 'store']);
    Route::get('internships/eligible', [InternshipController::class, 'eligibleForStudent']);
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
    Route::post('applications', [ApplicationController::class, 'store']);
    Route::patch('applications/{application}/status', [ApplicationController::class, 'updateStatus']);

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

    Route::get('admin/users', [AdminController::class, 'users']);
    Route::patch('admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::patch('admin/subscription-plans/{planSlug}', [SubscriptionPlanController::class, 'update']);

    Route::get('directory/{role}', [DirectoryController::class, 'index']);
    Route::get('organization/access', [OrganizationAccessController::class, 'index']);
    Route::post('organization/access/members', [OrganizationAccessController::class, 'storeMember']);
    Route::patch('organization/access/members/{membership}', [OrganizationAccessController::class, 'updateMember']);
    Route::get('rbac/tenants', [TenantRbacController::class, 'tenants']);
    Route::post('rbac/tenants', [TenantRbacController::class, 'storeTenant']);
    Route::get('rbac/tenants/{tenant}/roles', [TenantRbacController::class, 'index']);
    Route::post('rbac/tenants/{tenant}/members', [TenantRbacController::class, 'storeMember']);
    Route::post('rbac/tenants/{tenant}/roles', [TenantRbacController::class, 'storeRole']);
    Route::delete('rbac/tenants/{tenant}/roles/{role}', [TenantRbacController::class, 'destroyRole']);
    Route::put('rbac/tenants/{tenant}/roles/{role}/permissions', [TenantRbacController::class, 'syncPermissions']);
    Route::patch('rbac/tenants/{tenant}/members/{membership}', [TenantRbacController::class, 'updateMember']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
});

Route::get('internships/{internship}', [InternshipController::class, 'show'])->whereNumber('internship');

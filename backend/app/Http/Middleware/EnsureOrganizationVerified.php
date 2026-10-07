<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts consequential organization actions for school/company users
 * until their organization is active and verification_status is approved.
 *
 * Platform admins and non-organization users (including students) are not gated.
 */
class EnsureOrganizationVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->isPlatformAdmin() || $user->effectiveAppRole() === 'admin') {
            return $next($request);
        }

        $appRole = $user->effectiveAppRole();
        if ($appRole !== 'school' && $appRole !== 'company') {
            return $next($request);
        }

        $organization = $user->activeOrganization();
        if (! $organization) {
            return $next($request);
        }

        if (! $organization->is_active) {
            return response()->json([
                'message' => 'Organization is inactive.',
                'code' => 'organization_inactive',
            ], Response::HTTP_FORBIDDEN);
        }

        $verificationStatus = null;
        if ($organization->type === 'school') {
            $verificationStatus = $user->organizationSchool()?->verification_status;
        } elseif ($organization->type === 'company') {
            $verificationStatus = $user->organizationCompany()?->verification_status;
        }

        $verificationStatus = strtolower(trim((string) ($verificationStatus ?? 'pending')));

        if ($verificationStatus === 'approved') {
            return $next($request);
        }

        if ($verificationStatus === 'rejected') {
            return response()->json([
                'message' => 'Organization verification was rejected.',
                'code' => 'organization_verification_rejected',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Organization verification is pending.',
            'code' => 'organization_verification_pending',
        ], Response::HTTP_FORBIDDEN);
    }
}

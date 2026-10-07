<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated requests when the user account has been disabled.
 * Must run after auth:sanctum so $request->user() is available.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && ! $user->is_active) {
            return response()->json([
                'message' => 'Account Disabled',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

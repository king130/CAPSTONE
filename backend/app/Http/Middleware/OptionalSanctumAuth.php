<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a Sanctum bearer token when present without requiring authentication.
 * Used on public catalog/detail routes so students can receive partnership flags.
 */
class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        if (! $request->bearerToken()) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();
        if ($user) {
            Auth::setUser($user);
            $request->setUserResolver(static fn () => $user);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks organization write actions when subscription access is expired.
 * Reads stay allowed. Platform admins and students (for their own flows) are not blocked.
 */
class EnsureSubscriptionWritable
{
    public function __construct(private readonly SubscriptionAccessService $access)
    {
    }

    /**
     * Paths that remain writable even when expired (billing + student DTR submit).
     *
     * @var list<string>
     */
    private array $allowWhenExpired = [
        'subscriptions/checkout',
        'subscriptions/verify',
        'subscriptions/cancel-pending',
        'ojt-dtrs', // student submit — method POST without /approve|/reject
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Reads stay allowed — only mutate methods are gated.
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->effectiveAppRole() === 'admin') {
            return $next($request);
        }

        // Students / teachers / interns are never locked out of their own data by org billing.
        $role = $user->effectiveAppRole();
        if (in_array($role, ['student', 'teacher', 'guest'], true) || $role === null) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }

        if ($this->isAllowedWhenExpired($request, $path)) {
            return $next($request);
        }

        $organization = $this->access->organizationForUser($user);
        if (! $organization) {
            // No org context — leave authorization to the controller (403), do not 402.
            return $next($request);
        }

        $state = $this->access->resolve($organization);

        if ($state['canWrite']) {
            return $next($request);
        }

        // Expired host DTR approve/reject specifically.
        if (! $state['canReviewDtr'] && preg_match('#^ojt-dtrs/\d+/(approve|reject)$#', $path)) {
            return response()->json([
                'message' => $state['message'],
                'code' => $state['code'],
                'subscriptionAccess' => $state,
            ], 402);
        }

        return response()->json([
            'message' => $state['message'].' Organization features are read-only until you upgrade.',
            'code' => $state['code'],
            'subscriptionAccess' => $state,
        ], 402);
    }

    private function isAllowedWhenExpired(Request $request, string $path): bool
    {
        foreach ($this->allowWhenExpired as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed.'/')) {
                // Block host DTR review endpoints even under ojt-dtrs prefix.
                if (preg_match('#^ojt-dtrs/\d+/(approve|reject)$#', $path)) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }
}

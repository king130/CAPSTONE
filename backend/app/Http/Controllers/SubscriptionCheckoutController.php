<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlanDefinition;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class SubscriptionCheckoutController extends Controller
{
    /**
     * Only allow known PayMongo checkout method identifiers.
     *
     * @var array<int, string>
     */
    private const ALLOWED_PAYMENT_METHOD_TYPES = [
        'card',
        'gcash',
        'paymaya',
        'grab_pay',
        'billease',
        'dob',
    ];

    public function __construct(private readonly SubscriptionPlanService $subscriptionPlans)
    {
    }

    private function resolvePaymongoCaBundle(): string
    {
        $caBundle = trim((string) config('services.paymongo.ca_bundle', ''));
        if ($caBundle === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:\\\\/', $caBundle) === 1 || str_starts_with($caBundle, '\\\\')) {
            return $caBundle;
        }

        if (str_starts_with($caBundle, '/')) {
            return $caBundle;
        }

        return base_path($caBundle);
    }

    private function paymongoHttp(string $secretKey)
    {
        $http = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 200);

        $verifySsl = (bool) config('services.paymongo.verify_ssl', true);
        $caBundle = $this->resolvePaymongoCaBundle();

        if (! $verifySsl && app()->environment('local')) {
            return $http->withOptions(['verify' => false]);
        }

        if ($caBundle !== '') {
            $http = $http->withOptions(['verify' => $caBundle]);
        }

        return $http;
    }

    private function paymongoSslErrorResponse(ConnectionException $exception)
    {
        $configured = trim((string) config('services.paymongo.ca_bundle', ''));
        $resolved = $this->resolvePaymongoCaBundle();

        $hints = [];
        if ($configured === '') {
            $hints[] = 'Set PAYMONGO_CA_BUNDLE to the path of a CA bundle file (cacert.pem).';
        } else {
            $hints[] = 'Verify PAYMONGO_CA_BUNDLE points to an existing cacert.pem file.';
        }

        $hints[] = 'Example (backend-relative): PAYMONGO_CA_BUNDLE=storage/certs/cacert.pem';
        if (PHP_OS_FAMILY === 'Windows') {
            $hints[] = 'Windows tip: If you have Git for Windows installed, you can copy C:\\Program Files\\Git\\usr\\ssl\\certs\\ca-bundle.crt to backend/storage/certs/cacert.pem.';
        }
        $hints[] = 'For local dev only, you can set PAYMONGO_VERIFY_SSL=false (not recommended).';

        return response()->json([
            'message' => 'PayMongo request failed due to an SSL certificate verification problem (cURL error 60).',
            'details' => $exception->getMessage(),
            'paymongo' => [
                'caBundleConfigured' => $configured !== '' ? $configured : null,
                'caBundleResolved' => $resolved !== '' ? $resolved : null,
                'verifySsl' => (bool) config('services.paymongo.verify_ssl', true),
            ],
            'hints' => $hints,
        ], Response::HTTP_BAD_GATEWAY);
    }

    /**
     * Resolve payment methods safely.
     * If nothing valid is configured, default to all allowed methods.
     *
     * @return array<int, string>
     */
    private function resolvePaymentMethodTypes(): array
    {
        $configuredMethods = config('services.paymongo.payment_methods', []);
        if (! is_array($configuredMethods)) {
            return self::ALLOWED_PAYMENT_METHOD_TYPES;
        }

        $sanitized = [];
        foreach ($configuredMethods as $method) {
            $normalized = strtolower(trim((string) $method));
            if ($normalized === '' || ! in_array($normalized, self::ALLOWED_PAYMENT_METHOD_TYPES, true)) {
                continue;
            }

            $sanitized[$normalized] = $normalized;
        }

        return $sanitized === [] ? self::ALLOWED_PAYMENT_METHOD_TYPES : array_values($sanitized);
    }

    public function create(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'requestId' => ['required', 'string', 'max:255'],
            'planId' => ['required', 'string', 'max:255'],
            'planName' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['school', 'company'])],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'billingCycle' => ['nullable', 'string', 'max:255'],
            'returnUrl' => ['nullable', 'url', 'max:2048'],
        ]);

        $paymongoSecretKey = (string) config('services.paymongo.secret_key', '');
        if ($paymongoSecretKey === '') {
            return response()->json([
                'message' => 'PayMongo test mode is not configured yet. Add PAYMONGO_SECRET_KEY to your backend environment first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organization = $user->primaryOrganizationMembership()?->organization;
        if (! $organization) {
            return response()->json([
                'message' => 'No active organization found for this account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($organization->type !== $data['role']) {
            return response()->json([
                'message' => 'The requested plan role does not match your organization type.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $planSlug = strtolower(trim((string) $data['planId']));
        /** @var SubscriptionPlanDefinition|null $planDefinition */
        $planDefinition = SubscriptionPlanDefinition::query()
            ->where('is_active', true)
            ->where('slug', $planSlug)
            ->first();

        if (! $planDefinition) {
            return response()->json([
                'message' => 'Selected subscription plan is not available.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subscription = $organization->subscription;
        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'plan' => 'free',
                'status' => 'active',
                'billing_cycle' => 'monthly',
            ]);
            $organization->subscription_id = $subscription->id;
            $organization->save();
        }

        $settings = $organization->settings ?? [];
        $existingPending = $settings['pending_plan_change'] ?? null;
        if (is_array($existingPending) && ($existingPending['requestId'] ?? null) !== $data['requestId']) {
            return response()->json([
                'message' => 'A pending checkout is already in progress for this organization.',
                'pendingChange' => $existingPending,
            ], Response::HTTP_CONFLICT);
        }

        $frontendBase = (string) ($data['returnUrl'] ?? config('app.frontend_url', config('app.url')));
        $successUrl = $this->appendQuery($frontendBase, [
            'paymongo' => 'success',
            'requestId' => $data['requestId'],
        ]);
        $cancelUrl = $this->appendQuery($frontendBase, [
            'paymongo' => 'cancel',
            'requestId' => $data['requestId'],
        ]);

        $name = $user->name ?: $organization->name;
        $billingCycle = strtolower(trim((string) ($data['billingCycle'] ?? $subscription->billing_cycle ?? 'monthly'))) ?: 'monthly';

        $amount = $organization->type === 'school'
            ? (float) $planDefinition->school_price
            : (float) $planDefinition->company_price;
        $amountInCentavos = (int) round($amount * 100);
        $paymentMethodTypes = $this->resolvePaymentMethodTypes();
        $checkoutAttributes = [
            'billing' => [
                'name' => $name,
                'email' => $user->email,
            ],
            'description' => sprintf('%s subscription upgrade to %s', ucfirst($data['role']), $planDefinition->name),
            'line_items' => [
                [
                    'currency' => 'PHP',
                    'amount' => $amountInCentavos,
                    'name' => sprintf('%s Plan', $planDefinition->name),
                    'quantity' => 1,
                    'description' => sprintf(
                        '%s subscription billed %s',
                        ucfirst($planDefinition->name),
                        $billingCycle
                    ),
                ],
            ],
            'send_email_receipt' => false,
            'show_description' => true,
            'show_line_items' => true,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'request_id' => $data['requestId'],
                'target_plan' => $planDefinition->slug,
                'billing_cycle' => $billingCycle,
                'organization_id' => (string) $organization->id,
                'organization_type' => $organization->type,
                'user_id' => (string) $user->id,
            ],
        ];
        $checkoutAttributes['payment_method_types'] = $paymentMethodTypes;

        try {
            $response = $this->paymongoHttp($paymongoSecretKey)->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => $checkoutAttributes,
                ],
            ]);
        } catch (ConnectionException $exception) {
            return $this->paymongoSslErrorResponse($exception);
        }

        if ($response->failed()) {
            $message = $response->json('errors.0.detail')
                ?? $response->json('errors.0.code')
                ?? 'Could not create PayMongo checkout session.';

            return response()->json(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $checkout = $response->json('data');
        if (! is_array($checkout)) {
            return response()->json(['message' => 'PayMongo returned an invalid checkout response.'], Response::HTTP_BAD_GATEWAY);
        }

        $attributes = is_array($checkout['attributes'] ?? null) ? $checkout['attributes'] : [];

        $pending = [
            'requestId' => $data['requestId'],
            'targetPlan' => (string) $planDefinition->slug,
            'planName' => (string) $planDefinition->name,
            'amount' => (float) $amount,
            'currency' => 'PHP',
            'billingCycle' => $billingCycle,
            'createdAt' => now()->toIso8601String(),
            'checkoutSessionId' => (string) ($checkout['id'] ?? ''),
            'checkoutUrl' => (string) ($attributes['checkout_url'] ?? ''),
            'checkoutStatus' => (string) ($attributes['status'] ?? 'active'),
            'paymentMethods' => $paymentMethodTypes,
        ];

        $settings['pending_plan_change'] = $pending;
        $organization->settings = $settings;
        $organization->save();

        $subscription->status = 'pending';
        $subscription->billing_cycle = $billingCycle;
        $subscription->save();
        $this->subscriptionPlans->syncOrganizationCompliance($organization->fresh(['subscription', 'owner']));

        return response()->json([
            'requestId' => $pending['requestId'],
            'checkoutSessionId' => $pending['checkoutSessionId'],
            'checkoutUrl' => $pending['checkoutUrl'],
            'status' => $pending['checkoutStatus'],
            'paymentMethods' => $pending['paymentMethods'],
        ]);
    }

    public function verify(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'checkoutSessionId' => ['required', 'string', 'max:255'],
            'requestId' => ['required', 'string', 'max:255'],
        ]);

        $paymongoSecretKey = (string) config('services.paymongo.secret_key', '');
        if ($paymongoSecretKey === '') {
            return response()->json([
                'message' => 'PayMongo test mode is not configured yet. Add PAYMONGO_SECRET_KEY to your backend environment first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $organization = $user->primaryOrganizationMembership()?->organization;
        if (! $organization) {
            return response()->json([
                'message' => 'No active organization found for this account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $pending = $organization->settings['pending_plan_change'] ?? null;
        if (! is_array($pending) || ($pending['requestId'] ?? null) !== $data['requestId']) {
            return response()->json([
                'message' => 'No matching pending plan change was found.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (($pending['checkoutSessionId'] ?? null) !== $data['checkoutSessionId']) {
            return response()->json([
                'message' => 'The checkout session does not match the pending plan change.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $response = $this->paymongoHttp($paymongoSecretKey)->get('https://api.paymongo.com/v1/checkout_sessions/'.$data['checkoutSessionId']);
        } catch (ConnectionException $exception) {
            return $this->paymongoSslErrorResponse($exception);
        }

        if ($response->failed()) {
            $message = $response->json('errors.0.detail')
                ?? $response->json('errors.0.code')
                ?? 'Could not verify PayMongo checkout session.';

            return response()->json(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $checkout = $response->json('data');
        $attributes = is_array($checkout['attributes'] ?? null) ? $checkout['attributes'] : [];
        $payments = is_array($attributes['payments'] ?? null) ? $attributes['payments'] : [];
        $latestPayment = $payments[0]['attributes'] ?? null;
        $paymentStatus = is_array($latestPayment) ? ($latestPayment['status'] ?? null) : null;
        $checkoutStatus = strtolower(trim((string) ($attributes['status'] ?? 'active')));

        $paid = false;
        if ($paymentStatus === 'paid') {
            $paid = true;
        }
        if (in_array($checkoutStatus, ['paid', 'succeeded', 'complete', 'completed'], true)) {
            $paid = true;
        }

        if (! $paid) {
            $settings = $organization->settings ?? [];
            $pending['checkoutStatus'] = (string) ($attributes['status'] ?? 'active');
            $settings['pending_plan_change'] = $pending;
            $organization->settings = $settings;
            $organization->save();

            return response()->json([
                'verified' => false,
                'checkoutStatus' => $pending['checkoutStatus'],
                'paymentStatus' => $paymentStatus,
                'message' => in_array($checkoutStatus, ['cancelled', 'canceled', 'expired'], true)
                    ? 'The PayMongo checkout session was cancelled or expired. Start a new checkout to continue.'
                    : 'Payment is not completed yet in PayMongo test mode.',
            ]);
        }

        $subscription = $organization->subscription;
        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'plan' => 'free',
                'status' => 'active',
                'billing_cycle' => 'monthly',
            ]);
            $organization->subscription_id = $subscription->id;
            $organization->save();
        }

        $subscription->plan = (string) ($pending['targetPlan'] ?? $subscription->plan);
        $subscription->status = 'active';
        if (! empty($pending['billingCycle'])) {
            $subscription->billing_cycle = (string) $pending['billingCycle'];
        }
        $subscription->save();

        $settings = $organization->settings ?? [];
        unset($settings['pending_plan_change']);
        $organization->settings = $settings;
        $organization->save();
        $this->subscriptionPlans->syncOrganizationCompliance($organization->fresh(['subscription', 'owner']));

        $user->refresh();
        $user->load([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);

        $auth = app(AuthController::class);

        return response()->json([
            'verified' => true,
            'checkoutStatus' => (string) ($attributes['status'] ?? 'paid'),
            'paymentStatus' => 'paid',
            'user' => $auth->formatUserProfile($user),
        ]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function appendQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query);
    }
}

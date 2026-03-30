<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class SubscriptionCheckoutController extends Controller
{
    public function create(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'requestId' => ['required', 'string', 'max:255'],
            'planId' => ['required', 'string', 'max:255'],
            'planName' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['school', 'company'])],
            'amount' => ['required', 'numeric', 'min:1'],
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

        $subscription = $organization->subscription;
        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'plan' => 'free',
                'status' => 'inactive',
                'billing_cycle' => 'monthly',
            ]);
            $organization->subscription_id = $subscription->id;
            $organization->save();
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
        $amountInCentavos = (int) round(((float) $data['amount']) * 100);
        $paymentMethodTypes = array_values(array_filter(array_map(
            static fn ($method) => trim((string) $method),
            config('services.paymongo.payment_methods', ['card'])
        )));

        $response = Http::withBasicAuth($paymongoSecretKey, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => [
                            'name' => $name,
                            'email' => $user->email,
                        ],
                        'description' => sprintf('%s subscription upgrade to %s', ucfirst($data['role']), $data['planName']),
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => $amountInCentavos,
                                'name' => sprintf('%s Plan', $data['planName']),
                                'quantity' => 1,
                                'description' => sprintf(
                                    '%s subscription billed %s',
                                    ucfirst($data['planName']),
                                    $data['billingCycle'] ?: ($subscription->billing_cycle ?: 'monthly')
                                ),
                            ],
                        ],
                        'payment_method_types' => $paymentMethodTypes !== [] ? $paymentMethodTypes : ['card'],
                        'send_email_receipt' => false,
                        'show_description' => true,
                        'show_line_items' => true,
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'metadata' => [
                            'request_id' => $data['requestId'],
                            'target_plan' => $data['planId'],
                            'organization_id' => (string) $organization->id,
                            'organization_type' => $organization->type,
                            'user_id' => (string) $user->id,
                        ],
                    ],
                ],
            ]);

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
            'targetPlan' => $data['planId'],
            'amount' => (float) $data['amount'],
            'currency' => 'PHP',
            'createdAt' => now()->toIso8601String(),
            'checkoutSessionId' => (string) ($checkout['id'] ?? ''),
            'checkoutUrl' => (string) ($attributes['checkout_url'] ?? ''),
            'checkoutStatus' => (string) ($attributes['status'] ?? 'active'),
            'paymentMethods' => $paymentMethodTypes !== [] ? $paymentMethodTypes : ['card'],
        ];

        $settings = $organization->settings ?? [];
        $settings['pending_plan_change'] = $pending;
        $organization->settings = $settings;
        $organization->save();

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

        $response = Http::withBasicAuth($paymongoSecretKey, '')
            ->acceptJson()
            ->get('https://api.paymongo.com/v1/checkout_sessions/'.$data['checkoutSessionId']);

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

        if ($paymentStatus !== 'paid') {
            $settings = $organization->settings ?? [];
            $pending['checkoutStatus'] = (string) ($attributes['status'] ?? 'active');
            $settings['pending_plan_change'] = $pending;
            $organization->settings = $settings;
            $organization->save();

            return response()->json([
                'verified' => false,
                'checkoutStatus' => $pending['checkoutStatus'],
                'paymentStatus' => $paymentStatus,
                'message' => 'Payment is not completed yet in PayMongo test mode.',
            ]);
        }

        $subscription = $organization->subscription;
        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'plan' => 'free',
                'status' => 'inactive',
                'billing_cycle' => 'monthly',
            ]);
            $organization->subscription_id = $subscription->id;
            $organization->save();
        }

        $subscription->plan = (string) ($pending['targetPlan'] ?? $subscription->plan);
        $subscription->status = 'active';
        $subscription->save();

        $settings = $organization->settings ?? [];
        unset($settings['pending_plan_change']);
        $organization->settings = $settings;
        $organization->save();

        $user->refresh();
        $user->load([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);

        $auth = new AuthController;

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

import { apiFetch } from './http'
import type { UserProfile } from './auth'
import { mapApiUserToProfile } from './auth'
import { useAuthStore } from '@/stores/auth'

export type SubscriptionStatus = 'active' | 'pending' | 'inactive'
export type CheckoutVerificationResult = {
  verified: boolean
  checkoutStatus?: string
  paymentStatus?: string | null
  message?: string
}

function syncAuthUser(raw: UserProfile | Record<string, unknown>): UserProfile {
  const profile =
    'uid' in raw ? mapApiUserToProfile(raw as Record<string, unknown>) : (raw as unknown as UserProfile)
  const authStore = useAuthStore()
  authStore.setUserProfile(profile)
  return profile
}

/** Switch the caller's organization to the free plan via the dedicated billing endpoint. */
export async function switchToFreePlan(_uid: string): Promise<UserProfile> {
  const raw = await apiFetch<Record<string, unknown>>('/subscriptions/free', {
    method: 'POST',
    body: JSON.stringify({}),
  })
  return syncAuthUser(raw)
}

/** Cancel an unpaid pending upgrade via the dedicated billing endpoint. */
export async function cancelPendingUpgrade(_uid: string): Promise<UserProfile> {
  const raw = await apiFetch<Record<string, unknown>>('/subscriptions/cancel-pending', {
    method: 'POST',
    body: JSON.stringify({}),
  })
  return syncAuthUser(raw)
}

export async function createPaymongoCheckoutSession(
  _uid: string,
  payload: {
    requestId: string
    planId: string
    planName: string
    role: 'school' | 'company'
    amount: number
    billingCycle: string
    returnUrl?: string
  }
): Promise<{
  requestId: string
  checkoutSessionId: string
  checkoutUrl: string
  status: string
  paymentMethods: string[]
}> {
  return apiFetch('/subscriptions/checkout', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function verifyPaymongoCheckoutSession(
  _uid: string,
  payload: {
    requestId: string
    checkoutSessionId: string
  }
): Promise<CheckoutVerificationResult> {
  const raw = await apiFetch<CheckoutVerificationResult & { user?: Record<string, unknown> }>('/subscriptions/verify', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  if (raw.user) {
    syncAuthUser(raw.user)
  }

  return {
    verified: raw.verified,
    checkoutStatus: raw.checkoutStatus,
    paymentStatus: raw.paymentStatus,
    message: raw.message,
  }
}

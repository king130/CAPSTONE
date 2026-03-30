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
  const profile = 'uid' in raw ? mapApiUserToProfile(raw as Record<string, unknown>) : (raw as UserProfile)
  const authStore = useAuthStore()
  authStore.setUserProfile(profile)
  return profile
}

export async function updateUserSubscription(
  _uid: string,
  updates: {
    plan?: string
    status?: SubscriptionStatus
    billingCycle?: string
  }
): Promise<UserProfile> {
  const raw = await apiFetch<Record<string, unknown>>('/profile', {
    method: 'PATCH',
    body: JSON.stringify({
      subscription: {
        plan: updates.plan,
        status: updates.status,
        billingCycle: updates.billingCycle,
      },
    }),
  })
  return syncAuthUser(raw)
}

export type PaymentMethod = 'gcash' | 'maya' | 'bank' | 'cash' | 'other'

export async function createPendingPlanChange(
  _uid: string,
  request: {
    requestId: string
    targetPlan: string
    amount: number
    currency: 'PHP'
  }
): Promise<UserProfile> {
  const raw = await apiFetch<Record<string, unknown>>('/profile', {
    method: 'PATCH',
    body: JSON.stringify({
      subscription: {
        status: 'pending',
        pendingChange: {
          ...request,
          createdAt: new Date().toISOString(),
        },
      },
    }),
  })
  return syncAuthUser(raw)
}

export async function submitPendingPlanPayment(
  _uid: string,
  payload: {
    requestId: string
    method: PaymentMethod
    receiptReference: string
    paidAtIso: string
  }
): Promise<UserProfile> {
  const raw = await apiFetch<Record<string, unknown>>('/profile', {
    method: 'PATCH',
    body: JSON.stringify({
      subscription: {
        pendingPayment: payload,
      },
    }),
  })
  return syncAuthUser(raw)
}

export async function clearPendingPlanChange(_uid: string): Promise<UserProfile> {
  const raw = await apiFetch<Record<string, unknown>>('/profile', {
    method: 'PATCH',
    body: JSON.stringify({
      subscription: {
        pendingChange: null,
      },
    }),
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

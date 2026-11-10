/**
 * Shared subscription-access helpers.
 * Status values come from backend SubscriptionAccessService::resolve() —
 * never re-derive expired/unverified on the client from raw plan dates.
 */

export type SubscriptionAccessStatus =
  | 'unverified'
  | 'trial'
  | 'free'
  | 'active'
  | 'pending_payment'
  | 'past_due'
  | 'expired'

/** Human labels used on the billing page and Access summary. Never show raw enums. */
export type BillingDisplayStatus =
  | 'Free trial'
  | 'Trial ended'
  | 'Active'
  | 'Payment failed'
  | 'Pending payment'

export type SubscriptionAccessLike = {
  status?: string | null
  message?: string | null
  trialEndsAt?: string | null
  paidEndsAt?: string | null
  daysRemaining?: number | null
} | null | undefined

export function isTrialEndedAccess(access: SubscriptionAccessLike): boolean {
  const status = String(access?.status || '').toLowerCase()
  if (status !== 'past_due' && status !== 'expired') return false
  const message = String(access?.message || '').toLowerCase()
  if (message.includes('trial')) return true
  return Boolean(access?.trialEndsAt) && !access?.paidEndsAt
}

/**
 * Single display status for the subscription page (badge, Status box, heading, Access).
 * Never returns "Trialing" or raw backend codes.
 */
export function billingDisplayStatus(access: SubscriptionAccessLike): BillingDisplayStatus {
  const status = String(access?.status || '').toLowerCase()

  if (status === 'pending_payment') return 'Pending payment'
  if (status === 'trial') return 'Free trial'
  if (isTrialEndedAccess(access)) return 'Trial ended'
  if (status === 'past_due') return 'Payment failed'
  if (status === 'active' || status === 'free') return 'Active'
  if (status === 'expired') return 'Trial ended'
  return 'Active'
}

export function shouldShowSubscriptionBanner(status: string | null | undefined): boolean {
  return (
    status === 'trial' ||
    status === 'pending_payment' ||
    status === 'past_due' ||
    status === 'expired'
  )
}

export function isSubscriptionReadOnly(status: string | null | undefined, canWrite?: boolean): boolean {
  if (typeof canWrite === 'boolean') {
    return !canWrite
  }
  return status === 'expired'
}

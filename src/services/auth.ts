import { apiFetch, setToken, getToken } from './http'

export type UserRole = 'student' | 'company' | 'school' | 'admin' | 'guest' | null
export type AccessScope = 'platform' | 'organization' | 'personal'

export interface MembershipSummary {
  id: string
  organization: {
    id: string
    name: string
    type: 'school' | 'company'
    isActive?: boolean
  }
  role: {
    id: string
    slug: string
    name: string
    scope: 'organization' | 'platform'
  }
  title?: string | null
  status: string
  permissions: string[]
}

export interface RegisterPayload {
  fullName: string
  email: string
  password: string
  role?: UserRole
  profile?: Record<string, unknown>
  subscriptionPlan?: string
  billingCycle?: string
  schoolSubscriptionCode?: string
}

export interface UserProfile {
  uid: string
  email: string
  displayName: string
  role: UserRole
  accessScope?: AccessScope
  isTemporary?: boolean
  isActive?: boolean
  profileSetupComplete?: boolean
  profile?: Record<string, unknown>
  mustChangePassword?: boolean
  platformRole?: {
    id: string
    slug: string
    name: string
    permissions: string[]
  } | null
  memberships?: MembershipSummary[]
  activeOrganization?: {
    id: string
    name: string
    type: 'school' | 'company'
    subscriptionId?: string | null
  } | null
  subscription?: {
    plan: string
    billingCycle: string
    status: 'active' | 'pending' | 'inactive'
    subscriptionCode?: string
    pendingChange?: {
      requestId: string
      targetPlan: string
      amount: number
      currency: 'PHP'
      createdAt?: unknown
      checkoutSessionId?: string
      checkoutUrl?: string
      checkoutStatus?: string
      paymentMethods?: string[]
      payment?: {
        requestId?: string
        method?: string
        receiptReference?: string
        paidAtIso?: string
      }
    } | null
  }
  createdAt?: unknown
  updatedAt?: unknown
}

interface AuthResponse {
  token: string
  user: Record<string, unknown>
}

export function mapApiUserToProfile(raw: Record<string, unknown>): UserProfile {
  const role = (raw.role as string | null) || null
  return {
    uid: String(raw.uid ?? ''),
    email: String(raw.email ?? ''),
    displayName: String(raw.displayName ?? ''),
    role: role === 'guest' ? null : (role as UserRole),
    accessScope: (raw.accessScope as AccessScope | undefined) ?? 'personal',
    isTemporary: raw.isTemporary as boolean | undefined,
    isActive: raw.isActive !== false,
    profileSetupComplete: raw.profileSetupComplete as boolean | undefined,
    profile: (raw.profile as Record<string, unknown>) || {},
    mustChangePassword: raw.mustChangePassword as boolean | undefined,
    platformRole: (raw.platformRole as UserProfile['platformRole']) ?? null,
    memberships: (raw.memberships as MembershipSummary[] | undefined) ?? [],
    activeOrganization: (raw.activeOrganization as UserProfile['activeOrganization']) ?? null,
    subscription: raw.subscription as UserProfile['subscription'],
    createdAt: raw.createdAt,
    updatedAt: raw.updatedAt,
  }
}

export async function validateSchoolSubscription(_code: string): Promise<string | null> {
  // Server validates on register when schoolSubscriptionCode is sent
  return null
}

export async function registerUser(payload: RegisterPayload): Promise<UserProfile> {
  const body = {
    email: payload.email,
    password: payload.password,
    fullName: payload.fullName,
    role: payload.role ?? null,
    profile: payload.profile ?? {},
    subscriptionPlan: payload.subscriptionPlan,
    billingCycle: payload.billingCycle,
    schoolSubscriptionCode: payload.schoolSubscriptionCode,
  }
  const res = await apiFetch<AuthResponse>('/auth/register', {
    method: 'POST',
    body: JSON.stringify(body),
  })
  setToken(res.token)
  return mapApiUserToProfile(res.user as Record<string, unknown>)
}

export async function loginUser(email: string, password: string): Promise<UserProfile> {
  const res = await apiFetch<AuthResponse>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email: email.trim().toLowerCase(), password }),
  })
  setToken(res.token)
  return mapApiUserToProfile(res.user as Record<string, unknown>)
}

export async function logoutUser(): Promise<void> {
  try {
    if (getToken()) {
      await apiFetch('/auth/logout', { method: 'POST', body: JSON.stringify({}) })
    }
  } finally {
    setToken(null)
  }
}

export async function fetchUserProfile(_uid: string): Promise<UserProfile | null> {
  if (!getToken()) return null
  try {
    const raw = await apiFetch<Record<string, unknown>>('/auth/me')
    return mapApiUserToProfile(raw)
  } catch {
    return null
  }
}

export async function createMissingUserProfile(): Promise<void> {
  // Profiles are created server-side; no client stub document
}

export async function updateCurrentUserPassword(newPassword: string): Promise<void> {
  await apiFetch('/auth/password', {
    method: 'POST',
    body: JSON.stringify({ password: newPassword, password_confirmation: newPassword }),
  })
}

/** @deprecated Realtime removed — use auth store + /auth/me */
export function subscribeToUserProfile(
  _uid: string,
  callback: (profile: UserProfile | null) => void
): () => void {
  let cancelled = false
  async function load() {
    const p = await fetchUserProfile(_uid)
    if (!cancelled) callback(p)
  }
  load()
  const id = window.setInterval(load, 30000)
  return () => {
    cancelled = true
    clearInterval(id)
  }
}

/** @deprecated Use bootstrap + token restore */
export function subscribeToAuthState(_callback: (user: { uid: string } | null) => void): () => void {
  return () => {}
}

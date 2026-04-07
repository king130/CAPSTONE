import axios from 'axios'
import apiClient from './apiClient'
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
    limits?: {
      school?: { coordinators: number; students: number }
      company?: { accounts: number; internships: number }
    } | null
    overages?: {
      requiresAction: boolean
      items: Array<{
        key: string
        scope: string
        resource: string
        label: string
        limit: number
        current: number
        excess: number
        message: string
      }>
    } | null
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

function getAuthErrorMessage(
  error: unknown,
  fallback: string
): string {
  if (!axios.isAxiosError(error)) {
    return fallback
  }

  if (error.code === 'ERR_NETWORK' || error.message === 'Network Error') {
    return (
      'Cannot reach the API server. Set VITE_API_BASE_URL in the project root .env to your Laravel API ' +
      '(e.g. https://your-api-host/api), run npm run build, upload dist, and ensure FRONTEND_URL on the API matches this site.'
    )
  }

  const res = error.response
  if (!res) {
    return error.message || fallback
  }

  const data = res.data
  if (typeof data === 'string' && data.trim()) {
    return data.length > 280 ? fallback : data.trim()
  }

  const responseData = data as
    | { message?: string; errors?: Record<string, string[] | string | Record<string, string[]>> }
    | undefined

  let firstValidation: string | undefined
  const errs = responseData?.errors
  if (errs && typeof errs === 'object') {
    for (const v of Object.values(errs)) {
      if (Array.isArray(v) && v[0]) {
        firstValidation = String(v[0])
        break
      }
      if (typeof v === 'string') {
        firstValidation = v
        break
      }
      if (v && typeof v === 'object') {
        const nested = Object.values(v as Record<string, string[]>)[0]
        if (Array.isArray(nested) && nested[0]) {
          firstValidation = String(nested[0])
          break
        }
      }
    }
  }

  if (res.status === 404) {
    return (
      responseData?.message ||
      'API returned 404. VITE_API_BASE_URL is probably wrong — it must be the full base including /api (e.g. https://api.example.com/api).'
    )
  }

  return (
    responseData?.message ||
    firstValidation ||
    (res.status >= 500
      ? 'Server error while registering. Check API logs (storage/logs/laravel.log) and run migrations on the server.'
      : fallback)
  )
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
  try {
    const { data } = await apiClient.post<AuthResponse>('/auth/register', {
      email: payload.email,
      password: payload.password,
      fullName: payload.fullName,
      role: payload.role ?? null,
      profile: payload.profile ?? {},
      subscriptionPlan: payload.subscriptionPlan,
      billingCycle: payload.billingCycle,
      schoolSubscriptionCode: payload.schoolSubscriptionCode,
    })
    setToken(data.token)
    return mapApiUserToProfile(data.user as Record<string, unknown>)
  } catch (error) {
    throw new Error(
      getAuthErrorMessage(error, 'Unable to create your account right now. Please try again later.')
    )
  }
}

export async function loginUser(email: string, password: string): Promise<UserProfile> {
  try {
    const { data } = await apiClient.post<AuthResponse>('/auth/login', {
      email: email.trim().toLowerCase(),
      password,
    })
    setToken(data.token)
    return mapApiUserToProfile(data.user as Record<string, unknown>)
  } catch (error) {
    throw new Error(
      getAuthErrorMessage(error, 'Unable to sign in right now. Please try again later.')
    )
  }
}

export async function logoutUser(): Promise<void> {
  try {
    if (getToken()) {
      await apiFetch('/auth/logout', { method: 'POST', body: JSON.stringify({}) })
    }
  } catch (error) {
    if (!(error instanceof Error) || error.message !== 'Unauthenticated.') {
      throw error
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

export async function validateAccountSetupToken(token: string): Promise<{ email: string; displayName: string; expiresAt?: string }> {
  const response = await apiFetch<{ data: { email: string; displayName: string; expiresAt?: string } }>('/auth/account-setup/validate', {
    method: 'POST',
    body: JSON.stringify({ token }),
  })
  return response.data
}

export async function completeAccountSetup(token: string, password: string): Promise<UserProfile> {
  const response = await apiClient.post<AuthResponse>('/auth/account-setup/complete', {
    token,
    password,
    password_confirmation: password,
  })
  setToken(response.data.token)
  return mapApiUserToProfile(response.data.user as Record<string, unknown>)
}

export async function updateCurrentUserProfile(payload: {
  displayName?: string
  profile?: Record<string, unknown>
  profileSetupComplete?: boolean
}): Promise<UserProfile> {
  const { data } = await apiClient.patch<Record<string, unknown>>('/profile', payload)
  return mapApiUserToProfile(data)
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

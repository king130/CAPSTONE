/**
 * Decide where an authenticated user should be redirected when opening
 * organization registration (/register/school|company|municipal-hall).
 *
 * Guests (role null / 'guest') must be allowed to complete registration.
 * Existing School/Company/Municipal Hall/Student/Admin users are sent to their normal destinations.
 */
export function resolveAuthenticatedRegisterRedirect(user: {
  role: string | null
  mustChangePassword?: boolean
  verificationStatus?: string | null
} | null | undefined): string | null {
  if (!user) return null

  if (user.mustChangePassword) {
    return '/change-password'
  }

  const role = user.role
  if (!role || role === 'guest') {
    return null
  }

  const status = String(user.verificationStatus || 'pending').toLowerCase()
  if (
    (role === 'school' || role === 'company' || role === 'municipal_hall') &&
    (status === 'pending' || status === 'under_review' || status === 'rejected')
  ) {
    return '/organization-verification'
  }

  if (role === 'admin') return '/admin/overview'
  if (role === 'company' || role === 'municipal_hall') return '/dashboard'
  if (role === 'school') return '/school'
  if (role === 'student') return '/intern'

  return '/opportunities'
}

/** Guests keep a session on subscription pages; clear it before public org registration. */
export function shouldClearSessionBeforeOrganizationRegister(
  role: string | null | undefined,
): boolean {
  return !role || role === 'guest'
}

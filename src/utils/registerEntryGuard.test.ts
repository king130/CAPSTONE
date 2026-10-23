import { describe, expect, it } from 'vitest'

import {
  resolveAuthenticatedRegisterRedirect,
  shouldClearSessionBeforeOrganizationRegister,
} from '@/utils/registerEntryGuard'

describe('registerEntryGuard', () => {
  it('allows Guest (null role) to stay on School registration', () => {
    expect(resolveAuthenticatedRegisterRedirect({ role: null })).toBeNull()
    expect(shouldClearSessionBeforeOrganizationRegister(null)).toBe(true)
  })

  it('allows Guest (guest role) to stay on Company registration', () => {
    expect(resolveAuthenticatedRegisterRedirect({ role: 'guest' })).toBeNull()
    expect(shouldClearSessionBeforeOrganizationRegister('guest')).toBe(true)
  })

  it('blocks authenticated approved School from public registration', () => {
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'school',
        verificationStatus: 'approved',
      }),
    ).toBe('/school')
    expect(shouldClearSessionBeforeOrganizationRegister('school')).toBe(false)
  })

  it('blocks authenticated pending Company from public registration', () => {
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'company',
        verificationStatus: 'pending',
      }),
    ).toBe('/organization-verification')
    expect(shouldClearSessionBeforeOrganizationRegister('company')).toBe(false)
  })

  it('blocks authenticated rejected School from public registration', () => {
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'school',
        verificationStatus: 'rejected',
      }),
    ).toBe('/organization-verification')
  })

  it('blocks Student and Admin from public registration', () => {
    expect(resolveAuthenticatedRegisterRedirect({ role: 'student' })).toBe('/intern')
    expect(resolveAuthenticatedRegisterRedirect({ role: 'admin' })).toBe('/admin/overview')
  })

  it('forces password change before registration when required', () => {
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: null,
        mustChangePassword: true,
      }),
    ).toBe('/change-password')
  })

  it('sends school staff with inherited approved parent verification to the school dashboard', () => {
    // Teachers/dept heads receive the parent School verificationStatus from /auth/me.
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'school',
        mustChangePassword: false,
        verificationStatus: 'approved',
      }),
    ).toBe('/school')
  })

  it('keeps school staff restricted when parent School verification is still pending', () => {
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'school',
        mustChangePassword: false,
        verificationStatus: 'pending',
      }),
    ).toBe('/organization-verification')
  })

  it('keeps organizations under review on the verification page', () => {
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'company',
        verificationStatus: 'under_review',
      }),
    ).toBe('/organization-verification')
  })

  it('routes Municipal Hall hosts like company hosts', () => {
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'municipal_hall',
        verificationStatus: 'pending',
      }),
    ).toBe('/organization-verification')
    expect(
      resolveAuthenticatedRegisterRedirect({
        role: 'municipal_hall',
        verificationStatus: 'approved',
      }),
    ).toBe('/dashboard')
  })
})

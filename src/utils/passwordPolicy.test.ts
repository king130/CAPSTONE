import { describe, expect, it } from 'vitest'

import {
  PASSWORD_POLICY_MESSAGE,
  isChangePasswordFormValid,
  meetsPasswordPolicy,
  passwordPolicyError,
} from '@/utils/passwordPolicy'

describe('passwordPolicy', () => {
  it('rejects passwords shorter than 8 characters', () => {
    expect(meetsPasswordPolicy('Ab12')).toBe(false)
    expect(meetsPasswordPolicy('Abcde1')).toBe(false)
    expect(passwordPolicyError('Abcde1')).toBe(PASSWORD_POLICY_MESSAGE)
  })

  it('rejects passwords without a letter', () => {
    expect(meetsPasswordPolicy('12345678')).toBe(false)
    expect(passwordPolicyError('12345678')).toBe(PASSWORD_POLICY_MESSAGE)
  })

  it('rejects passwords without a number', () => {
    expect(meetsPasswordPolicy('Password')).toBe(false)
    expect(passwordPolicyError('abcdefgh')).toBe(PASSWORD_POLICY_MESSAGE)
  })

  it('accepts passwords that meet backend rules', () => {
    expect(meetsPasswordPolicy('Password1')).toBe(true)
    expect(meetsPasswordPolicy('abc12345')).toBe(true)
    expect(passwordPolicyError('Password1')).toBeNull()
  })

  it('does not flag an empty password until the user types', () => {
    expect(passwordPolicyError('')).toBeNull()
  })

  it('rejects confirm-password mismatch even when the password meets policy', () => {
    expect(isChangePasswordFormValid('Password1', 'Password2')).toBe(false)
    expect(isChangePasswordFormValid('Password1', 'Password1')).toBe(true)
  })
})

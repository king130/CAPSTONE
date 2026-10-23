/** Matches AuthController password policy (Change Password / register). */
export const PASSWORD_POLICY_MESSAGE =
  'Password must be at least 8 characters and include a letter and a number'

export function meetsPasswordPolicy(password: string): boolean {
  return password.length >= 8 && /[A-Za-z]/.test(password) && /[0-9]/.test(password)
}

export function passwordPolicyError(password: string): string | null {
  if (!password) return null
  return meetsPasswordPolicy(password) ? null : PASSWORD_POLICY_MESSAGE
}

export function isChangePasswordFormValid(password: string, confirmPassword: string): boolean {
  return meetsPasswordPolicy(password) && password === confirmPassword
}

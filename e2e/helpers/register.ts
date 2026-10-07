import { expect, type Page } from '@playwright/test'

export type RegisterRole = 'school' | 'company'

export const VALID = {
  fullName: 'Test Account Owner',
  password: 'ValidPass1',
  phone: '09171234567',
  location: 'Imus City',
  barangay: 'Alapan I-A',
  addressLine: 'Rm 101, Test Building',
  schoolName: 'Playwright Test School',
  contactPerson: 'OJT Coordinator',
  companyName: 'Playwright Test Company',
  industry: 'Technology',
} as const

export function uniqueEmail(role: RegisterRole, tag = 'ok') {
  return `e2e.${role}.${tag}.${Date.now()}@example.com`
}

export async function gotoRegister(page: Page, role: RegisterRole) {
  await page.goto(role === 'school' ? '/register/school' : '/register/company')
  await expect(page.getByRole('heading', { name: /Register Your Organization/i })).toBeVisible()
}

export async function fillCommonValid(page: Page, email: string, overrides: Partial<typeof VALID> = {}) {
  const data = { ...VALID, ...overrides }
  await page.locator('#fullName').fill(data.fullName)
  await page.locator('#registerEmail').fill(email)
  await page.locator('#registerPassword').fill(data.password)
  await page.locator('#confirmPassword').fill(data.password)
  await page.locator('#contactPhone').fill(data.phone)
  await page.locator('#locationArea').selectOption(data.location)
  await page.locator('#barangay').selectOption(data.barangay)
  await page.locator('#addressLine').fill(data.addressLine)
}

export async function fillSchoolOnly(page: Page) {
  await page.locator('#schoolName').fill(VALID.schoolName)
  await page.locator('#contactPerson').fill(VALID.contactPerson)
}

export async function fillCompanyOnly(page: Page) {
  await page.locator('#companyName').fill(VALID.companyName)
  await page.locator('#industry').selectOption(VALID.industry)
}

export async function fillValidRegistration(page: Page, role: RegisterRole, email: string) {
  await fillCommonValid(page, email)
  if (role === 'school') await fillSchoolOnly(page)
  else await fillCompanyOnly(page)
}

export async function submitRegister(page: Page) {
  await page.getByRole('button', { name: /Create .* Account/i }).click()
}

export async function expectStillOnRegister(page: Page) {
  await expect(page).toHaveURL(/\/register/)
}

export async function expectFieldError(page: Page, message: string | RegExp) {
  await expect(page.locator('p.text-red-600').filter({ hasText: message }).first()).toBeVisible()
}

export async function expectAnyValidationError(page: Page) {
  await expect(page.locator('p.text-red-600').first()).toBeVisible()
}

export async function expectServerOrClientError(page: Page, pattern: RegExp) {
  const client = page.locator('p.text-red-600').filter({ hasText: pattern })
  const alert = page.getByRole('alert').filter({ hasText: pattern })
  await expect(client.or(alert).first()).toBeVisible({ timeout: 15_000 })
}

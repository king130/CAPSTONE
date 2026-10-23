import { expect, type Page } from '@playwright/test'
import { VALID } from './register'

export function uniqueMunicipalEmail(tag = 'mh') {
  return `e2e.municipal.${tag}.${Date.now()}@example.com`
}

export async function gotoMunicipalRegister(page: Page) {
  await page.goto('/register/municipal-hall')
  await expect(page.getByRole('heading', { name: /Register Your Organization/i })).toBeVisible()
}

export async function fillMunicipalRegistration(page: Page, email: string, officialName: string) {
  await page.locator('#fullName').fill(`QA-${VALID.fullName}`)
  await page.locator('#registerEmail').fill(email)
  await page.locator('#registerPassword').fill(VALID.password)
  await page.locator('#confirmPassword').fill(VALID.password)
  await page.locator('#contactPhone').fill(VALID.phone)
  await page.locator('#officialName').fill(officialName)
  await page.locator('#lguType').selectOption('municipality')
  const repName = page.locator('#representativeName')
  if (await repName.count()) {
    await repName.fill(`QA-${VALID.contactPerson}`)
  }
  const repPos = page.locator('#representativePosition')
  if (await repPos.count()) {
    await repPos.fill('Municipal Administrator')
  }
  await page.locator('#locationArea').selectOption(VALID.location)
  await page.locator('#barangay').selectOption(VALID.barangay)
  await page.locator('#addressLine').fill(`QA-${VALID.addressLine}`)
}

export async function submitMunicipalRegister(page: Page) {
  await page.getByRole('button', { name: /Create .* Account/i }).click()
}

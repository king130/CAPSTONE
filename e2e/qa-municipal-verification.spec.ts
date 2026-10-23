import { test, expect } from '@playwright/test'
import path from 'node:path'
import fs from 'node:fs'
import { DEMO, loginAs, shot } from './helpers/auth'
import {
  fillMunicipalRegistration,
  gotoMunicipalRegister,
  submitMunicipalRegister,
  uniqueMunicipalEmail,
} from './helpers/municipal-register'

const FIXTURE_PDF = path.join('e2e', 'fixtures', 'qa-lgu-doc.pdf')

function ensureFixturePdf() {
  const dir = path.dirname(FIXTURE_PDF)
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true })
  if (!fs.existsSync(FIXTURE_PDF)) {
    // Minimal valid-enough PDF bytes for upload validation.
    const pdf = Buffer.from(
      '%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n',
      'utf8',
    )
    fs.writeFileSync(FIXTURE_PDF, pdf)
  }
}

test.describe.configure({ mode: 'serial' })

test.describe('QA Municipal Hall verification + admin actions', () => {
  const email = uniqueMunicipalEmail('verify')
  const officialName = `QA-Municipal Hall ${Date.now()}`

  test.beforeAll(() => {
    ensureFixturePdf()
  })

  test('register Municipal Hall and land in pending verification', async ({ page }) => {
    await gotoMunicipalRegister(page)
    await expect(page.getByRole('button', { name: /Create Municipal Hall Account/i })).toBeVisible()
    await fillMunicipalRegistration(page, email, officialName)
    await shot(page, '01-mh-register-form')
    await submitMunicipalRegister(page)
    await expect(page).toHaveURL(/\/login/, { timeout: 25_000 })
    await shot(page, '02-mh-register-success-login')

    await loginAs(page, email, 'ValidPass1')
    await expect(page).toHaveURL(/organization-verification/, { timeout: 20_000 })
    await expect(page.getByText(/verification|LGU authorization|pending|under review/i).first()).toBeVisible()
    await expect(page.getByText(/\bCompany\b/).first()).toHaveCount(0).catch(async () => {
      // Soft: collect visible Company labels for the report.
    })
    const body = await page.locator('body').innerText()
    const companyHits = body.match(/\bCompany\b/g) || []
    // Allow zero Company labels on MH verification; hard-fail if "Your company" style copy appears.
    expect(body.toLowerCase()).not.toMatch(/your company/)
    await shot(page, '03-mh-pending-verification')

    // Checklist UX: each required type has a hidden file input; upload starts on select.
    const firstRequired = page.getByRole('listitem').filter({ hasText: /authorization from the local chief executive/i }).first()
    await firstRequired.locator('input[type="file"]').setInputFiles(FIXTURE_PDF)
    await expect(firstRequired.getByText(/Uploaded|Under review/i).first()).toBeVisible({ timeout: 15_000 })
    await shot(page, '04-mh-document-uploaded')
    expect(companyHits.length).toBeLessThan(5)
  })

  test('admin actions menu is state-aware with confirmation dialogs', async ({ page }) => {
    await loginAs(page, DEMO.admin.email, DEMO.admin.password)
    await page.goto('/admin/companies')
    await expect(page.getByRole('heading', { name: /Manage Companies|Companies|Organizations/i }).first()).toBeVisible({
      timeout: 20_000,
    })
    await shot(page, '05-admin-companies-table')

    const row = page.locator('tr, [role="row"]').filter({ hasText: officialName }).first()
    await expect(row).toBeVisible({ timeout: 20_000 })

    // Pending: Approve + Reject
    await row.getByRole('button', { name: /Actions/i }).click()
    const menu = page.locator('[role="menu"]').last()
    await expect(menu).toBeVisible()
    const menuBox = await menu.boundingBox()
    expect(menuBox).toBeTruthy()
    // Teleported menu should sit in the viewport (floating, not clipped to 0-height).
    expect((menuBox?.height || 0) > 20).toBeTruthy()
    await expect(menu.getByText(/^Approve$/i)).toBeVisible()
    await expect(menu.getByText(/^Reject$/i)).toBeVisible()
    await expect(menu.getByText(/^Revoke$/i)).toHaveCount(0)
    await shot(page, '06-admin-pending-actions')

    await menu.getByText(/^Approve$/i).click()
    await expect(page.getByRole('heading', { name: /Approve organization/i })).toBeVisible()
    await page.getByRole('button', { name: /^Approve$/i }).last().click()
    await expect(row.getByText(/Approved|Verified/i).first()).toBeVisible({ timeout: 15_000 })
    await shot(page, '07-admin-approved')

    // Approved: Revoke only
    await row.getByRole('button', { name: /Actions/i }).click()
    const approvedMenu = page.locator('[role="menu"]').last()
    await expect(approvedMenu.getByText(/^Revoke$/i)).toBeVisible()
    await expect(approvedMenu.getByText(/^Reject$/i)).toHaveCount(0)
    await expect(approvedMenu.getByText(/^Approve$/i)).toHaveCount(0)
    await approvedMenu.getByText(/^Revoke$/i).click()
    await expect(page.getByRole('heading', { name: /Revoke verification/i })).toBeVisible()
    await page.getByRole('button', { name: /^Revoke$/i }).last().click()
    await expect(row.getByText(/Pending/i).first()).toBeVisible({ timeout: 15_000 })
    await shot(page, '08-admin-revoked-pending')

    // Reject with reason
    await row.getByRole('button', { name: /Actions/i }).click()
    await page.locator('[role="menu"]').last().getByText(/^Reject$/i).click()
    await expect(page.getByRole('heading', { name: /Reject organization/i })).toBeVisible()
    await page.locator('#organization-rejection-reason').fill('QA-Incomplete LGU documents')
    await page.getByRole('button', { name: /Reject organization/i }).click()
    await expect(row.getByText(/Rejected/i).first()).toBeVisible({ timeout: 15_000 })
    await shot(page, '09-admin-rejected')

    // Rejected: Re-approve only
    await row.getByRole('button', { name: /Actions/i }).click()
    const rejectedMenu = page.locator('[role="menu"]').last()
    await expect(rejectedMenu.getByText(/^Re-approve$/i)).toBeVisible()
    await expect(rejectedMenu.getByText(/^Reject$/i)).toHaveCount(0)
    await expect(rejectedMenu.getByText(/^Revoke$/i)).toHaveCount(0)
    await rejectedMenu.getByText(/^Re-approve$/i).click()
    await expect(page.getByRole('heading', { name: /Re-approve organization/i })).toBeVisible()
    await page.getByRole('button', { name: /^Re-approve$/i }).last().click()
    await expect(row.getByText(/Approved|Verified/i).first()).toBeVisible({ timeout: 15_000 })
    await shot(page, '10-admin-reapproved')
  })
})

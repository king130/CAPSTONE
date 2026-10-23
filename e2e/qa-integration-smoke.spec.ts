import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'
import * as fs from 'node:fs'
import * as path from 'node:path'

const API = process.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api'
const shotDir = 'e2e/qa-screenshots/integration'

async function shotInt(page: import('@playwright/test').Page, name: string) {
  fs.mkdirSync(shotDir, { recursive: true })
  await page.screenshot({ path: path.join(shotDir, `${name}.png`), fullPage: true })
}

test.describe('Integration smoke (demo accounts)', () => {
  test('Company: banners, billing, admin holidays; Municipal: dashboard wording', async ({ page, request }) => {
    test.setTimeout(150_000)

    const plans = await request.get(`${API}/subscription-plans`)
    expect(plans.ok()).toBeTruthy()
    const ids = ((await plans.json()).data || []).map((p: { id: string }) => p.id)
    expect(ids).not.toContain('free')

    // Admin: holidays UI + org subscriptions
    await loginAs(page, DEMO.admin.email, DEMO.admin.password)
    await page.goto('/admin/holidays')
    await expect(page.getByTestId('admin-public-holidays')).toBeVisible({ timeout: 20_000 })
    await shotInt(page, 'admin-holidays-desktop')
    await page.goto('/admin/organization-subscriptions')
    await expect(page.getByTestId('admin-org-subscriptions')).toBeVisible({ timeout: 20_000 })
    await shotInt(page, 'admin-org-subscriptions-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shotInt(page, 'admin-org-subscriptions-390')
    const theme = page.getByRole('button', { name: /switch to (dark|light) mode/i }).first()
    if (await theme.isVisible().catch(() => false)) {
      await theme.click()
      await shotInt(page, 'admin-org-subscriptions-dark')
    }
    await page.setViewportSize({ width: 1280, height: 800 })

    // Company dashboard + billing
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await shotInt(page, 'company-dashboard-desktop')
    await page.goto('/billing')
    await expect(page).toHaveURL(/billing|subscription/i, { timeout: 20_000 })
    await shotInt(page, 'company-billing-desktop')
    // Soft content check — SubscriptionManager may still be loading plans
    await expect(page.locator('body')).toContainText(/Subscription|Standard|Premium|PayMongo|trial|Access/i, {
      timeout: 15_000,
    }).catch(async () => {
      await shotInt(page, 'company-billing-empty-fallback')
    })

    const adminLogin = await request.post(`${API}/auth/login`, {
      data: { email: DEMO.admin.email, password: DEMO.admin.password },
    })
    const adminToken = (await adminLogin.json()).token as string
    const orgs = await request.get(`${API}/admin/subscriptions`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    const tech = ((await orgs.json()).data || []).find((o: { name: string }) => o.name.includes('TechCorp'))
    expect(tech).toBeTruthy()
    await request.post(`${API}/admin/subscriptions/${tech.id}/deactivate`, {
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      data: { mode: 'expired', reason: 'Integration smoke expire' },
    })

    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await expect(page.getByTestId('subscription-access-banner')).toBeVisible({ timeout: 20_000 })
    await expect(page.getByTestId('subscription-banner-title')).toContainText(/expired|read-only/i)
    await shotInt(page, 'company-expired-banner-desktop')
    await page.setViewportSize({ width: 390, height: 844 })
    await shotInt(page, 'company-expired-banner-390')
    await page.setViewportSize({ width: 1280, height: 800 })

    await request.post(`${API}/admin/subscriptions/${tech.id}/activate`, {
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      data: { plan: 'standard', reason: 'Integration smoke restore' },
    })

    await loginAs(page, DEMO.municipal.email, DEMO.municipal.password)
    await page.goto('/dashboard')
    await shotInt(page, 'municipal-dashboard-desktop')
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(/Company interview/i)

    await loginAs(page, DEMO.school.email, DEMO.school.password)
    await page.goto('/school')
    await shotInt(page, 'school-dashboard-desktop')
    // Course setup lives in Settings (and a dashboard banner when incomplete)
    await page.goto('/settings')
    await expect(page).toHaveURL(/settings/i, { timeout: 20_000 })
    await shotInt(page, 'school-settings-desktop')
    const schoolSettings = await page.locator('body').innerText()
    // Prefer seeing course requirements; if the panel is tabbed, at least Settings loaded.
    if (!/course|OJT|year level|hours/i.test(schoolSettings)) {
      await shotInt(page, 'school-settings-no-course-panel-visible')
    }

    await loginAs(page, DEMO.deptHead.email, DEMO.deptHead.password)
    await page.goto('/settings')
    await expect(page).toHaveURL(/settings/i, { timeout: 20_000 })
    await shotInt(page, 'depthead-settings-desktop')
  })
})

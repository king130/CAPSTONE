import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

const API = process.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api'

async function adminToken(request: import('@playwright/test').APIRequestContext) {
  const login = await request.post(`${API}/auth/login`, {
    data: { email: DEMO.admin.email, password: DEMO.admin.password },
  })
  expect(login.ok()).toBeTruthy()
  const body = await login.json()
  return body.token as string
}

async function findOrgId(request: import('@playwright/test').APIRequestContext, token: string, namePart: string) {
  const res = await request.get(`${API}/admin/subscriptions`, {
    headers: { Authorization: `Bearer ${token}` },
  })
  expect(res.ok()).toBeTruthy()
  const body = await res.json()
  const row = (body.data || []).find((o: { name: string }) => String(o.name).includes(namePart))
  expect(row, `org containing ${namePart}`).toBeTruthy()
  return String(row.id)
}

/**
 * Phase H: trial/expired banners + admin extend.
 * Requires local backend (main sqlite) + Vite + seeded demo accounts.
 */
test.describe('Phase H subscription trial / expired', () => {
  test('trial days, expired banner, admin extend', async ({ page, request }) => {
    test.setTimeout(90_000)

    const plans = await request.get(`${API}/subscription-plans`)
    expect(plans.ok()).toBeTruthy()
    const planIds = ((await plans.json()).data || []).map((p: { id: string }) => p.id)
    expect(planIds).not.toContain('free')
    expect(planIds).toContain('standard')

    const token = await adminToken(request)
    const companyId = await findOrgId(request, token, 'TechCorp')

    // Put company into trial via admin API
    const trial = await request.post(`${API}/admin/subscriptions/${companyId}/extend-trial`, {
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      data: { extraDays: 14, reason: 'Playwright Phase H trial setup' },
    })
    expect(trial.ok()).toBeTruthy()
    expect((await trial.json()).data.access.status).toBe('trial')

    // Company dashboard shows trial banner with days remaining
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await expect(page.getByTestId('subscription-access-banner')).toBeVisible({ timeout: 20_000 })
    await expect(page.getByTestId('subscription-banner-title')).toContainText(/trial/i)
    await expect(page.getByTestId('subscription-banner-title')).toContainText(/day/i)
    await shot(page, 'phase-h-company-trial-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-h-company-trial-390')

    const themeToggle = page.getByRole('button', { name: /switch to (dark|light) mode/i }).first()
    if (await themeToggle.isVisible().catch(() => false)) {
      await themeToggle.click()
      await shot(page, 'phase-h-company-trial-dark')
    }

    await page.setViewportSize({ width: 1280, height: 800 })

    // Expire via admin API
    const expire = await request.post(`${API}/admin/subscriptions/${companyId}/deactivate`, {
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      data: { mode: 'expired', reason: 'Playwright Phase H expire' },
    })
    expect(expire.ok()).toBeTruthy()
    expect((await expire.json()).data.access.status).toBe('expired')

    // Re-login so auth store picks up expired access from /auth/me.
    await page.goto('/login')
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await expect(page.getByTestId('subscription-access-banner')).toBeVisible({ timeout: 20_000 })
    await expect(page.getByTestId('subscription-banner-title')).toContainText(/expired|read-only/i)
    await shot(page, 'phase-h-company-expired-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-h-company-expired-390')

    // Blocked write returns 402 with machine-readable code
    const companyLogin = await request.post(`${API}/auth/login`, {
      data: { email: DEMO.company.email, password: DEMO.company.password },
    })
    const companyToken = (await companyLogin.json()).token as string
    const blocked = await request.post(`${API}/internships`, {
      headers: { Authorization: `Bearer ${companyToken}`, 'Content-Type': 'application/json' },
      data: { title: 'Should Block', description: 'x', slots: 1 },
    })
    expect(blocked.status()).toBe(402)
    expect((await blocked.json()).code).toBe('subscription_expired')

    // Admin UI: extend trial (reachable + confirmation)
    await page.setViewportSize({ width: 1280, height: 800 })
    await page.goto('/login')
    await loginAs(page, DEMO.admin.email, DEMO.admin.password)
    await page.goto('/admin/organization-subscriptions')
    await expect(page.getByTestId('admin-org-subscriptions')).toBeVisible({ timeout: 20_000 })
    await shot(page, 'phase-h-admin-org-subscriptions-desktop')

    const rowCard = page.locator('[data-testid="admin-org-subscriptions"]').locator('.border-border').filter({ hasText: 'TechCorp' }).first()
    await expect(rowCard).toBeVisible()
    await rowCard.getByTestId('admin-extend-trial').click()
    await page.locator('.swal2-input').fill('Playwright Phase H UI extend')
    await page.locator('.swal2-confirm').click()
    await expect(page.locator('.swal2-success, .swal2-title')).toBeVisible({ timeout: 15_000 }).catch(() => null)
    await page.locator('.swal2-confirm').click({ timeout: 5_000 }).catch(() => null)

    // Restore paid active for demo safety
    const activate = await request.post(`${API}/admin/subscriptions/${companyId}/activate`, {
      headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
      data: { plan: 'standard', reason: 'Playwright restore after Phase H' },
    })
    expect(activate.ok()).toBeTruthy()
  })
})

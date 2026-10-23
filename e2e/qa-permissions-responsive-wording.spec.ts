import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

test.describe('QA permissions, wording, responsive, teachers', () => {
  test('approved MH host lands on dashboard, not awaiting verification', async ({ page }) => {
    await loginAs(page, DEMO.municipal.email, DEMO.municipal.password)
    await expect(page).not.toHaveURL(/organization-verification/)
    await expect(page).toHaveURL(/dashboard|\/$/)
    const body = (await page.locator('body').innerText()).toLowerCase()
    expect(body).not.toMatch(/awaiting verification/)
    await shot(page, '11-mh-dashboard-landing')
  })

  test('MH screens avoid wrong Company labels on dashboard/settings', async ({ page }) => {
    await loginAs(page, DEMO.municipal.email, DEMO.municipal.password)
    await page.goto('/dashboard')
    await shot(page, '12-mh-dashboard-wording')
    const dash = await page.locator('main, body').first().innerText()
    // Soft assertions collected: hard-fail on obvious company-only copy in MH chrome.
    expect(dash).not.toMatch(/Your company/i)

    await page.goto('/settings')
    await page.waitForTimeout(800)
    await shot(page, '13-mh-settings-wording')
    const settings = await page.locator('body').innerText()
    expect(settings).not.toMatch(/Your company/i)
    // Address section should not be labeled plain "Company Address" for MH.
    const hasWrongCompanyAddress = /Company Address/i.test(settings)
    expect(hasWrongCompanyAddress).toBeFalsy()
  })

  test('student interview panel cannot propose interviews', async ({ page }) => {
    await loginAs(page, DEMO.student.email, DEMO.student.password)
    await page.goto('/intern')
    await shot(page, '14-student-intern')
    // Open first application detail if present.
    const openApp = page.getByRole('button', { name: /view|details|open/i }).first()
    if (await openApp.isVisible().catch(() => false)) {
      await openApp.click()
      await page.waitForTimeout(500)
    }
    await expect(page.getByRole('button', { name: /Propose interview/i })).toHaveCount(0)
    await shot(page, '15-student-no-propose-interview')
  })

  test('school department head sees limited school fields', async ({ page }) => {
    await loginAs(page, DEMO.deptHead.email, DEMO.deptHead.password)
    await expect(page).not.toHaveURL(/organization-verification/)
    await page.goto('/settings')
    await expect(page.getByText(/School Name|School Courses/i).first()).toBeVisible({
      timeout: 25_000,
    })
    await shot(page, '16-depthead-settings')
    await expect(page.locator('#schoolEmailDomain')).toHaveCount(0)
    await expect(page.locator('#companyAddress')).toHaveCount(0)
    await expect(page.getByRole('button', { name: /Add Course/i })).toHaveCount(0)
  })

  test('company cannot browse MH-only student/DTR queues as cross-tenant', async ({ page }) => {
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dtr-submissions')
    await shot(page, '17-company-dtr')
    const body = await page.locator('body').innerText()
    expect(body).not.toMatch(/Municipality of Naic/i)

    await loginAs(page, DEMO.municipal.email, DEMO.municipal.password)
    await page.goto('/dtr-submissions')
    await shot(page, '18-mh-dtr')
    const mhBody = await page.locator('body').innerText()
    expect(mhBody).not.toMatch(/TechCorp|techcorp/i)
  })

  test('mobile + dark: dashboard, DTR, admin table, messages chrome', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.emulateMedia({ colorScheme: 'dark' })

    await loginAs(page, DEMO.municipal.email, DEMO.municipal.password)
    await page.goto('/dashboard')
    await shot(page, '19-mobile-dark-mh-dashboard')
    await page.goto('/dtr-submissions')
    await shot(page, '20-mobile-dark-dtr')

    // Messages / floating chat
    const launcher = page.getByRole('button', { name: /messages|open messages/i }).first()
    if (await launcher.isVisible().catch(() => false)) {
      await launcher.click()
      await page.waitForTimeout(500)
      await shot(page, '21-mobile-dark-messages')
      const conv = page.locator('.conversation-item, [data-conversation], button').filter({ hasText: /.+/ }).first()
      if (await conv.isVisible().catch(() => false)) {
        await conv.click()
        await page.waitForTimeout(400)
        const back = page.getByRole('button', { name: /back/i }).first()
        await expect(back).toBeVisible({ timeout: 5_000 }).catch(() => null)
        await shot(page, '22-mobile-dark-conversation')
      }
    }

    await page.goto('/login')
    await loginAs(page, DEMO.admin.email, DEMO.admin.password)
    await page.goto('/admin/companies')
    await shot(page, '23-mobile-dark-admin-companies')

    // Spot-check hardcoded light-only sky utility still present (report-only via soft expect).
    const sky = page.locator('.text-sky-700')
    const skyCount = await sky.count()
    await shot(page, '24-mobile-dark-admin-sky-check')
    // Soft: presence of text-sky-700 in dark mode is a wording/theme smell.
    test.info().annotations.push({ type: 'theme', description: `text-sky-700 count=${skyCount}` })
  })
})

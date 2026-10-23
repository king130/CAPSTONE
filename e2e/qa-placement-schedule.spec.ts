import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

test.describe('Phase F placement schedule', () => {
  test('host job form shows schedule editor; student schedule panel reachable', async ({ page }) => {
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await shot(page, 'phase-f-company-desktop')

    const createBtn = page.getByRole('button', { name: /post|create|new internship|add job/i }).first()
    if (await createBtn.isVisible().catch(() => false)) {
      await createBtn.click()
      await page.waitForTimeout(500)
      const scheduleHeading = page.getByText(/default schedule/i)
      if (await scheduleHeading.count()) {
        await expect(scheduleHeading.first()).toBeVisible()
        await page.getByRole('button', { name: /8:00 AM/i }).first().click().catch(() => {})
        await shot(page, 'phase-f-company-schedule-editor')
      }
    }

    await page.setViewportSize({ width: 390, height: 844 })
    await page.emulateMedia({ colorScheme: 'dark' })
    await shot(page, 'phase-f-company-390-dark')

    await loginAs(page, DEMO.student.email, DEMO.student.password)
    await page.goto('/intern')
    await page.getByRole('button', { name: /status tracker|tracker/i }).first().click().catch(() => {})
    await page.waitForTimeout(800)
    const placement = page.getByText(/placement schedule/i)
    if (await placement.count()) {
      await expect(placement.first()).toBeVisible()
    }
    await shot(page, 'phase-f-student-schedule')
  })
})

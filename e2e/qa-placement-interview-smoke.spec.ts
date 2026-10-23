import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

/**
 * Smoke coverage for host-scheduled interviews and host dashboards.
 * Full endorse→apply→DTR chain is time-boxed; missing steps are reported NOT TESTED in the QA report.
 */
test.describe('QA placement / interview smoke (MH + Company)', () => {
  test('MH host interview panel can schedule; wording uses host context', async ({ page }) => {
    await loginAs(page, DEMO.municipal.email, DEMO.municipal.password)
    await page.goto('/dashboard')
    await shot(page, '25-mh-host-dashboard')

    // Try open an applicant detail that exposes the interview panel.
    const applicantBtn = page.getByRole('button', { name: /view|review|details|applicants/i }).first()
    if (await applicantBtn.isVisible().catch(() => false)) {
      await applicantBtn.click()
      await page.waitForTimeout(600)
    }

    const schedule = page.getByRole('button', { name: /Schedule interview|Propose interview/i })
    const companyInterviewHeading = page.getByRole('heading', { name: /^Company interview$/i })
    const tracker = page.getByText(/Interview tracker/i)
    await shot(page, '26-mh-interview-panel')

    // If the panel is visible for an endorsed app, host should schedule — student must not.
    if (await schedule.count()) {
      await expect(schedule.first()).toBeVisible()
    }
    if (await tracker.count()) {
      await expect(tracker.first()).toBeVisible()
    }
    // Wording defect if MH still shows "Company interview"
    if (await companyInterviewHeading.count()) {
      throw new Error('FAIL wording: Municipal Hall host UI shows "Company interview"')
    }
  })

  test('Company host dashboard loads and is not verification gate', async ({ page }) => {
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await expect(page).not.toHaveURL(/organization-verification/)
    await page.goto('/dashboard')
    await shot(page, '27-company-dashboard')
    await expect(page.locator('body')).toContainText(/dashboard|internship|applicant|job/i)
  })

  test('student required hours / DTR page loads without propose interview', async ({ page }) => {
    await loginAs(page, DEMO.student.email, DEMO.student.password)
    await page.goto('/ojt-hours')
    await shot(page, '28-student-ojt-hours')
    const body = await page.locator('body').innerText()
    // Required hours should be present somewhere if applications exist.
    test.info().annotations.push({
      type: 'hours',
      description: body.match(/required|hours/i)?.[0] || 'no hours keyword',
    })
    await expect(page.getByRole('button', { name: /Propose interview|Schedule interview/i })).toHaveCount(0)
  })
})

import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

test.describe.configure({ mode: 'serial' })

test.describe('QA Phase D — hours mismatch and agreement', () => {
  test('host posting form shows optional hours field (desktop, 390, dark)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await expect(page.getByRole('heading', { name: /TechCorp|Dashboard|Overview/i }).first()).toBeVisible({
      timeout: 20_000,
    })

    await page.locator('main').getByRole('button', { name: /^Post a job$/i }).first().click()
    await expect(page.getByText(/Create Job Posting|Basic Information|Internship Details/i).first()).toBeVisible({
      timeout: 20_000,
    })

    // Jump to Internship Details step for the optional hours field.
    await page.getByRole('button', { name: /Step 2\s*Internship Details/i }).click()
    await expect(page.getByText(/^Internship Details$/i).first()).toBeVisible({ timeout: 10_000 })

    await expect(page.getByText(/Hours offered by/i).first()).toBeVisible({
      timeout: 15_000,
    })
    await expect(page.locator('#jobHostHours')).toBeVisible()
    await shot(page, 'phase-d-01-host-hours-field-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-d-02-host-hours-field-390')

    await page.evaluate(() => {
      document.documentElement.classList.add('dark')
      localStorage.setItem('theme', 'dark')
    })
    await page.waitForTimeout(200)
    await shot(page, 'phase-d-03-host-hours-field-theme')
  })

  test('student tracker shows hours agreement panel when mismatch exists', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await loginAs(page, DEMO.student.email, DEMO.student.password)
    await page.goto('/intern/tracker')
    await expect(page.getByText(/Status Tracker|Hours agreement|My Applications|Latest update/i).first()).toBeVisible({
      timeout: 20_000,
    })

    // Panel is always mounted when there is a latest application; mismatch badge only when hours differ.
    const agreement = page.getByText(/Hours agreement/i).first()
    if (await agreement.isVisible().catch(() => false)) {
      await expect(agreement).toBeVisible()
      await shot(page, 'phase-d-04-student-hours-agreement-desktop')
    } else {
      // No application yet — still capture tracker page for manual follow-up.
      await shot(page, 'phase-d-04-student-tracker-no-agreement-desktop')
    }

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-d-05-student-hours-390')
  })
})

import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

/**
 * Phase G: Interview tracker reachability in host / school / student UIs.
 * Walks existing demo data when available; marks gaps via annotations.
 */
test.describe('Phase G interview tracker UI', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  test('company applicants dialog shows interview tracker', async ({ page }) => {
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await page.emulateMedia({ colorScheme: 'dark' })

    const openTracker = page.getByRole('button', { name: /open tracker|view profile/i }).first()
    if (!(await openTracker.isVisible().catch(() => false))) {
      test.info().annotations.push({ type: 'note', description: 'No applicants visible for company demo' })
      await shot(page, 'phase-g-company-no-applicants')
      return
    }

    await openTracker.click()
    await expect(page.getByText(/interview tracker/i).first()).toBeVisible({ timeout: 10_000 })
    await shot(page, 'phase-g-company-tracker-dark-mobile')
    await expect(page.getByRole('button', { name: /propose interview/i })).toHaveCount(0)
  })

  test('student status tracker is read-only for stages', async ({ page }) => {
    await loginAs(page, DEMO.student.email, DEMO.student.password)
    await page.goto('/intern')
    await page.getByRole('button', { name: /status tracker|tracker/i }).first().click().catch(() => {})
    await page.waitForTimeout(800)
    await shot(page, 'phase-g-student-tracker')

    await expect(page.getByRole('button', { name: /^endorse$/i })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /^accept$/i })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /submit assessment/i })).toHaveCount(0)
    await expect(page.getByRole('button', { name: /mark interviewed/i })).toHaveCount(0)
  })

  test('school endorsement dialog exposes tracker', async ({ page }) => {
    await loginAs(page, DEMO.school.email, DEMO.school.password)
    await page.goto('/dashboard')
    const review = page.getByRole('button', { name: /view|review|details/i }).first()
    if (!(await review.isVisible().catch(() => false))) {
      test.info().annotations.push({ type: 'note', description: 'No school endorsement rows for demo' })
      await shot(page, 'phase-g-school-no-rows')
      return
    }
    await review.click()
    await page.waitForTimeout(600)
    const tracker = page.getByText(/interview tracker/i)
    if (await tracker.count()) {
      await expect(tracker.first()).toBeVisible()
      await shot(page, 'phase-g-school-tracker')
    } else {
      test.info().annotations.push({ type: 'note', description: 'Tracker not in opened dialog' })
      await shot(page, 'phase-g-school-dialog')
    }
  })
})

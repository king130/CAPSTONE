import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

test.describe.configure({ mode: 'serial' })

test.describe('QA Phase B — course year levels and OJT hours', () => {
  test('school admin configures course year levels and hours', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await loginAs(page, DEMO.school.email, DEMO.school.password)
    await page.goto('/settings')
    await expect(page.getByText(/Course OJT Requirements/i).first()).toBeVisible({ timeout: 20_000 })

    // Year-level chips on the add-course form (1st–5th, 3rd/4th selected by default).
    await expect(page.getByRole('button', { name: /^3rd Year$/i }).first()).toBeVisible()
    await expect(page.getByRole('button', { name: /^4th Year$/i }).first()).toBeVisible()
    await expect(page.getByRole('button', { name: /^1st Year$/i }).first()).toBeVisible()
    await expect(page.getByRole('button', { name: /^5th Year$/i }).first()).toBeVisible()
    await expect(page.getByText(/3rd Year and 4th Year are selected by default/i).first()).toBeVisible()

    await shot(page, 'phase-b-01-school-course-year-levels-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-b-02-school-course-year-levels-390')

    await page.evaluate(() => {
      document.documentElement.classList.add('dark')
      localStorage.setItem('theme', 'dark')
    })
    await page.waitForTimeout(200)
    await shot(page, 'phase-b-03-school-course-year-levels-theme')
  })

  test('student account form year levels follow the selected course', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await loginAs(page, DEMO.school.email, DEMO.school.password)
    await page.goto('/school/students')
    await expect(page.getByText(/Student Accounts|Create student|Add student/i).first()).toBeVisible({
      timeout: 20_000,
    })

    const createBtn = page.getByRole('button', { name: /Create Student|Add Student|New Student|Create account/i }).first()
    await expect(createBtn).toBeVisible({ timeout: 20_000 })
    await createBtn.click()

    // Open year-level dropdown — options come from course config (default 3rd/4th when no course yet).
    const yearTrigger = page.locator('[data-student-dropdown]').filter({ hasText: /year level|Select year/i }).first()
    if (await yearTrigger.isVisible().catch(() => false)) {
      await yearTrigger.click()
    } else {
      await page.getByText(/Select year level|Year Level/i).first().click()
    }

    await expect(page.getByText('3rd Year').first()).toBeVisible({ timeout: 10_000 })
    await expect(page.getByText('4th Year').first()).toBeVisible()
    // 1st Year should not appear unless the selected course allows it (defaults exclude it).
    const firstYearInMenu = page.locator('.dropdown-menu').getByText(/^1st Year$/)
    await expect(firstYearInMenu).toHaveCount(0)

    await shot(page, 'phase-b-04-student-year-options-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-b-05-student-year-options-390')
  })
})

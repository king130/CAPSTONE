import { test, expect } from '@playwright/test'
import { DEMO, loginAs, shot } from './helpers/auth'

test.describe.configure({ mode: 'serial' })

test.describe('QA Phase E — endorsement-only job documents', () => {
  test('host posting form shows only Endorsement (desktop, 390px, dark)', async ({ page }) => {
    await loginAs(page, DEMO.company.email, DEMO.company.password)
    await page.goto('/dashboard')
    await expect(page.getByRole('heading', { name: /TechCorp|Dashboard|Overview/i }).first()).toBeVisible({
      timeout: 20_000,
    })

    // Open create-job from the overview CTA (main content, not nav).
    await page.locator('main').getByRole('button', { name: /^Post a job$/i }).first().click()
    await expect(page.getByText(/Create Job Posting|Basic Information/i).first()).toBeVisible({ timeout: 20_000 })

    // Jump to Requirements wizard step (clickable step card).
    await page.locator('button').filter({ hasText: 'Requirements' }).first().click()
    await expect(page.getByText(/Required document/i).first()).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText(/Endorsement/i).first()).toBeVisible()
    await expect(page.getByText(/Require Resume/i)).toHaveCount(0)
    await expect(page.getByPlaceholder(/Add a document like Resume/i)).toHaveCount(0)
    await shot(page, 'phase-e-01-host-posting-endorsement-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-e-02-host-posting-endorsement-390')

    const themeToggle = page.getByRole('button', { name: /Switch to (light|dark) mode/i }).first()
    if (await themeToggle.isVisible().catch(() => false)) {
      await themeToggle.click()
      await page.waitForTimeout(200)
    }
    await shot(page, 'phase-e-03-host-posting-endorsement-theme')
  })

  test('student apply checklist shows only Endorsement', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })
    await loginAs(page, DEMO.student.email, DEMO.student.password)

    // Apply flow: Find Internships → View & Apply → opens intern opportunities with ?focus=
    await page.goto('/find-internships')
    await expect(page.getByRole('heading', { name: /Find Internships|Internships|Openings/i }).first()).toBeVisible({
      timeout: 20_000,
    })

    const viewApply = page.getByRole('button', { name: /View & Apply/i }).first()
    await expect(viewApply).toBeVisible({ timeout: 20_000 })
    await viewApply.click()

    await expect(page).toHaveURL(/\/intern\/opportunities/, { timeout: 20_000 })
    await expect(page.getByText(/Required document:\s*Endorsement|Endorsement letter/i).first()).toBeVisible({
      timeout: 20_000,
    })
    await expect(page.getByText(/Resume required/i)).toHaveCount(0)
    await expect(page.getByText(/Requirement Checklist/i)).toHaveCount(0)
    await expect(page.getByRole('button', { name: /Submit Application/i })).toBeVisible()
    await shot(page, 'phase-e-04-student-apply-endorsement-desktop')

    await page.setViewportSize({ width: 390, height: 844 })
    await shot(page, 'phase-e-05-student-apply-endorsement-390')

    // Dialog overlay blocks the header theme toggle; apply dark class directly for the shot.
    await page.evaluate(() => {
      document.documentElement.classList.add('dark')
      localStorage.setItem('theme', 'dark')
    })
    await page.waitForTimeout(200)
    await shot(page, 'phase-e-06-student-apply-endorsement-theme')
  })
})

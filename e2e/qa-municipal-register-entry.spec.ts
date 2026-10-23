import { test, expect } from '@playwright/test'
import path from 'node:path'
import fs from 'node:fs'
import { fillMunicipalRegistration, uniqueMunicipalEmail } from './helpers/municipal-register'
import { shot } from './helpers/auth'

const FIXTURE_PDF = path.join('e2e', 'fixtures', 'qa-lgu-doc.pdf')

function ensureFixturePdf() {
  const dir = path.dirname(FIXTURE_PDF)
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true })
  if (!fs.existsSync(FIXTURE_PDF)) {
    fs.writeFileSync(FIXTURE_PDF, Buffer.from('%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n', 'utf8'))
  }
}

test.describe('Municipal Hall register entry wiring', () => {
  test.beforeAll(() => {
    ensureFixturePdf()
    fs.mkdirSync('e2e/qa-screenshots', { recursive: true })
  })

  test('generic /register exposes Municipal Hall and completes signup + login', async ({ page }) => {
    const email = uniqueMunicipalEmail('entry')
    const officialName = `QA-MH Entry ${Date.now()}`

    await page.goto('/register')
    await expect(page.getByRole('heading', { name: /Register Your Organization/i })).toBeVisible()
    await expect(page.getByRole('button', { name: /Municipal Hall/i })).toBeVisible()
    await shot(page, 'mh-entry-01-register-chooser')

    await page.getByRole('button', { name: /Municipal Hall/i }).click()
    await expect(page.getByRole('button', { name: /Create Municipal Hall Account/i })).toBeVisible()
    await expect(page.locator('#officialName')).toBeVisible()
    await shot(page, 'mh-entry-02-mh-form')

    await fillMunicipalRegistration(page, email, officialName)
    await page.getByRole('button', { name: /Create Municipal Hall Account/i }).click()
    await expect(page).toHaveURL(/\/login/, { timeout: 25_000 })
    await shot(page, 'mh-entry-03-login-after-register')

    await page.locator('#email').fill(email)
    await page.locator('#password').fill('ValidPass1')
    await page.getByRole('button', { name: /^Login$/i }).click()
    await expect(page).toHaveURL(/organization-verification/, { timeout: 20_000 })
    await expect(page.getByText(/LGU|municipal|verification/i).first()).toBeVisible()
    await shot(page, 'mh-entry-04-pending-verification')
  })

  test('Landing and dedicated MH route also expose Municipal Hall', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByRole('link', { name: /Register as Municipal Hall/i })).toBeVisible()
    await shot(page, 'mh-entry-05-landing-link')

    await page.goto('/register/municipal-hall')
    await expect(page.getByRole('button', { name: /Create Municipal Hall Account/i })).toBeVisible()
    await expect(page.getByText(/Municipal Hall Setup/i).first()).toBeVisible()
    await shot(page, 'mh-entry-06-dedicated-route')
  })

  test('mobile + dark: /register shows Municipal Hall option', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.emulateMedia({ colorScheme: 'dark' })
    await page.addInitScript(() => {
      document.documentElement.classList.add('dark')
      localStorage.setItem('ojt-color-mode', 'dark')
    })
    await page.goto('/register')
    const mhChooser = page.getByRole('button', { name: /Municipal Hall/i }).first()
    await expect(mhChooser).toBeVisible()
    await mhChooser.click()
    await expect(page.getByRole('button', { name: /Create Municipal Hall Account/i })).toBeVisible()
    await expect(page.locator('#officialName')).toBeVisible()
    await shot(page, 'mh-entry-07-mobile-dark-register')
  })
})

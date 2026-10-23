import { expect, type Browser, type BrowserContext, type Page } from '@playwright/test'
import * as fs from 'node:fs'
import * as path from 'node:path'

export const DEMO = {
  admin: { email: 'admin@ojtpath.com', password: 'Admin@2026!' },
  company: { email: 'company@techcorp.com', password: 'Company@2026!' },
  municipal: { email: 'municipal@naic.gov.ph', password: 'Municipal@2026!' },
  school: { email: 'school@cvsu.edu.ph', password: 'School@2026!' },
  deptHead: { email: 'depthead@cvsu.edu.ph', password: 'DeptHead@2026!' },
  student: { email: 'student@cvsu.edu.ph', password: 'Student@2026!' },
} as const

export const TOKEN_KEY = 'capstone_auth_token'

export function laravelLogPath() {
  return path.join(process.cwd(), 'backend', 'storage', 'logs', 'laravel.log')
}

/** Byte length of laravel.log — call BEFORE creating the student / sending mail. */
export function markLaravelLogCursor(): number {
  const p = laravelLogPath()
  if (!fs.existsSync(p)) return 0
  return fs.statSync(p).size
}

/**
 * Poll only bytes appended after `afterBytes` for the newest account-setup URL.
 * Prefer a URL that appears in a chunk that also mentions `emailHint` when provided.
 */
export async function waitForAccountSetupUrl(options: {
  afterBytes: number
  emailHint?: string
  timeoutMs?: number
}): Promise<string> {
  const timeoutMs = options.timeoutMs ?? 45_000
  const deadline = Date.now() + timeoutMs
  const urlRe = /https?:\/\/127\.0\.0\.1:5173\/account-setup\?token=[A-Za-z0-9]+/g
  let lastError = 'no URL yet'

  while (Date.now() < deadline) {
    try {
      const p = laravelLogPath()
      if (!fs.existsSync(p)) {
        lastError = 'laravel.log missing'
        await new Promise((r) => setTimeout(r, 300))
        continue
      }
      const buf = fs.readFileSync(p)
      const appended = buf.subarray(Math.min(options.afterBytes, buf.length)).toString('utf8')
      if (!appended.trim()) {
        lastError = 'no new log bytes'
        await new Promise((r) => setTimeout(r, 300))
        continue
      }

      const hint = options.emailHint?.toLowerCase()
      if (hint && appended.toLowerCase().includes(hint)) {
        // Prefer URLs from the slice after the email mention when possible
        const idx = appended.toLowerCase().indexOf(hint)
        const afterEmail = appended.slice(Math.max(0, idx - 200))
        const near = [...afterEmail.matchAll(urlRe)]
        if (near.length) return near[near.length - 1][0]
      }

      const all = [...appended.matchAll(urlRe)]
      if (all.length) return all[all.length - 1][0]
      lastError = `appended ${appended.length} bytes, no account-setup URL`
    } catch (e) {
      lastError = String(e)
    }
    await new Promise((r) => setTimeout(r, 300))
  }

  throw new Error(
    `No account-setup URL in laravel.log after byte ${options.afterBytes}` +
      (options.emailHint ? ` (email hint ${options.emailHint})` : '') +
      `: ${lastError}`
  )
}

export async function readAuthToken(page: Page): Promise<string | null> {
  return page.evaluate((key) => {
    try {
      return localStorage.getItem(key)
    } catch {
      return null
    }
  }, TOKEN_KEY)
}

/** Isolated browser context per role — create once; do not clear its token mid-journey. */
export async function newRoleContext(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
  const context = await browser.newContext({
    viewport: { width: 1280, height: 800 },
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:5173',
  })
  const page = await context.newPage()
  page.setDefaultTimeout(45_000)
  return { context, page }
}

/**
 * Login only when this context has no token.
 * Never navigates to /login while a token already exists.
 */
export async function ensureLoggedIn(page: Page, email: string, password: string) {
  const existing = await readAuthToken(page)
  if (existing) return
  await loginAsFresh(page, email, password)
}

/** Wait until the main app shell is painted (avoids blank RouterView races). */
export async function waitForAppShell(page: Page) {
  await expect(
    page
      .locator('nav, aside, [data-sidebar], header, main, #app')
      .or(page.getByRole('navigation'))
      .or(page.getByRole('heading'))
      .first()
  ).toBeVisible({ timeout: 30_000 })
}

/**
 * Soft-refresh Pinia profile from /api/auth/me without visiting /login.
 * Use after backend status changes (org verified, subscription expired).
 */
export async function refreshAuthProfile(page: Page) {
  // Reload re-runs auth init → /api/auth/me so Pinia picks up verification/subscription changes.
  await page.reload({ waitUntil: 'domcontentloaded' })
  await waitForAppShell(page)
}

/**
 * Fresh login into a context that has NO token.
 * Do not call this on a context you intend to keep after clearing its token elsewhere —
 * create a new role context instead.
 */
export async function loginAsFresh(page: Page, email: string, password: string) {
  const existing = await readAuthToken(page)
  if (existing) {
    throw new Error(
      `loginAsFresh refused: context already has ${TOKEN_KEY}. Use ensureLoggedIn or a new role context.`
    )
  }

  await page.keyboard.press('Escape').catch(() => {})
  await page.goto('/login', { waitUntil: 'domcontentloaded' })
  await page.keyboard.press('Escape').catch(() => {})

  await expect(page.locator('#email, input[type="email"]').first()).toBeVisible({ timeout: 20_000 })
  await page.locator('#email, input[type="email"]').first().fill(email)
  await page.locator('#password, input[type="password"]').first().fill(password)
  await page.getByRole('button', { name: /sign in|log in|login/i }).first().click()

  try {
    await expect
      .poll(async () => readAuthToken(page), {
        timeout: 25_000,
        message: `Auth token not stored for ${email}`,
      })
      .toBeTruthy()
  } catch (firstError) {
    await page.keyboard.press('Escape').catch(() => {})
    await page.goto('/login', { waitUntil: 'domcontentloaded' })
    await page.locator('#email, input[type="email"]').first().fill(email)
    await page.locator('#password, input[type="password"]').first().fill(password)
    await page.getByRole('button', { name: /sign in|log in|login/i }).first().click()
    await expect
      .poll(async () => readAuthToken(page), {
        timeout: 20_000,
        message: `Auth token not stored for ${email} (retry). First: ${String(firstError).slice(0, 120)}`,
      })
      .toBeTruthy()
  }

  await expect(page).not.toHaveURL(/\/login\/?$/, { timeout: 25_000 })
  await page.waitForTimeout(600)
}

/** @deprecated Prefer ensureLoggedIn / loginAsFresh — kept as alias for older specs. */
export async function loginAs(page: Page, email: string, password: string) {
  await ensureLoggedIn(page, email, password)
}

/**
 * Open account-setup URL in a FRESH student context, set password, Confirm Account,
 * wait for token; if missing, login once with the new password.
 */
export async function completeStudentAccountSetup(
  browser: Browser,
  setupUrl: string,
  password: string,
  loginEmail: string
): Promise<{ context: BrowserContext; page: Page }> {
  const { context, page } = await newRoleContext(browser)
  const pathOnly = setupUrl.replace(/^https?:\/\/127\.0\.0\.1:5173/, '')
  await page.goto(pathOnly, { waitUntil: 'domcontentloaded' })

  await expect(page.getByText(/Create Your Password|Confirm Account|Set up/i).first()).toBeVisible({
    timeout: 30_000,
  })
  // Wait until validate finishes and password fields appear
  const pw0 = page.locator('input[type="password"]').nth(0)
  const pw1 = page.locator('input[type="password"]').nth(1)
  await expect(pw0).toBeVisible({ timeout: 30_000 })
  await expect(pw1).toBeVisible({ timeout: 10_000 })
  await pw0.fill(password)
  await pw1.fill(password)

  const confirm = page.getByRole('button', { name: /Confirm Account/i })
  await expect(confirm).toBeEnabled({ timeout: 10_000 })
  await confirm.click()

  try {
    await expect
      .poll(async () => readAuthToken(page), {
        timeout: 25_000,
        message: 'Token missing after Confirm Account',
      })
      .toBeTruthy()
  } catch (e) {
    // One login retry with the new password on this same fresh context (no prior token)
    const still = await readAuthToken(page)
    if (still) return { context, page }
    await loginAsFresh(page, loginEmail, password)
  }

  if (/organization-verification/i.test(page.url())) {
    throw new Error(`PRODUCT/BUG: student landed on org verification ${page.url()}`)
  }

  const token = await readAuthToken(page)
  if (!token) {
    throw new Error(
      `Account setup failed: no ${TOKEN_KEY} after Confirm Account + login retry. url=${page.url()}`
    )
  }

  // Leave account-setup so later ensureLoggedIn + role routes start from a real app shell.
  if (/account-setup/i.test(page.url())) {
    await page.goto('/intern', { waitUntil: 'domcontentloaded' })
    await waitForAppShell(page)
  }

  return { context, page }
}

export async function logoutIfNeeded(page: Page) {
  const logout = page.getByRole('button', { name: /log out|sign out/i }).first()
  if (await logout.isVisible().catch(() => false)) {
    await logout.click()
    await page.waitForTimeout(500)
  }
}

export async function shot(page: Page, name: string) {
  await page.screenshot({
    path: `e2e/qa-screenshots/${name}.png`,
    fullPage: true,
  })
}

export async function shotJourney(page: Page, name: string) {
  await page.screenshot({
    path: `e2e/qa-screenshots/e2e-journey/${name}.png`,
    fullPage: true,
  })
}

export function logStep(n: number | string, whoDoesWhat: string) {
  // eslint-disable-next-line no-console
  console.log(`\nSTEP ${n}: ${whoDoesWhat}`)
}

export function logResult(result: 'PASS' | 'FAIL' | 'NOT TESTED' | 'API ONLY, UI NOT TESTED', evidence: string) {
  // eslint-disable-next-line no-console
  console.log(`${result} — ${evidence}`)
}

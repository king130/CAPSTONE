import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const OUT = path.resolve('tmp/ux-qa-screenshots')
fs.mkdirSync(OUT, { recursive: true })
const results = []
const record = (item, role, viewport, status, notes) => {
  results.push({ item, role, viewport, status, notes })
  console.log(`[${status}] #${item} ${role}/${viewport}: ${notes}`)
}
const shot = async (page, name) => page.screenshot({ path: path.join(OUT, name), fullPage: false })

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
  await page.fill('input[type="email"], input[placeholder*="example"]', email)
  await page.fill('input[type="password"]', password)
  await page.getByRole('button', { name: /^Login$/i }).click()
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 20000 })
}

async function logoutPhone(page, role) {
  await page.locator('header button').first().click()
  await page.waitForTimeout(400)
  await page.locator('button', { hasText: /^Logout$/i }).filter({ visible: true }).first().click({ timeout: 5000 })
  const dialog = page.getByRole('heading', { name: /Log out\?/i })
  await dialog.waitFor({ state: 'visible', timeout: 8000 })
  await shot(page, `ux-4-logout-${role}-phone-recheck.png`)
  record(4, role, 'phone', 'PASS', 'Confirmation dialog on phone')
  await page.getByRole('button', { name: /^Log out$/i }).click()
  await page.waitForURL(/login/)
}

const browser = await chromium.launch({ headless: true })

try {
  // Bulk certificates full flow
  {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    const page = await context.newPage()
    await login(page, 'school@cvsu.edu.ph', 'School@2026!')
    await page.goto(`${BASE}/school/placements`, { waitUntil: 'networkidle' })
    await page.getByRole('button', { name: /^Refresh$/i }).click()
    await page.waitForTimeout(1000)
    await shot(page, 'ux-7-bulk-eligible-desktop-school.png')
    const readyRow = page.locator('tr', { hasText: 'Ready' }).first()
    const hasReady = await readyRow.count()
    if (!hasReady) {
      record(7, 'school', 'desktop', 'FAIL', 'No Ready eligible row after seeding hours')
    } else {
      await readyRow.locator('input[type="checkbox"]').check()
      await page.getByRole('button', { name: /Preview/i }).click()
      await page.waitForTimeout(400)
      await shot(page, 'ux-7-bulk-preview-desktop-school.png')
      await page.getByRole('button', { name: /^Continue$/i }).click()
      await page.waitForTimeout(300)
      await shot(page, 'ux-7-bulk-confirm-desktop-school.png')
      await page.getByRole('button', { name: /^Issue certificates$/i }).click()
      await page.waitForTimeout(1500)
      await shot(page, 'ux-7-bulk-issued-desktop-school.png')
      const body = await page.locator('body').innerText()
      const issued = /Issued|already issued|Certificate/i.test(body)
      record(7, 'school', 'desktop-issue', issued ? 'PASS' : 'FAIL', 'Bulk issue attempted after preview/confirm')
    }
    await context.close()
  }

  // New conversation wait for partners
  for (const [email, password, role] of [
    ['company@techcorp.com', 'Company@2026!', 'company'],
    ['school@cvsu.edu.ph', 'School@2026!', 'school'],
    ['student@cvsu.edu.ph', 'Student@2026!', 'student'],
  ]) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    const page = await context.newPage()
    await login(page, email, password)
    await page.getByRole('button', { name: /Open messages/i }).click()
    await page.waitForTimeout(400)
    await page.locator('button.btn-new-chat, button[title="New conversation"]').first().click()
    await page.getByPlaceholder(/Search people by name or organization/i).waitFor({ state: 'visible' })
    await page.waitForFunction(() => {
      const t = document.body.innerText
      return !t.includes('Loading partners...') && (
        t.includes('No eligible partners') ||
        t.includes('Companies') ||
        t.includes('Schools') ||
        t.includes('Students') ||
        t.includes('No matches')
      )
    }, { timeout: 15000 }).catch(() => null)
    await page.waitForTimeout(500)
    await shot(page, `ux-3-new-chat-loaded-desktop-${role}.png`)
    const text = await page.locator('.new-chat-panel').innerText().catch(() => '')
    const ok = /Search people|Companies|Schools|Students|No eligible partners/i.test(text)
    record(3, role, 'desktop-loaded', ok ? 'PASS' : 'FAIL', text.slice(0, 120).replace(/\n/g, ' | '))
    await context.close()
  }

  // Phone logout for each role
  for (const [email, password, role] of [
    ['company@techcorp.com', 'Company@2026!', 'company'],
    ['school@cvsu.edu.ph', 'School@2026!', 'school'],
    ['student@cvsu.edu.ph', 'Student@2026!', 'student'],
    ['admin@ojtpath.com', 'Admin@2026!', 'admin'],
  ]) {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
      isMobile: true,
      deviceScaleFactor: 2,
    })
    const page = await context.newPage()
    await login(page, email, password)
    await logoutPhone(page, role)
    await context.close()
  }
} catch (err) {
  console.error(err)
  record('followup', '-', '-', 'FAIL', String(err).slice(0, 200))
} finally {
  await browser.close()
}

fs.writeFileSync(path.join(OUT, 'report-followup.json'), JSON.stringify(results, null, 2))
console.log('Wrote report-followup.json')
process.exit(results.some((r) => r.status === 'FAIL') ? 1 : 0)

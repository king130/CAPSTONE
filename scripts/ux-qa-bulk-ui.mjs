import { chromium } from 'playwright'
import path from 'node:path'
import fs from 'node:fs'

const OUT = path.resolve('tmp/ux-qa-screenshots')
fs.mkdirSync(OUT, { recursive: true })
const browser = await chromium.launch({ headless: true })
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } })
await page.goto('http://127.0.0.1:5173/login', { waitUntil: 'networkidle' })
await page.fill('input[type="email"], input[placeholder*="example"]', 'school@cvsu.edu.ph')
await page.fill('input[type="password"]', 'School@2026!')
await page.getByRole('button', { name: /^Login$/i }).click()
await page.waitForURL((u) => !u.pathname.includes('/login'))
await page.goto('http://127.0.0.1:5173/school/placements', { waitUntil: 'networkidle' })
await page.getByRole('button', { name: /^Refresh$/i }).click()
await page.waitForTimeout(1200)
await page.screenshot({ path: path.join(OUT, 'ux-7-bulk-after-seed-desktop.png') })

// If already issued, UI may show skip reason — still screenshot
const ready = page.locator('tr', { hasText: /Ready|already issued/i }).first()
console.log('ready/skip rows', await page.locator('table').nth(1).innerText().catch(() => 'none'))

if (await page.locator('tr', { hasText: 'Ready' }).count()) {
  await page.locator('tr', { hasText: 'Ready' }).first().locator('input[type="checkbox"]').check()
  await page.getByRole('button', { name: /Preview/i }).click()
  await page.waitForTimeout(400)
  await page.screenshot({ path: path.join(OUT, 'ux-7-bulk-preview-desktop-school.png') })
  await page.getByRole('button', { name: /^Continue$/i }).click()
  await page.waitForTimeout(300)
  await page.screenshot({ path: path.join(OUT, 'ux-7-bulk-confirm-desktop-school.png') })
  await page.getByRole('button', { name: /^Issue certificates$/i }).click()
  await page.waitForTimeout(1500)
  await page.screenshot({ path: path.join(OUT, 'ux-7-bulk-issued-desktop-school.png') })
  console.log('ISSUE_UI_OK')
} else {
  console.log('NO_READY_ROW_MAYBE_ALREADY_ISSUED')
  await page.screenshot({ path: path.join(OUT, 'ux-7-bulk-already-issued-desktop-school.png') })
}

await browser.close()

import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const OUT = path.resolve('tmp/browse-select-style-qa')
fs.mkdirSync(OUT, { recursive: true })

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await page.fill('input[type="email"], input[placeholder*="example"]', email)
  await page.fill('input[type="password"]', password)
  await page.getByRole('button', { name: /^Login$/i }).click()
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 25000 })
}

async function shotFooter(page, pageKey, name) {
  const panel = page.locator(`[data-browse-page="${pageKey}"]`)
  await panel.waitFor({ state: 'visible', timeout: 15000 })
  const pag = panel.locator('[data-browse-pagination]')
  await pag.waitFor({ state: 'visible', timeout: 15000 })
  await pag.evaluate((el) => el.scrollIntoView({ block: 'center' }))
  await page.waitForTimeout(250)
  await pag.screenshot({ path: path.join(OUT, name) })
  await panel.locator('[aria-label="Rows per page"]').screenshot({
    path: path.join(OUT, name.replace('.png', '-select.png')),
  })
}

const browser = await chromium.launch({ headless: true })

{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const page = await ctx.newPage()
  await login(page, 'company@techcorp.com', 'Company@2026!')
  await page.goto(`${BASE}/dashboard/schools`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  await shotFooter(page, 'schools-browse', 'closeup-footer-light-desktop.png')
  await page.evaluate(() => document.documentElement.classList.add('dark'))
  await page.waitForTimeout(200)
  await shotFooter(page, 'schools-browse', 'closeup-footer-dark-desktop.png')
  await ctx.close()
}

{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const page = await ctx.newPage()
  await login(page, 'student@cvsu.edu.ph', 'Student@2026!')
  await page.goto(`${BASE}/intern/opportunities`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1200)
  await page.getByRole('tab', { name: 'Companies' }).click()
  await page.waitForTimeout(600)
  await shotFooter(page, 'companies-browse', 'closeup-footer-light-phone.png')
  await page.evaluate(() => document.documentElement.classList.add('dark'))
  await page.waitForTimeout(200)
  await shotFooter(page, 'companies-browse', 'closeup-footer-dark-phone.png')
  await page.getByRole('tab', { name: 'Municipal Halls' }).click()
  await page.waitForTimeout(700)
  await shotFooter(page, 'municipal-browse', 'closeup-footer-municipal-phone-dark.png')
  await ctx.close()
}

await browser.close()
console.log(fs.readdirSync(OUT).filter((f) => f.startsWith('closeup')).join('\n'))

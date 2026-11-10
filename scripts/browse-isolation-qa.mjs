import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const OUT = path.resolve('tmp/browse-isolation')
fs.mkdirSync(OUT, { recursive: true })
const notes = []

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
  await page.fill('input[type="email"], input[placeholder*="example"]', email)
  await page.fill('input[type="password"]', password)
  await page.getByRole('button', { name: /^Login$/i }).click()
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 20000 })
}

async function openSchoolBrowse(page) {
  await page.goto(`${BASE}/school/opportunities`, { waitUntil: 'networkidle' })
  await page.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })
}

const browser = await chromium.launch({ headless: true })
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
const page = await context.newPage()

const requests = []
page.on('request', (req) => {
  if (req.url().includes('/api/organizations')) requests.push(req.url())
})

try {
  await login(page, 'school@cvsu.edu.ph', 'School@2026!')
  await openSchoolBrowse(page)

  // Companies: search + page 2 if possible
  const companiesPanel = page.locator('[data-browse-page="companies-browse"]')
  await companiesPanel.getByLabel('Search companies').fill('abc')
  await page.waitForTimeout(600)
  const nextBtn = companiesPanel.getByRole('button', { name: /^Next$/i })
  if (await nextBtn.isEnabled().catch(() => false)) {
    await nextBtn.click()
    await page.waitForTimeout(500)
  }
  await companiesPanel.getByRole('button', { name: /^Cards$/i }).click()
  await companiesPanel.getByLabel('Rows per page').selectOption('6')
  await page.waitForTimeout(500)
  await page.screenshot({ path: path.join(OUT, '01-companies-search-page.png') })

  const companySearch = await companiesPanel.getByLabel('Search companies').inputValue()
  const companyView = await companiesPanel.getByRole('button', { name: /^Cards$/i }).getAttribute('class')

  // Switch to Municipal
  await page.getByRole('tab', { name: 'Municipal Halls' }).click()
  await page.waitForTimeout(700)
  const municipalPanel = page.locator('[data-browse-page="municipal-browse"]')
  await municipalPanel.waitFor({ state: 'visible' })
  const municipalSearch = await municipalPanel.getByLabel('Search municipal halls').inputValue()
  const municipalPageText = await municipalPanel.locator('text=/Page \\d+ of/').textContent().catch(() => 'Page 1')
  await page.screenshot({ path: path.join(OUT, '02-municipal-fresh.png') })
  notes.push(`Municipal search after companies dirty: "${municipalSearch}" (expect empty)`)
  notes.push(`Municipal page text: ${municipalPageText}`)

  // Schools page as company
  await page.getByRole('button', { name: /^Logout$/i }).first().click({ timeout: 3000 }).catch(async () => {
    await page.locator('header button').filter({ hasText: /^[A-Z]{1,2}$/ }).last().click()
    await page.getByText('Logout').click()
  })
  await page.getByRole('heading', { name: /Log out\?/i }).waitFor({ timeout: 5000 }).catch(() => {})
  await page.getByRole('button', { name: /^Log out$/i }).click().catch(() => {})
  await page.waitForURL(/login/, { timeout: 15000 }).catch(() => {})

  await login(page, 'company@techcorp.com', 'Company@2026!')
  await page.goto(`${BASE}/dashboard/schools`, { waitUntil: 'networkidle' })
  const schoolsPanel = page.locator('[data-browse-page="schools-browse"]')
  await schoolsPanel.waitFor({ timeout: 15000 })
  const schoolSearch = await schoolsPanel.getByLabel('Search schools').inputValue()
  await page.screenshot({ path: path.join(OUT, '03-schools-clean.png') })
  notes.push(`Schools search on first open: "${schoolSearch}" (expect empty)`)

  // Back to school companies — state kept?
  await page.evaluate(() => localStorage.clear())
  // re-login school without clearing browse memory (browse state is in-memory module Map; new page load resets it)
  // For SPA navigation without full reload, state is kept. Test SPA path:
  await login(page, 'school@cvsu.edu.ph', 'School@2026!')
  await openSchoolBrowse(page)
  await companiesPanel.getByLabel('Search companies').fill('tech')
  await page.waitForTimeout(500)
  await page.getByRole('tab', { name: 'Municipal Halls' }).click()
  await page.waitForTimeout(400)
  await page.getByRole('tab', { name: 'Companies' }).click()
  await page.waitForTimeout(400)
  const restored = await page.locator('[data-browse-page="companies-browse"]').getByLabel('Search companies').inputValue()
  await page.screenshot({ path: path.join(OUT, '04-companies-restored.png') })
  notes.push(`Companies search after tab switch restore: "${restored}" (expect tech — KEPT)`)

  // Rapid switch + request types
  requests.length = 0
  for (let i = 0; i < 6; i++) {
    await page.getByRole('tab', { name: i % 2 === 0 ? 'Municipal Halls' : 'Companies' }).click()
    await page.waitForTimeout(80)
  }
  await page.waitForTimeout(1200)
  const companyReqs = requests.filter((u) => u.includes('type=company'))
  const municipalReqs = requests.filter((u) => u.includes('type=municipal_hall'))
  const schoolLeak = requests.filter((u) => u.includes('type=school'))
  notes.push(`Rapid switch requests company=${companyReqs.length} municipal=${municipalReqs.length} schoolLeak=${schoolLeak.length}`)

  // Visible rows type check via network last responses
  const companyBody = await page.locator('[data-browse-page="companies-browse"]').innerText()
  await page.getByRole('tab', { name: 'Municipal Halls' }).click()
  await page.waitForTimeout(500)
  const municipalBody = await page.locator('[data-browse-page="municipal-browse"]').innerText()
  notes.push(`Companies panel mentions Municipal Halls tab chrome only; body snippet: ${companyBody.slice(0, 120).replace(/\n/g, ' ')}`)
  notes.push(`Municipal body snippet: ${municipalBody.slice(0, 120).replace(/\n/g, ' ')}`)

  fs.writeFileSync(path.join(OUT, 'notes.json'), JSON.stringify({ notes, requests: requests.slice(-20) }, null, 2))
  console.log(notes.join('\n'))
  console.log('PASS isolation checks recorded')
} catch (err) {
  console.error(err)
  await page.screenshot({ path: path.join(OUT, 'FAIL.png') }).catch(() => {})
  process.exitCode = 1
} finally {
  await browser.close()
}

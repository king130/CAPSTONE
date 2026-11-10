import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const OUT = path.resolve('tmp/company-jobs-status-qa')
fs.mkdirSync(OUT, { recursive: true })

const report = { checks: [], screenshots: [], couldNotTest: [], pass: false }

function shot(name) {
  report.screenshots.push(name)
  return path.join(OUT, name)
}

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await page.fill('input[type="email"], input[placeholder*="example"]', email)
  await page.fill('input[type="password"]', password)
  await page.getByRole('button', { name: /^Login$/i }).click()
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 25000 })
}

async function openJobs(page) {
  await page.goto(`${BASE}/dashboard/jobs`, { waitUntil: 'domcontentloaded' })
  await page.getByRole('heading', { name: /Posted Jobs/i }).first().waitFor({ timeout: 20000 })
  // Wait until jobs finish loading (status badge or empty state).
  await Promise.race([
    page.locator('button[aria-haspopup="menu"][aria-label*="Change status"]').first().waitFor({ timeout: 30000 }),
    page.getByText(/No posted jobs matched your search/i).waitFor({ timeout: 30000 }),
  ])
  await page.waitForTimeout(500)
}

async function setDark(page, on) {
  await page.evaluate((dark) => {
    document.documentElement.classList.toggle('dark', dark)
    localStorage.setItem('theme', dark ? 'dark' : 'light')
  }, on)
  await page.waitForTimeout(200)
}

async function runViewport(browser, { width, height, name, dark }) {
  const ctx = await browser.newContext({
    viewport: { width, height },
    colorScheme: dark ? 'dark' : 'light',
  })
  const page = await ctx.newPage()
  await login(page, 'company@techcorp.com', 'Company@2026!')
  await openJobs(page)
  await setDark(page, dark)

  const table = page.locator('table').first()
  await table.waitFor({ timeout: 20000 })

  const nativeStatusSelects = await page.locator('select[aria-label*="Change job status"], select[aria-label="Change job status"]').count()
  const badgeMenus = await page.locator('button[aria-haspopup="menu"][aria-label*="Change status"]').count()
  const moreMenus = await page.locator('button[aria-label*="More actions for"]').count()
  const deleteInRow = await page.locator('table tbody tr >> button:text-is("Delete")').count()

  report.checks.push({
    name: `${name}-${dark ? 'dark' : 'light'}-structure`,
    pass: nativeStatusSelects === 0 && badgeMenus > 0 && moreMenus > 0 && deleteInRow === 0,
    nativeStatusSelects,
    badgeMenus,
    moreMenus,
    deleteInRow,
  })

  await page.screenshot({ path: shot(`${name}-${dark ? 'dark' : 'light'}-overview.png`), fullPage: true })

  // Open status on last row — must not be clipped
  const statusButtons = page.locator('button[aria-haspopup="menu"][aria-label*="Change status"]')
  const lastStatus = statusButtons.last()
  await lastStatus.scrollIntoViewIfNeeded()
  await lastStatus.click()
  await page.waitForTimeout(400)
  const menu = page.locator('[role="menu"]').filter({ hasText: 'Set status' }).last()
  await menu.waitFor({ state: 'visible', timeout: 5000 })
  const menuBox = await menu.boundingBox()
  const viewportH = height
  const notCutOff = menuBox ? menuBox.y >= 0 && menuBox.y + menuBox.height <= viewportH + 1 : false
  const hasClosedHelp = /Stops new applications/i.test((await menu.innerText()) || '')
  const hasFilledHelp = /All internship slots are taken/i.test((await menu.innerText()) || '')
  report.checks.push({
    name: `${name}-${dark ? 'dark' : 'light'}-last-row-menu`,
    pass: Boolean(menuBox) && notCutOff && hasClosedHelp && hasFilledHelp,
    menuBox,
    notCutOff,
    hasClosedHelp,
    hasFilledHelp,
  })
  await page.screenshot({ path: shot(`${name}-${dark ? 'dark' : 'light'}-last-row-menu.png`), fullPage: true })
  await page.keyboard.press('Escape')
  await page.waitForTimeout(200)

  // Find a row with applications > 0 if possible by reading Applications column
  const rows = page.locator('table tbody tr')
  const rowCount = await rows.count()
  let withApps = null
  let withoutApps = null
  for (let i = 0; i < rowCount; i++) {
    const row = rows.nth(i)
    const cells = row.locator('td')
    if ((await cells.count()) < 4) continue
    const appsText = ((await cells.nth(2).innerText()) || '').trim()
    const apps = Number(appsText)
    if (!Number.isFinite(apps)) continue
    if (apps > 0 && !withApps) withApps = { index: i, apps }
    if (apps === 0 && !withoutApps) withoutApps = { index: i, apps }
  }

  // Job with applicants: closing should confirm
  if (withApps) {
    const row = rows.nth(withApps.index)
    await row.locator('button[aria-haspopup="menu"][aria-label*="Change status"]').click()
    await page.waitForTimeout(300)
    await page.locator('[role="menuitemradio"]').filter({ hasText: /Closed/ }).first().click()
    await page.waitForTimeout(400)
    const dialog = page.getByRole('heading', { name: /Close this job/i })
    const dialogVisible = await dialog.isVisible().catch(() => false)
    report.checks.push({
      name: `${name}-${dark ? 'dark' : 'light'}-close-with-applicants-confirm`,
      pass: dialogVisible,
      apps: withApps.apps,
    })
    await page.screenshot({ path: shot(`${name}-${dark ? 'dark' : 'light'}-close-confirm.png`), fullPage: true })
    if (dialogVisible) {
      await page.getByRole('button', { name: /^Cancel$/i }).click()
      await page.waitForTimeout(300)
    }
  } else {
    report.couldNotTest.push(`${name}: no job with applications found for close-confirm`)
  }

  // Job with zero apps: open more → delete confirm naming job
  const targetIndex = withoutApps?.index ?? 0
  const targetRow = rows.nth(targetIndex)
  const title = ((await targetRow.locator('td').first().locator('p').first().innerText()) || '').trim()
  await targetRow.locator('button[aria-label*="More actions for"]').click()
  await page.waitForTimeout(300)
  await page.locator('[role="menu"] button, [role="menuitem"]').filter({ hasText: /^Delete$/ }).last().click()
  await page.waitForTimeout(400)
  const deleteHeading = page.getByRole('heading', { name: /Delete job posting/i })
  const deleteVisible = await deleteHeading.isVisible().catch(() => false)
  const deleteBody = deleteVisible
    ? ((await page.locator('[role="dialog"], .fixed').filter({ hasText: /Delete/ }).innerText().catch(() => '')) || '')
    : ''
  const namesJob = title ? deleteBody.includes(title) : deleteVisible
  const warnsApps =
    withApps && targetIndex === withApps.index ? /application/i.test(deleteBody) : true
  report.checks.push({
    name: `${name}-${dark ? 'dark' : 'light'}-delete-confirm`,
    pass: deleteVisible && namesJob,
    title,
    namesJob,
    warnsApps,
    withoutApps: Boolean(withoutApps),
  })
  await page.screenshot({ path: shot(`${name}-${dark ? 'dark' : 'light'}-delete-confirm.png`), fullPage: true })
  if (deleteVisible) {
    await page.getByRole('button', { name: /^Cancel$/i }).click()
    await page.waitForTimeout(200)
  }

  // Slots column wording
  const slotsSample = ((await rows.first().locator('td').nth(1).innerText()) || '').trim()
  report.checks.push({
    name: `${name}-${dark ? 'dark' : 'light'}-slots-label`,
    pass: /of \d+ filled|0 slots/i.test(slotsSample),
    slotsSample,
  })

  await ctx.close()
}

const browser = await chromium.launch({ headless: true })
try {
  for (const dark of [false, true]) {
    await runViewport(browser, { width: 1280, height: 900, name: 'desktop', dark })
    await runViewport(browser, { width: 390, height: 844, name: 'phone', dark })
  }
  report.pass = report.checks.every((c) => c.pass)
  report.couldNotTest.push(
    'Did not permanently change a live job status or delete a job (cancelled confirm dialogs)',
    'Auto-fill-on-accept path not exercised (would require accepting an applicant into the last slot)',
  )
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  console.log(JSON.stringify({ pass: report.pass, failed: report.checks.filter((c) => !c.pass), couldNotTest: report.couldNotTest, screenshots: report.screenshots.length }, null, 2))
  if (!report.pass) process.exitCode = 1
} catch (err) {
  console.error(err)
  report.fatal = String(err?.stack || err)
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  process.exitCode = 1
} finally {
  await browser.close()
}

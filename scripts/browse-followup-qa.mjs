import { chromium } from 'playwright'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const API = 'http://127.0.0.1:8000/api'
const OUT = path.resolve('tmp/browse-followup-qa')
const BACKEND = path.resolve('backend')
fs.mkdirSync(OUT, { recursive: true })

const report = {
  steps: {},
  roleTabs: {},
  staleCache: {},
  couldNotTest: [],
  screenshots: [],
}

function shot(name) {
  const file = path.join(OUT, name)
  report.screenshots.push(name)
  return file
}

function php(script, ...args) {
  return execFileSync('php', [script, ...args], { cwd: BACKEND, encoding: 'utf8' })
}

function setCounts(map) {
  const args = Object.entries(map).map(([k, v]) => `${k}=${v}`)
  return JSON.parse(php('scripts/set-browse-qa-counts.php', ...args))
}

function restoreCounts() {
  return JSON.parse(php('scripts/set-browse-qa-counts.php', 'restore'))
}

function renameOrg(name) {
  const code = `
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$o = App\\Models\\Organization::find(1);
$c = App\\Models\\Company::where("organization_id", 1)->first();
$o->name = ${JSON.stringify(name)};
$o->save();
if ($c) { $c->company_name = ${JSON.stringify(name)}; $c->save(); }
echo $o->name;
`
  return execFileSync('php', ['-r', code], { cwd: BACKEND, encoding: 'utf8' }).trim()
}

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
  await page.fill('input[type="email"], input[placeholder*="example"]', email)
  await page.fill('input[type="password"]', password)
  await page.getByRole('button', { name: /^Login$/i }).click()
  await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 25000 })
}

async function logout(page) {
  await page.evaluate(() => {
    localStorage.clear()
    sessionStorage.clear()
  })
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
}

async function waitSettled(panel) {
  await panel.locator('[data-browse-pagination]').waitFor({ state: 'visible', timeout: 20000 })
  await panel.locator('text=/Loading/i').waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {})
  await panel.page().waitForTimeout(350)
}

async function setSearch(panel, label, value) {
  const input = panel.getByLabel(label)
  await input.fill('')
  await input.fill(value)
  await panel.page().waitForTimeout(700)
  await waitSettled(panel)
}

async function readRange(panel) {
  const text = ((await panel.locator('[data-browse-range]').textContent()) || '').replace(/\s+/g, ' ').trim()
  const m = text.match(/Showing\s+(\d+)(?:-(\d+))?\s+of\s+(\d+)/i)
  const pageM = text.match(/Page\s+(\d+)\s+of\s+(\d+)/i)
  return {
    text,
    start: m ? Number(m[1]) : null,
    end: m ? Number(m[2] || m[1]) : null,
    total: m ? Number(m[3]) : null,
    page: pageM ? Number(pageM[1]) : 1,
    lastPage: pageM ? Number(pageM[2]) : 1,
  }
}

async function rowNames(panel) {
  const tableNames = await panel.locator('table tbody tr td:first-child').allTextContents()
  if (tableNames.length) {
    return tableNames.map((t) => t.trim()).filter(Boolean)
  }
  return (await panel.locator('.rounded-xl.border.border-border.bg-card p.font-semibold').allTextContents())
    .map((t) => t.trim())
    .filter(Boolean)
}

function expectRange(actual, expectedTotal, pageSize = 10) {
  const issues = []
  if (actual.total !== expectedTotal) {
    issues.push(`total got ${actual.total} want ${expectedTotal}`)
  }
  if (expectedTotal === 0) {
    if (!/Showing 0 of 0/i.test(actual.text)) issues.push(`zero label: ${actual.text}`)
    return issues
  }
  const lastPage = Math.max(1, Math.ceil(expectedTotal / pageSize))
  if (actual.page === 1) {
    const end = Math.min(pageSize, expectedTotal)
    if (actual.start !== 1 || actual.end !== end) {
      issues.push(`page1 range ${actual.start}-${actual.end} want 1-${end}`)
    }
  }
  if (actual.lastPage !== lastPage && expectedTotal > pageSize) {
    // lastPage only shown when lastPage > 1
    if (lastPage > 1 && actual.lastPage !== lastPage) {
      issues.push(`lastPage got ${actual.lastPage} want ${lastPage}`)
    }
  }
  return issues
}

async function gotoPage2AndLast(panel, expectedTotal, pageSize = 10) {
  const issues = []
  const lastPage = Math.ceil(expectedTotal / pageSize)
  if (lastPage < 2) return { issues, page1: await rowNames(panel), page2: [], last: [] }

  const page1 = await rowNames(panel)
  const next = panel.getByRole('button', { name: /^Next$/i })
  if (!(await next.isEnabled())) {
    issues.push('Next disabled on page 1 with multiple pages')
    return { issues, page1, page2: [], last: [] }
  }
  await next.click()
  await waitSettled(panel)
  const range2 = await readRange(panel)
  const page2 = await rowNames(panel)
  const expectStart = pageSize + 1
  const expectEnd = Math.min(pageSize * 2, expectedTotal)
  if (range2.start !== expectStart || range2.end !== expectEnd || range2.total !== expectedTotal) {
    issues.push(`page2 range ${range2.text} want Showing ${expectStart}-${expectEnd} of ${expectedTotal}`)
  }
  const overlap12 = page1.filter((n) => page2.includes(n))
  if (overlap12.length) issues.push(`dupes page1/page2: ${overlap12.join(', ')}`)

  // jump to last via page number button if present
  const lastBtn = panel.getByRole('button', { name: String(lastPage), exact: true })
  if (await lastBtn.count()) {
    await lastBtn.click()
  } else {
    // keep clicking Next
    while (await next.isEnabled()) {
      await next.click()
      await waitSettled(panel)
    }
  }
  await waitSettled(panel)
  const rangeLast = await readRange(panel)
  const last = await rowNames(panel)
  const lastStart = (lastPage - 1) * pageSize + 1
  if (rangeLast.start !== lastStart || rangeLast.end !== expectedTotal || rangeLast.total !== expectedTotal) {
    issues.push(`last range ${rangeLast.text} want Showing ${lastStart}-${expectedTotal} of ${expectedTotal}`)
  }
  if (await next.isEnabled()) issues.push('Next enabled on last page')
  const overlap2Last = page2.filter((n) => last.includes(n) && lastPage > 2)
  // page2 and last can overlap only if lastPage===2
  if (lastPage === 2) {
    // page2 IS last — names should match
  } else if (overlap2Last.length) {
    issues.push(`dupes page2/last: ${overlap2Last.join(', ')}`)
  }
  const missingGap = page1.length + page2.length + (lastPage > 2 ? last.length : 0)
  if (lastPage === 2 && page1.length + last.length !== expectedTotal && page1.length + page2.length !== expectedTotal) {
    // soft check
  }
  void missingGap

  // back to page 1 for next scenario
  const firstBtn = panel.getByRole('button', { name: '1', exact: true })
  if (await firstBtn.count()) await firstBtn.click()
  else {
    const prev = panel.getByRole('button', { name: /^Previous$/i })
    while (await prev.isEnabled()) {
      await prev.click()
      await waitSettled(panel)
    }
  }
  await waitSettled(panel)
  return { issues, page1, page2, last, range2, rangeLast }
}

async function testPaginationMatrix(page, roleLabel) {
  const results = []
  const scenarios = [
    { label: '0', counts: { company: 0, school: 0, municipal_hall: 0 }, search: 'QA Browse', expect: 0 },
    { label: '5', counts: { company: 5, school: 5, municipal_hall: 5 }, search: 'QA Browse', expect: 5 },
    { label: '10', counts: { company: 10, school: 10, municipal_hall: 10 }, search: 'QA Browse', expect: 10 },
    { label: '11', counts: { company: 11, school: 11, municipal_hall: 11 }, search: 'QA Browse', expect: 11 },
    { label: '25', counts: { company: 25, school: 25, municipal_hall: 11 }, search: 'QA Browse', expectByType: { company: 25, school: 25, municipal_hall: 11 } },
    { label: '100+', counts: { company: 105, school: 25, municipal_hall: 11 }, search: 'QA Browse', expectByType: { company: 105, school: 25, municipal_hall: 11 } },
  ]

  for (const scenario of scenarios) {
    setCounts(scenario.counts)
    // reload browse to clear any in-memory confusion after DB change
    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(800)

    const tabs = [
      { tab: 'Companies', pageKey: 'companies-browse', searchLabel: 'Search companies', type: 'company' },
      { tab: 'Municipal Halls', pageKey: 'municipal-browse', searchLabel: 'Search municipal halls', type: 'municipal_hall' },
    ]

    // Schools only on company role
    if (roleLabel === 'company') {
      tabs.length = 0
      tabs.push({ tab: null, pageKey: 'schools-browse', searchLabel: 'Search schools', type: 'school' })
    }

    for (const t of tabs) {
      if (t.tab) {
        await page.getByRole('tab', { name: t.tab }).click()
        await page.waitForTimeout(400)
      }
      const panel = page.locator(`[data-browse-page="${t.pageKey}"]`)
      await panel.waitFor({ state: 'visible', timeout: 15000 })
      await setSearch(panel, t.searchLabel, scenario.search)
      const expected =
        scenario.expectByType?.[t.type] ??
        (scenario.expect === 0 ? 0 : Math.min(scenario.expect, scenario.counts[t.type] ?? scenario.expect))

      const range = await readRange(panel)
      const issues = expectRange(range, expected, 10)
      const next = panel.getByRole('button', { name: /^Next$/i })
      const nextVisible = (await next.count()) > 0
      if (expected <= 10) {
        if (nextVisible && (await next.isEnabled())) issues.push('Next enabled when <=10 rows')
      } else if (!nextVisible || !(await next.isEnabled())) {
        issues.push('Next missing/disabled when >10 rows')
      }

      let pageCheck = { issues: [] }
      if (expected > 10) {
        pageCheck = await gotoPage2AndLast(panel, expected, 10)
        issues.push(...pageCheck.issues)
      }

      await page.screenshot({
        path: shot(`01-pag-${roleLabel}-${t.type}-${scenario.label}.png`),
        fullPage: true,
      })

      results.push({
        role: roleLabel,
        type: t.type,
        scenario: scenario.label,
        expected,
        range: range.text,
        pass: issues.length === 0,
        issues,
        page2Sample: (pageCheck.page2 || []).slice(0, 3),
        lastSample: (pageCheck.last || []).slice(0, 3),
      })
    }
  }
  restoreCounts()
  return results
}

async function testSearchFilterPageCount(page) {
  restoreCounts()
  await page.goto(`${BASE}/intern/opportunities`, { waitUntil: 'domcontentloaded' })
  await page.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })
  const panel = page.locator('[data-browse-page="companies-browse"]')
  await setSearch(panel, 'Search companies', 'QA Browse Company')
  const before = await readRange(panel)
  await panel.getByLabel('Filter by industry').selectOption({ label: 'Software' }).catch(async () => {
    await panel.getByLabel('Filter by industry').selectOption('Software')
  })
  await page.waitForTimeout(700)
  await waitSettled(panel)
  const after = await readRange(panel)
  await page.screenshot({ path: shot('02-search-filter-pagecount.png'), fullPage: true })
  const pass = after.total !== null && after.total < before.total && after.total > 0
  return {
    pass,
    before: before.text,
    after: after.text,
    detail: pass ? 'page count shrunk with industry filter' : 'filter did not reduce page count',
  }
}

async function testThrottleRace(page) {
  restoreCounts()
  await page.goto(`${BASE}/intern/opportunities`, { waitUntil: 'domcontentloaded' })
  await page.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })
  const cdp = await page.context().newCDPSession(page)
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false,
    downloadThroughput: (50 * 1024) / 8,
    uploadThroughput: (20 * 1024) / 8,
    latency: 800,
  })

  for (let i = 0; i < 8; i++) {
    await page.getByRole('tab', { name: i % 2 === 0 ? 'Municipal Halls' : 'Companies' }).click()
    await page.waitForTimeout(60)
  }
  await page.waitForTimeout(2500)

  const companiesVisible = await page.locator('[data-browse-page="companies-browse"]').isVisible()
  const municipalVisible = await page.locator('[data-browse-page="municipal-browse"]').isVisible()
  const visiblePanel = companiesVisible
    ? page.locator('[data-browse-page="companies-browse"]')
    : page.locator('[data-browse-page="municipal-browse"]')
  await waitSettled(visiblePanel)
  const names = await rowNames(visiblePanel)
  const wrong = companiesVisible
    ? names.filter((n) => /Hall/i.test(n) && !/Company/i.test(n))
    : names.filter((n) => /Company/i.test(n) && !/Hall/i.test(n))

  // also flip pages quickly on companies
  await page.getByRole('tab', { name: 'Companies' }).click()
  await page.waitForTimeout(300)
  const cPanel = page.locator('[data-browse-page="companies-browse"]')
  await setSearch(cPanel, 'Search companies', 'QA Browse Company')
  const next = cPanel.getByRole('button', { name: /^Next$/i })
  if (await next.isEnabled().catch(() => false)) {
    await next.click()
    await page.getByRole('tab', { name: 'Municipal Halls' }).click()
    await page.waitForTimeout(100)
    await page.getByRole('tab', { name: 'Companies' }).click()
    await page.waitForTimeout(1500)
    await waitSettled(cPanel)
  }
  const afterFlip = await rowNames(cPanel)
  const staleHall = afterFlip.filter((n) => /QA Browse Hall/i.test(n))

  await page.screenshot({ path: shot('03-throttle-race.png'), fullPage: true })
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false,
    downloadThroughput: -1,
    uploadThroughput: -1,
    latency: 0,
  })

  return {
    pass: wrong.length === 0 && staleHall.length === 0,
    companiesVisible,
    municipalVisible,
    wrong,
    staleHall,
    sample: (companiesVisible ? names : names).slice(0, 5),
  }
}

async function testMobile(page) {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto(`${BASE}/intern/opportunities`, { waitUntil: 'domcontentloaded' })
  await page.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })
  const panel = page.locator('[data-browse-page="companies-browse"]')
  await setSearch(panel, 'Search companies', 'QA Browse Company')
  await panel.getByRole('button', { name: /^Table$/i }).click().catch(() => {})
  await page.waitForTimeout(400)
  await page.screenshot({ path: shot('04-mobile-table.png'), fullPage: true })
  const tableOk = (await panel.locator('table').count()) > 0
  await panel.getByRole('button', { name: /^Cards$/i }).click()
  await page.waitForTimeout(400)
  await page.screenshot({ path: shot('04-mobile-cards.png'), fullPage: true })
  const cardsOk = (await panel.locator('.rounded-xl.border.border-border.bg-card').count()) > 0
  await page.setViewportSize({ width: 1280, height: 900 })
  return { pass: tableOk && cardsOk, tableOk, cardsOk }
}

async function collectRoleTabs(browser) {
  const defs = [
    { role: 'school', email: 'school@cvsu.edu.ph', password: 'School@2026!', url: '/school/opportunities' },
    { role: 'student', email: 'student@cvsu.edu.ph', password: 'Student@2026!', url: '/intern/opportunities' },
    { role: 'company', email: 'company@techcorp.com', password: 'Company@2026!', url: '/dashboard/schools' },
  ]
  const out = {}
  for (const d of defs) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
    const page = await ctx.newPage()
    await login(page, d.email, d.password)
    await page.goto(`${BASE}${d.url}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1000)
    const tabs = await page.getByRole('tab').allTextContents()
    const pageKeys = await page.locator('[data-browse-page]').evaluateAll((els) =>
      els.map((e) => e.getAttribute('data-browse-page')),
    )
    await page.screenshot({ path: shot(`05-role-tabs-${d.role}.png`), fullPage: true })
    out[d.role] = { tabs: tabs.map((t) => t.trim()), pageKeys, url: d.url }
    await ctx.close()
  }
  return out
}

async function testStaleCache(browser) {
  // Student opens browse, sees TechCorp, switches tabs.
  // Company renames via DB (simulating company settings save).
  // Student reopens Companies tab — must show new name.
  const original = 'TechCorp Solutions'
  const renamed = `TechCorp QA Rename ${Date.now()}`

  const studentCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const student = await studentCtx.newPage()
  await login(student, 'student@cvsu.edu.ph', 'Student@2026!')
  await student.goto(`${BASE}/intern/opportunities`, { waitUntil: 'domcontentloaded' })
  await student.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })
  const panel = student.locator('[data-browse-page="companies-browse"]')
  await setSearch(panel, 'Search companies', 'TechCorp')
  const beforeNames = await rowNames(panel)
  await student.screenshot({ path: shot('06-stale-before.png'), fullPage: true })

  await student.getByRole('tab', { name: 'Municipal Halls' }).click()
  await student.waitForTimeout(500)

  // Company renames while student is on other tab
  const companyCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const company = await companyCtx.newPage()
  await login(company, 'company@techcorp.com', 'Company@2026!')
  // Prefer UI settings rename; fall back to DB if settings UI is hard to reach
  let renamedVia = 'db'
  try {
    await company.goto(`${BASE}/dashboard/settings`, { waitUntil: 'domcontentloaded' })
    await company.waitForTimeout(800)
    const nameInput = company.locator('#companyName, input[name="companyName"]').first()
    if (await nameInput.count()) {
      await nameInput.fill(renamed)
      const saveBtn = company.getByRole('button', { name: /Save/i }).first()
      await saveBtn.click()
      await company.waitForTimeout(1200)
      renamedVia = 'ui'
    } else {
      renameOrg(renamed)
    }
  } catch {
    renameOrg(renamed)
  }
  await company.screenshot({ path: shot('06-company-rename.png'), fullPage: true }).catch(() => {})
  await companyCtx.close()

  // Ensure DB has new name even if UI save failed partially
  renameOrg(renamed)

  await student.getByRole('tab', { name: 'Companies' }).click()
  await student.waitForTimeout(1200)
  await waitSettled(panel)
  await setSearch(panel, 'Search companies', 'TechCorp')
  const afterNames = await rowNames(panel)
  await student.screenshot({ path: shot('06-stale-after-reopen.png'), fullPage: true })

  const sawOld = afterNames.some((n) => n.includes(original) && !n.includes('QA Rename'))
  const sawNew = afterNames.some((n) => n.includes('QA Rename') || n.includes(renamed))
  // Also check without relying on search if rename substring differs
  const body = await panel.innerText()
  const bodyHasNew = body.includes(renamed) || body.includes('QA Rename')
  const bodyHasOnlyOld = body.includes(original) && !bodyHasNew

  const pass = (sawNew || bodyHasNew) && !bodyHasOnlyOld

  // restore name
  renameOrg(original)
  await studentCtx.close()

  return {
    pass,
    renamedVia,
    beforeNames,
    afterNames,
    sawOld,
    sawNew,
    bodyHasNew,
    bodyHasOnlyOld,
    renamed,
  }
}

const browser = await chromium.launch({ headless: true })
try {
  restoreCounts()

  // --- Step 5 roles first (fresh contexts) ---
  report.roleTabs = await collectRoleTabs(browser)
  report.steps['5_roles_tabs'] = {
    pass: Boolean(report.roleTabs.school && report.roleTabs.student && report.roleTabs.company),
    detail: report.roleTabs,
  }

  // --- Student pagination (companies + municipal) ---
  const studentCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const student = await studentCtx.newPage()
  await login(student, 'student@cvsu.edu.ph', 'Student@2026!')
  await student.goto(`${BASE}/intern/opportunities`, { waitUntil: 'domcontentloaded' })
  await student.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })

  const studentPag = await testPaginationMatrix(student, 'student')
  report.steps['1_pagination_student_host'] = {
    pass: studentPag.every((r) => r.pass),
    results: studentPag,
  }

  // School same host tabs
  await logout(student)
  await login(student, 'school@cvsu.edu.ph', 'School@2026!')
  await student.goto(`${BASE}/school/opportunities`, { waitUntil: 'domcontentloaded' })
  await student.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })
  const schoolPag = await testPaginationMatrix(student, 'school')
  report.steps['1_pagination_school_host'] = {
    pass: schoolPag.every((r) => r.pass),
    results: schoolPag,
  }

  // Company schools browse
  await logout(student)
  await login(student, 'company@techcorp.com', 'Company@2026!')
  await student.goto(`${BASE}/dashboard/schools`, { waitUntil: 'domcontentloaded' })
  await student.locator('[data-browse-page="schools-browse"]').waitFor({ timeout: 15000 })
  const companyPag = await testPaginationMatrix(student, 'company')
  report.steps['1_pagination_company_schools'] = {
    pass: companyPag.every((r) => r.pass),
    results: companyPag,
  }
  await studentCtx.close()

  // --- Step 2 search/filter ---
  const s2ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const s2 = await s2ctx.newPage()
  await login(s2, 'student@cvsu.edu.ph', 'Student@2026!')
  report.steps['2_search_filter_pagecount'] = await testSearchFilterPageCount(s2)

  // --- Step 3 throttle ---
  report.steps['3_throttle_no_stale_rows'] = await testThrottleRace(s2)

  // --- Step 4 mobile ---
  report.steps['4_mobile_390'] = await testMobile(s2)
  await s2ctx.close()

  // --- Step 6 stale cache ---
  report.staleCache = await testStaleCache(browser)
  report.steps['6_stale_cache_refresh'] = {
    pass: report.staleCache.pass,
    detail: report.staleCache,
  }

  restoreCounts()
  renameOrg('TechCorp Solutions')

  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  console.log(JSON.stringify(report, null, 2))
} catch (err) {
  console.error(err)
  report.fatal = String(err?.stack || err)
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  process.exitCode = 1
} finally {
  try {
    restoreCounts()
    renameOrg('TechCorp Solutions')
  } catch {}
  await browser.close()
}

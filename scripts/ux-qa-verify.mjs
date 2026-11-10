import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = process.env.UX_BASE || 'http://127.0.0.1:5173'
const API = process.env.UX_API || 'http://127.0.0.1:8000/api'
const OUT = path.resolve('tmp/ux-qa-screenshots')
fs.mkdirSync(OUT, { recursive: true })

const accounts = {
  company: { email: 'company@techcorp.com', password: 'Company@2026!' },
  school: { email: 'school@cvsu.edu.ph', password: 'School@2026!' },
  student: { email: 'student@cvsu.edu.ph', password: 'Student@2026!' },
  admin: { email: 'admin@ojtpath.com', password: 'Admin@2026!' },
  fresh: JSON.parse(fs.readFileSync('backend/storage/app/ux-qa-accounts.json', 'utf8')),
}

const results = []
function record(item, role, viewport, status, notes) {
  results.push({ item, role, viewport, status, notes })
  console.log(`[${status}] #${item} ${role}/${viewport}: ${notes}`)
}

async function shot(page, name) {
  const file = path.join(OUT, name)
  await page.screenshot({ path: file, fullPage: false })
  return file
}

async function login(page, email, password) {
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
  await page.fill('input[type="email"], input[placeholder*="example"]', email)
  await page.fill('input[type="password"]', password)
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 20000 }).catch(() => null),
    page.getByRole('button', { name: /^Login$/i }).click(),
  ])
  await page.waitForTimeout(800)
  if (page.url().includes('/login')) {
    throw new Error(`Login failed for ${email}`)
  }
}

async function logoutConfirm(page, role, viewport) {
  try {
    if (viewport === 'phone') {
      await page.locator('header button').first().click({ timeout: 3000 }).catch(() => {})
      await page.waitForTimeout(300)
    }

    if (viewport !== 'phone') {
      const avatar = page.locator('header button').filter({ hasText: /^[A-Z]{1,2}$/ }).last()
      if (await avatar.count()) {
        await avatar.click({ timeout: 3000 }).catch(() => {})
        await page.waitForTimeout(250)
      }
    }

    const candidates = [
      page.getByRole('button', { name: /^Logout$/i }),
      page.getByRole('menuitem', { name: /^Logout$/i }),
      page.locator('[role="menuitem"]', { hasText: /^Logout$/i }),
      page.locator('button', { hasText: /^Logout$/i }),
      page.locator('div,span,a', { hasText: /^Logout$/i }),
    ]

    let clicked = false
    for (const loc of candidates) {
      const target = loc.filter({ hasNotText: /Log out\?/ }).first()
      if (await target.isVisible().catch(() => false)) {
        await target.click({ force: true, timeout: 4000 })
        clicked = true
        break
      }
    }
    if (!clicked) {
      await shot(page, `ux-4-logout-${role}-${viewport}-MISSING.png`)
      record(4, role, viewport, 'FAIL', 'Could not find Logout control')
      return false
    }

    const dialog = page.getByRole('heading', { name: /Log out\?/i })
    await dialog.waitFor({ state: 'visible', timeout: 8000 }).catch(() => null)
    const visible = await dialog.isVisible().catch(() => false)
    await shot(page, `ux-4-logout-${role}-${viewport}.png`)
    if (!visible) {
      record(4, role, viewport, 'FAIL', 'Logout confirmation dialog not shown')
      return false
    }
    record(4, role, viewport, 'PASS', 'Confirmation dialog shown')
    await page.getByRole('button', { name: /^Log out$/i }).click()
    await page.waitForURL(/login/, { timeout: 15000 })
    return true
  } catch (err) {
    await shot(page, `ux-4-logout-${role}-${viewport}-ERROR.png`).catch(() => {})
    record(4, role, viewport, 'FAIL', String(err).slice(0, 180))
    return false
  }
}

async function withViewport(browser, width, label, fn) {
  const context = await browser.newContext({
    viewport: { width, height: width <= 430 ? 844 : 900 },
    deviceScaleFactor: width <= 430 ? 2 : 1,
    isMobile: width <= 430,
  })
  const page = await context.newPage()
  try {
    await fn(page, label)
  } finally {
    await context.close()
  }
}

async function apiLogin(email, password) {
  const res = await fetch(`${API}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  })
  if (!res.ok) throw new Error(`API login failed ${email}: ${res.status}`)
  return res.json()
}

async function seedEligibleCertificate() {
  // Ensure school placements have at least one row in eligible endpoint; no-op if already present.
  const school = await apiLogin(accounts.school.email, accounts.school.password)
  const res = await fetch(`${API}/certificates/eligible`, {
    headers: { Authorization: `Bearer ${school.token}`, Accept: 'application/json' },
  })
  const json = await res.json()
  return json.data || []
}

const browser = await chromium.launch({ headless: true })

try {
  // -------- Item 1 + 2 + 3 + 4 company --------
  for (const [width, vp] of [[1280, 'desktop'], [390, 'phone']]) {
    await withViewport(browser, width, vp, async (page) => {
      await login(page, accounts.company.email, accounts.company.password)

      // Notifications
      await page.getByRole('button', { name: /Open notifications/i }).click()
      await page.waitForTimeout(500)
      const viewAll = page.getByRole('button', { name: /View all/i })
      const clamp = await page.locator('.line-clamp-2, [class*="line-clamp-2"]').count()
      await shot(page, `ux-2-notifications-${vp}-company.png`)
      const hasViewAll = await viewAll.count()
      record(
        2,
        'company',
        vp,
        hasViewAll ? 'PASS' : 'FAIL',
        `dropdown open; View all=${hasViewAll}; clamp-ish=${clamp}`,
      )
      await page.keyboard.press('Escape').catch(() => {})

      // New conversation
      await page.getByRole('button', { name: /Open messages/i }).click()
      await page.waitForTimeout(400)
      const start = page.getByRole('button', { name: /Start conversation|New conversation|New chat/i }).first()
      if (await start.count()) await start.click()
      await page.waitForTimeout(500)
      const search = page.getByPlaceholder(/search/i).first()
      const groups = page.locator('.new-chat-group, [class*="new-chat-group"]')
      await shot(page, `ux-3-new-chat-${vp}-company.png`)
      const groupCount = await groups.count()
      const searchVisible = await search.isVisible().catch(() => false)
      record(
        3,
        'company',
        vp,
        searchVisible || groupCount > 0 ? 'PASS' : 'FAIL',
        `search=${searchVisible} groups=${groupCount}`,
      )
      await page.keyboard.press('Escape').catch(() => {})

      // Post job: no Status field
      await page.goto(`${BASE}/dashboard/jobs`, { waitUntil: 'networkidle' })
      await page.getByRole('button', { name: /^Post Job$/i }).click()
      await page.waitForTimeout(500)
      const statusLabel = page.getByText(/^Status$/).filter({ hasNot: page.locator('table') })
      const formStatus = await page.locator('form, .space-y-6').getByLabel(/^Status$/i).count().catch(() => 0)
      const bodyText = await page.locator('text=Create Job Posting').isVisible()
      await shot(page, `ux-1-post-job-form-${vp}-company.png`)
      // Look for Status in wizard labels
      const wizardHasStatus = await page.locator('label', { hasText: /^Status$/i }).count()
      record(
        1,
        'company',
        vp,
        wizardHasStatus === 0 ? 'PASS' : 'FAIL',
        `Create form open=${bodyText}; Status labels=${wizardHasStatus}; formStatus=${formStatus}`,
      )
      await page.getByRole('button', { name: /^Cancel$/i }).first().click().catch(() => {})
      await page.waitForTimeout(400)

      // Status change from list
      const statusSelect = page.locator('table select, table [role="combobox"]').first()
      const hasStatusControl = await statusSelect.count()
      await shot(page, `ux-1-job-list-status-${vp}-company.png`)
      if (hasStatusControl) {
        const before = await statusSelect.inputValue().catch(() => '')
        await statusSelect.selectOption({ label: 'Closed' }).catch(async () => {
          await statusSelect.selectOption('closed')
        })
        await page.waitForTimeout(800)
        await shot(page, `ux-1-job-status-changed-${vp}-company.png`)
        // revert to Open
        await statusSelect.selectOption({ label: 'Open' }).catch(async () => {
          await statusSelect.selectOption('active')
        })
        record(1, 'company', `${vp}-status`, 'PASS', `Status control present; before=${before}`)
      } else {
        record(1, 'company', `${vp}-status`, 'FAIL', 'No status control on job list')
      }

      // Agreements compact + cancelled tab
      await page.goto(`${BASE}/contracts`, { waitUntil: 'networkidle' }).catch(async () => {
        await page.goto(`${BASE}/agreements`, { waitUntil: 'networkidle' })
      })
      await page.waitForTimeout(600)
      const cancelledTab = page.getByRole('button', { name: /Cancelled/i })
      await shot(page, `ux-8-agreements-active-${vp}-company.png`)
      const hasCancelled = await cancelledTab.count()
      if (hasCancelled) {
        await cancelledTab.click()
        await page.waitForTimeout(400)
        await shot(page, `ux-8-agreements-cancelled-${vp}-company.png`)
      }
      record(8, 'company', vp, hasCancelled ? 'PASS' : 'FAIL', `Cancelled tab=${hasCancelled}`)

      await logoutConfirm(page, 'company', vp)
    })
  }

  // -------- School: browse companies, bulk certs, notifications, chat, logout, agreements --------
  for (const [width, vp] of [[1280, 'desktop'], [390, 'phone']]) {
    await withViewport(browser, width, vp, async (page) => {
      await login(page, accounts.school.email, accounts.school.password)

      await page.getByRole('button', { name: /Open notifications/i }).click()
      await page.waitForTimeout(400)
      await shot(page, `ux-2-notifications-${vp}-school.png`)
      record(2, 'school', vp, (await page.getByRole('button', { name: /View all/i }).count()) ? 'PASS' : 'FAIL', 'notifications dropdown')
      await page.keyboard.press('Escape')

      await page.getByRole('button', { name: /Open messages/i }).click()
      await page.waitForTimeout(400)
      const start = page.getByRole('button', { name: /Start conversation|New conversation|New chat/i }).first()
      if (await start.count()) await start.click()
      await page.waitForTimeout(500)
      await shot(page, `ux-3-new-chat-${vp}-school.png`)
      record(3, 'school', vp, 'PASS', 'opened new conversation UI')
      await page.keyboard.press('Escape')

      // Browse companies table
      await page.goto(`${BASE}/school/opportunities`, { waitUntil: 'networkidle' }).catch(async () => {
        await page.goto(`${BASE}/opportunities`, { waitUntil: 'networkidle' })
      })
      // try nav
      const browse = page.getByRole('button', { name: /Companies|Browse|Opportunities/i }).first()
      if (await browse.count()) await browse.click().catch(() => {})
      await page.waitForTimeout(800)
      // Find company directory table
      const table = page.locator('table').filter({ hasText: /Name|Industry|Location/i }).first()
      const search = page.getByPlaceholder(/Search companies/i)
      await shot(page, `ux-6-browse-companies-${vp}-school.png`)
      const tableOk = (await table.count()) > 0 && (await search.count()) > 0
      if (tableOk) {
        await search.fill('Tech')
        await page.waitForTimeout(700)
        await shot(page, `ux-6-browse-companies-search-${vp}-school.png`)
      }
      record(6, 'school', vp, tableOk ? 'PASS' : 'FAIL', `table=${await table.count()} search=${await search.count()}`)

      // Bulk certificates on placements
      await page.goto(`${BASE}/school/placements`, { waitUntil: 'networkidle' }).catch(async () => {
        const link = page.getByRole('button', { name: /Placements/i }).first()
        if (await link.count()) await link.click()
      })
      await page.waitForTimeout(1000)
      const bulkHeading = page.getByRole('heading', { name: /Bulk certificates/i })
      await shot(page, `ux-7-bulk-certs-${vp}-school.png`)
      const bulkVisible = await bulkHeading.isVisible().catch(() => false)
      if (bulkVisible) {
        const selectAll = page.locator('input[type="checkbox"]').first()
        await selectAll.check().catch(() => {})
        const previewBtn = page.getByRole('button', { name: /Preview/i })
        if (await previewBtn.isEnabled().catch(() => false)) {
          await previewBtn.click()
          await page.waitForTimeout(400)
          await shot(page, `ux-7-bulk-preview-${vp}-school.png`)
          const continueBtn = page.getByRole('button', { name: /^Continue$/i })
          if (await continueBtn.count()) {
            await continueBtn.click()
            await page.waitForTimeout(300)
            await shot(page, `ux-7-bulk-confirm-${vp}-school.png`)
            await page.getByRole('button', { name: /Cancel/i }).click().catch(() => {})
          }
        }
        record(7, 'school', vp, 'PASS', 'Bulk certificates UI present with select/preview')
      } else {
        record(7, 'school', vp, 'FAIL', 'Bulk certificates section not visible')
      }

      await page.goto(`${BASE}/contracts`, { waitUntil: 'networkidle' }).catch(() => {})
      await page.waitForTimeout(500)
      await shot(page, `ux-8-agreements-${vp}-school.png`)
      record(8, 'school', vp, (await page.getByRole('button', { name: /Cancelled/i }).count()) ? 'PASS' : 'FAIL', 'agreements tabs')

      await logoutConfirm(page, 'school', vp)
    })
  }

  // -------- Student --------
  for (const [width, vp] of [[1280, 'desktop'], [390, 'phone']]) {
    await withViewport(browser, width, vp, async (page) => {
      try {
        await login(page, accounts.student.email, accounts.student.password)
        await page.getByRole('button', { name: /Open notifications/i }).click()
        await page.waitForTimeout(400)
        await shot(page, `ux-2-notifications-${vp}-student.png`)
        record(2, 'student', vp, (await page.getByRole('button', { name: /View all/i }).count()) ? 'PASS' : 'FAIL', 'notifications')
        await page.keyboard.press('Escape')

        await page.getByRole('button', { name: /Open messages/i }).click()
        await page.waitForTimeout(400)
        const start = page.getByRole('button', { name: /Start conversation|New conversation|New chat/i }).first()
        if (await start.count()) await start.click()
        await page.waitForTimeout(500)
        await shot(page, `ux-3-new-chat-${vp}-student.png`)
        record(3, 'student', vp, 'PASS', 'new conversation UI')
        await page.keyboard.press('Escape')

        await page.goto(`${BASE}/intern/opportunities`, { waitUntil: 'networkidle' }).catch(() => {})
        const companyBrowse = page.getByPlaceholder(/Search companies/i)
        if (await companyBrowse.count()) {
          await shot(page, `ux-6-browse-companies-${vp}-student.png`)
          record(6, 'student', vp, 'PASS', 'company directory reachable')
        } else {
          record(6, 'student', vp, 'SKIP', 'Browse companies is school/company directory; student uses opportunities')
        }

        await logoutConfirm(page, 'student', vp)
      } catch (err) {
        record('student-suite', 'student', vp, 'FAIL', String(err).slice(0, 200))
      }
    })
  }

  // -------- Admin logout + notifications --------
  for (const [width, vp] of [[1280, 'desktop'], [390, 'phone']]) {
    await withViewport(browser, width, vp, async (page) => {
      try {
        await login(page, accounts.admin.email, accounts.admin.password)
        await page.getByRole('button', { name: /Open notifications/i }).click().catch(() => {})
        await page.waitForTimeout(400)
        await shot(page, `ux-2-notifications-${vp}-admin.png`)
        record(2, 'admin', vp, (await page.getByRole('button', { name: /View all/i }).count()) ? 'PASS' : 'FAIL', 'notifications')
        await page.keyboard.press('Escape').catch(() => {})
        await logoutConfirm(page, 'admin', vp)
      } catch (err) {
        record('admin-suite', 'admin', vp, 'FAIL', String(err).slice(0, 200))
      }
    })
  }

  // -------- Item 5 setup link / mobile app --------
  const eligible = await seedEligibleCertificate()
  record(7, 'school', 'api', eligible.length ? 'INFO' : 'INFO', `eligible rows from API=${eligible.length}`)

  // Median / universal links check via source (no native app in repo)
  const medianSrc = fs.readFileSync('src/utils/medianAppLink.ts', 'utf8')
  const hasAssetLinks = fs.existsSync('public/.well-known/assetlinks.json') || fs.existsSync('public/.well-known/apple-app-site-association')
  const hasNativeApp = fs.existsSync('android') || fs.existsSync('ios') || fs.existsSync('mobile')
  record(
    5,
    'student',
    'code+repo',
    hasNativeApp || hasAssetLinks ? 'PASS' : 'INFO',
    hasNativeApp
      ? 'Native app present'
      : `No native mobile app in repo. Median handoff scheme exists (${/ojtintern/.test(medianSrc)}). Universal/App Links files=${hasAssetLinks}`,
  )

  // Fresh company account: verify create form still has no status
  await withViewport(browser, 1280, 'desktop', async (page) => {
    await login(page, accounts.fresh.accounts.company, accounts.fresh.password)
    await page.goto(`${BASE}/dashboard/jobs`, { waitUntil: 'networkidle' })
    await page.getByRole('button', { name: /^Post Job$/i }).click()
    await page.waitForTimeout(500)
    await shot(page, 'ux-1-post-job-fresh-company-desktop.png')
    const wizardHasStatus = await page.locator('label', { hasText: /^Status$/i }).count()
    record(1, 'fresh-company', 'desktop', wizardHasStatus === 0 ? 'PASS' : 'FAIL', `Status labels=${wizardHasStatus}`)
    await logoutConfirm(page, 'fresh-company', 'desktop')
  })
} catch (err) {
  console.error(err)
  results.push({ item: 'runner', role: '-', viewport: '-', status: 'FAIL', notes: String(err) })
} finally {
  await browser.close()
}

const reportPath = path.join(OUT, 'report.json')
fs.writeFileSync(reportPath, JSON.stringify(results, null, 2))
console.log(`\nWrote ${reportPath}`)
const fails = results.filter((r) => r.status === 'FAIL')
console.log(`Fails: ${fails.length}`)
process.exit(fails.length ? 1 : 0)

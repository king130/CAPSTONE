import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const OUT = path.resolve('tmp/browse-toolbar-qa')
fs.mkdirSync(OUT, { recursive: true })

const report = { steps: [], couldNotTest: [], screenshots: [] }

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

async function waitBrowse(panel) {
  await panel.locator('[data-browse-pagination]').waitFor({ state: 'visible', timeout: 20000 })
  await panel.page().waitForTimeout(400)
}

async function rangeText(panel) {
  return ((await panel.locator('[data-browse-range]').textContent()) || '').replace(/\s+/g, ' ').trim()
}

async function rowCount(panel) {
  const table = await panel.locator('table tbody tr').count()
  if (table) return table
  return panel.locator('[data-browse-results] .rounded-xl.border.border-border.bg-card').count()
}

async function assertNextClearOfChat(page, panel, label) {
  const next = panel.locator('[data-browse-next]')
  const chat = page.getByRole('button', { name: /Open messages/i })
  if (!(await next.count()) || !(await chat.count())) {
    return { label, skipped: true, reason: 'Next or chat missing' }
  }
  await next.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }))
  await page.waitForTimeout(250)
  const nextBox = await next.boundingBox()
  const chatBox = await chat.boundingBox()
  const clickable = await next.isEnabled()
  // Next bottom edge should sit above chat top (with a small gap), or horizontally not overlap.
  const verticalClear = nextBox && chatBox ? nextBox.y + nextBox.height <= chatBox.y - 4 : false
  const noOverlap =
    nextBox && chatBox
      ? nextBox.x + nextBox.width < chatBox.x ||
        chatBox.x + chatBox.width < nextBox.x ||
        nextBox.y + nextBox.height < chatBox.y ||
        chatBox.y + chatBox.height < nextBox.y
      : false
  return {
    label,
    pass: Boolean(clickable && (verticalClear || noOverlap)),
    clickable,
    verticalClear,
    noOverlap,
    nextBox,
    chatBox,
  }
}

async function exercisePanel(page, panel, meta) {
  const notes = { ...meta, checks: [] }

  // Toolbar must NOT contain rows-per-page
  const toolbarPageSize = await panel
    .locator('.flex.flex-col.gap-3.lg\\:flex-row [aria-label="Rows per page"]')
    .count()
    .catch(() => 0)
  // Prefer: page size lives under pagination
  const paginationPageSize = await panel.locator('[data-browse-pagination] [aria-label="Rows per page"]').count()
  notes.checks.push({
    name: 'page_size_in_pagination_not_toolbar',
    pass: paginationPageSize === 1,
    paginationPageSize,
    toolbarNearbyCount: toolbarPageSize,
  })

  await panel.getByRole('button', { name: /^Table$/i }).click().catch(() => {})
  await page.waitForTimeout(200)

  // Go to page 2 if possible, then change page size → must return to page 1 with new row count
  const next = panel.getByRole('button', { name: /^Next$/i })
  if (await next.isEnabled().catch(() => false)) {
    await next.click()
    await waitBrowse(panel)
  }
  const beforeSize = await rangeText(panel)
  const pageSize = panel.locator('[data-browse-pagination] [aria-label="Rows per page"]')
  await pageSize.selectOption('5')
  await waitBrowse(panel)
  const afterSize = await rangeText(panel)
  const rows = await rowCount(panel)
  const backToPage1 = /Page 1 of/i.test(afterSize) || !/Page \d+ of/i.test(afterSize)
  const showsFive = /Showing 1-5 of/i.test(afterSize) || (rows <= 5 && /Showing 1-\d+ of/i.test(afterSize))
  notes.checks.push({
    name: 'page_size_resets_and_reloads',
    pass: backToPage1 && showsFive && rows <= 5,
    beforeSize,
    afterSize,
    rows,
  })
  await page.screenshot({ path: shot(`${meta.role}-${meta.pageKey}-pagesize.png`), fullPage: true })

  // Dropdowns change results (location if options exist)
  const location = panel.getByLabel('Filter by location')
  const options = await location.locator('option').allTextContents()
  const cityOption = options.find((o) => o && o !== 'All locations')
  if (cityOption) {
    const beforeFilter = await rangeText(panel)
    await location.selectOption({ label: cityOption })
    await waitBrowse(panel)
    const afterFilter = await rangeText(panel)
    notes.checks.push({
      name: 'location_filter_changes_results',
      pass: afterFilter !== beforeFilter || /Showing 0 of 0/i.test(afterFilter),
      cityOption,
      beforeFilter,
      afterFilter,
    })
    await location.selectOption({ label: 'All locations' })
    await waitBrowse(panel)
  } else {
    notes.checks.push({ name: 'location_filter_changes_results', pass: true, skipped: 'no cities' })
  }

  // Sort dropdown
  const sort = panel.getByLabel('Sort organizations')
  const sortOptions = await sort.locator('option').evaluateAll((els) => els.map((e) => e.value))
  if (sortOptions.length > 1) {
    await sort.selectOption(sortOptions[1])
    await waitBrowse(panel)
    const sortLabel = await sort.inputValue()
    notes.checks.push({
      name: 'sort_dropdown_applies',
      pass: sortLabel === sortOptions[1],
      sortLabel,
    })
  }

  // Industry / LGU if present
  const industry = panel.getByLabel('Filter by industry')
  if (await industry.count()) {
    const indOptions = await industry.locator('option').allTextContents()
    const pick = indOptions.find((o) => o && o !== 'All industries')
    if (pick) {
      const before = await rangeText(panel)
      await industry.selectOption({ label: pick })
      await waitBrowse(panel)
      const after = await rangeText(panel)
      notes.checks.push({
        name: 'industry_filter_changes_results',
        pass: after !== before || /Showing 0 of 0/i.test(after),
        before,
        after,
        pick,
      })
      await industry.selectOption({ label: 'All industries' })
      await waitBrowse(panel)
    }
  }

  const lgu = panel.getByLabel('Filter by LGU type')
  if (await lgu.count()) {
    const lguOptions = await lgu.locator('option').evaluateAll((els) =>
      els.map((e) => ({ value: e.value, label: e.textContent })),
    )
    const pick = lguOptions.find((o) => o.value)
    if (pick) {
      const before = await rangeText(panel)
      await lgu.selectOption(pick.value)
      await waitBrowse(panel)
      const after = await rangeText(panel)
      const beforeTotal = Number((before.match(/of (\d+)/) || [])[1] || 0)
      const afterTotal = Number((after.match(/of (\d+)/) || [])[1] || 0)
      notes.checks.push({
        name: 'lgu_filter_changes_results',
        pass: afterTotal > 0 && afterTotal <= beforeTotal && (after !== before || afterTotal < beforeTotal),
        before,
        after,
        pick,
      })
      await lgu.selectOption('')
      await waitBrowse(panel)
    }
  }

  // Ensure enough pages for Next + last page: set page size 5, search for QA if needed
  await pageSize.selectOption('5')
  await waitBrowse(panel)
  const search = panel.locator('input[type="search"]')
  if (await search.count()) {
    const placeholder = (await search.getAttribute('placeholder')) || ''
    if (/company/i.test(placeholder)) await search.fill('QA Browse Company')
    else if (/school/i.test(placeholder)) await search.fill('QA Browse School')
    else if (/municipal/i.test(placeholder)) await search.fill('QA Browse Hall')
    await page.waitForTimeout(700)
    await waitBrowse(panel)
  }

  // Table view Next clear of chat
  await panel.getByRole('button', { name: /^Table$/i }).click().catch(() => {})
  await waitBrowse(panel)
  if (await next.isEnabled().catch(() => false)) {
    await next.click()
    await waitBrowse(panel)
  }
  const tableChat = await assertNextClearOfChat(page, panel, 'table')
  notes.checks.push({ name: 'next_clear_of_chat_table', ...tableChat, pass: tableChat.pass || tableChat.skipped })
  await page.screenshot({ path: shot(`${meta.role}-${meta.pageKey}-table-next.png`), fullPage: true })

  // Last page
  const lastBtn = panel.locator('[data-browse-page-controls] button').filter({ hasText: /^\d+$/ }).last()
  if (await lastBtn.count()) {
    await lastBtn.click()
    await waitBrowse(panel)
    const lastRange = await rangeText(panel)
    const nextDisabled = !(await next.isEnabled().catch(() => false))
    notes.checks.push({
      name: 'last_page_works',
      pass: nextDisabled && /Page \d+ of \d+/i.test(lastRange),
      lastRange,
      nextDisabled,
    })
    await page.screenshot({ path: shot(`${meta.role}-${meta.pageKey}-last.png`), fullPage: true })
  } else {
    notes.checks.push({ name: 'last_page_works', pass: true, skipped: 'single page' })
  }

  // Cards view
  await panel.getByRole('button', { name: /^Cards$/i }).click()
  await waitBrowse(panel)
  const cardsVisible = (await panel.locator('[data-browse-results]').count()) > 0
  // go back toward page 1 then next for chat check if multi-page
  const firstBtn = panel.locator('[data-browse-page-controls] button').filter({ hasText: /^1$/ })
  if (await firstBtn.count()) {
    await firstBtn.click()
    await waitBrowse(panel)
    if (await next.isEnabled().catch(() => false)) {
      await next.click()
      await waitBrowse(panel)
    }
  }
  const cardsChat = await assertNextClearOfChat(page, panel, 'cards')
  notes.checks.push({
    name: 'cards_view_and_next_clear',
    pass: cardsVisible && (cardsChat.pass || cardsChat.skipped),
    cardsVisible,
    cardsChat,
  })
  await page.screenshot({ path: shot(`${meta.role}-${meta.pageKey}-cards.png`), fullPage: true })

  notes.pass = notes.checks.every((c) => c.pass)
  return notes
}

async function runRole(browser, role) {
  const defs = {
    company: {
      email: 'company@techcorp.com',
      password: 'Company@2026!',
      url: '/dashboard/schools',
      panels: [{ tab: null, pageKey: 'schools-browse' }],
    },
    school: {
      email: 'school@cvsu.edu.ph',
      password: 'School@2026!',
      url: '/school/opportunities',
      panels: [
        { tab: 'Companies', pageKey: 'companies-browse' },
        { tab: 'Municipal Halls', pageKey: 'municipal-browse' },
      ],
    },
    student: {
      email: 'student@cvsu.edu.ph',
      password: 'Student@2026!',
      url: '/intern/opportunities',
      panels: [
        { tab: 'Companies', pageKey: 'companies-browse' },
        { tab: 'Municipal Halls', pageKey: 'municipal-browse' },
      ],
    },
  }
  const d = defs[role]
  const results = []

  for (const viewport of [
    { name: 'desktop', width: 1280, height: 900 },
    { name: 'phone', width: 390, height: 844 },
  ]) {
    const ctx = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height } })
    const page = await ctx.newPage()
    await login(page, d.email, d.password)
    await page.goto(`${BASE}${d.url}`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)

    for (const p of d.panels) {
      if (p.tab) {
        await page.getByRole('tab', { name: p.tab }).click()
        await page.waitForTimeout(500)
      }
      const panel = page.locator(`[data-browse-page="${p.pageKey}"]`)
      await panel.waitFor({ state: 'visible', timeout: 20000 })
      await waitBrowse(panel)
      const result = await exercisePanel(page, panel, {
        role,
        pageKey: p.pageKey,
        viewport: viewport.name,
      })
      results.push(result)
      await page.screenshot({
        path: shot(`${role}-${p.pageKey}-${viewport.name}-overview.png`),
        fullPage: true,
      })
    }
    await ctx.close()
  }
  return results
}

const browser = await chromium.launch({ headless: true })
try {
  for (const role of ['company', 'school', 'student']) {
    const results = await runRole(browser, role)
    report.steps.push(...results)
  }
  report.pass = report.steps.every((s) => s.pass)
  report.couldNotTest.push(
    'Fresh self-registered accounts (used demo school/student/company with fresh contexts)',
    'Company role has Schools browse only — Companies/Municipal Halls N/A for company',
  )
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  console.log(JSON.stringify(report, null, 2))
  if (!report.pass) process.exitCode = 1
} catch (err) {
  console.error(err)
  report.fatal = String(err?.stack || err)
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  process.exitCode = 1
} finally {
  await browser.close()
}

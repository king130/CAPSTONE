import { chromium } from 'playwright'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const OUT = path.resolve('tmp/browse-select-style-qa')
fs.mkdirSync(OUT, { recursive: true })

const report = { checks: [], screenshots: [], couldNotTest: [] }

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

async function setDark(page, enabled) {
  await page.evaluate((on) => {
    const root = document.documentElement
    if (on) root.classList.add('dark')
    else root.classList.remove('dark')
    localStorage.setItem('theme', on ? 'dark' : 'light')
  }, enabled)
  await page.waitForTimeout(200)
}

async function measureSelect(page, panelSelector, ariaLabel) {
  return page.evaluate(
    ({ sel, label }) => {
      const root = document.querySelector(sel)
      const select = root?.querySelector(`select[aria-label="${label}"]`)
      if (!select) return { found: false, label }
      // Skip hidden v-show panels
      if (select.getClientRects().length === 0) return { found: false, label, hidden: true }
      const wrapper = select.parentElement
      const icon = wrapper?.querySelector('svg')
      const s = select.getBoundingClientRect()
      const w = wrapper?.getBoundingClientRect()
      const i = icon?.getBoundingClientRect()
      const style = getComputedStyle(select)
      return {
        found: true,
        label,
        select: { x: s.x, y: s.y, width: s.width, height: s.height, right: s.right, top: s.top },
        wrapper: w ? { x: w.x, y: w.y, width: w.width, height: w.height, right: w.right } : null,
        icon: i ? { x: i.x, y: i.y, width: i.width, height: i.height, right: i.right, top: i.top } : null,
        appearance: style.appearance || style.webkitAppearance,
        paddingRight: style.paddingRight,
        borderRadius: style.borderRadius,
        widthOk: s.width >= 110 && s.width <= 130,
        wrapperMatchesSelect: w ? Math.abs(w.width - s.width) <= 2 : false,
        iconInside:
          i && w
            ? i.right <= w.right + 1 && i.left >= w.left - 1 && i.top >= w.top - 2 && i.bottom <= w.bottom + 2
            : false,
        iconNearRightEdge: i && w ? w.right - i.right >= 6 && w.right - i.right <= 20 : false,
      }
    },
    { sel: panelSelector, label: ariaLabel },
  )
}

async function measureToolbarSelects(page, panelSelector) {
  return page.evaluate((sel) => {
    const root = document.querySelector(sel)
    if (!root) return []
    return [...root.querySelectorAll('select')]
      .filter((select) => select.getClientRects().length > 0)
      .map((select) => {
        const label = select.getAttribute('aria-label') || select.name || 'unknown'
        const wrapper = select.parentElement
        const icon = wrapper?.querySelector('svg')
        const s = select.getBoundingClientRect()
        const w = wrapper?.getBoundingClientRect()
        const i = icon?.getBoundingClientRect()
        return {
          label,
          width: s.width,
          wrapperMatchesSelect: w ? Math.abs(w.width - s.width) <= 2 : false,
          iconInside:
            i && w
              ? i.right <= w.right + 1 && i.left >= w.left - 1 && i.top >= w.top - 2 && i.bottom <= w.bottom + 2
              : false,
        }
      })
  }, panelSelector)
}

async function footerLayout(page, panelSelector) {
  const pag = page.locator(`${panelSelector} [data-browse-pagination]`)
  await pag.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }))
  await page.waitForTimeout(200)
  return page.evaluate((sel) => {
    const panel = document.querySelector(sel)
    const range = panel?.querySelector('[data-browse-range]')
    const size = panel?.querySelector('[aria-label="Rows per page"]')
    const next = panel?.querySelector('[data-browse-next]')
    const chat = document.querySelector('button[aria-label*="Open messages"]')
    if (!range || !size) return { found: false }
    const rr = range.getBoundingClientRect()
    const sr = size.getBoundingClientRect()
    const nr = next?.getBoundingClientRect()
    const cr = chat?.getBoundingClientRect()
    const sameLine =
      Math.abs(rr.top - sr.top) < 20 && (!nr || Math.abs(sr.top - nr.top) < 28)
    const overlaps = (a, b) =>
      a && b && !(a.right < b.left || b.right < a.left || a.bottom < b.top || b.bottom < a.top)
    const nextClearOfChat = !nr || !cr || !overlaps(nr, cr)
    return {
      found: true,
      sameLine,
      nextClearOfChat,
      rangeY: rr.top,
      sizeY: sr.top,
      nextY: nr?.top ?? null,
      nextBottom: nr?.bottom ?? null,
      chatTop: cr?.top ?? null,
    }
  }, panelSelector)
}

const scenarios = [
  {
    role: 'company',
    email: 'company@techcorp.com',
    password: 'Company@2026!',
    url: '/dashboard/schools',
    panels: [{ tab: null, key: 'schools-browse', title: 'schools' }],
  },
  {
    role: 'school',
    email: 'school@cvsu.edu.ph',
    password: 'School@2026!',
    url: '/school/opportunities',
    panels: [
      { tab: 'Companies', key: 'companies-browse', title: 'companies' },
      { tab: 'Municipal Halls', key: 'municipal-browse', title: 'municipal' },
    ],
  },
  {
    role: 'student',
    email: 'student@cvsu.edu.ph',
    password: 'Student@2026!',
    url: '/intern/opportunities',
    panels: [
      { tab: 'Companies', key: 'companies-browse', title: 'companies' },
      { tab: 'Municipal Halls', key: 'municipal-browse', title: 'municipal' },
    ],
  },
]

const browser = await chromium.launch({ headless: true })
try {
  for (const mode of ['light', 'dark']) {
    for (const vp of [
      { name: 'desktop', width: 1280, height: 900 },
      { name: 'phone', width: 390, height: 844 },
    ]) {
      for (const sc of scenarios) {
        const ctx = await browser.newContext({
          viewport: { width: vp.width, height: vp.height },
          colorScheme: mode === 'dark' ? 'dark' : 'light',
        })
        const page = await ctx.newPage()
        await login(page, sc.email, sc.password)
        await page.goto(`${BASE}${sc.url}`, { waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(1200)
        await setDark(page, mode === 'dark')

        for (const panel of sc.panels) {
          if (panel.tab) {
            await page.getByRole('tab', { name: panel.tab }).click()
            await page.waitForTimeout(500)
          }
          const panelSel = `[data-browse-page="${panel.key}"]`
          await page.locator(panelSel).waitFor({ state: 'visible', timeout: 15000 })
          await page.locator(`${panelSel} [data-browse-pagination]`).waitFor({ state: 'visible', timeout: 15000 })
          await page.waitForTimeout(300)

          const footer = await footerLayout(page, panelSel)
          const pageSize = await measureSelect(page, panelSel, 'Rows per page')
          const toolbar = await measureToolbarSelects(page, panelSel)

          const pass =
            pageSize.found &&
            pageSize.wrapperMatchesSelect &&
            pageSize.iconInside &&
            pageSize.iconNearRightEdge &&
            pageSize.widthOk &&
            String(pageSize.appearance).includes('none') &&
            toolbar.every((t) => t.wrapperMatchesSelect && t.iconInside) &&
            footer.found &&
            (vp.name === 'desktop' ? footer.sameLine : true) &&
            footer.nextClearOfChat

          report.checks.push({
            mode,
            viewport: vp.name,
            role: sc.role,
            page: panel.title,
            pass,
            pageSize,
            toolbar,
            footer,
          })

          const file = `${mode}-${vp.name}-${sc.role}-${panel.title}.png`
          await page.locator(panelSel).locator('[data-browse-pagination]').scrollIntoViewIfNeeded()
          await page.waitForTimeout(150)
          await page.screenshot({ path: shot(file), fullPage: true })
        }

        // Settings select sample (company only, once per mode/desktop)
        if (sc.role === 'company' && vp.name === 'desktop') {
          await page.goto(`${BASE}/settings`, { waitUntil: 'domcontentloaded' })
          await page.waitForTimeout(1000)
          await setDark(page, mode === 'dark')
          const settingsSelects = await page.evaluate(() => {
            return [...document.querySelectorAll('select')].slice(0, 6).map((select) => {
              const wrapper = select.parentElement
              const icon = wrapper?.querySelector('svg')
              const s = select.getBoundingClientRect()
              const w = wrapper?.getBoundingClientRect()
              const i = icon?.getBoundingClientRect()
              return {
                id: select.id || select.name || select.getAttribute('aria-label') || 'select',
                wrapperMatchesSelect: w ? Math.abs(w.width - s.width) <= 2 : false,
                iconInside:
                  i && w
                    ? i.right <= w.right + 1 && i.left >= w.left - 1 && i.top >= w.top - 2 && i.bottom <= w.bottom + 2
                    : !icon,
                usesSharedSelect: Boolean(icon),
              }
            })
          })
          report.checks.push({
            mode,
            viewport: vp.name,
            role: 'company',
            page: 'settings',
            pass: settingsSelects
              .filter((s) => s.usesSharedSelect)
              .every((s) => s.wrapperMatchesSelect && s.iconInside),
            settingsSelects,
          })
          await page.screenshot({ path: shot(`${mode}-desktop-company-settings.png`), fullPage: true })
        }

        await ctx.close()
      }
    }
  }

  report.pass = report.checks.every((c) => c.pass)
  report.couldNotTest.push(
    'Native <select> elements that do not use the shared Select.vue component (if any remain)',
  )
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  console.log(JSON.stringify({ pass: report.pass, failed: report.checks.filter((c) => !c.pass), screenshots: report.screenshots.length }, null, 2))
  if (!report.pass) process.exitCode = 1
} catch (err) {
  console.error(err)
  report.fatal = String(err?.stack || err)
  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  process.exitCode = 1
} finally {
  await browser.close()
}

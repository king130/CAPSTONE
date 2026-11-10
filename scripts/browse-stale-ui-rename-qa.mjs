import { chromium } from 'playwright'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:5173'
const OUT = path.resolve('tmp/browse-followup-qa')
const BACKEND = path.resolve('backend')

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

function readOrgName() {
  const code = `
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$o = App\\Models\\Organization::find(1);
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

const renamed = `TechCorp UI Rename ${Date.now()}`
const browser = await chromium.launch({ headless: true })
try {
  renameOrg('TechCorp Solutions')

  const studentCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const student = await studentCtx.newPage()
  await login(student, 'student@cvsu.edu.ph', 'Student@2026!')
  await student.goto(`${BASE}/intern/opportunities`, { waitUntil: 'networkidle' })
  await student.getByRole('tab', { name: 'Companies' }).waitFor({ timeout: 15000 })
  const panel = student.locator('[data-browse-page="companies-browse"]')
  await panel.getByLabel('Search companies').fill('TechCorp')
  await student.waitForTimeout(800)
  const before = await panel.locator('table tbody tr td:first-child').allTextContents()
  await student.getByRole('tab', { name: 'Municipal Halls' }).click()
  await student.waitForTimeout(400)

  const companyCtx = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const company = await companyCtx.newPage()
  await login(company, 'company@techcorp.com', 'Company@2026!')
  await company.goto(`${BASE}/settings`, { waitUntil: 'networkidle' })
  await company.waitForTimeout(1200)
  await company.locator('#companyName').fill(renamed)

  await company.getByRole('button', { name: /^Save Changes$/i }).click()
  // SweetAlert confirmation uses the same label.
  await company.waitForTimeout(400)
  const swal = company.locator('.swal2-confirm')
  if (await swal.count()) await swal.click()
  await company.waitForTimeout(2500)
  const savedName = await company.locator('#companyName').inputValue()
  const dbName = readOrgName()
  await company.screenshot({ path: path.join(OUT, '06-company-rename-ui.png'), fullPage: true })
  await companyCtx.close()

  await student.getByRole('tab', { name: 'Companies' }).click()
  await student.waitForTimeout(1500)
  await panel.getByLabel('Search companies').fill('TechCorp')
  await student.waitForTimeout(900)
  const after = await panel.locator('table tbody tr td:first-child').allTextContents()
  const body = await panel.innerText()
  await student.screenshot({ path: path.join(OUT, '06-stale-after-ui-rename.png'), fullPage: true })
  await studentCtx.close()

  const result = {
    renamed,
    savedName,
    dbName,
    before,
    after,
    bodyHasNew: body.includes(renamed),
    pass: dbName.includes('UI Rename') && (body.includes(renamed) || after.some((n) => n.includes('UI Rename'))),
  }
  fs.writeFileSync(path.join(OUT, 'stale-ui-rename.json'), JSON.stringify(result, null, 2))
  console.log(JSON.stringify(result, null, 2))
} finally {
  try {
    renameOrg('TechCorp Solutions')
  } catch {}
  await browser.close()
}

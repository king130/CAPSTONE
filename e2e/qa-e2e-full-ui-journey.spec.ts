/**
 * Headed full OJT UI journey — fails the test when any step is FAIL.
 * Run: npx playwright test e2e/qa-e2e-full-ui-journey.spec.ts --headed --workers=1
 */
import { test, expect, type BrowserContext, type Page, type APIRequestContext } from '@playwright/test'
import * as fs from 'node:fs'
import * as path from 'node:path'
import { execSync } from 'node:child_process'
import {
  DEMO,
  ensureLoggedIn,
  loginAsFresh,
  completeStudentAccountSetup,
  markLaravelLogCursor,
  waitForAccountSetupUrl,
  newRoleContext,
  readAuthToken,
  refreshAuthProfile,
  waitForAppShell,
  logStep,
  logResult,
  shotJourney,
  TOKEN_KEY,
} from './helpers/auth'
import { fillValidRegistration, gotoRegister, submitRegister, uniqueEmail } from './helpers/register'

const SHOT = 'e2e/qa-screenshots/e2e-journey'
const FIXTURE = path.join('e2e', 'fixtures', 'qa-doc.pdf')
const RESULTS = path.join(SHOT, 'RESULTS.md')
const API = 'http://127.0.0.1:8000/api'
const PASSWORD = 'ValidPass1'
const STUDENT_PASSWORD = 'StudentQa1!'
const TEACHER_PASSWORD = 'TeacherQa1!'

type StepResult = 'PASS' | 'FAIL' | 'NOT TESTED' | 'API ONLY, UI NOT TESTED'

const stamp = Date.now()
const QA = {
  schoolName: `QA-School ${stamp}`,
  companyName: `QA-Company ${stamp}`,
  schoolEmail: uniqueEmail('school', `rerun${stamp}`),
  companyEmail: uniqueEmail('company', `rerun${stamp}`),
  teacherEmail: `qa.teacher.${stamp}@example.com`,
  teacherName: `QA-Teacher ${stamp}`,
  studentFirst: 'QA',
  studentLast: `Intern${stamp.toString().slice(-6)}`,
  courseName: 'QA BSIT',
  requiredHours: 40,
  hostHours: 80,
  jobTitle: `QA-Job ${stamp}`,
}

const results: Array<{ step: string; result: StepResult; evidence: string }> = []
let hardFailures = 0
let studentEmail = ''
let applicationId: number | undefined
let internshipId: number | undefined
let setupLogCursor = 0

function ensureDirs() {
  fs.mkdirSync(SHOT, { recursive: true })
  if (!fs.existsSync(FIXTURE)) {
    fs.writeFileSync(FIXTURE, Buffer.from('%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n'))
  }
  fs.writeFileSync(
    RESULTS,
    `# E2E UI Journey Re-run\n\nQA stamp: ${stamp}\n\n| Step | Result | Evidence |\n|---|---|---|\n`
  )
}

function record(step: string, result: StepResult, evidence: string) {
  results.push({ step, result, evidence })
  logResult(result, evidence)
  fs.appendFileSync(RESULTS, `| ${step} | ${result} | ${evidence.replace(/\|/g, '/').replace(/\n/g, ' ')} |\n`)
  if (result === 'FAIL') hardFailures += 1
}

async function hold(page: Page, ms = 2000) {
  await page.waitForTimeout(ms)
}

/** SweetAlert2 is DOM-based (not a native dialog). */
async function clickSwalConfirm(page: Page, name: RegExp | string) {
  const btn = page.locator('.swal2-popup').getByRole('button', { name }).first()
  await expect(btn).toBeVisible({ timeout: 15_000 })
  await btn.click()
}

/** HoursAgreementPanel uses window.confirm — stub it true so Vue @click always proceeds. */
async function clickConfirming(page: Page, locator: ReturnType<Page['locator']> | ReturnType<Page['getByRole']>) {
  await expect(locator).toBeVisible({ timeout: 15_000 })
  await page.evaluate(() => {
    ;(window as any).__capstoneConfirm = window.confirm
    window.confirm = () => true
  })
  await locator.click()
  await page.waitForTimeout(600)
  await page.evaluate(() => {
    if ((window as any).__capstoneConfirm) window.confirm = (window as any).__capstoneConfirm
  })
}

async function uploadAllVerificationDocs(page: Page) {
  const items = page.getByRole('listitem').filter({ has: page.locator('input[type="file"]') })
  const count = await items.count()
  for (let i = 0; i < count; i++) {
    const input = items.nth(i).locator('input[type="file"]')
    if (await input.count()) {
      await input.setInputFiles(FIXTURE)
      await page.waitForTimeout(600)
    }
  }
  return count
}

async function apiLogin(request: APIRequestContext, email: string, password: string) {
  const res = await request.post(`${API}/auth/login`, { data: { email, password } })
  expect(res.ok(), `login ${email}`).toBeTruthy()
  return res.json() as Promise<{ token: string; user: any }>
}

async function readCalendarHours(request: APIRequestContext, token: string, appId: number) {
  const cal = await request.get(`${API}/applications/${appId}/calendar`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  })
  if (!cal.ok()) return null
  const summary = (await cal.json()).data?.summary
  return {
    required: Number(summary?.requiredHours ?? 0),
    approved: Number(summary?.approvedHours ?? 0),
    remaining: Number(summary?.remainingHours ?? 0),
  }
}

/** Scope actions to the QA student row/card — never click the first View on the page. */
function qaStudentCard(page: Page) {
  const name = new RegExp(QA.studentLast, 'i')
  // Company applicants = <tr>; school endorsements = heading inside a card row.
  const tableRow = page.locator('tr').filter({ hasText: name }).first()
  const headingCard = page
    .getByRole('heading', { name })
    .locator(
      'xpath=ancestor::div[.//button[normalize-space()="View" or normalize-space()="Endorse" or contains(., "View Profile")]][1]'
    )
  return tableRow.or(headingCard).first()
}

test.describe.configure({ mode: 'serial' })
test.use({
  launchOptions: { headless: false, slowMo: 500 },
  viewport: { width: 1280, height: 800 },
})

test('Headed full OJT UI journey (hard-fail on step FAIL)', async ({ browser, request }) => {
  test.setTimeout(1_200_000)
  ensureDirs()

  let schoolCtx: BrowserContext | undefined
  let companyCtx: BrowserContext | undefined
  let adminCtx: BrowserContext | undefined
  let studentCtx: BrowserContext | undefined
  let teacherCtx: BrowserContext | undefined
  let schoolPage!: Page
  let companyPage!: Page
  let adminPage!: Page
  let studentPage!: Page

  try {
    // ── 1 School register ──────────────────────────────────────────
    logStep(1, 'Register QA-School + upload verification docs')
    {
      const r = await newRoleContext(browser)
      schoolCtx = r.context
      schoolPage = r.page
      await gotoRegister(schoolPage, 'school')
      await fillValidRegistration(schoolPage, 'school', QA.schoolEmail)
      await schoolPage.locator('#schoolName').fill(QA.schoolName)
      await shotJourney(schoolPage, 'r01-school-register')
      await submitRegister(schoolPage)
      await expect(schoolPage).toHaveURL(/\/login/, { timeout: 30_000 })
      await ensureLoggedIn(schoolPage, QA.schoolEmail, PASSWORD)
      if (!/organization-verification/i.test(schoolPage.url())) {
        record('1 school register', 'FAIL', `Expected verification gate, got ${schoolPage.url()}`)
      } else {
        const n = await uploadAllVerificationDocs(schoolPage)
        await shotJourney(schoolPage, 'r01-school-docs')
        record('1 school register', 'PASS', `Pending verification; uploaded ${n} docs`)
      }
      await hold(schoolPage)
    }

    // ── 2 Company register ─────────────────────────────────────────
    logStep(2, 'Register QA-Company + docs; trial')
    {
      const r = await newRoleContext(browser)
      companyCtx = r.context
      companyPage = r.page
      await gotoRegister(companyPage, 'company')
      await fillValidRegistration(companyPage, 'company', QA.companyEmail)
      await companyPage.locator('#companyName').fill(QA.companyName)
      await submitRegister(companyPage)
      await expect(companyPage).toHaveURL(/\/login/, { timeout: 30_000 })
      await ensureLoggedIn(companyPage, QA.companyEmail, PASSWORD)
      if (!/organization-verification/i.test(companyPage.url())) {
        record('2 company register', 'FAIL', `Expected verification gate, got ${companyPage.url()}`)
      } else {
        const n = await uploadAllVerificationDocs(companyPage)
        const body = await companyPage.locator('body').innerText()
        await shotJourney(companyPage, 'r02-company-docs')
        record('2 company register', 'PASS', `Docs=${n}; trialText=${/trial/i.test(body)}`)
      }
      await hold(companyPage)
    }

    // ── 3 Admin approve ────────────────────────────────────────────
    logStep(3, 'Admin approves QA-School and QA-Company')
    {
      const r = await newRoleContext(browser)
      adminCtx = r.context
      adminPage = r.page
      await ensureLoggedIn(adminPage, DEMO.admin.email, DEMO.admin.password)
      await adminPage.goto('/admin/schools')
      const sRow = adminPage.locator('tr, [role="row"]').filter({ hasText: QA.schoolName }).first()
      await expect(sRow).toBeVisible({ timeout: 20_000 })
      await sRow.getByRole('button', { name: /Actions/i }).click()
      const sMenu = adminPage.locator('[role="menu"]').last()
      await expect(sMenu).toBeVisible()
      await shotJourney(adminPage, 'r03-school-actions')
      await sMenu.getByText(/^Approve$/i).click()
      await expect(adminPage.getByRole('heading', { name: /Approve organization/i })).toBeVisible()
      await adminPage.getByRole('button', { name: /^Approve$/i }).last().click()
      await expect(sRow.getByText(/Approved|Verified/i).first()).toBeVisible({ timeout: 15_000 })

      await adminPage.goto('/admin/companies')
      const cRow = adminPage.locator('tr, [role="row"]').filter({ hasText: QA.companyName }).first()
      await expect(cRow).toBeVisible({ timeout: 20_000 })
      await cRow.getByRole('button', { name: /Actions/i }).click()
      const cMenu = adminPage.locator('[role="menu"]').last()
      await expect(cMenu).toBeVisible()
      await shotJourney(adminPage, 'r03-company-actions')
      await cMenu.getByText(/^Approve$/i).click()
      await expect(adminPage.getByRole('heading', { name: /Approve organization/i })).toBeVisible()
      await adminPage.getByRole('button', { name: /^Approve$/i }).last().click()
      await expect(cRow.getByText(/Approved|Verified/i).first()).toBeVisible({ timeout: 15_000 })
      record('3 admin approve', 'PASS', 'Both orgs approved with confirmation dialogs')
      await hold(adminPage)
    }

    // ── 4 Unlocked ─────────────────────────────────────────────────
    logStep(4, 'School and company login unlocked')
    {
      // Token still has pending verificationStatus until profile refresh after admin approve.
      await ensureLoggedIn(schoolPage, QA.schoolEmail, PASSWORD)
      await schoolPage.goto('/organization-verification')
      await schoolPage.waitForURL(/\/(school|dashboard)(\/|$)/, { timeout: 25_000 }).catch(async () => {
        await refreshAuthProfile(schoolPage)
        await schoolPage.goto('/school')
      })
      await waitForAppShell(schoolPage)
      await shotJourney(schoolPage, 'r04-school')
      if (/organization-verification/i.test(schoolPage.url())) {
        record('4 school unlock', 'FAIL', schoolPage.url())
      } else {
        record('4 school unlock', 'PASS', schoolPage.url())
      }
      await ensureLoggedIn(companyPage, QA.companyEmail, PASSWORD)
      await companyPage.goto('/organization-verification')
      await companyPage.waitForURL(/\/(school|dashboard)(\/|$)/, { timeout: 25_000 }).catch(async () => {
        await refreshAuthProfile(companyPage)
        await companyPage.goto('/dashboard')
      })
      await waitForAppShell(companyPage)
      await shotJourney(companyPage, 'r04-company')
      if (/organization-verification/i.test(companyPage.url())) {
        record('4 company unlock', 'FAIL', companyPage.url())
      } else {
        record('4 company unlock', 'PASS', companyPage.url())
      }
      await hold(companyPage)
    }

    // ── 5 Course setup ─────────────────────────────────────────────
    logStep(5, 'School sets QA course hours + year levels')
    {
      try {
        await schoolPage.goto('/settings')
        await schoolPage.waitForTimeout(800)
        // Course requirements panel — try common controls
        const courseBox = schoolPage.getByPlaceholder(/course|BSIT|program/i).first()
        if (await courseBox.isVisible().catch(() => false)) {
          await courseBox.fill(QA.courseName)
        }
        const hoursBox = schoolPage.locator('input[type="number"]').first()
        if (await hoursBox.isVisible().catch(() => false)) {
          await hoursBox.fill(String(QA.requiredHours))
        }
        for (const label of ['3rd Year', '4th Year']) {
          const cb = schoolPage.getByText(label, { exact: true }).first()
          if (await cb.isVisible().catch(() => false)) await cb.click().catch(() => {})
        }
        const save = schoolPage.getByRole('button', { name: /save|add course|create/i }).first()
        if (await save.isVisible().catch(() => false)) await save.click()
        await schoolPage.waitForTimeout(1000)

        // Ensure via API if UI ambiguous (but only record PASS if we verify course exists in UI later)
        const login = await apiLogin(request, QA.schoolEmail, PASSWORD)
        const list = await request.get(`${API}/school-course-requirements`, {
          headers: { Authorization: `Bearer ${login.token}` },
        })
        const courses = ((await list.json()).data || []) as Array<{ courseName: string; requiredHours: number }>
        let found = courses.find((c) => c.courseName === QA.courseName)
        if (!found) {
          const created = await request.post(`${API}/school-course-requirements`, {
            headers: { Authorization: `Bearer ${login.token}` },
            data: {
              courseName: QA.courseName,
              requiredHours: QA.requiredHours,
              eligibleYearLevels: ['3rd Year', '4th Year'],
            },
          })
          if (!created.ok()) throw new Error(`course create ${created.status()} ${await created.text()}`)
          record('5 course setup', 'API ONLY, UI NOT TESTED', 'UI did not clearly save; API created QA BSIT @ 40h')
        } else {
          await schoolPage.goto('/dashboard')
          await shotJourney(schoolPage, 'r05-dashboard')
          record('5 course setup', 'PASS', `Course ${QA.courseName} hours=${found.requiredHours}`)
        }
      } catch (e) {
        record('5 course setup', 'FAIL', String(e).slice(0, 200))
      }
    }

    // ── 6 Post job ─────────────────────────────────────────────────
    logStep(6, 'Company posts job with host hours 80')
    {
      try {
        await companyPage.goto('/dashboard')
        await companyPage.locator('main').getByRole('button', { name: /^Post a job$/i }).first().click()
        await expect(companyPage.getByText(/Create Job Posting|Basic Information/i).first()).toBeVisible({
          timeout: 20_000,
        })
        const title = companyPage.locator('#jobTitle').first()
        await expect(title).toBeVisible({ timeout: 10_000 })
        await title.fill(QA.jobTitle)

        // Jump to Internship Details for host hours, then walk wizard to final Create Job.
        await companyPage.getByRole('button', { name: /Internship Details/i }).click()
        const hostHours = companyPage.locator('#jobHostHours')
        await expect(hostHours).toBeVisible({ timeout: 10_000 })
        await hostHours.fill(String(QA.hostHours))

        for (let i = 0; i < 8; i++) {
          const createJob = companyPage.getByRole('button', { name: /^Create Job$/i })
          if (await createJob.isVisible().catch(() => false)) {
            await expect(createJob).toBeEnabled()
            await createJob.click()
            break
          }
          const next = companyPage.getByRole('button', { name: /^Next$/i })
          if (await next.isVisible().catch(() => false) && (await next.isEnabled().catch(() => false))) {
            await next.click()
            await companyPage.waitForTimeout(500)
            continue
          }
          throw new Error('Create Job never became visible in job wizard')
        }
        await companyPage.waitForTimeout(1500)
        await shotJourney(companyPage, 'r06-job')

        const login = await apiLogin(request, QA.companyEmail, PASSWORD)
        const jobs = await request.get(`${API}/internships`, {
          headers: { Authorization: `Bearer ${login.token}` },
        })
        const job = ((await jobs.json()).data || []).find((j: any) => String(j.title) === QA.jobTitle)
        if (!job) throw new Error('Job not found in company list after Create Job click')
        internshipId = Number(job.id)
        record('6 post job', 'PASS', `UI Create Job; id=${internshipId}; hostHours=${job.hostRequiredHours ?? job.host_required_hours}`)
      } catch (e) {
        record('6 post job', 'FAIL', String(e).slice(0, 200))
      }
      await hold(companyPage)
    }

    // ── 7 Agreement (UI) ───────────────────────────────────────────
    logStep(7, 'Company proposes agreement via /agreements/new; school accepts')
    {
      try {
        // Prior FAIL cause: clicked Send Contract Request but never confirmed SweetAlert
        // ("Send Request"), so no pending agreement existed for school Accept.
        await companyPage.goto('/agreements/new')
        await expect(companyPage.getByText(/Choose a school|Create an agreement/i).first()).toBeVisible({
          timeout: 20_000,
        })
        await shotJourney(companyPage, 'r07-choose-school')
        const partnerBtn = companyPage.getByRole('button').filter({ hasText: QA.schoolName }).first()
        await expect(partnerBtn).toBeVisible({ timeout: 20_000 })
        await partnerBtn.click()
        await companyPage.waitForTimeout(1000)

        const typeCard = companyPage.getByRole('button', { name: /OJT Memorandum of Agreement/i }).first()
        await expect(typeCard).toBeVisible({ timeout: 20_000 })
        await typeCard.click()

        await companyPage.getByPlaceholder(/Memorandum of Agreement/i).fill(`QA-MOA ${stamp}`)
        await companyPage.getByPlaceholder(/purpose of this contract/i).fill('E2E journey MOA')

        // Required dynamic fields on OJT MOA
        const programChip = companyPage
          .getByRole('button', { name: /BS Information Technology|QA BSIT|Computer Science|Information Systems/i })
          .first()
        if (await programChip.isVisible().catch(() => false)) {
          await programChip.click()
        } else {
          // Fall back: first chip inside Eligible Programs block
          const anyChip = companyPage.locator('button').filter({ hasText: /BS |Bachelor/i }).first()
          if (await anyChip.isVisible().catch(() => false)) await anyChip.click()
        }
        await companyPage.getByPlaceholder(/Primary Contact Person/i).fill('QA Contact')
        await companyPage.getByPlaceholder(/Primary Contact Email/i).fill(QA.companyEmail)

        await shotJourney(companyPage, 'r07-form')
        const submit = companyPage.getByRole('button', { name: /Send Contract Request/i })
        await expect(submit).toBeEnabled({ timeout: 10_000 })
        await submit.click()
        await clickSwalConfirm(companyPage, /Send Request/i)
        await companyPage.waitForURL(/\/agreements$/, { timeout: 20_000 }).catch(() => {})
        await companyPage.waitForTimeout(1500)
        await shotJourney(companyPage, 'r07-submitted')

        // Confirm pending exists before school Accept (distinguishes selector vs real submit bug)
        {
          const login = await apiLogin(request, QA.companyEmail, PASSWORD)
          const agr = await request.get(`${API}/agreements`, {
            headers: { Authorization: `Bearer ${login.token}` },
          })
          const pending = ((await agr.json()).data || []).find(
            (row: any) => /pending/i.test(String(row.status)) && String(row.subject || '').includes('QA-MOA')
          )
          if (!pending) {
            throw new Error('REAL BUG or submit incomplete: no pending QA-MOA after Send Request confirm')
          }
        }

        await schoolPage.goto('/agreements')
        await expect(schoolPage.getByText(/Agreement|Active|Pending/i).first()).toBeVisible({ timeout: 15_000 })
        await shotJourney(schoolPage, 'r07-school-list')
        const row = schoolPage.locator('.border-border').filter({ hasText: /QA-MOA|QA-Company/i }).first()
        const accept = (await row.count())
          ? row.getByRole('button', { name: /^Accept$/i }).first()
          : schoolPage.getByRole('button', { name: /^Accept$/i }).first()
        await expect(accept).toBeVisible({ timeout: 15_000 })
        await accept.click()
        // Contracts.vue opens a Dialog with Confirm Accept (not SweetAlert)
        const dialogConfirm = schoolPage.getByRole('button', { name: /Confirm Accept/i }).first()
        await expect(dialogConfirm).toBeVisible({ timeout: 10_000 })
        await dialogConfirm.click()
        await schoolPage.waitForTimeout(1200)
        await shotJourney(schoolPage, 'r07-accepted')
        const body = await schoolPage.locator('body').innerText()
        if (!/active/i.test(body)) {
          throw new Error('Accepted agreement not clearly Active on school UI')
        }
        record('7 agreement', 'PASS', 'Company proposed via /agreements/new + Swal; school Accept confirmed')
      } catch (e) {
        record('7 agreement', 'FAIL', `UI failure (selector/flow): ${String(e).slice(0, 280)}`)
      }
      await hold(schoolPage)
    }

    // ── 8 Add student (UI) ─────────────────────────────────────────
    logStep(8, 'School creates student via /school/students UI')
    {
      try {
        setupLogCursor = markLaravelLogCursor()
        // Prior failure: wrong URL (/school/students API vs UI) and free-typed email.
        // Real UI generates email from first/last + school domain and uses Create Student Account dialog.
        await schoolPage.goto('/school/students')
        await expect(schoolPage.getByRole('heading', { name: /Student Account/i }).first()).toBeVisible({
          timeout: 20_000,
        })
        await schoolPage.getByRole('button', { name: /Create Student Account/i }).click()
        await expect(schoolPage.getByRole('heading', { name: /Create Student Account/i })).toBeVisible()
        await schoolPage.getByPlaceholder('Juan').fill(QA.studentFirst)
        await schoolPage.getByPlaceholder('Dela Cruz').fill(QA.studentLast)
        // Course dropdown
        await schoolPage.getByRole('button', { name: /Select a saved school course|QA BSIT|Course/i }).first().click().catch(async () => {
          await schoolPage.locator('[data-student-dropdown]').filter({ hasText: /course|Select/i }).locator('button').first().click()
        })
        await schoolPage.waitForTimeout(300)
        const courseOpt = schoolPage.getByRole('button', { name: new RegExp(QA.courseName, 'i') }).first()
        if (await courseOpt.isVisible().catch(() => false)) {
          await courseOpt.click()
        } else {
          // click any course option containing QA or first real course
          await schoolPage.locator('.dropdown-option').filter({ hasText: /QA|BSIT|Computer/i }).first().click()
        }
        // Year level
        await schoolPage.getByRole('button', { name: /Select year level|Year/i }).first().click().catch(async () => {
          await schoolPage.locator('[data-student-dropdown]').filter({ hasText: /year/i }).locator('button').first().click()
        })
        await schoolPage.getByRole('button', { name: /4th Year/i }).first().click()
        // Capture generated email
        const emailInput = schoolPage.locator('input[readonly]').first()
        studentEmail = ((await emailInput.inputValue().catch(() => '')) || '').trim()
        await shotJourney(schoolPage, 'r08-student-form')
        await schoolPage.getByRole('button', { name: /Create Account|Create Student/i }).last().click()
        // SweetAlert confirm
        const swalConfirm = schoolPage.locator('.swal2-confirm')
        if (await swalConfirm.isVisible({ timeout: 5_000 }).catch(() => false)) {
          await swalConfirm.click()
        }
        await schoolPage.waitForTimeout(1500)
        if (await swalConfirm.isVisible().catch(() => false)) await swalConfirm.click()
        await shotJourney(schoolPage, 'r08-student-created')
        if (!studentEmail.includes('@')) {
          // fallback read from invite card
          const inviteEmail = schoolPage.locator('.text-sm.text-muted-foreground').filter({ hasText: /@/ }).first()
          studentEmail = ((await inviteEmail.textContent()) || '').trim()
        }
        if (!studentEmail.includes('@')) throw new Error('Generated student email not captured')
        record('8 add student', 'PASS', `Created via UI email=${studentEmail}`)
      } catch (e) {
        record('8 add student', 'FAIL', `UI failure: ${String(e).slice(0, 220)}`)
      }
      await hold(schoolPage)
    }

    // ── 9 Student setup ────────────────────────────────────────────
    logStep(9, 'Student account-setup from mail log; Confirm Account; no org verification')
    {
      try {
        if (!studentEmail) throw new Error('No student email from step 8')
        let setupUrl: string
        try {
          setupUrl = await waitForAccountSetupUrl({
            afterBytes: setupLogCursor,
            emailHint: studentEmail,
            timeoutMs: 60_000,
          })
        } catch (firstError) {
          const login = await apiLogin(request, QA.schoolEmail, PASSWORD)
          const list = await request.get(`${API}/school-students`, {
            headers: { Authorization: `Bearer ${login.token}` },
          })
          const row = ((await list.json()).data || []).find(
            (s: any) => String(s.email || '').toLowerCase() === studentEmail.toLowerCase()
          )
          if (!row?.id) throw new Error(`Student not found for setup resend: ${studentEmail}; first wait: ${String(firstError)}`)
          const resendCursor = markLaravelLogCursor()
          const resend = await request.post(`${API}/school-students/${row.id}/resend-setup-link`, {
            headers: { Authorization: `Bearer ${login.token}` },
          })
          if (!resend.ok()) throw new Error(`Setup-link resend failed ${resend.status()}: ${await resend.text()}`)
          setupUrl = await waitForAccountSetupUrl({
            afterBytes: resendCursor,
            emailHint: studentEmail,
            timeoutMs: 60_000,
          })
        }
        const setup = await completeStudentAccountSetup(browser, setupUrl, STUDENT_PASSWORD, studentEmail)
        await studentCtx?.close().catch(() => {})
        studentCtx = setup.context
        studentPage = setup.page
        await shotJourney(studentPage, 'r09-dashboard')
        if (/organization-verification/i.test(studentPage.url())) {
          throw new Error(`Student landed on organization-verification: ${studentPage.url()}`)
        }
        record('9 student setup', 'PASS', `Account setup complete; url=${studentPage.url()}; no organization-verification`)
        await hold(studentPage)
      } catch (e) {
        record('9 student setup', 'FAIL', String(e))
      }
    }

    // ── 10 Apply ───────────────────────────────────────────────────
    logStep(10, 'Student applies via /find-internships with hours badge')
    {
      try {
        await ensureLoggedIn(studentPage, studentEmail, STUDENT_PASSWORD)
        await studentPage.goto('/find-internships')
        await expect(studentPage.getByRole('heading', { name: /Find Internships/i }).first()).toBeVisible({
          timeout: 20_000,
        })
        const search = studentPage.getByPlaceholder(/search/i).first()
        if (await search.isVisible().catch(() => false)) {
          await search.fill(QA.jobTitle)
          await studentPage.waitForTimeout(1000)
        }
        await shotJourney(studentPage, 'r10-find')
        const blocked = studentPage.getByRole('button', { name: /Not yet available/i }).first()
        if (await blocked.isVisible().catch(() => false)) {
          throw new Error('Job visible but Not yet available — active school↔company agreement missing (step 7)')
        }
        const card = studentPage.locator('article, .border').filter({ hasText: QA.jobTitle }).first()
        const viewApply = (await card.count())
          ? card.getByRole('button', { name: /View & Apply/i }).first()
          : studentPage.getByRole('button', { name: /View & Apply/i }).first()
        await expect(viewApply).toBeVisible({ timeout: 20_000 })
        await viewApply.click()
        // View & Apply routes to intern opportunities with focus; open apply dialog there.
        await studentPage.waitForURL(/intern/, { timeout: 20_000 })
        await studentPage.waitForTimeout(1200)
        // Open apply if focus didn't auto-open
        const applyOpen = studentPage.getByRole('button', { name: /Apply|View & Apply|Request/i }).first()
        if (await applyOpen.isVisible().catch(() => false)) {
          await applyOpen.click().catch(() => {})
        }
        await expect(studentPage.getByText(/Endorsement|Submit Application/i).first()).toBeVisible({
          timeout: 20_000,
        })
        await shotJourney(studentPage, 'r10-apply-dialog')
        const dialogText = await studentPage.locator('body').innerText()
        const hoursBadge = /80|40|hours differ|school.*h|host.*h|mismatch|agreement/i.test(dialogText)
        // Endorsement is required — Submit stays disabled until a file is chosen (input is sr-only).
        const endorsementInput = studentPage.locator('input[type="file"][accept*="pdf"]').last()
        await endorsementInput.setInputFiles(FIXTURE)
        await expect(studentPage.getByText(/Uploaded|qa-doc|endorsement selected/i).first()).toBeVisible({
          timeout: 10_000,
        })
        const submitApp = studentPage.getByRole('button', { name: /Submit Application/i })
        await expect(submitApp).toBeEnabled({ timeout: 10_000 })
        const applyResponse = studentPage.waitForResponse(
          (r) => /\/api\/applications\/?$/.test(new URL(r.url()).pathname) && r.request().method() === 'POST',
          { timeout: 30_000 }
        )
        await submitApp.click()
        const posted = await applyResponse.catch(() => null)
        if (posted && !posted.ok()) {
          throw new Error(`Apply API ${posted.status()}: ${(await posted.text()).slice(0, 180)}`)
        }
        await expect(studentPage.getByText(/Application submitted|submitted successfully|applied/i).first())
          .toBeVisible({ timeout: 15_000 })
          .catch(() => null)
        await shotJourney(studentPage, 'r10-applied')
        const st = await apiLogin(request, studentEmail, STUDENT_PASSWORD)
        let app: { id?: number | string } | undefined
        for (let i = 0; i < 10; i++) {
          const apps = await request.get(`${API}/applications`, {
            headers: { Authorization: `Bearer ${st.token}` },
          })
          const rows = ((await apps.json()).data || []) as Array<{ id?: number | string; internshipTitle?: string }>
          app =
            rows.find((row) => String(row.internshipTitle || '').includes('QA-Job')) ||
            rows[0]
          if (app?.id) break
          await studentPage.waitForTimeout(500)
        }
        applicationId = app?.id ? Number(app.id) : undefined
        if (!applicationId) {
          const bodySnippet = (await studentPage.locator('body').innerText()).slice(0, 240)
          throw new Error(`No application after UI submit; body=${bodySnippet}`)
        }
        // Hours mismatch evidence may appear on intern dashboard after apply
        await studentPage.goto('/intern/tracker')
        await waitForAppShell(studentPage)
        const postApply = await studentPage.locator('body').innerText()
        const hoursEvidence =
          hoursBadge || /Hours differ|School \d+h|Host \d+h|80|40|hours agreement/i.test(postApply)
        record(
          '10 apply',
          hoursEvidence ? 'PASS' : 'FAIL',
          hoursEvidence
            ? `Applied appId=${applicationId}; hours evidence present`
            : `Applied appId=${applicationId} but hours-differ badge/text not found`
        )
      } catch (e) {
        record('10 apply', 'FAIL', String(e).slice(0, 280))
      }
    }

    // Continuity only — step 10 stays FAIL if UI apply did not create the row.
    if (!applicationId && studentEmail) {
      try {
        const st = await apiLogin(request, studentEmail, STUDENT_PASSWORD)
        const jobs = await request.get(`${API}/internships?search=${encodeURIComponent(QA.jobTitle)}`, {
          headers: { Authorization: `Bearer ${st.token}` },
        })
        const job =
          ((await jobs.json()).data || []).find((j: any) => String(j.title || '').includes('QA-Job')) ||
          ((await jobs.json()).data || [])[0]
        if (job?.id) {
          const form = new FormData()
          // Prefer re-check list first
          const apps = await request.get(`${API}/applications`, {
            headers: { Authorization: `Bearer ${st.token}` },
          })
          const existing = ((await apps.json()).data || [])[0]
          if (existing?.id) {
            applicationId = Number(existing.id)
            logResult('API ONLY, UI NOT TESTED', `Recovered applicationId=${applicationId} after step 10 FAIL`)
          }
        }
      } catch {
        /* ignore */
      }
    }

    // ── 11 Hours agreement (product state + UI agreement) ─────────
    logStep(11, 'School + company agree hours in UI')
    {
      let productEvidence = 'Product state not loaded'
      try {
        if (!applicationId) throw new Error('no applicationId')
        const schoolLogin = await apiLogin(request, QA.schoolEmail, PASSWORD)
        const agreementResponse = await request.get(`${API}/applications/${applicationId}/hours-agreement`, {
          headers: { Authorization: `Bearer ${schoolLogin.token}`, Accept: 'application/json' },
        })
        if (!agreementResponse.ok()) {
          throw new Error(`Hours agreement product check failed ${agreementResponse.status()}: ${await agreementResponse.text()}`)
        }
        const productState = (await agreementResponse.json()).data
        const rawSchoolHours =
          productState?.schoolRequiredHours ?? productState?.agreement?.schoolRequiredHours ?? null
        const schoolRequiredHours =
          rawSchoolHours !== null && Number(rawSchoolHours) >= 1 ? Number(rawSchoolHours) : null

        // School endorsements list uses View (opens detail with HoursAgreementPanel), not Review.
        await ensureLoggedIn(schoolPage, QA.schoolEmail, PASSWORD)
        await schoolPage.goto('/school/endorsements')
        await expect(schoolPage.getByRole('heading', { name: /Endorsements/i }).first()).toBeVisible({
          timeout: 20_000,
        })
        const schoolCard = qaStudentCard(schoolPage)
        await expect(schoolCard).toBeVisible({ timeout: 20_000 })
        await schoolCard.getByRole('button', { name: /^View$/i }).click()
        await expect(schoolPage.getByText(/Hours agreement/i).first()).toBeVisible({ timeout: 15_000 })
        // Agreement may still be loading; wait for Status or explicit empty state.
        await expect(
          schoolPage.getByText(/Status:|No hours agreement for this application/i).first()
        ).toBeVisible({ timeout: 20_000 })
        if (await schoolPage.getByText(/No hours agreement for this application/i).isVisible().catch(() => false)) {
          throw new Error(
            `PRODUCT: hours agreement missing for app ${applicationId} after apply (expected school vs host mismatch to create it)`
          )
        }
        await shotJourney(schoolPage, 'r11-school')
        const hoursPanel = schoolPage
          .locator('div.rounded-2xl, div.space-y-3')
          .filter({ has: schoolPage.getByRole('button', { name: /Propose custom/i }) })
          .first()
        await expect(hoursPanel).toBeVisible({ timeout: 15_000 })
        const useSchoolHours = hoursPanel.getByRole('button', { name: /Use school hours/i }).first()
        const schoolBtnDisabled = await useSchoolHours.isDisabled().catch(() => true)
        if (schoolRequiredHours === null) {
          if (!schoolBtnDisabled) {
            throw new Error('PRODUCT: schoolRequiredHours null/0 but Use school hours is enabled')
          }
          const warnVisible = await hoursPanel
            .getByText(/School course hours are not set|Use school hours does nothing/i)
            .first()
            .isVisible()
            .catch(() => false)
          productEvidence = warnVisible
            ? 'PRODUCT: schoolRequiredHours null/0 — Use school hours disabled + warning shown'
            : 'PRODUCT: schoolRequiredHours null/0 — Use school hours disabled (warning text missing)'
        } else if (schoolBtnDisabled) {
          productEvidence = `PRODUCT BUG: API schoolRequiredHours=${schoolRequiredHours} but Use school hours still disabled in UI`
        } else {
          productEvidence = `schoolRequiredHours=${schoolRequiredHours}; Use school hours enabled`
        }

        // Propose custom 40h from school, then company confirms — this is the PASS criterion.
        const customInput = hoursPanel.locator('input[type="number"]').first()
        await expect(customInput).toBeVisible({ timeout: 10_000 })
        await customInput.click()
        await customInput.fill('')
        await customInput.pressSequentially(String(QA.requiredHours), { delay: 40 })
        await expect(customInput).toHaveValue(String(QA.requiredHours))
        const proposeWait = schoolPage.waitForResponse(
          (r) => /hours-agreement\/propose/.test(r.url()) && r.request().method() === 'POST',
          { timeout: 20_000 }
        )
        await clickConfirming(schoolPage, hoursPanel.getByRole('button', { name: /Propose custom/i }).first())
        const proposeRes = await proposeWait
        if (!proposeRes.ok()) {
          throw new Error(`Propose custom API ${proposeRes.status()}: ${(await proposeRes.text()).slice(0, 180)}`)
        }
        await expect(schoolPage.getByText(/Proposed:\s*40|Hours proposal sent/i).first()).toBeVisible({
          timeout: 15_000,
        })
        await shotJourney(schoolPage, 'r11-school-proposed')
        await schoolPage.keyboard.press('Escape').catch(() => {})

        // Company confirms hours from applicant "View Profile" (available pre-endorse).
        await ensureLoggedIn(companyPage, QA.companyEmail, PASSWORD)
        await companyPage.goto('/dashboard')
        await companyPage.getByRole('button', { name: /Review applicants/i }).first().click().catch(async () => {
          await companyPage.getByRole('navigation').getByText(/Applicants/i).first().click()
        })
        await companyPage.waitForTimeout(1000)
        const companyCard = qaStudentCard(companyPage)
        await expect(companyCard).toBeVisible({ timeout: 20_000 })
        await companyCard.getByRole('button', { name: /View Profile/i }).click()
        await companyPage.waitForTimeout(800)
        await expect(companyPage.getByText(/Hours agreement/i).first()).toBeVisible({ timeout: 15_000 })
        await shotJourney(companyPage, 'r11-company')
        const confirmProposal = companyPage.getByRole('button', { name: /Confirm proposal/i }).first()
        await clickConfirming(companyPage, confirmProposal)
        await companyPage.waitForTimeout(1200)
        await shotJourney(companyPage, 'r11-agreed')
        const agreedBadge = companyPage.getByText(/Hours agreed/i).first()
        await expect(agreedBadge).toBeVisible({ timeout: 15_000 })
        record('11 hours agree', 'PASS', `${productEvidence}; custom 40h proposed and Hours agreed appeared in company UI`)
      } catch (e) {
        record('11 hours agree', 'FAIL', `${productEvidence}; ${String(e).slice(0, 220)}`)
      }
    }

    // If hours UI failed but we need continuity for later UI steps, soft-unblock via API
    // WITHOUT marking those later steps as depending on a PASS for 11 — 11 stays FAIL.
    if (applicationId && results.some((r) => r.step === '11 hours agree' && r.result === 'FAIL')) {
      try {
        const s = await apiLogin(request, QA.schoolEmail, PASSWORD)
        const c = await apiLogin(request, QA.companyEmail, PASSWORD)
        await request.post(`${API}/applications/${applicationId}/hours-agreement/propose`, {
          headers: { Authorization: `Bearer ${s.token}` },
          data: { source: 'school', hours: QA.requiredHours },
        })
        await request.post(`${API}/applications/${applicationId}/hours-agreement/confirm`, {
          headers: { Authorization: `Bearer ${c.token}` },
        })
        logResult('API ONLY, UI NOT TESTED', 'Unblocked hours via API after UI FAIL (step 11 remains FAIL)')
      } catch {
        /* ignore */
      }
    }

    // ── 12 Endorse ─────────────────────────────────────────────────
    logStep(12, 'School endorses in UI')
    {
      try {
        await ensureLoggedIn(schoolPage, QA.schoolEmail, PASSWORD)
        await schoolPage.goto('/school/endorsements')
        const endorseCard = qaStudentCard(schoolPage)
        await expect(endorseCard).toBeVisible({ timeout: 20_000 })
        // Endorse is available on the list card (and inside View detail).
        const endorse = endorseCard.getByRole('button', { name: /^Endorse$/i }).first()
        await expect(endorse).toBeVisible({ timeout: 15_000 })
        await endorse.click()
        // AlertDialog confirm also labeled Endorse
        const conf = schoolPage.getByRole('alertdialog').getByRole('button', { name: /^Endorse$/i })
        if (await conf.isVisible().catch(() => false)) await conf.click()
        else {
          const fallback = schoolPage.getByRole('button', { name: /^Endorse$/i }).last()
          if (await fallback.isVisible().catch(() => false)) await fallback.click()
        }
        await schoolPage.waitForTimeout(1000)
        await shotJourney(schoolPage, 'r12-endorsed')
        record('12 endorse', 'PASS', 'Endorse clicked in school UI')
      } catch (e) {
        record('12 endorse', 'FAIL', String(e).slice(0, 200))
        if (applicationId) {
          const s = await apiLogin(request, QA.schoolEmail, PASSWORD)
          await request.post(`${API}/applications/${applicationId}/tracker/transition`, {
            headers: { Authorization: `Bearer ${s.token}` },
            data: { to_stage: 'endorsed' },
          }).catch(() =>
            request.patch(`${API}/applications/${applicationId}/status`, {
              headers: { Authorization: `Bearer ${s.token}` },
              data: { status: 'endorsed' },
            })
          )
        }
      }
    }

    // ── 13 Interview ───────────────────────────────────────────────
    logStep(13, 'Company schedules interview; student confirms; no propose for student')
    {
      try {
        await ensureLoggedIn(companyPage, QA.companyEmail, PASSWORD)
        await companyPage.goto('/dashboard')
        await companyPage.getByRole('button', { name: /Review applicants/i }).first().click().catch(async () => {
          await companyPage.getByRole('navigation').getByText(/Applicants/i).first().click()
        })
        await companyPage.waitForTimeout(1000)
        const interviewCard = qaStudentCard(companyPage)
        await expect(interviewCard).toBeVisible({ timeout: 20_000 })
        await interviewCard.getByRole('button', { name: /View Profile/i }).click()
        await companyPage.waitForTimeout(800)
        const schedule = companyPage.getByRole('button', { name: /Schedule interview/i }).first()
        await expect(schedule).toBeVisible({ timeout: 15_000 })
        const dateTime = companyPage.locator('input[type="datetime-local"]').first()
        await expect(dateTime).toBeVisible()
        await dateTime.fill('2026-10-25T10:00')
        await schedule.click()
        await companyPage.waitForTimeout(1200)
        await shotJourney(companyPage, 'r13-scheduled')
        await ensureLoggedIn(studentPage, studentEmail, STUDENT_PASSWORD)
        await studentPage.goto('/intern/tracker')
        await shotJourney(studentPage, 'r13-student')
        const proposeCount = await studentPage.getByRole('button', { name: /Propose interview|Schedule interview/i }).count()
        if (proposeCount > 0) {
          record('13 interview', 'FAIL', 'Student has propose/schedule interview button')
        } else {
          const confirm = studentPage.getByRole('button', { name: /Confirm interview|Confirm/i }).first()
          if (await confirm.isVisible().catch(() => false)) await confirm.click()
          record('13 interview', 'PASS', 'Host scheduled; student has no propose button')
        }
      } catch (e) {
        record('13 interview', 'FAIL', String(e).slice(0, 200))
        if (applicationId) {
          const c = await apiLogin(request, QA.companyEmail, PASSWORD)
          const iv = await request.post(`${API}/interviews`, {
            headers: { Authorization: `Bearer ${c.token}` },
            data: {
              application_id: applicationId,
              scheduled_at: new Date(Date.now() + 86400000).toISOString(),
              mode: 'online',
            },
          })
          if (iv.ok()) {
            const id = (await iv.json()).data.id
            const st = await apiLogin(request, studentEmail, STUDENT_PASSWORD)
            await request.patch(`${API}/interviews/${id}/confirm`, {
              headers: { Authorization: `Bearer ${st.token}` },
            })
          }
        }
      }
    }

    // ── 14 Assess + accept ─────────────────────────────────────────
    logStep(14, 'Company interviewed → assess → accept (UI preferred)')
    {
      try {
        await ensureLoggedIn(companyPage, QA.companyEmail, PASSWORD)
        await companyPage.goto('/dashboard')
        await companyPage.getByRole('button', { name: /Review applicants/i }).first().click().catch(() => {})
        await companyPage.waitForTimeout(800)
        const assessCard = qaStudentCard(companyPage)
        await expect(assessCard).toBeVisible({ timeout: 20_000 })
        await assessCard.getByRole('button', { name: /View Profile/i }).click()
        await companyPage.waitForTimeout(800)
        // Phase G: interview_scheduled → Mark interviewed → Submit assessment → Accept (assessed only).
        const interviewed = companyPage.getByRole('button', { name: /Mark interviewed/i }).first()
        await expect(interviewed).toBeVisible({ timeout: 15_000 })
        await interviewed.click()
        await companyPage.waitForTimeout(800)
        await expect(companyPage.getByText(/Assessment \(scale 1–5\)/i).first()).toBeVisible({ timeout: 10_000 })
        await companyPage.getByLabel(/^Technical$/i).fill('4')
        await companyPage.getByLabel(/^Communication$/i).fill('4')
        await companyPage.getByLabel(/^Attitude$/i).fill('4')
        await companyPage.getByRole('button', { name: /Submit assessment/i }).click()
        await companyPage.waitForTimeout(1200)
        const accept = companyPage.getByRole('button', { name: /^Accept$/i }).first()
        await expect(accept).toBeVisible({ timeout: 15_000 })
        await accept.click()
        const conf = companyPage.getByRole('dialog').getByRole('button', { name: /^Confirm$/i })
        await expect(conf).toBeVisible({ timeout: 10_000 })
        await conf.click()
        await companyPage.waitForTimeout(1000)
        await shotJourney(companyPage, 'r14-accepted')
        record('14 assess+accept', 'PASS', 'Mark interviewed → Submit assessment → Accept in company UI')
      } catch (e) {
        record('14 assess+accept', 'FAIL', String(e).slice(0, 200))
        if (applicationId) {
          const c = await apiLogin(request, QA.companyEmail, PASSWORD)
          await request.post(`${API}/applications/${applicationId}/tracker/transition`, {
            headers: { Authorization: `Bearer ${c.token}` },
            data: { to_stage: 'interviewed' },
          })
          await request.post(`${API}/applications/${applicationId}/tracker/assessment`, {
            headers: { Authorization: `Bearer ${c.token}` },
            data: { technical: 4, communication: 4, attitude: 5, comments: 'QA' },
          })
          await request.post(`${API}/applications/${applicationId}/tracker/transition`, {
            headers: { Authorization: `Bearer ${c.token}` },
            data: { to_stage: 'accepted' },
          })
        }
      }
    }

    // ── 15 Schedule change ─────────────────────────────────────────
    logStep(15, 'Student calendar + schedule change; company approves')
    {
      try {
        await ensureLoggedIn(studentPage, studentEmail, STUDENT_PASSWORD)
        const appsWait = studentPage
          .waitForResponse(
            (r) => /\/api\/applications(\?|$)/.test(r.url()) && r.ok(),
            { timeout: 30_000 }
          )
          .catch(() => null)
        await studentPage.goto('/intern/tracker')
        await appsWait
        await waitForAppShell(studentPage)
        await expect(
          studentPage.getByText(new RegExp(QA.jobTitle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i')).first()
        ).toBeVisible({ timeout: 30_000 })
        await expect(studentPage.getByText(/Placement schedule/i).first()).toBeVisible({ timeout: 20_000 })
        await shotJourney(studentPage, 'r15-calendar')
        const body = await studentPage.locator('body').innerText()
        const hasCal = /Placement schedule/i.test(body)
        const reqBtn = studentPage.getByRole('button', { name: /Request different schedule/i }).first()
        await expect(reqBtn).toBeVisible({ timeout: 15_000 })
        await reqBtn.click()
        await studentPage.getByPlaceholder(/Why do you need a different schedule/i).fill('QA schedule change')
        await studentPage.getByRole('button', { name: /Submit request/i }).click()
        await studentPage.waitForTimeout(1000)
        await ensureLoggedIn(companyPage, QA.companyEmail, PASSWORD)
        await companyPage.goto('/dashboard')
        await companyPage.getByRole('button', { name: /Review applicants/i }).first().click().catch(async () => {
          await companyPage.getByRole('navigation').getByText(/Applicants/i).first().click()
        })
        await qaStudentCard(companyPage).getByRole('button', { name: /View Profile/i }).click()
        await companyPage.waitForTimeout(600)
        await expect(companyPage.getByText(/Pending request|Placement schedule/i).first()).toBeVisible({
          timeout: 15_000,
        })
        const approve = companyPage.getByRole('button', { name: /^Approve$/i }).first()
        await expect(approve).toBeVisible({ timeout: 15_000 })
        await approve.click()
        const conf = companyPage.getByRole('dialog').getByRole('button', { name: /^Confirm$/i })
        await expect(conf).toBeVisible({ timeout: 10_000 })
        await conf.click()
        record('15 schedule', 'PASS', `Schedule request + company Approve/Confirm in UI; calendar=${hasCal}`)
      } catch (e) {
        record('15 schedule', 'FAIL', String(e).slice(0, 220))
        if (applicationId) {
          try {
            const st = await apiLogin(request, studentEmail, STUDENT_PASSWORD)
            const req = await request.post(`${API}/applications/${applicationId}/schedule/request`, {
              headers: { Authorization: `Bearer ${st.token}` },
              data: {
                slots: [
                  { day: 'tue', start: '08:00', end: '12:00' },
                  { day: 'thu', start: '08:00', end: '12:00' },
                ],
                reason: 'QA',
              },
            })
            if (req.ok()) {
              const id = (await req.json()).data.request.id
              const c = await apiLogin(request, QA.companyEmail, PASSWORD)
              await request.post(`${API}/applications/${applicationId}/schedule/requests/${id}/decide`, {
                headers: { Authorization: `Bearer ${c.token}` },
                data: { decision: 'approved' },
              })
              logResult('API ONLY, UI NOT TESTED', 'Unblocked schedule via API after UI FAIL')
            }
          } catch {
            /* ignore */
          }
        }
      }
    }

    // ── 16 DTR (student submit path — code supports both) ──────────
    logStep(16, 'DTR: student submit → company reject → resubmit → company approve → school approve')
    {
      try {
        if (!applicationId) throw new Error('no applicationId')
        // Path used: STUDENT uploads (Intern / ojt-hours), company then school approve.
        await ensureLoggedIn(studentPage, studentEmail, STUDENT_PASSWORD)
        const placementAppsWait = studentPage
          .waitForResponse(
            (r) => /\/api\/(applications|ojt|dtr)/i.test(r.url()) && r.ok(),
            { timeout: 30_000 }
          )
          .catch(() => null)
        await studentPage.goto('/intern/placement')
        await placementAppsWait
        await waitForAppShell(studentPage)
        await expect(studentPage.getByText(/Daily Time Record \(DTR\)/i).first()).toBeVisible({ timeout: 25_000 })
        await expect(studentPage.getByText(/Accepted placement/i).first()).toBeVisible({ timeout: 20_000 })
        const placementSelect = studentPage
          .getByText(/Accepted placement/i)
          .locator('..')
          .locator('select')
          .first()
        await expect(placementSelect).toBeVisible({ timeout: 15_000 })
        const opts = placementSelect.locator('option')
        if ((await opts.count()) > 1) await placementSelect.selectOption({ index: 1 })
        await studentPage.getByText(/^Hours$/).locator('..').locator('input').fill(String(QA.requiredHours)).catch(async () => {
          await studentPage.locator('input[type="number"]').first().fill(String(QA.requiredHours))
        })
        const periodStart = studentPage.locator('input[type="date"]').first()
        const periodEnd = studentPage.locator('input[type="date"]').nth(1)
        await periodStart.fill('2026-10-06')
        await periodEnd.fill('2026-10-10')
        await studentPage.locator('input[type="file"]').first().setInputFiles(FIXTURE)
        const uploadBtn = studentPage.getByRole('button', { name: /Upload DTR/i }).first()
        await expect(uploadBtn).toBeEnabled({ timeout: 10_000 })
        await uploadBtn.click()
        const usedUiUpload = true
        await studentPage.waitForTimeout(1500)
        await shotJourney(studentPage, 'r16-student-submit')

        // API tokens are used only to read the same calendar summary for each role.
        const st = await apiLogin(request, studentEmail, STUDENT_PASSWORD)
        const c = await apiLogin(request, QA.companyEmail, PASSWORD)
        await ensureLoggedIn(companyPage, QA.companyEmail, PASSWORD)
        await companyPage.goto('/dashboard/dtr')
        await companyPage.reload()
        await shotJourney(companyPage, 'r16-company-pending')
        const rejectBtn = companyPage.getByRole('button', { name: /reject|revision/i }).first()
        await expect(rejectBtn).toBeVisible({ timeout: 15_000 })
        await rejectBtn.click()
        await companyPage.getByPlaceholder(/Explain what needs to be corrected/i).fill('QA incomplete')
        const revisionCheckbox = companyPage.locator('input[type="checkbox"]').last()
        if (!(await revisionCheckbox.isChecked())) await revisionCheckbox.check()
        await companyPage.getByRole('button', { name: /Request revision/i }).click()
        await expect(companyPage.getByText(/Revision Required|QA incomplete/i).first()).toBeVisible({ timeout: 15_000 })

        // Resubmit (student)
        await studentPage.goto('/intern/placement')
        await studentPage.reload()
        await shotJourney(studentPage, 'r16-revision')
        const resubmitBtn = studentPage.getByRole('button', { name: /Resubmit/i }).first()
        await expect(resubmitBtn).toBeVisible({ timeout: 15_000 })
        await resubmitBtn.click()
        await studentPage.locator('input[type="file"]').first().setInputFiles(FIXTURE)
        const submitResubmission = studentPage.getByRole('button', { name: /Resubmit DTR/i }).first()
        await expect(submitResubmission).toBeEnabled()
        await submitResubmission.click()
        await studentPage.waitForTimeout(1200)

        await companyPage.goto('/dashboard/dtr')
        await companyPage.reload()
        const approveHost = companyPage.getByRole('button', { name: /^Approve$/i }).first()
        await expect(approveHost).toBeVisible({ timeout: 15_000 })
        await approveHost.click()

        await ensureLoggedIn(schoolPage, QA.schoolEmail, PASSWORD)
        await schoolPage.goto('/school/dtr')
        await schoolPage.reload()
        await shotJourney(schoolPage, 'r16-school')
        const approveSchool = schoolPage.getByRole('button', { name: /^Approve$/i }).first()
        await expect(approveSchool).toBeVisible({ timeout: 15_000 })
        await approveSchool.click()
        await schoolPage.waitForTimeout(1200)

        // Reload hours on three roles
        await studentPage.goto('/intern/placement')
        await studentPage.reload()
        await companyPage.goto('/dashboard/dtr')
        await companyPage.reload()
        await schoolPage.goto('/school/dtr')
        await schoolPage.reload()
        const hStudent = await readCalendarHours(request, st.token, applicationId)
        const hCompany = await readCalendarHours(request, c.token, applicationId)
        const sch = await apiLogin(request, QA.schoolEmail, PASSWORD)
        const hSchool = await readCalendarHours(request, sch.token, applicationId)
        await shotJourney(studentPage, 'r16-hours-final')

        const ok =
          hStudent &&
          hCompany &&
          hSchool &&
          hStudent.approved === QA.requiredHours &&
          hCompany.approved === QA.requiredHours &&
          hSchool.approved === QA.requiredHours &&
          hStudent.approved === hCompany.approved

        if (usedUiUpload && ok) {
          record(
            '16 DTR',
            'PASS',
            `Student-upload path; approvedHours=${hStudent?.approved} on all views; no double count`
          )
        } else {
          record(
            '16 DTR',
            'FAIL',
            `Student-upload path=${usedUiUpload}; hours mismatch student=${JSON.stringify(hStudent)} company=${JSON.stringify(hCompany)} school=${JSON.stringify(hSchool)}`
          )
        }
      } catch (e) {
        record('16 DTR', 'FAIL', `Student-upload path: ${String(e).slice(0, 220)}`)
      }
    }

    // ── 17 Certificate ─────────────────────────────────────────────
    logStep(17, 'School issues certificate after approved DTR hours; student downloads')
    {
      try {
        if (!applicationId) throw new Error('no applicationId')
        await ensureLoggedIn(schoolPage, QA.schoolEmail, PASSWORD)
        await schoolPage.goto('/school/placements')
        await expect(schoolPage.getByRole('heading', { name: /Placements/i }).first()).toBeVisible({
          timeout: 20_000,
        })
        // Placements table uses <td>, not a heading for the intern name.
        const certRow = schoolPage.locator('tr').filter({ hasText: new RegExp(QA.studentLast, 'i') }).first()
        await expect(certRow).toBeVisible({ timeout: 20_000 })
        await shotJourney(schoolPage, 'r17-placements')
        const issue = certRow.getByRole('button', { name: /^Issue$/i }).first()
        await expect(issue).toBeVisible({ timeout: 15_000 })
        await issue.click()
        await schoolPage.waitForTimeout(1500)
        await shotJourney(schoolPage, 'r17-issued')
        await ensureLoggedIn(studentPage, studentEmail, STUDENT_PASSWORD)
        await studentPage.goto('/intern/placement')
        await waitForAppShell(studentPage)
        await expect(studentPage.getByRole('button', { name: /View Certificate/i }).first()).toBeVisible({
          timeout: 20_000,
        })
        await shotJourney(studentPage, 'r17-student')
        const viewBtn = studentPage.getByRole('button', { name: /View Certificate/i }).first()
        const printBtn = studentPage.getByRole('button', { name: /Print \/ Save as PDF/i }).first()
        await expect(printBtn).toBeVisible({ timeout: 10_000 })
        await viewBtn.click()
        await studentPage.waitForTimeout(800)
        // Student download path is View + Print/Save as PDF (HTML certificate).
        record('17 certificate', 'PASS', 'Issued via school Placements Issue; student View Certificate + Print/Save as PDF visible')
      } catch (e) {
        record('17 certificate', 'FAIL', String(e).slice(0, 220))
      }
    }

    // ── 18 Teacher ─────────────────────────────────────────────────
    logStep(18, 'School creates teacher; teacher sees school name/courses read-only')
    {
      try {
        await ensureLoggedIn(schoolPage, QA.schoolEmail, PASSWORD)
        // School staff accounts are created on Tenant RBAC (/access/roles), not OrganizationAccessManager.
        await schoolPage.goto('/access/roles')
        await expect(schoolPage.getByText(/Members|Roles|Create role/i).first()).toBeVisible({
          timeout: 20_000,
        })
        await shotJourney(schoolPage, 'r18-roles')
        await schoolPage.getByPlaceholder('Full name').fill(QA.teacherName)
        await schoolPage.getByPlaceholder(/^Email$/i).fill(QA.teacherEmail)
        const roleSelect = schoolPage.locator('select').filter({ hasText: /Select role|Teacher|Intern|Coordinator/i }).first()
        await expect(roleSelect).toBeVisible({ timeout: 10_000 })
        const options = await roleSelect.locator('option').allTextContents()
        const teacherOpt = options.find((o) => /teacher/i.test(o))
        if (!teacherOpt) throw new Error(`Teacher role unavailable; options=${JSON.stringify(options)}`)
        await roleSelect.selectOption({ label: teacherOpt.trim() })

        const createBtn = schoolPage.getByRole('button', { name: /Add user/i })
        await expect(createBtn).toBeEnabled({ timeout: 10_000 })
        await createBtn.click()
        // Toast: "User created." / "Temporary password: …"
        const toast = schoolPage.getByText(/Temporary password:/i).first()
        await expect(toast).toBeVisible({ timeout: 15_000 })
        const toastText = await toast.innerText()
        const tempMatch = toastText.match(/Temporary password:\s*([^\s]+)/i)
        if (!tempMatch?.[1]) throw new Error(`Temporary password missing from toast: ${toastText}`)
        const tempPass = tempMatch[1]
        await shotJourney(schoolPage, 'r18-teacher-created')

        if (teacherCtx) await teacherCtx.close().catch(() => {})
        const r = await newRoleContext(browser)
        teacherCtx = r.context
        const teacherPage = r.page
        await loginAsFresh(teacherPage, QA.teacherEmail, tempPass)
        // Forced password change if required
        const onPwChange =
          /change-password|forced/i.test(teacherPage.url()) ||
          (await teacherPage.getByText(/change your password|new password/i).first().isVisible().catch(() => false))
        if (onPwChange) {
          const pwInputs = teacherPage.locator('input[type="password"]')
          const count = await pwInputs.count()
          if (count >= 3) {
            await pwInputs.nth(0).fill(tempPass)
            await pwInputs.nth(1).fill(TEACHER_PASSWORD)
            await pwInputs.nth(2).fill(TEACHER_PASSWORD)
          } else {
            await pwInputs.nth(0).fill(TEACHER_PASSWORD)
            await pwInputs.nth(1).fill(TEACHER_PASSWORD)
          }
          const savePw = teacherPage.getByRole('button', { name: /Save|Update password|Change password/i }).first()
          await expect(savePw).toBeEnabled({ timeout: 10_000 })
          await savePw.click()
          await teacherPage
            .waitForURL((url) => !/change-password/i.test(url.pathname), { timeout: 20_000 })
            .catch(async () => {
              // If still on change-password, wait for success toast / token refresh then continue.
              await teacherPage.waitForTimeout(1500)
            })
        }
        // Close+reopen so settings loads with post-password-change token (avoids blank shell).
        await teacherCtx?.close().catch(() => {})
        const r2 = await newRoleContext(browser)
        teacherCtx = r2.context
        const teacherPage2 = r2.page
        await loginAsFresh(teacherPage2, QA.teacherEmail, onPwChange ? TEACHER_PASSWORD : tempPass)
        await teacherPage2.goto('/settings')
        await expect(
          teacherPage2.getByRole('heading', { name: /Profile Settings|Settings/i }).first()
        ).toBeVisible({ timeout: 30_000 })
        await shotJourney(teacherPage2, 'r18-teacher-settings')
        const tBody = await teacherPage2.locator('body').innerText()
        const seesSchool =
          tBody.includes(QA.schoolName) ||
          (await teacherPage2.getByLabel(/School Name/i).inputValue().catch(() => '')).includes('QA-School') ||
          /QA-School/i.test(tBody)
        const seesCourses =
          tBody.includes(QA.courseName) ||
          (await teacherPage2.getByText(/School Courses|QA BSIT/i).first().isVisible().catch(() => false))
        const hasOrgSettings = /subscription|billing|manage members|organization settings/i.test(tBody)
        const hasCourseAdmin = await teacherPage2
          .getByRole('button', { name: /Add course|Save course|Delete course|Manage course/i })
          .count()
        if (seesSchool && seesCourses && !hasOrgSettings && hasCourseAdmin === 0) {
          record('18 teacher', 'PASS', 'Teacher settings shows school/courses context without org admin settings')
        } else {
          record(
            '18 teacher',
            'FAIL',
            `seesSchool=${seesSchool} seesCourses=${seesCourses} hasOrgSettings=${hasOrgSettings} courseAdminControls=${hasCourseAdmin}; body snippet=${tBody.slice(0, 200)}`
          )
        }
      } catch (e) {
        record('18 teacher', 'FAIL', String(e).slice(0, 220))
      }
    }

    // ── 19 Subscription ────────────────────────────────────────────
    logStep(19, 'Expire QA-Company; banner; restore Active')
    {
      let orgId: number | undefined
      try {
        const c = await apiLogin(request, QA.companyEmail, PASSWORD)
        orgId = Number(c.user.activeOrganization.id)
        execSync(`php artisan subscription:demo-status ${orgId} expired --reason="E2E rerun"`, {
          cwd: path.join(process.cwd(), 'backend'),
          stdio: 'pipe',
        })
        // Always use a fresh context after artisan — ensureLoggedIn would keep stale subscriptionAccess.
        await companyCtx?.close().catch(() => {})
        const fresh = await newRoleContext(browser)
        companyCtx = fresh.context
        companyPage = fresh.page
        await loginAsFresh(companyPage, QA.companyEmail, PASSWORD)
        await companyPage.goto('/dashboard')
        await waitForAppShell(companyPage)
        let banner = companyPage.getByTestId('subscription-access-banner')
        let diagnosis = 'fresh-login'
        if (!(await banner.isVisible().catch(() => false))) {
          await refreshAuthProfile(companyPage)
          await companyPage.goto('/dashboard')
          await waitForAppShell(companyPage)
          banner = companyPage.getByTestId('subscription-access-banner')
          diagnosis = 'reload-after-me'
        }
        await shotJourney(companyPage, 'r19-expired')
        if (!(await banner.isVisible().catch(() => false))) {
          const probe = await companyPage.evaluate(async (key) => {
            const token = localStorage.getItem(key)
            if (!token) return { error: 'no token' }
            const res = await fetch('/api/auth/me', {
              headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
            })
            const body = await res.json().catch(() => null)
            return {
              status: res.status,
              subscriptionAccess: body?.data?.subscriptionAccess ?? body?.subscriptionAccess ?? null,
              orgType: body?.data?.activeOrganization?.type ?? body?.activeOrganization?.type ?? null,
            }
          }, TOKEN_KEY)
          throw new Error(
            `REAL BUG: expired banner missing after ${diagnosis}; /me probe=${JSON.stringify(probe)}`
          )
        }
        const title = banner.getByTestId('subscription-banner-title')
        await expect(title).toContainText(/expired/i)
        record('19 subscription', 'PASS', `Expired banner visible for QA company (${diagnosis})`)
      } catch (e) {
        record('19 subscription', 'FAIL', String(e).slice(0, 200))
      } finally {
        if (orgId) {
          execSync(`php artisan subscription:demo-status ${orgId} active --reason="E2E restore"`, {
            cwd: path.join(process.cwd(), 'backend'),
            stdio: 'pipe',
          })
          await companyPage.reload().catch(() => {})
        }
      }
    }

    // ── 20 Responsive ──────────────────────────────────────────────
    logStep(20, '390px + dark screenshots')
    {
      try {
        await studentPage.setViewportSize({ width: 390, height: 844 })
        await studentPage.goto('/intern')
        await studentPage.evaluate(() => document.documentElement.classList.add('dark'))
        await shotJourney(studentPage, 'r20-student-390-dark')
        await companyPage.setViewportSize({ width: 390, height: 844 })
        await companyPage.goto('/dashboard')
        await shotJourney(companyPage, 'r20-company-390')
        record('20 responsive', 'PASS', 'Screenshots captured')
      } catch (e) {
        record('20 responsive', 'FAIL', String(e).slice(0, 160))
      }
    }
  } finally {
    logStep('CLEANUP', 'Remove QA records; restore demo orgs Active')
    try {
      const admin = await apiLogin(request, DEMO.admin.email, DEMO.admin.password)
      for (const email of [QA.schoolEmail, QA.companyEmail]) {
        try {
          const login = await apiLogin(request, email, PASSWORD)
          const agr = await request.get(`${API}/agreements`, {
            headers: { Authorization: `Bearer ${login.token}` },
          })
          for (const row of (await agr.json()).data || []) {
            if (String(row.subject || '').includes('QA')) {
              await request.patch(`${API}/agreements/${row.id}/cancel`, {
                headers: { Authorization: `Bearer ${login.token}` },
                data: { reason: 'E2E cleanup' },
              })
            }
          }
          const jobs = await request.get(`${API}/internships`, {
            headers: { Authorization: `Bearer ${login.token}` },
          })
          for (const job of (await jobs.json()).data || []) {
            if (String(job.title || '').startsWith('QA-')) {
              await request.delete(`${API}/internships/${job.id}`, {
                headers: { Authorization: `Bearer ${login.token}` },
              })
            }
          }
        } catch {
          /* ignore */
        }
      }
      const orgs = await request.get(`${API}/admin/subscriptions`, {
        headers: { Authorization: `Bearer ${admin.token}` },
      })
      for (const org of (await orgs.json()).data || []) {
        if (/TechCorp|Naic|Cavite State/i.test(org.name || '')) {
          await request.post(`${API}/admin/subscriptions/${org.id}/activate`, {
            headers: { Authorization: `Bearer ${admin.token}` },
            data: { plan: 'standard', reason: 'E2E confirm demo Active' },
          })
        }
      }
      // Deactivate QA orgs in DB
      execSync(
        `php -r "require 'vendor/autoload.php'; $a=require 'bootstrap/app.php'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); DB::table('organizations')->where('name','like','QA-%')->update(['is_active'=>0]); echo 'ok';"`,
        { cwd: path.join(process.cwd(), 'backend'), stdio: 'pipe' }
      )
      record('CLEANUP', 'PASS', 'QA agreements/jobs cleaned; QA orgs deactivated; demos activated')
    } catch (e) {
      record('CLEANUP', 'FAIL', String(e).slice(0, 180))
    }

    await schoolCtx?.close()
    await companyCtx?.close()
    await adminCtx?.close()
    await studentCtx?.close()
    await teacherCtx?.close()

    fs.appendFileSync(
      RESULTS,
      `\n\n## Summary\nhardFailures=${hardFailures}\n${results.map((r) => `- ${r.step}: ${r.result}`).join('\n')}\n`
    )

    expect(hardFailures, `Journey had ${hardFailures} FAIL step(s) — see e2e/qa-screenshots/e2e-journey/RESULTS.md`).toBe(0)
  }
})

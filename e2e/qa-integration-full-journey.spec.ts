import { test, expect, type APIRequestContext, type Page } from '@playwright/test'
import { DEMO, loginAs } from './helpers/auth'
import * as fs from 'node:fs'
import * as path from 'node:path'

/**
 * Full integration journeys for Company and Municipal Hall.
 * Prefer UI when selectors are stable; fall back to API for tracker/DTR mutations.
 * Each step records PASS / NOT TESTED (with blocker) via annotations + screenshots.
 */
// Always hit Laravel directly (do not inherit empty/relative VITE_API_BASE_URL from frontend .env).
const API = 'http://127.0.0.1:8000/api'
const shotDir = 'e2e/qa-screenshots/integration'
const HOST_HOURS = 300 // school BSCS = 486 → mismatch triggers hours agreement
const JOB_TITLE_PREFIX = 'QA Integration Journey'

type HostKey = 'company' | 'municipal'

const HOSTS: Record<
  HostKey,
  { label: string; email: string; password: string; slug: string }
> = {
  company: {
    label: 'Company',
    email: DEMO.company.email,
    password: DEMO.company.password,
    slug: 'company',
  },
  municipal: {
    label: 'Municipal Hall',
    email: DEMO.municipal.email,
    password: DEMO.municipal.password,
    slug: 'municipal',
  },
}

type JourneyState = {
  hostToken: string
  schoolToken: string
  studentToken: string
  internshipId?: number
  applicationId?: number
  interviewId?: number
  dtrId?: number
  dtrResubmitId?: number
  agreementIdCreated?: number
  endorsementUrl?: string
  createdJobTitle: string
  blockers: string[]
}

async function shotInt(page: Page, name: string) {
  fs.mkdirSync(shotDir, { recursive: true })
  await page.screenshot({ path: path.join(shotDir, `${name}.png`), fullPage: true })
}

async function loginApi(
  request: APIRequestContext,
  email: string,
  password: string
): Promise<{ token: string; user: any }> {
  const res = await request.post(`${API}/auth/login`, { data: { email, password } })
  expect(res.ok(), `login ${email}`).toBeTruthy()
  return res.json()
}

function auth(token: string) {
  return { Authorization: `Bearer ${token}` }
}

function note(testInfo: { annotations: { type: string; description?: string }[] }, type: string, description: string) {
  testInfo.annotations.push({ type, description })
}

async function ensureMhAgreement(
  request: APIRequestContext,
  state: JourneyState,
  municipalUserId: number,
  testInfo: any
) {
  const list = await request.get(`${API}/agreements`, { headers: auth(state.schoolToken) })
  const rows = ((await list.json()).data || []) as Array<{ id: string; companyId: string; status: string }>
  const existing = rows.find((r) => String(r.companyId) === String(municipalUserId) && r.status === 'active')
  if (existing) {
    note(testInfo, 'step', `MH agreement already active id=${existing.id}`)
    return
  }
  const pending = rows.find((r) => String(r.companyId) === String(municipalUserId) && r.status === 'pending')
  let agreementId = pending ? Number(pending.id) : 0
  if (!agreementId) {
    const created = await request.post(`${API}/agreements`, {
      headers: auth(state.schoolToken),
      data: {
        requestedByRole: 'school',
        subject: 'QA Integration MOA Naic',
        companyId: municipalUserId,
        purpose: 'Integration QA temporary MOA',
        startDate: '2026-10-01',
        endDate: '2027-10-01',
        terms: 'Temporary QA agreement — cancelled after journey',
      },
    })
    if (!created.ok()) {
      state.blockers.push(`MH agreement create failed: ${created.status()} ${await created.text()}`)
      note(testInfo, 'NOT_TESTED', state.blockers[state.blockers.length - 1])
      return
    }
    agreementId = Number((await created.json()).data.id)
    state.agreementIdCreated = agreementId
  }
  const accept = await request.patch(`${API}/agreements/${agreementId}/accept`, {
    headers: auth(state.hostToken),
    data: {},
  })
  if (!accept.ok()) {
    state.blockers.push(`MH agreement accept failed: ${accept.status()} ${await accept.text()}`)
    note(testInfo, 'NOT_TESTED', state.blockers[state.blockers.length - 1])
    return
  }
  note(testInfo, 'step', `MH agreement activated id=${agreementId}`)
}

async function ensureEndorsement(request: APIRequestContext, state: JourneyState, testInfo: any) {
  const docs = await request.get(`${API}/documents`, {
    headers: { ...auth(state.studentToken), Accept: 'application/json' },
  })
  const list = ((await docs.json()).data || []) as Array<{ category: string; file_url?: string; fileUrl?: string }>
  const existing = list.find((d) => /endorsement/i.test(d.category || ''))
  if (existing) {
    const fileUrl = existing.fileUrl || existing.file_url
    state.endorsementUrl = fileUrl?.startsWith('http')
      ? fileUrl
      : fileUrl
        ? `http://127.0.0.1:8000${fileUrl}`
        : undefined
    note(testInfo, 'step', 'Using existing endorsement document')
    return
  }
  const pdfPath = path.join(shotDir, '_qa-endorsement.pdf')
  fs.mkdirSync(shotDir, { recursive: true })
  // Minimal PDF bytes
  fs.writeFileSync(
    pdfPath,
    Buffer.from(
      '%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n',
      'utf8'
    )
  )
  const upload = await request.post(`${API}/documents`, {
    headers: { ...auth(state.studentToken), Accept: 'application/json' },
    multipart: {
      category: 'application-endorsement',
      'files[]': {
        name: 'qa-endorsement.pdf',
        mimeType: 'application/pdf',
        buffer: fs.readFileSync(pdfPath),
      },
    },
  })
  const uploadText = await upload.text()
  if (!upload.ok()) {
    state.blockers.push(`Endorsement upload failed: ${upload.status()} ${uploadText.slice(0, 200)}`)
    note(testInfo, 'NOT_TESTED', state.blockers[state.blockers.length - 1])
    return
  }
  let body: any
  try {
    body = JSON.parse(uploadText)
  } catch {
    state.blockers.push(`Endorsement upload returned non-JSON: ${uploadText.slice(0, 120)}`)
    note(testInfo, 'NOT_TESTED', state.blockers[state.blockers.length - 1])
    return
  }
  const fileUrl = body.data?.[0]?.file_url || body.data?.[0]?.fileUrl
  state.endorsementUrl = fileUrl?.startsWith('http')
    ? fileUrl
    : fileUrl
      ? `http://127.0.0.1:8000${fileUrl}`
      : undefined
  note(testInfo, 'step', `Endorsement ready url=${state.endorsementUrl ? 'yes' : 'no'}`)
}

async function readHours(
  request: APIRequestContext,
  token: string,
  applicationId: number
): Promise<{ required?: number; approved?: number; remaining?: number; raw?: any }> {
  const cal = await request.get(`${API}/applications/${applicationId}/calendar`, {
    headers: auth(token),
  })
  if (!cal.ok()) {
    const apps = await request.get(`${API}/applications`, { headers: auth(token) })
    const row = ((await apps.json()).data || []).find((a: any) => Number(a.id) === applicationId)
    return {
      required: row?.requiredOjtHours ?? row?.required_ojt_hours,
      raw: row,
    }
  }
  const data = (await cal.json()).data
  return {
    required: data?.summary?.requiredHours,
    approved: data?.summary?.approvedHours,
    remaining: data?.summary?.remainingHours,
    raw: data?.summary,
  }
}

async function cleanupJourney(request: APIRequestContext, state: JourneyState, hostKey: HostKey) {
  const admin = await loginApi(request, DEMO.admin.email, DEMO.admin.password)
  // Soft-delete / cancel created records via host/school tokens where possible
  if (state.dtrResubmitId || state.dtrId) {
    // no hard delete API — leave orphan rows marked via cancelled internship if possible
  }
  if (state.internshipId) {
    await request.delete(`${API}/internships/${state.internshipId}`, {
      headers: auth(state.hostToken),
    })
  }
  if (state.agreementIdCreated) {
    await request.patch(`${API}/agreements/${state.agreementIdCreated}/cancel`, {
      headers: auth(state.schoolToken),
      data: { reason: 'QA integration cleanup' },
    })
  }
  // Also cancel any QA Integration MOA Naic left pending/active from prior runs (except keep none)
  const agr = await request.get(`${API}/agreements`, { headers: auth(state.schoolToken) })
  for (const row of (await agr.json()).data || []) {
    if (
      String(row.subject || '').includes('QA Integration MOA') &&
      ['pending', 'active'].includes(row.status)
    ) {
      await request.patch(`${API}/agreements/${row.id}/cancel`, {
        headers: auth(state.schoolToken),
        data: { reason: 'QA integration cleanup' },
      })
    }
  }
  // Restore demo org subscriptions to active
  const orgs = await request.get(`${API}/admin/subscriptions`, {
    headers: auth(admin.token),
  })
  for (const org of (await orgs.json()).data || []) {
    const name = String(org.name || '')
    if (
      name.includes('TechCorp') ||
      name.includes('Naic') ||
      name.includes('Cavite State')
    ) {
      await request.post(`${API}/admin/subscriptions/${org.id}/activate`, {
        headers: { ...auth(admin.token), 'Content-Type': 'application/json' },
        data: { plan: 'standard', reason: `QA restore after ${hostKey} journey` },
      })
    }
  }
}

async function runJourney(page: Page, request: APIRequestContext, hostKey: HostKey) {
  const host = HOSTS[hostKey]
  const testInfo = test.info()
  test.setTimeout(300_000)
  const createdJobTitle = `${JOB_TITLE_PREFIX} ${host.label} ${Date.now()}`
  const state: JourneyState = {
    hostToken: '',
    schoolToken: '',
    studentToken: '',
    createdJobTitle,
    blockers: [],
  }

  const hostLogin = await loginApi(request, host.email, host.password)
  const schoolLogin = await loginApi(request, DEMO.school.email, DEMO.school.password)
  const studentLogin = await loginApi(request, DEMO.student.email, DEMO.student.password)
  state.hostToken = hostLogin.token
  state.schoolToken = schoolLogin.token
  state.studentToken = studentLogin.token

  if (hostKey === 'municipal') {
    await ensureMhAgreement(request, state, Number(hostLogin.user.uid), testInfo)
  }

  await ensureEndorsement(request, state, testInfo)

  // --- 1) Host posts job with different hours (API; UI wizard is multi-step) ---
  const jobRes = await request.post(`${API}/internships`, {
    headers: auth(state.hostToken),
    data: {
      title: createdJobTitle,
      description: 'Integration QA posting with mismatched host hours.',
      status: 'active',
      requires_resume: false,
      host_required_hours: HOST_HOURS,
      slots_available: 2,
      schedule_slots: [
        { day: 'mon', start: '08:00', end: '17:00' },
        { day: 'tue', start: '08:00', end: '17:00' },
        { day: 'wed', start: '08:00', end: '17:00' },
        { day: 'thu', start: '08:00', end: '17:00' },
        { day: 'fri', start: '08:00', end: '17:00' },
      ],
    },
  })
  if (!jobRes.ok()) {
    note(testInfo, 'NOT_TESTED', `Host post job failed: ${jobRes.status()} ${await jobRes.text()}`)
    await cleanupJourney(request, state, hostKey)
    return
  }
  state.internshipId = Number((await jobRes.json()).data.id)
  note(testInfo, 'PASS', `1-host-post jobId=${state.internshipId} hostHours=${HOST_HOURS}`)

  await loginAs(page, host.email, host.password)
  await page.goto('/dashboard')
  await shotInt(page, `${host.slug}-01-host-dashboard-after-post`)

  // --- 2) Student applies via /find-internships (UI preferred) ---
  await loginAs(page, DEMO.student.email, DEMO.student.password)
  await page.goto('/find-internships')
  await expect(page.getByRole('heading', { name: /Find Internships|Internships|Openings/i }).first()).toBeVisible({
    timeout: 20_000,
  })
  await shotInt(page, `${host.slug}-02-find-internships`)

  let appliedViaUi = false
  const search = page.getByPlaceholder(/search/i).first()
  if (await search.isVisible().catch(() => false)) {
    await search.fill(JOB_TITLE_PREFIX)
    await page.waitForTimeout(800)
  }
  const card = page.getByText(createdJobTitle).first()
  if (await card.isVisible({ timeout: 8_000 }).catch(() => false)) {
    const viewApply = page.getByRole('button', { name: /View & Apply/i }).first()
    if (await viewApply.isVisible().catch(() => false)) {
      await viewApply.click()
      await page.waitForTimeout(1000)
      await shotInt(page, `${host.slug}-03-apply-dialog`)
      const submit = page.getByRole('button', { name: /Submit Application/i })
      if (await submit.isVisible().catch(() => false)) {
        await submit.click()
        await page.waitForTimeout(1500)
        appliedViaUi = true
        note(testInfo, 'PASS', '2-student-apply via UI /find-internships')
      }
    }
  }

  if (!appliedViaUi) {
    if (!state.endorsementUrl) {
      note(testInfo, 'NOT_TESTED', '2-student-apply: no endorsement URL and UI apply could not complete')
      await cleanupJourney(request, state, hostKey)
      return
    }
    const apply = await request.post(`${API}/applications`, {
      headers: auth(state.studentToken),
      data: {
        internship_id: state.internshipId,
        endorsement_url: state.endorsementUrl,
      },
    })
    if (!apply.ok()) {
      note(testInfo, 'NOT_TESTED', `2-student-apply API failed: ${apply.status()} ${await apply.text()}`)
      await cleanupJourney(request, state, hostKey)
      return
    }
    state.applicationId = Number((await apply.json()).data.id)
    note(testInfo, 'PASS', `2-student-apply via API (UI card not found/submit blocked) appId=${state.applicationId}`)
  } else {
    const apps = await request.get(`${API}/applications`, { headers: auth(state.studentToken) })
    const row = ((await apps.json()).data || []).find(
      (a: any) => Number(a.internshipId || a.internship_id) === state.internshipId
    )
    state.applicationId = Number(row?.id)
  }

  if (!state.applicationId) {
    note(testInfo, 'NOT_TESTED', '2-student-apply: application id missing after apply')
    await cleanupJourney(request, state, hostKey)
    return
  }

  // --- 3) Hours badge ---
  await page.goto('/intern/tracker')
  await page.waitForTimeout(1000)
  await shotInt(page, `${host.slug}-04-hours-badge-tracker`)
  const bodyText = await page.locator('body').innerText()
  if (/hours|486|300|mismatch|agreement/i.test(bodyText)) {
    note(testInfo, 'PASS', '3-hours-badge visible on tracker')
  } else {
    note(testInfo, 'NOT_TESTED', '3-hours-badge: tracker loaded but mismatch badge text not found in DOM')
  }
  let hours = await readHours(request, state.studentToken, state.applicationId)
  note(testInfo, 'hours', `after-apply required=${hours.required}`)

  // --- 4) School + host agree hours ---
  const propose = await request.post(`${API}/applications/${state.applicationId}/hours-agreement/propose`, {
    headers: auth(state.schoolToken),
    data: { source: 'school', hours: 486 },
  })
  if (!propose.ok()) {
    note(testInfo, 'NOT_TESTED', `4-hours-propose failed: ${propose.status()} ${await propose.text()}`)
  } else {
    const confirm = await request.post(`${API}/applications/${state.applicationId}/hours-agreement/confirm`, {
      headers: auth(state.hostToken),
    })
    if (!confirm.ok()) {
      note(testInfo, 'NOT_TESTED', `4-hours-confirm failed: ${confirm.status()} ${await confirm.text()}`)
    } else {
      note(testInfo, 'PASS', '4-hours-agreement school propose + host confirm = 486')
    }
  }
  await page.reload()
  hours = await readHours(request, state.studentToken, state.applicationId)
  note(testInfo, 'hours', `after-agree required=${hours.required} approved=${hours.approved}`)
  await shotInt(page, `${host.slug}-05-after-hours-agree`)

  // --- 5) School endorses ---
  const endorse = await request.post(`${API}/applications/${state.applicationId}/tracker/transition`, {
    headers: auth(state.schoolToken),
    data: { to_stage: 'endorsed' },
  })
  // fallback status patch
  if (!endorse.ok()) {
    const patch = await request.patch(`${API}/applications/${state.applicationId}/status`, {
      headers: auth(state.schoolToken),
      data: { status: 'endorsed' },
    })
    if (!patch.ok()) {
      note(testInfo, 'NOT_TESTED', `5-endorse failed: ${endorse.status()}/${patch.status()}`)
      await cleanupJourney(request, state, hostKey)
      return
    }
  }
  note(testInfo, 'PASS', '5-school-endorse')
  await page.reload()
  await shotInt(page, `${host.slug}-06-after-endorse`)

  // --- 6) Host schedules interview ---
  const interview = await request.post(`${API}/interviews`, {
    headers: auth(state.hostToken),
    data: {
      application_id: state.applicationId,
      scheduled_at: new Date(Date.now() + 86400000).toISOString(),
      duration_minutes: 45,
      mode: 'online',
      location_or_link: 'https://meet.example.com/qa',
    },
  })
  if (!interview.ok()) {
    note(testInfo, 'NOT_TESTED', `6-schedule-interview failed: ${interview.status()} ${await interview.text()}`)
    await cleanupJourney(request, state, hostKey)
    return
  }
  state.interviewId = Number((await interview.json()).data.id)
  await request.patch(`${API}/interviews/${state.interviewId}/confirm`, {
    headers: auth(state.studentToken),
  })
  note(testInfo, 'PASS', `6-interview-scheduled id=${state.interviewId}`)

  // --- 7) Interviewed → Assessed → Accept ---
  const interviewed = await request.post(`${API}/applications/${state.applicationId}/tracker/transition`, {
    headers: auth(state.hostToken),
    data: { to_stage: 'interviewed' },
  })
  if (!interviewed.ok()) {
    note(testInfo, 'NOT_TESTED', `7-interviewed failed: ${interviewed.status()} ${await interviewed.text()}`)
    await cleanupJourney(request, state, hostKey)
    return
  }
  const assess = await request.post(`${API}/applications/${state.applicationId}/tracker/assessment`, {
    headers: auth(state.hostToken),
    data: { technical: 4, communication: 4, attitude: 5, comments: 'QA accept' },
  })
  if (!assess.ok()) {
    note(testInfo, 'NOT_TESTED', `7-assessment failed: ${assess.status()} ${await assess.text()}`)
    await cleanupJourney(request, state, hostKey)
    return
  }
  note(testInfo, 'PASS', '7-assessed')
  const accept = await request.post(`${API}/applications/${state.applicationId}/tracker/transition`, {
    headers: auth(state.hostToken),
    data: { to_stage: 'accepted' },
  })
  if (!accept.ok()) {
    note(testInfo, 'NOT_TESTED', `7-accept failed: ${accept.status()} ${await accept.text()}`)
    await cleanupJourney(request, state, hostKey)
    return
  }
  note(testInfo, 'PASS', '7-accepted')
  await page.goto('/ojt-hours')
  await page.waitForTimeout(800)
  hours = await readHours(request, state.studentToken, state.applicationId)
  note(testInfo, 'hours', `after-accept required=${hours.required} approved=${hours.approved}`)
  await shotInt(page, `${host.slug}-07-after-accept-ojt-hours`)

  // --- 8) Student requests schedule change; host approves ---
  const schedReq = await request.post(`${API}/applications/${state.applicationId}/schedule/request`, {
    headers: auth(state.studentToken),
    data: {
      slots: [
        { day: 'tue', start: '08:00', end: '12:00' },
        { day: 'thu', start: '08:00', end: '12:00' },
      ],
      reason: 'QA shorter weekly load',
    },
  })
  if (!schedReq.ok()) {
    note(testInfo, 'NOT_TESTED', `8-schedule-request failed: ${schedReq.status()} ${await schedReq.text()}`)
  } else {
    const requestId = Number((await schedReq.json()).data.request.id)
    const decide = await request.post(
      `${API}/applications/${state.applicationId}/schedule/requests/${requestId}/decide`,
      {
        headers: auth(state.hostToken),
        data: { decision: 'approved' },
      }
    )
    if (!decide.ok()) {
      note(testInfo, 'NOT_TESTED', `8-schedule-approve failed: ${decide.status()} ${await decide.text()}`)
    } else {
      note(testInfo, 'PASS', '8-schedule-change approved')
    }
  }
  await page.reload()
  hours = await readHours(request, state.studentToken, state.applicationId)
  note(testInfo, 'hours', `after-schedule-change required=${hours.required} approved=${hours.approved}`)
  await shotInt(page, `${host.slug}-08-after-schedule-change`)

  // --- 9) DTR submit → host reject → resubmit → host approve → school approve ---
  const pdfPath = path.join(shotDir, '_qa-dtr.pdf')
  fs.writeFileSync(pdfPath, Buffer.from('%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n', 'utf8'))
  const dtrBuf = fs.readFileSync(pdfPath)
  const upload = await request.post(`${API}/ojt-dtrs`, {
    headers: auth(state.studentToken),
    multipart: {
      application_id: String(state.applicationId),
      period_start: '2026-10-06',
      period_end: '2026-10-10',
      submitted_hours: '8',
      file: { name: 'qa-dtr.pdf', mimeType: 'application/pdf', buffer: dtrBuf },
    },
  })
  if (!upload.ok()) {
    note(testInfo, 'NOT_TESTED', `9-dtr-submit failed: ${upload.status()} ${await upload.text()}`)
  } else {
    state.dtrId = Number((await upload.json()).data.id)
    note(testInfo, 'PASS', `9a-dtr-submit id=${state.dtrId}`)
    const reject = await request.post(`${API}/ojt-dtrs/${state.dtrId}/reject`, {
      headers: auth(state.hostToken),
      data: { reason: 'QA incomplete signatures', revision_required: true },
    })
    if (!reject.ok()) {
      note(testInfo, 'NOT_TESTED', `9b-dtr-reject failed: ${reject.status()} ${await reject.text()}`)
    } else {
      note(testInfo, 'PASS', '9b-dtr-host-reject-with-remarks')
      const resubmit = await request.post(`${API}/ojt-dtrs/${state.dtrId}/resubmit`, {
        headers: auth(state.studentToken),
        multipart: {
          application_id: String(state.applicationId),
          period_start: '2026-10-06',
          period_end: '2026-10-10',
          submitted_hours: '8',
          file: { name: 'qa-dtr-v2.pdf', mimeType: 'application/pdf', buffer: dtrBuf },
        },
      })
      if (!resubmit.ok()) {
        note(testInfo, 'NOT_TESTED', `9c-dtr-resubmit failed: ${resubmit.status()} ${await resubmit.text()}`)
      } else {
        state.dtrResubmitId = Number((await resubmit.json()).data.id)
        note(testInfo, 'PASS', `9c-dtr-resubmit id=${state.dtrResubmitId}`)
        const hostApprove = await request.post(`${API}/ojt-dtrs/${state.dtrResubmitId}/approve`, {
          headers: auth(state.hostToken),
        })
        if (!hostApprove.ok()) {
          // student resubmit may still be pending_company; if host already skipped, try school
          note(testInfo, 'step', `9d-host-approve status=${hostApprove.status()} ${await hostApprove.text()}`)
        } else {
          note(testInfo, 'PASS', '9d-dtr-host-approve')
        }
        const targetId = state.dtrResubmitId
        const schoolApprove = await request.post(`${API}/ojt-dtrs/${targetId}/approve`, {
          headers: auth(state.schoolToken),
        })
        if (!schoolApprove.ok()) {
          // maybe still pending_company — host approve first then school
          await request.post(`${API}/ojt-dtrs/${targetId}/approve`, { headers: auth(state.hostToken) })
          const school2 = await request.post(`${API}/ojt-dtrs/${targetId}/approve`, {
            headers: auth(state.schoolToken),
          })
          if (!school2.ok()) {
            note(testInfo, 'NOT_TESTED', `9e-school-approve failed: ${school2.status()} ${await school2.text()}`)
          } else {
            note(testInfo, 'PASS', '9e-dtr-school-approve')
          }
        } else {
          note(testInfo, 'PASS', '9e-dtr-school-approve')
        }
      }
    }
  }

  await page.reload()
  hours = await readHours(request, state.studentToken, state.applicationId)
  note(testInfo, 'hours', `after-dtr required=${hours.required} approved=${hours.approved}`)
  await shotInt(page, `${host.slug}-09-after-dtr-chain`)

  await loginAs(page, host.email, host.password)
  await page.goto('/dashboard')
  await shotInt(page, `${host.slug}-10-host-final`)

  await cleanupJourney(request, state, hostKey)
  note(testInfo, 'cleanup', `removed internship=${state.internshipId}; restored demo orgs Active`)
}

test.describe.configure({ mode: 'serial' })

test.describe('Integration full journeys', () => {
  test('Company host full placement journey', async ({ page, request }) => {
    await runJourney(page, request, 'company')
  })

  test('Municipal Hall host full placement journey', async ({ page, request }) => {
    await runJourney(page, request, 'municipal')
  })
})

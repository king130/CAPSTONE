import { apiFetch } from './http'
import { createSharedPollingResource } from './sharedPolling'

export interface InternshipRecord {
  id: string
  title: string
  description?: string
  hostType?: 'company' | 'school'
  hostName?: string
  companyId: string
  companyName: string
  industry?: string
  schoolId?: string
  schoolName?: string
  hostId?: string
  /** Host organization PK for public /org/:id links. */
  organizationId?: string | null
  location: string
  type: string
  duration: string
  slotsAvailable: number
  /** Remaining open slots when provided by API; falls back to slotsAvailable. */
  slotsRemaining?: number
  eligibleCourses?: string[]
  requirements?: string[]
  allowance?: string
  startDate?: string
  endDate?: string
  applicationDeadline?: string | null
  schedule?: string
  scheduleType?: string | null
  weeklyHours?: number | null
  scheduleDays?: string[]
  timeIn?: string | null
  timeOut?: string | null
  timezone?: string | null
  isFlexible?: boolean
  /** On-site / Hybrid / Remote / Field Work when provided (else mirrors `type`). */
  workSetup?: string
  coverImage?: string | null
  perks?: string[]
  /** Gallery from internship detail endpoint. */
  organizationMedia?: Array<{
    id: string
    url: string
    caption?: string | null
    sortOrder?: number
    isCover?: boolean
  }>
  /** Company/org verification badge when API provides it. */
  verified?: boolean
  /** Whether the viewer's school has an active partnership for this posting. */
  partneredWithMySchool?: boolean
  /** Present for authenticated students only. */
  saved?: boolean
  tasks?: string
  requiredSkills?: string[]
  internGains?: string
  requiredDocuments?: string[]
  applicationInstructions?: string
  contactInfo?: string
  approvalStatus?: 'pending' | 'approved' | 'declined'
  approvalNotes?: string
  status: 'active' | 'draft' | 'closed'
  createdAt?: unknown
  updatedAt?: unknown
}

export type InternshipSortOption =
  | 'newest'
  | 'slots_remaining'
  | 'allowance'
  | 'course_match'
  | 'title'

export interface InternshipSearchParams {
  q?: string
  city?: string
  location?: string
  work_setup?: string
  course?: string
  allowance?: string
  schedule_type?: string
  sort?: InternshipSortOption
  status?: string
  page?: number
  per_page?: number
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {}
}

function asString(value: unknown, fallback = ''): string {
  if (typeof value === 'string') return value
  if (typeof value === 'number' && Number.isFinite(value)) return String(value)
  return fallback
}

function asOptionalString(value: unknown): string | null | undefined {
  if (value == null) return value === null ? null : undefined
  const text = asString(value).trim()
  return text || null
}

function asNumber(value: unknown, fallback = 0): number {
  const n = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(n) ? n : fallback
}

function asBoolean(value: unknown): boolean | undefined {
  if (typeof value === 'boolean') return value
  if (value === 1 || value === '1' || value === 'true') return true
  if (value === 0 || value === '0' || value === 'false') return false
  return undefined
}

function asStringArray(value: unknown): string[] | undefined {
  if (!Array.isArray(value)) return undefined
  return value.map((item) => asString(item).trim()).filter(Boolean)
}

/** Normalize camelCase or snake_case internship payloads from the API. */
export function normalizeInternshipRecord(raw: unknown): InternshipRecord {
  const row = asRecord(raw)
  const slotsAvailable = asNumber(row.slotsAvailable ?? row.slots_available, 0)
  const slotsRemainingRaw = row.slotsRemaining ?? row.slots_remaining
  const workSetup = asOptionalString(row.workSetup ?? row.work_setup ?? row.type) ?? undefined
  const coverImage = asOptionalString(row.coverImage ?? row.cover_image)
  const partnered = asBoolean(row.partneredWithMySchool ?? row.partnered_with_my_school)
  const verified = asBoolean(row.verified ?? row.isVerified ?? row.is_verified)

  const mediaRaw = row.organizationMedia ?? row.organization_media
  const organizationMedia = Array.isArray(mediaRaw)
    ? mediaRaw.map((item) => {
        const media = asRecord(item)
        return {
          id: asString(media.id),
          url: asString(media.url),
          caption: asOptionalString(media.caption),
          sortOrder: asNumber(media.sortOrder ?? media.sort_order, 0),
          isCover: asBoolean(media.isCover ?? media.is_cover) ?? false,
        }
      }).filter((item) => item.id && item.url)
    : undefined

  return {
    id: asString(row.id),
    title: asString(row.title),
    description: asOptionalString(row.description) ?? undefined,
    hostType: (asString(row.hostType ?? row.host_type) as 'company' | 'school') || undefined,
    hostName: asOptionalString(row.hostName ?? row.host_name) ?? undefined,
    companyId: asString(row.companyId ?? row.company_id),
    companyName: asString(row.companyName ?? row.company_name ?? row.hostName ?? row.host_name),
    industry: asOptionalString(row.industry) ?? undefined,
    schoolId: asOptionalString(row.schoolId ?? row.school_id) ?? undefined,
    schoolName: asOptionalString(row.schoolName ?? row.school_name) ?? undefined,
    hostId: asOptionalString(row.hostId ?? row.host_id) ?? undefined,
    organizationId: asOptionalString(row.organizationId ?? row.organization_id) ?? null,
    location: asString(row.location),
    type: asString(row.type || workSetup),
    duration: asString(row.duration),
    slotsAvailable,
    slotsRemaining: slotsRemainingRaw == null ? slotsAvailable : asNumber(slotsRemainingRaw, slotsAvailable),
    eligibleCourses: asStringArray(row.eligibleCourses ?? row.eligible_courses),
    requirements: asStringArray(row.requirements),
    allowance: asOptionalString(row.allowance) ?? undefined,
    startDate: asOptionalString(row.startDate ?? row.start_date) ?? undefined,
    endDate: asOptionalString(row.endDate ?? row.end_date) ?? undefined,
    applicationDeadline: asOptionalString(row.applicationDeadline ?? row.application_deadline) ?? null,
    schedule: asOptionalString(row.schedule) ?? undefined,
    perks: asStringArray(row.perks),
    organizationMedia,
    scheduleType: asOptionalString(row.scheduleType ?? row.schedule_type),
    weeklyHours:
      row.weeklyHours == null && row.weekly_hours == null
        ? null
        : asNumber(row.weeklyHours ?? row.weekly_hours, 0),
    scheduleDays: asStringArray(row.scheduleDays ?? row.schedule_days),
    timeIn: asOptionalString(row.timeIn ?? row.time_in),
    timeOut: asOptionalString(row.timeOut ?? row.time_out),
    timezone: asOptionalString(row.timezone),
    isFlexible: asBoolean(row.isFlexible ?? row.is_flexible) ?? false,
    workSetup,
    coverImage,
    verified: verified ?? false,
    partneredWithMySchool: partnered,
    saved: asBoolean(row.saved),
    tasks: asOptionalString(row.tasks) ?? undefined,
    requiredSkills: asStringArray(row.requiredSkills ?? row.required_skills),
    internGains: asOptionalString(row.internGains ?? row.intern_gains) ?? undefined,
    requiredDocuments: asStringArray(row.requiredDocuments ?? row.required_documents),
    applicationInstructions:
      asOptionalString(row.applicationInstructions ?? row.application_instructions) ?? undefined,
    contactInfo: asOptionalString(row.contactInfo ?? row.contact_info) ?? undefined,
    approvalStatus: asString(row.approvalStatus ?? row.approval_status) as InternshipRecord['approvalStatus'],
    approvalNotes: asOptionalString(row.approvalNotes ?? row.approval_notes) ?? undefined,
    status: (asString(row.status, 'active') as InternshipRecord['status']) || 'active',
    createdAt: row.createdAt ?? row.created_at,
    updatedAt: row.updatedAt ?? row.updated_at,
  }
}

function buildInternshipQuery(params: InternshipSearchParams = {}): string {
  const search = new URLSearchParams()
  const entries: Array<[string, string | number | undefined]> = [
    ['q', params.q],
    ['city', params.city],
    ['location', params.location ?? params.city],
    ['work_setup', params.work_setup],
    ['course', params.course],
    ['allowance', params.allowance],
    ['schedule_type', params.schedule_type],
    ['sort', params.sort],
    ['status', params.status],
    ['page', params.page],
    ['per_page', params.per_page],
  ]

  for (const [key, value] of entries) {
    if (value === undefined || value === null || value === '') continue
    search.set(key, String(value))
  }

  const qs = search.toString()
  return qs ? `?${qs}` : ''
}

async function loadAllInternships(): Promise<InternshipRecord[]> {
  const res = await apiFetch<{ data: unknown[] }>('/internships')
  return (res.data ?? []).map(normalizeInternshipRecord)
}

async function loadEligibleInternships(): Promise<InternshipRecord[]> {
  const res = await apiFetch<{ data: unknown[] }>('/internships/eligible')
  return (res.data ?? []).map(normalizeInternshipRecord)
}

/**
 * Catalog search for the public/student opportunities listing.
 * Sends server-side filter/sort/page params; normalizes rich listing fields.
 */
export async function searchInternshipsCatalog(
  params: InternshipSearchParams = {},
): Promise<InternshipRecord[]> {
  const qs = buildInternshipQuery({ status: 'active', ...params })
  const res = await apiFetch<{ data: unknown[] }>(`/internships${qs}`)
  return (res.data ?? []).map(normalizeInternshipRecord)
}

export async function listEligibleInternshipIds(): Promise<Set<string>> {
  const items = await loadEligibleInternships()
  return new Set(items.map((item) => item.id))
}

const internshipsResource = createSharedPollingResource<InternshipRecord[]>({
  intervalMs: 15000,
  load: loadAllInternships,
  initialValue: [],
})

const eligibleInternshipsResource = createSharedPollingResource<InternshipRecord[]>({
  intervalMs: 15000,
  load: loadEligibleInternships,
  initialValue: [],
})

export function subscribeInternships(callback: (items: InternshipRecord[]) => void): () => void {
  return internshipsResource.subscribe(callback)
}

export function subscribeActiveInternships(callback: (items: InternshipRecord[]) => void): () => void {
  return subscribeInternships((items) => {
    callback(items.filter((i) => i.status === 'active'))
  })
}

export function subscribeEligibleInternships(callback: (items: InternshipRecord[]) => void): () => void {
  return eligibleInternshipsResource.subscribe(callback)
}

export function subscribeCompanyInternships(
  companyId: string,
  callback: (items: InternshipRecord[]) => void
): () => void {
  return subscribeInternships((items) => {
    callback(items.filter((internship) => internship.companyId === companyId))
  })
}

export function subscribeSchoolInternships(
  schoolId: string,
  callback: (items: InternshipRecord[]) => void
): () => void {
  return subscribeInternships((items) => {
    callback(items.filter((internship) => internship.schoolId === schoolId))
  })
}

export async function getInternship(internshipId: string): Promise<InternshipRecord | null> {
  try {
    const res = await apiFetch<{ data: unknown }>(`/internships/${internshipId}`)
    return res.data ? normalizeInternshipRecord(res.data) : null
  } catch {
    return null
  }
}

export type CreateInternshipPayload = Omit<InternshipRecord, 'id' | 'createdAt' | 'updatedAt'>

export async function createInternship(payload: CreateInternshipPayload): Promise<string> {
  const res = await apiFetch<{ data: InternshipRecord }>('/internships', {
    method: 'POST',
    body: JSON.stringify({
      title: payload.title,
      description: payload.description,
      location: payload.location,
      type: payload.type,
      industry: payload.industry,
      duration: payload.duration,
      slots_available: payload.slotsAvailable,
      status: payload.status || 'active',
      requirements: payload.requirements,
      eligible_courses: payload.eligibleCourses,
      allowance: payload.allowance,
      start_date: payload.startDate,
      end_date: payload.endDate,
      schedule: payload.schedule,
      schedule_type: payload.scheduleType,
      weekly_hours: payload.weeklyHours,
      schedule_days: payload.scheduleDays,
      time_in: payload.timeIn,
      time_out: payload.timeOut,
      timezone: payload.timezone,
      is_flexible: payload.isFlexible,
      tasks: payload.tasks,
      required_skills: payload.requiredSkills,
      intern_gains: payload.internGains,
      required_documents: payload.requiredDocuments,
      application_instructions: payload.applicationInstructions,
      contact_info: payload.contactInfo,
    }),
  })
  await internshipsResource.refresh()
  return res.data?.id ?? ''
}

export async function updateInternship(internshipId: string, payload: Partial<InternshipRecord>): Promise<void> {
  await apiFetch(`/internships/${internshipId}`, {
    method: 'PATCH',
    body: JSON.stringify({
      title: payload.title,
      description: payload.description,
      location: payload.location,
      type: payload.type,
      industry: payload.industry,
      duration: payload.duration,
      slots_available: payload.slotsAvailable,
      status: payload.status,
      requirements: payload.requirements,
      eligible_courses: payload.eligibleCourses,
      allowance: payload.allowance,
      start_date: payload.startDate,
      end_date: payload.endDate,
      schedule: payload.schedule,
      schedule_type: payload.scheduleType,
      weekly_hours: payload.weeklyHours,
      schedule_days: payload.scheduleDays,
      time_in: payload.timeIn,
      time_out: payload.timeOut,
      timezone: payload.timezone,
      is_flexible: payload.isFlexible,
      tasks: payload.tasks,
      required_skills: payload.requiredSkills,
      intern_gains: payload.internGains,
      required_documents: payload.requiredDocuments,
      application_instructions: payload.applicationInstructions,
      contact_info: payload.contactInfo,
    }),
  })
  await internshipsResource.refresh()
  await eligibleInternshipsResource.refresh()
}

export async function deleteInternship(internshipId: string): Promise<void> {
  await apiFetch(`/internships/${internshipId}`, { method: 'DELETE' })
  await internshipsResource.refresh()
  await eligibleInternshipsResource.refresh()
}

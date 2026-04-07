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
  hostType?: 'company' | 'school'
  hostId?: string
  hostName?: string
  location: string
  type: string
  duration: string
  slotsAvailable: number
  eligibleCourses?: string[]
  requirements?: string[]
  allowance?: string
  startDate?: string
  endDate?: string
  schedule?: string
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

async function loadAllInternships(): Promise<InternshipRecord[]> {
  const res = await apiFetch<{ data: InternshipRecord[] }>('/internships')
  return res.data ?? []
}

async function loadEligibleInternships(): Promise<InternshipRecord[]> {
  const res = await apiFetch<{ data: InternshipRecord[] }>('/internships/eligible')
  return res.data ?? []
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
    const res = await apiFetch<{ data: InternshipRecord }>(`/internships/${internshipId}`)
    return res.data ?? null
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

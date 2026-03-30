import { apiFetch } from './http'

export interface InternshipRecord {
  id: string
  title: string
  description?: string
  companyId: string
  companyName: string
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
  contactInfo?: string
  approvalStatus?: 'pending' | 'approved' | 'declined'
  approvalNotes?: string
  status: 'active' | 'draft' | 'closed'
  createdAt?: unknown
  updatedAt?: unknown
}

function poll(
  load: () => Promise<void>,
  intervalMs: number
): () => void {
  let cancelled = false
  load()
  const id = window.setInterval(() => {
    if (!cancelled) load()
  }, intervalMs)
  return () => {
    cancelled = true
    clearInterval(id)
  }
}

export function subscribeInternships(callback: (items: InternshipRecord[]) => void): () => void {
  return poll(async () => {
    try {
      const res = await apiFetch<{ data: InternshipRecord[] }>('/internships')
      callback(res.data ?? [])
    } catch {
      callback([])
    }
  }, 15000)
}

export function subscribeActiveInternships(callback: (items: InternshipRecord[]) => void): () => void {
  return subscribeInternships((items) => {
    callback(items.filter((i) => i.status === 'active'))
  })
}

export function subscribeCompanyInternships(
  companyId: string,
  callback: (items: InternshipRecord[]) => void
): () => void {
  return poll(async () => {
    try {
      const q = new URLSearchParams({ company_user_id: companyId })
      const res = await apiFetch<{ data: InternshipRecord[] }>(`/internships?${q.toString()}`)
      callback(res.data ?? [])
    } catch {
      callback([])
    }
  }, 15000)
}

export function subscribeSchoolInternships(
  schoolId: string,
  callback: (items: InternshipRecord[]) => void
): () => void {
  return poll(async () => {
    try {
      const q = new URLSearchParams({ school_user_id: schoolId })
      const res = await apiFetch<{ data: InternshipRecord[] }>(`/internships?${q.toString()}`)
      callback(res.data ?? [])
    } catch {
      callback([])
    }
  }, 15000)
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
      duration: payload.duration,
      slots_available: payload.slotsAvailable,
      status: payload.status || 'active',
      requirements: payload.requirements,
      eligible_courses: payload.eligibleCourses,
      allowance: payload.allowance,
      contact_info: payload.contactInfo,
    }),
  })
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
      duration: payload.duration,
      slots_available: payload.slotsAvailable,
      status: payload.status,
      requirements: payload.requirements,
      eligible_courses: payload.eligibleCourses,
      allowance: payload.allowance,
      contact_info: payload.contactInfo,
    }),
  })
}

export async function deleteInternship(internshipId: string): Promise<void> {
  await apiFetch(`/internships/${internshipId}`, { method: 'DELETE' })
}

import { apiFetch } from './http'

export interface ApplicationRecord {
  id: string
  internshipId: string
  studentId: string
  companyId?: string
  internshipTitle?: string
  studentName?: string
  studentEmail?: string
  studentCourse?: string
  status: string
  resume?: string | null
  documents?: string[] | null
  documentsPending?: boolean
  createdAt?: string
  updatedAt?: string
}

function poll(load: () => Promise<void>, intervalMs: number): () => void {
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

export function subscribeApplications(
  userId: string,
  callback: (items: ApplicationRecord[]) => void
): () => void {
  return poll(async () => {
    try {
      const res = await apiFetch<{ data: ApplicationRecord[] }>('/applications')
      const mine = (res.data ?? []).filter((a) => a.studentId === userId)
      callback(mine)
    } catch {
      callback([])
    }
  }, 12000)
}

export function subscribeCompanyApplications(
  companyId: string,
  callback: (items: ApplicationRecord[]) => void,
  onError?: (err: Error) => void
): () => void {
  return poll(async () => {
    try {
      const res = await apiFetch<{ data: ApplicationRecord[] }>('/applications')
      const mine = (res.data ?? []).filter((a) => a.companyId === companyId)
      callback(mine)
    } catch (err) {
      onError?.(err instanceof Error ? err : new Error('applications'))
      callback([])
    }
  }, 12000)
}

export function subscribeCompanyApplicationsByInternships(
  internshipIds: string[],
  callback: (items: ApplicationRecord[]) => void,
  onError?: (err: Error) => void
): () => void {
  if (internshipIds.length === 0) {
    callback([])
    return () => {}
  }
  const set = new Set(internshipIds.map(String))
  return poll(async () => {
    try {
      const res = await apiFetch<{ data: ApplicationRecord[] }>('/applications')
      const filtered = (res.data ?? []).filter((a) => set.has(String(a.internshipId)))
      callback(filtered)
    } catch (err) {
      onError?.(err instanceof Error ? err : new Error('applications'))
      callback([])
    }
  }, 12000)
}

export async function submitApplication(payload: Omit<ApplicationRecord, 'id'>): Promise<string> {
  const res = await apiFetch<{ data: { id: string } }>('/applications', {
    method: 'POST',
    body: JSON.stringify({
      internship_id: Number(payload.internshipId),
      resume_url: payload.resume ?? undefined,
      documents: payload.documents ?? undefined,
    }),
  })
  return res.data?.id ?? ''
}

export async function updateApplicationStatus(applicationId: string, status: string): Promise<void> {
  await apiFetch(`/applications/${applicationId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })
}

export async function updateApplicationDocuments(
  _applicationId: string,
  _updates: { resume?: string | null; documents?: string[] | null; documentsPending?: boolean }
): Promise<void> {
  // Extend API with PATCH /applications/{id} when document uploads are wired to storage.
}

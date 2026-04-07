import { apiFetch, getToken } from './http'
import { createSharedPollingResource } from './sharedPolling'

export interface ApplicationRecord {
  id: string
  internshipId: string
  studentId: string
  companyId?: string
  companyName?: string
  schoolName?: string
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

async function loadAllApplications(): Promise<ApplicationRecord[]> {
  if (!getToken()) return []
  const res = await apiFetch<{ data: ApplicationRecord[] }>('/applications')
  return res.data ?? []
}

const applicationsResource = createSharedPollingResource<ApplicationRecord[]>({
  intervalMs: 12000,
  load: loadAllApplications,
  initialValue: [],
})

export function subscribeAllApplications(callback: (items: ApplicationRecord[]) => void): () => void {
  return applicationsResource.subscribe(callback)
}

export function subscribeApplications(
  userId: string,
  callback: (items: ApplicationRecord[]) => void
): () => void {
  return subscribeAllApplications((items) => {
    callback(items.filter((application) => application.studentId === userId))
  })
}

export async function listApplications(): Promise<ApplicationRecord[]> {
  await applicationsResource.refresh()
  return applicationsResource.getSnapshot()
}

export function subscribeCompanyApplications(
  companyId: string,
  callback: (items: ApplicationRecord[]) => void,
  onError?: (err: Error) => void
): () => void {
  return subscribeAllApplications((items) => {
    try {
      if (!getToken()) {
        callback([])
        return
      }

      callback(items.filter((application) => application.companyId === companyId))
    } catch (err) {
      onError?.(err instanceof Error ? err : new Error('applications'))
      callback([])
    }
  })
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
  return subscribeAllApplications((items) => {
    try {
      if (!getToken()) {
        callback([])
        return
      }

      callback(items.filter((application) => set.has(String(application.internshipId))))
    } catch (err) {
      onError?.(err instanceof Error ? err : new Error('applications'))
      callback([])
    }
  })
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
  await applicationsResource.refresh()
  return res.data?.id ?? ''
}

export async function updateApplicationStatus(applicationId: string, status: string): Promise<void> {
  await apiFetch(`/applications/${applicationId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })
  await applicationsResource.refresh()
}

export async function updateApplicationDocuments(
  _applicationId: string,
  _updates: { resume?: string | null; documents?: string[] | null; documentsPending?: boolean }
): Promise<void> {
  // Extend API with PATCH /applications/{id} when document uploads are wired to storage.
}

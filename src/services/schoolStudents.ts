import { createSharedPollingResource } from './sharedPolling'
import { apiBase, apiFetch, getToken } from './http'

export type SchoolStudentRecord = SchoolStudentRow

export interface SchoolStudentRow {
  id: string
  schoolId: string
  internCode?: string
  email: string
  studentName?: string
  studentNumber?: string
  course?: string
  yearLevel?: string
  status?: string
  inviteSent?: boolean
  setupLinkExpiresAt?: string
  createdAt?: unknown
}

const schoolStudentsResource = createSharedPollingResource<SchoolStudentRow[]>({
  intervalMs: 15000,
  initialValue: [],
  load: async () => {
    if (!getToken()) {
      return []
    }

    const response = await apiFetch<{ data?: SchoolStudentRow[] }>('/school-students')
    return response.data ?? []
  },
})

export function subscribeSchoolStudents(
  _schoolId: string,
  callback: (items: SchoolStudentRow[]) => void
): () => void {
  return schoolStudentsResource.subscribe(callback)
}

export async function addSchoolStudent(
  _schoolId: string,
  email: string,
  extra?: Record<string, unknown>
): Promise<SchoolStudentRow> {
  const response = await apiFetch<{ data: SchoolStudentRow }>('/school-students', {
    method: 'POST',
    body: JSON.stringify({
      email,
      ...extra,
    }),
  })
  await schoolStudentsResource.refresh()
  return response.data
}

export async function resendSchoolStudentSetupLink(id: string): Promise<{ sent: boolean; expiresAt?: string | null; errorMessage?: string }> {
  const response = await apiFetch<{ invite?: { sent: boolean; expiresAt?: string | null; errorMessage?: string } }>(`/school-students/${id}/resend-setup-link`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
  await schoolStudentsResource.refresh()
  return response.invite ?? { sent: false }
}

export async function updateSchoolStudent(id: string, patch: Record<string, unknown>): Promise<void> {
  await apiFetch(`/school-students/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(patch),
  })
  await schoolStudentsResource.refresh()
}

export async function removeSchoolStudent(id: string): Promise<void> {
  await apiFetch(`/school-students/${id}`, {
    method: 'DELETE',
  })
  await schoolStudentsResource.refresh()
}

export async function findSchoolStudentByEmail(email: string): Promise<SchoolStudentRow | null> {
  let students = schoolStudentsResource.getSnapshot()
  if (!students.length) {
    await schoolStudentsResource.refresh()
    students = schoolStudentsResource.getSnapshot()
  }

  return students.find((student) => student.email.toLowerCase() === email.trim().toLowerCase()) ?? null
}

export async function exportSchoolStudentsCsv(_schoolId: string): Promise<Blob> {
  const base = apiBase()
  const url = base ? `${base}/school-students/export` : '/api/school-students/export'
  const headers = new Headers({
    Accept: 'text/csv',
  })
  const token = getToken()
  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
  }

  const response = await fetch(url, { headers })
  if (!response.ok) {
    throw new Error('Could not export student roster.')
  }
  return await response.blob()
}

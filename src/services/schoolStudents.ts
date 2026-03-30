import { apiBase, apiFetch, getToken } from './http'

export type SchoolStudentRecord = SchoolStudentRow

export interface SchoolStudentRow {
  id: string
  schoolId: string
  email: string
  studentName?: string
  studentNumber?: string
  course?: string
  yearLevel?: string
  status?: string
  defaultPassword?: string
  createdAt?: unknown
}

function poll(load: () => Promise<void>, intervalMs: number): () => void {
  let cancelled = false
  load()
  const id = window.setInterval(() => {
    if (!cancelled) load()
  }, intervalMs)
  const refresh = () => {
    if (!cancelled) load()
  }
  window.addEventListener('school-students:changed', refresh)
  return () => {
    cancelled = true
    clearInterval(id)
    window.removeEventListener('school-students:changed', refresh)
  }
}

function emitSchoolStudentsChanged() {
  window.dispatchEvent(new CustomEvent('school-students:changed'))
}

export function subscribeSchoolStudents(
  _schoolId: string,
  callback: (items: SchoolStudentRow[]) => void
): () => void {
  return poll(async () => {
    try {
      const response = await apiFetch<{ data?: SchoolStudentRow[] }>('/school-students')
      callback(response.data ?? [])
    } catch {
      callback([])
    }
  }, 15000)
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
  emitSchoolStudentsChanged()
  return response.data
}

export async function updateSchoolStudent(id: string, patch: Record<string, unknown>): Promise<void> {
  await apiFetch(`/school-students/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(patch),
  })
  emitSchoolStudentsChanged()
}

export async function removeSchoolStudent(id: string): Promise<void> {
  await apiFetch(`/school-students/${id}`, {
    method: 'DELETE',
  })
  emitSchoolStudentsChanged()
}

export async function findSchoolStudentByEmail(email: string): Promise<SchoolStudentRow | null> {
  const response = await apiFetch<{ data?: SchoolStudentRow[] }>('/school-students')
  return (response.data ?? []).find((student) => student.email.toLowerCase() === email.trim().toLowerCase()) ?? null
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

import { apiFetch, apiBase, getToken } from '@/services/http'

export interface OJTLog {
  id: number
  internId: number
  internName: string
  date: string
  timeIn: string
  timeOut: string
  hoursRendered: number
  tasksDone: string
  mood?: 'productive' | 'okay' | 'difficult' | 'great'
  status: 'pending' | 'approved' | 'rejected'
  rejectionReason?: string
  createdAt: string
  company?: string
  school?: string
  submittedAt?: string
  applicationId?: string | null
  internshipId?: string | null
}

export interface OJTProgress {
  totalLogged: number
  totalApproved: number
  totalPending: number
  totalRejected: number
  requiredHours: number
  percentComplete: number
}

export interface InternOJTSummary {
  internId: number
  internName: string
  company: string
  school: string
  approvedHours: number
  requiredHours: number
  status: 'completed' | 'on_track' | 'at_risk' | 'not_started'
  avatarFallback?: string
  totalLoggedHours?: number
}

export interface OJTAdminAnalytics {
  hoursPerWeek: Array<{ label: string; hours: number }>
  completionBySchool: Array<{ school: string; completionRate: number }>
}

export interface OJTAdminFilters {
  school?: string
  company?: string
  status?: string
  semesterYear?: string
  dateFrom?: string
  dateTo?: string
  search?: string
  page?: number
}

export interface OJTLogQuery {
  dateFrom?: string
  dateTo?: string
  page?: number
  status?: string
  internId?: number
}

export interface OJTCreateLogPayload {
  date: string
  timeIn: string
  timeOut: string
  tasksDone: string
  mood?: 'productive' | 'okay' | 'difficult' | 'great'
  application_id?: number
  internship_id?: number
}

export interface OJTRejectPayload {
  reason: string
}

function buildQuery(params: Record<string, string | number | undefined>): string {
  const search = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      search.set(key, String(value))
    }
  })
  const qs = search.toString()
  return qs ? `?${qs}` : ''
}

function initialsFromName(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function compareLogs(left: OJTLog, right: OJTLog): number {
  const leftKey = `${left.date}T${left.timeIn || '00:00'}`
  const rightKey = `${right.date}T${right.timeIn || '00:00'}`
  return rightKey.localeCompare(leftKey)
}

function applyClientFilters(logs: OJTLog[], filters: OJTAdminFilters): OJTLog[] {
  const query = (filters.search ?? '').trim().toLowerCase()
  return logs.filter((log) => {
    if (filters.school && log.school !== filters.school) return false
    if (filters.company && log.company !== filters.company) return false
    if (filters.status && log.status !== filters.status) return false
    if (filters.dateFrom && log.date < filters.dateFrom) return false
    if (filters.dateTo && log.date > filters.dateTo) return false
    if (query) {
      const haystack = [log.internName, log.school ?? '', log.company ?? '', log.tasksDone].join(' ').toLowerCase()
      if (!haystack.includes(query)) return false
    }
    return true
  })
}

function analyticsFromLogs(logs: OJTLog[], requiredHours = 500): OJTAdminAnalytics {
  const weeklyHours = new Map<string, number>()
  const bySchool = new Map<string, { approved: number; required: number }>()

  logs.forEach((log) => {
    const weekLabel = `${log.date.slice(0, 7)}`
    weeklyHours.set(weekLabel, (weeklyHours.get(weekLabel) ?? 0) + log.hoursRendered)

    const schoolName = log.school || 'Unassigned School'
    const bucket = bySchool.get(schoolName) ?? { approved: 0, required: requiredHours }
    if (log.status === 'approved') {
      bucket.approved += log.hoursRendered
    }
    bySchool.set(schoolName, bucket)
  })

  return {
    hoursPerWeek: Array.from(weeklyHours.entries())
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([label, hours]) => ({ label, hours: Number(hours.toFixed(1)) })),
    completionBySchool: Array.from(bySchool.entries())
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([school, values]) => ({
        school,
        completionRate: values.required > 0 ? Math.min(100, Math.round((values.approved / values.required) * 100)) : 0,
      })),
  }
}

export async function createOJTLog(payload: OJTCreateLogPayload): Promise<void> {
  await apiFetch('/ojt-logs', {
    method: 'POST',
    body: JSON.stringify({
      date: payload.date,
      timeIn: payload.timeIn,
      timeOut: payload.timeOut,
      tasksDone: payload.tasksDone,
      mood: payload.mood,
      application_id: payload.application_id,
      internship_id: payload.internship_id,
    }),
  })
}

export async function listInternOJTLogs(query: OJTLogQuery = {}): Promise<OJTLog[]> {
  const qs = buildQuery({
    date_from: query.dateFrom,
    date_to: query.dateTo,
    status: query.status,
    student_id: query.internId,
  })
  const res = await apiFetch<{ data: OJTLog[] }>(`/ojt-logs${qs}`)
  return (res.data ?? []).sort(compareLogs)
}

export async function updateOJTLog(logId: number, payload: OJTCreateLogPayload): Promise<void> {
  await apiFetch(`/ojt-logs/${logId}`, {
    method: 'PATCH',
    body: JSON.stringify({
      date: payload.date,
      timeIn: payload.timeIn,
      timeOut: payload.timeOut,
      tasksDone: payload.tasksDone,
      mood: payload.mood,
    }),
  })
}

export async function deleteOJTLog(logId: number): Promise<void> {
  await apiFetch(`/ojt-logs/${logId}`, { method: 'DELETE' })
}

export async function listCompanyOJTLogs(status = 'pending'): Promise<OJTLog[]> {
  const qs = buildQuery({ status: status || undefined })
  const res = await apiFetch<{ data: OJTLog[] }>(`/ojt-logs${qs}`)
  return (res.data ?? []).sort(compareLogs)
}

export async function approveOJTLog(logId: number): Promise<void> {
  await apiFetch(`/ojt-logs/${logId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status: 'approved' }),
  })
}

export async function rejectOJTLog(logId: number, payload: OJTRejectPayload): Promise<void> {
  await apiFetch(`/ojt-logs/${logId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status: 'rejected', reason: payload.reason }),
  })
}

export async function fetchSchoolOJTSummaries(): Promise<InternOJTSummary[]> {
  const res = await apiFetch<{ data: InternOJTSummary[] }>('/ojt-logs/progress')
  return (res.data ?? [])
    .map((row) => ({
      ...row,
      avatarFallback: row.avatarFallback ?? initialsFromName(row.internName || 'Intern'),
    }))
    .sort((left, right) => left.internName.localeCompare(right.internName))
}

export async function exportSchoolOJTLogsCsv(): Promise<Blob> {
  const base = apiBase()
  const path = '/reports/export?type=ojt'
  const url = base ? `${base}${path}` : `/api${path}`
  const headers: HeadersInit = { Accept: 'text/csv' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(url, { headers })
  if (!response.ok) {
    throw new Error('Unable to export OJT logs.')
  }
  return response.blob()
}

export async function fetchAdminOJTLogs(filters: OJTAdminFilters = {}): Promise<OJTLog[]> {
  const qs = buildQuery({
    status: filters.status,
    date_from: filters.dateFrom,
    date_to: filters.dateTo,
  })
  const res = await apiFetch<{ data: OJTLog[] }>(`/ojt-logs${qs}`)
  return applyClientFilters(res.data ?? [], filters).sort(compareLogs)
}

export async function fetchAdminOJTAnalytics(): Promise<OJTAdminAnalytics> {
  const logs = await fetchAdminOJTLogs()
  return analyticsFromLogs(logs)
}

export async function fetchOJTProgress(): Promise<OJTProgress> {
  const res = await apiFetch<{ data: OJTProgress }>('/ojt-logs/progress')
  const data = res.data
  if (data && 'totalLogged' in data) {
    return data
  }

  return {
    totalLogged: 0,
    totalApproved: 0,
    totalPending: 0,
    totalRejected: 0,
    requiredHours: 500,
    percentComplete: 0,
  }
}

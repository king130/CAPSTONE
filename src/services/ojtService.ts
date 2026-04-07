import { fetchUserProfile, type UserProfile } from '@/services/auth'

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
}

export interface OJTRejectPayload {
  reason: string
}

interface StoredOJTLog extends OJTLog {
  internUid: string
}

interface StoredAdminSettings {
  requiredHours: string
}

const OJT_STORAGE_KEY = 'capstone.ojt.logs'
const ADMIN_SYSTEM_SETTINGS_KEY = 'capstone.admin-system-settings'

function readStorageValue<T>(key: string, fallback: T): T {
  if (typeof window === 'undefined') return fallback

  const raw = window.localStorage.getItem(key)
  if (!raw) return fallback

  try {
    return JSON.parse(raw) as T
  } catch {
    return fallback
  }
}

function writeStorageValue<T>(key: string, value: T) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(key, JSON.stringify(value))
}

function readLogs(): StoredOJTLog[] {
  return readStorageValue<StoredOJTLog[]>(OJT_STORAGE_KEY, [])
}

function writeLogs(logs: StoredOJTLog[]) {
  writeStorageValue(OJT_STORAGE_KEY, logs)
}

function getRequiredHours(): number {
  const settings = readStorageValue<StoredAdminSettings>(ADMIN_SYSTEM_SETTINGS_KEY, { requiredHours: '500' })
  return Number(settings.requiredHours || '500') || 500
}

function stringHashToNumber(value: string): number {
  return value.split('').reduce((hash, character) => {
    return (hash * 31 + character.charCodeAt(0)) >>> 0
  }, 7)
}

function hoursBetween(timeIn: string, timeOut: string): number {
  const [inHours = 0, inMinutes = 0] = timeIn.split(':').map(Number)
  const [outHours = 0, outMinutes = 0] = timeOut.split(':').map(Number)
  return Math.max(0, (outHours * 60 + outMinutes - (inHours * 60 + inMinutes)) / 60)
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

function getProfileRecord(user: UserProfile | null): Record<string, unknown> {
  return (user?.profile as Record<string, unknown> | undefined) ?? {}
}

function resolveSchoolName(user: UserProfile | null): string {
  const profile = getProfileRecord(user)
  return String(profile.schoolName ?? profile.institutionName ?? user?.activeOrganization?.name ?? '')
}

function resolveCompanyName(user: UserProfile | null): string {
  const profile = getProfileRecord(user)
  return String(profile.companyName ?? user?.activeOrganization?.name ?? '')
}

function compareLogs(left: OJTLog, right: OJTLog): number {
  const leftKey = `${left.date}T${left.timeIn || '00:00'}`
  const rightKey = `${right.date}T${right.timeIn || '00:00'}`
  return rightKey.localeCompare(leftKey)
}

function applyLogQuery(logs: StoredOJTLog[], query: OJTLogQuery): StoredOJTLog[] {
  return logs.filter((log) => {
    if (query.internId !== undefined && log.internId !== query.internId) return false
    if (query.status && log.status !== query.status) return false
    if (query.dateFrom && log.date < query.dateFrom) return false
    if (query.dateTo && log.date > query.dateTo) return false
    return true
  })
}

async function requireUser(): Promise<UserProfile> {
  const user = await fetchUserProfile('')
  if (!user) {
    throw new Error('You need to be signed in to manage OJT logs.')
  }
  return user
}

function ensurePending(log: StoredOJTLog) {
  if (log.status !== 'pending') {
    throw new Error('Only pending logs can be updated.')
  }
}

function summarizeByIntern(logs: StoredOJTLog[]): InternOJTSummary[] {
  const requiredHours = getRequiredHours()
  const grouped = new Map<number, StoredOJTLog[]>()

  logs.forEach((log) => {
    const collection = grouped.get(log.internId) ?? []
    collection.push(log)
    grouped.set(log.internId, collection)
  })

  return Array.from(grouped.entries()).map(([internId, internLogs]) => {
    const approvedHours = internLogs
      .filter((log) => log.status === 'approved')
      .reduce((sum, log) => sum + log.hoursRendered, 0)
    const totalLoggedHours = internLogs.reduce((sum, log) => sum + log.hoursRendered, 0)
    const percent = requiredHours > 0 ? (approvedHours / requiredHours) * 100 : 0
    const status: InternOJTSummary['status'] =
      approvedHours >= requiredHours && requiredHours > 0
        ? 'completed'
        : approvedHours <= 0 && totalLoggedHours <= 0
          ? 'not_started'
          : percent >= 60
            ? 'on_track'
            : 'at_risk'

    const latestLog = [...internLogs].sort(compareLogs)[0]
    return {
      internId,
      internName: latestLog?.internName ?? 'Intern',
      company: latestLog?.company ?? '',
      school: latestLog?.school ?? '',
      approvedHours,
      requiredHours,
      status,
      avatarFallback: initialsFromName(latestLog?.internName ?? 'Intern'),
      totalLoggedHours,
    }
  })
}

function analyticsFromLogs(logs: StoredOJTLog[]): OJTAdminAnalytics {
  const weeklyHours = new Map<string, number>()
  const bySchool = new Map<string, { approved: number; required: number }>()
  const requiredHours = getRequiredHours()

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

function applyAdminFilters(logs: StoredOJTLog[], filters: OJTAdminFilters): StoredOJTLog[] {
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

export async function createOJTLog(payload: OJTCreateLogPayload): Promise<void> {
  const user = await requireUser()
  const logs = readLogs()
  const profile = getProfileRecord(user)
  const nextLog: StoredOJTLog = {
    id: Date.now(),
    internId: stringHashToNumber(user.uid),
    internUid: user.uid,
    internName: user.displayName || user.email,
    date: payload.date,
    timeIn: payload.timeIn,
    timeOut: payload.timeOut,
    hoursRendered: Number(hoursBetween(payload.timeIn, payload.timeOut).toFixed(2)),
    tasksDone: payload.tasksDone,
    mood: payload.mood,
    status: 'pending',
    createdAt: new Date().toISOString(),
    submittedAt: new Date().toISOString(),
    company: String(profile.companyName ?? ''),
    school: resolveSchoolName(user),
  }

  writeLogs([...logs, nextLog])
}

export async function listInternOJTLogs(query: OJTLogQuery = {}): Promise<OJTLog[]> {
  const user = await requireUser()
  const allLogs = readLogs()

  const filtered = user.role === 'student' && query.internId === undefined
    ? allLogs.filter((log) => log.internUid === user.uid)
    : allLogs

  return applyLogQuery(filtered, query).sort(compareLogs)
}

export async function updateOJTLog(logId: number, payload: OJTCreateLogPayload): Promise<void> {
  const user = await requireUser()
  const logs = readLogs()

  const nextLogs = logs.map((log) => {
    if (log.id !== logId) return log
    if (log.internUid !== user.uid) {
      throw new Error('You can only edit your own OJT logs.')
    }
    ensurePending(log)
    return {
      ...log,
      date: payload.date,
      timeIn: payload.timeIn,
      timeOut: payload.timeOut,
      tasksDone: payload.tasksDone,
      mood: payload.mood,
      hoursRendered: Number(hoursBetween(payload.timeIn, payload.timeOut).toFixed(2)),
    }
  })

  writeLogs(nextLogs)
}

export async function deleteOJTLog(logId: number): Promise<void> {
  const user = await requireUser()
  const logs = readLogs()
  const target = logs.find((log) => log.id === logId)

  if (!target) return
  if (target.internUid !== user.uid) {
    throw new Error('You can only delete your own OJT logs.')
  }
  ensurePending(target)

  writeLogs(logs.filter((log) => log.id !== logId))
}

export async function listCompanyOJTLogs(status = 'pending'): Promise<OJTLog[]> {
  const logs = readLogs().sort(compareLogs)
  return status ? logs.filter((log) => log.status === status) : logs
}

export async function approveOJTLog(logId: number): Promise<void> {
  const logs = readLogs()
  writeLogs(
    logs.map((log) => (log.id === logId ? { ...log, status: 'approved', rejectionReason: undefined } : log)),
  )
}

export async function rejectOJTLog(logId: number, payload: OJTRejectPayload): Promise<void> {
  const logs = readLogs()
  writeLogs(
    logs.map((log) =>
      log.id === logId
        ? { ...log, status: 'rejected', rejectionReason: payload.reason }
        : log,
    ),
  )
}

export async function fetchSchoolOJTSummaries(): Promise<InternOJTSummary[]> {
  return summarizeByIntern(readLogs()).sort((left, right) => left.internName.localeCompare(right.internName))
}

export async function exportSchoolOJTLogsCsv(): Promise<Blob> {
  const rows = summarizeByIntern(readLogs())
  const csv = [
    ['Intern Name', 'Company', 'School', 'Approved Hours', 'Required Hours', 'Status'].join(','),
    ...rows.map((row) =>
      [
        row.internName,
        row.company,
        row.school,
        String(row.approvedHours),
        String(row.requiredHours),
        row.status,
      ]
        .map((value) => `"${String(value).replace(/"/g, '""')}"`)
        .join(','),
    ),
  ].join('\n')

  return new Blob([csv], { type: 'text/csv;charset=utf-8' })
}

export async function fetchAdminOJTLogs(filters: OJTAdminFilters = {}): Promise<OJTLog[]> {
  return applyAdminFilters(readLogs(), filters).sort(compareLogs)
}

export async function fetchAdminOJTAnalytics(): Promise<OJTAdminAnalytics> {
  return analyticsFromLogs(readLogs())
}

export async function fetchOJTProgress(): Promise<OJTProgress> {
  const user = await requireUser()
  const logs = readLogs().filter((log) => log.internUid === user.uid)
  const requiredHours = getRequiredHours()
  const totalLogged = logs.reduce((sum, log) => sum + log.hoursRendered, 0)
  const totalApproved = logs.filter((log) => log.status === 'approved').reduce((sum, log) => sum + log.hoursRendered, 0)
  const totalPending = logs.filter((log) => log.status === 'pending').reduce((sum, log) => sum + log.hoursRendered, 0)
  const totalRejected = logs.filter((log) => log.status === 'rejected').reduce((sum, log) => sum + log.hoursRendered, 0)

  return {
    totalLogged,
    totalApproved,
    totalPending,
    totalRejected,
    requiredHours,
    percentComplete: requiredHours > 0 ? Math.min(100, (totalApproved / requiredHours) * 100) : 0,
  }
}

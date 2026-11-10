import { apiFetch } from './http'

export interface OjtCalendarDay {
  date: string
  kind: string
  dayKey?: string
  scheduledHours: number
  holidayName?: string | null
  inApprovedDtrPeriod?: boolean
  hoursOverScheduled?: boolean
  absenceType?: string | null
  absenceReason?: string | null
  approvedMakeupHours?: number | null
}

export interface OjtCalendarSummary {
  requiredHours: number | null
  approvedHours: number
  remainingHours: number | null
  ojtStartDate: string | null
  ojtStartEditable: boolean
  projectedEndDate: string | null
  workingDaysInProjection: number | null
  completedWorkingDays: number
  dailyBreakHours: number
  breakAppliesAfterHours: number
  issues: string[]
  scheduleStatus?: string | null
  activeScheduleLabel?: string | null
}

export interface OjtCalendarState {
  applicationId: string
  month: string
  timezone: string
  summary: OjtCalendarSummary
  days: OjtCalendarDay[]
  legend: Record<string, string>
}

export async function fetchOjtCalendar(applicationId: string, month?: string): Promise<OjtCalendarState> {
  const qs = month ? `?month=${encodeURIComponent(month)}` : ''
  const res = await apiFetch<{ data: OjtCalendarState }>(`/applications/${applicationId}/calendar${qs}`)
  return res.data
}

export async function updateOjtStartDate(applicationId: string, ojtStartDate: string): Promise<OjtCalendarState> {
  const res = await apiFetch<{ data: OjtCalendarState }>(`/applications/${applicationId}/calendar/start-date`, {
    method: 'PATCH',
    body: JSON.stringify({ ojt_start_date: ojtStartDate }),
  })
  return res.data
}

export interface OjtAttendanceEvent {
  id: number
  applicationId: string
  studentId: number
  date: string
  type: 'absent_excused' | 'absent_unexcused' | string
  reason: string
  markedByUserId?: string
  markedByName?: string | null
}

export async function markOjtAbsence(
  applicationId: string,
  payload: { date: string; type: 'absent_excused' | 'absent_unexcused'; reason: string },
): Promise<OjtAttendanceEvent> {
  const res = await apiFetch<{ data: OjtAttendanceEvent }>(`/applications/${applicationId}/attendance`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return res.data
}

export async function clearOjtAbsence(applicationId: string, attendanceEventId: number): Promise<void> {
  await apiFetch(`/applications/${applicationId}/attendance/${attendanceEventId}`, {
    method: 'DELETE',
    body: JSON.stringify({}),
  })
}

export async function listOjtAbsences(applicationId: string): Promise<OjtAttendanceEvent[]> {
  const res = await apiFetch<{ data: OjtAttendanceEvent[] }>(`/applications/${applicationId}/attendance`)
  return res.data
}

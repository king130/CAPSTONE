import { apiFetch } from './http'

export interface OjtCalendarDay {
  date: string
  kind: string
  dayKey?: string
  scheduledHours: number
  holidayName?: string | null
  inApprovedDtrPeriod?: boolean
  hoursOverScheduled?: boolean
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

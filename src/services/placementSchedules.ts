import { apiFetch } from './http'
import type { ScheduleSlot } from '@/utils/scheduleSlots'

export interface PlacementScheduleRequestRecord {
  id: string
  applicationId: string
  requestedSlots: ScheduleSlot[]
  requestedLabel?: string
  reason: string
  status: 'requested' | 'approved' | 'declined' | string
  requestedByName?: string | null
  decidedByName?: string | null
  decidedByRole?: string | null
  decidedAt?: string | null
  decisionNote?: string | null
  createdAt?: string | null
}

export interface PlacementScheduleState {
  applicationId: string
  hasPlacementSchedule: boolean
  status?: string | null
  source?: string | null
  activeSlots?: ScheduleSlot[] | null
  activeLabel?: string | null
  postingSlots?: ScheduleSlot[]
  postingLabel?: string | null
  postingSet?: boolean
  openRequest?: PlacementScheduleRequestRecord | null
  history: PlacementScheduleRequestRecord[]
  maxDailyHours: number
}

export async function fetchPlacementSchedule(applicationId: string): Promise<PlacementScheduleState> {
  const res = await apiFetch<{ data: PlacementScheduleState }>(`/applications/${applicationId}/schedule`)
  return res.data
}

export async function requestPlacementScheduleChange(
  applicationId: string,
  slots: ScheduleSlot[],
  reason: string,
): Promise<{ schedule: PlacementScheduleState }> {
  const res = await apiFetch<{ data: { schedule: PlacementScheduleState } }>(
    `/applications/${applicationId}/schedule/request`,
    {
      method: 'POST',
      body: JSON.stringify({ slots, reason }),
    },
  )
  return res.data
}

export async function decidePlacementScheduleRequest(
  applicationId: string,
  requestId: string,
  decision: 'approved' | 'declined',
  note?: string,
): Promise<{ schedule: PlacementScheduleState }> {
  const res = await apiFetch<{ data: { schedule: PlacementScheduleState } }>(
    `/applications/${applicationId}/schedule/requests/${requestId}/decide`,
    {
      method: 'POST',
      body: JSON.stringify({ decision, note }),
    },
  )
  return res.data
}

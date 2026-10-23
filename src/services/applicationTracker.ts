import { apiFetch } from './http'

export type TrackerStage =
  | 'applied'
  | 'endorsed'
  | 'interview_scheduled'
  | 'interviewed'
  | 'assessed'
  | 'accepted'
  | 'rejected'
  | 'school_rejected'

export interface TrackerHistoryEvent {
  id: string
  fromStage: string | null
  toStage: string
  changedByUserId: string | null
  changedByName?: string | null
  changedByRole?: string | null
  organizationId?: string | null
  organizationName?: string | null
  reason?: string | null
  isRollback: boolean
  createdAt?: string | null
}

export interface TrackerState {
  applicationId: string
  trackerStage: TrackerStage | string
  legacyStatus: string
  allowedNext: string[]
  allowedRollback: string[]
  history: TrackerHistoryEvent[]
  assessmentLocked: boolean
  viewerRole?: string | null
}

export interface TrackerAssessmentPayload {
  technical: number
  communication: number
  attitude: number
  comments?: string
}

export async function fetchApplicationTracker(applicationId: string): Promise<TrackerState> {
  const res = await apiFetch<{ data: TrackerState }>(`/applications/${applicationId}/tracker`)
  return res.data
}

export async function transitionApplicationStage(
  applicationId: string,
  toStage: string,
  options?: { reason?: string; rollback?: boolean },
): Promise<TrackerState> {
  const res = await apiFetch<{ data: TrackerState }>(`/applications/${applicationId}/tracker/transition`, {
    method: 'POST',
    body: JSON.stringify({
      to_stage: toStage,
      reason: options?.reason,
      rollback: options?.rollback ?? false,
    }),
  })
  return res.data
}

export async function submitTrackerAssessment(
  applicationId: string,
  payload: TrackerAssessmentPayload,
): Promise<{ tracker?: TrackerState }> {
  const res = await apiFetch<{ data: { tracker?: TrackerState } }>(
    `/applications/${applicationId}/tracker/assessment`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )
  return res.data
}

export async function unlockTrackerAssessment(
  applicationId: string,
  assessmentId: string,
  reason: string,
): Promise<{ tracker?: TrackerState }> {
  const res = await apiFetch<{ data: { tracker?: TrackerState } }>(
    `/applications/${applicationId}/tracker/assessments/${assessmentId}/unlock`,
    {
      method: 'POST',
      body: JSON.stringify({ reason }),
    },
  )
  return res.data
}

export const TRACKER_STAGE_LABELS: Record<string, string> = {
  applied: 'Applied',
  endorsed: 'Endorsed',
  interview_scheduled: 'Interview Scheduled',
  interviewed: 'Interviewed',
  assessed: 'Assessed',
  accepted: 'Accepted',
  rejected: 'Rejected',
  school_rejected: 'Not Endorsed',
}

export const TRACKER_FLOW: TrackerStage[] = [
  'applied',
  'endorsed',
  'interview_scheduled',
  'interviewed',
  'assessed',
  'accepted',
]

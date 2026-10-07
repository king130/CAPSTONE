import { apiFetch } from './http'

export interface InterviewRecord {
  id: string
  applicationId: string
  scheduledAt: string
  durationMinutes: number
  mode: 'onsite' | 'online'
  locationOrLink?: string | null
  status: 'proposed' | 'confirmed' | 'completed' | 'cancelled'
  notes?: string | null
  createdByUserId: string
  application?: {
    id: string
    internshipTitle?: string
    studentName?: string
    studentEmail?: string
    status?: string
    companyName?: string
  } | null
  createdAt?: string
  updatedAt?: string
}

export async function listInterviews(applicationId?: string): Promise<InterviewRecord[]> {
  const qs = applicationId ? `?application_id=${encodeURIComponent(applicationId)}` : ''
  const res = await apiFetch<{ data: InterviewRecord[] }>(`/interviews${qs}`)
  return res.data ?? []
}

export async function proposeInterview(payload: {
  application_id: number | string
  scheduled_at: string
  duration_minutes?: number
  mode: 'onsite' | 'online'
  location_or_link?: string
  notes?: string
}): Promise<InterviewRecord> {
  const res = await apiFetch<{ data: InterviewRecord }>('/interviews', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return res.data
}

export async function confirmInterview(interviewId: string): Promise<InterviewRecord> {
  const res = await apiFetch<{ data: InterviewRecord }>(`/interviews/${interviewId}/confirm`, {
    method: 'PATCH',
  })
  return res.data
}

export async function cancelInterview(interviewId: string, notes?: string): Promise<InterviewRecord> {
  const res = await apiFetch<{ data: InterviewRecord }>(`/interviews/${interviewId}/cancel`, {
    method: 'PATCH',
    body: JSON.stringify({ notes }),
  })
  return res.data
}

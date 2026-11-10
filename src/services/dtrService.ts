import { apiBase, apiFetch, getToken } from '@/services/http'

export interface DtrPlacement {
  applicationId: number
  internId: number
  internName: string
  internshipTitle: string
  companyName?: string
}

export type DtrStatus = 'draft' | 'submitted' | 'approved' | 'rejected' | 'revision_required'

export type DtrReviewStage = 'host' | 'school' | null

export type DtrDisplayStatus = DtrStatus

export interface DtrSubmission {
  id: number
  applicationId: number
  internId: number
  internName: string
  company: string
  internshipTitle: string
  periodStart: string
  periodEnd: string
  submittedHours: number
  status: DtrStatus
  displayStatus: DtrDisplayStatus
  reviewStage?: DtrReviewStage
  revisionRequired?: boolean
  remarks?: string | null
  rejectionReason?: string | null
  fileName: string
  submittedBy?: string | null
  submittedAt?: string | null
  reviewedBy?: string | null
  reviewedAt?: string | null
  companyReviewedBy?: string | null
  companyReviewedAt?: string | null
  previousSubmissionId?: number | null
}

export interface DtrSubmitPayload {
  applicationId: number
  periodStart: string
  periodEnd: string
  submittedHours: number
  remarks?: string
  file: File
}

function formDataFor(payload: DtrSubmitPayload): FormData {
  const body = new FormData()
  body.set('application_id', String(payload.applicationId))
  body.set('period_start', payload.periodStart)
  body.set('period_end', payload.periodEnd)
  body.set('submitted_hours', String(payload.submittedHours))
  if (payload.remarks?.trim()) body.set('remarks', payload.remarks.trim())
  body.set('file', payload.file)
  return body
}

export async function listDtrPlacements(): Promise<DtrPlacement[]> {
  const res = await apiFetch<{ data: DtrPlacement[] }>('/ojt-dtrs/placements')
  return res.data ?? []
}

export async function listDtrSubmissions(status?: string): Promise<DtrSubmission[]> {
  const query = status ? `?status=${encodeURIComponent(status)}` : ''
  const res = await apiFetch<{ data: DtrSubmission[] }>(`/ojt-dtrs${query}`)
  return res.data ?? []
}

export async function submitDtr(payload: DtrSubmitPayload): Promise<DtrSubmission> {
  const res = await apiFetch<{ data: DtrSubmission }>('/ojt-dtrs', {
    method: 'POST',
    body: formDataFor(payload),
  })
  return res.data
}

export async function resubmitDtr(id: number, payload: DtrSubmitPayload): Promise<DtrSubmission> {
  const res = await apiFetch<{ data: DtrSubmission }>(`/ojt-dtrs/${id}/resubmit`, {
    method: 'POST',
    body: formDataFor(payload),
  })
  return res.data
}

export async function approveDtr(id: number): Promise<DtrSubmission> {
  const res = await apiFetch<{ data: DtrSubmission }>(`/ojt-dtrs/${id}/approve`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
  return res.data
}

export async function deleteDtr(id: number): Promise<void> {
  await apiFetch(`/ojt-dtrs/${id}`, { method: 'DELETE' })
}

export function isPendingDtr(status: string): boolean {
  return status === 'draft'
    || status === 'submitted'
    || status === 'pending'
    || status === 'pending_company'
    || status === 'pending_coordinator'
}

export async function rejectDtr(id: number, reason: string, revisionRequired = false): Promise<DtrSubmission> {
  const res = await apiFetch<{ data: DtrSubmission }>(`/ojt-dtrs/${id}/reject`, {
    method: 'POST',
    body: JSON.stringify({ reason, revision_required: revisionRequired }),
  })
  return res.data
}

export async function downloadDtrFile(id: number, fileName: string): Promise<void> {
  const base = apiBase()
  const path = `/ojt-dtrs/${id}/file`
  const url = base ? `${base}${path}` : `/api${path}`
  const headers: HeadersInit = {}
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(url, { headers })
  if (!response.ok) {
    throw new Error('Unable to download the DTR file.')
  }
  const blob = await response.blob()
  const objectUrl = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = objectUrl
  anchor.download = fileName || 'dtr'
  anchor.click()
  URL.revokeObjectURL(objectUrl)
}

export function dtrStatusLabel(status: DtrDisplayStatus | DtrStatus | string): string {
  switch (status) {
    case 'draft':
      return 'Draft'
    case 'submitted':
    case 'pending':
    case 'pending_company':
    case 'pending_coordinator':
    case 'resubmitted':
      return 'Submitted'
    case 'approved':
      return 'Approved'
    case 'rejected':
    case 'revision_required':
      return 'Rejected'
    default:
      return 'Submitted'
  }
}

export function dtrRowStatus(row: { status: string; displayStatus?: string }): string {
  return row.displayStatus || row.status
}

export function dtrStatusDetail(row: { status: string; displayStatus?: string; reviewStage?: DtrReviewStage }): string {
  const status = dtrRowStatus(row)
  if (status !== 'submitted') return ''
  if (row.reviewStage === 'school' || row.status === 'pending_coordinator' || row.status === 'pending') {
    return 'Awaiting school review'
  }
  return 'Awaiting company review'
}

export function isAwaitingHost(row: { status: string; reviewStage?: DtrReviewStage }): boolean {
  return row.status === 'submitted' && row.reviewStage === 'host'
}

export function isAwaitingSchool(row: { status: string; reviewStage?: DtrReviewStage }): boolean {
  return row.status === 'submitted' && row.reviewStage === 'school'
}

export function isRejectedOrRevision(status: string): boolean {
  return status === 'rejected' || status === 'revision_required'
}

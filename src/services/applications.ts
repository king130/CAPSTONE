import { apiFetch, getToken } from './http'
import { createSharedPollingResource } from './sharedPolling'

export interface HoursAgreementRecord {
  id: string
  applicationId: string
  status: 'pending' | 'agreed' | 'declined' | string
  schoolRequiredHours: number
  hostOfferedHours: number
  proposedHours?: number | null
  agreedHours?: number | null
  proposedBySide?: string | null
  schoolConfirmed?: boolean
  hostConfirmed?: boolean
  note?: string | null
  hasApprovedDtr?: boolean
  history?: Array<{
    id: string
    action: string
    fromStatus?: string | null
    toStatus?: string | null
    proposedHours?: number | null
    agreedHours?: number | null
    changedByName?: string | null
    changedBySide?: string | null
    note?: string | null
    createdAt?: string | null
  }>
}

export interface ApplicationRecord {
  id: string
  internshipId: string
  studentId: string
  companyId?: string
  companyName?: string
  schoolName?: string
  hostType?: string | null
  hostName?: string | null
  internshipTitle?: string
  studentName?: string
  studentEmail?: string
  studentCourse?: string
  studentSkills?: Array<{
    id: string
    name: string
    category?: string
    status?: string
    level?: string
  }>
  status: string
  trackerStage?: string | null
  withdrawalReason?: string | null
  placementClosedAt?: string | null
  placementCloseReason?: string | null
  hasActivePlacement?: boolean
  resume?: string | null
  documents?: string[] | null
  documentsPending?: boolean
  requiredOjtHours?: number | null
  effectiveRequiredHours?: number | null
  hoursMismatch?: boolean
  schoolRequiredHours?: number | null
  hostRequiredHours?: number | null
  hoursAgreement?: HoursAgreementRecord | null
  createdAt?: string
  updatedAt?: string
}

export interface ActivePlacementSummary {
  applicationId: string
  internshipTitle: string
  hostName: string
  hostType?: string
  message: string
}

export async function fetchHoursAgreement(applicationId: string) {
  return apiFetch<{ data: {
    hoursMismatch: boolean
    schoolRequiredHours: number | null
    hostRequiredHours: number | null
    effectiveRequiredHours: number | null
    agreement: HoursAgreementRecord | null
  } }>(`/applications/${applicationId}/hours-agreement`)
}

export async function proposeHoursAgreement(
  applicationId: string,
  payload: { hours: number; source?: 'school' | 'host' | 'custom'; note?: string; reason?: string },
) {
  return apiFetch<{ data: { agreement: HoursAgreementRecord | null } }>(
    `/applications/${applicationId}/hours-agreement/propose`,
    { method: 'POST', body: JSON.stringify(payload) },
  )
}

export async function confirmHoursAgreement(applicationId: string, note?: string) {
  return apiFetch<{ data: { agreement: HoursAgreementRecord | null } }>(
    `/applications/${applicationId}/hours-agreement/confirm`,
    { method: 'POST', body: JSON.stringify({ note }) },
  )
}

export async function declineHoursAgreement(applicationId: string, note?: string) {
  return apiFetch<{ data: { agreement: HoursAgreementRecord | null } }>(
    `/applications/${applicationId}/hours-agreement/decline`,
    { method: 'POST', body: JSON.stringify({ note }) },
  )
}

let cachedActivePlacement: ActivePlacementSummary | null = null

export function getCachedActivePlacement(): ActivePlacementSummary | null {
  return cachedActivePlacement
}

export async function fetchActivePlacement(): Promise<ActivePlacementSummary | null> {
  if (!getToken()) {
    cachedActivePlacement = null
    return null
  }
  try {
    const res = await apiFetch<{ data: ActivePlacementSummary | null }>('/student/active-placement')
    cachedActivePlacement = res.data ?? null
    return cachedActivePlacement
  } catch {
    cachedActivePlacement = null
    return null
  }
}

export async function closePlacement(
  applicationId: string,
  reason: 'cancelled' | 'declined' | 'ended' | 'completed',
): Promise<ApplicationRecord> {
  const res = await apiFetch<{ data: ApplicationRecord }>(`/applications/${applicationId}/placement/close`, {
    method: 'POST',
    body: JSON.stringify({ reason }),
  })
  await applicationsResource.refresh()
  await fetchActivePlacement()
  return res.data
}

async function loadAllApplications(): Promise<ApplicationRecord[]> {
  if (!getToken()) return []
  const res = await apiFetch<{ data: ApplicationRecord[]; activePlacement?: ActivePlacementSummary | null }>('/applications')
  if (Object.prototype.hasOwnProperty.call(res, 'activePlacement')) {
    cachedActivePlacement = res.activePlacement ?? null
  }
  return res.data ?? []
}

const applicationsResource = createSharedPollingResource<ApplicationRecord[]>({
  intervalMs: 12000,
  load: loadAllApplications,
  initialValue: [],
})

export function subscribeAllApplications(callback: (items: ApplicationRecord[]) => void): () => void {
  return applicationsResource.subscribe(callback)
}

export function subscribeApplications(
  userId: string,
  callback: (items: ApplicationRecord[]) => void
): () => void {
  return subscribeAllApplications((items) => {
    callback(items.filter((application) => application.studentId === userId))
  })
}

export async function listApplications(): Promise<ApplicationRecord[]> {
  await applicationsResource.refresh()
  return applicationsResource.getSnapshot()
}

export function subscribeCompanyApplications(
  companyId: string,
  callback: (items: ApplicationRecord[]) => void,
  onError?: (err: Error) => void
): () => void {
  return subscribeAllApplications((items) => {
    try {
      if (!getToken()) {
        callback([])
        return
      }

      callback(items.filter((application) => application.companyId === companyId))
    } catch (err) {
      onError?.(err instanceof Error ? err : new Error('applications'))
      callback([])
    }
  })
}

export function subscribeCompanyApplicationsByInternships(
  internshipIds: string[],
  callback: (items: ApplicationRecord[]) => void,
  onError?: (err: Error) => void
): () => void {
  if (internshipIds.length === 0) {
    callback([])
    return () => {}
  }
  const set = new Set(internshipIds.map(String))
  return subscribeAllApplications((items) => {
    try {
      if (!getToken()) {
        callback([])
        return
      }

      callback(items.filter((application) => set.has(String(application.internshipId))))
    } catch (err) {
      onError?.(err instanceof Error ? err : new Error('applications'))
      callback([])
    }
  })
}

export async function submitApplication(
  payload: Omit<ApplicationRecord, 'id'> & { endorsementUrl?: string | null },
): Promise<string> {
  const res = await apiFetch<{ data: { id: string } }>('/applications', {
    method: 'POST',
    body: JSON.stringify({
      internship_id: Number(payload.internshipId),
      resume_url: payload.resume ?? undefined,
      endorsement_url: payload.endorsementUrl ?? undefined,
      documents: payload.documents ?? undefined,
    }),
  })
  await applicationsResource.refresh()
  return res.data?.id ?? ''
}

export async function updateApplicationStatus(applicationId: string, status: string): Promise<void> {
  await apiFetch(`/applications/${applicationId}/status`, {
    method: 'PATCH',
    body: JSON.stringify({ status }),
  })
  await applicationsResource.refresh()
}

export async function updateApplicationDocuments(
  _applicationId: string,
  _updates: { resume?: string | null; documents?: string[] | null; documentsPending?: boolean }
): Promise<void> {
  // Extend API with PATCH /applications/{id} when document uploads are wired to storage.
}

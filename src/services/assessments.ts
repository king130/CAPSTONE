import { apiFetch } from './http'

export interface AssessmentRecord {
  id: string
  applicationId: string
  assessedByUserId: string
  assessedByName?: string
  stage: string
  rubricScores: Record<string, unknown> | unknown[]
  overallScore: number | null
  comments?: string | null
  application?: {
    id: string
    internshipTitle?: string
    studentName?: string
    status?: string
    companyName?: string
  } | null
  createdAt?: string
  updatedAt?: string
}

export async function listAssessments(applicationId?: string): Promise<AssessmentRecord[]> {
  const qs = applicationId ? `?application_id=${encodeURIComponent(applicationId)}` : ''
  const res = await apiFetch<{ data: AssessmentRecord[] }>(`/assessments${qs}`)
  return res.data ?? []
}

export async function createAssessment(payload: {
  application_id: number | string
  stage?: string
  rubric_scores?: Record<string, unknown>
  overall_score?: number
  comments?: string
}): Promise<AssessmentRecord> {
  const res = await apiFetch<{ data: AssessmentRecord }>('/assessments', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return res.data
}

import { apiBase, apiFetch, getToken } from './http'

export interface KpiReport {
  placementRate: number
  applicationCountsByStatus: Record<string, number>
  totalApplications: number
  acceptedApplications: number
  agreementCountsByStatus: Record<string, number>
  totalAgreements: number
  avgApprovedOjtHours: number
  avgAssessmentScore: number
  studentCount: number
  scopedRole?: string | null
}

export interface StudentProgressRow {
  studentId: string
  userId?: string | null
  name: string
  email?: string
  course?: string
  schoolName?: string
  applicationStatus?: string
  internshipTitle?: string
  approvedOjtHours: number
  pendingOjtHours: number
  requiredOjtHours: number
  ojtPercentComplete: number
  latestAssessment?: {
    id: string
    stage: string
    overallScore: number | null
    comments?: string | null
    createdAt?: string
  } | null
}

export async function fetchKpiReport(): Promise<KpiReport> {
  const res = await apiFetch<{ data: KpiReport }>('/reports/kpi')
  return res.data
}

export async function fetchStudentProgress(): Promise<StudentProgressRow[]> {
  const res = await apiFetch<{ data: StudentProgressRow[] }>('/student-progress')
  return res.data ?? []
}

export async function exportReport(type: 'placements' | 'ojt' | 'assessments'): Promise<Blob> {
  const base = apiBase()
  const path = `/reports/export?type=${encodeURIComponent(type)}`
  const url = base ? `${base}${path}` : `/api${path}`
  const headers: HeadersInit = { Accept: 'text/csv' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(url, { headers })
  if (!response.ok) {
    throw new Error('Unable to export report.')
  }
  return response.blob()
}

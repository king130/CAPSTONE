import { apiFetch } from './http'

export type ReportStatus = 'draft' | 'pending' | 'revision_requested' | 'approved'

export interface ReportRecord {
  id: string
  schoolId: string
  reportType: string
  studentName: string
  internshipPeriodStart: string
  internshipPeriodEnd: string
  companyName: string
  companyStatus: ReportStatus
  schoolStatus: ReportStatus | null
  submittedAt?: string
  createdAt: string
  updatedAt: string
}

export async function listSchoolReports(schoolId: string): Promise<ReportRecord[]> {
  const response = await apiFetch<{ data?: ReportRecord[] }>('/school-reports')
  return (response.data ?? []).filter((report) => report.schoolId === schoolId)
}

export async function saveSchoolReport(payload: {
  schoolId: string
  reportType: string
  studentName: string
  internshipPeriodStart: string
  internshipPeriodEnd: string
  companyName: string
  status: ReportStatus
}): Promise<ReportRecord> {
  const response = await apiFetch<{ data: ReportRecord }>('/school-reports', {
    method: 'POST',
    body: JSON.stringify({
      reportType: payload.reportType,
      studentName: payload.studentName,
      internshipPeriodStart: payload.internshipPeriodStart,
      internshipPeriodEnd: payload.internshipPeriodEnd,
      companyName: payload.companyName,
      status: payload.status,
    }),
  })
  return response.data
}

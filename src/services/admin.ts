import apiClient from '@/services/apiClient'

export type VerificationStatus = 'pending' | 'approved' | 'rejected'

export interface AdminUserRecord {
  uid: string
  email?: string
  displayName?: string
  role?: string | null
  isTemporary?: boolean
  isActive?: boolean
  createdAt?: string
  profile?: Record<string, unknown>
  student?: {
    id: string
    schoolName?: string | null
    course?: string | null
    yearLevel?: string | null
  } | null
  school?: {
    id: string
    name?: string | null
    address?: string | null
    contactPerson?: string | null
    verificationStatus?: VerificationStatus | null
  } | null
  company?: {
    id: string
    name?: string | null
    industry?: string | null
    address?: string | null
    verificationStatus?: VerificationStatus | null
  } | null
}

export interface AdminApplicationRecord {
  id: string
  studentId: string
  companyId: string
  studentName?: string
  studentEmail?: string
  studentCourse?: string
  schoolName?: string
  companyName?: string
  status?: string
  internshipTitle?: string
  createdAt?: string
}

export async function fetchAdminUsers() {
  const { data } = await apiClient.get<{ data: AdminUserRecord[] }>('/admin/users')
  return data.data ?? []
}

export async function updateAdminUser(
  userId: string,
  payload: Partial<{ role: string; isActive: boolean; profileSetupComplete: boolean; verificationStatus: VerificationStatus }>,
) {
  const { data } = await apiClient.patch(`/admin/users/${userId}`, payload)
  return data
}

export async function fetchAdminApplications() {
  const { data } = await apiClient.get<{ data: AdminApplicationRecord[] }>('/applications')
  return data.data ?? []
}

export async function updateAdminApplicationStatus(applicationId: string, status: string) {
  const { data } = await apiClient.patch(`/applications/${applicationId}/status`, { status })
  return data
}

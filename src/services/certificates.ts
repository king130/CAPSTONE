import { apiBase, apiFetch, getToken } from './http'

export interface CertificateRecord {
  id: string
  studentId: string
  applicationId?: string | null
  organizationId?: string | null
  certificateNumber: string
  issuedByUserId: string
  issuedByName?: string
  issuedAt?: string
  filePath?: string | null
  metadata?: Record<string, unknown>
  studentName?: string | null
  internshipTitle?: string | null
  createdAt?: string
}

export async function listCertificates(): Promise<CertificateRecord[]> {
  const res = await apiFetch<{ data: CertificateRecord[] }>('/certificates')
  return res.data ?? []
}

export async function generateCertificate(applicationId: number | string): Promise<CertificateRecord> {
  const res = await apiFetch<{ data: CertificateRecord }>('/certificates/generate', {
    method: 'POST',
    body: JSON.stringify({ application_id: applicationId }),
  })
  return res.data
}

export async function downloadCertificate(certificateId: string): Promise<Blob> {
  const base = apiBase()
  const path = `/certificates/${certificateId}/download`
  const url = base ? `${base}${path}` : `/api${path}`
  const headers: HeadersInit = { Accept: 'text/html' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(url, { headers })
  if (!response.ok) {
    throw new Error('Unable to download certificate.')
  }
  return response.blob()
}

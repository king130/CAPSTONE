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

export interface CertificateEligibility {
  applicationId: string
  studentId: string
  studentName: string
  internshipTitle: string
  hostName: string
  approvedHours: number
  requiredHours: number
  eligible: boolean
  alreadyIssued: boolean
  certificateId?: string | null
  skipReason?: string | null
}

export interface BulkCertificateResult {
  issued: CertificateRecord[]
  skipped: Array<{ applicationId: string; reason: string; certificateId?: string | null }>
  failed: Array<{ applicationId: string; reason: string }>
}

export async function listCertificateEligible(): Promise<CertificateEligibility[]> {
  const res = await apiFetch<{ data: CertificateEligibility[] }>('/certificates/eligible')
  return res.data ?? []
}

export async function generateCertificate(applicationId: number | string): Promise<CertificateRecord> {
  const res = await apiFetch<{ data: CertificateRecord }>('/certificates/generate', {
    method: 'POST',
    body: JSON.stringify({ application_id: applicationId }),
  })
  return res.data
}

export async function bulkGenerateCertificates(applicationIds: Array<number | string>): Promise<BulkCertificateResult> {
  const res = await apiFetch<{ data: BulkCertificateResult }>('/certificates/bulk-generate', {
    method: 'POST',
    body: JSON.stringify({
      application_ids: applicationIds.map((id) => Number(id)),
    }),
  })
  return res.data ?? { issued: [], skipped: [], failed: [] }
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

async function openCertificateWindow(certificateId: string, autoPrint: boolean): Promise<void> {
  const blob = await downloadCertificate(certificateId)
  const htmlBlob = blob.type.includes('html') ? blob : new Blob([blob], { type: 'text/html;charset=UTF-8' })
  const objectUrl = URL.createObjectURL(htmlBlob)
  const opened = window.open(objectUrl, '_blank', 'noopener,noreferrer')
  if (!opened) {
    URL.revokeObjectURL(objectUrl)
    throw new Error('Unable to open certificate. Allow pop-ups and try again.')
  }

  if (!autoPrint) {
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
    return
  }

  let printed = false
  const triggerPrint = () => {
    if (printed || opened.closed) return
    printed = true
    try {
      opened.focus()
      opened.print()
    } finally {
      window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
    }
  }

  opened.addEventListener('load', triggerPrint, { once: true })
  // Fallback if load already fired for blob URLs in some browsers.
  window.setTimeout(triggerPrint, 500)
}

/** Open the printable HTML certificate in a new tab (authenticated fetch; not a public URL). */
export async function viewCertificateHtml(certificateId: string): Promise<void> {
  await openCertificateWindow(certificateId, false)
}

/** Open the printable HTML certificate and invoke the browser print dialog (Print / Save as PDF). */
export async function printCertificateHtml(certificateId: string): Promise<void> {
  await openCertificateWindow(certificateId, true)
}

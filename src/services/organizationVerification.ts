import { apiBase, apiFetch, getToken } from '@/services/http'

export type OrgVerificationStatus = 'pending' | 'under_review' | 'approved' | 'rejected' | string

export type VerificationDocumentType = string

export type ChecklistStatus = 'missing' | 'uploaded' | 'under_review' | 'approved' | 'needs_fix'

export interface OrganizationVerificationDocument {
  id: string
  organizationId: string
  documentType: VerificationDocumentType
  documentTypeLabel?: string
  fileName: string
  fileMime?: string | null
  fileSize: number
  status: 'pending' | 'approved' | 'rejected' | 'needs_fix' | string
  rejectionReason?: string | null
  uploadedBy?: string | null
  uploadedAt?: string | null
  reviewedBy?: string | null
  reviewedAt?: string | null
  history?: Array<{
    id: string
    fileName: string
    fileMime?: string | null
    fileSize: number
    status?: string | null
    rejectionReason?: string | null
    uploadedByUserId?: string | null
    uploadedAt?: string | null
  }>
}

export interface VerificationRequirement {
  type: string
  label: string
  required: boolean
  why?: string
  example?: string
}

export interface VerificationChecklistItem extends VerificationRequirement {
  checklistStatus: ChecklistStatus
  document: OrganizationVerificationDocument | null
}

export interface OrganizationVerificationMeta {
  organizationId: string
  organizationType: string
  verificationStatus: OrgVerificationStatus
  allowedTypes: VerificationDocumentType[]
  requirements?: VerificationRequirement[]
  checklist?: VerificationChecklistItem[]
  requiredComplete?: boolean
  canMutateDocuments?: boolean
  limits?: {
    maxKilobytes: number
    acceptedExtensions: string[]
    acceptedMimes: string[]
  }
  documentsMissingNotice?: string | null
}

/** Client-side catalog used when API omits checklist (older deploys) or for copy helpers. */
const DOCUMENT_CATALOG: Record<
  string,
  { label: string; why: string; example: string; required?: boolean }
> = {
  school_recognition_permit: {
    label: 'School recognition or permit to operate',
    why: 'Shows the school is authorized to operate (CHED, DepEd, TESDA, or equivalent).',
    example: 'Example: ched_recognition_certificate.pdf',
  },
  school_authorization: {
    label: 'School authorization / identity proof',
    why: 'Shows this school account belongs to a real institution.',
    example: 'Example: school_authorization_letter.pdf',
  },
  business_registration: {
    label: 'Business registration (SEC or DTI)',
    why: 'Proves the company is a registered business that can host interns.',
    example: 'Example: dti_or_sec_registration.pdf',
  },
  mayors_business_permit: {
    label: "Mayor's / business permit",
    why: 'Shows the business is allowed to operate in its city or municipality.',
    example: 'Example: mayors_business_permit_2026.pdf',
  },
  authorization_letter: {
    label: 'Authorization letter or designation',
    why: 'Shows this person is allowed to manage OJT accounts for the organization.',
    example: 'Example: authorization_letter.pdf',
  },
  bir_certificate: {
    label: 'BIR certificate (optional)',
    why: 'Optional tax registration proof. Helps speed up review but is not required.',
    example: 'Example: bir_certificate_of_registration.pdf',
    required: false,
  },
  lgu_authorization_letter: {
    label: 'Letter of authorization from the local chief executive',
    why: 'Shows the municipal hall / LGU office approved this OJT hosting account.',
    example: 'Example: mayor_authorization_letter.pdf',
  },
  representative_government_id: {
    label: 'Government ID of the authorized representative',
    why: 'Confirms the person creating this account is a real authorized representative.',
    example: 'Example: representative_national_id.jpg',
  },
  official_office_proof: {
    label: 'Official proof of the office (letter or certificate)',
    why: 'Confirms this account belongs to a real municipal hall or LGU office.',
    example: 'Example: official_office_letterhead.pdf',
  },
  other_proof: {
    label: 'Other proof of legitimacy',
    why: 'Any additional document that proves your organization is legitimate.',
    example: 'Example: supporting_document.pdf',
    required: false,
  },
}

const FALLBACK_BY_ORG: Record<string, string[]> = {
  school: ['school_recognition_permit', 'representative_government_id', 'authorization_letter'],
  company: [
    'business_registration',
    'mayors_business_permit',
    'representative_government_id',
    'authorization_letter',
    'bir_certificate',
  ],
  municipal_hall: ['lgu_authorization_letter', 'representative_government_id', 'official_office_proof'],
}

export function documentTypeLabel(type: string, fallbackLabel?: string): string {
  if (fallbackLabel) return fallbackLabel
  return DOCUMENT_CATALOG[type]?.label || type
}

export function documentTypeWhy(type: string, fallback?: string): string {
  if (fallback) return fallback
  return DOCUMENT_CATALOG[type]?.why || 'Required for organization verification.'
}

export function documentTypeExample(type: string, fallback?: string): string {
  if (fallback) return fallback
  return DOCUMENT_CATALOG[type]?.example || 'Example: document.pdf'
}

/**
 * Normalize API meta into a full checklist. Handles older Hostinger responses that only
 * return allowedTypes (no checklist/requirements).
 */
export function normalizeVerificationChecklist(
  meta: OrganizationVerificationMeta | null | undefined,
  uploadedDocs: OrganizationVerificationDocument[] = [],
): VerificationChecklistItem[] {
  if (!meta) return []

  if (Array.isArray(meta.checklist) && meta.checklist.length > 0) {
    return meta.checklist.map((item) => ({
      ...item,
      label: item.label || documentTypeLabel(item.type),
      why: item.why || documentTypeWhy(item.type),
      example: item.example || documentTypeExample(item.type),
      required: item.required !== false,
      checklistStatus: item.checklistStatus || (item.document ? 'uploaded' : 'missing'),
      document: item.document ?? null,
    }))
  }

  const latestByType = new Map<string, OrganizationVerificationDocument>()
  for (const doc of uploadedDocs) {
    if (!latestByType.has(doc.documentType)) latestByType.set(doc.documentType, doc)
  }

  const fromRequirements = Array.isArray(meta.requirements) ? meta.requirements : []
  const typeList =
    fromRequirements.length > 0
      ? fromRequirements.map((r) => r.type)
      : Array.isArray(meta.allowedTypes) && meta.allowedTypes.length > 0
        ? meta.allowedTypes
        : FALLBACK_BY_ORG[String(meta.organizationType || '').toLowerCase()] || []

  return typeList.map((type) => {
    const req = fromRequirements.find((r) => r.type === type)
    const document = latestByType.get(type) || null
    const required =
      req?.required ??
      (DOCUMENT_CATALOG[type]?.required === false ? false : true)

    return {
      type,
      label: req?.label || documentTypeLabel(type),
      required,
      why: req?.why || documentTypeWhy(type),
      example: req?.example || documentTypeExample(type),
      checklistStatus: (document
        ? document.status === 'approved'
          ? 'approved'
          : document.status === 'needs_fix' || document.status === 'rejected'
            ? 'needs_fix'
            : 'uploaded'
        : 'missing') as ChecklistStatus,
      document,
    }
  })
}

export function checklistStatusLabel(status: ChecklistStatus | string): string {
  switch (String(status || '').toLowerCase()) {
    case 'uploaded':
      return 'Uploaded'
    case 'under_review':
      return 'Under review'
    case 'approved':
      return 'Approved'
    case 'needs_fix':
      return 'Needs fix'
    case 'missing':
    default:
      return 'Missing'
  }
}

export function organizationTypeLabel(type?: string | null): string {
  switch (String(type || '').toLowerCase()) {
    case 'school':
      return 'School'
    case 'company':
      return 'Company'
    case 'municipal_hall':
      return 'Municipal Hall'
    default:
      return 'Organization'
  }
}

export function verificationStatusLabel(status: string): string {
  switch (String(status || '').toLowerCase()) {
    case 'approved':
      return 'Verified'
    case 'under_review':
      return 'Under Review'
    case 'rejected':
      return 'Rejected'
    case 'pending':
    default:
      return 'Pending Verification'
  }
}

export async function listOrganizationVerificationDocuments(): Promise<{
  data: OrganizationVerificationDocument[]
  meta: OrganizationVerificationMeta
}> {
  return apiFetch<{ data: OrganizationVerificationDocument[]; meta: OrganizationVerificationMeta }>(
    '/organization-verification/documents',
  )
}

export async function uploadOrganizationVerificationDocument(
  documentType: string,
  file: File,
): Promise<OrganizationVerificationDocument> {
  const body = new FormData()
  body.set('document_type', documentType)
  body.set('file', file)
  const res = await apiFetch<{ data: OrganizationVerificationDocument }>('/organization-verification/documents', {
    method: 'POST',
    body,
  })
  return res.data
}

export async function resubmitOrganizationVerificationDocument(
  id: string,
  file: File,
  documentType?: string,
): Promise<OrganizationVerificationDocument> {
  const body = new FormData()
  body.set('file', file)
  if (documentType) body.set('document_type', documentType)
  const res = await apiFetch<{ data: OrganizationVerificationDocument }>(
    `/organization-verification/documents/${id}/resubmit`,
    { method: 'POST', body },
  )
  return res.data
}

export async function deleteOrganizationVerificationDocument(id: string): Promise<void> {
  await apiFetch(`/organization-verification/documents/${id}`, { method: 'DELETE' })
}

export async function submitOrganizationVerificationForReview(): Promise<{
  message: string
  meta?: { verificationStatus?: string }
}> {
  return apiFetch('/organization-verification/submit', { method: 'POST' })
}

export async function listAdminVerificationDocuments(userId: string): Promise<{
  data: OrganizationVerificationDocument[]
  meta?: { organizationType?: string; requirements?: VerificationRequirement[] }
}> {
  return apiFetch<{
    data: OrganizationVerificationDocument[]
    meta?: { organizationType?: string; requirements?: VerificationRequirement[] }
  }>(`/admin/users/${userId}/verification-documents`)
}

export async function reviewAdminVerificationDocument(
  id: string,
  status: 'approved' | 'rejected' | 'needs_fix',
  reason?: string,
): Promise<OrganizationVerificationDocument> {
  const res = await apiFetch<{ data: OrganizationVerificationDocument }>(`/admin/verification-documents/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ status, reason: reason || null }),
  })
  return res.data
}

export async function downloadOrganizationVerificationDocument(
  id: string,
  fileName: string,
  asAdmin = false,
): Promise<void> {
  const base = apiBase()
  const path = asAdmin
    ? `/admin/verification-documents/${id}/file`
    : `/organization-verification/documents/${id}/file`
  const url = base ? `${base}${path}` : `/api${path}`
  const headers: HeadersInit = {}
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(url, { headers })
  if (!response.ok) {
    throw new Error('Unable to download the verification document.')
  }
  const blob = await response.blob()
  const objectUrl = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = objectUrl
  anchor.download = fileName || 'verification-document'
  anchor.click()
  URL.revokeObjectURL(objectUrl)
}

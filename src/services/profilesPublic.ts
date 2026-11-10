import { apiFetch } from './http'

export type AgreementDirectoryStatus = 'pending' | 'active' | null

export interface PublicProfile {
  uid: string
  organizationId?: string
  displayName: string
  role: 'school' | 'company' | 'student'
  orgName?: string
  email?: string
  courses?: string[]
  verificationStatus?: string | null
  /** Relative to the authenticated school/company viewer when loaded from /directory. */
  agreementStatus?: AgreementDirectoryStatus
  agreementSelectable?: boolean
  agreementStatusLabel?: string | null
}

export function isOrganizationApproved(partner: PublicProfile): boolean {
  return String(partner.verificationStatus || '').toLowerCase() === 'approved'
}

export function isAgreementPartnerSelectable(partner: PublicProfile): boolean {
  if (!isOrganizationApproved(partner)) {
    return false
  }
  if (typeof partner.agreementSelectable === 'boolean') {
    return partner.agreementSelectable
  }
  return !['active', 'pending'].includes(String(partner.agreementStatus || ''))
}

/** Counterparties shown on New Agreement — approved orgs only (active/pending still listed but disabled). */
export function filterAgreementDirectoryPartners(partners: PublicProfile[]): PublicProfile[] {
  return partners.filter((partner) => isOrganizationApproved(partner))
}

/** Kept for compatibility; directory data lives on users in MySQL. */
export async function ensurePublicProfile(
  _uid: string,
  _data: {
    displayName: string
    role: string
    orgName?: string
    email?: string
    courses?: string[]
  }
): Promise<void> {
  // No separate public profile document — school/company rows expose directory data via API.
}

export async function getPublicProfile(uid: string): Promise<PublicProfile | null> {
  for (const role of ['company', 'school'] as const) {
    const list = await listPublicProfiles(role).catch(() => [])
    const hit = list.find((p) => p.uid === uid)
    if (hit) return hit
  }
  return null
}

export async function listPublicProfiles(role: 'school' | 'company'): Promise<PublicProfile[]> {
  const res = await apiFetch<{ data: Array<Record<string, unknown>> }>(`/directory/${role}`)
  return (res.data ?? []).map((row) => {
    const statusRaw = row.agreementStatus ?? row.agreement_status
    const status =
      statusRaw === 'active' || statusRaw === 'pending' ? (statusRaw as AgreementDirectoryStatus) : null
    const selectableRaw = row.agreementSelectable ?? row.agreement_selectable
    const labelRaw = row.agreementStatusLabel ?? row.agreement_status_label
    const verificationRaw = row.verificationStatus ?? row.verification_status
    const verificationStatus =
      typeof verificationRaw === 'string' && verificationRaw.trim()
        ? verificationRaw.trim().toLowerCase()
        : 'pending'

    const organizationIdRaw = row.organizationId ?? row.organization_id
    return {
      uid: String(row.uid ?? ''),
      organizationId:
        typeof organizationIdRaw === 'string' || typeof organizationIdRaw === 'number'
          ? String(organizationIdRaw)
          : undefined,
      displayName: String(row.displayName ?? row.display_name ?? ''),
      role: (row.role as PublicProfile['role']) ?? role,
      orgName: typeof row.orgName === 'string' ? row.orgName : typeof row.org_name === 'string' ? row.org_name : undefined,
      email: typeof row.email === 'string' ? row.email : undefined,
      courses: Array.isArray(row.courses) ? (row.courses as string[]) : undefined,
      verificationStatus,
      agreementStatus: status,
      agreementSelectable:
        typeof selectableRaw === 'boolean'
          ? selectableRaw
          : verificationStatus === 'approved' && status == null,
      agreementStatusLabel: typeof labelRaw === 'string' ? labelRaw : null,
    }
  })
}

export function subscribePublicProfiles(
  role: 'school' | 'company',
  callback: (profiles: PublicProfile[]) => void
): () => void {
  let cancelled = false
  async function load() {
    try {
      const list = await listPublicProfiles(role)
      if (!cancelled) callback(list)
    } catch {
      if (!cancelled) callback([])
    }
  }
  load()
  const id = window.setInterval(load, 20000)
  return () => {
    cancelled = true
    clearInterval(id)
  }
}

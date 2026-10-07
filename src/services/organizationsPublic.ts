import { apiFetch } from './http'
import { normalizeInternshipRecord, type InternshipRecord } from './internships'
import { listOrganizationMedia, type OrganizationMediaItem } from './organizationMedia'

export interface PublicOrganizationProfile {
  id: string
  name: string
  type: 'school' | 'company' | string
  verified: boolean
  tagline: string
  description: string
  address: string
  city: string
  website: string
  industry: string
  perks: string[]
  coverImage: string | null
  internships?: InternshipRecord[]
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {}
}

function asString(value: unknown, fallback = ''): string {
  return typeof value === 'string' ? value : fallback
}

function asBoolean(value: unknown): boolean {
  return value === true || value === 1 || value === '1' || value === 'true'
}

function mapPublicOrganization(raw: unknown): PublicOrganizationProfile {
  const row = asRecord(raw)
  const internshipsRaw = row.internships
  const internships = Array.isArray(internshipsRaw)
    ? internshipsRaw.map(normalizeInternshipRecord)
    : undefined

  return {
    id: asString(row.id),
    name: asString(row.name),
    type: asString(row.type),
    verified: asBoolean(row.verified),
    tagline: asString(row.tagline),
    description: asString(row.description),
    address: asString(row.address),
    city: asString(row.city),
    website: asString(row.website),
    industry: asString(row.industry),
    perks: Array.isArray(row.perks)
      ? row.perks.filter((item): item is string => typeof item === 'string' && item.trim() !== '')
      : [],
    coverImage: (() => {
      const value = row.coverImage ?? row.cover_image
      return typeof value === 'string' && value.trim() ? value : null
    })(),
    internships,
  }
}

export async function fetchPublicOrganization(organizationId: string): Promise<PublicOrganizationProfile> {
  const res = await apiFetch<{ data: unknown }>(`/organizations/${organizationId}`)
  return mapPublicOrganization(res.data)
}

export async function fetchOrganizationInternships(
  organizationId: string,
  params?: { page?: number; per_page?: number },
): Promise<{ data: InternshipRecord[]; total: number }> {
  const search = new URLSearchParams()
  if (params?.page) search.set('page', String(params.page))
  if (params?.per_page) search.set('per_page', String(params.per_page))
  const qs = search.toString()
  const res = await apiFetch<{ data: unknown[]; meta?: { total?: number } }>(
    `/organizations/${organizationId}/internships${qs ? `?${qs}` : ''}`,
  )
  return {
    data: (res.data ?? []).map(normalizeInternshipRecord),
    total: Number(res.meta?.total ?? res.data?.length ?? 0),
  }
}

export async function fetchOrganizationGallery(organizationId: string): Promise<OrganizationMediaItem[]> {
  return listOrganizationMedia(organizationId)
}

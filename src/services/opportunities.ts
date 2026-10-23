import { apiFetch } from './http'

export interface DiscoverableOrganization {
  id: string
  name: string
  type: 'company' | 'school' | string
  verified: boolean
  tagline: string
  industry: string
  address: string
  city: string
  logoUrl: string | null
  openOpportunities: number
}

export interface OrganizationDirectoryResult {
  data: DiscoverableOrganization[]
  total: number
  currentPage: number
  lastPage: number
  cities: string[]
  industries: string[]
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {}
}

function asString(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

export async function listDiscoverableOrganizations(params: {
  type: 'company' | 'school'
  q?: string
  city?: string
  industry?: string
  hasOpenings?: boolean
  page?: number
}): Promise<OrganizationDirectoryResult> {
  const search = new URLSearchParams()
  search.set('type', params.type)
  search.set('page', String(params.page ?? 1))
  search.set('per_page', '12')
  if (params.q?.trim()) search.set('q', params.q.trim())
  if (params.city?.trim()) search.set('city', params.city.trim())
  if (params.industry?.trim()) search.set('industry', params.industry.trim())
  if (params.hasOpenings) search.set('has_openings', '1')

  const res = await apiFetch<{
    data?: unknown[]
    meta?: { total?: number; current_page?: number; last_page?: number }
    facets?: { cities?: unknown[]; industries?: unknown[] }
  }>(`/organizations?${search.toString()}`)

  return {
    data: (res.data ?? []).map((item) => {
      const row = asRecord(item)
      const logo = row.logoUrl ?? row.logo_url
      return {
        id: asString(row.id),
        name: asString(row.name),
        type: asString(row.type),
        verified: row.verified === true,
        tagline: asString(row.tagline),
        industry: asString(row.industry),
        address: asString(row.address),
        city: asString(row.city),
        logoUrl: typeof logo === 'string' && logo.trim() ? logo : null,
        openOpportunities: Number(row.openOpportunities ?? row.open_opportunities ?? 0) || 0,
      }
    }),
    total: Number(res.meta?.total ?? 0),
    currentPage: Number(res.meta?.current_page ?? 1),
    lastPage: Number(res.meta?.last_page ?? 1),
    cities: (res.facets?.cities ?? []).filter((item): item is string => typeof item === 'string'),
    industries: (res.facets?.industries ?? []).filter((item): item is string => typeof item === 'string'),
  }
}

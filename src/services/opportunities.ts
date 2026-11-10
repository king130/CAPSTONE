import { apiFetch } from './http'

export type CourseAlignmentMode = 'aligned' | 'all'
export type OrganizationCourseAlignment = 'aligned' | 'none' | 'unspecified'

export interface DiscoverableOrganization {
  id: string
  name: string
  type: 'company' | 'school' | 'municipal_hall' | string
  verified: boolean
  tagline: string
  industry: string
  lguType: string
  address: string
  city: string
  logoUrl: string | null
  openOpportunities: number
  sharedCourses: string[]
  alignment: OrganizationCourseAlignment | null
  isPartnered: boolean
}

export interface OrganizationDirectoryResult {
  data: DiscoverableOrganization[]
  total: number
  currentPage: number
  lastPage: number
  cities: string[]
  industries: string[]
  lguTypes: string[]
  courseAlignment: CourseAlignmentMode
  viewerHasCourses: boolean
  alignedCount: number
  hiddenCount: number
  catalogTotal: number
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {}
}

function asString(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

function asAlignment(value: unknown): OrganizationCourseAlignment | null {
  if (value === 'aligned' || value === 'none' || value === 'unspecified') return value
  return null
}

function asStringList(value: unknown): string[] {
  if (!Array.isArray(value)) return []
  return value.filter((item): item is string => typeof item === 'string' && item.trim().length > 0)
}

export async function listDiscoverableOrganizations(params: {
  type: 'company' | 'school' | 'municipal_hall'
  q?: string
  city?: string
  industry?: string
  lguType?: string
  hasOpenings?: boolean
  sort?: 'name' | 'city' | 'industry' | 'openings' | 'lguType'
  page?: number
  perPage?: number
  courseAlignment?: CourseAlignmentMode
  signal?: AbortSignal
}): Promise<OrganizationDirectoryResult> {
  const search = new URLSearchParams()
  search.set('type', params.type)
  search.set('page', String(params.page ?? 1))
  search.set('per_page', String(params.perPage ?? 10))
  if (params.q?.trim()) search.set('q', params.q.trim())
  if (params.city?.trim()) search.set('city', params.city.trim())
  if (params.industry?.trim()) search.set('industry', params.industry.trim())
  if (params.lguType?.trim()) search.set('lgu_type', params.lguType.trim())
  if (params.hasOpenings) search.set('has_openings', '1')
  if (params.sort) search.set('sort', params.sort === 'lguType' ? 'lgu_type' : params.sort)
  if (params.courseAlignment) search.set('course_alignment', params.courseAlignment)

  const res = await apiFetch<{
    data?: unknown[]
    meta?: {
      total?: number
      current_page?: number
      last_page?: number
      course_alignment?: string
      viewer_has_courses?: boolean
      aligned?: number
      hidden_count?: number
      catalog_total?: number
    }
    facets?: { cities?: unknown[]; industries?: unknown[]; lgu_types?: unknown[]; lguTypes?: unknown[] }
  }>(`/organizations?${search.toString()}`, { signal: params.signal })

  const lguFacet = res.facets?.lgu_types ?? res.facets?.lguTypes ?? []
  const mode = res.meta?.course_alignment === 'aligned' ? 'aligned' : 'all'

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
        lguType: asString(row.lguType ?? row.lgu_type),
        address: asString(row.address),
        city: asString(row.city),
        logoUrl: typeof logo === 'string' && logo.trim() ? logo : null,
        openOpportunities: Number(row.openOpportunities ?? row.open_opportunities ?? 0) || 0,
        sharedCourses: asStringList(row.sharedCourses ?? row.shared_courses),
        alignment: asAlignment(row.alignment),
        isPartnered: row.isPartnered === true || row.is_partnered === true,
      }
    }),
    total: Number(res.meta?.total ?? 0),
    currentPage: Number(res.meta?.current_page ?? 1),
    lastPage: Number(res.meta?.last_page ?? 1),
    cities: (res.facets?.cities ?? []).filter((item): item is string => typeof item === 'string'),
    industries: (res.facets?.industries ?? []).filter((item): item is string => typeof item === 'string'),
    lguTypes: lguFacet.filter((item): item is string => typeof item === 'string'),
    courseAlignment: mode,
    viewerHasCourses: res.meta?.viewer_has_courses === true,
    alignedCount: Number(res.meta?.aligned ?? 0) || 0,
    hiddenCount: Number(res.meta?.hidden_count ?? 0) || 0,
    catalogTotal: Number(res.meta?.catalog_total ?? res.meta?.total ?? 0) || 0,
  }
}

import type { BrowseDirectoryConfig, BrowsePageKey } from './types'

/** Frozen per-page configs — mutate one object and the others stay untouched. */
export const companiesBrowseConfig: Readonly<BrowseDirectoryConfig> = Object.freeze({
  pageKey: 'companies-browse',
  orgType: 'company',
  title: 'Companies',
  description: 'Discover companies that publish OJT opportunities. Profiles show only public information.',
  searchPlaceholder: 'Search companies',
  emptyCopy: 'No companies are currently available to browse.',
  actionLabel: 'View company',
  showIndustryFilter: true,
  showOpeningsFilter: true,
  showLguFilter: false,
  defaultSort: 'name',
  sortOptions: [
    { value: 'name', label: 'Sort: Name' },
    { value: 'city', label: 'Sort: Location' },
    { value: 'industry', label: 'Sort: Industry' },
    { value: 'openings', label: 'Sort: Openings' },
  ],
  columns: ['name', 'industry', 'city', 'openings', 'action'],
  cardFields: ['city', 'industry', 'openings'],
  defaultPageSize: 10,
  pageSizeOptions: [5, 10, 25],
})

/** Intern-facing overlay: directory is school–partner hosts only. */
export const partnerCompaniesBrowseConfig: Readonly<BrowseDirectoryConfig> = Object.freeze({
  ...companiesBrowseConfig,
  title: 'Partner Companies',
  description:
    'Companies with an active agreement with your school. Open a profile to browse their internship postings and apply.',
  searchPlaceholder: 'Search partner companies',
  emptyCopy:
    'Your school has no active company partnerships available yet. Ask your school coordinator about agreements with host companies.',
  actionLabel: 'View partner',
})

export const schoolsBrowseConfig: Readonly<BrowseDirectoryConfig> = Object.freeze({
  pageKey: 'schools-browse',
  orgType: 'school',
  title: 'Schools',
  description: 'Browse schools that take part in OJT. Open a public profile to see published information.',
  searchPlaceholder: 'Search schools',
  emptyCopy: 'No schools are currently available to browse.',
  actionLabel: 'View school',
  showIndustryFilter: false,
  showOpeningsFilter: false,
  showLguFilter: false,
  defaultSort: 'name',
  sortOptions: [
    { value: 'name', label: 'Sort: Name' },
    { value: 'city', label: 'Sort: Location' },
  ],
  columns: ['name', 'city', 'action'],
  cardFields: ['tagline', 'city'],
  defaultPageSize: 10,
  pageSizeOptions: [5, 10, 25],
})

export const municipalBrowseConfig: Readonly<BrowseDirectoryConfig> = Object.freeze({
  pageKey: 'municipal-browse',
  orgType: 'municipal_hall',
  title: 'Municipal Halls',
  description: 'Discover municipal and LGU offices that host OJT. Profiles show only public information.',
  searchPlaceholder: 'Search municipal halls',
  emptyCopy: 'No municipal halls are currently available to browse.',
  actionLabel: 'View municipal hall',
  showIndustryFilter: false,
  showOpeningsFilter: true,
  showLguFilter: true,
  defaultSort: 'name',
  sortOptions: [
    { value: 'name', label: 'Sort: Name' },
    { value: 'city', label: 'Sort: Location' },
    { value: 'lguType', label: 'Sort: LGU type' },
    { value: 'openings', label: 'Sort: Openings' },
  ],
  columns: ['name', 'lguType', 'city', 'openings', 'action'],
  cardFields: ['city', 'lguType', 'openings'],
  defaultPageSize: 10,
  pageSizeOptions: [5, 10, 25],
})

export const partnerMunicipalBrowseConfig: Readonly<BrowseDirectoryConfig> = Object.freeze({
  ...municipalBrowseConfig,
  title: 'Partner Municipal Halls',
  description:
    'Municipal and LGU hosts with an active agreement with your school. Open a profile to see their internship postings.',
  searchPlaceholder: 'Search partner municipal halls',
  emptyCopy:
    'Your school has no active municipal hall partnerships available yet. Ask your school coordinator about agreements with LGU hosts.',
  actionLabel: 'View partner',
})

const byKey: Record<BrowsePageKey, Readonly<BrowseDirectoryConfig>> = {
  'companies-browse': companiesBrowseConfig,
  'schools-browse': schoolsBrowseConfig,
  'municipal-browse': municipalBrowseConfig,
}

const partnerByKey: Partial<Record<BrowsePageKey, Readonly<BrowseDirectoryConfig>>> = {
  'companies-browse': partnerCompaniesBrowseConfig,
  'municipal-browse': partnerMunicipalBrowseConfig,
}

export function browseConfigFor(
  pageKey: BrowsePageKey,
  options?: { partnerScoped?: boolean },
): Readonly<BrowseDirectoryConfig> {
  if (options?.partnerScoped && partnerByKey[pageKey]) {
    return partnerByKey[pageKey]!
  }
  return byKey[pageKey]
}

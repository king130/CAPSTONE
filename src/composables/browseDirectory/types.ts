export type BrowsePageKey = 'companies-browse' | 'schools-browse' | 'municipal-browse'

export type BrowseOrgType = 'company' | 'school' | 'municipal_hall'

export type BrowseSortKey = 'name' | 'city' | 'industry' | 'openings' | 'lguType'

export type BrowseViewMode = 'table' | 'cards'

export type BrowseColumnKey = 'name' | 'industry' | 'lguType' | 'city' | 'openings' | 'action'

export type BrowseCardFieldKey = 'tagline' | 'industry' | 'lguType' | 'city' | 'openings'

export interface BrowseDirectoryConfig {
  pageKey: BrowsePageKey
  orgType: BrowseOrgType
  title: string
  description: string
  searchPlaceholder: string
  emptyCopy: string
  actionLabel: string
  showIndustryFilter: boolean
  showOpeningsFilter: boolean
  showLguFilter: boolean
  defaultSort: BrowseSortKey
  sortOptions: Array<{ value: BrowseSortKey; label: string }>
  columns: BrowseColumnKey[]
  cardFields: BrowseCardFieldKey[]
  defaultPageSize: number
  pageSizeOptions: number[]
}

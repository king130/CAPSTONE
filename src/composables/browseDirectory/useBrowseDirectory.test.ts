import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/services/opportunities', () => ({
  listDiscoverableOrganizations: vi.fn(async ({ type }: { type: string }) => ({
    data: [{ id: `${type}-1`, name: type, type, verified: true, tagline: '', industry: '', lguType: '', address: '', city: 'Imus', logoUrl: null, openOpportunities: 0 }],
    total: 1,
    currentPage: 1,
    lastPage: 1,
    cities: ['Imus'],
    industries: [],
    lguTypes: [],
  })),
}))

import { listDiscoverableOrganizations } from '@/services/opportunities'
import {
  getBrowseDirectoryState,
  resetBrowseDirectoryCaches,
} from './useBrowseDirectory'

describe('browse directory isolation', () => {
  beforeEach(() => {
    resetBrowseDirectoryCaches()
    vi.mocked(listDiscoverableOrganizations).mockClear()
  })

  it('keeps separate state objects and cache keys per page', () => {
    const companies = getBrowseDirectoryState('companies-browse')
    const schools = getBrowseDirectoryState('schools-browse')
    const municipal = getBrowseDirectoryState('municipal-browse')

    companies.query = 'abc'
    companies.page = 2
    companies.viewMode = 'cards'
    companies.pageSize = 6

    expect(schools.query).toBe('')
    expect(schools.page).toBe(1)
    expect(schools.viewMode).toBe('table')
    expect(municipal.query).toBe('')
    expect(municipal.page).toBe(1)
    expect(getBrowseDirectoryState('companies-browse').query).toBe('abc')
    expect(getBrowseDirectoryState('companies-browse').page).toBe(2)
  })
})

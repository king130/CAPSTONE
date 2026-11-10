import { computed, onUnmounted, reactive, ref, watch, type Ref } from 'vue'

import {
  listDiscoverableOrganizations,
  type CourseAlignmentMode,
  type DiscoverableOrganization,
} from '@/services/opportunities'

import { browseConfigFor } from './configs'
import type { BrowsePageKey, BrowseSortKey, BrowseViewMode } from './types'

const COURSE_ALIGNMENT_SESSION_KEY = 'browse.courseAlignment.includeNonAligned'

export interface BrowseDirectoryState {
  query: string
  city: string
  industry: string
  lguType: string
  hasOpenings: boolean
  /** When true, request course_alignment=all (include orgs with no shared courses). */
  includeNonAligned: boolean
  sort: BrowseSortKey
  page: number
  pageSize: number
  viewMode: BrowseViewMode
  rows: DiscoverableOrganization[]
  total: number
  lastPage: number
  cities: string[]
  industries: string[]
  lguTypes: string[]
  courseAlignment: CourseAlignmentMode
  viewerHasCourses: boolean
  alignedCount: number
  hiddenCount: number
  catalogTotal: number
  loading: boolean
  errorMessage: string
  requestId: number
}

function readIncludeNonAligned(): boolean {
  if (typeof sessionStorage === 'undefined') return false
  try {
    return sessionStorage.getItem(COURSE_ALIGNMENT_SESSION_KEY) === '1'
  } catch {
    return false
  }
}

function writeIncludeNonAligned(value: boolean) {
  if (typeof sessionStorage === 'undefined') return
  try {
    sessionStorage.setItem(COURSE_ALIGNMENT_SESSION_KEY, value ? '1' : '0')
  } catch {
    /* ignore quota / private mode */
  }
}

/** Isolated cache — one entry per pageKey; never shared across pages. */
const stateByPageKey = new Map<BrowsePageKey, BrowseDirectoryState>()
const abortByPageKey = new Map<BrowsePageKey, AbortController>()

function preferredViewMode(): BrowseViewMode {
  if (typeof window === 'undefined') return 'cards'
  return window.matchMedia('(max-width: 767px)').matches ? 'cards' : 'table'
}

function createState(pageKey: BrowsePageKey): BrowseDirectoryState {
  const config = browseConfigFor(pageKey)
  return reactive({
    query: '',
    city: '',
    industry: '',
    lguType: '',
    hasOpenings: false,
    includeNonAligned: readIncludeNonAligned(),
    sort: config.defaultSort,
    page: 1,
    pageSize: config.defaultPageSize,
    viewMode: preferredViewMode(),
    rows: [],
    total: 0,
    lastPage: 1,
    cities: [],
    industries: [],
    lguTypes: [],
    courseAlignment: 'aligned',
    viewerHasCourses: false,
    alignedCount: 0,
    hiddenCount: 0,
    catalogTotal: 0,
    loading: false,
    errorMessage: '',
    requestId: 0,
  })
}

export function getBrowseDirectoryState(pageKey: BrowsePageKey): BrowseDirectoryState {
  let state = stateByPageKey.get(pageKey)
  if (!state) {
    state = createState(pageKey)
    stateByPageKey.set(pageKey, state)
  }
  return state
}

/** Test helper — clears all page caches. */
export function resetBrowseDirectoryCaches() {
  for (const controller of abortByPageKey.values()) controller.abort()
  abortByPageKey.clear()
  stateByPageKey.clear()
}

export function useBrowseDirectory(
  pageKey: Ref<BrowsePageKey> | BrowsePageKey,
  options?: { partnerScoped?: Ref<boolean> | boolean },
) {
  const pageKeyRef = typeof pageKey === 'string' ? ref(pageKey) : pageKey
  const partnerScopedRef =
    typeof options?.partnerScoped === 'boolean'
      ? ref(options.partnerScoped)
      : options?.partnerScoped ?? ref(false)
  const config = computed(() =>
    browseConfigFor(pageKeyRef.value, { partnerScoped: partnerScopedRef.value }),
  )
  const state = computed(() => getBrowseDirectoryState(pageKeyRef.value))

  let debounceTimer: ReturnType<typeof setTimeout> | undefined
  let mounted = true

  async function load() {
    const key = pageKeyRef.value
    const current = getBrowseDirectoryState(key)
    const cfg = browseConfigFor(key, { partnerScoped: partnerScopedRef.value })

    abortByPageKey.get(key)?.abort()
    const controller = new AbortController()
    abortByPageKey.set(key, controller)

    const requestId = current.requestId + 1
    current.requestId = requestId
    current.loading = true
    current.errorMessage = ''

    try {
      const result = await listDiscoverableOrganizations({
        type: cfg.orgType,
        q: current.query,
        city: current.city,
        industry: cfg.showIndustryFilter ? current.industry : '',
        lguType: cfg.showLguFilter ? current.lguType : '',
        hasOpenings: cfg.showOpeningsFilter ? current.hasOpenings : false,
        sort: current.sort,
        page: current.page,
        perPage: current.pageSize,
        courseAlignment: current.includeNonAligned ? 'all' : 'aligned',
        signal: controller.signal,
      })

      if (!mounted || current.requestId !== requestId || controller.signal.aborted) return

      current.rows = result.data
      current.total = result.total
      current.lastPage = result.lastPage
      current.courseAlignment = result.courseAlignment
      current.viewerHasCourses = result.viewerHasCourses
      current.alignedCount = result.alignedCount
      current.hiddenCount = result.hiddenCount
      current.catalogTotal = result.catalogTotal
      if (result.cities.length) current.cities = result.cities
      if (result.industries.length) current.industries = result.industries
      if (result.lguTypes.length) current.lguTypes = result.lguTypes
    } catch (err) {
      if (controller.signal.aborted || !mounted || current.requestId !== requestId) return
      current.rows = []
      current.errorMessage = err instanceof Error ? err.message : 'Unable to load organizations.'
    } finally {
      // Always clear loading for this request id so a remount cannot stick on skeletons
      // after an aborted/unmounted request (page-key cache is shared across visits).
      if (current.requestId === requestId) {
        current.loading = false
      }
    }
  }

  function changePage(next: number) {
    state.value.page = next
    void load()
  }

  function setSort(next: string) {
    const sort = next as BrowseSortKey
    if (state.value.sort === sort) return
    state.value.sort = sort
    state.value.page = 1
    void load()
  }

  function setViewMode(mode: BrowseViewMode) {
    state.value.viewMode = mode
  }

  function setPageSize(size: number) {
    if (state.value.pageSize === size) return
    state.value.pageSize = size
    state.value.page = 1
    void load()
  }

  function setIncludeNonAligned(next: boolean) {
    if (state.value.includeNonAligned === next) return
    state.value.includeNonAligned = next
    writeIncludeNonAligned(next)
    state.value.page = 1
    void load()
  }

  function scheduleSearchLoad() {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      state.value.page = 1
      void load()
    }, 250)
  }

  watch(pageKeyRef, () => {
    void load()
  })

  watch(
    () => [state.value.city, state.value.industry, state.value.lguType, state.value.hasOpenings] as const,
    () => {
      state.value.page = 1
      void load()
    },
  )

  watch(
    () => state.value.query,
    () => scheduleSearchLoad(),
  )

  onUnmounted(() => {
    mounted = false
    if (debounceTimer) clearTimeout(debounceTimer)
    // Keep cache; only abort in-flight for this page so a late response cannot write.
    abortByPageKey.get(pageKeyRef.value)?.abort()
  })

  return {
    config,
    state,
    load,
    changePage,
    setSort,
    setViewMode,
    setPageSize,
    setIncludeNonAligned,
  }
}

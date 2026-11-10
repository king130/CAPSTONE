<script setup lang="ts">
import { Building2, LayoutGrid, List, Search, SlidersHorizontal, X } from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref, toRef, watch } from 'vue'
import { RouterLink } from 'vue-router'

import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Switch from '@/components/ui/switch/Switch.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import { useBrowseDirectory } from '@/composables/browseDirectory/useBrowseDirectory'
import type { BrowsePageKey } from '@/composables/browseDirectory/types'
import type { DiscoverableOrganization } from '@/services/opportunities'
import { formatSharedCoursesBadge } from '@/utils/courseAlignment'

const props = withDefaults(
  defineProps<{
    pageKey: BrowsePageKey
    /** When false, panel is hidden; becoming true again triggers a fresh fetch. */
    active?: boolean
    /** Intern audience: partner-only copy for titles and empty states. */
    partnerScoped?: boolean
  }>(),
  { active: true, partnerScoped: false },
)

const pageKeyRef = toRef(props, 'pageKey')
const partnerScopedRef = toRef(props, 'partnerScoped')
const { config, state, load, changePage, setSort, setViewMode, setPageSize, setIncludeNonAligned } =
  useBrowseDirectory(pageKeyRef, { partnerScoped: partnerScopedRef })

/** Course-alignment chrome is for school/company partner browse, not intern partner-scoped tabs. */
const showCourseAlignmentControls = computed(() => !props.partnerScoped)

const filtersOpen = ref(false)
const draftCity = ref('')
const draftIndustry = ref('')
const draftLguType = ref('')
const draftSort = ref(state.value.sort)
const draftHasOpenings = ref(false)

const colspan = computed(() => config.value.columns.length)
const isPhone = ref(typeof window !== 'undefined' ? window.matchMedia('(max-width: 767px)').matches : true)

const rangeStart = computed(() => {
  if (!state.value.total) return 0
  return (state.value.page - 1) * state.value.pageSize + 1
})
const rangeEnd = computed(() => {
  if (!state.value.total) return 0
  return Math.min(state.value.page * state.value.pageSize, state.value.total)
})
const rangeLabel = computed(() => {
  if (showCourseAlignmentControls.value && state.value.catalogTotal > 0 && state.value.hiddenCount > 0) {
    return `Showing ${state.value.total} of ${state.value.catalogTotal}`
  }
  if (!state.value.total) return 'Showing 0 of 0'
  return `Showing ${rangeStart.value}-${rangeEnd.value} of ${state.value.total}`
})

const alignmentEmptyState = computed(() => {
  if (!showCourseAlignmentControls.value) return false
  if (state.value.loading || state.value.errorMessage) return false
  if (state.value.rows.length) return false
  if (state.value.query || state.value.city || state.value.industry || state.value.lguType || state.value.hasOpenings) {
    return false
  }
  return state.value.viewerHasCourses && !state.value.includeNonAligned && state.value.catalogTotal > 0
})

function alignmentBadges(organization: DiscoverableOrganization) {
  const badges: Array<{ key: string; label: string; tone: 'default' | 'warning' | 'muted' | 'success' }> = []
  if (organization.alignment === 'aligned') {
    badges.push({ key: 'shared', label: formatSharedCoursesBadge(organization.sharedCourses), tone: 'default' })
  } else if (organization.alignment === 'none') {
    badges.push({ key: 'none', label: 'No shared courses', tone: 'warning' })
  } else if (organization.alignment === 'unspecified') {
    badges.push({ key: 'unspecified', label: 'Courses not specified', tone: 'muted' })
  }
  if (organization.isPartnered) {
    badges.push({ key: 'partnered', label: 'Partnered', tone: 'success' })
  }
  return badges
}

function badgeClass(tone: 'default' | 'warning' | 'muted' | 'success') {
  if (tone === 'warning') return 'border-amber-500/40 bg-amber-500/10 text-amber-800'
  if (tone === 'muted') return 'border-border bg-muted text-muted-foreground'
  if (tone === 'success') return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800'
  return 'border-primary/30 bg-primary/10 text-primary'
}
const pageNumbers = computed(() => {
  const current = state.value.page
  const last = state.value.lastPage
  if (last <= 1) return [] as number[]
  const pages = new Set<number>([1, last, current, current - 1, current + 1])
  return [...pages].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b)
})

const showTable = computed(() => !isPhone.value && state.value.viewMode === 'table')
const showRequestAgreement = computed(() => config.value.orgType === 'school')

const activeFilterChips = computed(() => {
  const chips: Array<{ key: string; label: string }> = []
  if (state.value.city) chips.push({ key: 'city', label: state.value.city })
  if (state.value.industry) chips.push({ key: 'industry', label: state.value.industry })
  if (state.value.lguType) chips.push({ key: 'lguType', label: lguLabel(state.value.lguType) })
  if (state.value.hasOpenings) chips.push({ key: 'openings', label: 'Open positions' })
  const sortLabel = config.value.sortOptions.find((option) => option.value === state.value.sort)?.label
  if (sortLabel && state.value.sort !== config.value.defaultSort) {
    chips.push({ key: 'sort', label: sortLabel.replace(/^Sort:\s*/i, '') })
  }
  return chips
})

function lguLabel(value?: string | null) {
  if (value === 'municipality') return 'Municipality'
  if (value === 'city') return 'City'
  if (value === 'other_office') return 'Other office'
  return value || '—'
}

function fieldValue(organization: DiscoverableOrganization, field: string) {
  if (field === 'city') return organization.city || organization.address || 'Cavite'
  if (field === 'industry') return organization.industry || '—'
  if (field === 'lguType') return lguLabel(organization.lguType)
  if (field === 'openings') return String(organization.openOpportunities)
  if (field === 'tagline') return organization.tagline || ''
  if (field === 'name') return organization.name || '—'
  return ''
}

function fieldLabel(field: string) {
  if (field === 'city') return 'Location'
  if (field === 'industry') return 'Industry'
  if (field === 'lguType') return 'LGU'
  if (field === 'openings') return 'Openings'
  return field
}

function onSortChange(value: string) {
  setSort(value)
}

function onPageSizeChange(value: string) {
  const size = Number(value)
  if (!Number.isFinite(size) || size <= 0) return
  setPageSize(size)
}

function openFilters() {
  draftCity.value = state.value.city
  draftIndustry.value = state.value.industry
  draftLguType.value = state.value.lguType
  draftSort.value = state.value.sort
  draftHasOpenings.value = state.value.hasOpenings
  filtersOpen.value = true
}

function applyFilters() {
  state.value.city = draftCity.value
  state.value.industry = draftIndustry.value
  state.value.lguType = draftLguType.value
  state.value.hasOpenings = draftHasOpenings.value
  if (draftSort.value !== state.value.sort) setSort(draftSort.value)
  state.value.page = 1
  filtersOpen.value = false
  void load()
}

function clearFilters() {
  draftCity.value = ''
  draftIndustry.value = ''
  draftLguType.value = ''
  draftHasOpenings.value = false
  draftSort.value = config.value.defaultSort
  state.value.city = ''
  state.value.industry = ''
  state.value.lguType = ''
  state.value.hasOpenings = false
  state.value.page = 1
  if (state.value.sort !== config.value.defaultSort) setSort(config.value.defaultSort)
  else void load()
  filtersOpen.value = false
}

function clearChip(key: string) {
  if (key === 'city') state.value.city = ''
  if (key === 'industry') state.value.industry = ''
  if (key === 'lguType') state.value.lguType = ''
  if (key === 'openings') state.value.hasOpenings = false
  if (key === 'sort') setSort(config.value.defaultSort)
  state.value.page = 1
  void load()
}

function syncPhone() {
  isPhone.value = window.matchMedia('(max-width: 767px)').matches
  if (isPhone.value && state.value.viewMode !== 'cards') setViewMode('cards')
}

onMounted(() => {
  syncPhone()
  window.addEventListener('resize', syncPhone)
  if (props.active) void load()
})

onUnmounted(() => {
  window.removeEventListener('resize', syncPhone)
})

watch(
  () => props.active,
  (isActive, wasActive) => {
    if (isActive && wasActive === false) void load()
  },
)
</script>

<template>
  <div class="space-y-4 md:space-y-5" :data-browse-page="pageKey">
    <div class="flex items-center gap-2">
      <div class="relative min-w-0 flex-1">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="state.query"
          type="search"
          class="min-h-11 pl-9 text-base md:text-sm"
          :placeholder="config.searchPlaceholder"
          :aria-label="config.searchPlaceholder"
        />
      </div>
      <div class="hidden flex-wrap items-center gap-2 md:flex">
        <div class="inline-flex rounded-lg border border-border p-1" role="group" aria-label="View mode">
          <Button
            type="button"
            size="sm"
            :variant="state.viewMode === 'table' ? 'default' : 'ghost'"
            class="min-h-11 gap-1"
            @click="setViewMode('table')"
          >
            <List class="h-4 w-4" />
            Table
          </Button>
          <Button
            type="button"
            size="sm"
            :variant="state.viewMode === 'cards' ? 'default' : 'ghost'"
            class="min-h-11 gap-1"
            @click="setViewMode('cards')"
          >
            <LayoutGrid class="h-4 w-4" />
            Cards
          </Button>
        </div>
      </div>
    </div>

    <!-- Phone: horizontal chip filters -->
    <div class="md:hidden">
      <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <button
          type="button"
          class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold"
          :class="state.city ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-foreground'"
          @click="openFilters"
        >
          {{ state.city || 'Location' }}
          <SlidersHorizontal class="h-3.5 w-3.5 opacity-70" />
        </button>
        <button
          v-if="config.showIndustryFilter"
          type="button"
          class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3 text-xs font-semibold"
          :class="state.industry ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-foreground'"
          @click="openFilters"
        >
          {{ state.industry || 'Industry' }}
        </button>
        <button
          v-if="config.showLguFilter"
          type="button"
          class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3 text-xs font-semibold"
          :class="state.lguType ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-foreground'"
          @click="openFilters"
        >
          {{ state.lguType ? lguLabel(state.lguType) : 'LGU type' }}
        </button>
        <button
          v-if="config.showOpeningsFilter"
          type="button"
          class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3 text-xs font-semibold"
          :class="state.hasOpenings ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-background text-foreground'"
          @click="state.hasOpenings = !state.hasOpenings; state.page = 1; load()"
        >
          Has openings
        </button>
        <button
          v-for="chip in activeFilterChips.filter((c) => c.key === 'sort')"
          :key="chip.key"
          type="button"
          class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-full border border-primary/40 bg-primary/10 px-3 text-xs font-semibold text-primary"
          @click="clearChip(chip.key)"
        >
          {{ chip.label }}
          <X class="h-3.5 w-3.5" />
        </button>
      </div>
    </div>

    <div class="hidden grid-cols-2 gap-3 md:grid xl:grid-cols-4">
      <Select v-model="state.city" class="relative z-20" aria-label="Filter by location">
        <option value="">All locations</option>
        <option v-for="option in state.cities" :key="option" :value="option">{{ option }}</option>
      </Select>
      <Select
        v-if="config.showIndustryFilter"
        v-model="state.industry"
        class="relative z-20"
        aria-label="Filter by industry"
      >
        <option value="">All industries</option>
        <option v-for="option in state.industries" :key="option" :value="option">{{ option }}</option>
      </Select>
      <Select
        v-if="config.showLguFilter"
        v-model="state.lguType"
        class="relative z-20"
        aria-label="Filter by LGU type"
      >
        <option value="">All LGU types</option>
        <option v-for="option in state.lguTypes" :key="option" :value="option">{{ lguLabel(option) }}</option>
      </Select>
      <Select
        :model-value="state.sort"
        class="relative z-20"
        aria-label="Sort organizations"
        @update:model-value="onSortChange"
      >
        <option v-for="option in config.sortOptions" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </Select>
    </div>

    <label
      v-if="config.showOpeningsFilter"
      class="hidden items-center gap-2 text-sm text-foreground md:inline-flex"
    >
      <input v-model="state.hasOpenings" type="checkbox" class="h-4 w-4 rounded border-border" />
      Has open internship opportunities
    </label>

    <div
      v-if="showCourseAlignmentControls"
      class="flex flex-col gap-3 rounded-xl border border-border bg-card/60 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <label class="flex min-w-0 items-center gap-3 text-sm text-foreground">
        <Switch
          :model-value="state.includeNonAligned"
          :disabled="!state.viewerHasCourses"
          @update:model-value="setIncludeNonAligned"
        />
        <span class="min-w-0 leading-snug">Include organizations with no shared courses</span>
      </label>
      <p
        v-if="state.hiddenCount > 0 && !state.includeNonAligned"
        class="text-sm text-muted-foreground"
      >
        Showing {{ state.total }} of {{ state.catalogTotal }}
        <button
          type="button"
          class="ml-2 font-semibold text-primary underline-offset-2 hover:underline"
          @click="setIncludeNonAligned(true)"
        >
          Show all
        </button>
      </p>
    </div>

    <p
      v-if="showCourseAlignmentControls && !state.viewerHasCourses && !state.loading"
      class="rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-foreground"
    >
      Add your courses in Settings to see aligned organizations.
      <RouterLink to="/settings?tab=courses" class="ml-1 font-semibold text-primary underline-offset-2 hover:underline">
        Open Courses
      </RouterLink>
    </p>

    <p
      v-if="state.errorMessage"
      class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
      {{ state.errorMessage }}
      <button type="button" class="ml-2 font-semibold underline" @click="load()">Retry</button>
    </p>

    <div
      v-if="showTable"
      class="overflow-x-auto rounded-xl border border-border bg-card pb-1"
      data-browse-results
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead v-if="config.columns.includes('name')">
              <button type="button" class="font-medium" @click="setSort('name')">Name</button>
            </TableHead>
            <TableHead v-if="config.columns.includes('industry')">
              <button type="button" class="font-medium" @click="setSort('industry')">Industry</button>
            </TableHead>
            <TableHead v-if="config.columns.includes('lguType')">
              <button type="button" class="font-medium" @click="setSort('lguType')">LGU type</button>
            </TableHead>
            <TableHead v-if="config.columns.includes('city')">
              <button type="button" class="font-medium" @click="setSort('city')">Location</button>
            </TableHead>
            <TableHead v-if="config.columns.includes('openings')">
              <button type="button" class="font-medium" @click="setSort('openings')">Openings</button>
            </TableHead>
            <TableHead v-if="config.columns.includes('action')" class="text-right">Action</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="state.loading">
            <TableRow v-for="index in 6" :key="index">
              <TableCell v-for="column in config.columns" :key="column">
                <Skeleton :class="column === 'action' ? 'ml-auto h-9 w-24' : 'h-5 w-32'" />
              </TableCell>
            </TableRow>
          </template>
          <template v-else-if="state.rows.length">
            <TableRow v-for="organization in state.rows" :key="`${pageKey}-${organization.id}`">
              <TableCell v-if="config.columns.includes('name')">
                <div class="flex items-center gap-3">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted">
                    <img
                      v-if="organization.logoUrl"
                      :src="organization.logoUrl"
                      :alt="organization.name"
                      class="h-full w-full object-contain p-1"
                      loading="lazy"
                    />
                    <Building2 v-else class="h-4 w-4 text-muted-foreground" />
                  </div>
                  <div class="min-w-0 space-y-1.5">
                    <p class="truncate font-medium text-foreground">{{ organization.name }}</p>
                    <p v-if="organization.tagline" class="truncate text-xs text-muted-foreground">
                      {{ organization.tagline }}
                    </p>
                    <div
                      v-if="showCourseAlignmentControls && alignmentBadges(organization).length"
                      class="flex flex-wrap gap-1.5"
                    >
                      <span
                        v-for="badge in alignmentBadges(organization)"
                        :key="badge.key"
                        class="inline-flex max-w-full truncate rounded-md border px-2 py-0.5 text-[11px] font-medium"
                        :class="badgeClass(badge.tone)"
                      >
                        {{ badge.label }}
                      </span>
                    </div>
                  </div>
                </div>
              </TableCell>
              <TableCell v-if="config.columns.includes('industry')" class="text-muted-foreground">
                {{ fieldValue(organization, 'industry') }}
              </TableCell>
              <TableCell v-if="config.columns.includes('lguType')" class="text-muted-foreground">
                {{ fieldValue(organization, 'lguType') }}
              </TableCell>
              <TableCell v-if="config.columns.includes('city')" class="text-muted-foreground">
                {{ fieldValue(organization, 'city') }}
              </TableCell>
              <TableCell v-if="config.columns.includes('openings')" class="text-foreground">
                {{ fieldValue(organization, 'openings') }}
              </TableCell>
              <TableCell v-if="config.columns.includes('action')" class="text-right">
                <div class="flex flex-wrap items-center justify-end gap-2">
                  <RouterLink
                    :to="{ name: 'organization-public', params: { id: organization.id } }"
                    class="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-background px-3 text-sm font-medium text-foreground hover:bg-accent"
                  >
                    View
                  </RouterLink>
                  <RouterLink
                    v-if="showRequestAgreement"
                    :to="{ name: 'agreements-new', query: { organizationId: organization.id } }"
                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                  >
                    Request Agreement
                  </RouterLink>
                </div>
              </TableCell>
            </TableRow>
          </template>
          <TableRow v-else>
            <TableCell :colspan="colspan" class="py-10 text-center text-muted-foreground">
              <template v-if="alignmentEmptyState">
                <p>No organizations share your courses yet. Turn on “Include organizations with no shared courses” to browse all.</p>
                <Button type="button" class="mt-3" @click="setIncludeNonAligned(true)">
                  Include organizations with no shared courses
                </Button>
              </template>
              <template v-else>
                {{
                  state.query || state.city || state.industry || state.lguType || state.hasOpenings
                    ? 'No matches for those filters.'
                    : config.emptyCopy
                }}
              </template>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <div v-else class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2 xl:grid-cols-3" data-browse-results>
      <template v-if="state.loading">
        <div v-for="index in 6" :key="index" class="overflow-hidden rounded-xl border border-border bg-card">
          <Skeleton class="aspect-[4/3] w-full rounded-none" />
          <div class="space-y-2 p-3">
            <Skeleton class="h-5 w-3/4" />
            <Skeleton class="h-4 w-1/2" />
            <Skeleton class="h-10 w-full" />
          </div>
        </div>
      </template>
      <template v-else-if="state.rows.length">
        <article
          v-for="organization in state.rows"
          :key="`${pageKey}-card-${organization.id}`"
          class="overflow-hidden rounded-xl border border-border bg-card"
        >
          <div class="flex aspect-[4/3] items-center justify-center bg-muted">
            <img
              v-if="organization.logoUrl"
              :src="organization.logoUrl"
              :alt="organization.name"
              class="h-full w-full object-contain p-4"
              loading="lazy"
            />
            <Building2 v-else class="h-10 w-10 text-muted-foreground" />
          </div>
          <div class="space-y-2 p-3">
            <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-foreground sm:text-base">
              {{ organization.name || 'Unnamed organization' }}
            </h3>
            <p class="truncate text-xs text-muted-foreground sm:text-sm">
              {{ fieldValue(organization, 'city') }}
            </p>
            <p
              v-if="config.cardFields.includes('openings')"
              class="text-xs font-medium text-foreground sm:text-sm"
            >
              {{ fieldValue(organization, 'openings') }} openings
            </p>
            <div
              v-if="showCourseAlignmentControls && alignmentBadges(organization).length"
              class="flex flex-wrap gap-1.5"
            >
              <span
                v-for="badge in alignmentBadges(organization)"
                :key="badge.key"
                class="inline-flex max-w-full truncate rounded-md border px-2 py-0.5 text-[11px] font-medium"
                :class="badgeClass(badge.tone)"
              >
                {{ badge.label }}
              </span>
            </div>
            <div class="grid gap-2">
              <RouterLink
                :to="{ name: 'organization-public', params: { id: organization.id } }"
                class="inline-flex h-10 w-full items-center justify-center whitespace-nowrap rounded-md border border-border bg-background px-3 text-sm font-semibold text-foreground hover:bg-accent"
              >
                View
              </RouterLink>
              <RouterLink
                v-if="showRequestAgreement"
                :to="{ name: 'agreements-new', query: { organizationId: organization.id } }"
                class="inline-flex h-10 w-full items-center justify-center whitespace-nowrap rounded-md bg-primary px-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
              >
                Request Agreement
              </RouterLink>
            </div>
          </div>
        </article>
      </template>
      <div
        v-else
        class="col-span-full rounded-xl border border-dashed border-border px-4 py-10 text-center text-muted-foreground"
      >
        <template v-if="alignmentEmptyState">
          <p>No organizations share your courses yet. Turn on “Include organizations with no shared courses” to browse all.</p>
          <Button type="button" class="mt-3" @click="setIncludeNonAligned(true)">
            Include organizations with no shared courses
          </Button>
        </template>
        <template v-else>
          {{
            state.query || state.city || state.industry || state.lguType || state.hasOpenings
              ? 'No matches for those filters.'
              : config.emptyCopy
          }}
        </template>
      </div>
    </div>

    <div
      v-if="!state.loading"
      class="flex scroll-mb-28 flex-col gap-3 pb-2 md:flex-row md:flex-wrap md:items-center md:justify-between"
      data-browse-pagination
    >
      <div class="flex min-w-0 flex-wrap items-center gap-2 sm:gap-3">
        <p class="text-sm text-muted-foreground" data-browse-range>
          {{ rangeLabel }}
          <span v-if="state.lastPage > 1"> · Page {{ state.page }} of {{ state.lastPage }}</span>
        </p>
        <Select
          :model-value="String(state.pageSize)"
          aria-label="Rows per page"
          class="z-20 hidden w-[120px] md:block"
          @update:model-value="onPageSizeChange"
        >
          <option v-for="size in config.pageSizeOptions" :key="size" :value="String(size)">
            {{ size }} / page
          </option>
        </Select>
      </div>
      <div
        v-if="state.lastPage > 1"
        class="flex max-w-full flex-wrap items-center gap-2"
        data-browse-page-controls
      >
        <Button class="min-h-11 flex-1 sm:flex-none" variant="outline" :disabled="state.page <= 1" @click="changePage(state.page - 1)">
          Previous
        </Button>
        <Button
          class="min-h-11 flex-1 sm:flex-none"
          variant="outline"
          :disabled="state.page >= state.lastPage"
          data-browse-next
          @click="changePage(state.page + 1)"
        >
          Next
        </Button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="filtersOpen" class="fixed inset-0 z-modal md:hidden">
        <button type="button" class="absolute inset-0 bg-slate-950/50" aria-label="Close filters" @click="filtersOpen = false" />
        <div class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-2xl border border-border bg-background p-4 pb-[max(1rem,var(--safe-bottom))] shadow-xl">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-foreground">Filters</h2>
            <Button type="button" variant="ghost" size="icon" class="min-h-11 min-w-11" @click="filtersOpen = false">
              <X class="h-5 w-5" />
            </Button>
          </div>
          <div class="space-y-3">
            <Select v-model="draftCity" aria-label="Filter by location">
              <option value="">All locations</option>
              <option v-for="option in state.cities" :key="option" :value="option">{{ option }}</option>
            </Select>
            <Select
              v-if="config.showIndustryFilter"
              v-model="draftIndustry"
              aria-label="Filter by industry"
            >
              <option value="">All industries</option>
              <option v-for="option in state.industries" :key="option" :value="option">{{ option }}</option>
            </Select>
            <Select
              v-if="config.showLguFilter"
              v-model="draftLguType"
              aria-label="Filter by LGU type"
            >
              <option value="">All LGU types</option>
              <option v-for="option in state.lguTypes" :key="option" :value="option">{{ lguLabel(option) }}</option>
            </Select>
            <Select v-model="draftSort" aria-label="Sort organizations">
              <option v-for="option in config.sortOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </Select>
            <label v-if="config.showOpeningsFilter" class="flex min-h-11 items-center gap-3 text-sm text-foreground">
              <input v-model="draftHasOpenings" type="checkbox" class="h-5 w-5 rounded border-border" />
              Has open internship opportunities
            </label>
          </div>
          <div class="mt-5 grid grid-cols-2 gap-2">
            <Button type="button" variant="outline" class="min-h-11" @click="clearFilters">Clear</Button>
            <Button type="button" class="min-h-11" @click="applyFilters">Apply</Button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

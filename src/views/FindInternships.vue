<script setup lang="ts">
import {
  BadgeCheck,
  BriefcaseBusiness,
  Building2,
  MapPin,
  Search,
  Wallet,
} from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import SaveInternshipButton from '@/components/internships/SaveInternshipButton.vue'
import PublicSiteHeader from '@/components/PublicSiteHeader.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { allCaviteLocations, courseGroups } from '@/config/courseCatalog'
import {
  listEligibleInternshipIds,
  searchInternshipsCatalog,
  type InternshipRecord,
  type InternshipSearchParams,
  type InternshipSortOption,
} from '@/services/internships'
import { useAuthStore } from '@/stores/auth'
import {
  matchCourseProgram,
  stableSortByCourseMatchLevel,
  type CourseMatchLevel,
} from '@/utils/courseMatch'

const PAGE_SIZE = 9
const SEARCH_DEBOUNCE_MS = 350

const workSetupOptions = ['On-site', 'Hybrid', 'Remote', 'Field Work'] as const
const scheduleTypeOptions = [
  { value: 'fixed', label: 'Fixed schedule' },
  { value: 'flexible', label: 'Flexible' },
  { value: 'shifting', label: 'Shifting' },
] as const
const allowanceFilterOptions = [
  { value: 'with', label: 'With allowance' },
  { value: 'without', label: 'No / unpaid noted' },
] as const

const authStore = useAuthStore()
const router = useRouter()

const loading = ref(true)
const loadingMore = ref(false)
const errorMessage = ref('')
const catalog = ref<InternshipRecord[]>([])
const eligibleIds = ref<Set<string>>(new Set())
const visibleCount = ref(PAGE_SIZE)

const searchInput = ref('')
const debouncedSearch = ref('')
const filterCity = ref('')
const filterWorkSetup = ref('')
const filterCourse = ref('')
const filterAllowance = ref('')
const filterScheduleType = ref('')
const sortBy = ref<InternshipSortOption>('newest')

let searchTimer: ReturnType<typeof setTimeout> | null = null
let reloadTimer: ReturnType<typeof setTimeout> | null = null
let requestSeq = 0
let skipNextReload = false

const isAuthenticatedStudent = computed(() => authStore.user?.role === 'student')
const isGuestOrPublic = computed(
  () => !authStore.user || authStore.user.role === 'guest' || authStore.user.role === null,
)
const studentCourse = computed(() => {
  const profile = (authStore.user?.profile as Record<string, unknown> | undefined) ?? {}
  return String(profile.course ?? '').trim()
})

const cityOptions = computed(() => {
  const fromCatalog = new Set(allCaviteLocations)
  for (const item of catalog.value) {
    const city = extractCity(item.location)
    if (city) fromCatalog.add(city)
  }
  return Array.from(fromCatalog).sort((a, b) => a.localeCompare(b))
})

function extractCity(location: string | undefined): string {
  if (!location) return ''
  const trimmed = location.trim()
  if (!trimmed) return ''
  const beforeComma = trimmed.split(',')[0]?.trim() ?? trimmed
  return beforeComma
}

function courseMatchBadge(level: CourseMatchLevel): {
  label: string
  variant: 'success' | 'secondary' | 'outline'
} {
  switch (level) {
    case 'strong':
      return { label: 'Matches your course', variant: 'success' }
    case 'related':
      return { label: 'Related to your course', variant: 'secondary' }
    case 'open':
      return { label: 'Open to all courses', variant: 'secondary' }
    case 'unknown':
      return { label: 'Course fit unavailable', variant: 'outline' }
    case 'weak':
    default:
      return { label: 'Check course fit', variant: 'outline' }
  }
}

function slotsLeft(item: InternshipRecord): number {
  const value = item.slotsRemaining ?? item.slotsAvailable
  return Number.isFinite(value) ? Math.max(0, Number(value)) : 0
}

function workSetupLabel(item: InternshipRecord): string {
  return (item.workSetup || item.type || 'Setup TBA').trim() || 'Setup TBA'
}

function isPartnered(item: InternshipRecord): boolean {
  if (typeof item.partneredWithMySchool === 'boolean') return item.partneredWithMySchool
  if (!isAuthenticatedStudent.value) return false
  if (item.hostType === 'school') return true
  return eligibleIds.value.has(item.id)
}

function matchesAllowanceFilter(item: InternshipRecord, filter: string): boolean {
  if (!filter) return true
  const allowance = (item.allowance || '').trim().toLowerCase()
  const unpaid =
    !allowance ||
    allowance.includes('unpaid') ||
    allowance === 'none' ||
    allowance === 'n/a' ||
    allowance === '0'
  if (filter === 'with') return !unpaid
  if (filter === 'without') return unpaid
  return true
}

function matchesClientFilters(item: InternshipRecord): boolean {
  if (item.status && item.status !== 'active') return false

  const city = filterCity.value.trim().toLowerCase()
  if (city) {
    const hay = `${item.location || ''}`.toLowerCase()
    if (!hay.includes(city)) return false
  }

  const setup = filterWorkSetup.value.trim().toLowerCase()
  if (setup) {
    const hay = workSetupLabel(item).toLowerCase()
    if (hay !== setup && !hay.includes(setup)) return false
  }

  const course = filterCourse.value.trim().toLowerCase()
  if (course) {
    const courses = (item.eligibleCourses || []).map((c) => c.toLowerCase())
    const open = courses.length === 0
    if (!open && !courses.some((c) => c.includes(course) || course.includes(c))) return false
  }

  if (!matchesAllowanceFilter(item, filterAllowance.value)) return false

  const schedule = filterScheduleType.value.trim().toLowerCase()
  if (schedule) {
    const type = String(item.scheduleType || '').toLowerCase()
    const flexible = Boolean(item.isFlexible)
    if (schedule === 'flexible') {
      if (!(flexible || type.includes('flex'))) return false
    } else if (type !== schedule && !type.includes(schedule)) {
      return false
    }
  }

  const q = debouncedSearch.value.trim().toLowerCase()
  if (q) {
    const haystack = [
      item.title,
      item.companyName,
      item.hostName,
      item.location,
      item.description,
      workSetupLabel(item),
      item.allowance,
      ...(item.eligibleCourses || []),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    if (!haystack.includes(q)) return false
  }

  return true
}

function allowanceSortValue(allowance: string | undefined): number {
  if (!allowance) return -1
  const digits = allowance.replace(/[^\d.]/g, '')
  const n = Number(digits)
  return Number.isFinite(n) ? n : -1
}

type ListingCard = InternshipRecord & {
  partnered: boolean
  courseMatchLevel: CourseMatchLevel
  courseMatchLabel: string
  courseMatchVariant: 'success' | 'secondary' | 'outline'
  slotsLeft: number
  workSetupLabel: string
  cityLabel: string
}

const filteredCards = computed<ListingCard[]>(() => {
  const course = studentCourse.value || null
  const rows = catalog.value.filter(matchesClientFilters).map((item) => {
    const match = matchCourseProgram(course, item.eligibleCourses)
    const badge = courseMatchBadge(match.level)
    return {
      ...item,
      partnered: isPartnered(item),
      courseMatchLevel: match.level,
      courseMatchLabel: badge.label,
      courseMatchVariant: badge.variant,
      slotsLeft: slotsLeft(item),
      workSetupLabel: workSetupLabel(item),
      cityLabel: extractCity(item.location) || item.location || 'Cavite',
    }
  })

  if (sortBy.value === 'course_match') {
    return stableSortByCourseMatchLevel(rows, (row) => row.courseMatchLevel)
  }

  const sorted = [...rows]
  sorted.sort((a, b) => {
    switch (sortBy.value) {
      case 'slots_remaining':
        return b.slotsLeft - a.slotsLeft || a.title.localeCompare(b.title)
      case 'allowance':
        return allowanceSortValue(b.allowance) - allowanceSortValue(a.allowance) || a.title.localeCompare(b.title)
      case 'title':
        return a.title.localeCompare(b.title)
      case 'newest':
      default: {
        const aTime = Date.parse(String(a.createdAt ?? '')) || 0
        const bTime = Date.parse(String(b.createdAt ?? '')) || 0
        return bTime - aTime || a.title.localeCompare(b.title)
      }
    }
  })
  return sorted
})

const visibleCards = computed(() => filteredCards.value.slice(0, visibleCount.value))
const hasMore = computed(() => visibleCount.value < filteredCards.value.length)
const resultCountLabel = computed(() => {
  const n = filteredCards.value.length
  return `${n} opening${n === 1 ? '' : 's'}`
})

function buildSearchParams(): InternshipSearchParams {
  return {
    q: debouncedSearch.value.trim() || undefined,
    city: filterCity.value || undefined,
    location: filterCity.value || undefined,
    work_setup: filterWorkSetup.value || undefined,
    course: filterCourse.value || undefined,
    allowance: filterAllowance.value || undefined,
    schedule_type: filterScheduleType.value || undefined,
    sort: sortBy.value,
    status: 'active',
    per_page: 100,
  }
}

async function loadCatalog(options: { append?: boolean } = {}) {
  const seq = ++requestSeq
  if (options.append) loadingMore.value = true
  else loading.value = true
  errorMessage.value = ''

  try {
    const params = buildSearchParams()
    const [items, eligible] = await Promise.all([
      searchInternshipsCatalog(params),
      isAuthenticatedStudent.value
        ? listEligibleInternshipIds().catch(() => new Set<string>())
        : Promise.resolve(new Set<string>()),
    ])

    if (seq !== requestSeq) return

    catalog.value = items
    eligibleIds.value = eligible
    if (!options.append) visibleCount.value = PAGE_SIZE
  } catch (error) {
    if (seq !== requestSeq) return
    errorMessage.value =
      error instanceof Error ? error.message : 'Unable to load internship openings right now.'
    catalog.value = []
  } finally {
    if (seq === requestSeq) {
      loading.value = false
      loadingMore.value = false
    }
  }
}

function loadMore() {
  visibleCount.value += PAGE_SIZE
}

function clearFilters() {
  skipNextReload = true
  searchInput.value = ''
  debouncedSearch.value = ''
  filterCity.value = ''
  filterWorkSetup.value = ''
  filterCourse.value = ''
  filterAllowance.value = ''
  filterScheduleType.value = ''
  sortBy.value = 'newest'
  visibleCount.value = PAGE_SIZE
  void loadCatalog()
}

function scheduleReload() {
  if (skipNextReload) {
    skipNextReload = false
    return
  }
  if (reloadTimer) clearTimeout(reloadTimer)
  reloadTimer = setTimeout(() => {
    visibleCount.value = PAGE_SIZE
    void loadCatalog()
  }, 150)
}

function applyPath(card: ListingCard) {
  if (!isAuthenticatedStudent.value) {
    void router.push({ name: 'login', query: { redirect: '/intern/opportunities' } })
    return
  }
  if (!card.partnered) return
  void router.push({ name: 'intern-opportunities', query: { focus: card.id } })
}

function setCatalogSaved(internshipId: string, saved: boolean) {
  const item = catalog.value.find((row) => row.id === internshipId)
  if (item) item.saved = saved
}

watch(searchInput, (value) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    debouncedSearch.value = value
  }, SEARCH_DEBOUNCE_MS)
})

watch(
  [debouncedSearch, filterCity, filterWorkSetup, filterCourse, filterAllowance, filterScheduleType, sortBy],
  () => {
    scheduleReload()
  },
)

onMounted(() => {
  if (authStore.user?.role === 'school') {
    router.replace('/school')
    return
  }

  if (authStore.user?.role === 'company') {
    router.replace('/dashboard')
    return
  }

  void loadCatalog()
})

onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer)
  if (reloadTimer) clearTimeout(reloadTimer)
  requestSeq += 1
})
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <PublicSiteHeader active="opportunities" />

    <main>
      <section class="border-b border-border bg-muted/40">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-12">
          <h1 class="max-w-2xl text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
            Find internships in Cavite
          </h1>
          <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">
            Browse openings the way you’d scan hotel stays — cover, host, location, and availability first.
            Course-based decision support helps you spot program fit; school partnerships control who can apply.
          </p>
        </div>
      </section>

      <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="sticky top-0 z-10 -mx-4 space-y-3 border-b border-border bg-background/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              v-model="searchInput"
              type="search"
              placeholder="Search by title, company, or city"
              class="pl-9"
              aria-label="Search internships"
            />
          </div>

          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <Select id="filter-city" v-model="filterCity" aria-label="Filter by city">
              <option value="">All cities</option>
              <option v-for="city in cityOptions" :key="city" :value="city">{{ city }}</option>
            </Select>

            <Select id="filter-work-setup" v-model="filterWorkSetup" aria-label="Filter by work setup">
              <option value="">All work setups</option>
              <option v-for="setup in workSetupOptions" :key="setup" :value="setup">{{ setup }}</option>
            </Select>

            <Select id="filter-course" v-model="filterCourse" aria-label="Filter by course">
              <option value="">All courses</option>
              <optgroup v-for="group in courseGroups" :key="group.label" :label="group.label">
                <option v-for="course in group.options" :key="course" :value="course">{{ course }}</option>
              </optgroup>
            </Select>

            <Select id="filter-allowance" v-model="filterAllowance" aria-label="Filter by allowance">
              <option value="">Any allowance</option>
              <option v-for="option in allowanceFilterOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </Select>

            <Select id="filter-schedule" v-model="filterScheduleType" aria-label="Filter by schedule type">
              <option value="">Any schedule</option>
              <option v-for="option in scheduleTypeOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </Select>

            <Select
              id="sort-by"
              :model-value="sortBy"
              aria-label="Sort internships"
              @update:model-value="sortBy = ($event as InternshipSortOption) || 'newest'"
            >
              <option value="newest">Newest</option>
              <option value="slots_remaining">Slots left</option>
              <option value="allowance">Allowance</option>
              <option value="title">Title A–Z</option>
              <option v-if="isAuthenticatedStudent" value="course_match">Course match</option>
            </Select>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">
              <span class="font-medium text-foreground">Course-based decision support</span>
              · {{ loading ? 'Loading…' : resultCountLabel }}
            </p>
            <Button type="button" variant="ghost" size="sm" @click="clearFilters">Clear filters</Button>
          </div>
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {{ errorMessage }}
        </p>

        <div v-if="loading" class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          <Card v-for="index in 6" :key="`skeleton-${index}`" class="overflow-hidden border-border bg-card">
            <Skeleton class="h-44 w-full rounded-none bg-muted" />
            <CardContent class="space-y-3 p-5">
              <Skeleton class="h-4 w-1/3 bg-muted" />
              <Skeleton class="h-6 w-4/5 bg-muted" />
              <Skeleton class="h-4 w-1/2 bg-muted" />
              <Skeleton class="h-10 w-full bg-muted" />
            </CardContent>
          </Card>
        </div>

        <div v-else-if="visibleCards.length" class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="card in visibleCards"
            :key="card.id"
            class="group flex flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm transition hover:border-primary/30"
          >
            <div class="relative aspect-[16/10] overflow-hidden bg-muted">
              <RouterLink :to="{ name: 'internship-detail', params: { id: card.id } }" class="absolute inset-0 block">
                <img
                  v-if="card.coverImage"
                  :src="card.coverImage"
                  :alt="`${card.title} cover`"
                  class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                  loading="lazy"
                />
                <div
                  v-else
                  class="flex h-full w-full flex-col items-center justify-center gap-2 bg-[radial-gradient(circle_at_top,_hsl(var(--muted-foreground)/0.12),_transparent_55%)] text-muted-foreground"
                >
                  <Building2 class="h-8 w-8 opacity-70" />
                  <span class="text-xs font-medium">No cover photo</span>
                </div>
              </RouterLink>
              <div class="pointer-events-none absolute left-3 top-3">
                <Badge
                  :variant="card.partnered ? 'success' : 'outline'"
                  :class="
                    card.partnered
                      ? 'border-emerald-500/30 bg-background/90 text-emerald-700 dark:text-emerald-300'
                      : 'bg-background/90'
                  "
                >
                  {{ card.partnered ? 'Partnered with your school' : 'Not yet available' }}
                </Badge>
              </div>
              <div class="absolute right-3 top-3 z-10">
                <SaveInternshipButton
                  :internship-id="card.id"
                  :saved="Boolean(card.saved)"
                  size="sm"
                  @update:saved="setCatalogSaved(card.id, $event)"
                />
              </div>
            </div>

            <div class="flex flex-1 flex-col gap-3 p-5">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="flex items-center gap-1.5 text-sm font-medium text-foreground">
                    <RouterLink
                      v-if="card.organizationId"
                      :to="{ name: 'organization-public', params: { id: card.organizationId } }"
                      class="truncate hover:underline"
                    >
                      {{ card.companyName || card.hostName || 'Host organization' }}
                    </RouterLink>
                    <span v-else class="truncate">{{ card.companyName || card.hostName || 'Host organization' }}</span>
                    <span
                      v-if="card.verified"
                      class="inline-flex shrink-0 items-center gap-1 rounded-full border border-border bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground"
                    >
                      <BadgeCheck class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" aria-hidden="true" />
                      Verified
                    </span>
                  </p>
                  <RouterLink
                    :to="{ name: 'internship-detail', params: { id: card.id } }"
                    class="mt-1 block line-clamp-2 text-lg font-semibold tracking-tight text-foreground hover:underline"
                  >
                    {{ card.title || 'Untitled internship' }}
                  </RouterLink>
                </div>
              </div>

              <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground">
                <span class="inline-flex items-center gap-1.5">
                  <MapPin class="h-3.5 w-3.5 shrink-0" />
                  {{ card.cityLabel }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <BriefcaseBusiness class="h-3.5 w-3.5 shrink-0" />
                  {{ card.workSetupLabel }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                  <Wallet class="h-3.5 w-3.5 shrink-0" />
                  {{ card.allowance?.trim() || 'Allowance TBA' }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{{ card.slotsLeft }} slot{{ card.slotsLeft === 1 ? '' : 's' }} left</Badge>
                <Badge
                  v-if="isAuthenticatedStudent || studentCourse"
                  :variant="card.courseMatchVariant"
                  :title="'Course-based decision support'"
                >
                  {{ card.courseMatchLabel }}
                </Badge>
                <Badge v-else variant="outline">Course-based decision support</Badge>
              </div>

              <p v-if="isAuthenticatedStudent && !card.partnered" class="text-xs leading-5 text-muted-foreground">
                Ask your school to partner with this company
              </p>
              <p v-else-if="isGuestOrPublic" class="text-xs leading-5 text-muted-foreground">
                Log in as a student to check school partnership and apply.
              </p>

              <div class="mt-auto flex flex-wrap gap-2 pt-1">
                <Button
                  class="flex-1"
                  :disabled="isAuthenticatedStudent && !card.partnered"
                  @click="applyPath(card)"
                >
                  {{
                    isAuthenticatedStudent
                      ? card.partnered
                        ? 'View & Apply'
                        : 'Not yet available'
                      : 'Log in to Apply'
                  }}
                </Button>
                <RouterLink
                  class="inline-flex h-10 flex-1 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground transition hover:bg-accent hover:text-accent-foreground"
                  :to="{ name: 'internship-detail', params: { id: card.id } }"
                >
                  Details
                </RouterLink>
              </div>
            </div>
          </article>
        </div>

        <Card v-else class="mt-8 border-dashed border-border bg-card">
          <CardContent class="flex flex-col items-center gap-3 px-6 py-16 text-center">
            <div class="rounded-2xl bg-muted p-3 text-muted-foreground">
              <BriefcaseBusiness class="h-6 w-6" />
            </div>
            <h3 class="text-lg font-semibold text-foreground">No openings match these filters</h3>
            <p class="max-w-md text-sm text-muted-foreground">
              Try another city, work setup, or course — or clear filters to see every active Cavite posting.
            </p>
            <Button variant="outline" @click="clearFilters">Clear filters</Button>
          </CardContent>
        </Card>

        <div v-if="!loading && hasMore" class="mt-8 flex justify-center">
          <Button variant="outline" :disabled="loadingMore" @click="loadMore">
            {{ loadingMore ? 'Loading…' : 'Load more openings' }}
          </Button>
        </div>
      </section>
    </main>

    <footer class="border-t border-border bg-card">
      <div
        class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
      >
        <p class="font-medium text-foreground">OJT Intern Path</p>
        <p>© {{ new Date().getFullYear() }} OJT Intern Path. Cavite internship coordination.</p>
        <div class="flex gap-4">
          <RouterLink to="/" class="transition hover:text-foreground">Home</RouterLink>
          <RouterLink to="/register" class="transition hover:text-foreground">Register</RouterLink>
          <RouterLink to="/login" class="transition hover:text-foreground">Log In</RouterLink>
        </div>
      </div>
    </footer>
  </div>
</template>

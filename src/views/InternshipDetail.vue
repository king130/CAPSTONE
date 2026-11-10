<script setup lang="ts">
import {
  BadgeCheck,
  BriefcaseBusiness,
  CalendarDays,
  MapPin,
  Wallet,
} from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import ApplicationCapacity from '@/components/internships/ApplicationCapacity.vue'
import ApplicationTrackerPanel from '@/components/ApplicationTrackerPanel.vue'
import SaveInternshipButton from '@/components/internships/SaveInternshipButton.vue'
import PublicSiteHeader from '@/components/PublicSiteHeader.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import type { LayoutRole } from '@/layouts/navigation'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import SkillChips from '@/components/skills/SkillChips.vue'
import { useActivePlacement } from '@/composables/useActivePlacement'
import { listApplications, type ApplicationRecord } from '@/services/applications'
import {
  getInternship,
  listEligibleInternshipIds,
  type InternshipRecord,
} from '@/services/internships'
import { fetchStudentSkills, type SelectedSkill } from '@/services/skills'
import { useAuthStore } from '@/stores/auth'
import { formatAllowanceDisplay } from '@/utils/formatAllowance'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const appRole = computed((): LayoutRole | null => {
  const role = authStore.user?.role
  if (role === 'student' || role === 'school' || role === 'company') return role
  return null
})

const listingsTo = computed(() => {
  if (appRole.value === 'school') return '/school/opportunities'
  if (appRole.value === 'company') return '/dashboard/schools'
  if (appRole.value === 'student') return '/intern/openings'
  return '/find-internships'
})

const pageShell = computed(() => (appRole.value ? MainLayout : 'div'))
const pageShellProps = computed(() =>
  appRole.value
    ? {
        role: appRole.value,
        title: appRole.value === 'student' ? 'Internship Opportunities' : 'Browse',
        activeItem: appRole.value === 'student' ? 'openings' : 'opportunities',
      }
    : { class: 'min-h-screen bg-background text-foreground' },
)

const loading = ref(true)
const notFound = ref(false)
const errorMessage = ref('')
const internship = ref<InternshipRecord | null>(null)
const myApplication = ref<ApplicationRecord | null>(null)
const mySkills = ref<SelectedSkill[]>([])
const previewPhotos = computed(() => (internship.value?.previewPhotos ?? internship.value?.organizationMedia ?? []).slice(0, 3))
const eligibleIds = ref<Set<string>>(new Set())

const jobSkillItems = computed(() =>
  (internship.value?.skills || []).map((skill) => ({
    id: skill.id,
    name: skill.name,
    category: skill.category || 'Other',
    status: (skill.status as SelectedSkill['status']) || 'approved',
    importance: skill.importance || 'required',
  })),
)

const isAuthenticatedStudent = computed(() => authStore.user?.role === 'student')
const isGuestOrPublic = computed(
  () => !authStore.user || authStore.user.role === 'guest' || authStore.user.role === null,
)

const skillMatchSummary = computed(() => {
  if (!isAuthenticatedStudent.value || !jobSkillItems.value.length) return null
  const mine = new Set(mySkills.value.map((skill) => skill.id))
  const required = jobSkillItems.value.filter((skill) => skill.importance !== 'nice_to_have')
  const nice = jobSkillItems.value.filter((skill) => skill.importance === 'nice_to_have')
  const matchedRequired = required.filter((skill) => mine.has(skill.id))
  const missingRequired = required.filter((skill) => !mine.has(skill.id))
  const matchedNice = nice.filter((skill) => mine.has(skill.id))
  return {
    matchedRequired,
    missingRequired,
    matchedNice,
    requiredTotal: required.length,
    niceTotal: nice.length,
  }
})
const partnered = computed(() => {
  const item = internship.value
  if (!item) return false
  if (typeof item.partneredWithMySchool === 'boolean') return item.partneredWithMySchool
  if (!isAuthenticatedStudent.value) return false
  if (item.hostType === 'school') return true
  return eligibleIds.value.has(item.id)
})

const slotsLeft = computed(() => {
  const item = internship.value
  if (!item) return 0
  const value = item.slotsRemaining ?? item.slotsAvailable
  return Number.isFinite(value) ? Math.max(0, Number(value)) : 0
})

const deadlinePassed = computed(() => {
  const deadline = internship.value?.applicationDeadline
  if (!deadline) return false
  const end = new Date(`${deadline}T23:59:59`)
  return Number.isFinite(end.getTime()) && end.getTime() < Date.now()
})

const isClosed = computed(() => deadlinePassed.value || slotsLeft.value <= 0 || internship.value?.status === 'closed')

const orgProfileTo = computed(() => {
  const orgId = internship.value?.organizationId
  return orgId ? { name: 'organization-public', params: { id: orgId } } : null
})

const { hasActivePlacement, placementBlockMessage, refreshActivePlacement } = useActivePlacement()

const applyLabel = computed(() => {
  if (isClosed.value) return 'Closed'
  if (isGuestOrPublic.value) return 'Log in to Apply'
  if (!isAuthenticatedStudent.value) return 'Students can apply'
  if (hasActivePlacement.value) return 'Already placed'
  if (!partnered.value) return 'Not yet available'
  return 'Apply'
})

const applyDisabled = computed(() => {
  if (isClosed.value) return true
  if (isGuestOrPublic.value) return false
  if (!isAuthenticatedStudent.value) return true
  if (hasActivePlacement.value) return true
  if (!partnered.value) return true
  return false
})

function workSetupLabel(item: InternshipRecord): string {
  const raw = (item.workSetup || item.type || '').trim()
  if (!raw) return 'Setup TBA'
  if (raw.toLowerCase() === 'onsite') return 'On-site'
  return raw.charAt(0).toUpperCase() + raw.slice(1)
}

function formatDate(value?: string | null): string {
  if (!value) return 'TBA'
  const date = new Date(`${value}T00:00:00`)
  if (!Number.isFinite(date.getTime())) return value
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

async function loadDetail() {
  const id = String(route.params.id || '')
  if (!id) {
    notFound.value = true
    loading.value = false
    return
  }

  loading.value = true
  notFound.value = false
  errorMessage.value = ''

  try {
    const [item, eligible] = await Promise.all([
      getInternship(id),
      isAuthenticatedStudent.value ? listEligibleInternshipIds().catch(() => new Set<string>()) : Promise.resolve(new Set<string>()),
    ])
    eligibleIds.value = eligible

    if (!item) {
      notFound.value = true
      internship.value = null
      return
    }

    internship.value = item
    myApplication.value = null
    if (isAuthenticatedStudent.value) {
      const [applications, skillsRes] = await Promise.all([
        listApplications().catch(() => [] as ApplicationRecord[]),
        fetchStudentSkills().catch(() => null),
      ])
      myApplication.value = applications.find((application) => String(application.internshipId) === String(item.id)) ?? null
      if (skillsRes?.data) {
        mySkills.value = skillsRes.data
      } else {
        const embedded = authStore.user?.profile as Record<string, unknown> | undefined
        mySkills.value = Array.isArray(embedded?.skills) ? (embedded.skills as SelectedSkill[]) : []
      }
    } else {
      mySkills.value = []
    }
  } catch (err) {
    const code = typeof err === 'object' && err && 'code' in err ? String((err as { code?: unknown }).code || '') : ''
    if (code === 'agreement_required' || /agreement/i.test(err instanceof Error ? err.message : '')) {
      errorMessage.value =
        err instanceof Error
          ? err.message
          : 'This internship is not available because your school does not have an active agreement with the company.'
    } else {
      errorMessage.value = err instanceof Error ? err.message : 'Unable to load internship.'
    }
    internship.value = null
  } finally {
    loading.value = false
  }
}

function onApply() {
  if (isClosed.value) return
  if (!isAuthenticatedStudent.value) {
    void router.push({ name: 'login', query: { redirect: `/internships/${route.params.id}` } })
    return
  }
  if (hasActivePlacement.value) return
  if (!partnered.value) return
  void router.push({ name: 'intern-openings', query: { focus: String(route.params.id) } })
}

watch(
  () => route.params.id,
  () => {
    void loadDetail()
  },
)

onMounted(() => {
  void refreshActivePlacement()
  void loadDetail()
})
</script>

<template>
  <component :is="pageShell" v-bind="pageShellProps">
    <PublicSiteHeader v-if="!appRole" active="opportunities" />

    <div :class="appRole ? 'pb-20 lg:pb-0' : 'mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:pb-12'">
      <div v-if="loading" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-4">
          <Skeleton class="aspect-[16/10] w-full rounded-xl" />
          <Skeleton class="h-8 w-2/3" />
          <Skeleton class="h-24 w-full" />
        </div>
        <Skeleton class="hidden h-64 rounded-xl lg:block" />
      </div>

      <Card v-else-if="notFound" class="border-border bg-card">
        <CardContent class="space-y-3 px-6 py-16 text-center">
          <h1 class="text-2xl font-semibold text-foreground">Internship not found</h1>
          <p class="text-sm text-muted-foreground">This posting may have been removed or is no longer public.</p>
          <RouterLink
            :to="listingsTo"
            class="inline-flex h-10 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground hover:bg-accent"
          >
            Back to listings
          </RouterLink>
        </CardContent>
      </Card>

      <Card v-else-if="errorMessage" class="border-border bg-card">
        <CardContent class="space-y-3 px-6 py-16 text-center">
          <h1 class="text-2xl font-semibold text-foreground">
            {{ /agreement/i.test(errorMessage) ? 'Not available for your school' : 'Unable to load internship' }}
          </h1>
          <p class="text-sm text-muted-foreground">{{ errorMessage }}</p>
          <div class="flex flex-wrap justify-center gap-2">
            <RouterLink
              v-if="appRole === 'student'"
              :to="listingsTo"
              class="inline-flex h-10 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground hover:bg-accent"
            >
              Back to openings
            </RouterLink>
            <Button variant="outline" @click="loadDetail">Retry</Button>
          </div>
        </CardContent>
      </Card>

      <div v-else-if="internship" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
          <div class="overflow-hidden rounded-xl border border-border bg-muted">
            <img
              v-if="internship.coverImage"
              :src="internship.coverImage"
              :alt="`${internship.companyName || internship.hostName || 'Company'} logo`"
              class="aspect-[16/9] w-full object-contain p-8 sm:aspect-[16/7]"
            />
            <div v-else class="flex aspect-[16/9] w-full items-center justify-center text-sm text-muted-foreground sm:aspect-[16/7]">
              No company logo
            </div>
          </div>

          <section v-if="previewPhotos.length" class="space-y-2">
            <h2 class="text-sm font-medium text-foreground">Company preview</h2>
            <div class="grid grid-cols-3 gap-2">
              <img
                v-for="photo in previewPhotos"
                :key="photo.id"
                :src="photo.url"
                :alt="photo.caption || `${internship.companyName || 'Company'} photo`"
                class="aspect-[4/3] w-full rounded-lg border border-border bg-muted object-cover"
                loading="lazy"
              />
            </div>
          </section>

          <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
              <component
                :is="orgProfileTo ? RouterLink : 'span'"
                :to="orgProfileTo || undefined"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-foreground"
                :class="orgProfileTo ? 'hover:underline' : ''"
              >
                <span>{{ internship.companyName || internship.hostName || 'Host organization' }}</span>
                <span
                  v-if="internship.verified"
                  class="inline-flex items-center gap-1 rounded-full border border-border bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground"
                >
                  <BadgeCheck class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" />
                  Verified
                </span>
              </component>
              <Badge :variant="partnered ? 'success' : 'outline'">
                {{ partnered ? 'Partnered with your school' : 'Not yet available' }}
              </Badge>
              <Badge v-if="isClosed" variant="destructive">Closed</Badge>
            </div>

            <h1 class="text-3xl font-semibold tracking-tight text-foreground">
              {{ internship.title || 'Untitled internship' }}
            </h1>

            <ApplicationTrackerPanel
              v-if="myApplication"
              :application-id="myApplication.id"
              mode="student"
            />

            <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground">
              <span class="inline-flex items-center gap-1.5">
                <MapPin class="h-3.5 w-3.5" />
                {{ internship.location || 'Cavite' }}
              </span>
              <span class="inline-flex items-center gap-1.5">
                <BriefcaseBusiness class="h-3.5 w-3.5" />
                {{ workSetupLabel(internship) }}
              </span>
              <span class="inline-flex items-center gap-1.5">
                <Wallet class="h-3.5 w-3.5" />
                {{ formatAllowanceDisplay(internship.allowance) }}
              </span>
              <span class="inline-flex items-center gap-1.5">
                <CalendarDays class="h-3.5 w-3.5" />
                Start {{ formatDate(internship.startDate) }}
              </span>
            </div>
          </div>

          <section class="space-y-2">
            <h2 class="text-lg font-semibold text-foreground">About this role</h2>
            <p class="whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
              {{ internship.description?.trim() || 'No description provided yet.' }}
            </p>
          </section>

          <section v-if="internship.perks?.length" class="space-y-2">
            <h2 class="text-lg font-semibold text-foreground">Perks</h2>
            <div class="flex flex-wrap gap-2">
              <Badge v-for="perk in internship.perks" :key="perk" variant="secondary">{{ perk }}</Badge>
            </div>
          </section>

          <section class="space-y-3">
            <h2 class="text-lg font-semibold text-foreground">Skills</h2>
            <SkillChips
              :skills="jobSkillItems"
              mode="job"
              empty-text="No skills listed for this role yet."
            />
            <div
              v-if="skillMatchSummary"
              class="rounded-xl border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
            >
              <p class="font-medium text-foreground">How your skills compare</p>
              <p class="mt-1">
                You match
                <span class="font-semibold text-foreground">
                  {{ skillMatchSummary.matchedRequired.length }} of {{ skillMatchSummary.requiredTotal }}
                </span>
                required skill{{ skillMatchSummary.requiredTotal === 1 ? '' : 's' }}
                <template v-if="skillMatchSummary.niceTotal">
                  ·
                  {{ skillMatchSummary.matchedNice.length }} of {{ skillMatchSummary.niceTotal }} nice-to-have
                </template>
                .
                This is informational only and does not block applying.
              </p>
              <div v-if="skillMatchSummary.missingRequired.length" class="mt-3 space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Still missing</p>
                <SkillChips :skills="skillMatchSummary.missingRequired" mode="job" />
              </div>
              <p v-else-if="skillMatchSummary.requiredTotal" class="mt-2 text-foreground">
                You already cover the required skills listed for this role.
              </p>
              <p v-if="!mySkills.length" class="mt-2">
                Add skills on your Intern profile so hosts can see what you know.
              </p>
            </div>
          </section>

          <section class="space-y-2">
            <h2 class="text-lg font-semibold text-foreground">Requirements</h2>
            <ul v-if="internship.requirements?.length" class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
              <li v-for="requirement in internship.requirements" :key="requirement">{{ requirement }}</li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">No specific requirements listed.</p>
          </section>

          <section class="space-y-2">
            <h2 class="text-lg font-semibold text-foreground">Accepted courses</h2>
            <div class="flex flex-wrap gap-2">
              <Badge v-for="course in internship.eligibleCourses || []" :key="course" variant="outline">
                {{ course }}
              </Badge>
              <Badge v-if="!(internship.eligibleCourses || []).length" variant="outline">Open to all courses</Badge>
            </div>
          </section>

          <section class="grid gap-3 text-sm text-muted-foreground sm:grid-cols-2">
            <div class="rounded-xl border border-border bg-card p-4">
              <p class="font-medium text-foreground">Schedule</p>
              <p class="mt-1">{{ internship.schedule?.trim() || internship.scheduleType || 'Schedule TBA' }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
              <p class="font-medium text-foreground">Application deadline</p>
              <p class="mt-1">{{ formatDate(internship.applicationDeadline) }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 sm:col-span-2">
              <ApplicationCapacity
                :slots-available="internship.slotsAvailable"
                :slots-remaining="internship.slotsRemaining"
                :filled-slots="internship.filledSlots"
              />
            </div>
            <div class="rounded-xl border border-border bg-card p-4">
              <p class="font-medium text-foreground">Duration</p>
              <p class="mt-1">{{ internship.duration?.trim() || 'TBA' }}</p>
            </div>
          </section>
        </div>

        <aside class="hidden lg:block">
          <div class="sticky top-6 space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <ApplicationCapacity
              :slots-available="internship.slotsAvailable"
              :slots-remaining="internship.slotsRemaining"
              :filled-slots="internship.filledSlots"
            />
            <p v-if="isClosed" class="text-sm text-muted-foreground">
              {{ deadlinePassed ? 'The application deadline has passed.' : 'No slots remaining.' }}
            </p>
            <p v-else-if="isAuthenticatedStudent && hasActivePlacement" class="text-sm text-muted-foreground">
              {{ placementBlockMessage }}
            </p>
            <p v-else-if="isAuthenticatedStudent && !partnered" class="text-sm text-muted-foreground">
              Ask your school to partner with this company
            </p>
            <p v-else-if="isGuestOrPublic" class="text-sm text-muted-foreground">
              Log in as a student to apply through your school partnership.
            </p>
            <div class="flex items-center gap-2">
              <Button class="flex-1" :disabled="applyDisabled" @click="onApply">
                {{ applyLabel }}
              </Button>
              <SaveInternshipButton
                :internship-id="internship.id"
                :saved="Boolean(internship.saved)"
                @update:saved="internship.saved = $event"
              />
            </div>
            <RouterLink
              v-if="orgProfileTo"
              :to="orgProfileTo"
              class="inline-flex h-10 w-full items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground hover:bg-accent"
            >
              View organization
            </RouterLink>
          </div>
        </aside>
      </div>
    </div>

    <div
      v-if="internship && !loading"
      class="fixed inset-x-0 bottom-0 z-header border-t border-border bg-card/95 p-3 backdrop-blur lg:hidden"
    >
      <div class="mx-auto flex max-w-7xl items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-foreground">{{ internship.title }}</p>
          <p class="truncate text-xs text-muted-foreground">
            {{ isClosed ? 'Closed' : `${slotsLeft} slot${slotsLeft === 1 ? '' : 's'} remaining` }}
          </p>
        </div>
        <SaveInternshipButton
          :internship-id="internship.id"
          :saved="Boolean(internship.saved)"
          size="sm"
          @update:saved="internship.saved = $event"
        />
        <Button :disabled="applyDisabled" @click="onApply">{{ applyLabel }}</Button>
      </div>
    </div>
  </component>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  BriefcaseBusiness,
  CheckCircle2,
  Clock3,
  Eye,
  FileText,
  LoaderCircle,
  MessageSquare,
  Search,
  School as SchoolIcon,
  UserRoundPlus,
  Users,
  XCircle,
} from 'lucide-vue-next'

import AlertDialog from '@/components/ui/alert-dialog/AlertDialog.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'
import Input from '@/components/ui/input/Input.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import Table from '@/components/ui/table/Table.vue'
import TableBody from '@/components/ui/table/TableBody.vue'
import TableCell from '@/components/ui/table/TableCell.vue'
import TableHead from '@/components/ui/table/TableHead.vue'
import TableHeader from '@/components/ui/table/TableHeader.vue'
import TableRow from '@/components/ui/table/TableRow.vue'
import ApplicationAssessmentPanel from '@/components/ApplicationAssessmentPanel.vue'
import ApplicationInterviewPanel from '@/components/ApplicationInterviewPanel.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { subscribeAllApplications, updateApplicationStatus, type ApplicationRecord } from '@/services/applications'
import { subscribeSchoolContracts, type ContractRecord } from '@/services/contracts'
import { subscribeInternships, type InternshipRecord } from '@/services/internships'
import { generateCertificate } from '@/services/certificates'
import { exportReport, fetchKpiReport, fetchStudentProgress, type KpiReport, type StudentProgressRow } from '@/services/kpi'
import { hasPermission } from '@/services/permissions'
import { subscribeSchoolStudents, type SchoolStudentRow } from '@/services/schoolStudents'
import { useAuthStore } from '@/stores/auth'

type SchoolView = 'dashboard' | 'student-interns' | 'opportunities' | 'applications' | 'placements' | 'reports'
type PendingDecision = { applicationId: string; action: 'endorsed' | 'school_rejected'; internName: string } | null
type EndorsementDetail = {
  id: string
  internName: string
  email: string
  course: string
  company: string
  school: string
  internshipTitle: string
  appliedAt: string
  status: string
}

const currentView = ref<SchoolView>('dashboard')
const { success, error } = useToast()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const students = ref<SchoolStudentRow[]>([])
const applications = ref<ApplicationRecord[]>([])
const internships = ref<InternshipRecord[]>([])
const contracts = ref<ContractRecord[]>([])
const loadingStudents = ref(true)
const loadingApplications = ref(true)
const loadingInternships = ref(true)
const loadingContracts = ref(true)
const searchQuery = ref('')
const pendingDecision = ref<PendingDecision>(null)
const submittingDecision = ref(false)
const selectedEndorsement = ref<EndorsementDetail | null>(null)
const studentProgress = ref<StudentProgressRow[]>([])
const loadingProgress = ref(false)
const kpi = ref<KpiReport | null>(null)
const exportingReport = ref(false)

let unsubscribeStudents: (() => void) | null = null
let unsubscribeInternships: (() => void) | null = null
let unsubscribeContracts: (() => void) | null = null
let unsubscribeApplications: (() => void) | null = null

function stopSchoolSubscriptions() {
  unsubscribeStudents?.()
  unsubscribeInternships?.()
  unsubscribeContracts?.()
  unsubscribeApplications?.()
  unsubscribeStudents = null
  unsubscribeInternships = null
  unsubscribeContracts = null
  unsubscribeApplications = null
}

function startSchoolSubscriptions(userId?: string) {
  stopSchoolSubscriptions()

  loadingStudents.value = true
  loadingApplications.value = true
  loadingInternships.value = true
  loadingContracts.value = true

  unsubscribeStudents = subscribeSchoolStudents('', (items) => {
    students.value = items
    loadingStudents.value = false
  })

  unsubscribeInternships = subscribeInternships((items) => {
    internships.value = items
    loadingInternships.value = false
  })

  if (userId) {
    unsubscribeContracts = subscribeSchoolContracts(userId, (items) => {
      contracts.value = items
      loadingContracts.value = false
    })
  } else {
    contracts.value = []
    loadingContracts.value = false
  }

  unsubscribeApplications = subscribeAllApplications((items) => {
    applications.value = items
    loadingApplications.value = false
  })
}

const pageTitle = computed(() => {
  const titles: Record<SchoolView, string> = {
    dashboard: 'Dashboard',
    'student-interns': 'Interns List',
    opportunities: 'Opportunities',
    applications: 'Endorsements',
    placements: 'Placements',
    reports: 'Reports',
  }
  return titles[currentView.value]
})

const activeItem = computed(() => currentView.value)

const searchableInternRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return students.value
    .map((student) => {
      const relatedApplication = applications.value.find((application) => application.studentId === student.id || application.studentEmail?.toLowerCase() === student.email.toLowerCase())

      return {
        id: student.id,
        name: student.studentName || 'Unnamed Intern',
        course: student.course || 'Not set',
        company: relatedApplication?.companyName || 'No application yet',
        status: normalizeStatus(relatedApplication?.status || student.status),
        email: student.email,
      }
    })
    .filter((row) =>
      !query ||
      [row.name, row.course, row.company, row.email].some((value) => value.toLowerCase().includes(query)),
    )
})

const activeContractCompanyIds = computed(() =>
  new Set(
    contracts.value
      .filter((contract) => String(contract.status || '').trim().toLowerCase() === 'active')
      .map((contract) => String(contract.companyId || '').trim())
      .filter(Boolean),
  ),
)

const visibleOpportunities = computed(() => {
  const schoolUserId = String(authStore.user?.uid || '').trim()

  return internships.value.filter((internship) => {
    if (internship.hostType === 'school') {
      return String(internship.schoolId || '').trim() === schoolUserId
    }

    return activeContractCompanyIds.value.has(String(internship.companyId || '').trim())
  })
})

const opportunityRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return visibleOpportunities.value
    .map((internship) => ({
      id: internship.id,
      title: internship.title || 'Untitled placement',
      hostName: internship.companyName || internship.hostName || internship.schoolName || 'Host not set',
      hostType: internship.hostType === 'school' ? 'School' : 'Company',
      location: internship.location || 'Location not set',
      status: normalizeOpportunityStatus(internship.status),
      programs: (internship.eligibleCourses || []).join(', ') || 'Open to endorsed students',
      slots: internship.slotsAvailable || 0,
    }))
    .filter((row) =>
      !query ||
      [row.title, row.hostName, row.hostType, row.location, row.programs].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const pendingEndorsements = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return applications.value
    .filter((application) => normalizeStatus(application.status) === 'submitted')
    .map((application) => ({
      id: application.id,
      internName: application.studentName || 'Unnamed Intern',
      email: application.studentEmail || 'No email provided',
      course: application.studentCourse || 'Not set',
      company: application.companyName || 'Pending assignment',
      internshipTitle: application.internshipTitle || 'Untitled position',
      appliedAt: formatDate(application.createdAt),
      school: application.schoolName || 'Current school',
      status: normalizeStatus(application.status),
    }))
    .filter((row) =>
      !query ||
      [row.internName, row.email, row.course, row.company, row.school, row.internshipTitle].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const schoolDisplayName = computed(() => {
  const user = authStore.user as Record<string, unknown> | null
  const school = (user?.school ?? null) as Record<string, unknown> | null
  const profile = (user?.profile ?? null) as Record<string, unknown> | null
  return (
    String(school?.schoolName || school?.name || profile?.schoolName || user?.name || 'School workspace')
  )
})

const openOpportunitiesCount = computed(
  () => visibleOpportunities.value.filter((internship) => normalizeOpportunityStatus(internship.status) === 'active').length,
)

const dashboardStats = computed(() => {
  const totalInterns = students.value.length
  const pendingCount = applications.value.filter((application) => normalizeStatus(application.status) === 'submitted').length
  const activePlacements = applications.value.filter((application) => normalizeStatus(application.status) === 'accepted').length

  return [
    {
      label: 'Total Interns',
      value: totalInterns,
      icon: Users,
      iconClass: 'bg-accent text-primary',
      caption: 'On your roster',
    },
    {
      label: 'Pending Endorsements',
      value: pendingCount,
      icon: Clock3,
      iconClass: 'bg-amber-100 text-amber-700',
      caption: 'Awaiting school action',
    },
    {
      label: 'Active Placements',
      value: activePlacements,
      icon: BriefcaseBusiness,
      iconClass: 'bg-emerald-100 text-emerald-700',
      caption: 'Accepted by partners',
    },
    {
      label: 'Open Opportunities',
      value: openOpportunitiesCount.value,
      icon: BriefcaseBusiness,
      iconClass: 'bg-violet-100 text-violet-700',
      caption: 'Visible to students',
    },
  ]
})

const attentionEndorsements = computed(() => pendingEndorsements.value.slice(0, 5))

const compactQuickActions = computed(() => [
  {
    label: 'Student Accounts',
    icon: UserRoundPlus,
    action: () => router.push({ name: 'school-students' }),
  },
  {
    label: 'Agreements',
    icon: FileText,
    action: () => router.push({ name: 'agreements' }),
  },
  {
    label: 'Opportunities',
    icon: BriefcaseBusiness,
    action: () => router.push({ name: 'school-opportunities' }),
  },
  {
    label: 'Messages',
    icon: MessageSquare,
    action: () => window.dispatchEvent(new CustomEvent('chat:open')),
  },
])

const placementRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()

  return applications.value
    .filter((application) => normalizeStatus(application.status) === 'accepted')
    .map((application) => ({
      id: application.id,
      internName: application.studentName || 'Unnamed Intern',
      program: application.studentCourse || 'Program not set',
      host: application.companyName || application.schoolName || 'Placement not assigned',
      placementType: application.companyName ? 'Company-hosted' : 'School-hosted',
      updatedAt: formatDate(application.updatedAt || application.createdAt),
      status: normalizeStatus(application.status),
    }))
    .filter((row) =>
      !query ||
      [row.internName, row.program, row.host, row.placementType].some((value) =>
        value.toLowerCase().includes(query),
      ),
    )
})

const reportCards = computed(() => {
  const accepted = applications.value.filter((application) => normalizeStatus(application.status) === 'accepted').length
  const pending = applications.value.filter((application) => normalizeStatus(application.status) === 'submitted').length
  const schoolHosted = visibleOpportunities.value.filter((internship) => internship.hostType === 'school').length
  const companyHosted = visibleOpportunities.value.filter((internship) => internship.hostType !== 'school').length

  return [
    { label: 'Placements Confirmed', value: accepted, copy: 'Students already accepted by company partners.' },
    { label: 'Pending Endorsements', value: pending, copy: 'Students still waiting for school endorsement.' },
    { label: 'School-hosted Roles', value: schoolHosted, copy: 'Opportunities that can support education or campus-based OJT.' },
    { label: 'Company-hosted Roles', value: companyHosted, copy: 'Industry placements currently visible to your students.' },
  ]
})

function normalizeStatus(status?: string | null) {
  const normalized = String(status || 'submitted').trim().toLowerCase()
  if (['submitted', 'pending'].includes(normalized)) return 'submitted'
  if (normalized === 'endorsed') return 'endorsed'
  if (['accepted', 'approved', 'active', 'registered'].includes(normalized)) return 'accepted'
  if (['school_rejected', 'rejected', 'declined', 'disabled'].includes(normalized)) return 'rejected'
  return 'submitted'
}

function normalizeOpportunityStatus(status?: string | null) {
  const normalized = String(status || 'draft').trim().toLowerCase()
  if (normalized === 'active') return 'active'
  if (normalized === 'closed') return 'closed'
  return 'draft'
}

function schoolViewFromRouteName(name: unknown): SchoolView {
  if (name === 'school-interns') return 'student-interns'
  if (name === 'school-opportunities') return 'opportunities'
  if (name === 'school-endorsements') return 'applications'
  if (name === 'school-placements') return 'placements'
  if (name === 'school-reports') return 'reports'
  return 'dashboard'
}

function badgeLabel(status: string) {
  if (status === 'accepted') return 'Accepted'
  if (status === 'endorsed') return 'Endorsed'
  if (status === 'rejected') return 'Rejected'
  return 'Submitted'
}

function badgeVariant(status: string) {
  if (status === 'accepted') return 'success'
  if (status === 'endorsed') return 'outline'
  if (status === 'rejected') return 'destructive'
  return 'warning'
}

function opportunityBadgeLabel(status: string) {
  if (status === 'active') return 'Open'
  if (status === 'closed') return 'Closed'
  return 'Draft'
}

function opportunityBadgeVariant(status: string) {
  if (status === 'active') return 'success'
  if (status === 'closed') return 'destructive'
  return 'warning'
}

function formatDate(value?: string) {
  if (!value) return '—'
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? '—'
    : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

async function loadApplications() {
  loadingApplications.value = false
}

function openDecisionDialog(applicationId: string, action: 'endorsed' | 'school_rejected', internName: string) {
  pendingDecision.value = { applicationId, action, internName }
}

function openEndorsementDetail(endorsement: EndorsementDetail) {
  selectedEndorsement.value = endorsement
}

async function confirmDecision() {
  if (!pendingDecision.value) return
  submittingDecision.value = true
  try {
    await updateApplicationStatus(pendingDecision.value.applicationId, pendingDecision.value.action)
    await loadApplications()
    success(
      pendingDecision.value.action === 'endorsed' ? 'Student endorsed.' : 'Endorsement rejected.',
      {
        description: `${pendingDecision.value.internName} has been updated.`,
      },
    )
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update endorsement.',
    })
  } finally {
    submittingDecision.value = false
    pendingDecision.value = null
  }
}

function handleMenuClick(item: string) {
  const next = ['dashboard', 'student-interns', 'opportunities', 'applications', 'placements', 'reports'].includes(item)
    ? (item as SchoolView)
    : 'dashboard'
  currentView.value = next
  const targetName =
    next === 'student-interns'
      ? 'school-interns'
      : next === 'opportunities'
        ? 'school-opportunities'
      : next === 'applications'
        ? 'school-endorsements'
        : next === 'placements'
          ? 'school-placements'
          : next === 'reports'
            ? 'school-reports'
        : 'school'
  void router.push({ name: targetName })
}

async function loadStudentProgress() {
  loadingProgress.value = true
  try {
    studentProgress.value = await fetchStudentProgress()
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load student OJT progress.' })
    studentProgress.value = []
  } finally {
    loadingProgress.value = false
  }
}

async function loadKpi() {
  try {
    kpi.value = await fetchKpiReport()
  } catch {
    kpi.value = null
  }
}

async function downloadReport(type: 'placements' | 'ojt' | 'assessments') {
  exportingReport.value = true
  try {
    const blob = await exportReport(type)
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `${type}-report.csv`
    anchor.click()
    URL.revokeObjectURL(url)
    success('Report downloaded.')
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to export report.' })
  } finally {
    exportingReport.value = false
  }
}

async function issueCertificate(applicationId: string) {
  if (!hasPermission('org.approve_certificates')) {
    error('Missing permission to issue certificates.')
    return
  }
  try {
    await generateCertificate(applicationId)
    success('Certificate of completion issued.')
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to generate certificate. Ensure placement is accepted and OJT hours meet the requirement.' })
  }
}

onMounted(() => {
  currentView.value = schoolViewFromRouteName(route.name)
  startSchoolSubscriptions(authStore.user?.uid)
  if (currentView.value === 'dashboard' || currentView.value === 'reports' || currentView.value === 'placements') {
    void loadKpi()
  }
  if (currentView.value === 'reports' || currentView.value === 'placements') {
    void loadStudentProgress()
  }
})

onUnmounted(() => {
  stopSchoolSubscriptions()
})

watch(
  () => route.name,
  (name) => {
    currentView.value = schoolViewFromRouteName(name)
  },
)

watch(currentView, (view) => {
  if (view === 'dashboard' || view === 'reports' || view === 'placements') {
    void loadKpi()
  }
  if (view === 'reports' || view === 'placements') {
    void loadStudentProgress()
  }
})

watch(
  () => authStore.user?.uid,
  (userId) => {
    startSchoolSubscriptions(userId)
  },
)
</script>

<template>
  <MainLayout role="school" :title="pageTitle" :active-item="activeItem" @navigate="handleMenuClick($event.key)">
    <div class="space-y-6">
      <template v-if="currentView === 'dashboard'">
        <section class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
          <div class="border-b border-border bg-gradient-to-r from-accent via-card to-emerald-50/60 px-6 py-6 sm:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-accent text-primary">
                  <SchoolIcon class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Overview</p>
                  <h2 class="mt-1 text-2xl font-semibold tracking-tight text-foreground">{{ schoolDisplayName }}</h2>
                  <p class="mt-1 text-sm text-muted-foreground">
                    {{ dashboardStats[1]?.value || 0 }} endorsement{{ (dashboardStats[1]?.value || 0) === 1 ? '' : 's' }} waiting ·
                    {{ dashboardStats[2]?.value || 0 }} active placement{{ (dashboardStats[2]?.value || 0) === 1 ? '' : 's' }}
                  </p>
                </div>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" @click="handleMenuClick('applications')">Review endorsements</Button>
                <Button size="sm" @click="handleMenuClick('reports')">Open reports</Button>
              </div>
            </div>
          </div>

          <div class="grid gap-px bg-muted sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="stat in dashboardStats" :key="stat.label" class="bg-card px-5 py-5">
              <template v-if="loadingStudents || loadingApplications || loadingInternships">
                <Skeleton class="h-9 w-9 rounded-xl" />
                <Skeleton class="mt-4 h-3 w-24" />
                <Skeleton class="mt-2 h-8 w-14" />
              </template>
              <template v-else>
                <div class="flex items-center justify-between gap-3">
                  <div :class="['flex h-9 w-9 items-center justify-center rounded-xl', stat.iconClass]">
                    <component :is="stat.icon" class="h-4 w-4" />
                  </div>
                  <p class="text-xs font-medium text-muted-foreground">{{ stat.caption }}</p>
                </div>
                <p class="mt-4 text-sm font-medium text-muted-foreground">{{ stat.label }}</p>
                <p class="mt-1 text-3xl font-semibold tracking-tight text-foreground">{{ stat.value }}</p>
              </template>
            </div>
          </div>
        </section>

        <section v-if="kpi" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Placement rate</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.placementRate }}%</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Agreements</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.totalAgreements }}</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Avg approved OJT hrs</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.avgApprovedOjtHours }}</p>
          </div>
          <div class="rounded-2xl border border-border bg-card px-5 py-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Avg assessment score</p>
            <p class="mt-2 text-2xl font-semibold text-foreground">{{ kpi.avgAssessmentScore }}</p>
          </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
          <section class="rounded-2xl border border-border bg-card shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
              <div>
                <h3 class="text-lg font-semibold text-foreground">Needs attention</h3>
                <p class="text-sm text-muted-foreground">Pending endorsements that need a school decision.</p>
              </div>
              <Button size="sm" variant="outline" @click="handleMenuClick('applications')">View all</Button>
            </div>
            <div class="divide-y divide-border">
              <template v-if="loadingApplications">
                <div v-for="index in 3" :key="index" class="space-y-2 px-5 py-4">
                  <Skeleton class="h-4 w-40" />
                  <Skeleton class="h-3 w-56" />
                </div>
              </template>
              <template v-else-if="attentionEndorsements.length">
                <div
                  v-for="item in attentionEndorsements"
                  :key="item.id"
                  class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-foreground">{{ item.internName }}</p>
                    <p class="truncate text-sm text-muted-foreground">
                      {{ item.internshipTitle }} · {{ item.company }}
                    </p>
                  </div>
                  <Button size="sm" variant="outline" class="shrink-0" @click="handleMenuClick('applications')">
                    Review
                  </Button>
                </div>
              </template>
              <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                No pending endorsements right now.
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-foreground">Quick actions</h3>
            <p class="mt-1 text-sm text-muted-foreground">Jump to common school workflows.</p>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
              <button
                v-for="action in compactQuickActions"
                :key="action.label"
                type="button"
                class="flex items-center gap-3 rounded-xl border border-border px-4 py-3 text-left transition hover:border-border hover:bg-accent/60"
                @click="action.action()"
              >
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-muted text-foreground">
                  <component :is="action.icon" class="h-4 w-4" />
                </span>
                <span class="text-sm font-medium text-foreground">{{ action.label }}</span>
              </button>
            </div>
          </section>
        </div>
      </template>

      <Card v-else-if="currentView === 'opportunities'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Internship Opportunities</h2>
            <p class="text-sm text-muted-foreground">Review school-hosted placements and company posts from partners with active contracts.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search opportunities..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="mb-4 rounded-2xl border border-border bg-accent p-4 text-sm text-foreground">
            <p class="font-medium">Student-visible opportunities</p>
            <p class="mt-2">
              This list now mirrors what students can access: your school-hosted posts and company opportunities from active partner contracts.
            </p>
          </div>
          <div class="rounded-xl border border-border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Opportunity</TableHead>
                  <TableHead>Host</TableHead>
                  <TableHead>Programs</TableHead>
                  <TableHead>Slots</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead class="text-right">Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingInternships || loadingContracts">
                  <TableRow v-for="index in 5" :key="index">
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-44" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-12" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-20" /></TableCell>
                    <TableCell><Skeleton class="ml-auto h-9 w-32" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="opportunityRows.length">
                  <TableRow v-for="opportunity in opportunityRows" :key="opportunity.id">
                    <TableCell>
                      <div class="space-y-1">
                        <p class="font-medium text-foreground">{{ opportunity.title }}</p>
                        <p class="text-sm text-muted-foreground">{{ opportunity.location }}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div class="space-y-1">
                        <p class="text-foreground">{{ opportunity.hostName }}</p>
                        <p class="text-sm text-muted-foreground">{{ opportunity.hostType }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-foreground">{{ opportunity.programs }}</TableCell>
                    <TableCell class="text-foreground">{{ opportunity.slots }}</TableCell>
                    <TableCell>
                      <Badge :variant="opportunityBadgeVariant(opportunity.status)">{{ opportunityBadgeLabel(opportunity.status) }}</Badge>
                    </TableCell>
                    <TableCell class="text-right">
                      <Button variant="outline" size="sm" @click="router.push({ name: 'school-endorsements' })">
                        Endorse Student
                      </Button>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                    No student-visible opportunities matched your search.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="currentView === 'student-interns'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Interns List</h2>
            <p class="text-sm text-muted-foreground">Search enrolled interns, review their course, and see the company tied to the latest application.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search interns..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="rounded-xl border border-border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Intern Name</TableHead>
                  <TableHead>Course</TableHead>
                  <TableHead>Company Applied To</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingStudents || loadingApplications">
                  <TableRow v-for="index in 5" :key="index">
                    <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="searchableInternRows.length">
                  <TableRow v-for="intern in searchableInternRows" :key="intern.id">
                    <TableCell>
                      <div class="space-y-1">
                        <p class="font-medium text-foreground">{{ intern.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ intern.email }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-foreground">{{ intern.course }}</TableCell>
                    <TableCell class="text-foreground">{{ intern.company }}</TableCell>
                    <TableCell>
                      <Badge :variant="badgeVariant(intern.status)">{{ badgeLabel(intern.status) }}</Badge>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="4" class="py-10 text-center text-muted-foreground">
                    No intern records matched your search.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="currentView === 'placements'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Placements</h2>
            <p class="text-sm text-muted-foreground">Track students who already have company-accepted placements and review where each intern has been assigned.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search placements..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="rounded-xl border border-border bg-card">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Intern</TableHead>
                  <TableHead>Program</TableHead>
                  <TableHead>Host</TableHead>
                  <TableHead>Placement Type</TableHead>
                  <TableHead>Updated</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead class="text-right">Certificate</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <template v-if="loadingApplications">
                  <TableRow v-for="index in 4" :key="index">
                    <TableCell><Skeleton class="h-5 w-36" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-40" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-28" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-24" /></TableCell>
                    <TableCell><Skeleton class="h-5 w-20" /></TableCell>
                    <TableCell><Skeleton class="ml-auto h-5 w-20" /></TableCell>
                  </TableRow>
                </template>
                <template v-else-if="placementRows.length">
                  <TableRow v-for="placement in placementRows" :key="placement.id">
                    <TableCell class="font-medium text-foreground">{{ placement.internName }}</TableCell>
                    <TableCell class="text-foreground">{{ placement.program }}</TableCell>
                    <TableCell class="text-foreground">{{ placement.host }}</TableCell>
                    <TableCell class="text-foreground">{{ placement.placementType }}</TableCell>
                    <TableCell class="text-muted-foreground">{{ placement.updatedAt }}</TableCell>
                    <TableCell>
                      <Badge :variant="badgeVariant(placement.status)">{{ badgeLabel(placement.status) }}</Badge>
                    </TableCell>
                    <TableCell class="text-right">
                      <Button size="sm" variant="outline" @click="issueCertificate(placement.id)">
                        Issue
                      </Button>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                    No accepted placements matched your search.
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      <Card v-else-if="currentView === 'reports'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-foreground">Reports</h2>
          <p class="text-sm text-muted-foreground">Use these summaries to monitor endorsement flow, school-based opportunities, and placement coverage.</p>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card v-for="report in reportCards" :key="report.label" class="border-border/70 shadow-none">
              <CardContent class="p-5">
                <p class="text-sm font-medium text-muted-foreground">{{ report.label }}</p>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ report.value }}</p>
                <p class="mt-3 text-sm text-muted-foreground">{{ report.copy }}</p>
              </CardContent>
            </Card>
          </div>

          <div v-if="kpi" class="grid gap-4 md:grid-cols-4">
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">KPI placement rate</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ kpi.placementRate }}%</p>
              </CardContent>
            </Card>
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">Agreements</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ kpi.totalAgreements }}</p>
              </CardContent>
            </Card>
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">Avg approved OJT hrs</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ kpi.avgApprovedOjtHours }}</p>
              </CardContent>
            </Card>
            <Card class="border-border/70 shadow-none">
              <CardContent class="p-4">
                <p class="text-xs font-medium text-muted-foreground">Avg assessment score</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ kpi.avgAssessmentScore }}</p>
              </CardContent>
            </Card>
          </div>

          <div class="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" :disabled="exportingReport" @click="downloadReport('placements')">
              Export placements
            </Button>
            <Button size="sm" variant="outline" :disabled="exportingReport" @click="downloadReport('ojt')">
              Export OJT hours
            </Button>
            <Button size="sm" variant="outline" :disabled="exportingReport" @click="downloadReport('assessments')">
              Export assessments
            </Button>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-lg font-semibold text-foreground">Student OJT Progress</h3>
                <p class="text-sm text-muted-foreground">Approved hours, application status, and latest assessment for department monitoring.</p>
              </div>
              <Button variant="outline" size="sm" :disabled="loadingProgress" @click="loadStudentProgress">
                <LoaderCircle v-if="loadingProgress" class="h-4 w-4 animate-spin" />
                <span>Refresh</span>
              </Button>
            </div>
            <div class="overflow-x-auto rounded-xl border border-border/70">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Student</TableHead>
                    <TableHead>Application</TableHead>
                    <TableHead>Approved Hours</TableHead>
                    <TableHead>Progress</TableHead>
                    <TableHead>Latest Assessment</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <template v-if="loadingProgress">
                    <TableRow v-for="index in 3" :key="index">
                      <TableCell colspan="5"><Skeleton class="h-6 w-full" /></TableCell>
                    </TableRow>
                  </template>
                  <template v-else-if="studentProgress.length">
                    <TableRow v-for="row in studentProgress" :key="row.studentId">
                      <TableCell>
                        <div class="font-medium text-foreground">{{ row.name }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.course || '—' }}</div>
                      </TableCell>
                      <TableCell>
                        <div class="text-sm text-foreground">{{ row.internshipTitle || '—' }}</div>
                        <Badge class="mt-1" :variant="badgeVariant(row.applicationStatus || '')">
                          {{ badgeLabel(row.applicationStatus || 'none') }}
                        </Badge>
                      </TableCell>
                      <TableCell class="text-foreground">
                        {{ row.approvedOjtHours }} / {{ row.requiredOjtHours }}
                      </TableCell>
                      <TableCell class="text-foreground">{{ row.ojtPercentComplete }}%</TableCell>
                      <TableCell class="text-muted-foreground">
                        <template v-if="row.latestAssessment">
                          {{ row.latestAssessment.stage }}
                          <span v-if="row.latestAssessment.overallScore != null">
                            · {{ row.latestAssessment.overallScore }}
                          </span>
                        </template>
                        <span v-else>—</span>
                      </TableCell>
                    </TableRow>
                  </template>
                  <TableRow v-else>
                    <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                      No student progress records yet.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card v-else class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Endorsements</h2>
            <p class="text-sm text-muted-foreground">Review newly submitted applications and either endorse them to the company or reject them at the school level.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="searchQuery" placeholder="Search pending endorsements..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent class="space-y-4">
          <template v-if="loadingApplications">
            <Card v-for="index in 3" :key="index" class="border-border/70 shadow-none">
              <CardContent class="space-y-3 p-5">
                <Skeleton class="h-5 w-40" />
                <Skeleton class="h-4 w-56" />
                <Skeleton class="h-4 w-32" />
                <div class="flex gap-3 pt-2">
                  <Skeleton class="h-10 w-24" />
                  <Skeleton class="h-10 w-24" />
                </div>
              </CardContent>
            </Card>
          </template>
          <template v-else-if="pendingEndorsements.length">
            <Card v-for="endorsement in pendingEndorsements" :key="endorsement.id" class="border-border/70 shadow-none">
              <CardContent class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between">
                <div class="space-y-2">
                  <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold text-foreground">{{ endorsement.internName }}</h3>
                    <Badge variant="warning">Submitted</Badge>
                  </div>
                  <p class="text-sm text-muted-foreground">{{ endorsement.course }} · {{ endorsement.company }}</p>
                  <p class="text-sm text-muted-foreground">Applied {{ endorsement.appliedAt }}</p>
                </div>
                <div class="flex gap-3">
                  <Button
                    variant="ghost"
                    class="gap-2"
                    :disabled="submittingDecision"
                    @click="openEndorsementDetail(endorsement)"
                  >
                    <Eye class="h-4 w-4" />
                    View
                  </Button>
                  <Button
                    class="gap-2"
                    :disabled="submittingDecision"
                    @click="openDecisionDialog(endorsement.id, 'endorsed', endorsement.internName)"
                  >
                    <CheckCircle2 class="h-4 w-4" />
                    Endorse
                  </Button>
                  <Button
                    variant="outline"
                    class="gap-2"
                    :disabled="submittingDecision"
                    @click="openDecisionDialog(endorsement.id, 'school_rejected', endorsement.internName)"
                  >
                    <XCircle class="h-4 w-4" />
                    Reject
                  </Button>
                </div>
              </CardContent>
            </Card>
          </template>
          <Card v-else class="border-dashed border-border/80 shadow-none">
            <CardContent class="py-10 text-center text-muted-foreground">
              No submitted applications are waiting for school endorsement.
            </CardContent>
          </Card>
        </CardContent>
      </Card>
    </div>

    <AlertDialog
      :open="!!pendingDecision"
      :title="pendingDecision?.action === 'endorsed' ? 'Endorse application?' : 'Reject endorsement?'"
      :description="
        pendingDecision
          ? `${pendingDecision.action === 'endorsed' ? 'Endorse' : 'Reject'} ${pendingDecision.internName}'s application at the school stage?`
          : ''
      "
      :action-label="pendingDecision?.action === 'endorsed' ? 'Endorse' : 'Reject'"
      :action-variant="pendingDecision?.action === 'endorsed' ? 'default' : 'outline'"
      @update:open="(open) => { if (!open) pendingDecision = null }"
      @action="confirmDecision"
    />

    <Dialog :open="!!selectedEndorsement" @update:open="(open) => { if (!open) selectedEndorsement = null }">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>{{ selectedEndorsement?.internName || 'Intern Application' }}</DialogTitle>
          <p class="text-sm text-muted-foreground">Review this intern's submitted endorsement details before taking action.</p>
        </DialogHeader>

        <div v-if="selectedEndorsement" class="mt-6 space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Email</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.email }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Course</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.course }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Internship</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.internshipTitle }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Company</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.company }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">School</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.school }}</p>
            </div>
            <div class="rounded-xl bg-muted p-4">
              <p class="text-sm text-muted-foreground">Applied</p>
              <p class="mt-2 font-medium text-foreground">{{ selectedEndorsement.appliedAt }}</p>
            </div>
          </div>

          <div class="flex items-center justify-between rounded-xl border border-border/70 bg-card p-4">
            <div>
              <p class="text-sm text-muted-foreground">Current Status</p>
              <p class="mt-2">
                <Badge :variant="badgeVariant(selectedEndorsement.status)">{{ badgeLabel(selectedEndorsement.status) }}</Badge>
              </p>
            </div>
          </div>

          <ApplicationInterviewPanel :application-id="selectedEndorsement.id" mode="manage" />
          <ApplicationAssessmentPanel :application-id="selectedEndorsement.id" />
        </div>

        <div class="mt-6 flex justify-end">
          <Button variant="outline" @click="close">Close</Button>
        </div>
      </template>
    </Dialog>

    <div
      v-if="submittingDecision"
      class="fixed bottom-6 right-6 z-40 flex items-center gap-2 rounded-full bg-foreground px-4 py-2 text-sm text-background shadow-lg"
    >
      <LoaderCircle class="h-4 w-4 animate-spin" />
      Saving decision...
    </div>
  </MainLayout>
</template>

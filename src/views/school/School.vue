<script setup lang="ts">
import type { Component } from 'vue'
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
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { defaultNavItems } from '@/layouts/navigation'
import type { LayoutNavItem } from '@/layouts/navigation'
import { membershipForActiveOrganization } from '@/services/auth'
import { subscribeAllApplications, updateApplicationStatus, type ApplicationRecord } from '@/services/applications'
import { subscribeSchoolContracts, type ContractRecord } from '@/services/contracts'
import { subscribeInternships, type InternshipRecord } from '@/services/internships'
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

const membershipPermissions = computed(() => membershipForActiveOrganization(authStore.user)?.permissions ?? [])

function hasAnyPermission(keys: string[]) {
  const perms = membershipPermissions.value
  return keys.some((key) => perms.includes(key))
}

const isOrganizationOwner = computed(() => authStore.user?.isOrganizationOwner === true)

/** Matches backend SchoolStudentController::canManageRoster */
const canManageStudentRoster = computed(
  () =>
    isOrganizationOwner.value ||
    hasAnyPermission([
      'manage_users',
      'org.manage_members',
      'org.manage_roles',
      'manage_roles',
      'manage_permissions',
    ]),
)

/** Matches backend SchoolTenantPermissions::userMayCoordinateSchoolTenant */
const canCoordinateSchool = computed(
  () =>
    isOrganizationOwner.value ||
    hasAnyPermission([
      'manage_users',
      'org.manage_members',
      'manage_roles',
      'manage_permissions',
      'org.manage_roles',
      'manage_contracts',
      'org.manage_contracts',
      'manage_subscription',
      'org.manage_subscription',
    ]),
)

const canAccessTenantRbac = computed(
  () =>
    isOrganizationOwner.value ||
    hasAnyPermission([
      'manage_roles',
      'manage_permissions',
      'org.manage_roles',
      'manage_users',
      'org.manage_members',
    ]),
)

const canManageContracts = computed(
  () =>
    isOrganizationOwner.value ||
    hasAnyPermission(['manage_contracts', 'org.manage_contracts']) ||
    canCoordinateSchool.value,
)

const canManageSubscription = computed(
  () =>
    isOrganizationOwner.value || hasAnyPermission(['manage_subscription', 'org.manage_subscription']),
)

const schoolNavItems = computed<LayoutNavItem[]>(() => {
  const items = defaultNavItems.school
  return items.filter((item) => {
    switch (item.key) {
      case 'student-accounts':
        return canManageStudentRoster.value
      case 'tenant-role-management':
      case 'tenant-permission-assignment':
        return canAccessTenantRbac.value
      case 'contracts':
        return canManageContracts.value
      case 'subscription':
        return canManageSubscription.value
      default:
        return true
    }
  })
})

const quickActions = computed(() => {
  const actions: Array<{
    label: string
    copy: string
    icon: Component
    action: () => void
    buttonLabel: string
  }> = []

  if (canManageStudentRoster.value) {
    actions.push({
      label: 'Student Accounts',
      copy: 'Create temporary student logins, bulk import roster records, and export the current list.',
      icon: UserRoundPlus,
      action: () => router.push({ name: 'school-students' }),
      buttonLabel: 'Manage Accounts',
    })
  }

  if (canManageContracts.value) {
    actions.push({
      label: 'Contracts',
      copy: 'Send new contract requests and respond to company agreements from the shared contracts workspace.',
      icon: FileText,
      action: () => router.push({ name: 'contracts' }),
      buttonLabel: 'Open Contracts',
    })
  }

  actions.push(
    {
      label: 'Messages',
      copy: 'Open the restored messaging widget to contact companies and continue school-side conversations.',
      icon: MessageSquare,
      action: () => window.dispatchEvent(new CustomEvent('chat:open')),
      buttonLabel: 'Open Messages',
    },
    {
      label: 'Opportunities',
      copy: 'Review company-hosted and school-hosted placements that match the programs you coordinate.',
      icon: BriefcaseBusiness,
      action: () => router.push({ name: 'school-opportunities' }),
      buttonLabel: 'Browse Opportunities',
    },
  )

  return actions
})

const dashboardStats = computed(() => {
  const totalInterns = students.value.length
  const pendingEndorsements = applications.value.filter((application) => normalizeStatus(application.status) === 'submitted').length
  const activePlacements = applications.value.filter((application) => normalizeStatus(application.status) === 'accepted').length

  return [
    {
      label: 'Total Interns Enrolled',
      value: totalInterns,
      icon: Users,
      iconClass: 'bg-sky-100 text-sky-700',
      caption: 'Students currently in your roster',
    },
    {
      label: 'Pending Endorsements',
      value: pendingEndorsements,
      icon: Clock3,
      iconClass: 'bg-amber-100 text-amber-700',
      caption: 'Applications waiting for school action',
    },
    {
      label: 'Active Placements',
      value: activePlacements,
      icon: BriefcaseBusiness,
      iconClass: 'bg-emerald-100 text-emerald-700',
      caption: 'Students accepted by company partners',
    },
  ]
})

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

onMounted(() => {
  currentView.value = schoolViewFromRouteName(route.name)
  startSchoolSubscriptions(authStore.user?.uid)
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

watch(
  () => authStore.user?.uid,
  (userId) => {
    startSchoolSubscriptions(userId)
  },
)
</script>

<template>
  <MainLayout role="school" :title="pageTitle" :active-item="activeItem" :nav-items="schoolNavItems" @navigate="handleMenuClick($event.key)">
    <div class="space-y-6">
      <Card v-if="currentView === 'dashboard'" class="border-border/80 shadow-sm">
        <CardHeader>
          <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
              <SchoolIcon class="h-5 w-5" />
            </div>
            <div>
              <h2 class="text-2xl font-semibold text-slate-950">School Dashboard</h2>
              <p class="text-sm text-slate-600">Monitor intern enrollment, endorsement workload, and active placements at a glance.</p>
            </div>
          </div>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Card v-for="stat in dashboardStats" :key="stat.label" class="border-border/70 shadow-none">
            <CardContent class="p-5">
              <template v-if="loadingStudents || loadingApplications">
                <Skeleton class="h-11 w-11 rounded-2xl" />
                <Skeleton class="mt-5 h-4 w-28" />
                <Skeleton class="mt-3 h-8 w-16" />
                <Skeleton class="mt-3 h-4 w-36" />
              </template>
              <template v-else>
                <div :class="['flex h-11 w-11 items-center justify-center rounded-2xl', stat.iconClass]">
                  <component :is="stat.icon" class="h-5 w-5" />
                </div>
                <p class="mt-5 text-sm font-medium text-slate-500">{{ stat.label }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ stat.value }}</p>
                <p class="mt-3 text-sm text-slate-600">{{ stat.caption }}</p>
              </template>
            </CardContent>
          </Card>
        </CardContent>
      </Card>

      <Card v-if="currentView === 'dashboard'" class="border-border/80 shadow-sm">
        <CardHeader>
          <h2 class="text-2xl font-semibold text-slate-950">School Tools</h2>
          <p class="text-sm text-slate-600">
            Jump back into the older operational flows that schools use most often.
          </p>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-3">
          <Card v-for="action in quickActions" :key="action.label" class="border-border/70 shadow-none">
            <CardContent class="space-y-4 p-5">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                <component :is="action.icon" class="h-5 w-5" />
              </div>
              <div>
                <p class="text-lg font-semibold text-slate-950">{{ action.label }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ action.copy }}</p>
              </div>
              <Button class="w-full justify-center" variant="outline" @click="action.action()">
                {{ action.buttonLabel }}
              </Button>
            </CardContent>
          </Card>
        </CardContent>
      </Card>

      <Card v-else-if="currentView === 'opportunities'" class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-slate-950">Internship Opportunities</h2>
            <p class="text-sm text-slate-600">Review school-hosted placements and company posts from partners with active contracts.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="searchQuery" placeholder="Search opportunities..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="mb-4 rounded-2xl border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
            <p class="font-medium">Student-visible opportunities</p>
            <p class="mt-2">
              This list now mirrors what students can access: your school-hosted posts and company opportunities from active partner contracts.
            </p>
          </div>
          <div class="rounded-xl border border-border bg-white">
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
                        <p class="font-medium text-slate-950">{{ opportunity.title }}</p>
                        <p class="text-sm text-slate-500">{{ opportunity.location }}</p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div class="space-y-1">
                        <p class="text-slate-700">{{ opportunity.hostName }}</p>
                        <p class="text-sm text-slate-500">{{ opportunity.hostType }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-slate-700">{{ opportunity.programs }}</TableCell>
                    <TableCell class="text-slate-700">{{ opportunity.slots }}</TableCell>
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
            <h2 class="text-2xl font-semibold text-slate-950">Interns List</h2>
            <p class="text-sm text-slate-600">Search enrolled interns, review their course, and see the company tied to the latest application.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="searchQuery" placeholder="Search interns..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="rounded-xl border border-border bg-white">
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
                        <p class="font-medium text-slate-950">{{ intern.name }}</p>
                        <p class="text-sm text-slate-500">{{ intern.email }}</p>
                      </div>
                    </TableCell>
                    <TableCell class="text-slate-700">{{ intern.course }}</TableCell>
                    <TableCell class="text-slate-700">{{ intern.company }}</TableCell>
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
            <h2 class="text-2xl font-semibold text-slate-950">Placements</h2>
            <p class="text-sm text-slate-600">Track students who already have company-accepted placements and review where each intern has been assigned.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="searchQuery" placeholder="Search placements..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div class="rounded-xl border border-border bg-white">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Intern</TableHead>
                  <TableHead>Program</TableHead>
                  <TableHead>Host</TableHead>
                  <TableHead>Placement Type</TableHead>
                  <TableHead>Updated</TableHead>
                  <TableHead>Status</TableHead>
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
                  </TableRow>
                </template>
                <template v-else-if="placementRows.length">
                  <TableRow v-for="placement in placementRows" :key="placement.id">
                    <TableCell class="font-medium text-slate-950">{{ placement.internName }}</TableCell>
                    <TableCell class="text-slate-700">{{ placement.program }}</TableCell>
                    <TableCell class="text-slate-700">{{ placement.host }}</TableCell>
                    <TableCell class="text-slate-700">{{ placement.placementType }}</TableCell>
                    <TableCell class="text-slate-600">{{ placement.updatedAt }}</TableCell>
                    <TableCell>
                      <Badge :variant="badgeVariant(placement.status)">{{ badgeLabel(placement.status) }}</Badge>
                    </TableCell>
                  </TableRow>
                </template>
                <TableRow v-else>
                  <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
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
          <h2 class="text-2xl font-semibold text-slate-950">Reports</h2>
          <p class="text-sm text-slate-600">Use these summaries to monitor endorsement flow, school-based opportunities, and placement coverage.</p>
        </CardHeader>
        <CardContent class="space-y-6">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card v-for="report in reportCards" :key="report.label" class="border-border/70 shadow-none">
              <CardContent class="p-5">
                <p class="text-sm font-medium text-slate-500">{{ report.label }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ report.value }}</p>
                <p class="mt-3 text-sm text-slate-600">{{ report.copy }}</p>
              </CardContent>
            </Card>
          </div>

          <div class="rounded-2xl border border-sky-100 bg-sky-50 p-5">
            <h3 class="text-lg font-semibold text-slate-950">Recommended next build</h3>
            <p class="mt-2 text-sm leading-6 text-slate-700">
              This section is ready for export reports, course-level placement breakdowns, and partner-school analytics once your backend fields for host type, placement category, and required OJT hours are in place.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-else class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-slate-950">Endorsements</h2>
            <p class="text-sm text-slate-600">Review newly submitted applications and either endorse them to the company or reject them at the school level.</p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
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
                    <h3 class="text-lg font-semibold text-slate-950">{{ endorsement.internName }}</h3>
                    <Badge variant="warning">Submitted</Badge>
                  </div>
                  <p class="text-sm text-slate-600">{{ endorsement.course }} · {{ endorsement.company }}</p>
                  <p class="text-sm text-slate-500">Applied {{ endorsement.appliedAt }}</p>
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
                  <template v-if="canCoordinateSchool">
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
                  </template>
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
          <p class="text-sm text-slate-600">Review this intern's submitted endorsement details before taking action.</p>
        </DialogHeader>

        <div v-if="selectedEndorsement" class="mt-6 space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-sm text-slate-500">Email</p>
              <p class="mt-2 font-medium text-slate-950">{{ selectedEndorsement.email }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-sm text-slate-500">Course</p>
              <p class="mt-2 font-medium text-slate-950">{{ selectedEndorsement.course }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-sm text-slate-500">Internship</p>
              <p class="mt-2 font-medium text-slate-950">{{ selectedEndorsement.internshipTitle }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-sm text-slate-500">Company</p>
              <p class="mt-2 font-medium text-slate-950">{{ selectedEndorsement.company }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-sm text-slate-500">School</p>
              <p class="mt-2 font-medium text-slate-950">{{ selectedEndorsement.school }}</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4">
              <p class="text-sm text-slate-500">Applied</p>
              <p class="mt-2 font-medium text-slate-950">{{ selectedEndorsement.appliedAt }}</p>
            </div>
          </div>

          <div class="flex items-center justify-between rounded-xl border border-border/70 bg-white p-4">
            <div>
              <p class="text-sm text-slate-500">Current Status</p>
              <p class="mt-2">
                <Badge :variant="badgeVariant(selectedEndorsement.status)">{{ badgeLabel(selectedEndorsement.status) }}</Badge>
              </p>
            </div>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <Button variant="outline" @click="close">Close</Button>
        </div>
      </template>
    </Dialog>

    <div
      v-if="submittingDecision"
      class="fixed bottom-6 right-6 z-40 flex items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm text-white shadow-lg"
    >
      <LoaderCircle class="h-4 w-4 animate-spin" />
      Saving decision...
    </div>
  </MainLayout>
</template>

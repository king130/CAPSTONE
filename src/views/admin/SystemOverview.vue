<script setup lang="ts">
import { createColumnHelper } from '@tanstack/vue-table'
import {
  BriefcaseBusiness,
  Building2,
  CircleAlert,
  GraduationCap,
  TrendingUp,
} from 'lucide-vue-next'
import { computed, h, onMounted, ref } from 'vue'

import AdminEntityTable from '@/components/admin/AdminEntityTable.vue'
import AdminRowActions from '@/components/admin/AdminRowActions.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { useToast } from '@/composables/useToast'
import { fetchAdminApplications, fetchAdminUsers, type AdminApplicationRecord, type AdminUserRecord, type VerificationStatus } from '@/services/admin'

type DashboardTab = 'interns' | 'schools' | 'companies' | 'applications'

interface DashboardInternRow {
  id: string
  name: string
  school: string
  company: string
  status: 'pending' | 'approved' | 'rejected'
  internshipTitle: string
  email: string
}

interface DashboardSchoolRow {
  id: string
  name: string
  email: string
  address: string
  contactPerson: string
  status: 'pending' | 'approved' | 'rejected'
}

interface DashboardCompanyRow {
  id: string
  name: string
  email: string
  industry: string
  address: string
  status: 'pending' | 'approved' | 'rejected'
}

interface DashboardApplicationRow {
  id: string
  studentName: string
  studentEmail: string
  schoolName: string
  companyName: string
  internshipTitle: string
  status: 'pending' | 'approved' | 'rejected'
  createdAt: string
}

const loading = ref(true)
const { error } = useToast()
const users = ref<AdminUserRecord[]>([])
const applications = ref<AdminApplicationRecord[]>([])
const selectedIntern = ref<DashboardInternRow | null>(null)
const activeTab = ref<DashboardTab>('interns')

const stats = computed(() => {
  const interns = users.value.filter((user) => user.role === 'student')
  const schools = users.value.filter((user) => user.role === 'school')
  const companies = users.value.filter((user) => user.role === 'company')
  const pendingApplications = applications.value.filter((application) => normalizeStatus(application.status) === 'pending')

  return [
    {
      key: 'interns' as const,
      label: 'Total Interns',
      value: interns.length,
      trend: 'Active learner records',
      icon: GraduationCap,
      iconClass: 'bg-sky-100 text-sky-700',
    },
    {
      key: 'schools' as const,
      label: 'Total Schools',
      value: schools.length,
      trend: 'Institution partners',
      icon: Building2,
      iconClass: 'bg-emerald-100 text-emerald-700',
    },
    {
      key: 'companies' as const,
      label: 'Total Companies',
      value: companies.length,
      trend: 'Hiring organizations',
      icon: BriefcaseBusiness,
      iconClass: 'bg-violet-100 text-violet-700',
    },
    {
      key: 'applications' as const,
      label: 'Pending Applications',
      value: pendingApplications.length,
      trend: 'Awaiting review',
      icon: CircleAlert,
      iconClass: 'bg-amber-100 text-amber-700',
    },
  ]
})

function normalizeStatus(status?: string | null): 'pending' | 'approved' | 'rejected' {
  const value = String(status || 'pending').trim().toLowerCase()
  if (['approved', 'accepted', 'active'].includes(value)) return 'approved'
  if (['rejected', 'declined', 'cancelled'].includes(value)) return 'rejected'
  return 'pending'
}

function normalizeVerificationStatus(status?: VerificationStatus | string | null): 'pending' | 'approved' | 'rejected' {
  return normalizeStatus(status ? String(status) : 'pending')
}

function formatDate(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' })
}

const internRows = computed<DashboardInternRow[]>(() => {
  const latestApplications = new Map<string, AdminApplicationRecord>()

  for (const application of applications.value) {
    if (!application.studentId) continue

    const existing = latestApplications.get(application.studentId)
    if (!existing || new Date(application.createdAt || 0).getTime() > new Date(existing.createdAt || 0).getTime()) {
      latestApplications.set(application.studentId, application)
    }
  }

  return users.value
    .filter((user) => user.role === 'student')
    .map((user) => {
      const latestApplication = latestApplications.get(user.uid)
      return {
        id: latestApplication?.id || user.uid,
        name: user.displayName || 'Unnamed Intern',
        school: user.student?.schoolName || String(user.profile?.schoolName || 'No school assigned'),
        company: latestApplication?.companyName || 'Not assigned',
        status: normalizeStatus(latestApplication?.status),
        internshipTitle: latestApplication?.internshipTitle || 'No application yet',
        email: user.email || 'No email',
      }
    })
})

const schoolRows = computed<DashboardSchoolRow[]>(() =>
  users.value
    .filter((user) => user.role === 'school')
    .map((user) => ({
      id: user.school?.id || user.uid,
      name: user.school?.name || user.displayName || 'Unnamed School',
      email: user.email || 'No email',
      address: user.school?.address || String(user.profile?.schoolAddress || '—'),
      contactPerson: user.school?.contactPerson || user.displayName || String(user.profile?.contactPersonName || '—'),
      status: normalizeVerificationStatus(user.school?.verificationStatus),
    })),
)

const companyRows = computed<DashboardCompanyRow[]>(() =>
  users.value
    .filter((user) => user.role === 'company')
    .map((user) => ({
      id: user.company?.id || user.uid,
      name: user.company?.name || user.displayName || 'Unnamed Company',
      email: user.email || 'No email',
      industry: user.company?.industry || String(user.profile?.industryType || '—'),
      address: user.company?.address || String(user.profile?.companyAddress || '—'),
      status: normalizeVerificationStatus(user.company?.verificationStatus),
    })),
)

const pendingApplicationRows = computed<DashboardApplicationRow[]>(() =>
  applications.value
    .filter((application) => normalizeStatus(application.status) === 'pending')
    .map((application) => ({
      id: application.id,
      studentName: application.studentName || 'Unnamed Intern',
      studentEmail: application.studentEmail || 'No email',
      schoolName: application.schoolName || '—',
      companyName: application.companyName || '—',
      internshipTitle: application.internshipTitle || '—',
      status: normalizeStatus(application.status),
      createdAt: application.createdAt || '',
    }))
    .sort((a, b) => new Date(b.createdAt || 0).getTime() - new Date(a.createdAt || 0).getTime()),
)

const internColumnHelper = createColumnHelper<DashboardInternRow>()
const schoolColumnHelper = createColumnHelper<DashboardSchoolRow>()
const companyColumnHelper = createColumnHelper<DashboardCompanyRow>()
const applicationColumnHelper = createColumnHelper<DashboardApplicationRow>()

const internColumns = [
  internColumnHelper.accessor('name', {
    header: 'Name',
    cell: ({ row }) =>
      h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-medium text-slate-950' }, row.original.name),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.email),
      ]),
  }),
  internColumnHelper.accessor('school', {
    header: 'School',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue()),
  }),
  internColumnHelper.accessor('company', {
    header: 'Company',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue()),
  }),
  internColumnHelper.accessor('status', {
    header: 'Status',
    cell: ({ getValue }) =>
      h(
        Badge,
        {
          variant:
            getValue() === 'approved' ? 'success' : getValue() === 'rejected' ? 'destructive' : 'warning',
        },
        () => getValue().charAt(0).toUpperCase() + getValue().slice(1),
      ),
  }),
  internColumnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) =>
      h(AdminRowActions, {
        showApprove: false,
        showReject: false,
        onView: () => {
          selectedIntern.value = row.original
        },
      }),
  }),
]

const schoolColumns = [
  schoolColumnHelper.accessor('name', {
    header: 'School',
    cell: ({ row }) =>
      h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-medium text-slate-950' }, row.original.name),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.email),
      ]),
  }),
  schoolColumnHelper.accessor('contactPerson', {
    header: 'Contact',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue() || '—'),
  }),
  schoolColumnHelper.accessor('address', {
    header: 'Address',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue() || '—'),
  }),
  schoolColumnHelper.accessor('status', {
    header: 'Verification',
    cell: ({ getValue }) =>
      h(
        Badge,
        {
          variant:
            getValue() === 'approved' ? 'success' : getValue() === 'rejected' ? 'destructive' : 'warning',
        },
        () => getValue().charAt(0).toUpperCase() + getValue().slice(1),
      ),
  }),
]

const companyColumns = [
  companyColumnHelper.accessor('name', {
    header: 'Company',
    cell: ({ row }) =>
      h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-medium text-slate-950' }, row.original.name),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.email),
      ]),
  }),
  companyColumnHelper.accessor('industry', {
    header: 'Industry',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue() || '—'),
  }),
  companyColumnHelper.accessor('address', {
    header: 'Address',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue() || '—'),
  }),
  companyColumnHelper.accessor('status', {
    header: 'Verification',
    cell: ({ getValue }) =>
      h(
        Badge,
        {
          variant:
            getValue() === 'approved' ? 'success' : getValue() === 'rejected' ? 'destructive' : 'warning',
        },
        () => getValue().charAt(0).toUpperCase() + getValue().slice(1),
      ),
  }),
]

const applicationColumns = [
  applicationColumnHelper.accessor('studentName', {
    header: 'Intern',
    cell: ({ row }) =>
      h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-medium text-slate-950' }, row.original.studentName),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.studentEmail),
      ]),
  }),
  applicationColumnHelper.accessor('schoolName', {
    header: 'School',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue() || '—'),
  }),
  applicationColumnHelper.accessor('companyName', {
    header: 'Company',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue() || '—'),
  }),
  applicationColumnHelper.accessor('internshipTitle', {
    header: 'Internship',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue() || '—'),
  }),
  applicationColumnHelper.accessor('createdAt', {
    header: 'Submitted',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, formatDate(getValue())),
  }),
  applicationColumnHelper.accessor('status', {
    header: 'Status',
    cell: ({ getValue }) =>
      h(
        Badge,
        {
          variant:
            getValue() === 'approved' ? 'success' : getValue() === 'rejected' ? 'destructive' : 'warning',
        },
        () => getValue().charAt(0).toUpperCase() + getValue().slice(1),
      ),
  }),
]

const tableConfig = computed(() => {
  if (activeTab.value === 'schools') {
    return {
      title: 'Schools Table',
      subtitle: 'Registered schools and verification status.',
      countLabel: 'schools shown',
      searchPlaceholder: 'Search schools...',
      emptyText: 'No school records found.',
      data: schoolRows.value,
      columns: schoolColumns,
    }
  }

  if (activeTab.value === 'companies') {
    return {
      title: 'Companies Table',
      subtitle: 'Registered companies and verification status.',
      countLabel: 'companies shown',
      searchPlaceholder: 'Search companies...',
      emptyText: 'No company records found.',
      data: companyRows.value,
      columns: companyColumns,
    }
  }

  if (activeTab.value === 'applications') {
    return {
      title: 'Pending Applications',
      subtitle: 'Applications awaiting review and assignment.',
      countLabel: 'pending shown',
      searchPlaceholder: 'Search applications...',
      emptyText: 'No pending applications found.',
      data: pendingApplicationRows.value,
      columns: applicationColumns,
    }
  }

  return {
    title: 'Interns Table',
    subtitle: 'Latest intern application statuses and assignment context.',
    countLabel: 'interns shown',
    searchPlaceholder: 'Search interns...',
    emptyText: 'No intern records found.',
    data: internRows.value,
    columns: internColumns,
  }
})

function selectTab(tab: DashboardTab) {
  activeTab.value = tab
  selectedIntern.value = null
}

async function loadDashboard() {
  loading.value = true
  try {
    const [userRows, applicationRows] = await Promise.all([fetchAdminUsers(), fetchAdminApplications()])
    users.value = userRows
    applications.value = applicationRows
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to load admin overview.',
    })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadDashboard()
})
</script>

<template>
  <section class="space-y-8">
    <div>
      <p class="text-sm font-semibold uppercase tracking-[0.3em] text-sky-700">Admin Dashboard</p>
      <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Platform overview at a glance</h2>
      <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
        Monitor key organization counts, review pending activity, and quickly inspect intern records from the admin workspace.
      </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <Card
        v-for="stat in stats"
        :key="stat.label"
        role="button"
        tabindex="0"
        :class="[
          'border-border/80 shadow-sm transition hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-500',
          activeTab === stat.key ? 'ring-2 ring-sky-500' : '',
        ]"
        @click="selectTab(stat.key)"
        @keydown.enter.prevent="selectTab(stat.key)"
        @keydown.space.prevent="selectTab(stat.key)"
      >
        <CardContent class="p-6">
          <template v-if="loading">
            <Skeleton class="h-12 w-12 rounded-2xl" />
            <Skeleton class="mt-6 h-4 w-24" />
            <Skeleton class="mt-3 h-8 w-20" />
            <Skeleton class="mt-3 h-4 w-32" />
          </template>
          <template v-else>
            <div class="flex items-start justify-between gap-4">
              <div :class="['flex h-12 w-12 items-center justify-center rounded-2xl', stat.iconClass]">
                <component :is="stat.icon" class="h-5 w-5" />
              </div>
              <div class="flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                <TrendingUp class="h-3.5 w-3.5" />
                Live
              </div>
            </div>
            <p class="mt-6 text-sm font-medium text-slate-500">{{ stat.label }}</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950">{{ stat.value }}</p>
            <p class="mt-3 text-sm text-slate-600">{{ stat.trend }}</p>
          </template>
        </CardContent>
      </Card>
    </div>

    <Card class="border-border/80 shadow-sm">
      <CardContent class="space-y-6 p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-xl font-semibold text-slate-950">{{ tableConfig.title }}</h3>
            <p class="text-sm text-slate-600">{{ tableConfig.subtitle }}</p>
          </div>
          <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600">
            {{ tableConfig.data.length }} {{ tableConfig.countLabel }}
          </div>
        </div>

        <AdminEntityTable
          :columns="tableConfig.columns"
          :data="tableConfig.data"
          :loading="loading"
          :search-placeholder="tableConfig.searchPlaceholder"
          :empty-text="tableConfig.emptyText"
          :page-size="6"
        />
      </CardContent>
    </Card>

    <div
      v-if="activeTab === 'interns' && selectedIntern"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm"
      @click.self="selectedIntern = null"
    >
      <Card class="w-full max-w-lg shadow-2xl">
        <CardContent class="space-y-6 p-6">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-xl font-semibold text-slate-950">{{ selectedIntern.name }}</h3>
              <p class="text-sm text-slate-600">{{ selectedIntern.email }}</p>
            </div>
            <Badge
              :variant="
                selectedIntern.status === 'approved'
                  ? 'success'
                  : selectedIntern.status === 'rejected'
                    ? 'destructive'
                    : 'warning'
              "
            >
              {{ selectedIntern.status.charAt(0).toUpperCase() + selectedIntern.status.slice(1) }}
            </Badge>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">School</p>
              <p class="mt-2 text-sm text-slate-900">{{ selectedIntern.school }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Company</p>
              <p class="mt-2 text-sm text-slate-900">{{ selectedIntern.company }}</p>
            </div>
            <div class="sm:col-span-2">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Internship</p>
              <p class="mt-2 text-sm text-slate-900">{{ selectedIntern.internshipTitle }}</p>
            </div>
          </div>

          <div class="flex justify-end">
            <button class="text-sm font-medium text-slate-600 transition hover:text-slate-950" @click="selectedIntern = null">
              Close
            </button>
          </div>
        </CardContent>
      </Card>
    </div>
  </section>
</template>

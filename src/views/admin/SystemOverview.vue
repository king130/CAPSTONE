<script setup lang="ts">
import { createColumnHelper } from '@tanstack/vue-table'
import {
  BriefcaseBusiness,
  Building2,
  CircleAlert,
  GraduationCap,
  TrendingUp,
  Users,
} from 'lucide-vue-next'
import { computed, h, onMounted, ref } from 'vue'

import AdminEntityTable from '@/components/admin/AdminEntityTable.vue'
import AdminRowActions from '@/components/admin/AdminRowActions.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { useToast } from '@/composables/useToast'
import { fetchAdminApplications, fetchAdminUsers, type AdminApplicationRecord, type AdminUserRecord } from '@/services/admin'

interface DashboardInternRow {
  id: string
  name: string
  school: string
  company: string
  status: 'pending' | 'approved' | 'rejected'
  internshipTitle: string
  email: string
}

const loading = ref(true)
const { error } = useToast()
const users = ref<AdminUserRecord[]>([])
const applications = ref<AdminApplicationRecord[]>([])
const selectedIntern = ref<DashboardInternRow | null>(null)

const stats = computed(() => {
  const interns = users.value.filter((user) => user.role === 'student')
  const schools = users.value.filter((user) => user.role === 'school')
  const companies = users.value.filter((user) => user.role === 'company')
  const pendingApplications = applications.value.filter((application) => normalizeStatus(application.status) === 'pending')

  return [
    {
      label: 'Total Interns',
      value: interns.length,
      trend: 'Active learner records',
      icon: GraduationCap,
      iconClass: 'bg-sky-100 text-sky-700',
    },
    {
      label: 'Total Schools',
      value: schools.length,
      trend: 'Institution partners',
      icon: Building2,
      iconClass: 'bg-emerald-100 text-emerald-700',
    },
    {
      label: 'Total Companies',
      value: companies.length,
      trend: 'Hiring organizations',
      icon: BriefcaseBusiness,
      iconClass: 'bg-violet-100 text-violet-700',
    },
    {
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
    .slice(0, 8)
})

const columnHelper = createColumnHelper<DashboardInternRow>()

const columns = [
  columnHelper.accessor('name', {
    header: 'Name',
    cell: ({ row }) =>
      h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-medium text-slate-950' }, row.original.name),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.email),
      ]),
  }),
  columnHelper.accessor('school', {
    header: 'School',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue()),
  }),
  columnHelper.accessor('company', {
    header: 'Company',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue()),
  }),
  columnHelper.accessor('status', {
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
  columnHelper.display({
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
      <Card v-for="stat in stats" :key="stat.label" class="border-border/80 shadow-sm">
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
            <h3 class="text-xl font-semibold text-slate-950">Interns Table</h3>
            <p class="text-sm text-slate-600">Latest intern application statuses and assignment context.</p>
          </div>
          <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600">
            {{ internRows.length }} interns shown
          </div>
        </div>

        <AdminEntityTable
          :columns="columns"
          :data="internRows"
          :loading="loading"
          search-placeholder="Search interns..."
          empty-text="No intern records found."
          :page-size="6"
        />
      </CardContent>
    </Card>

    <div
      v-if="selectedIntern"
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

<script setup lang="ts">
import { createColumnHelper } from '@tanstack/vue-table'
import { useRoute } from 'vue-router'
import { computed, h, onMounted, ref } from 'vue'

import AdminEntityTable from '@/components/admin/AdminEntityTable.vue'
import AdminRowActions from '@/components/admin/AdminRowActions.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import { useToast } from '@/composables/useToast'
import { fetchAdminApplications, fetchAdminUsers, updateAdminApplicationStatus, updateAdminUser, type AdminApplicationRecord, type AdminUserRecord, type VerificationStatus } from '@/services/admin'

type ManagementMode = 'interns' | 'schools' | 'companies'

interface InternRow {
  id: string
  userId: string
  name: string
  school: string
  company: string
  status: 'pending' | 'approved' | 'rejected'
  email: string
  applicationId?: string
}

interface OrganizationRow {
  id: string
  userId: string
  name: string
  secondary: string
  tertiary: string
  status: VerificationStatus
  email: string
}

type SelectedRecord = InternRow | OrganizationRow

const route = useRoute()
const loading = ref(true)
const { success, error } = useToast()
const users = ref<AdminUserRecord[]>([])
const applications = ref<AdminApplicationRecord[]>([])
const selectedRecord = ref<SelectedRecord | null>(null)

const mode = computed<ManagementMode>(() => {
  if (route.name === 'admin-manage-schools') return 'schools'
  if (route.name === 'admin-manage-companies') return 'companies'
  return 'interns'
})

const pageMeta = computed(() => {
  if (mode.value === 'schools') {
    return {
      title: 'Schools',
      description: 'Review and manage school accounts, verification states, and primary contacts.',
      searchPlaceholder: 'Search schools...',
      emptyText: 'No school accounts found.',
    }
  }

  if (mode.value === 'companies') {
    return {
      title: 'Companies',
      description: 'Review and manage company accounts, industries, and verification states.',
      searchPlaceholder: 'Search companies...',
      emptyText: 'No company accounts found.',
    }
  }

  return {
    title: 'Interns',
    description: 'Review intern applications, placements, and approval outcomes from a single table.',
    searchPlaceholder: 'Search interns...',
    emptyText: 'No intern records found.',
  }
})

function normalizeStatus(status?: string | null): 'pending' | 'approved' | 'rejected' {
  const value = String(status || 'pending').trim().toLowerCase()
  if (['approved', 'accepted', 'active'].includes(value)) return 'approved'
  if (['rejected', 'declined', 'cancelled'].includes(value)) return 'rejected'
  return 'pending'
}

const internRows = computed<InternRow[]>(() => {
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
        userId: user.uid,
        name: user.displayName || 'Unnamed Intern',
        school: user.student?.schoolName || String(user.profile?.schoolName || 'No school assigned'),
        company: latestApplication?.companyName || 'Not assigned',
        status: normalizeStatus(latestApplication?.status),
        email: user.email || 'No email',
        applicationId: latestApplication?.id,
      }
    })
})

const schoolRows = computed<OrganizationRow[]>(() =>
  users.value
    .filter((user) => user.role === 'school')
    .map((user) => ({
      id: user.school?.id || user.uid,
      userId: user.uid,
      name: user.school?.name || user.displayName || 'Unnamed School',
      secondary: user.school?.address || 'No address provided',
      tertiary: user.school?.contactPerson || 'No contact person',
      status: user.school?.verificationStatus || 'pending',
      email: user.email || 'No email',
    })),
)

const companyRows = computed<OrganizationRow[]>(() =>
  users.value
    .filter((user) => user.role === 'company')
    .map((user) => ({
      id: user.company?.id || user.uid,
      userId: user.uid,
      name: user.company?.name || user.displayName || 'Unnamed Company',
      secondary: user.company?.industry || 'No industry specified',
      tertiary: user.company?.address || 'No address provided',
      status: user.company?.verificationStatus || 'pending',
      email: user.email || 'No email',
    })),
)

async function loadRows() {
  loading.value = true
  try {
    const [userRows, applicationRows] = await Promise.all([fetchAdminUsers(), fetchAdminApplications()])
    users.value = userRows
    applications.value = applicationRows
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to load admin records.',
    })
  } finally {
    loading.value = false
  }
}

async function handleInternDecision(row: InternRow, status: 'approved' | 'rejected') {
  if (!row.applicationId) return
  try {
    await updateAdminApplicationStatus(row.applicationId, status)
    await loadRows()
    success(status === 'approved' ? 'Application approved.' : 'Application rejected.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update application status.',
    })
  }
}

async function handleOrganizationDecision(row: OrganizationRow, status: VerificationStatus) {
  try {
    await updateAdminUser(row.userId, { verificationStatus: status })
    await loadRows()
    success(status === 'approved' ? 'Organization approved.' : 'Organization rejected.')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update organization status.',
    })
  }
}

const internColumnHelper = createColumnHelper<InternRow>()
const organizationColumnHelper = createColumnHelper<OrganizationRow>()

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
        { variant: getValue() === 'approved' ? 'success' : getValue() === 'rejected' ? 'destructive' : 'warning' },
        () => getValue().charAt(0).toUpperCase() + getValue().slice(1),
      ),
  }),
  internColumnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) =>
      h(AdminRowActions, {
        showApprove: !!row.original.applicationId,
        showReject: !!row.original.applicationId,
        onView: () => {
          selectedRecord.value = row.original
        },
        onApprove: () => handleInternDecision(row.original, 'approved'),
        onReject: () => handleInternDecision(row.original, 'rejected'),
      }),
  }),
]

const organizationColumns = computed(() => [
  organizationColumnHelper.accessor('name', {
    header: 'Name',
    cell: ({ row }) =>
      h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-medium text-slate-950' }, row.original.name),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.email),
      ]),
  }),
  organizationColumnHelper.accessor('secondary', {
    header: mode.value === 'schools' ? 'Address' : 'Industry',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue()),
  }),
  organizationColumnHelper.accessor('tertiary', {
    header: mode.value === 'schools' ? 'Contact Person' : 'Address',
    cell: ({ getValue }) => h('span', { class: 'text-slate-700' }, getValue()),
  }),
  organizationColumnHelper.accessor('status', {
    header: 'Status',
    cell: ({ getValue }) =>
      h(
        Badge,
        { variant: getValue() === 'approved' ? 'success' : getValue() === 'rejected' ? 'destructive' : 'warning' },
        () => getValue().charAt(0).toUpperCase() + getValue().slice(1),
      ),
  }),
  organizationColumnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) =>
      h(AdminRowActions, {
        onView: () => {
          selectedRecord.value = row.original
        },
        onApprove: () => handleOrganizationDecision(row.original, 'approved'),
        onReject: () => handleOrganizationDecision(row.original, 'rejected'),
      }),
  }),
])

const tableData = computed<Array<InternRow | OrganizationRow>>(() =>
  mode.value === 'interns' ? internRows.value : mode.value === 'schools' ? schoolRows.value : companyRows.value,
)

const tableColumns = computed(() => (mode.value === 'interns' ? internColumns : organizationColumns.value))

onMounted(() => {
  loadRows()
})
</script>

<template>
  <section class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-sky-700">Admin Management</p>
        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">{{ pageMeta.title }}</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
          {{ pageMeta.description }}
        </p>
      </div>
      <Button variant="outline" size="sm" @click="loadRows">Refresh</Button>
    </div>

    <Card class="border-border/80 shadow-sm">
      <CardContent class="p-6">
        <AdminEntityTable
          :columns="tableColumns"
          :data="tableData"
          :loading="loading"
          :search-placeholder="pageMeta.searchPlaceholder"
          :empty-text="pageMeta.emptyText"
        />
      </CardContent>
    </Card>

    <div
      v-if="selectedRecord"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm"
      @click.self="selectedRecord = null"
    >
      <Card class="w-full max-w-lg shadow-2xl">
        <CardContent class="space-y-5 p-6">
          <div>
            <h3 class="text-xl font-semibold text-slate-950">{{ selectedRecord.name }}</h3>
            <p class="text-sm text-slate-600">{{ selectedRecord.email }}</p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <template v-for="(value, key) in selectedRecord" :key="String(key)">
              <div v-if="!['name', 'email', 'id', 'userId'].includes(String(key))">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ key }}</p>
                <p class="mt-2 text-sm text-slate-900">{{ value || '—' }}</p>
              </div>
            </template>
          </div>

          <div class="flex justify-end">
            <button class="text-sm font-medium text-slate-600 transition hover:text-slate-950" @click="selectedRecord = null">
              Close
            </button>
          </div>
        </CardContent>
      </Card>
    </div>
  </section>
</template>

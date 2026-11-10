<script setup lang="ts">
import { createColumnHelper } from '@tanstack/vue-table'
import { useRoute } from 'vue-router'
import { computed, h, onMounted, ref, watch } from 'vue'

import AdminEntityTable from '@/components/admin/AdminEntityTable.vue'
import AdminRowActions from '@/components/admin/AdminRowActions.vue'
import FilePreviewActions from '@/components/FilePreviewActions.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Select from '@/components/ui/select/Select.vue'
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import { fetchAdminApplications, fetchAdminUsers, updateAdminApplicationStatus, updateAdminUser, type AdminApplicationRecord, type AdminUserRecord, type VerificationStatus } from '@/services/admin'
import { fetchApiFileBlob } from '@/services/fileBlob'
import {
  documentTypeLabel,
  listAdminVerificationDocuments,
  markAdminVerificationDocumentViewed,
  reviewAdminVerificationDocument,
  verificationStatusLabel,
  type OrganizationVerificationDocument,
} from '@/services/organizationVerification'

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
  rejectionReason?: string | null
  createdAt?: string | null
}

type StatusFilter = 'all' | 'pending' | 'approved' | 'rejected'
type OrgSort = 'newest' | 'oldest' | 'name'

type SelectedRecord = InternRow | OrganizationRow

const route = useRoute()
const loading = ref(true)
const { success, error } = useToast()
const users = ref<AdminUserRecord[]>([])
const applications = ref<AdminApplicationRecord[]>([])
const selectedRecord = ref<SelectedRecord | null>(null)
const rejectTarget = ref<OrganizationRow | null>(null)
const rejectionReason = ref('')
const rejectSubmitting = ref(false)
const confirmTarget = ref<OrganizationRow | null>(null)
const confirmNextStatus = ref<VerificationStatus | null>(null)
const confirmSubmitting = ref(false)
const verificationDocs = ref<OrganizationVerificationDocument[]>([])
const loadingVerificationDocs = ref(false)
const docReviewTarget = ref<OrganizationVerificationDocument | null>(null)
const docReviewStatus = ref<'approved' | 'rejected' | 'needs_fix' | null>(null)
const docReviewReason = ref('')
const docReviewSubmitting = ref(false)
const statusFilter = ref<StatusFilter>('all')
const orgSort = ref<OrgSort>('newest')
/** Per org-owner userId: document view progress after details were opened. */
const docViewProgressByUserId = ref<Record<string, { total: number; viewed: number }>>({})

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
      title: 'Companies & Municipal Halls',
      description: 'Review and verify company and municipal hall host accounts, documents, and statuses.',
      searchPlaceholder: 'Search companies or municipal halls...',
      emptyText: 'No company or municipal hall accounts found.',
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

/** Bucket for filter/counts: under_review counts as pending. */
function organizationStatusBucket(status?: string | null): 'pending' | 'approved' | 'rejected' {
  const value = String(status || 'pending').trim().toLowerCase()
  if (value === 'approved') return 'approved'
  if (value === 'rejected') return 'rejected'
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
      rejectionReason: user.school?.verificationRejectionReason || null,
      createdAt: user.createdAt || null,
    })),
)

const companyRows = computed<OrganizationRow[]>(() =>
  users.value
    .filter((user) => {
      if (!user.company) return false
      const role = String(user.role || '').toLowerCase()
      const orgType = String(user.organization?.type || user.company?.organizationType || '').toLowerCase()
      return role === 'company' || role === 'municipal_hall' || orgType === 'municipal_hall' || orgType === 'company'
    })
    .map((user) => {
      const orgType = String(user.organization?.type || user.company?.organizationType || 'company').toLowerCase()
      const isMunicipal = orgType === 'municipal_hall'
      return {
        id: user.company?.id || user.uid,
        userId: user.uid,
        name: user.company?.name || user.displayName || (isMunicipal ? 'Unnamed Municipal Hall' : 'Unnamed Company'),
        secondary: isMunicipal
          ? `Municipal Hall · ${user.company?.lguType || 'LGU'}${user.company?.cityMunicipality ? ` · ${user.company.cityMunicipality}` : ''}`
          : user.company?.industry || 'No industry specified',
        tertiary: user.company?.address || 'No address provided',
        status: user.company?.verificationStatus || 'pending',
        email: user.email || 'No email',
        rejectionReason: user.company?.verificationRejectionReason || null,
        createdAt: user.createdAt || null,
      }
    }),
)

const organizationSourceRows = computed(() =>
  mode.value === 'schools' ? schoolRows.value : mode.value === 'companies' ? companyRows.value : [],
)

const organizationStatusCounts = computed(() => {
  const counts = { all: 0, pending: 0, approved: 0, rejected: 0 }
  for (const row of organizationSourceRows.value) {
    counts.all += 1
    counts[organizationStatusBucket(row.status)] += 1
  }
  return counts
})

const filteredSortedOrganizationRows = computed(() => {
  let rows = organizationSourceRows.value
  if (statusFilter.value !== 'all') {
    rows = rows.filter((row) => organizationStatusBucket(row.status) === statusFilter.value)
  }

  const sorted = [...rows]
  if (orgSort.value === 'name') {
    sorted.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
  } else {
    const direction = orgSort.value === 'newest' ? -1 : 1
    sorted.sort((a, b) => {
      const aTime = new Date(a.createdAt || 0).getTime()
      const bTime = new Date(b.createdAt || 0).getTime()
      if (aTime !== bTime) return (aTime - bTime) * direction
      return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }) * direction
    })
  }
  return sorted
})

watch(mode, () => {
  statusFilter.value = 'all'
  orgSort.value = 'newest'
})

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

function openOrganizationRejectDialog(row: OrganizationRow) {
  const actions = organizationActionConfig(row.status)
  const gates = organizationDecisionGates(row.userId, actions.requiresDocumentViews)
  if (gates.rejectDisabled) {
    error(new Error('View all documents to enable actions.'), {
      fallback: 'View all documents to enable actions.',
    })
    void openOrganizationDetails(row)
    return
  }
  rejectTarget.value = row
  rejectionReason.value = ''
}

function closeOrganizationRejectDialog() {
  rejectTarget.value = null
  rejectionReason.value = ''
  rejectSubmitting.value = false
}

function organizationActionConfig(status: string) {
  const normalized = String(status || 'pending').toLowerCase()
  if (normalized === 'approved') {
    return {
      showApprove: true,
      showReject: false,
      approveLabel: 'Revoke',
      nextOnApprove: 'pending' as VerificationStatus,
      requiresDocumentViews: false,
    }
  }
  if (normalized === 'rejected') {
    return {
      showApprove: true,
      showReject: false,
      approveLabel: 'Re-approve',
      nextOnApprove: 'approved' as VerificationStatus,
      requiresDocumentViews: true,
    }
  }
  return {
    showApprove: true,
    showReject: true,
    approveLabel: 'Approve',
    nextOnApprove: 'approved' as VerificationStatus,
    requiresDocumentViews: true,
  }
}

function syncDocViewProgress(userId: string, docs: OrganizationVerificationDocument[]) {
  const viewed = docs.filter((doc) => !!doc.adminViewedAt).length
  docViewProgressByUserId.value = {
    ...docViewProgressByUserId.value,
    [userId]: { total: docs.length, viewed },
  }
}

function organizationDecisionGates(userId: string, requiresDocumentViews: boolean) {
  if (!requiresDocumentViews) {
    return { approveDisabled: false, rejectDisabled: false }
  }
  const progress = docViewProgressByUserId.value[userId]
  if (!progress) {
    return { approveDisabled: true, rejectDisabled: true }
  }
  if (progress.total === 0) {
    return { approveDisabled: true, rejectDisabled: false }
  }
  const allViewed = progress.viewed >= progress.total
  return { approveDisabled: !allViewed, rejectDisabled: !allViewed }
}

const selectedOrgDocProgress = computed(() => {
  if (!selectedRecord.value || mode.value === 'interns' || !('userId' in selectedRecord.value)) {
    return null
  }
  return (
    docViewProgressByUserId.value[selectedRecord.value.userId] ?? {
      total: verificationDocs.value.length,
      viewed: verificationDocs.value.filter((doc) => !!doc.adminViewedAt).length,
    }
  )
})

const selectedOrgCanApprove = computed(() => {
  const progress = selectedOrgDocProgress.value
  if (!progress) return false
  if (progress.total === 0) return false
  return progress.viewed >= progress.total
})

const selectedOrgCanReject = computed(() => {
  const progress = selectedOrgDocProgress.value
  if (!progress) return false
  if (progress.total === 0) return true
  return progress.viewed >= progress.total
})

function openOrganizationConfirm(row: OrganizationRow, status: VerificationStatus) {
  const actions = organizationActionConfig(row.status)
  if (status === 'approved' || status === 'rejected') {
    const gates = organizationDecisionGates(row.userId, actions.requiresDocumentViews)
    const blocked = status === 'approved' ? gates.approveDisabled : gates.rejectDisabled
    if (blocked) {
      error(new Error('View all documents to enable actions.'), {
        fallback: 'View all documents to enable actions.',
      })
      void openOrganizationDetails(row)
      return
    }
  }
  confirmTarget.value = row
  confirmNextStatus.value = status
}

function closeOrganizationConfirm() {
  confirmTarget.value = null
  confirmNextStatus.value = null
  confirmSubmitting.value = false
}

const confirmDialogCopy = computed(() => {
  const status = confirmNextStatus.value
  const name = confirmTarget.value?.name || 'this organization'
  if (status === 'pending') {
    return {
      title: 'Revoke verification',
      body: `Revoke verification for ${name}? The organization will return to pending and host features will be blocked until re-approved.`,
      action: 'Revoke',
      variant: 'destructive' as const,
    }
  }
  if (status === 'approved') {
    return {
      title: confirmTarget.value?.status === 'rejected' ? 'Re-approve organization' : 'Approve organization',
      body: `Mark ${name} as verified? Host features will become available for this organization.`,
      action: confirmTarget.value?.status === 'rejected' ? 'Re-approve' : 'Approve',
      variant: 'default' as const,
    }
  }
  return {
    title: 'Confirm status change',
    body: `Update verification status for ${name}?`,
    action: 'Confirm',
    variant: 'default' as const,
  }
})

async function handleOrganizationDecision(row: OrganizationRow, status: VerificationStatus, reason?: string) {
  try {
    await updateAdminUser(row.userId, {
      verificationStatus: status,
      ...(status === 'rejected' ? { verificationRejectionReason: reason?.trim() || '' } : {}),
    })
    await loadRows()
    const message =
      status === 'approved'
        ? 'Organization approved.'
        : status === 'pending'
          ? 'Organization verification revoked.'
          : 'Organization rejected.'
    success(message)
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Unable to update organization status.',
    })
    throw caughtError
  }
}

async function submitOrganizationConfirm() {
  if (!confirmTarget.value || !confirmNextStatus.value) return
  confirmSubmitting.value = true
  try {
    await handleOrganizationDecision(confirmTarget.value, confirmNextStatus.value)
    closeOrganizationConfirm()
  } catch {
    // Toast already shown.
  } finally {
    confirmSubmitting.value = false
  }
}

async function submitOrganizationRejection() {
  if (!rejectTarget.value) return
  const reason = rejectionReason.value.trim()
  if (!reason) {
    error(new Error('A rejection reason is required.'), {
      fallback: 'A rejection reason is required.',
    })
    return
  }

  rejectSubmitting.value = true
  try {
    await handleOrganizationDecision(rejectTarget.value, 'rejected', reason)
    closeOrganizationRejectDialog()
  } catch {
    // Toast already shown by handleOrganizationDecision.
  } finally {
    rejectSubmitting.value = false
  }
}

const internColumnHelper = createColumnHelper<InternRow>()
const organizationColumnHelper = createColumnHelper<OrganizationRow>()

const internColumns = [
  internColumnHelper.accessor('name', {
    header: 'Name',
    cell: ({ row }) =>
      h('div', { class: 'space-y-1' }, [
        h('p', { class: 'font-medium text-foreground' }, row.original.name),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.email),
      ]),
  }),
  internColumnHelper.accessor('school', {
    header: 'School',
    cell: ({ getValue }) => h('span', { class: 'text-muted-foreground' }, getValue()),
  }),
  internColumnHelper.accessor('company', {
    header: 'Company',
    cell: ({ getValue }) => h('span', { class: 'text-muted-foreground' }, getValue()),
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
        h('p', { class: 'font-medium text-foreground' }, row.original.name),
        h('p', { class: 'text-sm text-muted-foreground' }, row.original.email),
      ]),
  }),
  organizationColumnHelper.accessor('secondary', {
    header: mode.value === 'schools' ? 'Address' : 'Industry',
    cell: ({ getValue }) => h('span', { class: 'text-muted-foreground' }, getValue()),
  }),
  organizationColumnHelper.accessor('tertiary', {
    header: mode.value === 'schools' ? 'Contact Person' : 'Address',
    cell: ({ getValue }) => h('span', { class: 'text-muted-foreground' }, getValue()),
  }),
  organizationColumnHelper.accessor('status', {
    header: 'Status',
    cell: ({ getValue }) =>
      h(
        Badge,
        {
          variant:
            getValue() === 'approved' ? 'success' : getValue() === 'rejected' ? 'destructive' : 'warning',
        },
        () => verificationStatusLabel(String(getValue())),
      ),
  }),
  organizationColumnHelper.display({
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) => {
      const actions = organizationActionConfig(row.original.status)
      const gates = organizationDecisionGates(row.original.userId, actions.requiresDocumentViews)
      return h(AdminRowActions, {
        showApprove: actions.showApprove,
        showReject: actions.showReject,
        approveLabel: actions.approveLabel,
        approveDisabled: gates.approveDisabled,
        rejectDisabled: gates.rejectDisabled,
        onView: () => {
          void openOrganizationDetails(row.original)
        },
        onApprove: () => openOrganizationConfirm(row.original, actions.nextOnApprove),
        onReject: () => openOrganizationRejectDialog(row.original),
      })
    },
  }),
])

async function openOrganizationDetails(row: OrganizationRow) {
  selectedRecord.value = row
  loadingVerificationDocs.value = true
  verificationDocs.value = []
  try {
    const res = await listAdminVerificationDocuments(row.userId)
    verificationDocs.value = res.data ?? []
    syncDocViewProgress(row.userId, verificationDocs.value)
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to load verification documents.' })
  } finally {
    loadingVerificationDocs.value = false
  }
}

function adminVerificationDocLoader(doc: OrganizationVerificationDocument) {
  return async () => {
    try {
      const marked = await markAdminVerificationDocumentViewed(doc.id)
      verificationDocs.value = verificationDocs.value.map((row) => (row.id === marked.id ? marked : row))
      if (selectedRecord.value && 'userId' in selectedRecord.value) {
        syncDocViewProgress(selectedRecord.value.userId, verificationDocs.value)
      }
    } catch {
      // Preview can still proceed if mark-viewed fails.
    }
    return fetchApiFileBlob(`/admin/verification-documents/${doc.id}/file`)
  }
}

function openDocReview(doc: OrganizationVerificationDocument, status: 'approved' | 'rejected' | 'needs_fix') {
  docReviewTarget.value = doc
  docReviewStatus.value = status
  docReviewReason.value = ''
}

function closeDocReview() {
  docReviewTarget.value = null
  docReviewStatus.value = null
  docReviewReason.value = ''
  docReviewSubmitting.value = false
}

async function submitDocReview() {
  if (!docReviewTarget.value || !docReviewStatus.value) return
  if (
    (docReviewStatus.value === 'rejected' || docReviewStatus.value === 'needs_fix') &&
    !docReviewReason.value.trim()
  ) {
    error(new Error('A reason is required.'), { fallback: 'A reason is required.' })
    return
  }

  docReviewSubmitting.value = true
  try {
    await reviewAdminVerificationDocument(
      docReviewTarget.value.id,
      docReviewStatus.value,
      docReviewReason.value.trim() || undefined,
    )
    success(
      docReviewStatus.value === 'approved'
        ? 'Document approved.'
        : docReviewStatus.value === 'needs_fix'
          ? 'Re-upload requested.'
          : 'Document rejected.',
    )
    closeDocReview()
    if (selectedRecord.value && 'userId' in selectedRecord.value) {
      await openOrganizationDetails(selectedRecord.value as OrganizationRow)
    }
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to update the document.' })
  } finally {
    docReviewSubmitting.value = false
  }
}

function closeSelectedRecord() {
  selectedRecord.value = null
  verificationDocs.value = []
  closeDocReview()
}

const tableData = computed<Array<InternRow | OrganizationRow>>(() =>
  mode.value === 'interns' ? internRows.value : filteredSortedOrganizationRows.value,
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
        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-primary">Admin Management</p>
        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-foreground">{{ pageMeta.title }}</h2>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">
          {{ pageMeta.description }}
        </p>
      </div>
      <Button variant="outline" size="sm" @click="loadRows">Refresh</Button>
    </div>

    <Card class="border-border/80 shadow-sm">
      <CardContent class="space-y-4 p-6">
        <div
          v-if="mode !== 'interns'"
          class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
          <div class="flex flex-wrap gap-2 text-xs text-muted-foreground">
            <span class="rounded-full border border-border px-2.5 py-1">All {{ organizationStatusCounts.all }}</span>
            <span class="rounded-full border border-border px-2.5 py-1">Pending {{ organizationStatusCounts.pending }}</span>
            <span class="rounded-full border border-border px-2.5 py-1">Approved {{ organizationStatusCounts.approved }}</span>
            <span class="rounded-full border border-border px-2.5 py-1">Rejected {{ organizationStatusCounts.rejected }}</span>
          </div>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <Select
              id="admin-org-status-filter"
              v-model="statusFilter"
              class="w-full sm:w-48"
              aria-label="Filter by verification status"
            >
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </Select>
            <Select
              id="admin-org-sort"
              v-model="orgSort"
              class="w-full sm:w-48"
              aria-label="Sort organizations"
            >
              <option value="newest">Newest first</option>
              <option value="oldest">Oldest first</option>
              <option value="name">Name A–Z</option>
            </Select>
          </div>
        </div>

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
      class="fixed inset-0 z-modal flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm"
      @click.self="selectedRecord = null"
    >
      <Card class="w-full max-w-lg shadow-2xl">
        <CardContent class="space-y-5 p-6">
          <div>
            <h3 class="text-xl font-semibold text-foreground">{{ selectedRecord.name }}</h3>
            <p class="text-sm text-muted-foreground">{{ selectedRecord.email }}</p>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <template v-for="(value, key) in selectedRecord" :key="String(key)">
              <div v-if="!['name', 'email', 'id', 'userId'].includes(String(key))">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">{{ key }}</p>
                <p class="mt-2 text-sm text-foreground">
                  {{ key === 'status' ? verificationStatusLabel(String(value || '')) : value || '—' }}
                </p>
              </div>
            </template>
          </div>

          <div v-if="mode !== 'interns'" class="space-y-3 border-t border-border pt-4">
            <p class="text-sm font-semibold text-foreground">Verification documents</p>
            <p
              v-if="selectedOrgDocProgress"
              class="text-xs text-muted-foreground"
              data-testid="admin-doc-view-progress"
            >
              <template v-if="selectedOrgDocProgress.total === 0">
                No documents uploaded — Approve is blocked; Reject stays available.
              </template>
              <template v-else-if="selectedOrgDocProgress.viewed < selectedOrgDocProgress.total">
                View all documents to enable actions ({{ selectedOrgDocProgress.viewed }}/{{ selectedOrgDocProgress.total }} viewed).
              </template>
              <template v-else>
                All documents viewed ({{ selectedOrgDocProgress.viewed }}/{{ selectedOrgDocProgress.total }}).
              </template>
            </p>
            <p v-if="loadingVerificationDocs" class="text-sm text-muted-foreground">Loading documents…</p>
            <p v-else-if="verificationDocs.length === 0" class="text-sm text-muted-foreground">
              No documents uploaded yet.
            </p>
            <ul v-else class="space-y-2">
              <li
                v-for="doc in verificationDocs"
                :key="doc.id"
                class="space-y-2 rounded-lg border border-border px-3 py-2 text-sm"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="font-medium text-foreground">{{ doc.fileName }}</p>
                    <p class="text-xs text-muted-foreground">
                      {{ documentTypeLabel(doc.documentType, doc.documentTypeLabel) }} ·
                      {{ verificationStatusLabel(doc.status) }}
                      ·
                      {{ doc.adminViewedAt ? 'Viewed' : 'Not viewed' }}
                    </p>
                    <p v-if="doc.rejectionReason" class="mt-1 text-xs text-destructive">
                      {{ doc.rejectionReason }}
                    </p>
                    <p v-if="doc.history?.length" class="mt-1 text-xs text-muted-foreground">
                      {{ doc.history.length }} previous version(s) retained
                    </p>
                  </div>
                  <FilePreviewActions
                    :file-name="doc.fileName || 'verification-document'"
                    :mime-type="doc.fileMime"
                    :file-size="doc.fileSize"
                    :loader="adminVerificationDocLoader(doc)"
                  />
                </div>
                <div class="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" @click="openDocReview(doc, 'approved')">Approve file</Button>
                  <Button size="sm" variant="outline" @click="openDocReview(doc, 'needs_fix')">Request re-upload</Button>
                  <Button size="sm" variant="destructive" @click="openDocReview(doc, 'rejected')">Reject file</Button>
                </div>
              </li>
            </ul>

            <div
              v-if="selectedRecord && 'status' in selectedRecord && selectedRecord.status !== 'approved'"
              class="flex flex-wrap justify-end gap-2 pt-2"
            >
              <Button
                size="sm"
                variant="outline"
                :disabled="!selectedOrgCanApprove"
                @click="openOrganizationConfirm(selectedRecord as OrganizationRow, 'approved')"
              >
                Approve
              </Button>
              <Button
                size="sm"
                variant="destructive"
                :disabled="!selectedOrgCanReject"
                @click="openOrganizationRejectDialog(selectedRecord as OrganizationRow)"
              >
                Reject
              </Button>
            </div>
          </div>

          <div class="flex justify-end">
            <button
              class="text-sm font-medium text-muted-foreground transition hover:text-foreground"
              @click="closeSelectedRecord"
            >
              Close
            </button>
          </div>
        </CardContent>
      </Card>
    </div>

    <div
      v-if="confirmTarget && confirmNextStatus"
      class="fixed inset-0 z-modal flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm"
      @click.self="closeOrganizationConfirm"
    >
      <Card class="w-full max-w-lg shadow-2xl">
        <CardContent class="space-y-5 p-6">
          <div>
            <h3 class="text-xl font-semibold text-foreground">{{ confirmDialogCopy.title }}</h3>
            <p class="mt-2 text-sm text-muted-foreground">{{ confirmDialogCopy.body }}</p>
          </div>
          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="closeOrganizationConfirm">Cancel</Button>
            <Button
              :variant="confirmDialogCopy.variant === 'destructive' ? 'destructive' : 'default'"
              :disabled="confirmSubmitting"
              @click="submitOrganizationConfirm"
            >
              {{ confirmSubmitting ? 'Saving...' : confirmDialogCopy.action }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <div
      v-if="docReviewTarget && docReviewStatus"
      class="fixed inset-0 z-modal flex items-center justify-center bg-background/80 px-4 backdrop-blur-sm"
      @click.self="closeDocReview"
    >
      <Card class="w-full max-w-lg shadow-2xl">
        <CardContent class="space-y-5 p-6">
          <div>
            <h3 class="text-xl font-semibold text-foreground">
              {{
                docReviewStatus === 'approved'
                  ? 'Approve document'
                  : docReviewStatus === 'needs_fix'
                    ? 'Request re-upload'
                    : 'Reject document'
              }}
            </h3>
            <p class="mt-2 text-sm text-muted-foreground">
              {{ docReviewTarget.fileName }} ·
              {{ documentTypeLabel(docReviewTarget.documentType, docReviewTarget.documentTypeLabel) }}
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
              Approving a file does not verify the organization. Organization verification still requires an explicit
              Approve action.
            </p>
          </div>
          <div v-if="docReviewStatus !== 'approved'" class="space-y-2">
            <label class="text-sm font-medium text-foreground" for="doc-review-reason">Reason</label>
            <Textarea
              id="doc-review-reason"
              v-model="docReviewReason"
              :rows="4"
              placeholder="Explain what the organization must fix"
            />
          </div>
          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="closeDocReview">Cancel</Button>
            <Button
              :variant="docReviewStatus === 'rejected' ? 'destructive' : 'default'"
              :disabled="
                docReviewSubmitting ||
                ((docReviewStatus === 'rejected' || docReviewStatus === 'needs_fix') && !docReviewReason.trim())
              "
              @click="submitDocReview"
            >
              {{ docReviewSubmitting ? 'Saving...' : 'Confirm' }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <div
      v-if="rejectTarget"
      class="fixed inset-0 z-modal flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm"
      @click.self="closeOrganizationRejectDialog"
    >
      <Card class="w-full max-w-lg shadow-2xl">
        <CardContent class="space-y-5 p-6">
          <div>
            <h3 class="text-xl font-semibold text-foreground">Reject organization</h3>
            <p class="mt-2 text-sm text-muted-foreground">
              Provide a rejection reason for <span class="font-medium text-foreground">{{ rejectTarget.name }}</span>.
              The organization owner will stay able to sign in and read this reason.
            </p>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium text-foreground" for="organization-rejection-reason">Rejection reason</label>
            <Textarea
              id="organization-rejection-reason"
              v-model="rejectionReason"
              :rows="5"
              placeholder="Explain why this organization is being rejected"
            />
          </div>

          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="closeOrganizationRejectDialog">Cancel</Button>
            <Button variant="destructive" :disabled="rejectSubmitting || !rejectionReason.trim()" @click="submitOrganizationRejection">
              {{ rejectSubmitting ? 'Rejecting...' : 'Reject organization' }}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </section>
</template>

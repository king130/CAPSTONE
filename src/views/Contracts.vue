<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  CheckCircle2,
  Eye,
  LoaderCircle,
  Paperclip,
  Plus,
  Search,
  Send,
  XCircle,
} from 'lucide-vue-next'
import { useRouter } from 'vue-router'

import FilePreviewActions from '@/components/FilePreviewActions.vue'
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
import Textarea from '@/components/ui/textarea/Textarea.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import {
  acceptContract,
  amendContract,
  cancelContract,
  rejectContract,
  subscribeCompanyContracts,
  subscribeSchoolContracts,
  type ContractRecord,
} from '@/services/contracts'
import { hasPermission } from '@/services/permissions'
import { useAuthStore } from '@/stores/auth'
import { formatDate as formatDisplayDate } from '@/utils/formatDisplay'

type ContractRole = 'school' | 'company'
type ContractAction = 'accept' | 'reject' | 'cancel'

interface PendingActionState {
  contract: ContractRecord
  action: ContractAction
}

function normalizeStatus(status?: string) {
  const normalized = String(status || 'pending').trim().toLowerCase()
  if (normalized === 'active') return 'active'
  if (normalized === 'pending_amendment') return 'pending_amendment'
  if (normalized === 'rejected') return 'rejected'
  if (normalized === 'cancelled') return 'cancelled'
  return 'pending'
}

function statusBadgeVariant(status?: string) {
  const normalized = normalizeStatus(status)
  if (normalized === 'active') return 'success'
  if (normalized === 'pending' || normalized === 'pending_amendment') return 'warning'
  return 'destructive'
}

function partnerNameForContract(contract: ContractRecord) {
  return currentRole.value === 'school' ? contract.companyName : contract.schoolName
}

function statusLabel(contract: ContractRecord) {
  const normalized = normalizeStatus(contract.status)
  const partner = partnerNameForContract(contract) || 'partner'

  if (normalized === 'pending') {
    if (contract.requestedByRole === currentRole.value) {
      return `Waiting for ${partner} to respond`
    }
    return 'Needs your response'
  }
  if (normalized === 'pending_amendment') {
    if (contract.requestedByRole === currentRole.value) {
      return `Waiting for ${partner} to accept changes`
    }
    return 'Changes waiting for your review'
  }
  if (normalized === 'active') return 'Active'
  if (normalized === 'rejected') return 'Rejected'
  if (normalized === 'cancelled') return 'Cancelled'
  return 'Pending'
}

function formatDate(value: unknown) {
  const formatted = formatDisplayDate(value == null ? null : String(value))
  return formatted === '—' ? 'Not available' : formatted
}

function isAgreementExpired(contract: ContractRecord): boolean {
  if (!contract.endDate) return false
  const end = new Date(String(contract.endDate))
  if (Number.isNaN(end.getTime())) return false
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  end.setHours(0, 0, 0, 0)
  return end.getTime() < today.getTime()
}

function agreementTitle(contract: ContractRecord): string {
  return contract.contractType || contract.subject || 'Untitled agreement'
}

const authStore = useAuthStore()
const { success, error } = useToast()
const router = useRouter()

const currentRole = computed<ContractRole>(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const loading = ref(true)
const search = ref('')
const statusTab = ref<'active' | 'pending' | 'cancelled'>('active')
const contracts = ref<ContractRecord[]>([])
const actionDialogOpen = ref(false)
const pendingAction = ref<PendingActionState | null>(null)
const actionReason = ref('')
const actionSubmitting = ref(false)
const amendDialogOpen = ref(false)
const amendTarget = ref<ContractRecord | null>(null)
const amendSubmitting = ref(false)
const amendFiles = ref<File[]>([])
const amendForm = ref({
  notes: '',
  purpose: '',
  startDate: '',
  endDate: '',
  terms: '',
})
const reviewDialogOpen = ref(false)
const reviewTarget = ref<ContractRecord | null>(null)

type AmendmentChange = { from?: unknown; to?: unknown }
type AmendmentEntry = {
  at?: string
  by_role?: string
  changes?: Record<string, AmendmentChange>
}

function latestAmendment(contract: ContractRecord | null): AmendmentEntry | null {
  const metadata = (contract?.metadata ?? {}) as Record<string, unknown>
  const amendments = Array.isArray(metadata.amendments) ? (metadata.amendments as AmendmentEntry[]) : []
  return amendments.length ? amendments[amendments.length - 1]! : null
}

function formatChangeValue(value: unknown): string {
  if (value == null || value === '') return 'Not set'
  if (Array.isArray(value)) {
    if (!value.length) return 'None'
    if (typeof value[0] === 'object' && value[0] !== null && 'name' in (value[0] as object)) {
      return value.map((item) => String((item as { name?: string }).name || 'file')).join(', ')
    }
    return value.map((item) => String(item)).join(', ')
  }
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

function changeFieldLabel(key: string): string {
  const labels: Record<string, string> = {
    subject: 'Subject',
    purpose: 'Purpose',
    start_date: 'Start date',
    end_date: 'End date',
    notes: 'Notes',
    terms: 'Terms',
    attachments: 'Documents',
    internship_slots: 'Internship slots',
    student_programs: 'Programs',
  }
  return labels[key] || key.replaceAll('_', ' ')
}

let unsubscribeContracts: (() => void) | null = null

const filteredContracts = computed(() => {
  const query = search.value.trim().toLowerCase()
  return contracts.value.filter((contract) => {
    const status = normalizeStatus(contract.status)
    if (statusTab.value === 'cancelled') {
      if (status !== 'cancelled') return false
    } else if (statusTab.value === 'pending') {
      if (status !== 'pending' && status !== 'pending_amendment') return false
    } else if (status !== 'active') {
      return false
    }
    if (!query) return true
    return [
      contract.subject,
      contract.contractType,
      contract.moaReferenceNo,
      contract.schoolName,
      contract.companyName,
      statusLabel(contract),
    ].some((value) => String(value || '').toLowerCase().includes(query))
  })
})

const pendingCount = computed(() =>
  contracts.value.filter((contract) => {
    const status = normalizeStatus(contract.status)
    return status === 'pending' || status === 'pending_amendment'
  }).length,
)

const cancelledCount = computed(() =>
  contracts.value.filter((contract) => normalizeStatus(contract.status) === 'cancelled').length,
)

function canAccept(contract: ContractRecord) {
  const status = normalizeStatus(contract.status)
  return (
    (status === 'pending' || status === 'pending_amendment')
    && contract.requestedByRole !== currentRole.value
  )
}

function canReject(contract: ContractRecord) {
  return normalizeStatus(contract.status) === 'pending' && contract.requestedByRole !== currentRole.value
}

function canCancel(contract: ContractRecord) {
  return ['pending', 'active', 'pending_amendment'].includes(normalizeStatus(contract.status))
}

function canAmend(contract: ContractRecord) {
  return (
    hasPermission('org.manage_agreements')
    && ['pending', 'active'].includes(normalizeStatus(contract.status))
  )
}

function canReviewChanges(contract: ContractRecord) {
  return normalizeStatus(contract.status) === 'pending_amendment' && Boolean(latestAmendment(contract))
}

function openReviewDialog(contract: ContractRecord) {
  reviewTarget.value = contract
  reviewDialogOpen.value = true
}

function onAmendFilesSelected(event: Event) {
  const input = event.target as HTMLInputElement
  amendFiles.value = input.files ? Array.from(input.files) : []
}

function openCreatePage() {
  void router.push({ name: 'agreements-new' })
}

function openActionDialog(contract: ContractRecord, action: ContractAction) {
  pendingAction.value = { contract, action }
  actionReason.value = ''
  actionDialogOpen.value = true
}

function openAmendDialog(contract: ContractRecord) {
  amendTarget.value = contract
  amendFiles.value = []
  amendForm.value = {
    notes: contract.notes || '',
    purpose: contract.purpose || '',
    startDate: contract.startDate || '',
    endDate: contract.endDate || '',
    terms: contract.terms || '',
  }
  amendDialogOpen.value = true
}

const amendPurposeError = ref('')

async function confirmAmend() {
  if (!amendTarget.value) return
  if (!amendForm.value.purpose.trim()) {
    amendPurposeError.value = 'Purpose is required.'
    return
  }
  amendPurposeError.value = ''
  amendSubmitting.value = true
  try {
    await amendContract(amendTarget.value.id, {
      notes: amendForm.value.notes || undefined,
      purpose: amendForm.value.purpose || undefined,
      startDate: amendForm.value.startDate || undefined,
      endDate: amendForm.value.endDate || undefined,
      terms: amendForm.value.terms || undefined,
      files: amendFiles.value.length ? amendFiles.value : undefined,
    })
    success('Change request submitted.')
    amendDialogOpen.value = false
    amendTarget.value = null
    amendFiles.value = []
  } catch (caughtError) {
    error(caughtError, { fallback: 'Could not submit your change request.' })
  } finally {
    amendSubmitting.value = false
  }
}

async function confirmAction() {
  if (!pendingAction.value) return
  const activeAction = pendingAction.value.action
  actionSubmitting.value = true
  try {
    if (activeAction === 'accept') {
      await acceptContract(pendingAction.value.contract.id)
      success('Agreement accepted.')
    } else if (activeAction === 'reject') {
      await rejectContract(pendingAction.value.contract.id, actionReason.value || undefined)
      success('Agreement rejected.')
    } else {
      await cancelContract(pendingAction.value.contract.id, currentRole.value, actionReason.value || undefined)
      success('Agreement cancelled.')
    }
    actionDialogOpen.value = false
    pendingAction.value = null
    actionReason.value = ''
  } catch (caughtError) {
    error(caughtError, {
      fallback:
        activeAction === 'accept'
          ? 'Could not accept the agreement.'
          : activeAction === 'reject'
            ? 'Could not reject the agreement.'
            : 'Could not cancel the agreement.',
    })
  } finally {
    actionSubmitting.value = false
  }
}

function subscribeContractsForRole() {
  const uid = authStore.user?.uid
  if (!uid) {
    loading.value = false
    return
  }

  const callback = (items: ContractRecord[]) => {
    contracts.value = items
    loading.value = false
  }

  unsubscribeContracts =
    currentRole.value === 'school'
      ? subscribeSchoolContracts(uid, callback)
      : subscribeCompanyContracts(uid, callback)
}

onMounted(() => {
  subscribeContractsForRole()
})

onUnmounted(() => {
  unsubscribeContracts?.()
})
</script>

<template>
  <MainLayout :role="currentRole" title="Agreements" active-item="agreements">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Agreements Workspace</h2>
            <p class="text-sm text-muted-foreground">
              Manage agreement requests, approvals, rejections, and cancellations between schools and companies.
            </p>
          </div>
          <div class="flex flex-col items-start gap-2 sm:items-end">
            <Button @click="openCreatePage">
              <Plus class="h-4 w-4" />
              <span>New Agreement Request</span>
            </Button>
            <button
              type="button"
              class="text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
              @click="router.push({ name: 'agreement-types-manage' })"
            >
              Advanced: custom templates
            </button>
          </div>
        </CardHeader>
      </Card>

      <Card class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">My Agreements</h2>
            <p class="text-sm text-muted-foreground">
              Review active agreements and respond to incoming requests.
            </p>
          </div>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
              <Button
                size="sm"
                :variant="statusTab === 'active' ? 'default' : 'outline'"
                @click="statusTab = 'active'"
              >
                Active
              </Button>
              <Button
                size="sm"
                :variant="statusTab === 'pending' ? 'default' : 'outline'"
                @click="statusTab = 'pending'"
              >
                Pending{{ pendingCount ? ` (${pendingCount})` : '' }}
              </Button>
              <Button
                size="sm"
                :variant="statusTab === 'cancelled' ? 'default' : 'outline'"
                @click="statusTab = 'cancelled'"
              >
                Cancelled{{ cancelledCount ? ` (${cancelledCount})` : '' }}
              </Button>
            </div>
            <div class="relative max-w-md flex-1">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input v-model="search" placeholder="Search agreements..." class="pl-9" />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="loading" class="space-y-3">
            <Skeleton v-for="index in 5" :key="index" class="h-20 rounded-xl" />
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="contract in filteredContracts"
              :key="contract.id"
              class="rounded-xl border border-border px-4 py-3"
            >
              <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0 space-y-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="truncate text-base font-semibold text-foreground">{{ agreementTitle(contract) }}</h3>
                    <Badge :variant="statusBadgeVariant(contract.status)">{{ statusLabel(contract) }}</Badge>
                    <Badge v-if="isAgreementExpired(contract)" variant="outline">Expired</Badge>
                  </div>
                  <p class="text-sm text-muted-foreground">
                    {{ currentRole === 'school' ? contract.companyName : contract.schoolName }}
                    <span v-if="contract.moaReferenceNo"> · {{ contract.moaReferenceNo }}</span>
                    ·
                    {{ contract.startDate ? formatDate(contract.startDate) : 'No start' }}
                    –
                    <span v-if="contract.endDate">
                      Ends {{ formatDate(contract.endDate) }}
                    </span>
                    <span v-else>No end</span>
                    · {{
                      contract.internshipSlots == null || Number(contract.internshipSlots) <= 0
                        ? 'Slots: not set'
                        : `${contract.internshipSlots} slots`
                    }}
                  </p>
                  <p v-if="contract.cancelledReason" class="text-sm text-destructive">
                    Cancelled: {{ contract.cancelledReason }}
                  </p>
                  <p v-else-if="contract.rejectedReason" class="text-sm text-destructive">
                    Rejected: {{ contract.rejectedReason }}
                  </p>
                  <div
                    v-if="contract.attachments?.length"
                    class="flex flex-wrap items-center gap-2 pt-1"
                  >
                    <span class="text-xs font-medium text-muted-foreground">Documents:</span>
                    <FilePreviewActions
                      v-for="(file, index) in contract.attachments"
                      :key="`${contract.id}-file-${index}`"
                      :file-name="file.name || `Document ${index + 1}`"
                      :mime-type="file.type"
                      :file-size="file.size"
                      :source="file.url || null"
                    />
                  </div>
                </div>
                <div class="flex flex-wrap gap-2">
                  <Button v-if="canReviewChanges(contract)" size="sm" variant="outline" @click="openReviewDialog(contract)">
                    <Eye class="h-4 w-4" />
                    View changes
                  </Button>
                  <Button v-if="canAccept(contract)" size="sm" @click="openActionDialog(contract, 'accept')">
                    <CheckCircle2 class="h-4 w-4" />
                    Accept
                  </Button>
                  <Button v-if="canReject(contract)" size="sm" variant="outline" @click="openActionDialog(contract, 'reject')">
                    <XCircle class="h-4 w-4" />
                    Reject
                  </Button>
                  <Button v-if="canAmend(contract)" size="sm" variant="outline" @click="openAmendDialog(contract)">
                    Request changes
                  </Button>
                  <Button
                    v-if="canCancel(contract)"
                    size="sm"
                    variant="outline"
                    class="ml-1 border-red-300 text-red-700 hover:bg-red-50 hover:text-red-800 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40"
                    @click="openActionDialog(contract, 'cancel')"
                  >
                    Cancel
                  </Button>
                </div>
              </div>
            </div>

            <div v-if="!filteredContracts.length" class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-muted-foreground">
              {{
                statusTab === 'cancelled'
                  ? 'No cancelled agreements.'
                  : statusTab === 'pending'
                    ? (search.trim() ? 'No pending agreements matched your search.' : 'No pending agreement requests.')
                    : (search.trim() ? 'No active agreements matched your search yet.' : 'No active agreements yet.')
              }}
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <Dialog v-model:open="actionDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>
            {{
              pendingAction?.action === 'accept'
                ? 'Accept Agreement'
                : pendingAction?.action === 'reject'
                  ? 'Reject Agreement'
                  : 'Cancel Agreement'
            }}
          </DialogTitle>
          <p class="text-sm text-muted-foreground">
            {{
              pendingAction?.action === 'accept'
                ? normalizeStatus(pendingAction?.contract.status) === 'pending_amendment'
                  ? 'Accept the proposed amendment to make this agreement active again.'
                  : 'Please confirm that you want to accept this agreement request.'
                : 'Add an optional reason to help the other party understand the update.'
            }}
          </p>
        </DialogHeader>
        <div class="mt-6 space-y-4">
          <Textarea
            v-if="pendingAction?.action !== 'accept'"
            v-model="actionReason"
            :rows="5"
            placeholder="Enter a reason"
          />
          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="close">Close</Button>
            <Button
              :variant="pendingAction?.action === 'cancel' ? 'destructive' : 'outline'"
              :disabled="actionSubmitting"
              @click="confirmAction"
            >
              <LoaderCircle v-if="actionSubmitting" class="h-4 w-4 animate-spin" />
              <Send v-else class="h-4 w-4" />
              <span>
                {{
                  pendingAction?.action === 'accept'
                    ? 'Confirm Accept'
                    : pendingAction?.action === 'reject'
                      ? 'Confirm Reject'
                      : 'Confirm Cancel'
                }}
              </span>
            </Button>
          </div>
        </div>
      </template>
    </Dialog>

    <Dialog v-model:open="amendDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Request changes</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Update agreement details. The other party must accept your changes before they take effect.
          </p>
        </DialogHeader>
        <div class="mt-6 space-y-4">
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm text-muted-foreground">Start date</label>
              <Input v-model="amendForm.startDate" type="date" />
            </div>
            <div>
              <label class="mb-1 block text-sm text-muted-foreground">End date</label>
              <Input v-model="amendForm.endDate" type="date" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm text-muted-foreground">Purpose <span class="text-red-500">*</span></label>
            <Textarea v-model="amendForm.purpose" :rows="3" required @blur="amendPurposeError = amendForm.purpose.trim() ? '' : 'Purpose is required.'" />
            <p v-if="amendPurposeError" class="mt-1 text-sm text-destructive">{{ amendPurposeError }}</p>
          </div>
          <div>
            <label class="mb-1 block text-sm text-muted-foreground">Terms</label>
            <Textarea v-model="amendForm.terms" :rows="3" />
          </div>
          <div>
            <label class="mb-1 block text-sm text-muted-foreground">Notes</label>
            <Textarea v-model="amendForm.notes" :rows="3" />
          </div>
          <div>
            <label class="mb-1 block text-sm text-muted-foreground">Attach documents (optional)</label>
            <Input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" @change="onAmendFilesSelected" />
            <p class="mt-1 text-xs text-muted-foreground">
              PDF, Word, Excel, or images up to 10MB each.
              <span v-if="amendFiles.length"> Selected: {{ amendFiles.map((file) => file.name).join(', ') }}</span>
            </p>
          </div>
          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="close">Close</Button>
            <Button :disabled="amendSubmitting" @click="confirmAmend">
              <LoaderCircle v-if="amendSubmitting" class="h-4 w-4 animate-spin" />
              <Send v-else class="h-4 w-4" />
              <span>Send change request</span>
            </Button>
          </div>
        </div>
      </template>
    </Dialog>

    <Dialog v-model:open="reviewDialogOpen">
      <template #default="{ close }">
        <DialogHeader>
          <DialogTitle>Proposed changes</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Review what {{ reviewTarget?.requestedByRole === 'school' ? 'the school' : 'the company' }} wants to change before you accept.
          </p>
        </DialogHeader>
        <div class="mt-6 space-y-4">
          <div
            v-for="(change, key) in latestAmendment(reviewTarget)?.changes || {}"
            :key="String(key)"
            class="rounded-xl border border-border bg-muted/40 p-4"
          >
            <p class="text-sm font-semibold text-foreground">{{ changeFieldLabel(String(key)) }}</p>
            <p class="mt-2 text-sm text-muted-foreground">
              From: <span class="text-foreground">{{ formatChangeValue(change.from) }}</span>
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
              To: <span class="text-foreground">{{ formatChangeValue(change.to) }}</span>
            </p>
          </div>
          <div v-if="reviewTarget?.attachments?.length" class="rounded-xl border border-border p-4">
            <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-foreground">
              <Paperclip class="h-4 w-4" />
              Attached documents
            </p>
            <ul class="space-y-2 text-sm text-muted-foreground">
              <li
                v-for="(file, index) in reviewTarget.attachments"
                :key="`${file.name}-${index}`"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 px-3 py-2"
              >
                <span class="min-w-0 truncate font-medium text-foreground">
                  {{ file.name || `Document ${index + 1}` }}
                </span>
                <FilePreviewActions
                  v-if="file.url"
                  :file-name="file.name || `Document ${index + 1}`"
                  :mime-type="file.type"
                  :file-size="file.size"
                  :source="file.url"
                />
                <span v-else class="text-xs text-muted-foreground">No file URL</span>
              </li>
            </ul>
          </div>
          <div class="flex flex-wrap justify-end gap-3">
            <Button variant="outline" @click="close">Close</Button>
            <Button
              v-if="reviewTarget && canAccept(reviewTarget)"
              @click="() => { close(); if (reviewTarget) openActionDialog(reviewTarget, 'accept') }"
            >
              <CheckCircle2 class="h-4 w-4" />
              Accept changes
            </Button>
          </div>
        </div>
      </template>
    </Dialog>
  </MainLayout>
</template>

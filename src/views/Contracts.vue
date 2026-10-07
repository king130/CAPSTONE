<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  CheckCircle2,
  LoaderCircle,
  Plus,
  Search,
  Send,
  XCircle,
} from 'lucide-vue-next'
import { useRouter } from 'vue-router'

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

function statusLabel(status?: string) {
  const normalized = normalizeStatus(status)
  if (normalized === 'pending_amendment') return 'pending amendment'
  return normalized
}

function formatDate(value: unknown) {
  if (!value) return 'Not available'
  const date = new Date(String(value))
  if (Number.isNaN(date.getTime())) return 'Not available'
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const authStore = useAuthStore()
const { success, error } = useToast()
const router = useRouter()

const currentRole = computed<ContractRole>(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const loading = ref(true)
const search = ref('')
const contracts = ref<ContractRecord[]>([])
const actionDialogOpen = ref(false)
const pendingAction = ref<PendingActionState | null>(null)
const actionReason = ref('')
const actionSubmitting = ref(false)
const amendDialogOpen = ref(false)
const amendTarget = ref<ContractRecord | null>(null)
const amendSubmitting = ref(false)
const amendForm = ref({
  notes: '',
  purpose: '',
  startDate: '',
  endDate: '',
  terms: '',
})

let unsubscribeContracts: (() => void) | null = null

const filteredContracts = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) return contracts.value
  return contracts.value.filter((contract) =>
    [
      contract.subject,
      contract.contractType,
      contract.moaReferenceNo,
      contract.schoolName,
      contract.companyName,
      contract.status,
    ].some((value) => String(value || '').toLowerCase().includes(query)),
  )
})

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
  amendForm.value = {
    notes: contract.notes || '',
    purpose: contract.purpose || '',
    startDate: contract.startDate || '',
    endDate: contract.endDate || '',
    terms: contract.terms || '',
  }
  amendDialogOpen.value = true
}

async function confirmAmend() {
  if (!amendTarget.value) return
  amendSubmitting.value = true
  try {
    await amendContract(amendTarget.value.id, {
      notes: amendForm.value.notes || undefined,
      purpose: amendForm.value.purpose || undefined,
      startDate: amendForm.value.startDate || undefined,
      endDate: amendForm.value.endDate || undefined,
      terms: amendForm.value.terms || undefined,
    })
    success('Amendment submitted.')
    amendDialogOpen.value = false
    amendTarget.value = null
  } catch (caughtError) {
    error(caughtError, { fallback: 'Could not amend the agreement.' })
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
          ? 'Could not accept the contract.'
          : activeAction === 'reject'
            ? 'Could not reject the contract.'
            : 'Could not cancel the contract.',
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
              Restore agreement requests, approvals, rejections, and cancellations between schools and companies.
            </p>
          </div>
          <div class="flex flex-wrap gap-3">
            <Button variant="outline" @click="router.push({ name: 'agreement-types-manage' })">
              <span>Manage Agreement Types</span>
            </Button>
            <Button @click="openCreatePage">
              <Plus class="h-4 w-4" />
              <span>New Agreement Request</span>
            </Button>
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
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="search" placeholder="Search agreements..." class="pl-9" />
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="loading" class="space-y-3">
            <Skeleton v-for="index in 5" :key="index" class="h-28 rounded-xl" />
          </div>
          <div v-else class="space-y-4">
            <Card
              v-for="contract in filteredContracts"
              :key="contract.id"
              class="border-border/70 shadow-none"
            >
              <CardContent class="space-y-4 p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="space-y-2">
                    <div class="flex items-center gap-2">
                      <h3 class="text-lg font-semibold text-foreground">{{ contract.subject || 'Untitled contract' }}</h3>
                      <Badge :variant="statusBadgeVariant(contract.status)">{{ statusLabel(contract.status) }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                      {{ currentRole === 'school' ? contract.companyName : contract.schoolName }}
                      <span v-if="contract.contractType"> · {{ contract.contractType }}</span>
                    </p>
                    <p class="text-sm text-muted-foreground">
                      Requested by {{ contract.requestedByRole }} on {{ formatDate(contract.createdAt) }}
                    </p>
                    <p v-if="contract.moaReferenceNo" class="text-sm font-medium text-primary">
                      Reference No. {{ contract.moaReferenceNo }}
                    </p>
                    <p
                      v-if="normalizeStatus(contract.status) === 'pending_amendment'"
                      class="text-sm text-amber-700"
                    >
                      Amendment awaiting counterparty acceptance.
                    </p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <Button v-if="canAccept(contract)" size="sm" @click="openActionDialog(contract, 'accept')">
                      <CheckCircle2 class="h-4 w-4" />
                      Accept
                    </Button>
                    <Button v-if="canReject(contract)" size="sm" variant="outline" @click="openActionDialog(contract, 'reject')">
                      <XCircle class="h-4 w-4" />
                      Reject
                    </Button>
                    <Button v-if="canAmend(contract)" size="sm" variant="outline" @click="openAmendDialog(contract)">
                      Amend
                    </Button>
                    <Button v-if="canCancel(contract)" size="sm" variant="ghost" @click="openActionDialog(contract, 'cancel')">
                      Cancel
                    </Button>
                  </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                  <div class="rounded-xl bg-muted p-4">
                    <p class="text-sm text-muted-foreground">Partner</p>
                    <p class="mt-2 font-medium text-foreground">{{ currentRole === 'school' ? contract.companyName : contract.schoolName }}</p>
                  </div>
                  <div class="rounded-xl bg-muted p-4">
                    <p class="text-sm text-muted-foreground">Type</p>
                    <p class="mt-2 font-medium text-foreground">{{ contract.contractType || 'Custom' }}</p>
                  </div>
                  <div class="rounded-xl bg-muted p-4">
                    <p class="text-sm text-muted-foreground">Reference No.</p>
                    <p class="mt-2 font-medium text-foreground">{{ contract.moaReferenceNo || 'Generating...' }}</p>
                  </div>
                  <div class="rounded-xl bg-muted p-4">
                    <p class="text-sm text-muted-foreground">Period</p>
                    <p class="mt-2 font-medium text-foreground">
                      {{ contract.startDate ? formatDate(contract.startDate) : 'Not set' }}
                      -
                      {{ contract.endDate ? formatDate(contract.endDate) : 'Not set' }}
                    </p>
                  </div>
                  <div class="rounded-xl bg-muted p-4">
                    <p class="text-sm text-muted-foreground">Slots</p>
                    <p class="mt-2 font-medium text-foreground">{{ contract.internshipSlots || 0 }}</p>
                  </div>
                </div>

                <div v-if="contract.purpose || contract.notes || contract.rejectedReason || contract.cancelledReason" class="space-y-2">
                  <p v-if="contract.purpose" class="text-sm text-foreground"><span class="font-medium">Purpose:</span> {{ contract.purpose }}</p>
                  <p v-if="contract.notes" class="text-sm text-foreground"><span class="font-medium">Notes:</span> {{ contract.notes }}</p>
                  <p v-if="contract.rejectedReason" class="text-sm text-red-600"><span class="font-medium">Rejected reason:</span> {{ contract.rejectedReason }}</p>
                  <p v-if="contract.cancelledReason" class="text-sm text-red-600"><span class="font-medium">Cancelled reason:</span> {{ contract.cancelledReason }}</p>
                </div>
              </CardContent>
            </Card>

            <Card v-if="!filteredContracts.length" class="border-dashed">
              <CardContent class="py-10 text-center text-muted-foreground">
                No contracts matched your search yet.
              </CardContent>
            </Card>
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
          <DialogTitle>Amend Agreement</DialogTitle>
          <p class="text-sm text-muted-foreground">
            Update agreement details. Active agreements move to pending amendment until the other party accepts.
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
            <label class="mb-1 block text-sm text-muted-foreground">Purpose</label>
            <Textarea v-model="amendForm.purpose" :rows="3" />
          </div>
          <div>
            <label class="mb-1 block text-sm text-muted-foreground">Terms</label>
            <Textarea v-model="amendForm.terms" :rows="3" />
          </div>
          <div>
            <label class="mb-1 block text-sm text-muted-foreground">Notes</label>
            <Textarea v-model="amendForm.notes" :rows="3" />
          </div>
          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="close">Close</Button>
            <Button :disabled="amendSubmitting" @click="confirmAmend">
              <LoaderCircle v-if="amendSubmitting" class="h-4 w-4 animate-spin" />
              <Send v-else class="h-4 w-4" />
              <span>Submit Amendment</span>
            </Button>
          </div>
        </div>
      </template>
    </Dialog>
  </MainLayout>
</template>

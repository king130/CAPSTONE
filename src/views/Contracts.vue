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
  cancelContract,
  rejectContract,
  subscribeCompanyContracts,
  subscribeSchoolContracts,
  type ContractRecord,
} from '@/services/contracts'
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
  if (normalized === 'rejected') return 'rejected'
  if (normalized === 'cancelled') return 'cancelled'
  return 'pending'
}

function statusBadgeVariant(status?: string) {
  const normalized = normalizeStatus(status)
  if (normalized === 'active') return 'success'
  if (normalized === 'pending') return 'warning'
  return 'destructive'
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
  return normalizeStatus(contract.status) === 'pending' && contract.requestedByRole !== currentRole.value
}

function canReject(contract: ContractRecord) {
  return normalizeStatus(contract.status) === 'pending' && contract.requestedByRole !== currentRole.value
}

function canCancel(contract: ContractRecord) {
  return ['pending', 'active'].includes(normalizeStatus(contract.status))
}

function openCreatePage() {
  void router.push({ name: 'contracts-new' })
}

function openActionDialog(contract: ContractRecord, action: ContractAction) {
  pendingAction.value = { contract, action }
  actionReason.value = ''
  actionDialogOpen.value = true
}

async function confirmAction() {
  if (!pendingAction.value) return
  const activeAction = pendingAction.value.action
  actionSubmitting.value = true
  try {
    if (activeAction === 'accept') {
      await acceptContract(pendingAction.value.contract.id)
      success('Contract accepted.')
    } else if (activeAction === 'reject') {
      await rejectContract(pendingAction.value.contract.id, actionReason.value || undefined)
      success('Contract rejected.')
    } else {
      await cancelContract(pendingAction.value.contract.id, currentRole.value, actionReason.value || undefined)
      success('Contract cancelled.')
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
  <MainLayout :role="currentRole" title="Contracts" active-item="contracts">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">Contracts Workspace</h2>
            <p class="text-sm text-muted-foreground">
              Restore contract requests, approvals, rejections, and cancellations between schools and companies.
            </p>
          </div>
          <div class="flex flex-wrap gap-3">
            <Button variant="outline" @click="router.push({ name: 'contract-types-manage' })">
              <span>Manage Contract Types</span>
            </Button>
            <Button @click="openCreatePage">
              <Plus class="h-4 w-4" />
              <span>New Contract Request</span>
            </Button>
          </div>
        </CardHeader>
      </Card>

      <Card class="border-border/80 shadow-sm">
        <CardHeader class="space-y-4">
          <div>
            <h2 class="text-2xl font-semibold text-foreground">My Contracts</h2>
            <p class="text-sm text-muted-foreground">
              Review active agreements and respond to incoming requests.
            </p>
          </div>
          <div class="relative max-w-md">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input v-model="search" placeholder="Search contracts..." class="pl-9" />
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
                      <h3 class="text-lg font-semibold text-slate-950">{{ contract.subject || 'Untitled contract' }}</h3>
                      <Badge :variant="statusBadgeVariant(contract.status)">{{ contract.status }}</Badge>
                    </div>
                    <p class="text-sm text-slate-600">
                      {{ currentRole === 'school' ? contract.companyName : contract.schoolName }}
                      <span v-if="contract.contractType"> · {{ contract.contractType }}</span>
                    </p>
                    <p class="text-sm text-slate-500">
                      Requested by {{ contract.requestedByRole }} on {{ formatDate(contract.createdAt) }}
                    </p>
                    <p v-if="contract.moaReferenceNo" class="text-sm font-medium text-sky-700">
                      Reference No. {{ contract.moaReferenceNo }}
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
                    <Button v-if="canCancel(contract)" size="sm" variant="ghost" @click="openActionDialog(contract, 'cancel')">
                      Cancel
                    </Button>
                  </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                  <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Partner</p>
                    <p class="mt-2 font-medium text-slate-900">{{ currentRole === 'school' ? contract.companyName : contract.schoolName }}</p>
                  </div>
                  <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Type</p>
                    <p class="mt-2 font-medium text-slate-900">{{ contract.contractType || 'Custom' }}</p>
                  </div>
                  <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Reference No.</p>
                    <p class="mt-2 font-medium text-slate-900">{{ contract.moaReferenceNo || 'Generating...' }}</p>
                  </div>
                  <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Period</p>
                    <p class="mt-2 font-medium text-slate-900">
                      {{ contract.startDate ? formatDate(contract.startDate) : 'Not set' }}
                      -
                      {{ contract.endDate ? formatDate(contract.endDate) : 'Not set' }}
                    </p>
                  </div>
                  <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Slots</p>
                    <p class="mt-2 font-medium text-slate-900">{{ contract.internshipSlots || 0 }}</p>
                  </div>
                </div>

                <div v-if="contract.purpose || contract.notes || contract.rejectedReason || contract.cancelledReason" class="space-y-2">
                  <p v-if="contract.purpose" class="text-sm text-slate-700"><span class="font-medium">Purpose:</span> {{ contract.purpose }}</p>
                  <p v-if="contract.notes" class="text-sm text-slate-700"><span class="font-medium">Notes:</span> {{ contract.notes }}</p>
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
                ? 'Accept Contract'
                : pendingAction?.action === 'reject'
                  ? 'Reject Contract'
                  : 'Cancel Contract'
            }}
          </DialogTitle>
          <p class="text-sm text-muted-foreground">
            {{
              pendingAction?.action === 'accept'
                ? 'Please confirm that you want to accept this contract request.'
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
  </MainLayout>
</template>

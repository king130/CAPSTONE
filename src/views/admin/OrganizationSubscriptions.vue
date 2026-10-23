<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Swal from '@/services/swal'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import { apiFetch } from '@/services/http'

type AccessStatus = 'trial' | 'active' | 'grace' | 'expired' | string

type OrgSubscriptionRow = {
  id: string
  name: string
  type: string
  access: {
    status: AccessStatus
    canWrite: boolean
    daysRemaining?: number | null
    plan?: string | null
    message?: string
  }
}

const rows = ref<OrgSubscriptionRow[]>([])
const loading = ref(true)
const statusFilter = ref<string>('')
const busyId = ref<string | null>(null)

const filtered = computed(() => {
  if (!statusFilter.value) return rows.value
  return rows.value.filter((row) => row.access?.status === statusFilter.value)
})

const typeLabel = (type: string) => {
  if (type === 'municipal_hall') return 'Municipal Hall'
  if (type === 'school') return 'School'
  if (type === 'company') return 'Company'
  return type
}

async function load() {
  loading.value = true
  try {
    const query = statusFilter.value ? `?status=${encodeURIComponent(statusFilter.value)}` : ''
    const response = await apiFetch<{ data: OrgSubscriptionRow[] }>(`/admin/subscriptions${query}`)
    rows.value = response.data ?? []
  } catch (error) {
    console.error(error)
    await Swal.fire({
      icon: 'error',
      title: 'Unable to load subscriptions',
      text: 'The organization subscription list could not be loaded.',
    })
  } finally {
    loading.value = false
  }
}

async function extendTrial(row: OrgSubscriptionRow) {
  const { value: reason } = await Swal.fire({
    title: `Extend trial for ${row.name}?`,
    input: 'text',
    inputLabel: 'Reason (required)',
    inputPlaceholder: 'Why is this trial being extended?',
    showCancelButton: true,
    confirmButtonText: 'Extend 14 days',
    inputValidator: (v) => (!v || v.trim().length < 3 ? 'Reason is required' : null),
  })
  if (!reason) return

  busyId.value = row.id
  try {
    await apiFetch(`/admin/subscriptions/${row.id}/extend-trial`, {
      method: 'POST',
      body: JSON.stringify({ extraDays: 14, reason: String(reason).trim() }),
    })
    await load()
    await Swal.fire({ icon: 'success', title: 'Trial extended', text: 'A fresh trial window was applied.' })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Extend failed',
      text: error instanceof Error ? error.message : 'Could not extend trial.',
    })
  } finally {
    busyId.value = null
  }
}

async function activate(row: OrgSubscriptionRow) {
  const confirmed = await Swal.fire({
    title: `Activate Standard for ${row.name}?`,
    text: 'This manually activates a paid Standard plan for one month. Confirmation required.',
    showCancelButton: true,
    confirmButtonText: 'Activate',
    input: 'text',
    inputLabel: 'Reason (required)',
    inputValidator: (v) => (!v || v.trim().length < 3 ? 'Reason is required' : null),
  })
  if (!confirmed.isConfirmed || !confirmed.value) return

  busyId.value = row.id
  try {
    await apiFetch(`/admin/subscriptions/${row.id}/activate`, {
      method: 'POST',
      body: JSON.stringify({
        plan: 'standard',
        billingCycle: 'monthly',
        reason: String(confirmed.value).trim(),
      }),
    })
    await load()
    await Swal.fire({ icon: 'success', title: 'Plan activated', text: 'Paid access restored.' })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Activation failed',
      text: error instanceof Error ? error.message : 'Could not activate plan.',
    })
  } finally {
    busyId.value = null
  }
}

async function deactivate(row: OrgSubscriptionRow, mode: 'expired' | 'grace') {
  const confirmed = await Swal.fire({
    title: mode === 'grace' ? `Force grace for ${row.name}?` : `Force expired for ${row.name}?`,
    showCancelButton: true,
    confirmButtonText: mode === 'grace' ? 'Set grace' : 'Set expired',
    input: 'text',
    inputLabel: 'Reason (required)',
    inputValidator: (v) => (!v || v.trim().length < 3 ? 'Reason is required' : null),
  })
  if (!confirmed.isConfirmed || !confirmed.value) return

  busyId.value = row.id
  try {
    await apiFetch(`/admin/subscriptions/${row.id}/deactivate`, {
      method: 'POST',
      body: JSON.stringify({ mode, reason: String(confirmed.value).trim() }),
    })
    await load()
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Update failed',
      text: error instanceof Error ? error.message : 'Could not update status.',
    })
  } finally {
    busyId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="space-y-5" data-testid="admin-org-subscriptions">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h2 class="text-2xl font-semibold tracking-tight text-foreground">Organization subscriptions</h2>
        <p class="mt-1 text-sm text-muted-foreground">
          Filter by Trial, Active, Grace, or Expired. Extend trials and activate plans with a logged reason.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <select
          v-model="statusFilter"
          class="h-9 rounded-md border border-border bg-background px-3 text-sm"
          data-testid="subscription-status-filter"
          @change="load"
        >
          <option value="">All statuses</option>
          <option value="trial">Trial</option>
          <option value="active">Active</option>
          <option value="grace">Grace</option>
          <option value="expired">Expired</option>
        </select>
        <Button size="sm" variant="outline" :disabled="loading" @click="load">Refresh</Button>
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted-foreground">Loading organizations…</p>

    <div v-else class="grid gap-3">
      <Card v-for="row in filtered" :key="row.id" class="border-border">
        <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="font-semibold text-foreground">{{ row.name }}</p>
            <p class="text-sm text-muted-foreground">
              {{ typeLabel(row.type) }} · {{ row.access?.status }} · plan {{ row.access?.plan || '—' }}
              <template v-if="row.access?.daysRemaining != null">
                · {{ row.access.daysRemaining }} day(s)
              </template>
            </p>
            <p class="mt-1 text-xs text-muted-foreground">{{ row.access?.message }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" :disabled="busyId === row.id" data-testid="admin-extend-trial" @click="extendTrial(row)">
              Extend trial
            </Button>
            <Button size="sm" :disabled="busyId === row.id" data-testid="admin-activate-plan" @click="activate(row)">
              Activate
            </Button>
            <Button size="sm" variant="outline" :disabled="busyId === row.id" @click="deactivate(row, 'grace')">
              Grace
            </Button>
            <Button size="sm" variant="destructive" :disabled="busyId === row.id" @click="deactivate(row, 'expired')">
              Expire
            </Button>
          </div>
        </CardContent>
      </Card>
      <p v-if="!filtered.length" class="text-sm text-muted-foreground">No organizations match this filter.</p>
    </div>
  </section>
</template>

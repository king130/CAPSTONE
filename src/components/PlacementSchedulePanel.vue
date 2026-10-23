<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import ScheduleEditor from '@/components/ScheduleEditor.vue'
import { useToast } from '@/composables/useToast'
import {
  decidePlacementScheduleRequest,
  fetchPlacementSchedule,
  requestPlacementScheduleChange,
  type PlacementScheduleState,
} from '@/services/placementSchedules'
import { useAuthStore } from '@/stores/auth'
import { type ScheduleSlot, validateSlots } from '@/utils/scheduleSlots'

const props = defineProps<{
  applicationId: string
  mode?: 'host' | 'school' | 'student'
  accepted?: boolean
}>()

const authStore = useAuthStore()
const { success, error } = useToast()

const state = ref<PlacementScheduleState | null>(null)
const loading = ref(false)
const submitting = ref(false)
const showRequestForm = ref(false)
const requestSlots = ref<ScheduleSlot[]>([])
const requestReason = ref('')
const confirmDecision = ref<'approved' | 'declined' | null>(null)
const decisionNote = ref('')

const role = computed(() => authStore.user?.role)
const isStudent = computed(() => props.mode === 'student' || role.value === 'student')
const isHost = computed(() => props.mode === 'host' || role.value === 'company' || role.value === 'municipal_hall')
const canDecide = computed(() => isHost.value && !isStudent.value)
const canRequest = computed(() => isStudent.value && props.accepted !== false)

async function load() {
  if (!props.applicationId) return
  loading.value = true
  try {
    state.value = await fetchPlacementSchedule(props.applicationId)
    if (!requestSlots.value.length && state.value.activeSlots?.length) {
      requestSlots.value = state.value.activeSlots.map((s) => ({ ...s }))
    } else if (!requestSlots.value.length && state.value.postingSlots?.length) {
      requestSlots.value = state.value.postingSlots.map((s) => ({ ...s }))
    }
  } catch (caught) {
    error(caught, { fallback: 'Unable to load placement schedule.' })
  } finally {
    loading.value = false
  }
}

async function submitRequest() {
  const validation = validateSlots(requestSlots.value, state.value?.maxDailyHours ?? 12)
  if (validation) {
    error(validation)
    return
  }
  if (!requestReason.value.trim()) {
    error('Provide a reason for the schedule change.')
    return
  }
  submitting.value = true
  try {
    const result = await requestPlacementScheduleChange(
      props.applicationId,
      requestSlots.value,
      requestReason.value.trim(),
    )
    state.value = result.schedule
    showRequestForm.value = false
    requestReason.value = ''
    success('Schedule change requested.')
  } catch (caught) {
    error(caught, { fallback: 'Unable to submit schedule request.' })
  } finally {
    submitting.value = false
  }
}

async function confirmHostDecision() {
  if (!confirmDecision.value || !state.value?.openRequest?.id) return
  submitting.value = true
  try {
    const result = await decidePlacementScheduleRequest(
      props.applicationId,
      state.value.openRequest.id,
      confirmDecision.value,
      decisionNote.value || undefined,
    )
    state.value = result.schedule
    success(confirmDecision.value === 'approved' ? 'Schedule approved.' : 'Schedule declined.')
    confirmDecision.value = null
    decisionNote.value = ''
  } catch (caught) {
    error(caught, { fallback: 'Unable to decide schedule request.' })
  } finally {
    submitting.value = false
  }
}

onMounted(load)
watch(() => props.applicationId, load)
</script>

<template>
  <div class="space-y-4 rounded-2xl border border-border/70 bg-card p-4 text-card-foreground">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h4 class="text-sm font-semibold text-foreground">Placement schedule</h4>
        <p class="text-xs text-muted-foreground">
          Default schedule from the posting; students may request a change after acceptance.
        </p>
      </div>
      <Button size="sm" variant="outline" :disabled="loading" @click="load">
        <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin" />
        Refresh
      </Button>
    </div>

    <div v-if="state" class="space-y-2">
      <div class="flex flex-wrap items-center gap-2">
        <Badge variant="outline">{{ state.status || (state.hasPlacementSchedule ? 'default' : 'Not set') }}</Badge>
        <span class="text-sm text-foreground">{{ state.activeLabel || 'Not set' }}</span>
      </div>
      <p v-if="!state.hasPlacementSchedule" class="text-sm text-muted-foreground">
        No placement schedule yet. It is created when the application is accepted.
      </p>
      <p v-else-if="!state.postingSet && state.source === 'system_default'" class="text-xs text-muted-foreground">
        Posting had no schedule; Mon–Fri 8:00 AM–5:00 PM was applied as the default.
      </p>
    </div>

    <div v-if="state?.openRequest" class="space-y-2 rounded-xl border border-border/60 bg-muted/40 p-3">
      <p class="text-sm font-medium text-foreground">Pending request</p>
      <p class="text-sm text-muted-foreground">{{ state.openRequest.requestedLabel }}</p>
      <p class="text-xs text-muted-foreground">Reason: {{ state.openRequest.reason }}</p>
      <div v-if="canDecide" class="flex flex-wrap gap-2">
        <Button size="sm" :disabled="submitting" @click="confirmDecision = 'approved'">Approve</Button>
        <Button size="sm" variant="outline" :disabled="submitting" @click="confirmDecision = 'declined'">Decline</Button>
      </div>
      <p v-else-if="props.mode === 'school'" class="text-xs text-muted-foreground">
        View only — the host decides this request.
      </p>
    </div>

    <div v-if="canRequest && state?.hasPlacementSchedule && !state.openRequest" class="space-y-3">
      <Button
        v-if="!showRequestForm"
        size="sm"
        variant="outline"
        @click="showRequestForm = true"
      >
        Request different schedule
      </Button>
      <div v-else class="space-y-3 border-t border-border/60 pt-3">
        <ScheduleEditor v-model="requestSlots" />
        <label class="block space-y-1 text-sm">
          <span class="font-medium text-foreground">Reason</span>
          <Input v-model="requestReason" placeholder="Why do you need a different schedule?" />
        </label>
        <div class="flex flex-wrap gap-2">
          <Button :disabled="submitting" @click="submitRequest">
            <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
            Submit request
          </Button>
          <Button variant="outline" @click="showRequestForm = false">Cancel</Button>
        </div>
      </div>
    </div>

    <div v-if="state?.history?.length" class="space-y-2 border-t border-border/60 pt-3">
      <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Request history</p>
      <ul class="max-h-40 space-y-2 overflow-y-auto">
        <li
          v-for="item in state.history"
          :key="item.id"
          class="rounded-lg bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
        >
          <span class="font-medium text-foreground">{{ item.status }}</span>
          · {{ item.requestedLabel }}
          <div v-if="item.reason">Reason: {{ item.reason }}</div>
          <div v-if="item.decisionNote">Note: {{ item.decisionNote }}</div>
          <div>
            {{ item.requestedByName || 'Student' }}
            <template v-if="item.createdAt"> · {{ new Date(item.createdAt).toLocaleString() }}</template>
          </div>
        </li>
      </ul>
    </div>

    <div
      v-if="confirmDecision"
      class="fixed inset-0 z-50 flex items-end justify-center bg-background/70 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
    >
      <div class="w-full max-w-md space-y-4 rounded-2xl border border-border bg-card p-4 shadow-lg">
        <h5 class="text-sm font-semibold text-foreground">
          {{ confirmDecision === 'approved' ? 'Approve schedule request?' : 'Decline schedule request?' }}
        </h5>
        <Input v-model="decisionNote" placeholder="Optional note" />
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="confirmDecision = null">Cancel</Button>
          <Button :disabled="submitting" @click="confirmHostDecision">Confirm</Button>
        </div>
      </div>
    </div>
  </div>
</template>

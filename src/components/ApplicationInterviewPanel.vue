<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import { useToast } from '@/composables/useToast'
import {
  cancelInterview,
  confirmInterview,
  listInterviews,
  proposeInterview,
  type InterviewRecord,
} from '@/services/interviews'
import { hasPermission } from '@/services/permissions'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  applicationId: string
  mode?: 'manage' | 'student'
  allowSchedule?: boolean
}>()

const emit = defineEmits<{
  (e: 'updated'): void
}>()

const authStore = useAuthStore()
const { success, error } = useToast()
const interviews = ref<InterviewRecord[]>([])
const loading = ref(false)
const submitting = ref(false)
const form = ref({
  scheduledAt: '',
  durationMinutes: '60',
  mode: 'online' as 'onsite' | 'online',
  locationOrLink: '',
  notes: '',
})

const currentUserId = computed(() => String(authStore.user?.uid || ''))
const hostLabel = computed(() => {
  const type = String(
    authStore.user?.activeOrganization?.type || authStore.user?.profile?.organizationType || '',
  ).toLowerCase()
  return type === 'municipal_hall' ? 'Municipal Hall' : 'Company'
})
const canManage = () => hasPermission('org.manage_applications') || hasPermission('org.approve_applications')
const canSchedule = computed(() => {
  if (hasLockedInterview.value) return false
  if (props.mode === 'student' || !canManage()) return false
  const role = authStore.user?.role
  if (role === 'company' || role === 'admin') return true
  return role === 'school' && props.allowSchedule === true
})

const LOCKED_STATUSES = ['confirmed', 'scheduled', 'accepted', 'completed']
const OPEN_STATUSES = ['proposed', 'pending']

function interviewStatus(interview: InterviewRecord) {
  return String(interview.status || '').toLowerCase()
}

function isLockedStatus(status: string) {
  return LOCKED_STATUSES.includes(status)
}

function statusLabel(status: string) {
  if (status === 'proposed' || status === 'pending') return 'Pending'
  if (status === 'confirmed' || status === 'scheduled') return 'Scheduled'
  if (status === 'accepted') return 'Accepted'
  if (status === 'completed') return 'Completed'
  if (status === 'cancelled') return 'Cancelled'
  return status
}

function canConfirmInterview(interview: InterviewRecord): boolean {
  if (!OPEN_STATUSES.includes(interviewStatus(interview))) return false
  if (currentUserId.value && String(interview.createdByUserId) === currentUserId.value) return false
  return props.mode === 'student' || canManage()
}

function canRescheduleOrCancel(interview: InterviewRecord): boolean {
  return OPEN_STATUSES.includes(interviewStatus(interview))
}

const hasLockedInterview = computed(() => interviews.value.some((interview) => isLockedStatus(interviewStatus(interview))))

async function load() {
  if (!props.applicationId) return
  loading.value = true
  try {
    interviews.value = await listInterviews(props.applicationId)
  } catch (caught) {
    error(caught, { fallback: 'Unable to load interviews.' })
  } finally {
    loading.value = false
  }
}

async function submitPropose() {
  if (!form.value.scheduledAt) {
    error('Choose a date and time for the interview.')
    return
  }
  submitting.value = true
  try {
    await proposeInterview({
      application_id: props.applicationId,
      scheduled_at: form.value.scheduledAt,
      duration_minutes: Number(form.value.durationMinutes) || 60,
      mode: form.value.mode,
      location_or_link: form.value.locationOrLink || undefined,
      notes: form.value.notes || undefined,
    })
    success('Interview scheduled.')
    form.value = { scheduledAt: '', durationMinutes: '60', mode: 'online', locationOrLink: '', notes: '' }
    await load()
    emit('updated')
  } catch (caught) {
    error(caught, { fallback: 'Unable to schedule interview.' })
  } finally {
    submitting.value = false
  }
}

async function onConfirm(id: string) {
  submitting.value = true
  try {
    await confirmInterview(id)
    success('Interview confirmed.')
    await load()
    emit('updated')
  } catch (caught) {
    error(caught, { fallback: 'Unable to confirm interview.' })
  } finally {
    submitting.value = false
  }
}

async function onCancel(id: string) {
  submitting.value = true
  try {
    await cancelInterview(id, props.mode === 'student' ? 'Student requested reschedule' : undefined)
    success(props.mode === 'student' ? 'Reschedule requested.' : 'Interview cancelled.')
    await load()
    emit('updated')
  } catch (caught) {
    error(caught, { fallback: props.mode === 'student' ? 'Unable to request reschedule.' : 'Unable to cancel interview.' })
  } finally {
    submitting.value = false
  }
}

onMounted(load)
watch(() => props.applicationId, load)
</script>

<template>
  <div class="space-y-4 rounded-2xl border border-border/70 p-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <h4 class="text-sm font-semibold text-foreground">{{ hostLabel }} interview</h4>
        <p class="text-xs text-muted-foreground">
          {{
            canSchedule
              ? 'Schedule the interview after the school endorses this application.'
              : `Interview times are scheduled by the ${hostLabel.toLowerCase()}.`
          }}
        </p>
      </div>
      <Button size="sm" variant="outline" :disabled="loading" @click="load">
        <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin" />
        Refresh
      </Button>
    </div>

    <div v-if="interviews.length" class="space-y-2">
      <div
        v-for="interview in interviews"
        :key="interview.id"
        class="flex flex-col gap-2 rounded-xl bg-muted p-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div class="min-w-0">
          <p class="text-sm font-medium text-foreground">
            {{ new Date(interview.scheduledAt).toLocaleString() }}
            · {{ interview.durationMinutes }} min · {{ interview.mode }}
          </p>
          <p v-if="interview.locationOrLink" class="truncate text-xs text-muted-foreground">{{ interview.locationOrLink }}</p>
          <Badge class="mt-1" variant="outline">{{ statusLabel(interviewStatus(interview)) }}</Badge>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button
            v-if="canConfirmInterview(interview)"
            size="sm"
            :disabled="submitting"
            @click="onConfirm(interview.id)"
          >
            Confirm
          </Button>
          <Button
            v-if="canRescheduleOrCancel(interview)"
            size="sm"
            variant="outline"
            :disabled="submitting"
            @click="onCancel(interview.id)"
          >
            {{ props.mode === 'student' ? 'Request reschedule' : 'Cancel' }}
          </Button>
        </div>
      </div>
    </div>
    <p v-else class="text-sm text-muted-foreground">No interviews scheduled yet.</p>

    <div v-if="canSchedule" class="grid gap-3 border-t border-border/60 pt-4 sm:grid-cols-2">
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Date & time</span>
        <Input v-model="form.scheduledAt" type="datetime-local" />
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Duration (minutes)</span>
        <Input v-model="form.durationMinutes" type="number" min="15" step="15" />
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Mode</span>
        <Select v-model="form.mode">
          <option value="online">Online</option>
          <option value="onsite">Onsite</option>
        </Select>
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Location / link</span>
        <Input v-model="form.locationOrLink" placeholder="Meet link or address" />
      </label>
      <label class="space-y-1 text-sm sm:col-span-2">
        <span class="font-medium text-foreground">Notes</span>
        <Input v-model="form.notes" placeholder="Optional notes" />
      </label>
      <div class="sm:col-span-2">
        <Button :disabled="submitting" @click="submitPropose">
          <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
          Schedule interview
        </Button>
      </div>
    </div>
  </div>
</template>

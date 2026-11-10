<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import {
  confirmHoursAgreement,
  declineHoursAgreement,
  fetchHoursAgreement,
  proposeHoursAgreement,
  type HoursAgreementRecord,
} from '@/services/applications'
import { useToast } from '@/composables/useToast'
import { statusLabel } from '@/utils/statusLabels'

const props = defineProps<{
  applicationId: string
  /** school | host | student */
  mode: 'school' | 'host' | 'student'
  hoursMismatch?: boolean
  schoolRequiredHours?: number | null
  hostRequiredHours?: number | null
}>()

const { success, error } = useToast()
const loading = ref(false)
const saving = ref(false)
const agreement = ref<HoursAgreementRecord | null>(null)
const envelopeSchoolHours = ref<number | null>(null)
const envelopeHostHours = ref<number | null>(null)
const customHours = ref('')
const note = ref('')
const reason = ref('')

const canMutate = computed(() => props.mode === 'school' || props.mode === 'host')
const showMismatch = computed(
  () =>
    props.hoursMismatch ||
    (agreement.value &&
      agreement.value.schoolRequiredHours !== agreement.value.hostOfferedHours &&
      agreement.value.status !== 'agreed'),
)

/** Positive integer hours available for the "Use school/host hours" shortcuts. */
function positiveHours(value: unknown): number | null {
  const n = Number(value)
  return Number.isInteger(n) && n >= 1 ? n : null
}

const schoolHoursReady = computed(
  () =>
    positiveHours(agreement.value?.schoolRequiredHours) ??
    envelopeSchoolHours.value ??
    positiveHours(props.schoolRequiredHours),
)
const hostHoursReady = computed(
  () =>
    positiveHours(agreement.value?.hostOfferedHours) ??
    envelopeHostHours.value ??
    positiveHours(props.hostRequiredHours),
)

async function reload() {
  loading.value = true
  try {
    const res = await fetchHoursAgreement(props.applicationId)
    agreement.value = res.data.agreement
    envelopeSchoolHours.value = positiveHours(res.data.schoolRequiredHours)
    envelopeHostHours.value = positiveHours(res.data.hostRequiredHours)
    if (agreement.value?.proposedHours) {
      customHours.value = String(agreement.value.proposedHours)
    }
  } catch (caught) {
    error(caught, { fallback: 'Unable to load hours agreement.' })
  } finally {
    loading.value = false
  }
}

watch(
  () => props.applicationId,
  () => {
    void reload()
  },
  { immediate: true },
)

async function propose(source: 'school' | 'host' | 'custom') {
  if (!canMutate.value) return
  const hours =
    source === 'school'
      ? schoolHoursReady.value
      : source === 'host'
        ? hostHoursReady.value
        : positiveHours(customHours.value)
  if (hours === null) {
    error(
      source === 'school'
        ? 'School course hours are not set on this application — Use school hours cannot run. Set the course requirement or propose custom hours.'
        : source === 'host'
          ? 'Host hours are not set on this posting — Use host hours cannot run.'
          : 'Enter a valid hours value.'
    )
    return
  }
  if (!confirm(`Propose ${hours} hours for this placement?`)) return

  saving.value = true
  try {
    const res = await proposeHoursAgreement(props.applicationId, {
      hours,
      source,
      note: note.value || undefined,
      reason: reason.value || undefined,
    })
    agreement.value = res.data.agreement
    success('Hours proposal sent.')
    reason.value = ''
  } catch (caught) {
    error(caught, { fallback: 'Unable to propose hours.' })
  } finally {
    saving.value = false
  }
}

async function confirm() {
  if (!canMutate.value) return
  if (!confirm('Confirm the current hours proposal?')) return
  saving.value = true
  try {
    const res = await confirmHoursAgreement(props.applicationId, note.value || undefined)
    agreement.value = res.data.agreement
    success(agreement.value?.status === 'agreed' ? 'Hours agreed.' : 'Your confirmation was recorded.')
  } catch (caught) {
    error(caught, { fallback: 'Unable to confirm.' })
  } finally {
    saving.value = false
  }
}

async function decline() {
  if (!canMutate.value) return
  if (!confirm('Decline this hours agreement?')) return
  saving.value = true
  try {
    const res = await declineHoursAgreement(props.applicationId, note.value || undefined)
    agreement.value = res.data.agreement
    success('Hours agreement declined.')
  } catch (caught) {
    error(caught, { fallback: 'Unable to decline.' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-3 rounded-2xl border border-border bg-card p-4 text-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p class="font-semibold text-foreground">Hours agreement</p>
      <Badge v-if="showMismatch" variant="warning">Hours differ from your school requirement</Badge>
      <Badge v-else-if="agreement?.status === 'agreed'" variant="success">Hours agreed</Badge>
    </div>

    <p class="text-muted-foreground">
      School:
      <span class="font-medium text-foreground">{{ schoolHoursReady ?? agreement?.schoolRequiredHours ?? schoolRequiredHours ?? '—' }}h</span>
      · Host:
      <span class="font-medium text-foreground">{{ hostHoursReady ?? agreement?.hostOfferedHours ?? hostRequiredHours ?? '—' }}h</span>
      <template v-if="agreement?.agreedHours">
        · Agreed:
        <span class="font-medium text-foreground">{{ agreement.agreedHours }}h</span>
      </template>
    </p>

    <p v-if="loading" class="text-muted-foreground">Loading agreement…</p>

    <template v-else-if="agreement">
      <p class="text-muted-foreground">
        Status:
        <span class="font-medium text-foreground">{{ statusLabel(agreement.status) }}</span>
        <span v-if="agreement.proposedHours"> · Proposed: {{ agreement.proposedHours }}h</span>
        <span v-if="agreement.schoolConfirmed"> · School confirmed</span>
        <span v-if="agreement.hostConfirmed"> · Host confirmed</span>
      </p>

      <div v-if="canMutate && agreement.status !== 'declined'" class="space-y-3 border-t border-border pt-3">
        <div class="flex flex-wrap gap-2">
          <Button
            size="sm"
            variant="outline"
            :disabled="saving || schoolHoursReady === null"
            :title="schoolHoursReady === null ? 'School course hours missing on this application' : undefined"
            @click="propose('school')"
          >
            Use school hours ({{ schoolHoursReady ?? '—' }})
          </Button>
          <Button
            size="sm"
            variant="outline"
            :disabled="saving || hostHoursReady === null"
            :title="hostHoursReady === null ? 'Host hours missing on this posting' : undefined"
            @click="propose('host')"
          >
            Use host hours ({{ hostHoursReady ?? '—' }})
          </Button>
        </div>
        <p v-if="schoolHoursReady === null" class="text-xs text-amber-700 dark:text-amber-300">
          School course hours are not set on this application, so Use school hours does nothing. Fix the course requirement or propose custom hours.
        </p>
        <div class="flex flex-wrap items-end gap-2">
          <div class="min-w-[120px] flex-1">
            <label class="mb-1 block text-xs text-muted-foreground">Custom hours</label>
            <Input v-model="customHours" type="number" min="1" max="10000" />
          </div>
          <Button size="sm" :disabled="saving" @click="propose('custom')">Propose custom</Button>
        </div>
        <div>
          <label class="mb-1 block text-xs text-muted-foreground">Note (optional)</label>
          <Input v-model="note" placeholder="Optional note" />
        </div>
        <div v-if="agreement.hasApprovedDtr && agreement.status === 'agreed'">
          <label class="mb-1 block text-xs text-muted-foreground">Reason required after approved DTR</label>
          <Input v-model="reason" placeholder="Why change hours after DTR approval?" />
        </div>
        <div class="flex flex-wrap gap-2">
          <Button
            size="sm"
            :disabled="saving || !agreement.proposedHours"
            @click="confirm"
          >
            Confirm proposal
          </Button>
          <Button size="sm" variant="outline" :disabled="saving" @click="decline">Decline</Button>
        </div>
      </div>

      <div v-else-if="mode === 'student'" class="text-muted-foreground">
        You can view this agreement. Only your school and the host can confirm or change it.
      </div>

      <div v-if="agreement.history?.length" class="space-y-2 border-t border-border pt-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">History</p>
        <ul class="space-y-1 text-xs text-muted-foreground">
          <li v-for="event in agreement.history" :key="event.id">
            <span class="font-medium text-foreground">{{ event.action }}</span>
            <span v-if="event.proposedHours"> · {{ event.proposedHours }}h</span>
            <span v-if="event.changedByName"> · {{ event.changedByName }}</span>
            <span v-if="event.note"> — {{ event.note }}</span>
          </li>
        </ul>
      </div>
    </template>

    <p v-else class="text-muted-foreground">No hours agreement for this application.</p>
  </div>
</template>

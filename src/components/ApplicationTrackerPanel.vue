<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { LoaderCircle } from 'lucide-vue-next'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { useToast } from '@/composables/useToast'
import ApplicationInterviewPanel from '@/components/ApplicationInterviewPanel.vue'
import {
  fetchApplicationTracker,
  submitTrackerAssessment,
  TRACKER_FLOW,
  transitionApplicationStage,
  type TrackerState,
} from '@/services/applicationTracker'
import { statusLabel } from '@/utils/statusLabels'
import { closePlacement } from '@/services/applications'
import { hasPermission } from '@/services/permissions'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  applicationId: string
  mode?: 'manage' | 'student' | 'school'
  allowSchedule?: boolean
}>()

const emit = defineEmits<{
  (e: 'updated'): void
}>()

const authStore = useAuthStore()
const { success, error } = useToast()

const tracker = ref<TrackerState | null>(null)
const loading = ref(false)
const submitting = ref(false)
const confirmOpen = ref(false)
const confirmAction = ref<'accepted' | 'rejected' | null>(null)
const rejectReason = ref('')
const rollbackReason = ref('')
const rollbackTarget = ref('')
const assessmentForm = ref({
  technical: '3',
  communication: '3',
  attitude: '3',
  comments: '',
})

const role = computed(() => authStore.user?.role)
const isStudent = computed(() => props.mode === 'student' || role.value === 'student')
const canManageStages = computed(() => {
  if (isStudent.value) return false
  return hasPermission('org.approve_applications') || hasPermission('org.manage_applications')
})
const canAssess = computed(() => {
  if (isStudent.value) return false
  return hasPermission('org.manage_assessments') || hasPermission('org.approve_applications')
})

const stage = computed(() => tracker.value?.trackerStage || 'applied')
const averageScore = computed(() => {
  const t = Number(assessmentForm.value.technical)
  const c = Number(assessmentForm.value.communication)
  const a = Number(assessmentForm.value.attitude)
  if ([t, c, a].some((n) => Number.isNaN(n))) return null
  return Math.round(((t + c + a) / 3) * 100) / 100
})

const flowSteps = computed(() => {
  const current = stage.value
  if (current === 'rejected' || current === 'school_rejected') {
    return [...TRACKER_FLOW.slice(0, -1), current as typeof TRACKER_FLOW[number]]
  }
  return TRACKER_FLOW
})

function stepTone(step: string): 'complete' | 'active' | 'pending' | 'error' {
  const current = stage.value
  if (current === 'rejected' || current === 'school_rejected') {
    if (step === current) return 'error'
  }
  const order = TRACKER_FLOW
  const curIdx = order.indexOf(current as typeof TRACKER_FLOW[number])
  const stepIdx = order.indexOf(step as typeof TRACKER_FLOW[number])
  if (curIdx < 0 || stepIdx < 0) {
    return step === current ? 'active' : 'pending'
  }
  if (stepIdx < curIdx) return 'complete'
  if (stepIdx === curIdx) return 'active'
  return 'pending'
}

async function load() {
  if (!props.applicationId) return
  loading.value = true
  try {
    tracker.value = await fetchApplicationTracker(props.applicationId)
  } catch (caught) {
    error(caught, { fallback: 'Unable to load interview tracker.' })
  } finally {
    loading.value = false
  }
}

async function runTransition(toStage: string, options?: { reason?: string; rollback?: boolean }) {
  submitting.value = true
  try {
    tracker.value = await transitionApplicationStage(props.applicationId, toStage, options)
    success(options?.rollback ? 'Stage rolled back.' : `Moved to ${statusLabel(toStage)}.`)
    emit('updated')
  } catch (caught) {
    error(caught, { fallback: 'Unable to update stage.' })
  } finally {
    submitting.value = false
  }
}

async function runClosePlacement(reason: 'cancelled' | 'ended' | 'completed') {
  submitting.value = true
  try {
    await closePlacement(props.applicationId, reason)
    await load()
    success(reason === 'cancelled' ? 'Placement cancelled.' : 'Placement ended.')
    emit('updated')
  } catch (caught) {
    error(caught, { fallback: 'Unable to close this placement.' })
  } finally {
    submitting.value = false
  }
}

function openDecision(action: 'accepted' | 'rejected') {
  confirmAction.value = action
  rejectReason.value = ''
  confirmOpen.value = true
}

async function confirmDecision() {
  if (!confirmAction.value) return
  if (confirmAction.value === 'rejected' && !rejectReason.value.trim()) {
    error('A rejection reason is required.')
    return
  }
  await runTransition(confirmAction.value, {
    reason: confirmAction.value === 'rejected' ? rejectReason.value.trim() : undefined,
  })
  confirmOpen.value = false
  confirmAction.value = null
}

async function submitAssessment() {
  const technical = Number(assessmentForm.value.technical)
  const communication = Number(assessmentForm.value.communication)
  const attitude = Number(assessmentForm.value.attitude)
  if ([technical, communication, attitude].some((n) => Number.isNaN(n) || n < 1 || n > 5)) {
    error('Scores must be between 1 and 5.')
    return
  }
  submitting.value = true
  try {
    const result = await submitTrackerAssessment(props.applicationId, {
      technical,
      communication,
      attitude,
      comments: assessmentForm.value.comments || undefined,
    })
    if (result.tracker) tracker.value = result.tracker
    else await load()
    success('Assessment submitted and locked.')
    emit('updated')
  } catch (caught) {
    error(caught, { fallback: 'Unable to submit assessment.' })
  } finally {
    submitting.value = false
  }
}

async function rollback() {
  if (!rollbackTarget.value || !rollbackReason.value.trim()) {
    error('Choose a prior stage and provide a reason.')
    return
  }
  await runTransition(rollbackTarget.value, {
    reason: rollbackReason.value.trim(),
    rollback: true,
  })
  rollbackReason.value = ''
  rollbackTarget.value = ''
}

const primaryAction = computed(() => {
  if (!tracker.value || isStudent.value || !canManageStages.value) return null
  const next = tracker.value.allowedNext.filter((s) => s !== 'interview_scheduled')
  const current = stage.value

  if (role.value === 'school' || props.mode === 'school') {
    if (current === 'applied' && next.includes('endorsed')) {
      return { label: 'Endorse', stage: 'endorsed' as const }
    }
    if (current === 'applied' && next.includes('school_rejected')) {
      return null
    }
  }

  if (current === 'interview_scheduled' && next.includes('interviewed')) {
    return { label: 'Mark interviewed', stage: 'interviewed' as const }
  }

  if (current === 'assessed') {
    return null
  }

  return null
})

const showSchoolEndorse = computed(() => {
  if (isStudent.value || !canManageStages.value) return false
  if (!(role.value === 'school' || props.mode === 'school' || role.value === 'admin')) return false
  return stage.value === 'applied' && (tracker.value?.allowedNext || []).includes('endorsed')
})

const showSchoolReject = computed(() => {
  if (isStudent.value || !canManageStages.value) return false
  if (!(role.value === 'school' || props.mode === 'school' || role.value === 'admin')) return false
  return stage.value === 'applied' && (tracker.value?.allowedNext || []).includes('school_rejected')
})

const showMarkInterviewed = computed(() => {
  if (isStudent.value || !canManageStages.value) return false
  return stage.value === 'interview_scheduled' && (tracker.value?.allowedNext || []).includes('interviewed')
})

const showAssessmentForm = computed(() => {
  return !isStudent.value && canAssess.value && stage.value === 'interviewed' && !tracker.value?.assessmentLocked
})

const showDecision = computed(() => {
  if (isStudent.value || !canManageStages.value) return false
  return stage.value === 'assessed'
})

const showClosePlacement = computed(() => {
  if (isStudent.value || !canManageStages.value) return false
  return stage.value === 'accepted'
})

const showInterviewSection = computed(() => {
  return stage.value === 'endorsed' || stage.value === 'interview_scheduled' || stage.value === 'interviewed'
})

const completedStepLabels = computed(() =>
  flowSteps.value
    .filter((step) => stepTone(step) === 'complete')
    .map((step) => statusLabel(step)),
)

const stageSummary = computed(() => {
  const current = statusLabel(stage.value)
  if (stage.value === 'applied' && isStudent.value) {
    return 'Waiting for your school to endorse this application.'
  }
  if (stage.value === 'applied' && (role.value === 'company' || role.value === 'admin')) {
    return 'Waiting for the school to endorse this applicant.'
  }
  if (isStudent.value && ['interviewed', 'assessed', 'accepted', 'rejected', 'school_rejected'].includes(stage.value)) {
    return `Current stage: ${current}. Earlier steps are finished.`
  }
  if (['accepted', 'rejected', 'school_rejected'].includes(stage.value)) {
    return `Current stage: ${current}.`
  }
  return ''
})

const hostNextHint = computed(() => {
  if (isStudent.value || !canManageStages.value) return ''
  if (showDecision.value) return 'Host decision is ready — Accept or Reject this applicant.'
  if (showAssessmentForm.value) return 'Submit the assessment below. Accept/Reject appear after it is saved.'
  if (showMarkInterviewed.value) return 'Mark the interview complete, then submit an assessment to unlock Accept/Reject.'
  if (stage.value === 'endorsed' || stage.value === 'interview_scheduled') {
    return 'Schedule or complete the interview first. Accept/Reject unlock after assessment.'
  }
  if (stage.value === 'interviewed' && tracker.value?.assessmentLocked) {
    return 'Assessment is locked. Unlock it with a reason, or contact an admin, before Accept/Reject.'
  }
  return ''
})

const showRollback = computed(() => {
  if (isStudent.value || !canManageStages.value) return false
  return (tracker.value?.allowedRollback || []).length > 0
})

onMounted(load)
watch(() => props.applicationId, load)
</script>

<template>
  <div class="space-y-4 rounded-2xl border border-border/70 bg-card p-4 text-card-foreground">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h4 class="text-sm font-semibold text-foreground">Interview tracker</h4>
        <p class="text-xs text-muted-foreground">
          One active stage at a time. The system controls valid next steps.
        </p>
      </div>
      <Button size="sm" variant="outline" :disabled="loading" @click="load">
        <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin" />
        Refresh
      </Button>
    </div>

    <ol class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-1">
      <li
        v-for="(step, index) in flowSteps"
        :key="step"
        class="flex items-center gap-2"
      >
        <div
          class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-[11px] font-semibold"
          :class="{
            'border-primary bg-primary text-primary-foreground': stepTone(step) === 'active',
            'border-border bg-muted text-muted-foreground': stepTone(step) === 'pending',
            'border-emerald-500/40 bg-emerald-500/15 text-emerald-800 dark:text-emerald-200': stepTone(step) === 'complete',
            'border-destructive/40 bg-destructive/10 text-destructive': stepTone(step) === 'error',
          }"
        >
          {{ index + 1 }}
        </div>
        <span
          class="text-xs font-medium"
          :class="stepTone(step) === 'active' || stepTone(step) === 'error' ? 'text-foreground' : 'text-muted-foreground'"
        >
          {{ statusLabel(step) }}
        </span>
        <span
          v-if="index < flowSteps.length - 1"
          class="hidden text-muted-foreground sm:inline"
          aria-hidden="true"
        >→</span>
      </li>
    </ol>

    <div class="flex flex-wrap items-center gap-2">
      <Badge variant="outline">Current: {{ statusLabel(stage) }}</Badge>
    </div>

    <p
      v-if="hostNextHint"
      class="rounded-xl border border-border/60 bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
    >
      {{ hostNextHint }}
    </p>

    <p
      v-if="completedStepLabels.length"
      class="rounded-xl border border-border/60 bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
    >
      Finished: {{ completedStepLabels.join(' · ') }}
    </p>

    <p
      v-if="stageSummary && !showInterviewSection && !showSchoolEndorse && !showAssessmentForm && !showDecision"
      class="text-sm text-muted-foreground"
    >
      {{ stageSummary }}
    </p>

    <ApplicationInterviewPanel
      v-if="showInterviewSection"
      :application-id="applicationId"
      :mode="isStudent ? 'student' : 'manage'"
      :allow-schedule="allowSchedule"
      @updated="load(); emit('updated')"
    />

    <div v-if="!isStudent" class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
      <Button
        v-if="showSchoolEndorse"
        :disabled="submitting"
        @click="runTransition('endorsed')"
      >
        <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
        Endorse
      </Button>
      <Button
        v-if="showSchoolReject"
        variant="outline"
        :disabled="submitting"
        @click="runTransition('school_rejected', { reason: 'Not endorsed by school' })"
      >
        Do not endorse
      </Button>
      <Button
        v-if="showMarkInterviewed"
        :disabled="submitting"
        @click="runTransition('interviewed')"
      >
        <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
        Mark interviewed
      </Button>
      <template v-if="showDecision">
        <Button
          :disabled="submitting"
          @click="openDecision('accepted')"
        >
          Accept
        </Button>
        <Button
          variant="outline"
          :disabled="submitting"
          @click="openDecision('rejected')"
        >
          Reject
        </Button>
      </template>
      <template v-if="showClosePlacement">
        <Button
          variant="outline"
          :disabled="submitting"
          @click="runClosePlacement('cancelled')"
        >
          Cancel placement
        </Button>
        <Button
          variant="outline"
          :disabled="submitting"
          @click="runClosePlacement('ended')"
        >
          End placement
        </Button>
      </template>
    </div>

    <div
      v-if="showAssessmentForm"
      class="space-y-3 rounded-xl border border-border/60 bg-muted/40 p-3"
    >
      <div>
        <p class="text-sm font-medium text-foreground">Assessment (scale 1–5)</p>
        <p class="text-xs text-muted-foreground">
          Technical, Communication, and Attitude. Overall average is calculated automatically.
        </p>
      </div>
      <div class="grid gap-3 sm:grid-cols-3">
        <label class="space-y-1 text-sm">
          <span class="font-medium text-foreground">Technical</span>
          <Input v-model="assessmentForm.technical" type="number" min="1" max="5" step="1" />
        </label>
        <label class="space-y-1 text-sm">
          <span class="font-medium text-foreground">Communication</span>
          <Input v-model="assessmentForm.communication" type="number" min="1" max="5" step="1" />
        </label>
        <label class="space-y-1 text-sm">
          <span class="font-medium text-foreground">Attitude</span>
          <Input v-model="assessmentForm.attitude" type="number" min="1" max="5" step="1" />
        </label>
      </div>
      <label class="block space-y-1 text-sm">
        <span class="font-medium text-foreground">Comments</span>
        <Input v-model="assessmentForm.comments" placeholder="Optional comments" />
      </label>
      <p class="text-sm text-muted-foreground">
        Overall average:
        <span class="font-semibold text-foreground">{{ averageScore ?? '—' }}</span>
        / 5
      </p>
      <Button :disabled="submitting" @click="submitAssessment">
        <LoaderCircle v-if="submitting" class="h-4 w-4 animate-spin" />
        Submit assessment
      </Button>
    </div>

    <p
      v-else-if="stage === 'interviewed' && tracker?.assessmentLocked"
      class="text-sm text-muted-foreground"
    >
      Assessment is locked. Contact an authorized reviewer to unlock with a logged reason.
    </p>

    <details v-if="showRollback || tracker?.history?.length" class="border-t border-border/60 pt-3">
      <summary class="cursor-pointer text-xs font-medium text-muted-foreground">Earlier steps</summary>
      <div v-if="showRollback" class="mt-3 space-y-2">
        <p class="text-xs font-medium text-foreground">Move back a stage (reason required)</p>
        <div class="flex flex-col gap-2 sm:flex-row">
          <select
            v-model="rollbackTarget"
            class="flex h-11 w-full rounded-md border border-input bg-background px-3 text-[16px] text-foreground sm:max-w-[12rem] md:text-sm"
          >
            <option value="">Select stage</option>
            <option
              v-for="s in tracker?.allowedRollback || []"
              :key="s"
              :value="s"
            >
              {{ statusLabel(s) }}
            </option>
          </select>
          <Input v-model="rollbackReason" placeholder="Reason" class="flex-1" />
          <Button variant="outline" :disabled="submitting" @click="rollback">Roll back</Button>
        </div>
      </div>

      <ul v-if="tracker?.history?.length" class="mt-3 max-h-48 space-y-2 overflow-y-auto">
        <li
          v-for="event in tracker.history"
          :key="event.id"
          class="rounded-lg bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
        >
          <span class="font-medium text-foreground">
            {{ statusLabel(event.fromStage || '') }}
            →
            {{ statusLabel(event.toStage) }}
          </span>
          <span v-if="event.isRollback" class="ml-1 text-destructive">(rollback)</span>
          <div class="mt-0.5">
            {{ event.changedByName || 'Unknown' }}
            <template v-if="event.changedByRole"> · {{ event.changedByRole }}</template>
            <template v-if="event.organizationName"> · {{ event.organizationName }}</template>
            <template v-if="event.createdAt"> · {{ new Date(event.createdAt).toLocaleString() }}</template>
          </div>
          <p v-if="event.reason" class="mt-0.5 italic">{{ event.reason }}</p>
        </li>
      </ul>
    </details>

    <div
      v-if="confirmOpen"
      class="fixed inset-0 z-50 flex items-end justify-center bg-background/70 p-4 sm:items-center"
      role="dialog"
      aria-modal="true"
    >
      <div class="w-full max-w-md space-y-4 rounded-2xl border border-border bg-card p-4 text-card-foreground shadow-lg">
        <h5 class="text-sm font-semibold text-foreground">
          {{ confirmAction === 'accepted' ? 'Confirm acceptance' : 'Confirm rejection' }}
        </h5>
        <p class="text-sm text-muted-foreground">
          {{
            confirmAction === 'accepted'
              ? 'Accept this applicant after assessment?'
              : 'Reject this applicant? A reason is required.'
          }}
        </p>
        <Input
          v-if="confirmAction === 'rejected'"
          v-model="rejectReason"
          placeholder="Rejection reason"
        />
        <div class="flex justify-end gap-2">
          <Button variant="outline" @click="confirmOpen = false">Cancel</Button>
          <Button :disabled="submitting" @click="confirmDecision">
            Confirm
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

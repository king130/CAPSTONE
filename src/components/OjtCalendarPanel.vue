<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, LoaderCircle } from 'lucide-vue-next'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { useToast } from '@/composables/useToast'
import {
  clearOjtAbsence,
  fetchOjtCalendar,
  listOjtAbsences,
  markOjtAbsence,
  updateOjtStartDate,
  type OjtAttendanceEvent,
  type OjtCalendarDay,
  type OjtCalendarState,
} from '@/services/ojtCalendar'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  applicationId: string
  mode?: 'student' | 'host' | 'school'
}>()

const authStore = useAuthStore()
const { success, error } = useToast()

const state = ref<OjtCalendarState | null>(null)
const loading = ref(false)
const saving = ref(false)
const absenceSaving = ref(false)
const month = ref('')
const startDateDraft = ref('')
const absences = ref<OjtAttendanceEvent[]>([])
const absenceDraft = ref({
  date: '',
  type: 'absent_excused' as 'absent_excused' | 'absent_unexcused',
  reason: '',
})

const canEditStart = computed(() => {
  if (props.mode === 'student') return false
  if (props.mode === 'school') return false
  const role = authStore.user?.role
  return role === 'company' || role === 'municipal_hall' || role === 'admin' || props.mode === 'host'
})

const canManageAbsences = computed(() => {
  if (props.mode === 'student' || props.mode === 'school') return false
  const role = authStore.user?.role
  return role === 'company' || role === 'municipal_hall' || role === 'admin' || props.mode === 'host'
})

function shiftMonth(delta: number) {
  const [y, m] = (month.value || state.value?.month || '').split('-').map(Number)
  if (!y || !m) return
  const d = new Date(y, m - 1 + delta, 1)
  month.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
  void load()
}

function dayClass(day: OjtCalendarDay): string {
  const projected = state.value?.summary.projectedEndDate === day.date
  if (projected) return 'border-primary bg-primary/15 text-foreground'
  switch (day.kind) {
    case 'completed':
      return 'border-emerald-500/40 bg-emerald-500/15 text-foreground'
    case 'working':
      return 'border-border bg-accent/60 text-foreground'
    case 'holiday':
      return 'border-amber-500/40 bg-amber-500/15 text-foreground'
    case 'absent_excused':
      return 'border-sky-500/40 bg-sky-500/15 text-foreground'
    case 'absent_unexcused':
      return 'border-rose-500/40 bg-rose-500/15 text-foreground'
    case 'before_start':
      return 'border-border bg-muted/40 text-muted-foreground'
    default:
      return 'border-transparent bg-transparent text-muted-foreground'
  }
}

function absenceForDate(date: string): OjtAttendanceEvent | undefined {
  return absences.value.find((row) => row.date === date)
}

async function load() {
  if (!props.applicationId) return
  loading.value = true
  try {
    const [calendar, absenceRows] = await Promise.all([
      fetchOjtCalendar(props.applicationId, month.value || undefined),
      listOjtAbsences(props.applicationId),
    ])
    state.value = calendar
    absences.value = absenceRows
    month.value = calendar.month
    startDateDraft.value = calendar.summary.ojtStartDate || ''
  } catch (caught) {
    error(caught, { fallback: 'Unable to load OJT calendar.' })
  } finally {
    loading.value = false
  }
}

async function saveStartDate() {
  if (!startDateDraft.value) {
    error('Choose a start date.')
    return
  }
  saving.value = true
  try {
    state.value = await updateOjtStartDate(props.applicationId, startDateDraft.value)
    success('OJT start date updated.')
  } catch (caught) {
    error(caught, { fallback: 'Unable to update start date.' })
  } finally {
    saving.value = false
  }
}

async function submitAbsence() {
  if (!absenceDraft.value.date || !absenceDraft.value.reason.trim()) {
    error('Date and reason are required to mark an absence.')
    return
  }
  absenceSaving.value = true
  try {
    await markOjtAbsence(props.applicationId, {
      date: absenceDraft.value.date,
      type: absenceDraft.value.type,
      reason: absenceDraft.value.reason.trim(),
    })
    absenceDraft.value = { date: '', type: 'absent_excused', reason: '' }
    success('Absence marked.')
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to mark absence.' })
  } finally {
    absenceSaving.value = false
  }
}

async function removeAbsence(event: OjtAttendanceEvent) {
  absenceSaving.value = true
  try {
    await clearOjtAbsence(props.applicationId, event.id)
    success('Absence cleared.')
    await load()
  } catch (caught) {
    error(caught, { fallback: 'Unable to clear absence.' })
  } finally {
    absenceSaving.value = false
  }
}

const weeks = computed(() => {
  const days = state.value?.days || []
  const firstDay = days[0]
  if (!firstDay) return [] as OjtCalendarDay[][]
  const first = new Date(firstDay.date + 'T00:00:00')
  const pad = (first.getDay() + 6) % 7 // Monday-first
  const cells: (OjtCalendarDay | null)[] = [...Array(pad).fill(null), ...days]
  while (cells.length % 7 !== 0) cells.push(null)
  const rows: (OjtCalendarDay | null)[][] = []
  for (let i = 0; i < cells.length; i += 7) rows.push(cells.slice(i, i + 7))
  return rows
})

onMounted(load)
watch(() => props.applicationId, () => {
  month.value = ''
  void load()
})
</script>

<template>
  <div class="space-y-4 rounded-2xl border border-border/70 bg-card p-4 text-card-foreground">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h4 class="text-sm font-semibold text-foreground">OJT calendar</h4>
        <p class="text-xs text-muted-foreground">
          Projected from required hours, active schedule, and approved DTR hours (Asia/Manila).
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button size="sm" variant="outline" :disabled="loading" @click="shiftMonth(-1)">
          <ChevronLeft class="h-4 w-4" />
        </Button>
        <span class="min-w-[5.5rem] text-center text-sm font-medium text-foreground">{{ month }}</span>
        <Button size="sm" variant="outline" :disabled="loading" @click="shiftMonth(1)">
          <ChevronRight class="h-4 w-4" />
        </Button>
        <Button size="sm" variant="outline" :disabled="loading" @click="load">
          <LoaderCircle v-if="loading" class="h-3.5 w-3.5 animate-spin" />
          Refresh
        </Button>
      </div>
    </div>

    <div v-if="state" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-xl bg-muted/50 p-3">
        <p class="text-xs text-muted-foreground">Required</p>
        <p class="text-lg font-semibold text-foreground">{{ state.summary.requiredHours ?? 'Not set' }}</p>
      </div>
      <div class="rounded-xl bg-muted/50 p-3">
        <p class="text-xs text-muted-foreground">Approved (DTR)</p>
        <p class="text-lg font-semibold text-foreground">{{ state.summary.approvedHours }}</p>
      </div>
      <div class="rounded-xl bg-muted/50 p-3">
        <p class="text-xs text-muted-foreground">Remaining</p>
        <p class="text-lg font-semibold text-foreground">{{ state.summary.remainingHours ?? '—' }}</p>
      </div>
      <div class="rounded-xl bg-muted/50 p-3">
        <p class="text-xs text-muted-foreground">Projected end</p>
        <p class="text-lg font-semibold text-foreground">{{ state.summary.projectedEndDate || '—' }}</p>
      </div>
    </div>

    <div v-if="state?.summary.issues?.length" class="flex flex-wrap gap-2">
      <Badge v-for="issue in state.summary.issues" :key="issue" variant="outline">{{ issue }}</Badge>
    </div>

    <div
      v-if="canEditStart && state?.summary.ojtStartEditable"
      class="flex flex-col gap-2 rounded-xl border border-border/60 bg-muted/30 p-3 sm:flex-row sm:items-end"
    >
      <label class="flex-1 space-y-1 text-sm">
        <span class="font-medium text-foreground">OJT start date</span>
        <Input v-model="startDateDraft" type="date" />
      </label>
      <Button :disabled="saving" @click="saveStartDate">
        <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" />
        Save start date
      </Button>
    </div>
    <p v-else-if="state?.summary.ojtStartDate" class="text-xs text-muted-foreground">
      Start date: {{ state.summary.ojtStartDate }}
      <span v-if="!state.summary.ojtStartEditable"> (locked after first approved DTR)</span>
    </p>

    <div
      v-if="canManageAbsences"
      class="space-y-3 rounded-xl border border-border/60 bg-muted/30 p-3"
    >
      <div>
        <p class="text-sm font-medium text-foreground">Mark absence</p>
        <p class="text-xs text-muted-foreground">
          Excused absences extend the projected end date. Students cannot log that day until cleared.
        </p>
      </div>
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <label class="space-y-1 text-sm">
          <span class="text-muted-foreground">Date</span>
          <Input v-model="absenceDraft.date" type="date" />
        </label>
        <label class="space-y-1 text-sm">
          <span class="text-muted-foreground">Type</span>
          <select
            v-model="absenceDraft.type"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
          >
            <option value="absent_excused">Excused</option>
            <option value="absent_unexcused">Unexcused</option>
          </select>
        </label>
        <label class="space-y-1 text-sm sm:col-span-2">
          <span class="text-muted-foreground">Reason</span>
          <Input v-model="absenceDraft.reason" placeholder="Reason for absence" />
        </label>
      </div>
      <Button size="sm" :disabled="absenceSaving" @click="submitAbsence">
        <LoaderCircle v-if="absenceSaving" class="h-4 w-4 animate-spin" />
        Mark absence
      </Button>
      <div v-if="absences.length" class="space-y-2">
        <p class="text-xs font-medium text-muted-foreground">Recorded absences</p>
        <div
          v-for="row in absences"
          :key="row.id"
          class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/50 bg-background/70 px-3 py-2 text-sm"
        >
          <div>
            <span class="font-medium">{{ row.date }}</span>
            <span class="mx-2 text-muted-foreground">·</span>
            <span>{{ row.type === 'absent_excused' ? 'Excused' : 'Unexcused' }}</span>
            <p class="text-xs text-muted-foreground">{{ row.reason }}</p>
          </div>
          <Button size="sm" variant="outline" :disabled="absenceSaving" @click="removeAbsence(row)">
            Clear
          </Button>
        </div>
      </div>
    </div>

    <div class="overflow-x-auto">
      <div class="grid min-w-[280px] grid-cols-7 gap-1 text-center text-[11px] font-medium text-muted-foreground">
        <span v-for="label in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="label">{{ label }}</span>
      </div>
      <div class="mt-1 grid min-w-[280px] grid-cols-7 gap-1">
        <template v-for="(row, ri) in weeks" :key="ri">
          <div
            v-for="(day, di) in row"
            :key="`${ri}-${di}`"
            class="aspect-square rounded-lg border p-1 text-left"
            :class="day ? dayClass(day) : 'border-transparent'"
            :title="day?.absenceReason || day?.holidayName || undefined"
          >
            <template v-if="day">
              <p class="text-xs font-semibold">{{ Number(day.date.slice(8)) }}</p>
              <p v-if="day.scheduledHours" class="text-[10px] opacity-80">{{ day.scheduledHours }}h</p>
              <p v-if="day.holidayName" class="truncate text-[9px] leading-tight opacity-80">{{ day.holidayName }}</p>
              <p
                v-if="day.approvedMakeupHours"
                class="truncate text-[9px] font-medium leading-tight text-violet-700 dark:text-violet-300"
              >
                Make-up {{ day.approvedMakeupHours }}h
              </p>
              <p
                v-else-if="absenceForDate(day.date)"
                class="truncate text-[9px] leading-tight opacity-80"
              >
                Absent
              </p>
            </template>
          </div>
        </template>
      </div>
    </div>

    <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-border bg-accent/60" /> Working</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-emerald-500/40 bg-emerald-500/15" /> Completed</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-amber-500/40 bg-amber-500/15" /> Holiday</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-sky-500/40 bg-sky-500/15" /> Excused absent</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-rose-500/40 bg-rose-500/15" /> Unexcused absent</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-primary bg-primary/15" /> Projected end</span>
    </div>
    <p class="text-xs text-muted-foreground">
      Daily credit = slot length minus {{ state?.summary.dailyBreakHours ?? 1 }}h unpaid break when the slot is longer than
      {{ state?.summary.breakAppliesAfterHours ?? 5 }}h (e.g. 8:00–17:00 → 8h).
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, LoaderCircle } from 'lucide-vue-next'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { useToast } from '@/composables/useToast'
import {
  fetchOjtCalendar,
  updateOjtStartDate,
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
const month = ref('')
const startDateDraft = ref('')

const canEditStart = computed(() => {
  if (props.mode === 'student') return false
  if (props.mode === 'school') return false
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
    case 'before_start':
      return 'border-border bg-muted/40 text-muted-foreground'
    default:
      return 'border-transparent bg-transparent text-muted-foreground'
  }
}

async function load() {
  if (!props.applicationId) return
  loading.value = true
  try {
    state.value = await fetchOjtCalendar(props.applicationId, month.value || undefined)
    month.value = state.value.month
    startDateDraft.value = state.value.summary.ojtStartDate || ''
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

const weeks = computed(() => {
  const days = state.value?.days || []
  if (!days.length) return [] as OjtCalendarDay[][]
  const first = new Date(days[0].date + 'T00:00:00')
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
          >
            <template v-if="day">
              <p class="text-xs font-semibold">{{ Number(day.date.slice(8)) }}</p>
              <p v-if="day.scheduledHours" class="text-[10px] opacity-80">{{ day.scheduledHours }}h</p>
              <p v-if="day.holidayName" class="truncate text-[9px] leading-tight opacity-80">{{ day.holidayName }}</p>
            </template>
          </div>
        </template>
      </div>
    </div>

    <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-border bg-accent/60" /> Working</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-emerald-500/40 bg-emerald-500/15" /> Completed</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-amber-500/40 bg-amber-500/15" /> Holiday</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded border border-primary bg-primary/15" /> Projected end</span>
    </div>
    <p class="text-xs text-muted-foreground">
      Daily credit = slot length minus {{ state?.summary.dailyBreakHours ?? 1 }}h unpaid break when the slot is longer than
      {{ state?.summary.breakAppliesAfterHours ?? 5 }}h (e.g. 8:00–17:00 → 8h).
    </p>
  </div>
</template>

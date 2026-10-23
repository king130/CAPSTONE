<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import Button from '@/components/ui/button/Button.vue'
import {
  buildTimeOptions,
  DAY_OPTIONS,
  TIME_PRESETS,
  type ScheduleDay,
  type ScheduleSlot,
  validateSlots,
} from '@/utils/scheduleSlots'

const props = withDefaults(
  defineProps<{
    modelValue: ScheduleSlot[]
    disabled?: boolean
    maxDailyHours?: number
  }>(),
  { disabled: false, maxDailyHours: 12 },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: ScheduleSlot[]): void
}>()

const applySameTime = ref(true)
const sharedStart = ref('08:00')
const sharedEnd = ref('17:00')
const timeOptions = buildTimeOptions(30)
const error = ref<string | null>(null)

const selectedDays = computed(() => new Set(props.modelValue.map((s) => s.day)))

function slotFor(day: ScheduleDay): ScheduleSlot | undefined {
  return props.modelValue.find((s) => s.day === day)
}

function toggleDay(day: ScheduleDay) {
  if (props.disabled) return
  const existing = [...props.modelValue]
  const idx = existing.findIndex((s) => s.day === day)
  if (idx >= 0) {
    existing.splice(idx, 1)
  } else {
    existing.push({
      day,
      start: applySameTime.value ? sharedStart.value : '08:00',
      end: applySameTime.value ? sharedEnd.value : '17:00',
    })
  }
  push(existing)
}

function setDayTime(day: ScheduleDay, field: 'start' | 'end', value: string) {
  if (props.disabled) return
  const existing = props.modelValue.map((s) => ({ ...s }))
  const slot = existing.find((s) => s.day === day)
  if (!slot) return
  slot[field] = value
  push(existing)
}

function push(slots: ScheduleSlot[]) {
  const ordered = DAY_OPTIONS.map((d) => slots.find((s) => s.day === d.value)).filter(Boolean) as ScheduleSlot[]
  error.value = validateSlots(ordered, props.maxDailyHours)
  emit('update:modelValue', ordered)
}

function applySharedToAll() {
  if (props.disabled) return
  const next = props.modelValue.map((s) => ({
    ...s,
    start: sharedStart.value,
    end: sharedEnd.value,
  }))
  push(next)
}

function applyPreset(start: string, end: string) {
  if (props.disabled) return
  sharedStart.value = start
  sharedEnd.value = end
  if (!props.modelValue.length) {
    push(
      (['mon', 'tue', 'wed', 'thu', 'fri'] as ScheduleDay[]).map((day) => ({
        day,
        start,
        end,
      })),
    )
    return
  }
  push(props.modelValue.map((s) => ({ ...s, start, end })))
}

watch(
  () => props.modelValue,
  (slots) => {
    if (!slots.length) return
    const starts = new Set(slots.map((s) => s.start))
    const ends = new Set(slots.map((s) => s.end))
    if (starts.size === 1 && ends.size === 1) {
      sharedStart.value = slots[0].start
      sharedEnd.value = slots[0].end
      applySameTime.value = true
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap gap-2">
      <button
        v-for="day in DAY_OPTIONS"
        :key="day.value"
        type="button"
        :disabled="disabled"
        class="min-w-[3rem] rounded-xl border px-3 py-2 text-xs font-medium transition sm:text-sm"
        :class="
          selectedDays.has(day.value)
            ? 'border-primary bg-primary text-primary-foreground'
            : 'border-border bg-muted text-foreground hover:border-primary/40'
        "
        @click="toggleDay(day.value)"
      >
        {{ day.short }}
      </button>
    </div>

    <div class="flex flex-wrap gap-2">
      <Button
        v-for="preset in TIME_PRESETS"
        :key="preset.label"
        type="button"
        size="sm"
        variant="outline"
        :disabled="disabled"
        @click="applyPreset(preset.start, preset.end)"
      >
        {{ preset.label }}
      </Button>
    </div>

    <label class="inline-flex items-center gap-2 text-sm text-foreground">
      <input
        v-model="applySameTime"
        type="checkbox"
        class="h-4 w-4 rounded border-border"
        :disabled="disabled"
        @change="applySameTime && applySharedToAll()"
      />
      Apply the same time to all selected days
    </label>

    <div v-if="applySameTime" class="grid gap-3 sm:grid-cols-2">
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">Start</span>
        <select
          v-model="sharedStart"
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
          :disabled="disabled"
          @change="applySharedToAll()"
        >
          <option v-for="opt in timeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </label>
      <label class="space-y-1 text-sm">
        <span class="font-medium text-foreground">End</span>
        <select
          v-model="sharedEnd"
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
          :disabled="disabled"
          @change="applySharedToAll()"
        >
          <option v-for="opt in timeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </label>
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="day in DAY_OPTIONS.filter((d) => selectedDays.has(d.value))"
        :key="day.value"
        class="grid gap-2 rounded-xl border border-border/60 bg-muted/40 p-3 sm:grid-cols-[5rem_1fr_1fr]"
      >
        <p class="text-sm font-medium text-foreground self-center">{{ day.short }}</p>
        <select
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
          :value="slotFor(day.value)?.start"
          :disabled="disabled"
          @change="setDayTime(day.value, 'start', ($event.target as HTMLSelectElement).value)"
        >
          <option v-for="opt in timeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <select
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
          :value="slotFor(day.value)?.end"
          :disabled="disabled"
          @change="setDayTime(day.value, 'end', ($event.target as HTMLSelectElement).value)"
        >
          <option v-for="opt in timeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
    </div>

    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
    <p v-else class="text-xs text-muted-foreground">Times use 30-minute steps. Max {{ maxDailyHours }} hours per day.</p>
  </div>
</template>

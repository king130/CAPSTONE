<script setup lang="ts">
import { Check, ChevronDown } from 'lucide-vue-next'
import { computed, nextTick, ref, watch } from 'vue'

import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue'
import {
  jobStatusBadgeClass,
  jobStatusLabel,
  normalizeJobStatus,
  statusOptionsForJob,
  type JobStatusValue,
} from '@/utils/jobStatus'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    modelValue?: string | null
    disabled?: boolean
    ariaLabel?: string
    hasApplications?: boolean
  }>(),
  {
    modelValue: 'active',
    disabled: false,
    ariaLabel: 'Change status',
    hasApplications: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: JobStatusValue]
  select: [value: JobStatusValue]
}>()

const menuRef = ref<{ closeMenu?: () => void } | null>(null)
const activeIndex = ref(0)
const optionRefs = ref<HTMLButtonElement[]>([])

const current = computed(() => normalizeJobStatus(props.modelValue))
const currentLabel = computed(() => jobStatusLabel(current.value))
const options = computed(() =>
  statusOptionsForJob({ current: current.value, hasApplications: props.hasApplications }),
)

const badgeClass = computed(() =>
  cn(
    'inline-flex h-7 cursor-pointer items-center gap-1 rounded-full border px-2.5 text-xs font-semibold transition-colors',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
    'disabled:cursor-not-allowed disabled:opacity-50',
    jobStatusBadgeClass(current.value),
  ),
)

watch(
  [current, options],
  () => {
    activeIndex.value = Math.max(
      0,
      options.value.findIndex((option) => option.value === current.value),
    )
  },
  { immediate: true },
)

function setOptionRef(el: unknown, index: number) {
  if (el instanceof HTMLButtonElement) optionRefs.value[index] = el
}

function choose(value: JobStatusValue, close?: () => void) {
  if (props.disabled || value === current.value) {
    close?.()
    return
  }
  emit('update:modelValue', value)
  emit('select', value)
  close?.()
}

async function onMenuKeydown(event: KeyboardEvent) {
  if (!['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', ' '].includes(event.key)) return
  event.preventDefault()
  const last = options.value.length - 1
  if (event.key === 'ArrowDown') activeIndex.value = activeIndex.value >= last ? 0 : activeIndex.value + 1
  if (event.key === 'ArrowUp') activeIndex.value = activeIndex.value <= 0 ? last : activeIndex.value - 1
  if (event.key === 'Home') activeIndex.value = 0
  if (event.key === 'End') activeIndex.value = last
  if (event.key === 'Enter' || event.key === ' ') {
    const option = options.value[activeIndex.value]
    if (option) choose(option.value, () => menuRef.value?.closeMenu?.())
    return
  }
  await nextTick()
  optionRefs.value[activeIndex.value]?.focus()
}
</script>

<template>
  <DropdownMenu ref="menuRef" align="start" content-class="w-64">
    <template #trigger="{ open }">
      <button
        type="button"
        :class="badgeClass"
        :disabled="disabled"
        :aria-label="ariaLabel"
        :aria-expanded="open"
        aria-haspopup="menu"
      >
        <span>{{ currentLabel }}</span>
        <ChevronDown class="h-3.5 w-3.5 opacity-80" aria-hidden="true" />
      </button>
    </template>

    <template #default="{ close }">
      <div role="menu" aria-label="Job status" @keydown="onMenuKeydown">
        <p class="px-3 pb-1.5 pt-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
          Set status
        </p>
        <button
          v-for="(option, index) in options"
          :key="option.value"
          :ref="(el) => setOptionRef(el, index)"
          type="button"
          role="menuitemradio"
          :aria-checked="option.value === current"
          :tabindex="index === activeIndex ? 0 : -1"
          class="flex w-full flex-col gap-0.5 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
          :class="option.value === current ? 'bg-accent/70' : ''"
          @mouseenter="activeIndex = index"
          @click="choose(option.value, close)"
        >
          <span class="flex items-center justify-between gap-3 text-sm font-medium text-foreground">
            <span
              class="inline-flex h-5 items-center rounded-full border px-2 text-[11px] font-semibold"
              :class="jobStatusBadgeClass(option.value)"
            >
              {{ option.label }}
            </span>
            <Check v-if="option.value === current" class="h-4 w-4 text-foreground" aria-hidden="true" />
          </span>
          <span v-if="option.description" class="text-xs leading-snug text-muted-foreground">
            {{ option.description }}
          </span>
        </button>
      </div>
    </template>
  </DropdownMenu>
</template>

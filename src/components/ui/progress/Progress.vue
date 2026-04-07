<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

interface ProgressProps {
  value?: number
  indicatorClass?: string
  class?: string
}

const props = withDefaults(defineProps<ProgressProps>(), {
  value: 0,
  indicatorClass: '',
  class: '',
})

const safeValue = computed(() => Math.max(0, Math.min(100, props.value)))
const indicatorStyle = computed(() => ({ width: `${safeValue.value}%` }))
const rootClass = computed(() => cn('relative h-3 w-full overflow-hidden rounded-full bg-slate-200', props.class))
const barClass = computed(() => cn('h-full rounded-full bg-primary transition-all', props.indicatorClass))
</script>

<template>
  <div :class="rootClass">
    <div :class="barClass" :style="indicatorStyle" />
  </div>
</template>

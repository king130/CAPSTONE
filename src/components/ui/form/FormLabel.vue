<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    for?: string
    class?: string
  }>(),
  {
    for: undefined,
    class: '',
  },
)

const labelClass = computed(() => cn('text-sm font-medium text-slate-700', props.class))
</script>

<template>
  <label :for="for" :class="labelClass">
    <slot />
  </label>
</template>

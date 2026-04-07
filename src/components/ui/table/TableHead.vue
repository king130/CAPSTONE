<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const headClass = computed(() =>
  cn('h-12 px-4 text-left align-middle text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground', props.class),
)
</script>

<template>
  <th :class="headClass">
    <slot />
  </th>
</template>

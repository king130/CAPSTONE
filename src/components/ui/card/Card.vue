<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    class?: string
  }>(),
  {
    class: '',
  },
)

const cardClass = computed(() =>
  cn('rounded-xl border border-border bg-card text-card-foreground shadow-sm', props.class),
)
</script>

<template>
  <div :class="cardClass">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    variant?: 'default' | 'destructive'
    class?: string
  }>(),
  {
    variant: 'default',
    class: '',
  },
)

const alertClass = computed(() =>
  cn(
    'relative w-full rounded-lg border p-4 text-sm',
    props.variant === 'default' && 'border-border bg-background text-foreground',
    props.variant === 'destructive' &&
      'border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300',
    props.class,
  ),
)
</script>

<template>
  <div :class="alertClass" role="alert">
    <slot />
  </div>
</template>

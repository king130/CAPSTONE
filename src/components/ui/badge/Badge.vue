<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    variant?: 'default' | 'secondary' | 'outline' | 'success' | 'warning' | 'destructive'
    class?: string
  }>(),
  {
    variant: 'default',
    class: '',
  },
)

const badgeClass = computed(() =>
  cn(
    'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors',
    props.variant === 'default' && 'border-transparent bg-primary text-primary-foreground',
    props.variant === 'secondary' && 'border-transparent bg-secondary text-secondary-foreground',
    props.variant === 'outline' && 'border-border text-foreground',
    props.variant === 'success' && 'border-emerald-200 bg-emerald-50 text-emerald-700',
    props.variant === 'warning' && 'border-amber-200 bg-amber-50 text-amber-700',
    props.variant === 'destructive' && 'border-red-200 bg-red-50 text-red-700',
    props.class,
  ),
)
</script>

<template>
  <div :class="badgeClass">
    <slot />
  </div>
</template>

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

const itemClass = computed(() =>
  cn(
    'flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium outline-none transition-all',
    'text-slate-700 hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground',
    '[&>svg]:h-4 [&>svg]:w-4 [&>svg]:shrink-0 [&>svg]:text-slate-500',
    props.class,
  ),
)
</script>

<template>
  <button type="button" :class="itemClass">
    <slot />
  </button>
</template>

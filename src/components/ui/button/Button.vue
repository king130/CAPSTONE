<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset'
    variant?: 'default' | 'ghost' | 'outline' | 'destructive'
    size?: 'default' | 'sm' | 'icon'
    disabled?: boolean
    class?: string
  }>(),
  {
    type: 'button',
    variant: 'default',
    size: 'default',
    disabled: false,
    class: '',
  },
)

const buttonClass = computed(() =>
  cn(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 ring-offset-background',
    {
      'bg-primary text-primary-foreground hover:bg-primary/90': props.variant === 'default',
      'hover:bg-accent hover:text-accent-foreground': props.variant === 'ghost',
      'border border-border bg-background hover:bg-accent hover:text-accent-foreground':
        props.variant === 'outline',
      'bg-red-600 text-white hover:bg-red-700': props.variant === 'destructive',
      'h-10 px-4 py-2': props.size === 'default',
      'h-9 rounded-md px-3': props.size === 'sm',
      'h-10 w-10': props.size === 'icon',
    },
    props.class,
  ),
)
</script>

<template>
  <button :type="type" :disabled="disabled" :class="buttonClass">
    <slot />
  </button>
</template>

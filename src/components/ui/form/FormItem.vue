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

const itemClass = computed(() => cn('space-y-2', props.class))
</script>

<template>
  <div :class="itemClass">
    <slot />
  </div>
</template>

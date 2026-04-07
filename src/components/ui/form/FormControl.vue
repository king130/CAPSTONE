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

const controlClass = computed(() => cn(props.class))
</script>

<template>
  <div :class="controlClass">
    <slot />
  </div>
</template>

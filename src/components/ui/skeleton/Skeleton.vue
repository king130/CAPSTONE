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

const skeletonClass = computed(() => cn('animate-pulse rounded-md bg-slate-200/80', props.class))
</script>

<template>
  <div :class="skeletonClass" />
</template>

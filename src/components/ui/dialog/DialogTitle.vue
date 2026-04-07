<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const titleClass = computed(() => cn('text-xl font-semibold tracking-tight text-slate-950', props.class))
</script>

<template>
  <h2 :class="titleClass">
    <slot />
  </h2>
</template>

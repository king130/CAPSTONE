<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const headerClass = computed(() => cn('space-y-2 text-left', props.class))
</script>

<template>
  <div :class="headerClass">
    <slot />
  </div>
</template>

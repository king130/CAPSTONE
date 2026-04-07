<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const cellClass = computed(() => cn('p-4 align-middle', props.class))
</script>

<template>
  <td :class="cellClass">
    <slot />
  </td>
</template>

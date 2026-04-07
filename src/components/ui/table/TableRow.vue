<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<{ class?: string }>(), { class: '' })
const rowClass = computed(() => cn('border-b transition-colors hover:bg-muted/40 data-[state=selected]:bg-muted', props.class))
</script>

<template>
  <tr :class="rowClass">
    <slot />
  </tr>
</template>

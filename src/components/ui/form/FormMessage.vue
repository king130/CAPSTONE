<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    class?: string
    message?: string
  }>(),
  {
    class: '',
    message: '',
  },
)

const messageClass = computed(() => cn('text-sm font-medium text-red-600', props.class))
</script>

<template>
  <p v-if="message || $slots.default" :class="messageClass">
    <slot>{{ message }}</slot>
  </p>
</template>

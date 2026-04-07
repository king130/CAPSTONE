<script setup lang="ts">
import { computed, inject } from 'vue'

import { cn } from '@/lib/utils'
import { tabsContextKey } from '@/components/ui/tabs/context'

interface TabsContentProps {
  value: string
  class?: string
}

const props = withDefaults(defineProps<TabsContentProps>(), {
  class: '',
})

const tabsContext = inject(tabsContextKey)
const isActive = computed(() => tabsContext?.modelValue.value === props.value)
const contentClass = computed(() => cn('mt-6', props.class))
</script>

<template>
  <div v-if="isActive" :class="contentClass">
    <slot />
  </div>
</template>

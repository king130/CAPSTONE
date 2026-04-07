<script setup lang="ts">
import { computed, inject } from 'vue'

import { cn } from '@/lib/utils'
import { tabsContextKey } from '@/components/ui/tabs/context'

interface TabsTriggerProps {
  value: string
  class?: string
}

const props = withDefaults(defineProps<TabsTriggerProps>(), {
  class: '',
})

const tabsContext = inject(tabsContextKey)

const isActive = computed(() => tabsContext?.modelValue.value === props.value)
const triggerClass = computed(() =>
  cn(
    'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
    isActive.value
      ? 'bg-background text-foreground shadow-sm'
      : 'text-muted-foreground hover:text-foreground',
    props.class,
  ),
)

function handleClick() {
  tabsContext?.setValue(props.value)
}
</script>

<template>
  <button type="button" :class="triggerClass" @click="handleClick">
    <slot />
  </button>
</template>

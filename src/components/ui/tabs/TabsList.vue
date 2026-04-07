<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

interface TabsListProps {
  class?: string
}

const props = withDefaults(defineProps<TabsListProps>(), {
  class: '',
})

const listClass = computed(() =>
  cn(
    'inline-flex h-10 items-center justify-center rounded-lg bg-muted p-1 text-muted-foreground',
    props.class,
  ),
)
</script>

<template>
  <div :class="listClass">
    <slot />
  </div>
</template>

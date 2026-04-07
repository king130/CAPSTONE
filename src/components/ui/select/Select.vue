<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next'
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    modelValue?: string
    class?: string
  }>(),
  {
    modelValue: '',
    class: '',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const selectClass = computed(() =>
  cn(
    'flex h-11 w-full appearance-none rounded-md border border-input bg-background px-3 py-2 pr-10 text-sm ring-offset-background transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    props.class,
  ),
)

function onChange(event: Event) {
  emit('update:modelValue', (event.target as HTMLSelectElement).value)
}
</script>

<template>
  <div class="relative">
    <select :value="modelValue" :class="selectClass" @change="onChange">
      <slot />
    </select>
    <ChevronDown class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
  </div>
</template>

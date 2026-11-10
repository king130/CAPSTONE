<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next'
import { computed, useAttrs } from 'vue'

import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(
  defineProps<{
    modelValue?: string
    class?: string
    id?: string
    disabled?: boolean
    name?: string
  }>(),
  {
    modelValue: '',
    class: '',
    id: undefined,
    disabled: false,
    name: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const attrs = useAttrs()

/** Width/layout on the wrapper so the chevron stays inside the bordered box. */
const wrapperClass = computed(() =>
  cn('relative inline-block max-w-full w-full align-middle', props.class),
)

const selectClass = computed(() =>
  cn(
    // appearance-none removes the native arrow; pr-10 reserves space for the custom chevron.
    'flex h-11 w-full cursor-pointer appearance-none rounded-md border border-input bg-background px-3 py-2 pr-10 text-[16px] text-foreground shadow-none outline-none ring-offset-background transition md:text-sm',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
    'disabled:cursor-not-allowed disabled:opacity-50',
    '[color-scheme:light] dark:[color-scheme:dark]',
    props.class,
  ),
)

function onChange(event: Event) {
  emit('update:modelValue', (event.target as HTMLSelectElement).value)
}
</script>

<template>
  <div :class="wrapperClass">
    <select
      v-bind="attrs"
      :id="id"
      :name="name"
      :value="modelValue"
      :class="selectClass"
      :disabled="disabled"
      @change="onChange"
    >
      <slot />
    </select>
    <ChevronDown
      class="pointer-events-none absolute right-3 top-1/2 z-[1] h-4 w-4 -translate-y-1/2 text-muted-foreground"
      aria-hidden="true"
    />
  </div>
</template>

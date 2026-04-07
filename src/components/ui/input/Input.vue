<script setup lang="ts">
import { computed, useAttrs } from 'vue'

import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(
  defineProps<{
    modelValue?: string | number
    class?: string
    type?: string
  }>(),
  {
    modelValue: '',
    class: '',
    type: 'text',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const attrs = useAttrs()

const inputClass = computed(() =>
  cn(
    'flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    props.class,
  ),
)

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>

<template>
  <input
    v-bind="attrs"
    :type="type"
    :value="modelValue"
    :class="inputClass"
    @input="onInput"
  />
</template>

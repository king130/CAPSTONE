<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

interface TextareaProps {
  modelValue?: string
  class?: string
  rows?: number
  placeholder?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<TextareaProps>(), {
  modelValue: '',
  class: '',
  rows: 4,
  placeholder: '',
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const textareaClass = computed(() =>
  cn(
    'flex min-h-[96px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background transition placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    props.class,
  ),
)

function onInput(event: Event) {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}
</script>

<template>
  <textarea
    :value="modelValue"
    :rows="rows"
    :placeholder="placeholder"
    :disabled="disabled"
    :class="textareaClass"
    @input="onInput"
  />
</template>

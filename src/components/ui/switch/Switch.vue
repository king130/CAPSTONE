<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

interface SwitchProps {
  modelValue?: boolean
  disabled?: boolean
  class?: string
}

const props = withDefaults(defineProps<SwitchProps>(), {
  modelValue: false,
  disabled: false,
  class: '',
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const rootClass = computed(() =>
  cn(
    'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    props.modelValue ? 'bg-primary' : 'bg-slate-300',
    props.class,
  ),
)

const thumbClass = computed(() =>
  cn(
    'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform',
    props.modelValue ? 'translate-x-5' : 'translate-x-0',
  ),
)

function toggleSwitch() {
  if (props.disabled) return
  emit('update:modelValue', !props.modelValue)
}
</script>

<template>
  <button
    type="button"
    role="switch"
    :aria-checked="modelValue"
    :disabled="disabled"
    :class="rootClass"
    @click="toggleSwitch"
  >
    <span :class="thumbClass" />
  </button>
</template>

<script setup lang="ts">
import { computed, provide, toRef } from 'vue'

import { tabsContextKey } from '@/components/ui/tabs/context'

const props = withDefaults(
  defineProps<{
    modelValue: string
    class?: string
  }>(),
  {
    class: '',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const currentValue = toRef(props, 'modelValue')
const rootClass = computed(() => ['w-full', props.class].filter(Boolean).join(' '))

function setValue(value: string) {
  emit('update:modelValue', value)
}

provide(tabsContextKey, {
  modelValue: currentValue,
  setValue,
})
</script>

<template>
  <div :class="rootClass">
    <slot />
  </div>
</template>

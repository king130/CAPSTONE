<script setup lang="ts">
import { computed, ref, useAttrs } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'

import Input from '@/components/ui/input/Input.vue'
import { cn } from '@/lib/utils'

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(
  defineProps<{
    modelValue?: string | number
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

const attrs = useAttrs()
const visible = ref(false)

const inputClass = computed(() => cn('pr-10', props.class))

const forwardedAttrs = computed(() => {
  const { type: _type, ...rest } = attrs as Record<string, unknown>
  return rest
})
</script>

<template>
  <div class="relative w-full">
    <Input
      v-bind="forwardedAttrs"
      :model-value="modelValue"
      :type="visible ? 'text' : 'password'"
      :class="inputClass"
      @update:model-value="emit('update:modelValue', $event)"
    />
    <button
      type="button"
      class="absolute right-1 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:h-8 md:w-8"
      :aria-label="visible ? 'Hide password' : 'Show password'"
      :aria-pressed="visible"
      @click="visible = !visible"
    >
      <EyeOff v-if="visible" class="h-4 w-4" />
      <Eye v-else class="h-4 w-4" />
    </button>
  </div>
</template>

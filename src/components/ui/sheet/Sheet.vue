<script setup lang="ts">
import { computed } from 'vue'
import { X } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    open: boolean
    side?: 'left' | 'right'
  }>(),
  {
    side: 'left',
  },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const panelClass = computed(() =>
  cn(
    'fixed inset-y-0 z-50 w-[18rem] border-border bg-background p-6 shadow-xl transition-transform duration-200 ease-out',
    props.side === 'left'
      ? 'left-0 border-r data-[open=true]:translate-x-0'
      : 'right-0 border-l data-[open=true]:translate-x-0',
    props.side === 'left'
      ? props.open
        ? 'translate-x-0'
        : '-translate-x-full'
      : props.open
        ? 'translate-x-0'
        : 'translate-x-full',
  ),
)

function closeSheet() {
  emit('update:open', false)
}
</script>

<template>
  <slot name="trigger" />

  <Teleport to="body">
    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <button
        v-if="open"
        type="button"
        class="fixed inset-0 z-40 bg-black/50"
        aria-label="Close navigation"
        @click="closeSheet"
      />
    </transition>

    <div v-if="open" :class="panelClass">
      <div class="mb-6 flex items-center justify-between">
        <slot name="header" />
        <Button variant="ghost" size="icon" class="text-muted-foreground" @click="closeSheet">
          <X class="h-4 w-4" />
        </Button>
      </div>

      <slot :close="closeSheet" />
    </div>
  </Teleport>
</template>

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
    'fixed top-0 z-sheet flex h-dvh max-h-dvh w-[18rem] flex-col overflow-hidden border-border bg-background shadow-xl transition-transform duration-200 ease-out',
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
        class="fixed inset-0 z-sheet bg-black/50"
        aria-label="Close navigation"
        @click="closeSheet"
      />
    </transition>

    <div v-if="open" :class="panelClass">
      <div class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-4">
        <slot name="header" />
        <Button variant="ghost" size="icon" class="shrink-0 text-muted-foreground" @click="closeSheet">
          <X class="h-4 w-4" />
        </Button>
      </div>

      <div class="shell-menu-scroll px-3 py-4">
        <slot :close="closeSheet" />
      </div>

      <div v-if="$slots.footer" class="shrink-0 border-t border-border px-3 py-3">
        <slot name="footer" :close="closeSheet" />
      </div>
    </div>
  </Teleport>
</template>

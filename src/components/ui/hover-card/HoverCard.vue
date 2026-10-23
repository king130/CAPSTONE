<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'

import { useAnchoredOverlay } from '@/composables/useAnchoredOverlay'

const open = ref(false)
const { triggerRef, panelRef, panelStyle } = useAnchoredOverlay(open, 'center')
let hideTimer: ReturnType<typeof setTimeout> | undefined

function showCard() {
  if (hideTimer) clearTimeout(hideTimer)
  open.value = true
}

function hideCard() {
  hideTimer = setTimeout(() => {
    open.value = false
  }, 80)
}

onBeforeUnmount(() => {
  if (hideTimer) clearTimeout(hideTimer)
})
</script>

<template>
  <div ref="triggerRef" class="relative inline-flex" @mouseenter="showCard" @mouseleave="hideCard">
    <slot name="trigger" />
  </div>

  <Teleport to="body">
    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-1 opacity-0"
    >
      <div
        v-if="open"
        ref="panelRef"
        :style="panelStyle"
        class="w-64 rounded-xl border border-border bg-popover p-3 text-sm text-popover-foreground shadow-lg"
        @mouseenter="showCard"
        @mouseleave="hideCard"
      >
        <slot />
      </div>
    </transition>
  </Teleport>
</template>

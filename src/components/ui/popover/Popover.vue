<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

import { useAnchoredOverlay } from '@/composables/useAnchoredOverlay'

const open = ref(false)
function closePopover() {
  setOpen(false)
}

const { triggerRef, panelRef, panelStyle, containsTarget } = useAnchoredOverlay(open, 'end', closePopover)

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

function setOpen(value: boolean) {
  open.value = value
  emit('update:open', value)
}

function toggleOpen() {
  setOpen(!open.value)
}

function onDocumentClick(event: MouseEvent) {
  if (!containsTarget(event.target)) {
    closePopover()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <div ref="triggerRef" class="relative inline-flex">
    <div @click="toggleOpen">
      <slot name="trigger" :open="open" />
    </div>
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
        class="w-80 rounded-xl border border-border bg-popover p-2 text-popover-foreground shadow-lg"
        role="dialog"
      >
        <slot :close="closePopover" />
      </div>
    </transition>
  </Teleport>
</template>

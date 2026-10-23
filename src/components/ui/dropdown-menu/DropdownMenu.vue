<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import { useAnchoredOverlay } from '@/composables/useAnchoredOverlay'
import { cn } from '@/lib/utils'

const open = ref(false)
function closeMenu() {
  open.value = false
}

const { triggerRef, panelRef, panelStyle, containsTarget, updatePosition } = useAnchoredOverlay(open, 'end', closeMenu)

const menuClass = computed(() =>
  cn(
    'mt-0 w-56 max-w-[calc(100vw-1rem)] rounded-2xl border border-border bg-popover p-2 text-popover-foreground shadow-lg',
  ),
)

function toggleMenu() {
  open.value = !open.value
}

function onDocumentClick(event: MouseEvent) {
  if (!containsTarget(event.target)) {
    closeMenu()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})

defineExpose({
  closeMenu,
  updatePosition,
})
</script>

<template>
  <div ref="triggerRef" class="relative inline-flex">
    <div class="cursor-pointer" @click="toggleMenu">
      <slot name="trigger" :open="open" />
    </div>
  </div>

  <Teleport to="body">
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 scale-95 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 scale-100 opacity-100"
      leave-to-class="translate-y-2 scale-95 opacity-0"
    >
      <div v-if="open" ref="panelRef" :style="panelStyle" :class="menuClass" role="menu">
        <slot :close="closeMenu" />
      </div>
    </transition>
  </Teleport>
</template>

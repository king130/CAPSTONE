<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import { cn } from '@/lib/utils'

const open = ref(false)
const root = ref<HTMLElement | null>(null)

const menuClass = computed(() =>
  cn(
    'absolute right-0 top-full z-50 mt-3 min-w-56 overflow-hidden rounded-2xl border border-border/80 bg-background/95 p-2 text-popover-foreground shadow-[0_24px_60px_rgba(15,23,42,0.18)] backdrop-blur-xl',
    'before:pointer-events-none before:absolute before:inset-x-0 before:top-0 before:h-px before:bg-white/70',
  ),
)

function toggleMenu() {
  open.value = !open.value
}

function closeMenu() {
  open.value = false
}

function onDocumentClick(event: MouseEvent) {
  if (!root.value) return

  const target = event.target as Node | null
  if (target && !root.value.contains(target)) {
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
})
</script>

<template>
  <div ref="root" class="relative inline-flex">
    <div class="cursor-pointer" @click="toggleMenu">
      <slot name="trigger" :open="open" />
    </div>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 scale-95 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 scale-100 opacity-100"
      leave-to-class="translate-y-2 scale-95 opacity-0"
    >
      <div v-if="open" :class="menuClass">
        <slot :close="closeMenu" />
      </div>
    </transition>
  </div>
</template>

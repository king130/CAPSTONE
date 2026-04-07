<script setup lang="ts">
import { ref } from 'vue'

const open = ref(false)
</script>

<template>
  <div class="relative inline-flex" @mouseenter="open = true" @mouseleave="open = false">
    <slot name="trigger" />
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
        class="absolute left-1/2 top-full z-50 mt-2 w-64 -translate-x-1/2 rounded-xl border border-border bg-popover p-3 text-sm text-popover-foreground shadow-lg"
      >
        <slot />
      </div>
    </transition>
  </div>
</template>

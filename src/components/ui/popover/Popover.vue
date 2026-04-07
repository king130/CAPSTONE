<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const open = ref(false)
const root = ref<HTMLElement | null>(null)

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

function closePopover() {
  setOpen(false)
}

function onDocumentClick(event: MouseEvent) {
  if (!root.value) return

  const target = event.target as Node | null
  if (target && !root.value.contains(target)) {
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
  <div ref="root" class="relative inline-flex">
    <div @click="toggleOpen">
      <slot name="trigger" :open="open" />
    </div>

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
        class="absolute right-0 top-full z-50 mt-2 w-80 rounded-xl border border-border bg-popover p-2 text-popover-foreground shadow-lg"
      >
        <slot :close="closePopover" />
      </div>
    </transition>
  </div>
</template>

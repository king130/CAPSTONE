<script setup lang="ts">
import { X } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'

withDefaults(
  defineProps<{
    open: boolean
  }>(),
  {
    open: false,
  },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

function closeDialog() {
  emit('update:open', false)
}
</script>

<template>
  <Teleport to="body">
    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="open" class="fixed inset-0 z-50 bg-slate-950/50 backdrop-blur-sm" @click="closeDialog" />
    </transition>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-4 opacity-0 sm:scale-95"
      enter-to-class="translate-y-0 opacity-100 sm:scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100 sm:scale-100"
      leave-to-class="translate-y-4 opacity-0 sm:scale-95"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 overflow-y-auto p-4"
        @click.self="closeDialog"
      >
        <div class="mx-auto my-4 flex min-h-full w-full max-w-2xl items-center justify-center sm:my-8">
          <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-border bg-background shadow-2xl">
          <div class="sticky top-0 z-10 flex items-center justify-end border-b border-border/60 bg-background px-4 py-4">
            <Button variant="ghost" size="icon" class="text-muted-foreground" @click="closeDialog">
              <X class="h-4 w-4" />
            </Button>
          </div>
          <div class="max-h-[calc(100vh-8rem)] overflow-y-auto px-6 pb-6 pt-2">
            <slot :close="closeDialog" />
          </div>
        </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'
import Dialog from '@/components/ui/dialog/Dialog.vue'
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue'
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    description: string
    actionLabel?: string
    cancelLabel?: string
    actionVariant?: 'default' | 'outline' | 'ghost' | 'destructive'
  }>(),
  {
    actionLabel: 'Confirm',
    cancelLabel: 'Cancel',
    actionVariant: 'default',
  },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  action: []
}>()
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <template #default="{ close }">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <p class="text-sm leading-6 text-slate-600">{{ description }}</p>
      </DialogHeader>

      <div class="mt-6 flex justify-end gap-3">
        <Button variant="outline" @click="close">{{ cancelLabel }}</Button>
        <Button :variant="actionVariant" @click="emit('action'); close()">{{ actionLabel }}</Button>
      </div>
    </template>
  </Dialog>
</template>

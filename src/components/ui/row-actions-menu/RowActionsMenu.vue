<script setup lang="ts">
import { MoreHorizontal } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue'
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue'

export interface RowActionItem {
  key: string
  label: string
  icon?: unknown
  destructive?: boolean
  disabled?: boolean
}

withDefaults(
  defineProps<{
    items?: RowActionItem[]
    label?: string
    align?: 'start' | 'end' | 'center'
  }>(),
  {
    items: () => [],
    label: 'More actions',
    align: 'end',
  },
)

const emit = defineEmits<{
  select: [key: string]
}>()
</script>

<template>
  <DropdownMenu :align="align" content-class="w-48">
    <template #trigger>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        class="h-8 w-8 px-0"
        :aria-label="label"
      >
        <MoreHorizontal class="h-4 w-4" />
      </Button>
    </template>

    <template #default="{ close }">
      <slot :close="close">
        <DropdownMenuItem
          v-for="item in items"
          :key="item.key"
          :class="item.destructive ? 'text-red-600 hover:text-red-600' : ''"
          :disabled="item.disabled"
          @click="
            () => {
              if (item.disabled) return
              emit('select', item.key)
              close()
            }
          "
        >
          <component :is="item.icon" v-if="item.icon" class="h-4 w-4" />
          <span>{{ item.label }}</span>
        </DropdownMenuItem>
      </slot>
    </template>
  </DropdownMenu>
</template>

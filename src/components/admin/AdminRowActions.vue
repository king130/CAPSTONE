<script setup lang="ts">
import { ChevronDown, Check, Eye, X } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue'
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue'

withDefaults(
  defineProps<{
    approveLabel?: string
    rejectLabel?: string
    showApprove?: boolean
    showReject?: boolean
  }>(),
  {
    approveLabel: 'Approve',
    rejectLabel: 'Reject',
    showApprove: true,
    showReject: true,
  },
)

const emit = defineEmits<{
  view: []
  approve: []
  reject: []
}>()
</script>

<template>
  <DropdownMenu>
    <template #trigger>
      <Button variant="ghost" size="sm" class="h-9 gap-2">
        Actions
        <ChevronDown class="h-4 w-4" />
      </Button>
    </template>

    <template #default="{ close }">
      <DropdownMenuItem @click="emit('view'); close()">
        <Eye class="h-4 w-4" />
        <span>View</span>
      </DropdownMenuItem>
      <DropdownMenuItem v-if="showApprove" @click="emit('approve'); close()">
        <Check class="h-4 w-4" />
        <span>{{ approveLabel }}</span>
      </DropdownMenuItem>
      <DropdownMenuItem v-if="showReject" class="text-red-600 hover:text-red-600" @click="emit('reject'); close()">
        <X class="h-4 w-4" />
        <span>{{ rejectLabel }}</span>
      </DropdownMenuItem>
    </template>
  </DropdownMenu>
</template>

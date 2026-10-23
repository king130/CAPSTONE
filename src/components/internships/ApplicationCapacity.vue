<script setup lang="ts">
import { computed } from 'vue'

import Progress from '@/components/ui/progress/Progress.vue'
import { internshipCapacity } from '@/utils/internshipCapacity'

const props = withDefaults(
  defineProps<{
    slotsAvailable?: number | null
    slotsRemaining?: number | null
    filledSlots?: number | null
    compact?: boolean
  }>(),
  {
    slotsAvailable: 0,
    slotsRemaining: null,
    filledSlots: null,
    compact: false,
  },
)

const capacity = computed(() =>
  internshipCapacity({
    slotsAvailable: props.slotsAvailable,
    slotsRemaining: props.slotsRemaining,
    filledSlots: props.filledSlots,
  }),
)
</script>

<template>
  <div class="space-y-2">
    <div class="flex items-baseline justify-between gap-3">
      <p class="text-sm font-medium text-foreground">Applications</p>
      <p class="text-sm tabular-nums text-foreground">
        <span class="font-semibold">{{ capacity.filled }}</span>
        <span class="text-muted-foreground"> / {{ capacity.limit }}</span>
      </p>
    </div>
    <Progress
      :value="capacity.percent"
      role="progressbar"
      :aria-valuenow="capacity.percent"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="capacity.label"
    />
    <p v-if="!compact" class="text-xs text-muted-foreground">
      {{ capacity.percent }}% · {{ capacity.remainingLabel }}
    </p>
    <p v-else class="text-xs text-muted-foreground">{{ capacity.remainingLabel }}</p>
  </div>
</template>

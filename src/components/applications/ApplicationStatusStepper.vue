<script setup lang="ts">
import { Check, Circle, X } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'

import type { ApplicationStepperResult } from '@/utils/applicationStepper'

defineProps<{
  result: ApplicationStepperResult
}>()
</script>

<template>
  <div class="space-y-3">
    <ol class="flex flex-col gap-3 xl:flex-row xl:items-start xl:gap-2">
      <li
        v-for="(step, index) in result.steps"
        :key="step.key"
        class="relative flex min-w-0 flex-1 items-start gap-3 xl:flex-col xl:items-center xl:text-center"
      >
        <div
          v-if="index < result.steps.length - 1"
          class="absolute left-4 top-8 h-[calc(100%-0.5rem)] w-px bg-border xl:left-[calc(50%+1.1rem)] xl:top-4 xl:h-px xl:w-[calc(100%-0.5rem)]"
          aria-hidden="true"
        />
        <div
          class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border"
          :class="{
            'border-emerald-500/40 bg-emerald-500/15 text-emerald-800 dark:text-emerald-200': step.tone === 'complete',
            'border-primary bg-primary text-primary-foreground': step.tone === 'active',
            'border-destructive/40 bg-destructive/10 text-destructive': step.tone === 'error',
            'border-amber-500/40 bg-amber-500/15 text-amber-800 dark:text-amber-200': step.tone === 'warning',
            'border-border bg-muted text-muted-foreground': step.tone === 'pending',
          }"
        >
          <Check v-if="step.tone === 'complete'" class="h-4 w-4" />
          <X v-else-if="step.tone === 'error'" class="h-4 w-4" />
          <Circle v-else class="h-3.5 w-3.5" :class="step.tone === 'active' ? 'fill-current' : ''" />
        </div>
        <div class="min-w-0 md:px-1">
          <p
            class="text-sm font-medium"
            :class="step.current || step.tone === 'error' || step.tone === 'warning' ? 'text-foreground' : 'text-muted-foreground'"
          >
            {{ step.label }}
          </p>
          <p v-if="step.message && (step.current || step.tone === 'error' || step.tone === 'warning')" class="mt-1 text-xs leading-5 text-muted-foreground">
            {{ step.message }}
          </p>
        </div>
      </li>
    </ol>

    <p class="text-sm text-muted-foreground">
      <span class="font-medium text-foreground">Next:</span>
      <RouterLink
        v-if="result.nextActionTo"
        :to="result.nextActionTo"
        class="ml-1 text-primary underline-offset-4 hover:underline"
      >
        {{ result.nextAction }}
      </RouterLink>
      <span v-else class="ml-1">{{ result.nextAction }}</span>
    </p>
  </div>
</template>

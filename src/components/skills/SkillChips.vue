<script setup lang="ts">
import {
  skillImportanceLabel,
  skillLevelLabel,
  type SelectedSkill,
} from '@/services/skills'

withDefaults(
  defineProps<{
    skills: SelectedSkill[]
    mode?: 'intern' | 'job' | 'plain'
    emptyText?: string
  }>(),
  {
    mode: 'plain',
    emptyText: 'No skills listed yet.',
  },
)
</script>

<template>
  <div v-if="skills.length" class="flex flex-wrap gap-2">
    <span
      v-for="skill in skills"
      :key="skill.id"
      class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border bg-muted px-3 py-1 text-xs font-medium text-foreground"
    >
      <span class="truncate">{{ skill.name }}</span>
      <span
        v-if="mode === 'intern' && skill.level"
        class="rounded-full bg-background px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
      >
        {{ skillLevelLabel(skill.level) }}
      </span>
      <span
        v-else-if="mode === 'job' && skill.importance"
        class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
        :class="
          skill.importance === 'nice_to_have'
            ? 'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200'
            : 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
        "
      >
        {{ skillImportanceLabel(skill.importance) }}
      </span>
      <span
        v-if="skill.status === 'pending_review'"
        class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800"
      >
        Pending
      </span>
    </span>
  </div>
  <p v-else class="text-sm text-muted-foreground">{{ emptyText }}</p>
</template>

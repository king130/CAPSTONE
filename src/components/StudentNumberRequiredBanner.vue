<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { IdCard } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const needsStudentNumber = computed(() => {
  if (authStore.user?.role !== 'student') return false
  const profile = (authStore.user.profile as Record<string, unknown> | undefined) ?? {}
  const value = String(profile.studentNumber ?? profile.studentId ?? '').trim()
  return value === ''
})
</script>

<template>
  <div
    v-if="needsStudentNumber"
    class="border-b border-amber-300 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
    role="status"
  >
    <div class="mx-auto flex max-w-6xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-start gap-3">
        <IdCard class="mt-0.5 h-5 w-5 shrink-0" />
        <div>
          <p class="text-sm font-semibold">Add your student number</p>
          <p class="text-sm opacity-90">
            Your school has not set a student number on your account yet. Please enter it in Settings so your records stay complete.
          </p>
        </div>
      </div>
      <RouterLink
        to="/settings"
        class="inline-flex items-center justify-center rounded-md bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700"
      >
        Open Settings
      </RouterLink>
    </div>
  </div>
</template>

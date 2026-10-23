<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, Clock3, Lock, Sparkles } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const access = computed(() => authStore.user?.subscriptionAccess ?? null)
const role = computed(() => authStore.user?.role)
const orgType = computed(() => authStore.user?.activeOrganization?.type)
const showForOrg = computed(() =>
  ['school', 'company', 'municipal_hall'].includes(String(role.value)) ||
  ['school', 'company', 'municipal_hall'].includes(String(orgType.value)),
)

const status = computed(() => access.value?.status ?? null)
const daysRemaining = computed(() => access.value?.daysRemaining ?? null)

const tone = computed(() => {
  switch (status.value) {
    case 'trial':
      return 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100'
    case 'grace':
      return 'border-amber-400 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/50 dark:text-amber-100'
    case 'expired':
      return 'border-destructive/40 bg-destructive/10 text-foreground dark:border-destructive/50 dark:bg-destructive/20'
    default:
      return ''
  }
})

const title = computed(() => {
  if (status.value === 'trial') {
    const days = daysRemaining.value
    return days === null
      ? 'Trial active'
      : `Trial: ${days} day${days === 1 ? '' : 's'} remaining`
  }
  if (status.value === 'grace') {
    const days = daysRemaining.value
    return days === null
      ? 'Grace period — upgrade required'
      : `Grace period: ${days} day${days === 1 ? '' : 's'} left — upgrade required`
  }
  if (status.value === 'expired') {
    return 'Subscription expired — organization features are read-only'
  }
  return ''
})

const detail = computed(() => access.value?.message ?? '')

function goBilling() {
  void router.push({ name: 'organization-subscription' })
}
</script>

<template>
  <div
    v-if="showForOrg && access && status && status !== 'active'"
    data-testid="subscription-access-banner"
    class="mb-4 flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    :class="tone"
    role="status"
  >
    <div class="flex gap-3">
      <div class="mt-0.5 shrink-0">
        <Sparkles v-if="status === 'trial'" class="h-5 w-5" />
        <AlertTriangle v-else-if="status === 'grace'" class="h-5 w-5" />
        <Lock v-else class="h-5 w-5" />
      </div>
      <div>
        <p class="text-sm font-semibold" data-testid="subscription-banner-title">{{ title }}</p>
        <p class="mt-0.5 text-sm opacity-90">{{ detail }}</p>
        <p v-if="status === 'expired'" class="mt-1 text-xs opacity-80">
          You can still view data, messages, billing, and download your own records. Creating or editing postings, endorsements, interviews, students, staff, schedules, and agreements is blocked until you upgrade.
        </p>
      </div>
    </div>
    <Button
      size="sm"
      class="shrink-0"
      :variant="status === 'expired' ? 'default' : 'outline'"
      data-testid="subscription-upgrade-cta"
      @click="goBilling"
    >
      <Clock3 class="mr-1.5 h-4 w-4" />
      {{ status === 'expired' ? 'Upgrade to restore access' : 'View plans' }}
    </Button>
  </div>
</template>

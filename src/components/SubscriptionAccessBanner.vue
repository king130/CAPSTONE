<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AlertTriangle, Clock3, Lock, CreditCard, Sparkles, X } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import { shouldShowSubscriptionBanner } from '@/lib/subscriptionAccess'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const dismissed = ref(false)

const access = computed(() => authStore.user?.subscriptionAccess ?? null)
const role = computed(() => authStore.user?.role)
const orgType = computed(() => authStore.user?.activeOrganization?.type)
const showForOrg = computed(() =>
  ['school', 'company', 'municipal_hall'].includes(String(role.value)) ||
  ['school', 'company', 'municipal_hall'].includes(String(orgType.value)),
)

const status = computed(() => access.value?.status ?? null)
const daysRemaining = computed(() => access.value?.daysRemaining ?? null)

const isTrialEndedPastDue = computed(() => {
  if (status.value !== 'past_due') return false
  const message = String(access.value?.message || '').toLowerCase()
  if (message.includes('trial')) return true
  return Boolean(access.value?.trialEndsAt) && !access.value?.paidEndsAt
})

/** Banner for trial | pending_payment | past_due | expired — never for unverified / free / active. */
const showBanner = computed(() =>
  !dismissed.value &&
  showForOrg.value &&
  !!access.value &&
  route.name !== 'organization-subscription' &&
  shouldShowSubscriptionBanner(status.value),
)

watch(status, () => {
  dismissed.value = false
})

const tone = computed(() => {
  switch (status.value) {
    case 'trial':
      return 'border-emerald-300 bg-emerald-50 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
    case 'pending_payment':
      return 'border-sky-300 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100'
    case 'past_due':
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
    if (days == null) return 'Trial active'
    if (days <= 0) return 'Trial ending today'
    return `Trial — ${days} day${days === 1 ? '' : 's'} remaining`
  }
  if (status.value === 'pending_payment') {
    return 'Payment pending'
  }
  if (status.value === 'past_due') {
    if (isTrialEndedPastDue.value) return 'Your trial has ended'
    return 'Billing needs attention'
  }
  if (status.value === 'expired') {
    return 'Subscription expired — organization features are read-only'
  }
  return ''
})

const detail = computed(() => {
  if (status.value === 'trial') {
    return 'Your organization is on a free trial with Standard plan limits.'
  }
  if (status.value === 'pending_payment') {
    return 'Complete checkout to activate your plan.'
  }
  if (status.value === 'past_due') {
    if (isTrialEndedPastDue.value) {
      return 'Choose a paid plan to keep write access.'
    }
    return 'Update billing to keep write access.'
  }
  if (status.value === 'expired') {
    return 'You can still view data and billing. Creating or editing is blocked until you upgrade.'
  }
  return access.value?.message ?? ''
})

function goBilling() {
  void router.push({ name: 'organization-subscription' })
}

function dismiss() {
  dismissed.value = true
}
</script>

<template>
  <div
    v-if="showBanner"
    data-testid="subscription-access-banner"
    class="mb-3 flex items-center gap-3 rounded-lg border px-3 py-2 sm:px-4"
    :class="tone"
    role="status"
  >
    <div class="shrink-0">
      <Sparkles v-if="status === 'trial'" class="h-4 w-4" />
      <CreditCard v-else-if="status === 'pending_payment'" class="h-4 w-4" />
      <AlertTriangle v-else-if="status === 'past_due'" class="h-4 w-4" />
      <Lock v-else class="h-4 w-4" />
    </div>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold leading-tight" data-testid="subscription-banner-title">{{ title }}</p>
      <p class="text-xs leading-snug opacity-90 sm:text-sm">{{ detail }}</p>
    </div>
    <Button
      size="sm"
      class="h-8 shrink-0"
      :variant="status === 'expired' ? 'default' : 'outline'"
      data-testid="subscription-upgrade-cta"
      @click="goBilling"
    >
      <Clock3 class="mr-1.5 h-3.5 w-3.5" />
      {{
        status === 'expired'
          ? 'Upgrade'
          : status === 'pending_payment'
            ? 'Complete payment'
            : status === 'trial'
              ? 'View plans'
              : isTrialEndedPastDue
                ? 'Choose a plan'
                : 'Update billing'
      }}
    </Button>
    <button
      type="button"
      class="shrink-0 rounded-md p-1 opacity-70 transition hover:opacity-100"
      aria-label="Dismiss subscription banner"
      @click="dismiss"
    >
      <X class="h-4 w-4" />
    </button>
  </div>
</template>

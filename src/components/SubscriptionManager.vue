<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import {
  ArrowRight,
  BadgeCheck,
  Building2,
  CreditCard,
  ExternalLink,
  GraduationCap,
  LoaderCircle,
  RefreshCcw,
  ShieldCheck,
} from 'lucide-vue-next'

import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import { getSubscriptionPlans, type SubscriptionPlan } from '@/services/subscriptionPricing'
import {
  clearPendingPlanChange,
  createPaymongoCheckoutSession,
  updateUserSubscription,
  verifyPaymongoCheckoutSession,
} from '@/services/subscriptions'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{
  role: 'school' | 'company'
}>()

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const plans = ref<SubscriptionPlan[]>([])
const loading = ref(true)
const actionPlanId = ref<string | null>(null)
const verifying = ref(false)

const roleLabel = computed(() => (props.role === 'school' ? 'School' : 'Company'))
const roleIcon = computed(() => (props.role === 'school' ? GraduationCap : Building2))
const roleTheme = computed(() =>
  props.role === 'school'
    ? {
        panel: 'border-sky-200 bg-sky-50/80',
        badge: 'bg-sky-100 text-sky-700',
        accent: 'from-sky-600 via-cyan-600 to-emerald-500',
        ring: 'ring-sky-200',
        button: 'bg-sky-600 hover:bg-sky-700',
        soft: 'bg-sky-100 text-sky-700',
      }
    : {
        panel: 'border-emerald-200 bg-emerald-50/80',
        badge: 'bg-emerald-100 text-emerald-700',
        accent: 'from-emerald-600 via-teal-600 to-cyan-500',
        ring: 'ring-emerald-200',
        button: 'bg-emerald-600 hover:bg-emerald-700',
        soft: 'bg-emerald-100 text-emerald-700',
      },
)

const currentSubscription = computed(() => authStore.user?.subscription ?? null)
const currentPlanId = computed(() => normalizePlanId(currentSubscription.value?.plan))
const pendingChange = computed(() => currentSubscription.value?.pendingChange ?? null)

const currentPlan = computed(() => plans.value.find((plan) => plan.id === currentPlanId.value) ?? null)
const recommendedPlanId = computed(() => {
  const standard = plans.value.find((plan) => plan.id === 'standard')
  return standard?.id ?? plans.value[0]?.id ?? 'free'
})

const statusTone = computed(() => {
  switch (currentSubscription.value?.status) {
    case 'active':
      return 'bg-emerald-100 text-emerald-700'
    case 'pending':
      return 'bg-amber-100 text-amber-700'
    default:
      return 'bg-slate-200 text-slate-700'
  }
})

const summaryMetrics = computed(() => {
  const current = currentPlan.value
  if (!current) {
    return []
  }

  return props.role === 'school'
    ? [
        { label: 'Coordinator Seats', value: formatLimit(current.limits.school.coordinators), hint: 'active coordinators allowed' },
        { label: 'Student Capacity', value: formatLimit(current.limits.school.students), hint: 'managed intern accounts' },
        { label: 'Plan Status', value: capitalize(currentSubscription.value?.status ?? 'inactive'), hint: 'workspace access state' },
      ]
    : [
        { label: 'Team Seats', value: formatLimit(current.limits.company.accounts), hint: 'company users allowed' },
        { label: 'Internship Posts', value: formatLimit(current.limits.company.internships), hint: 'active opportunities capacity' },
        { label: 'Plan Status', value: capitalize(currentSubscription.value?.status ?? 'inactive'), hint: 'billing state' },
      ]
})

const compactFeatures = computed(() =>
  plans.value.reduce<Record<string, string[]>>((accumulator, plan) => {
    accumulator[plan.id] = plan.features[props.role].slice(0, 5)
    return accumulator
  }, {}),
)

onMounted(async () => {
  await loadPlans()

  if (route.query.paymongo === 'success' && pendingChange.value?.checkoutSessionId && pendingChange.value?.requestId) {
    await verifyPendingPayment()
  }
})

async function loadPlans() {
  loading.value = true
  try {
    plans.value = await getSubscriptionPlans()
  } catch (error) {
    console.error('Failed to load plans:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Unable to load plans',
      text: 'The subscription catalog could not be loaded right now.',
      confirmButtonColor: '#0f766e',
    })
  } finally {
    loading.value = false
  }
}

function normalizePlanId(plan: string | undefined): string {
  return (plan ?? 'free').toString().trim().toLowerCase()
}

function priceForRole(plan: SubscriptionPlan): number {
  return props.role === 'school' ? plan.schoolPrice : plan.companyPrice
}

function formatPrice(amount: number): string {
  return amount === 0 ? 'Free' : `PHP ${amount.toLocaleString()}`
}

function formatPriceAmount(amount: number): string {
  return amount.toLocaleString()
}

function formatLimit(value: number): string {
  return value >= 999 ? 'Unlimited' : value.toLocaleString()
}

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

function isCurrentPlan(plan: SubscriptionPlan): boolean {
  return currentPlanId.value === plan.id && !pendingChange.value
}

function isPendingPlan(plan: SubscriptionPlan): boolean {
  return normalizePlanId(pendingChange.value?.targetPlan) === plan.id
}

function isBusy(planId: string): boolean {
  return actionPlanId.value === planId
}

function badgeLabel(plan: SubscriptionPlan): string | null {
  if (isCurrentPlan(plan)) return 'Current Plan'
  if (isPendingPlan(plan)) return 'Pending Verification'
  if (plan.id === recommendedPlanId.value) return 'Recommended'
  return null
}

async function handlePlanAction(plan: SubscriptionPlan) {
  if (!authStore.user?.uid) {
    return
  }

  if (isCurrentPlan(plan)) {
    return
  }

  const amount = priceForRole(plan)
  actionPlanId.value = plan.id

  try {
    if (amount === 0) {
      await updateUserSubscription(authStore.user.uid, {
        plan: plan.id,
        status: 'active',
        billingCycle: 'monthly',
      })
      await clearPendingPlanChange(authStore.user.uid).catch(() => null)
      await authStore.refreshUser()
      await Swal.fire({
        icon: 'success',
        title: 'Plan updated',
        text: 'Your workspace has been moved to the free plan.',
        confirmButtonColor: '#0f766e',
      })
      return
    }

    const requestId = `plan_${plan.id}_${Date.now()}`
    const result = await createPaymongoCheckoutSession(authStore.user.uid, {
      requestId,
      planId: plan.id,
      planName: plan.name,
      role: props.role,
      amount,
      billingCycle: 'monthly',
      returnUrl: window.location.href.split('?')[0],
    })

    await authStore.refreshUser()

    await Swal.fire({
      icon: 'success',
      title: 'PayMongo checkout ready',
      text: 'You will be sent to PayMongo test checkout. Return here after payment to verify the upgrade.',
      confirmButtonText: 'Open Checkout',
      confirmButtonColor: props.role === 'school' ? '#0284c7' : '#059669',
    })

    window.open(result.checkoutUrl, '_blank', 'noopener,noreferrer')
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Unable to start checkout right now.'
    await Swal.fire({
      icon: 'error',
      title: 'Checkout unavailable',
      text: message,
      confirmButtonColor: '#dc2626',
    })
  } finally {
    actionPlanId.value = null
  }
}

async function verifyPendingPayment() {
  if (!authStore.user?.uid || !pendingChange.value?.checkoutSessionId || !pendingChange.value?.requestId) {
    return
  }

  verifying.value = true

  try {
    const result = await verifyPaymongoCheckoutSession(authStore.user.uid, {
      requestId: pendingChange.value.requestId,
      checkoutSessionId: pendingChange.value.checkoutSessionId,
    })

    await authStore.refreshUser()

    if (result.verified) {
      await Swal.fire({
        icon: 'success',
        title: 'Payment verified',
        text: 'Your organization subscription is now active.',
        confirmButtonColor: props.role === 'school' ? '#0284c7' : '#059669',
      })
      await router.replace({ path: route.path, query: {} })
      return
    }

    await Swal.fire({
      icon: 'info',
      title: 'Still waiting on payment',
      text: result.message ?? 'The PayMongo checkout has not completed yet.',
      confirmButtonColor: '#0f766e',
    })
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Unable to verify payment right now.'
    await Swal.fire({
      icon: 'error',
      title: 'Verification failed',
      text: message,
      confirmButtonColor: '#dc2626',
    })
  } finally {
    verifying.value = false
  }
}

function reopenCheckout() {
  if (!pendingChange.value?.checkoutUrl) {
    return
  }

  window.open(pendingChange.value.checkoutUrl, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <div class="space-y-6">
    <Card class="overflow-hidden border-slate-200 shadow-sm">
      <div class="grid gap-0 xl:grid-cols-[1.5fr_0.9fr]">
        <div class="relative overflow-hidden border-b border-slate-200 bg-slate-950 px-6 py-7 text-white xl:border-b-0 xl:border-r">
          <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.3),_transparent_45%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.28),_transparent_42%)]" />
          <div class="relative space-y-5">
            <div class="flex flex-wrap items-center gap-3">
              <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-slate-100">
                <component :is="roleIcon" class="h-4 w-4" />
                {{ roleLabel }} Billing
              </span>
              <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusTone">
                {{ capitalize(currentSubscription?.status ?? 'inactive') }}
              </span>
            </div>

            <div class="space-y-3">
              <h2 class="max-w-2xl text-3xl font-semibold tracking-tight sm:text-4xl">
                A cleaner billing workspace for plan changes, checkout, and verification.
              </h2>
              <p class="max-w-2xl text-sm leading-6 text-slate-200/90">
                Pick the right subscription for your {{ props.role === 'school' ? 'school operations' : 'internship program' }},
                launch PayMongo test checkout for paid upgrades, and verify the payment back inside the platform.
              </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
              <div
                v-for="metric in summaryMetrics"
                :key="metric.label"
                class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm"
              >
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">{{ metric.label }}</p>
                <p class="mt-3 text-2xl font-semibold text-white">{{ metric.value }}</p>
                <p class="mt-1 text-xs text-slate-300">{{ metric.hint }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-4 bg-white px-6 py-7">
          <div :class="['rounded-2xl border p-5', roleTheme.panel]">
            <div class="flex items-start gap-3">
              <div :class="['rounded-2xl p-3', roleTheme.soft]">
                <CreditCard class="h-5 w-5" />
              </div>
              <div class="space-y-2">
                <p class="text-sm font-semibold text-slate-950">PayMongo temporary checkout</p>
                <p class="text-sm leading-6 text-slate-600">
                  Use PayMongo as the temporary payment mode while you validate the subscription flow.
                  Paid plans open an external checkout, then return here for verification.
                </p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex items-start gap-3">
              <div class="rounded-2xl bg-emerald-100 p-3 text-emerald-700">
                <ShieldCheck class="h-5 w-5" />
              </div>
              <div class="space-y-2">
                <p class="text-sm font-semibold text-slate-950">Verification stays in-app</p>
                <p class="text-sm leading-6 text-slate-600">
                  After paying, use the verification action below. That keeps plan activation aligned with the organization inside this workspace.
                </p>
              </div>
            </div>
          </div>

          <div v-if="pendingChange" class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div class="flex flex-col gap-4">
              <div class="flex items-start gap-3">
                <div class="rounded-2xl bg-amber-100 p-3 text-amber-700">
                  <RefreshCcw class="h-5 w-5" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-slate-950">Pending plan upgrade</p>
                  <p class="mt-1 text-sm leading-6 text-slate-600">
                    {{
                      `A ${capitalize(pendingChange.targetPlan)} plan checkout is waiting for verification.`
                    }}
                  </p>
                </div>
              </div>

              <div class="grid gap-3 sm:grid-cols-2">
                <button
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                  :disabled="verifying"
                  @click="verifyPendingPayment"
                >
                  <LoaderCircle v-if="verifying" class="h-4 w-4 animate-spin" />
                  <BadgeCheck v-else class="h-4 w-4" />
                  Verify Payment
                </button>

                <button
                  v-if="pendingChange.checkoutUrl"
                  type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                  @click="reopenCheckout"
                >
                  <ExternalLink class="h-4 w-4" />
                  Reopen Checkout
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Card>

    <div v-if="loading" class="grid gap-4 lg:grid-cols-3">
      <Card v-for="index in 3" :key="index" class="border-slate-200 shadow-sm">
        <CardContent class="space-y-4 p-6">
          <div class="h-4 w-24 animate-pulse rounded bg-slate-200" />
          <div class="h-9 w-40 animate-pulse rounded bg-slate-200" />
          <div class="space-y-2">
            <div class="h-3 animate-pulse rounded bg-slate-200" />
            <div class="h-3 animate-pulse rounded bg-slate-200" />
            <div class="h-3 w-5/6 animate-pulse rounded bg-slate-200" />
          </div>
        </CardContent>
      </Card>
    </div>

    <div v-else class="space-y-6">
      <div class="grid gap-5 xl:grid-cols-3">
        <Card
          v-for="plan in plans"
          :key="plan.id"
          class="relative overflow-hidden border-slate-200 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg"
          :class="
            [
              plan.id === recommendedPlanId ? `ring-2 ${roleTheme.ring}` : '',
              isCurrentPlan(plan) ? 'border-emerald-200 ring-2 ring-emerald-200' : '',
              isPendingPlan(plan) ? 'border-amber-200 ring-2 ring-amber-200' : '',
            ]
              .filter(Boolean)
              .join(' ')
          "
        >
          <div :class="`h-1.5 bg-gradient-to-r ${roleTheme.accent}`" />
          <CardHeader class="space-y-4 p-6">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-lg font-semibold text-slate-950">{{ plan.name }}</p>
                <p class="mt-1 line-clamp-2 min-h-[2.75rem] text-sm text-slate-600">{{ plan.description }}</p>
              </div>
              <span
                v-if="badgeLabel(plan)"
                class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                :class="
                  isCurrentPlan(plan)
                    ? 'bg-emerald-100 text-emerald-700'
                    : isPendingPlan(plan)
                      ? 'bg-amber-100 text-amber-700'
                      : roleTheme.badge
                "
              >
                {{ badgeLabel(plan) }}
              </span>
            </div>

            <div class="space-y-1">
              <template v-if="priceForRole(plan) === 0">
                <p class="text-4xl font-semibold tracking-tight text-slate-950">
                  {{ formatPrice(priceForRole(plan)) }}
                </p>
              </template>
              <div v-else class="flex flex-wrap items-end gap-x-2 gap-y-1">
                <span class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">PHP</span>
                <p class="text-[2.45rem] font-semibold leading-none tracking-tight text-slate-950 sm:text-[2.7rem]">
                  {{ formatPriceAmount(priceForRole(plan)) }}
                </p>
                <span class="pb-1 text-sm text-slate-500">/month</span>
              </div>
              <p class="text-sm text-slate-500">
                {{ priceForRole(plan) === 0 ? 'No monthly charge' : 'Monthly billing via PayMongo test checkout' }}
              </p>
            </div>
          </CardHeader>

          <CardContent class="space-y-5 p-6 pt-0">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Capacity</p>
              <div class="mt-3 grid grid-cols-2 gap-3">
                <template v-if="props.role === 'school'">
                  <div class="rounded-xl bg-white px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">Coordinators</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ formatLimit(plan.limits.school.coordinators) }}</p>
                  </div>
                  <div class="rounded-xl bg-white px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">Students</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ formatLimit(plan.limits.school.students) }}</p>
                  </div>
                </template>
                <template v-else>
                  <div class="rounded-xl bg-white px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">Accounts</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ formatLimit(plan.limits.company.accounts) }}</p>
                  </div>
                  <div class="rounded-xl bg-white px-3 py-3">
                    <p class="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">Internships</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ formatLimit(plan.limits.company.internships) }}</p>
                  </div>
                </template>
              </div>
            </div>

            <ul class="space-y-2.5">
              <li
                v-for="feature in compactFeatures[plan.id] ?? []"
                :key="feature"
                class="flex items-start gap-3 text-sm leading-6 text-slate-700"
              >
                <span class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                  <BadgeCheck class="h-3.5 w-3.5" />
                </span>
                <span>{{ feature }}</span>
              </li>
            </ul>

            <p
              v-if="(plan.features[props.role]?.length ?? 0) > 5"
              class="text-xs font-medium uppercase tracking-[0.16em] text-slate-500"
            >
              + {{ plan.features[props.role].length - 5 }} more included features
            </p>

            <button
              type="button"
              class="inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-70"
              :class="isCurrentPlan(plan) ? 'bg-emerald-600 hover:bg-emerald-600' : roleTheme.button"
              :disabled="isCurrentPlan(plan) || isBusy(plan.id)"
              @click="handlePlanAction(plan)"
            >
              <LoaderCircle v-if="isBusy(plan.id)" class="h-4 w-4 animate-spin" />
              <BadgeCheck v-else-if="isCurrentPlan(plan)" class="h-4 w-4" />
              <ArrowRight v-else class="h-4 w-4" />
              {{
                isCurrentPlan(plan)
                  ? 'Current Plan'
                  : priceForRole(plan) === 0
                    ? 'Switch to Free'
                    : 'Choose and Pay'
              }}
            </button>
          </CardContent>
        </Card>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <Card class="border-slate-200 shadow-sm">
          <CardHeader class="space-y-2 p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Current Subscription</p>
            <h3 class="text-xl font-semibold text-slate-950">
              {{ currentPlan ? `${currentPlan.name} Plan` : 'No Active Plan Yet' }}
            </h3>
          </CardHeader>
          <CardContent class="space-y-4 p-6 pt-0">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm text-slate-500">Billing cycle</span>
                <span class="font-medium text-slate-900">{{ capitalize(currentSubscription?.billingCycle ?? 'monthly') }}</span>
              </div>
              <div class="mt-3 flex items-center justify-between gap-3">
                <span class="text-sm text-slate-500">Status</span>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusTone">
                  {{ capitalize(currentSubscription?.status ?? 'inactive') }}
                </span>
              </div>
              <div class="mt-3 flex items-center justify-between gap-3">
                <span class="text-sm text-slate-500">Subscription code</span>
                <span class="font-medium text-slate-900">
                  {{ currentSubscription?.subscriptionCode || 'Auto-generated after activation' }}
                </span>
              </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
              <p class="text-sm font-semibold text-slate-950">What happens after checkout</p>
              <ol class="mt-3 space-y-3 text-sm leading-6 text-slate-600">
                <li>1. Choose a paid plan and open the PayMongo checkout page.</li>
                <li>2. Finish the temporary payment flow in PayMongo.</li>
                <li>3. Return here and verify the payment to activate the upgrade.</li>
              </ol>
            </div>
          </CardContent>
        </Card>

        <Card class="border-slate-200 shadow-sm">
          <CardHeader class="space-y-2 p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Payment Notes</p>
            <h3 class="text-xl font-semibold text-slate-950">Designed for your current rollout</h3>
          </CardHeader>
          <CardContent class="space-y-3 p-6 pt-0 text-sm leading-6 text-slate-600">
            <p>
              This version is intentionally built around PayMongo as a temporary payment mode so you can keep shipping the platform while the final billing setup is still evolving.
            </p>
            <p>
              The upgrade flow is separated into checkout and verification on purpose, which makes it easier to recover pending payments without manually editing subscription data.
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>

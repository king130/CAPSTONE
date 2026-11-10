<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from '@/services/swal'
import {
  ArrowRight,
  BadgeCheck,
  Building2,
  CreditCard,
  ExternalLink,
  GraduationCap,
  LoaderCircle,
  RefreshCcw,
  X,
} from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import { getSubscriptionPlans, type SubscriptionPlan } from '@/services/subscriptionPricing'
import {
  cancelPendingUpgrade,
  createPaymongoCheckoutSession,
  verifyPaymongoCheckoutSession,
} from '@/services/subscriptions'
import { billingDisplayStatus, isTrialEndedAccess } from '@/lib/subscriptionAccess'
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
const cancelling = ref(false)

const isMunicipalHall = computed(
  () => String(authStore.user?.activeOrganization?.type || '').toLowerCase() === 'municipal_hall',
)
const roleLabel = computed(() =>
  props.role === 'school' ? 'School' : isMunicipalHall.value ? 'Municipal Hall' : 'Company',
)
const roleIcon = computed(() => (props.role === 'school' ? GraduationCap : Building2))
const roleTheme = computed(() =>
  props.role === 'school'
    ? {
        badge: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        button: 'bg-sky-600 hover:bg-sky-700',
        ring: 'ring-sky-300 dark:ring-sky-700',
        soft: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        panel: 'border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/30',
      }
    : {
        badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
        button: 'bg-emerald-600 hover:bg-emerald-700',
        ring: 'ring-emerald-300 dark:ring-emerald-700',
        soft: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
        panel: 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30',
      },
)

const currentSubscription = computed(() => authStore.user?.subscription ?? null)
const subscriptionAccess = computed(() => authStore.user?.subscriptionAccess ?? null)
const paymentConnected = computed(() => Boolean(authStore.user?.paymentConnected))
const currentPlanId = computed(() => normalizePlanId(currentSubscription.value?.plan))
const pendingChange = computed(() => currentSubscription.value?.pendingChange ?? null)
const currentPlan = computed(() => plans.value.find((plan) => plan.id === currentPlanId.value) ?? null)
const recommendedPlanId = computed(() => plans.value.find((plan) => plan.id === 'standard')?.id ?? plans.value[0]?.id ?? 'standard')
const selectablePlans = computed(() => plans.value.filter((plan) => plan.id !== 'free' && plan.isSelectable !== false))

const displayStatus = computed(() => billingDisplayStatus(subscriptionAccess.value))
const trialEnded = computed(() => isTrialEndedAccess(subscriptionAccess.value))

const planHeading = computed(() => {
  if (!currentPlan.value) return 'Choose a plan'
  if (displayStatus.value === 'Trial ended') {
    return `${currentPlan.value.name} plan (trial ended)`
  }
  return `${currentPlan.value.name} plan`
})

const statusTone = computed(() => {
  switch (displayStatus.value) {
    case 'Active':
    case 'Free trial':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
    case 'Pending payment':
      return 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200'
    case 'Trial ended':
    case 'Payment failed':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'
    default:
      return 'bg-muted text-muted-foreground'
  }
})

const summaryMetrics = computed(() => {
  const current = currentPlan.value
  if (!current) return []

  return props.role === 'school'
    ? [
        { label: 'Coordinators', value: formatLimit(current.limits.school.coordinators) },
        { label: 'Students', value: formatLimit(current.limits.school.students) },
        { label: 'Status', value: displayStatus.value },
      ]
    : [
        { label: 'Team seats', value: formatLimit(current.limits.company.accounts) },
        { label: 'Internship posts', value: formatLimit(current.limits.company.internships) },
        { label: 'Status', value: displayStatus.value },
      ]
})

const compactFeatures = computed(() =>
  plans.value.reduce<Record<string, string[]>>((accumulator, plan) => {
    accumulator[plan.id] = plan.features[props.role].slice(0, 5)
    return accumulator
  }, {}),
)

onMounted(async () => {
  // Ensure pendingChange is loaded before any return-from-PayMongo verify.
  await authStore.refreshUser().catch(() => null)
  await loadPlans()

  const paymongo = String(route.query.paymongo || '')
  if (paymongo === 'success') {
    await authStore.refreshUser().catch(() => null)
    if (pendingChange.value?.checkoutSessionId && pendingChange.value?.requestId) {
      await verifyPendingPayment()
    }
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
  return (plan ?? 'standard').toString().trim().toLowerCase()
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

/** School is on this plan but trial ended — not locked as "Current". */
function isTrialPlanCard(plan: SubscriptionPlan): boolean {
  return trialEnded.value && isCurrentPlan(plan)
}

/** True current paid/active plan — disables Choose and pay. */
function isLockedCurrentPlan(plan: SubscriptionPlan): boolean {
  return isCurrentPlan(plan) && !trialEnded.value
}

function isPendingPlan(plan: SubscriptionPlan): boolean {
  return normalizePlanId(pendingChange.value?.targetPlan) === plan.id
}

function isBusy(planId: string): boolean {
  return actionPlanId.value === planId
}

function badgeLabel(plan: SubscriptionPlan): string | null {
  if (isTrialPlanCard(plan)) return 'Trial plan'
  if (isLockedCurrentPlan(plan)) return 'Current'
  if (isPendingPlan(plan)) return 'Pending'
  if (plan.id === recommendedPlanId.value) return 'Recommended'
  return null
}

async function handlePlanAction(plan: SubscriptionPlan) {
  if (!authStore.user?.uid || isLockedCurrentPlan(plan)) return
  if (plan.id === 'free') {
    await Swal.fire({
      icon: 'info',
      title: 'Free plan unavailable',
      text: 'New organizations start on a trial, then choose a paid plan. The free plan is no longer offered.',
      confirmButtonColor: props.role === 'school' ? '#0284c7' : '#059669',
    })
    return
  }

  const amount = priceForRole(plan)
  actionPlanId.value = plan.id

  try {
    if (!paymentConnected.value) {
      await Swal.fire({
        icon: 'info',
        title: 'Payment not connected',
        text: 'Online checkout is not configured on this environment. Ask a platform admin to activate a paid plan manually, or continue on your trial while it lasts.',
        confirmButtonColor: props.role === 'school' ? '#0284c7' : '#059669',
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
      text: 'Complete payment in PayMongo, then return here to verify.',
      confirmButtonText: 'Open Checkout',
      confirmButtonColor: props.role === 'school' ? '#0284c7' : '#059669',
    })

    window.open(result.checkoutUrl, '_blank', 'noopener,noreferrer')
  } catch (error) {
    // Failed checkout must never look like payment success; refresh to confirm plan unchanged.
    await authStore.refreshUser().catch(() => null)
    const message = error instanceof Error ? error.message : 'Unable to start checkout right now.'
    const paymongoUnavailable =
      /paymongo.*(not configured|unavailable)|paid checkout is currently unavailable/i.test(message)

    await Swal.fire({
      icon: paymongoUnavailable ? 'info' : 'error',
      title: paymongoUnavailable ? 'Paid checkout unavailable' : 'Checkout unavailable',
      text: paymongoUnavailable
        ? `${message} No paid plan was activated. Free remains available, and any already-active paid plan stays unchanged.`
        : message,
      confirmButtonColor: paymongoUnavailable
        ? props.role === 'school'
          ? '#0284c7'
          : '#059669'
        : '#dc2626',
    })
  } finally {
    actionPlanId.value = null
  }
}

async function verifyPendingPayment() {
  if (!authStore.user?.uid) return

  await authStore.refreshUser().catch(() => null)

  const pending = authStore.user?.subscription?.pendingChange
  if (!pending?.checkoutSessionId || !pending?.requestId) {
    return
  }

  verifying.value = true

  try {
    const result = await verifyPaymongoCheckoutSession(authStore.user.uid, {
      requestId: pending.requestId,
      checkoutSessionId: pending.checkoutSessionId,
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
    await authStore.refreshUser().catch(() => null)
    const message = error instanceof Error ? error.message : 'Unable to verify payment right now.'
    const paymongoUnavailable =
      /paymongo.*(not configured|unavailable)|paid checkout is currently unavailable/i.test(message)
    await Swal.fire({
      icon: paymongoUnavailable ? 'info' : 'error',
      title: paymongoUnavailable ? 'Paid verification unavailable' : 'Verification failed',
      text: paymongoUnavailable
        ? `${message} No paid plan was activated from this attempt.`
        : message,
      confirmButtonColor: paymongoUnavailable
        ? props.role === 'school'
          ? '#0284c7'
          : '#059669'
        : '#dc2626',
    })
  } finally {
    verifying.value = false
  }
}

async function reopenCheckout() {
  const pending = pendingChange.value
  if (!authStore.user?.uid || !pending) return

  const plan = plans.value.find((item) => item.id === normalizePlanId(pending.targetPlan))
  if (!plan) {
    if (pending.checkoutUrl) window.open(pending.checkoutUrl, '_blank', 'noopener,noreferrer')
    return
  }

  await handlePlanAction(plan)
}

async function cancelPendingCheckout() {
  if (!authStore.user?.uid || !pendingChange.value) return

  const confirmed = await Swal.fire({
    icon: 'warning',
    title: 'Cancel pending checkout?',
    text: 'This clears the unfinished PayMongo upgrade so you can choose another plan.',
    showCancelButton: true,
    confirmButtonText: 'Cancel checkout',
    cancelButtonText: 'Keep pending',
    confirmButtonColor: '#dc2626',
  })

  if (!confirmed.isConfirmed) return

  cancelling.value = true
  try {
    await cancelPendingUpgrade(authStore.user.uid)
    await authStore.refreshUser()
    await router.replace({ path: route.path, query: {} })
    await Swal.fire({
      icon: 'success',
      title: 'Checkout cancelled',
      text: 'You can start a new plan change whenever you are ready.',
      confirmButtonColor: props.role === 'school' ? '#0284c7' : '#059669',
    })
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Unable to cancel the pending checkout.'
    await Swal.fire({
      icon: 'error',
      title: 'Cancel failed',
      text: message,
      confirmButtonColor: '#dc2626',
    })
  } finally {
    cancelling.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white text-slate-900 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-white">
      <div class="border-b border-slate-200 bg-slate-50/80 px-5 py-5 dark:border-slate-800 dark:bg-slate-900/80 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="flex items-start gap-3">
            <div :class="['flex h-11 w-11 items-center justify-center rounded-2xl', roleTheme.soft]">
              <component :is="roleIcon" class="h-5 w-5" />
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ roleLabel }} billing</p>
              <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                {{ planHeading }}
              </h2>
              <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                Manage capacity and upgrades with PayMongo checkout.
              </p>
            </div>
          </div>
          <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold" :class="statusTone">
            {{ displayStatus }}
          </span>
        </div>

        <div v-if="summaryMetrics.length" class="mt-5 grid gap-3 sm:grid-cols-3">
          <div
            v-for="metric in summaryMetrics"
            :key="metric.label"
            class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-800/50"
          >
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ metric.label }}</p>
            <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-white">{{ metric.value }}</p>
          </div>
        </div>
      </div>

      <div v-if="pendingChange" class="border-b border-amber-100 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30 px-5 py-4 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-start gap-3">
            <div class="rounded-xl bg-amber-100 p-2.5 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">
              <RefreshCcw class="h-4 w-4" />
            </div>
            <div>
              <p class="text-sm font-semibold text-foreground">
                Pending {{ capitalize(pendingChange.targetPlan) }} upgrade
              </p>
              <p class="mt-1 text-sm text-muted-foreground">
                Finish PayMongo payment, then verify here — or cancel to pick another plan.
              </p>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button size="sm" :disabled="verifying || cancelling" @click="verifyPendingPayment">
              <LoaderCircle v-if="verifying" class="h-4 w-4 animate-spin" />
              <BadgeCheck v-else class="h-4 w-4" />
              Verify payment
            </Button>
            <Button
              v-if="pendingChange.checkoutUrl"
              size="sm"
              variant="outline"
              :disabled="verifying || cancelling"
              @click="reopenCheckout"
            >
              <ExternalLink class="h-4 w-4" />
              Reopen checkout
            </Button>
            <Button size="sm" variant="outline" :disabled="verifying || cancelling" @click="cancelPendingCheckout">
              <LoaderCircle v-if="cancelling" class="h-4 w-4 animate-spin" />
              <X v-else class="h-4 w-4" />
              Cancel
            </Button>
          </div>
        </div>
      </div>

      <div
        class="grid gap-3 px-5 py-4 sm:px-6"
        :class="displayStatus === 'Active' ? 'sm:grid-cols-1' : 'sm:grid-cols-2'"
      >
        <div :class="['rounded-xl border p-4', roleTheme.panel]">
          <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
            <CreditCard class="h-4 w-4" />
            PayMongo checkout
          </div>
          <p class="mt-2 text-sm text-muted-foreground">
            Checkout asks PayMongo for GCash, Maya, GrabPay, ShopeePay, card, and related options. Only methods enabled on the PayMongo account appear on the payment page. QR “scan to pay” shows when wallets are not enabled there. PayPal is not supported.
          </p>
        </div>
        <div
          v-if="displayStatus !== 'Active'"
          class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50"
        >
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
            <BadgeCheck class="h-4 w-4" />
            Activation
          </div>
          <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
            New organizations start on a time-limited trial with Standard plan limits. Paid limits apply only after a real PayMongo payment is verified — or after a platform admin activates a plan manually.
          </p>
        </div>
      </div>

      <div
        v-if="!paymentConnected"
        class="border-t border-slate-200 bg-slate-50/60 px-5 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400 sm:px-6"
        data-testid="payment-not-connected"
      >
        Payment is not connected on this environment. Online checkout is unavailable; ask a platform admin to activate a paid plan if your trial ends.
      </div>

      <div
        v-if="subscriptionAccess"
        class="border-t border-slate-200 px-5 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300 sm:px-6"
        data-testid="subscription-access-summary"
      >
        <span class="font-medium text-slate-900 dark:text-white">Access:</span>
        {{ displayStatus }}
      </div>
    </section>

    <div v-if="loading" class="grid gap-4 lg:grid-cols-3">
      <Card
        v-for="index in 3"
        :key="index"
        class="border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
      >
        <CardContent class="space-y-4 p-6">
          <div class="h-4 w-24 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
          <div class="h-9 w-40 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
          <div class="h-3 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
          <div class="h-3 w-5/6 animate-pulse rounded bg-slate-100 dark:bg-slate-800" />
        </CardContent>
      </Card>
    </div>

    <div v-else class="grid gap-4 xl:grid-cols-3">
      <Card
        v-for="plan in selectablePlans"
        :key="plan.id"
        :class="[
          'border-slate-200 bg-white text-slate-900 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-white',
          isLockedCurrentPlan(plan) ? 'ring-2 ring-emerald-300 dark:ring-emerald-700' : '',
          isTrialPlanCard(plan) || isPendingPlan(plan) ? 'ring-2 ring-amber-300 dark:ring-amber-700' : '',
          plan.id === recommendedPlanId && !isLockedCurrentPlan(plan) && !isTrialPlanCard(plan) && !isPendingPlan(plan) ? `ring-2 ${roleTheme.ring}` : '',
        ].filter(Boolean).join(' ')"
      >
        <CardHeader class="space-y-3 p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ plan.name }}</p>
              <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ plan.description }}</p>
            </div>
            <span
              v-if="badgeLabel(plan)"
              class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide"
              :class="
                isLockedCurrentPlan(plan)
                  ? 'border border-amber-500/50 bg-amber-50/50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400'
                  : isTrialPlanCard(plan) || isPendingPlan(plan)
                    ? 'border border-amber-500/50 bg-amber-50/50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400'
                    : roleTheme.badge
              "
            >
              {{ badgeLabel(plan) }}
            </span>
          </div>

          <div>
            <template v-if="priceForRole(plan) === 0">
              <p class="text-3xl font-semibold text-slate-900 dark:text-white">{{ formatPrice(0) }}</p>
            </template>
            <div v-else class="flex items-end gap-2">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">PHP</span>
              <p class="text-3xl font-semibold leading-none text-slate-900 dark:text-white">{{ formatPriceAmount(priceForRole(plan)) }}</p>
              <span class="pb-0.5 text-sm text-slate-500 dark:text-slate-400">/mo</span>
            </div>
          </div>
        </CardHeader>

        <CardContent class="space-y-4 p-5 pt-0">
          <div class="grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
            <template v-if="props.role === 'school'">
              <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Coordinators</p>
                <p class="font-semibold text-slate-900 dark:text-white">{{ formatLimit(plan.limits.school.coordinators) }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Students</p>
                <p class="font-semibold text-slate-900 dark:text-white">{{ formatLimit(plan.limits.school.students) }}</p>
              </div>
            </template>
            <template v-else>
              <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Accounts</p>
                <p class="font-semibold text-slate-900 dark:text-white">{{ formatLimit(plan.limits.company.accounts) }}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Internships</p>
                <p class="font-semibold text-slate-900 dark:text-white">{{ formatLimit(plan.limits.company.internships) }}</p>
              </div>
            </template>
          </div>

          <ul class="space-y-2">
            <li
              v-for="feature in compactFeatures[plan.id] ?? []"
              :key="feature"
              class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400"
            >
              <BadgeCheck class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
              <span>{{ feature }}</span>
            </li>
          </ul>

          <Button
            class="w-full gap-2"
            :class="isLockedCurrentPlan(plan) ? 'bg-emerald-600 hover:bg-emerald-600' : roleTheme.button"
            :disabled="isLockedCurrentPlan(plan) || isBusy(plan.id) || (!!pendingChange && priceForRole(plan) !== 0)"
            @click="handlePlanAction(plan)"
          >
            <LoaderCircle v-if="isBusy(plan.id)" class="h-4 w-4 animate-spin" />
            <BadgeCheck v-else-if="isLockedCurrentPlan(plan)" class="h-4 w-4" />
            <ArrowRight v-else class="h-4 w-4" />
            {{
              isLockedCurrentPlan(plan)
                ? 'Current plan'
                : pendingChange && priceForRole(plan) !== 0
                  ? 'Resolve pending first'
                  : priceForRole(plan) === 0
                    ? 'Switch to Free'
                    : 'Choose and pay'
            }}
          </Button>
        </CardContent>
      </Card>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/60 sm:p-6">
      <h3 class="text-base font-semibold text-slate-900 dark:text-white">How upgrades work</h3>
      <ol class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
        <li class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">1. Choose a paid plan and open PayMongo checkout.</li>
        <li class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">2. Complete payment in the PayMongo window.</li>
        <li class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-700 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">3. Return here and verify to activate the plan.</li>
      </ol>
      <p v-if="currentSubscription?.subscriptionCode" class="mt-4 text-sm text-slate-600 dark:text-slate-400">
        Subscription code:
        <span class="font-medium text-slate-900 dark:text-white">{{ currentSubscription.subscriptionCode }}</span>
      </p>
    </section>
  </div>
</template>

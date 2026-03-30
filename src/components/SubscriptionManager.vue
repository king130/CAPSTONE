<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Swal from 'sweetalert2'
import { useAuthStore } from '@/stores/auth'
import { getSubscriptionPlans, formatPrice, type SubscriptionPlan } from '@/services/subscriptionPricing'
import {
  clearPendingPlanChange,
  createPaymongoCheckoutSession,
  updateUserSubscription,
  verifyPaymongoCheckoutSession,
  type SubscriptionStatus,
} from '@/services/subscriptions'

type Role = 'company' | 'school'

const props = defineProps<{
  role: Role
}>()

const authStore = useAuthStore()
const loading = ref(true)
const savingPlanId = ref<string | null>(null)
const verifyingPayment = ref(false)
const plans = ref<SubscriptionPlan[]>([])
const copied = ref(false)

const planRank = (id: string) => (id === 'free' ? 0 : id === 'standard' ? 1 : id === 'premium' ? 2 : -1)

const subscription = computed(() => authStore.user?.subscription)
const subscriptionPlanId = computed(() => (subscription.value?.plan || 'free').toLowerCase())
const subscriptionStatus = computed<SubscriptionStatus>(() => (subscription.value?.status || 'inactive') as SubscriptionStatus)
const billingCycle = computed(() => subscription.value?.billingCycle || 'monthly')
const schoolCode = computed(() => subscription.value?.subscriptionCode || '')
const pendingChange = computed(() => subscription.value?.pendingChange || null)
const currentPlan = computed(() => plans.value.find((p) => p.id === subscriptionPlanId.value) || null)
const pendingTargetPlan = computed(() => {
  const targetId = pendingChange.value?.targetPlan
  return targetId ? plans.value.find((p) => p.id === targetId) || null : null
})

const headerTitle = computed(() => (props.role === 'school' ? 'School Subscription' : 'Company Subscription'))
const hasActiveSubscription = computed(() => subscriptionStatus.value === 'active')
const hasPendingChange = computed(() => !!pendingChange.value)
const hasPaymongoCheckout = computed(() => !!pendingChange.value?.checkoutUrl && !!pendingChange.value?.checkoutSessionId)
const activeSummary = computed(() => {
  if (hasPendingChange.value && pendingTargetPlan.value) {
    return `Pending ${pendingTargetPlan.value.name}`
  }
  return currentPlan.value?.name || 'Free'
})

const sandboxHints = computed(() => {
  const methods = pendingChange.value?.paymentMethods?.length
    ? pendingChange.value.paymentMethods
    : ['card']
  return methods.map((method) => {
    if (method === 'card') return 'Use PayMongo test mode card details only. No real charge is created.'
    if (method === 'gcash') return 'Use a PayMongo test GCash flow if your account has it enabled.'
    if (method === 'maya') return 'Use a PayMongo test Maya flow if your account has it enabled.'
    return `Use PayMongo test mode for ${method}.`
  })
})

onMounted(async () => {
  try {
    plans.value = await getSubscriptionPlans()
    await maybeVerifyReturn()
  } finally {
    loading.value = false
  }
})

async function maybeVerifyReturn() {
  if (typeof window === 'undefined') return
  const url = new URL(window.location.href)
  const paymongoState = url.searchParams.get('paymongo')
  const requestId = url.searchParams.get('requestId')
  const pending = pendingChange.value

  if (!paymongoState || !requestId || !pending || pending.requestId !== requestId) return

  url.searchParams.delete('paymongo')
  url.searchParams.delete('requestId')
  window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`)

  if (paymongoState === 'success' && pending.checkoutSessionId) {
    await verifyPendingCheckout({ silentOnPending: false })
    return
  }

  if (paymongoState === 'cancel') {
    await Swal.fire({
      icon: 'info',
      title: 'Checkout closed',
      text: 'Your PayMongo test checkout is still saved. You can reopen it anytime from the pending payment card.',
      confirmButtonColor: '#2563eb',
    })
  }
}

const rolePrice = (plan: SubscriptionPlan) => (props.role === 'school' ? plan.schoolPrice : plan.companyPrice)
const roleFeatures = (plan: SubscriptionPlan) => (props.role === 'school' ? plan.features.school : plan.features.company)

function actionLabel(targetId: string) {
  if (subscriptionStatus.value !== 'active') return `Choose ${labelForPlan(targetId)}`
  const diff = planRank(targetId) - planRank(subscriptionPlanId.value)
  if (diff > 0) return `Upgrade to ${labelForPlan(targetId)}`
  if (diff < 0) return `Downgrade to ${labelForPlan(targetId)}`
  return 'Current Plan'
}

function labelForPlan(planId: string) {
  if (planId === 'free') return 'Free'
  if (planId === 'standard') return 'Standard'
  if (planId === 'premium') return 'Premium'
  return planId
}

function requiresPayment(current: SubscriptionPlan | null, target: SubscriptionPlan) {
  const currentAmount = current ? rolePrice(current) : 0
  const targetAmount = rolePrice(target)
  if (targetAmount <= 0) return false
  if (subscriptionStatus.value !== 'active') return true
  return targetAmount > currentAmount
}

function planDeltaLabel(target: SubscriptionPlan) {
  if (!hasActiveSubscription.value) return requiresPayment(currentPlan.value, target) ? 'Starts with PayMongo test checkout' : 'Activates immediately'
  const diff = planRank(target.id) - planRank(subscriptionPlanId.value)
  if (diff > 0) return requiresPayment(currentPlan.value, target) ? 'Pay in PayMongo test mode' : 'Upgrade instantly'
  if (diff < 0) return 'Downgrade without checkout'
  return 'Already active'
}

function canSelectPlan(target: SubscriptionPlan) {
  if (savingPlanId.value || verifyingPayment.value) return false
  if (target.id === subscriptionPlanId.value && !hasPendingChange.value) return false
  if (hasPendingChange.value && target.id !== 'free') return false
  return true
}

function disabledReason(target: SubscriptionPlan) {
  if (savingPlanId.value || verifyingPayment.value) return 'Please wait while billing is updating.'
  if (target.id === subscriptionPlanId.value && !hasPendingChange.value) return 'This is your current plan.'
  if (hasPendingChange.value && target.id !== 'free') return 'Finish or cancel the current PayMongo checkout first.'
  return ''
}

function generateRequestId() {
  try {
    return `PM-${crypto.randomUUID().slice(0, 8).toUpperCase()}`
  } catch {
    return `PM-${Math.random().toString(16).slice(2, 10).toUpperCase()}`
  }
}

async function copySchoolCode() {
  if (!schoolCode.value) return
  try {
    await navigator.clipboard.writeText(schoolCode.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 1400)
  } catch {
    copied.value = false
  }
}

async function setPlan(planId: string) {
  if (!authStore.user?.uid || savingPlanId.value) return
  const target = plans.value.find((p) => p.id === planId)
  if (!target) return

  if (hasPendingChange.value && planId !== 'free') {
    await Swal.fire({
      icon: 'info',
      title: 'Pending PayMongo checkout',
      text: 'Finish or cancel the current test checkout before selecting another paid plan.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  const result = await Swal.fire({
    icon: 'question',
    title: `${actionLabel(planId)}?`,
    html: `
      <div style="text-align:left;">
        <p><strong>Plan:</strong> ${target.name}</p>
        <p><strong>Price:</strong> ${formatPrice(rolePrice(target))}${rolePrice(target) > 0 ? `/${billingCycle.value}` : ''}</p>
        <p><strong>Flow:</strong> ${requiresPayment(currentPlan.value, target) ? 'PayMongo test checkout' : 'Direct update'}</p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: requiresPayment(currentPlan.value, target) ? 'Open PayMongo checkout' : 'Apply plan',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })
  if (!result.isConfirmed) return

  savingPlanId.value = planId
  try {
    if (requiresPayment(currentPlan.value, target)) {
      const requestId = generateRequestId()
      const checkout = await createPaymongoCheckoutSession(authStore.user.uid, {
        requestId,
        planId: target.id,
        planName: target.name,
        role: props.role,
        amount: rolePrice(target),
        billingCycle: billingCycle.value,
        returnUrl: typeof window !== 'undefined' ? window.location.href : '',
      })
      if (checkout.checkoutUrl) {
        window.open(checkout.checkoutUrl, '_blank', 'noopener,noreferrer')
      }

      await authStore.refreshUser()

      await Swal.fire({
        icon: 'success',
        title: 'PayMongo test checkout ready',
        html: `
          <div style="text-align:left;">
            <p>Your test checkout was created successfully.</p>
            <p><strong>Request ID:</strong> ${checkout.requestId}</p>
            <p><strong>Next step:</strong> finish the test payment in PayMongo, then click <strong>Verify payment</strong> on this page.</p>
          </div>
        `,
        confirmButtonColor: '#2563eb',
      })
      return
    }

    await updateUserSubscription(authStore.user.uid, { plan: target.id, status: 'active' })
    await Swal.fire({
      icon: 'success',
      title: 'Subscription updated',
      text: `Your plan is now ${target.name}.`,
      confirmButtonColor: '#2563eb',
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Billing update failed',
      text: error instanceof Error ? error.message : 'Could not update your subscription.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}

async function cancelToFree() {
  if (!authStore.user?.uid || savingPlanId.value) return

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Move to Free plan?',
    text: 'This will switch your account to Free and remove any pending PayMongo checkout.',
    showCancelButton: true,
    confirmButtonText: 'Move to Free',
    cancelButtonText: 'Keep current plan',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return

  savingPlanId.value = 'free'
  try {
    await updateUserSubscription(authStore.user.uid, { plan: 'free', status: 'active' })
    await clearPendingPlanChange(authStore.user.uid).catch(() => {})
    await Swal.fire({
      icon: 'success',
      title: 'Free plan active',
      text: 'Your subscription has been moved to the Free plan.',
      confirmButtonColor: '#2563eb',
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Cancellation failed',
      text: error instanceof Error ? error.message : 'Could not update your subscription.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}

async function cancelPendingRequest() {
  if (!authStore.user?.uid || !pendingChange.value || savingPlanId.value) return

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel pending PayMongo checkout?',
    text: 'This keeps your current plan and removes the saved test checkout session from the app.',
    showCancelButton: true,
    confirmButtonText: 'Cancel checkout',
    cancelButtonText: 'Keep checkout',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return

  savingPlanId.value = 'pending-cancel'
  try {
    await clearPendingPlanChange(authStore.user.uid)
    await Swal.fire({
      icon: 'success',
      title: 'Pending checkout removed',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}

function openPendingCheckout() {
  const url = pendingChange.value?.checkoutUrl
  if (!url) return
  window.open(url, '_blank', 'noopener,noreferrer')
}

async function verifyPendingCheckout(options: { silentOnPending?: boolean } = {}) {
  if (!authStore.user?.uid || !pendingChange.value?.checkoutSessionId || verifyingPayment.value) return

  verifyingPayment.value = true
  try {
    const result = await verifyPaymongoCheckoutSession(authStore.user.uid, {
      requestId: pendingChange.value.requestId,
      checkoutSessionId: pendingChange.value.checkoutSessionId,
    })

    if (result.verified) {
      await Swal.fire({
        icon: 'success',
        title: 'Payment verified',
        text: 'Your PayMongo test payment is complete and the subscription is now active.',
        confirmButtonColor: '#2563eb',
      })
      return
    }

    if (!options.silentOnPending) {
      await Swal.fire({
        icon: 'info',
        title: 'Still waiting for payment',
        text: result.message || 'The test payment is not marked as paid yet. Finish the PayMongo checkout first, then verify again.',
        confirmButtonColor: '#2563eb',
      })
    }
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Verification failed',
      text: error instanceof Error ? error.message : 'Could not verify your PayMongo checkout.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    verifyingPayment.value = false
    await authStore.refreshUser().catch(() => {})
  }
}
</script>

<template>
  <div class="subscription-manager">
    <header class="hero-card" :class="`hero-${props.role}`">
      <div>
        <div class="eyebrow">Billing</div>
        <h1>{{ headerTitle }}</h1>
        <p class="hero-copy">
          Pick a plan, launch a PayMongo test checkout for paid upgrades, and verify the sandbox payment without using real money.
        </p>
      </div>
      <div class="hero-status">
        <span class="status-chip" :class="`status-${subscriptionStatus}`">{{ subscriptionStatus }}</span>
        <span class="hero-plan">{{ activeSummary }}</span>
      </div>
    </header>

    <div v-if="loading" class="loading-card">
      Loading billing details...
    </div>

    <div v-else class="billing-layout">
      <section class="summary-grid">
        <article class="summary-card primary">
          <div class="summary-label">Current plan</div>
          <div class="summary-value">{{ currentPlan?.name || 'Free' }}</div>
          <div class="summary-subtext">
            {{ formatPrice(currentPlan ? rolePrice(currentPlan) : 0) }}
            <span v-if="currentPlan && rolePrice(currentPlan) > 0">/{{ billingCycle }}</span>
          </div>
        </article>

        <article class="summary-card">
          <div class="summary-label">Billing cycle</div>
          <div class="summary-value capitalize">{{ billingCycle }}</div>
          <div class="summary-subtext">Applies to the active organization subscription.</div>
        </article>

        <article class="summary-card" v-if="role === 'school' && schoolCode">
          <div class="summary-label">School code</div>
          <div class="summary-value code-text">{{ schoolCode }}</div>
          <button class="subtle-btn" type="button" @click="copySchoolCode">{{ copied ? 'Copied' : 'Copy code' }}</button>
        </article>
      </section>

      <section v-if="pendingChange" class="pending-panel">
        <div class="pending-head">
          <div>
            <div class="panel-title">Pending PayMongo Test Checkout</div>
            <div class="panel-copy">
              {{ pendingTargetPlan?.name || pendingChange.targetPlan }} for {{ formatPrice(pendingChange.amount) }}
            </div>
          </div>
          <div class="pending-badge">{{ pendingChange.checkoutStatus || 'waiting' }}</div>
        </div>

        <div class="pending-grid">
          <div class="pending-item">
            <span class="pending-label">Request ID</span>
            <strong>{{ pendingChange.requestId }}</strong>
          </div>
          <div class="pending-item" v-if="pendingChange.checkoutSessionId">
            <span class="pending-label">Checkout session</span>
            <strong class="mono">{{ pendingChange.checkoutSessionId }}</strong>
          </div>
          <div class="pending-item">
            <span class="pending-label">Sandbox mode</span>
            <strong>No real money is charged</strong>
          </div>
        </div>

        <div class="sandbox-box">
          <div class="sandbox-title">PayMongo test mode notes</div>
          <ul class="sandbox-list">
            <li v-for="hint in sandboxHints" :key="hint">{{ hint }}</li>
            <li>Open the checkout, finish the test payment there, then return here and click `Verify payment`.</li>
          </ul>
        </div>

        <div class="pending-actions">
          <button v-if="hasPaymongoCheckout" class="primary-btn" type="button" :disabled="verifyingPayment" @click="openPendingCheckout">
            Open checkout
          </button>
          <button class="primary-btn verify" type="button" :disabled="verifyingPayment" @click="verifyPendingCheckout()">
            {{ verifyingPayment ? 'Verifying...' : 'Verify payment' }}
          </button>
          <button class="ghost-btn danger" type="button" :disabled="!!savingPlanId || verifyingPayment" @click="cancelPendingRequest">
            Cancel pending checkout
          </button>
        </div>
      </section>

      <section class="plans-section">
        <div class="section-head">
          <div>
            <h2>Plans</h2>
            <p>Paid upgrades now go through PayMongo test checkout instead of manual receipt entry.</p>
          </div>
          <button
            v-if="subscriptionPlanId !== 'free' || subscriptionStatus !== 'active'"
            class="ghost-btn danger"
            type="button"
            :disabled="!!savingPlanId || verifyingPayment"
            @click="cancelToFree"
          >
            Move to Free
          </button>
        </div>

        <div class="plans-grid">
          <article
            v-for="plan in plans"
            :key="plan.id"
            class="plan-card"
            :class="{
              active: plan.id === subscriptionPlanId && !hasPendingChange,
              recommended: plan.id === 'standard' && plan.id !== subscriptionPlanId,
            }"
          >
            <div class="plan-head">
              <div class="plan-topline">
                <div class="plan-name">{{ plan.name }}</div>
                <div class="plan-tags">
                  <span v-if="plan.id === subscriptionPlanId && !hasPendingChange" class="tag current">Current</span>
                  <span v-else-if="plan.id === 'standard'" class="tag rec">Recommended</span>
                  <span v-if="requiresPayment(currentPlan, plan)" class="tag paymongo">PayMongo</span>
                </div>
              </div>
              <div class="plan-price">
                {{ formatPrice(rolePrice(plan)) }}
                <span v-if="rolePrice(plan) > 0" class="per">/{{ billingCycle }}</span>
              </div>
              <p class="plan-description">{{ plan.description }}</p>
              <p class="plan-delta">{{ planDeltaLabel(plan) }}</p>
            </div>

            <ul class="plan-features">
              <li v-for="feature in roleFeatures(plan)" :key="feature">{{ feature }}</li>
            </ul>

            <button
              class="plan-action"
              type="button"
              :disabled="!canSelectPlan(plan)"
              :title="disabledReason(plan)"
              @click="setPlan(plan.id)"
            >
              {{ savingPlanId === plan.id ? 'Processing...' : actionLabel(plan.id) }}
            </button>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.subscription-manager {
  padding: 28px;
  min-height: calc(100vh - 40px);
  background:
    radial-gradient(1000px 480px at 8% 0%, rgba(14, 165, 233, 0.12), transparent 60%),
    radial-gradient(760px 420px at 94% 2%, rgba(16, 185, 129, 0.11), transparent 58%),
    #f8fafc;
}

.hero-card {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  padding: 22px;
  border-radius: 22px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: rgba(255, 255, 255, 0.86);
  backdrop-filter: blur(8px);
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
}

.hero-school {
  box-shadow: 0 18px 40px rgba(37, 99, 235, 0.1);
}

.hero-company {
  box-shadow: 0 18px 40px rgba(5, 150, 105, 0.1);
}

.eyebrow {
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #64748b;
}

.hero-card h1 {
  margin: 6px 0 0;
  font-size: 26px;
  font-weight: 900;
  color: #0f172a;
}

.hero-copy {
  margin: 10px 0 0;
  max-width: 700px;
  color: #475569;
  line-height: 1.55;
}

.hero-status {
  min-width: 180px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 10px;
}

.status-chip {
  padding: 7px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
  text-transform: capitalize;
}

.status-active {
  background: #dcfce7;
  color: #166534;
}

.status-pending {
  background: #fef3c7;
  color: #92400e;
}

.status-inactive {
  background: #e2e8f0;
  color: #334155;
}

.hero-plan {
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
}

.loading-card,
.summary-card,
.pending-panel,
.plans-section {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
}

.loading-card {
  margin-top: 18px;
  padding: 24px;
  color: #475569;
}

.billing-layout {
  display: grid;
  gap: 18px;
  margin-top: 18px;
}

.summary-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.summary-card {
  padding: 18px;
}

.summary-card.primary {
  background: linear-gradient(135deg, #eff6ff, #ffffff);
}

.summary-label {
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
}

.summary-value {
  margin-top: 8px;
  font-size: 24px;
  font-weight: 900;
  color: #0f172a;
}

.summary-subtext {
  margin-top: 6px;
  color: #64748b;
  font-size: 14px;
}

.capitalize {
  text-transform: capitalize;
}

.code-text {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
  font-size: 20px;
}

.subtle-btn,
.ghost-btn,
.primary-btn,
.plan-action {
  border: none;
  cursor: pointer;
  font-weight: 800;
  transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.subtle-btn {
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #f1f5f9;
  color: #0f172a;
}

.ghost-btn {
  padding: 11px 14px;
  border-radius: 12px;
  background: #fff;
  border: 1px solid #cbd5e1;
  color: #0f172a;
}

.ghost-btn.danger {
  color: #991b1b;
  border-color: rgba(220, 38, 38, 0.2);
  background: rgba(220, 38, 38, 0.06);
}

.primary-btn,
.plan-action {
  padding: 12px 16px;
  border-radius: 12px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white;
  box-shadow: 0 14px 26px rgba(37, 99, 235, 0.22);
}

.primary-btn.verify {
  background: linear-gradient(135deg, #0f766e, #0d9488);
  box-shadow: 0 14px 26px rgba(13, 148, 136, 0.22);
}

.primary-btn:disabled,
.ghost-btn:disabled,
.plan-action:disabled,
.subtle-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.pending-panel,
.plans-section {
  padding: 20px;
}

.pending-head,
.section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.panel-title,
.section-head h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 900;
  color: #0f172a;
}

.panel-copy,
.section-head p {
  margin: 6px 0 0;
  color: #64748b;
  line-height: 1.45;
}

.pending-badge {
  padding: 8px 12px;
  border-radius: 999px;
  background: #fff7ed;
  color: #9a3412;
  font-size: 12px;
  font-weight: 900;
  text-transform: capitalize;
}

.pending-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  margin-top: 16px;
}

.pending-item {
  padding: 14px;
  border-radius: 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #0f172a;
}

.pending-label {
  display: block;
  margin-bottom: 6px;
  font-size: 12px;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
  font-size: 13px;
}

.sandbox-box {
  margin-top: 16px;
  padding: 16px;
  border-radius: 16px;
  background: linear-gradient(135deg, #ecfeff, #f8fafc);
  border: 1px solid #bae6fd;
}

.sandbox-title {
  font-size: 14px;
  font-weight: 900;
  color: #0f172a;
}

.sandbox-list {
  margin: 10px 0 0;
  padding-left: 18px;
  color: #334155;
  line-height: 1.55;
}

.pending-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 18px;
}

.plans-grid {
  display: grid;
  gap: 14px;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  margin-top: 18px;
}

.plan-card {
  display: flex;
  flex-direction: column;
  min-height: 340px;
  padding: 18px;
  border-radius: 18px;
  border: 1px solid #e2e8f0;
  background: linear-gradient(180deg, #ffffff, #fbfdff);
}

.plan-card.active {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.plan-card.recommended {
  border-color: rgba(37, 99, 235, 0.3);
}

.plan-head {
  padding-bottom: 12px;
  border-bottom: 1px solid #e2e8f0;
}

.plan-topline {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.plan-name {
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
}

.plan-tags {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.tag {
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 900;
}

.tag.current {
  background: #dcfce7;
  color: #166534;
}

.tag.rec {
  background: #eff6ff;
  color: #1d4ed8;
}

.tag.paymongo {
  background: #ecfeff;
  color: #0f766e;
}

.plan-price {
  margin-top: 10px;
  font-size: 28px;
  font-weight: 900;
  color: #111827;
}

.per {
  margin-left: 6px;
  font-size: 13px;
  font-weight: 700;
  color: #64748b;
}

.plan-description,
.plan-delta {
  margin: 8px 0 0;
  color: #64748b;
}

.plan-delta {
  font-size: 13px;
  font-weight: 800;
  color: #334155;
}

.plan-features {
  flex: 1;
  margin: 16px 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.plan-features li {
  position: relative;
  padding-left: 18px;
  color: #334155;
  line-height: 1.45;
}

.plan-features li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: #16a34a;
  font-weight: 900;
}

@media (max-width: 900px) {
  .hero-card,
  .pending-head,
  .section-head {
    flex-direction: column;
    align-items: stretch;
  }

  .hero-status {
    align-items: flex-start;
  }
}

@media (max-width: 768px) {
  .subscription-manager {
    padding: 18px;
  }

  .pending-actions,
  .section-head {
    flex-direction: column;
  }

  .primary-btn,
  .ghost-btn,
  .plan-action {
    width: 100%;
  }
}
</style>

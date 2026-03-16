<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Swal from 'sweetalert2'
import { useAuthStore } from '@/stores/auth'
import { getSubscriptionPlans, formatPrice, type SubscriptionPlan } from '@/services/subscriptionPricing'
import {
  clearPendingPlanChange,
  createPendingPlanChange,
  submitPendingPlanPayment,
  updateUserSubscription,
  type PaymentMethod,
  type SubscriptionStatus,
} from '@/services/subscriptions'

type Role = 'company' | 'school'

const props = defineProps<{
  role: Role
}>()

const authStore = useAuthStore()
const loading = ref(true)
const savingPlanId = ref<string | null>(null)
const plans = ref<SubscriptionPlan[]>([])
const copied = ref(false)

const planRank = (id: string) => (id === 'free' ? 0 : id === 'standard' ? 1 : id === 'premium' ? 2 : -1)

const subscription = computed(() => authStore.user?.subscription)
const subscriptionPlanId = computed(() => (subscription.value?.plan || 'free').toLowerCase())
const subscriptionStatus = computed<SubscriptionStatus>(() => (subscription.value?.status || 'inactive') as SubscriptionStatus)
const billingCycle = computed(() => subscription.value?.billingCycle || 'monthly')
const schoolCode = computed(() => subscription.value?.subscriptionCode || '')
const pendingChange = computed(() => (subscription.value as any)?.pendingChange as
  | {
      requestId: string
      targetPlan: string
      amount: number
      currency: 'PHP'
      createdAt?: unknown
      payment?: { method?: PaymentMethod; receiptReference?: string; paidAtIso?: string; submittedAt?: unknown }
    }
  | null
  | undefined)

const currentPlan = computed(() => plans.value.find((p) => p.id === subscriptionPlanId.value) || null)
const pendingTargetPlan = computed(() => {
  const pc = pendingChange.value
  if (!pc) return null
  return plans.value.find((p) => p.id === pc.targetPlan) || null
})

const rolePrice = (plan: SubscriptionPlan) => (props.role === 'school' ? plan.schoolPrice : plan.companyPrice)
const roleFeatures = (plan: SubscriptionPlan) => (props.role === 'school' ? plan.features.school : plan.features.company)

const headerTitle = computed(() => (props.role === 'school' ? 'School Subscription' : 'Company Subscription'))
const accent = computed(() => (props.role === 'school' ? 'school' : 'company'))
const hasActiveSubscription = computed(() => subscriptionStatus.value === 'active')
const hasPendingChange = computed(() => !!pendingChange.value)

onMounted(async () => {
  try {
    plans.value = await getSubscriptionPlans()
  } finally {
    loading.value = false
  }
})

async function copySchoolCode() {
  if (!schoolCode.value) return
  try {
    await navigator.clipboard.writeText(schoolCode.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 1400)
  } catch {
    const el = document.createElement('textarea')
    el.value = schoolCode.value
    el.style.position = 'fixed'
    el.style.top = '0'
    el.style.left = '0'
    el.style.opacity = '0'
    document.body.appendChild(el)
    el.focus()
    el.select()
    try {
      document.execCommand('copy')
      copied.value = true
      setTimeout(() => {
        copied.value = false
      }, 1400)
    } finally {
      document.body.removeChild(el)
    }
  }
}

function actionLabel(targetId: string) {
  if (subscriptionStatus.value !== 'active') return `Buy ${targetId === 'free' ? 'Free' : targetId === 'standard' ? 'Standard' : 'Premium'}`
  const diff = planRank(targetId) - planRank(subscriptionPlanId.value)
  if (diff > 0) return `Upgrade to ${targetId === 'standard' ? 'Standard' : 'Premium'}`
  if (diff < 0) return `Downgrade to ${targetId === 'free' ? 'Free' : 'Standard'}`
  return 'Current Plan'
}

function requiresPayment(current: SubscriptionPlan | null, target: SubscriptionPlan) {
  const currentAmount = current ? rolePrice(current) : 0
  const targetAmount = rolePrice(target)
  if (targetAmount <= 0) return false
  if (subscriptionStatus.value !== 'active') return true
  return targetAmount > currentAmount
}

function planDeltaLabel(target: SubscriptionPlan) {
  if (!hasActiveSubscription.value) return 'Requires payment + verification'
  const diff = planRank(target.id) - planRank(subscriptionPlanId.value)
  if (diff > 0) return requiresPayment(currentPlan.value, target) ? 'Pay then submit receipt' : 'Upgrade'
  if (diff < 0) return 'Downgrade (no payment)'
  return 'You are on this plan'
}

function canSelectPlan(target: SubscriptionPlan) {
  if (savingPlanId.value) return false
  if (target.id === subscriptionPlanId.value) return false
  if (hasPendingChange.value && target.id !== 'free') return false
  return true
}

function disabledReason(target: SubscriptionPlan) {
  if (savingPlanId.value) return 'Processing your request…'
  if (target.id === subscriptionPlanId.value) return 'This is your current plan.'
  if (hasPendingChange.value && target.id !== 'free') return 'You have a pending plan change request. Submit a receipt or cancel the request.'
  return ''
}

function generateRequestId() {
  try {
    return `PR-${crypto.randomUUID().slice(0, 8).toUpperCase()}`
  } catch {
    return `PR-${Math.random().toString(16).slice(2, 10).toUpperCase()}`
  }
}

async function collectReceiptDetails(amount: number) {
  const result = await Swal.fire({
    icon: 'info',
    title: 'Submit payment receipt',
    html: `
      <div style="text-align:left;">
        <p style="margin:0 0 10px 0;">GCash reference/transaction IDs are generated <strong>after</strong> you pay. Paste the receipt reference once available.</p>
        <div style="display:grid; gap:10px;">
          <div>
            <label style="font-size:12px; font-weight:800; color:#334155;">Amount (PHP)</label>
            <input id="pay-amount" class="swal2-input" style="margin:6px 0 0 0;" value="${amount}" disabled />
          </div>
          <div>
            <label style="font-size:12px; font-weight:800; color:#334155;">Payment method</label>
            <select id="pay-method" class="swal2-select" style="margin:6px 0 0 0; width:100%;">
              <option value="gcash">GCash</option>
              <option value="maya">Maya</option>
              <option value="bank">Bank transfer</option>
              <option value="cash">Cash</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label style="font-size:12px; font-weight:800; color:#334155;">Receipt reference / Transaction ID</label>
            <input id="pay-ref" class="swal2-input" style="margin:6px 0 0 0;" placeholder="Paste the reference from your receipt" />
          </div>
          <div>
            <label style="font-size:12px; font-weight:800; color:#334155;">Payment date/time</label>
            <input id="pay-date" type="datetime-local" class="swal2-input" style="margin:6px 0 0 0;"/>
          </div>
          <label style="display:flex; gap:10px; align-items:center; margin-top:4px;">
            <input id="pay-confirm" type="checkbox" />
            <span style="font-size:13px; color:#475569;">I confirm the receipt details are correct.</span>
          </label>
          <div style="font-size:12px; color:#64748b;">After submitting, your plan will update immediately.</div>
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Submit receipt',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
    focusConfirm: false,
    preConfirm: () => {
      const method = (document.getElementById('pay-method') as HTMLSelectElement | null)?.value as PaymentMethod | undefined
      const receiptReference = (document.getElementById('pay-ref') as HTMLInputElement | null)?.value?.trim() || ''
      const paidAtIso = (document.getElementById('pay-date') as HTMLInputElement | null)?.value || ''
      const confirmed = (document.getElementById('pay-confirm') as HTMLInputElement | null)?.checked === true

      if (!confirmed) {
        Swal.showValidationMessage('Please confirm the receipt details.')
        return
      }
      if (!method) {
        Swal.showValidationMessage('Please select a payment method.')
        return
      }
      if (receiptReference.length < 4) {
        Swal.showValidationMessage('Please enter a valid receipt reference / transaction ID.')
        return
      }
      if (!paidAtIso) {
        Swal.showValidationMessage('Please select the payment date/time.')
        return
      }

      return { method, receiptReference, paidAtIso }
    },
  })

  return result.isConfirmed ? (result.value as { method: PaymentMethod; receiptReference: string; paidAtIso: string }) : null
}

async function setPlan(planId: string) {
  if (!authStore.user?.uid) return
  if (savingPlanId.value) return

  const target = plans.value.find((p) => p.id === planId)
  if (!target) return

  if (hasPendingChange.value && planId !== 'free') {
    await Swal.fire({
      icon: 'info',
      title: 'Pending verification',
      text: 'You already have a pending plan change. Submit your receipt or cancel the request.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  const targetLabel = target.name
  const priceText = `${formatPrice(rolePrice(target))}${rolePrice(target) === 0 ? '' : `/${billingCycle.value}`}`

  const result = await Swal.fire({
    icon: 'question',
    title: `Confirm ${targetLabel}`,
    html: `
      <div style="text-align:left;">
        <p><strong>Plan:</strong> ${targetLabel}</p>
        <p><strong>Price:</strong> ${priceText}</p>
        <p><strong>Next:</strong> ${requiresPayment(currentPlan.value, target) ? 'Pay first → pending verification' : 'Plan activates immediately'}</p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Confirm',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#2563eb',
  })
  if (!result.isConfirmed) return

  savingPlanId.value = planId
  try {
    const current = currentPlan.value
    if (requiresPayment(current, target)) {
      const requestId = generateRequestId()
      await createPendingPlanChange(authStore.user.uid, {
        requestId,
        targetPlan: planId,
        amount: rolePrice(target),
        currency: 'PHP',
      })

      const next = await Swal.fire({
        icon: 'success',
        title: 'Payment request created',
        html: `
          <div style="text-align:left;">
            <p style="margin:0 0 10px 0;">Request ID (generated before payment):</p>
            <div style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-weight:900; font-size:16px; color:#0f172a;">
              ${requestId}
            </div>
            <div style="margin-top:10px; font-size:13px; color:#475569;">
              Pay first, then submit the GCash receipt reference/transaction ID to activate your plan.
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Submit receipt now',
        cancelButtonText: 'I will do it later',
        confirmButtonColor: '#2563eb',
      })

      if (next.isConfirmed) {
        const receipt = await collectReceiptDetails(rolePrice(target))
        if (!receipt) return
        await submitPendingPlanPayment(authStore.user.uid, {
          requestId,
          method: receipt.method,
          receiptReference: receipt.receiptReference,
          paidAtIso: receipt.paidAtIso,
        })
        await updateUserSubscription(authStore.user.uid, { plan: planId, status: 'active' })
        await clearPendingPlanChange(authStore.user.uid).catch(() => {})
        await Swal.fire({
          icon: 'success',
          title: 'Plan activated',
          text: 'Payment receipt received. Your plan is now active.',
          confirmButtonColor: '#2563eb',
        })
      }
    } else {
      await updateUserSubscription(authStore.user.uid, { plan: planId, status: 'active' })
      await Swal.fire({
        icon: 'success',
        title: 'Subscription updated',
        text: `Your plan is now ${targetLabel}.`,
        confirmButtonColor: '#2563eb',
      })
    }
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Update failed',
      text: error instanceof Error ? error.message : 'Could not update your subscription.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}

async function cancelToFree() {
  if (!authStore.user?.uid) return
  if (savingPlanId.value) return

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel subscription?',
    text: 'This will move your account to the Free plan and clear any pending change requests.',
    showCancelButton: true,
    confirmButtonText: 'Cancel subscription',
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
      title: 'Moved to Free',
      text: 'Your subscription has been cancelled and moved to the Free plan.',
      confirmButtonColor: '#2563eb',
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Cancellation failed',
      text: error instanceof Error ? error.message : 'Could not cancel your subscription.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}

async function cancelPendingRequest() {
  if (!authStore.user?.uid) return
  if (!pendingChange.value) return
  if (savingPlanId.value) return

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Cancel pending request?',
    text: 'This will remove the pending plan change request. Your current plan will remain the same.',
    showCancelButton: true,
    confirmButtonText: 'Cancel request',
    cancelButtonText: 'Keep request',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return

  savingPlanId.value = 'pending-cancel'
  try {
    await clearPendingPlanChange(authStore.user.uid)
    await Swal.fire({
      icon: 'success',
      title: 'Request cancelled',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}

async function submitReceiptFromBanner() {
  if (!authStore.user?.uid) return
  const pc = pendingChange.value
  if (!pc) return
  if (savingPlanId.value) return

  const receipt = await collectReceiptDetails(pc.amount)
  if (!receipt) return

  savingPlanId.value = 'pending-receipt'
  try {
    await submitPendingPlanPayment(authStore.user.uid, {
      requestId: pc.requestId,
      method: receipt.method,
      receiptReference: receipt.receiptReference,
      paidAtIso: receipt.paidAtIso,
    })
    await updateUserSubscription(authStore.user.uid, { plan: pc.targetPlan, status: 'active' })
    await clearPendingPlanChange(authStore.user.uid).catch(() => {})
    await Swal.fire({
      icon: 'success',
      title: 'Plan activated',
      text: 'Payment receipt received. Your plan is now active.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}
</script>

<template>
  <div class="subscription-manager">
    <header class="sub-header" :class="`accent-${accent}`">
      <div class="sub-title">
        <div class="kicker">Billing</div>
        <h1>{{ headerTitle }}</h1>
        <p>Upgrade, downgrade, cancel, or buy again. Paid changes require payment first.</p>
      </div>
      <div class="sub-actions">
        <div class="pill">
          <span class="pill-label">Status</span>
          <span class="pill-value" :class="`badge-${subscriptionStatus}`">{{ subscriptionStatus }}</span>
        </div>
      </div>
    </header>

    <div v-if="loading" class="loading-card">
      <div class="skeleton-row">
        <div class="skeleton skeleton-pill"></div>
        <div class="skeleton skeleton-line"></div>
      </div>
      <div class="skeleton-grid">
        <div v-for="i in 3" :key="i" class="skeleton skeleton-card"></div>
      </div>
    </div>

    <div v-else class="sub-content">
      <section class="current-card">
        <div class="current-top">
          <div>
            <div class="current-label">Current</div>
            <div class="current-plan">{{ currentPlan?.name || 'Free' }}</div>
            <div class="current-meta">
              <span class="meta">Billing: {{ billingCycle }}</span>
            </div>
          </div>

          <button
            v-if="subscriptionPlanId !== 'free' || subscriptionStatus !== 'active'"
            class="danger-btn"
            type="button"
            :disabled="!!savingPlanId"
            @click="cancelToFree"
          >
            {{ savingPlanId ? 'Working…' : 'Cancel subscription' }}
          </button>
        </div>

        <div v-if="pendingChange" class="pending-banner">
          <div class="pending-title">Awaiting receipt</div>
          <div class="pending-text">
            Payment Request ID: <strong>{{ pendingChange.requestId }}</strong>
            <span v-if="pendingTargetPlan"> &middot; Plan: <strong>{{ pendingTargetPlan.name }}</strong></span>
            &middot; Amount: <strong>{{ formatPrice(pendingChange.amount) }}</strong>
            <div class="pending-hint">This is an internal tracking ID (not your GCash reference).</div>
          </div>
          <div class="pending-actions">
            <button class="primary-mini" type="button" :disabled="!!savingPlanId" @click="submitReceiptFromBanner">
              Submit receipt
            </button>
            <button class="ghost-mini danger" type="button" :disabled="!!savingPlanId" @click="cancelPendingRequest">
              Cancel request
            </button>
          </div>
        </div>

        <div v-if="role === 'school' && schoolCode" class="school-code">
          <div class="code-top">
            <div>
              <div class="code-label">School subscription code</div>
              <div class="code">{{ schoolCode }}</div>
              <div class="code-hint">Share this with students during registration.</div>
            </div>
            <button class="ghost-btn" type="button" @click="copySchoolCode">
              {{ copied ? 'Copied' : 'Copy' }}
            </button>
          </div>
        </div>
      </section>

      <section class="plans-grid">
        <article
          v-for="plan in plans"
          :key="plan.id"
          class="plan-card"
          :class="{
            active: plan.id === subscriptionPlanId,
            recommended: plan.id === 'standard' && plan.id !== subscriptionPlanId,
          }"
        >
          <div class="plan-head">
            <div class="plan-head-top">
              <div class="plan-name">{{ plan.name }}</div>
              <div class="plan-badges">
                <span v-if="plan.id === subscriptionPlanId" class="tag tag-current">Current</span>
                <span v-else-if="plan.id === 'standard'" class="tag tag-rec">Recommended</span>
                <span v-if="requiresPayment(currentPlan, plan)" class="tag tag-pay">Pay first</span>
              </div>
            </div>
            <div class="plan-price">
              {{ formatPrice(rolePrice(plan)) }}
              <span v-if="rolePrice(plan) !== 0" class="per">/{{ billingCycle }}</span>
            </div>
            <div class="plan-desc">{{ plan.description }}</div>
            <div class="plan-delta">{{ planDeltaLabel(plan) }}</div>
          </div>

          <ul class="plan-features">
            <li v-for="f in roleFeatures(plan)" :key="f">{{ f }}</li>
          </ul>

          <button
            class="primary-btn"
            type="button"
            :disabled="!canSelectPlan(plan)"
            :title="disabledReason(plan)"
            @click="setPlan(plan.id)"
          >
            <span class="btn-title">{{ savingPlanId === plan.id ? 'Updating…' : actionLabel(plan.id) }}</span>
            <span class="btn-sub">{{ requiresPayment(currentPlan, plan) ? 'Submit payment → pending verification' : hasActiveSubscription ? 'Applies immediately' : 'Requires verification' }}</span>
          </button>
        </article>
      </section>
    </div>
  </div>
</template>

<style scoped>
.subscription-manager {
  padding: 28px;
  background:
    radial-gradient(1200px 500px at 10% 0%, rgba(37, 99, 235, 0.09), transparent 60%),
    radial-gradient(900px 420px at 95% 5%, rgba(16, 185, 129, 0.09), transparent 55%),
    #f8fafc;
  min-height: calc(100vh - 40px);
}

.sub-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 18px;
  padding: 18px 18px 16px;
  border-radius: 16px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  background: rgba(255, 255, 255, 0.72);
  backdrop-filter: blur(6px);
}

.accent-school {
  box-shadow: 0 12px 30px rgba(37, 99, 235, 0.08);
}

.accent-company {
  box-shadow: 0 12px 30px rgba(16, 185, 129, 0.08);
}

.kicker {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.6px;
  text-transform: uppercase;
  color: #64748b;
  margin-bottom: 4px;
}

.sub-title h1 {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
}

.sub-title p {
  margin: 6px 0 0 0;
  color: #64748b;
  font-size: 14px;
}

.sub-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pill {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 999px;
  border: 1px solid rgba(148, 163, 184, 0.5);
  background: rgba(255, 255, 255, 0.9);
}

.pill-label {
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.pill-value {
  font-size: 12px;
  font-weight: 900;
  text-transform: capitalize;
}

.loading-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 18px;
  color: #475569;
}

.skeleton-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 14px;
}

.skeleton {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 37%, #f1f5f9 63%);
  background-size: 400% 100%;
  animation: shimmer 1.25s ease infinite;
  border-radius: 12px;
}

.skeleton-pill {
  width: 140px;
  height: 36px;
}

.skeleton-line {
  width: 260px;
  height: 16px;
  border-radius: 999px;
}

.skeleton-card {
  height: 360px;
}

@keyframes shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: 0 0;
  }
}

.current-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 18px 18px 14px;
  margin-bottom: 16px;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
}

.current-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.current-label {
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
  text-transform: uppercase;
}

.current-plan {
  font-size: 20px;
  font-weight: 900;
  color: #111827;
  margin-top: 4px;
}

.current-meta {
  display: flex;
  gap: 10px;
  align-items: center;
  margin-top: 10px;
  color: #64748b;
  font-size: 13px;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  text-transform: capitalize;
}

.badge-active {
  background: #dcfce7;
  color: #166534;
}

.badge-pending {
  background: #fef3c7;
  color: #92400e;
}

.badge-inactive {
  background: #e5e7eb;
  color: #374151;
}

.danger-btn {
  background: #dc2626;
  color: white;
  border: none;
  padding: 10px 14px;
  border-radius: 10px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 8px 18px rgba(220, 38, 38, 0.25);
}

.danger-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.school-code {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #e5e7eb;
}

.code-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.code-label {
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
  font-size: 18px;
  font-weight: 800;
  color: #0f172a;
  margin-top: 6px;
}

.code-hint {
  color: #64748b;
  margin-top: 6px;
  font-size: 13px;
}

.ghost-btn {
  background: rgba(148, 163, 184, 0.15);
  border: 1px solid rgba(148, 163, 184, 0.25);
  color: #0f172a;
  padding: 10px 12px;
  border-radius: 12px;
  font-weight: 900;
  cursor: pointer;
}

.ghost-btn:hover {
  background: rgba(148, 163, 184, 0.22);
}

.pending-banner {
  margin-top: 14px;
  padding: 12px 12px;
  border-radius: 12px;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
}

.pending-title {
  font-weight: 900;
  font-size: 13px;
}

.pending-text {
  margin-top: 4px;
  font-size: 13px;
  color: #92400e;
}

.pending-hint {
  margin-top: 6px;
  font-size: 12px;
  color: #a16207;
}

.pending-actions {
  margin-top: 10px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.primary-mini {
  background: #2563eb;
  color: white;
  border: none;
  padding: 10px 12px;
  border-radius: 12px;
  font-weight: 900;
  cursor: pointer;
  box-shadow: 0 10px 22px rgba(37, 99, 235, 0.18);
}

.primary-mini:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  box-shadow: none;
}

.ghost-mini {
  background: rgba(148, 163, 184, 0.12);
  color: #0f172a;
  border: 1px solid rgba(148, 163, 184, 0.25);
  padding: 10px 12px;
  border-radius: 12px;
  font-weight: 900;
  cursor: pointer;
}

.ghost-mini:hover {
  background: rgba(148, 163, 184, 0.18);
}

.ghost-mini.danger {
  background: rgba(220, 38, 38, 0.1);
  border-color: rgba(220, 38, 38, 0.18);
  color: #991b1b;
}

.ghost-mini.danger:hover {
  background: rgba(220, 38, 38, 0.14);
}

.plans-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 14px;
}

.plan-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  min-height: 340px;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.plan-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
  border-color: #cbd5e1;
}

.plan-card.active {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.plan-card.recommended {
  border-color: rgba(37, 99, 235, 0.45);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.06);
}

.plan-head {
  padding-bottom: 10px;
  border-bottom: 1px solid #eef2f7;
  margin-bottom: 12px;
}

.plan-head-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.plan-name {
  font-size: 16px;
  font-weight: 900;
  color: #0f172a;
}

.plan-badges {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.tag {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 900;
  border: 1px solid transparent;
  white-space: nowrap;
}

.tag-current {
  background: #dcfce7;
  color: #166534;
}

.tag-rec {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: rgba(37, 99, 235, 0.22);
}

.tag-pay {
  background: #fff7ed;
  color: #9a3412;
  border-color: rgba(249, 115, 22, 0.25);
}

.plan-price {
  margin-top: 6px;
  font-size: 22px;
  font-weight: 800;
  color: #111827;
}

.per {
  font-size: 13px;
  font-weight: 700;
  color: #64748b;
  margin-left: 6px;
}

.plan-desc {
  margin-top: 8px;
  color: #64748b;
  font-size: 13px;
  line-height: 1.45;
}

.plan-delta {
  margin-top: 10px;
  color: #475569;
  font-size: 12px;
  font-weight: 800;
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 12px 0 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.plan-features li {
  color: #374151;
  font-size: 13px;
  padding-left: 18px;
  position: relative;
}

.plan-features li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: #16a34a;
  font-weight: 800;
}

.primary-btn {
  width: 100%;
  background: #2563eb;
  color: white;
  border: none;
  padding: 12px 14px;
  border-radius: 10px;
  font-weight: 900;
  cursor: pointer;
  box-shadow: 0 10px 22px rgba(37, 99, 235, 0.22);
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: center;
}

.primary-btn:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
  box-shadow: none;
}

.btn-title {
  font-size: 14px;
  font-weight: 950;
  line-height: 1.2;
}

.btn-sub {
  font-size: 12px;
  font-weight: 700;
  opacity: 0.92;
}

@media (max-width: 768px) {
  .subscription-manager {
    padding: 18px;
  }

  .skeleton-line {
    width: 160px;
  }
}
</style>

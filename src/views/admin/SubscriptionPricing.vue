<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Swal from '@/services/swal'
import {
  AcademicCapIcon,
  BuildingOfficeIcon,
  ClipboardDocumentListIcon,
  PlusIcon,
  SparklesIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'

import {
  formatPrice,
  getSubscriptionPlans,
  updateSubscriptionPlan,
  type SubscriptionPlan,
} from '@/services/subscriptionPricing'

type PlanSideEditor = {
  price: number
  features: string[]
  limits: {
    first: number
    second: number
  }
}

type PlanEditor = {
  id: string
  name: string
  description: string
  school: PlanSideEditor
  company: PlanSideEditor
}

const loading = ref(false)
const savingPlanId = ref<string | null>(null)
const originalPlans = ref<SubscriptionPlan[]>([])
const planEditors = ref<PlanEditor[]>([])

const planCountLabel = computed(() => `${planEditors.value.length} plans ready to edit`)

onMounted(() => {
  loadPlans()
})

async function loadPlans() {
  loading.value = true
  try {
    const plans = await getSubscriptionPlans()
    originalPlans.value = plans
    planEditors.value = plans.map(createEditor)
  } catch (error) {
    console.error('Failed to load subscription plans:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Loading Failed',
      text: 'Unable to load subscription plans right now.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    loading.value = false
  }
}

function createEditor(plan: SubscriptionPlan): PlanEditor {
  return {
    id: plan.id,
    name: plan.name,
    description: plan.description,
    school: {
      price: plan.schoolPrice,
      features: [...plan.features.school],
      limits: {
        first: plan.limits.school.coordinators,
        second: plan.limits.school.students,
      },
    },
    company: {
      price: plan.companyPrice,
      features: [...plan.features.company],
      limits: {
        first: plan.limits.company.accounts,
        second: plan.limits.company.internships,
      },
    },
  }
}

function resetPlan(planId: string) {
  const original = originalPlans.value.find((plan) => plan.id === planId)
  const index = planEditors.value.findIndex((plan) => plan.id === planId)

  if (!original || index === -1) {
    return
  }

  planEditors.value[index] = createEditor(original)
}

function addFeature(plan: PlanEditor, side: 'school' | 'company') {
  plan[side].features.push('')
}

function removeFeature(plan: PlanEditor, side: 'school' | 'company', index: number) {
  if (plan[side].features.length === 1) {
    plan[side].features[0] = ''
    return
  }

  plan[side].features.splice(index, 1)
}

function cleanFeatureList(features: string[]) {
  return features.map((feature) => feature.trim()).filter(Boolean)
}

function duplicateFeatures(plan: PlanEditor, from: 'school' | 'company', to: 'school' | 'company') {
  plan[to].features = [...plan[from].features]
}

function applyUnlimited(plan: PlanEditor, side: 'school' | 'company') {
  plan[side].limits.first = 999
  plan[side].limits.second = 999
}

function applyStarter(plan: PlanEditor, side: 'school' | 'company') {
  if (side === 'school') {
    plan.school.limits.first = 1
    plan.school.limits.second = 20
    return
  }

  plan.company.limits.first = 1
  plan.company.limits.second = 5
}

function formatPreview(price: number) {
  return formatPrice(price)
}

function featureCountLabel(features: string[]) {
  return `${cleanFeatureList(features).length} features`
}

async function savePlan(plan: PlanEditor) {
  const schoolFeatures = cleanFeatureList(plan.school.features)
  const companyFeatures = cleanFeatureList(plan.company.features)

  if (!plan.description.trim()) {
    await Swal.fire({
      icon: 'error',
      title: 'Description Needed',
      text: 'Please add a short description for this plan.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  if (
    plan.school.price < 0 ||
    plan.company.price < 0 ||
    plan.school.limits.first < 0 ||
    plan.school.limits.second < 0 ||
    plan.company.limits.first < 0 ||
    plan.company.limits.second < 0
  ) {
    await Swal.fire({
      icon: 'error',
      title: 'Invalid Values',
      text: 'Prices and limits must be zero or higher.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  if (schoolFeatures.length === 0 || companyFeatures.length === 0) {
    await Swal.fire({
      icon: 'error',
      title: 'Missing Features',
      text: 'Add at least one feature for both school and company before saving.',
      confirmButtonColor: '#2563eb',
    })
    return
  }

  savingPlanId.value = plan.id
  try {
    await updateSubscriptionPlan(plan.id, {
      description: plan.description.trim(),
      schoolPrice: plan.school.price,
      companyPrice: plan.company.price,
      schoolFeatures,
      companyFeatures,
      limits: {
        school: {
          coordinators: plan.school.limits.first,
          students: plan.school.limits.second,
        },
        company: {
          accounts: plan.company.limits.first,
          internships: plan.company.limits.second,
        },
      },
    })

    const updatedPlans = await getSubscriptionPlans()
    originalPlans.value = updatedPlans
    planEditors.value = updatedPlans.map(createEditor)

    await Swal.fire({
      icon: 'success',
      title: 'Plan Updated',
      text: `${plan.name} was saved successfully.`,
      confirmButtonColor: '#2563eb',
      timer: 1600,
      showConfirmButton: false,
    })
  } catch (error) {
    console.error('Failed to save plan:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Save Failed',
      text: 'The subscription plan could not be updated. Please try again.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    savingPlanId.value = null
  }
}
</script>

<template>
  <section class="subscription-editor">
    <header class="page-header">
      <div class="header-copy">
        <p class="eyebrow">Admin Controls</p>
        <h1>Subscription Plans</h1>
        <p class="subtitle">
          Edit prices, limits, and features with quick actions so you do less typing and fewer repetitive updates.
        </p>
      </div>

      <div class="header-actions">
        <span class="plan-count">{{ planCountLabel }}</span>
        <button class="refresh-button" type="button" :disabled="loading" @click="loadPlans">
          {{ loading ? 'Refreshing...' : 'Refresh' }}
        </button>
      </div>
    </header>

    <section class="tip-banner">
      <SparklesIcon class="tip-icon" />
      <div>
        <strong>Less typing, faster edits.</strong>
        <p>Use Add Feature, Copy Features, Starter Limits, and Unlimited to update plans with just a few clicks.</p>
      </div>
    </section>

    <div v-if="loading && !planEditors.length" class="empty-state">
      Loading subscription plans...
    </div>

    <div v-else class="plan-grid">
      <article v-for="plan in planEditors" :key="plan.id" class="plan-card">
        <div class="plan-top">
          <div>
            <p class="plan-id">{{ plan.id }}</p>
            <h2>{{ plan.name }}</h2>
            <p class="plan-preview">
              School: {{ formatPreview(plan.school.price) }} | Company: {{ formatPreview(plan.company.price) }}
            </p>
          </div>

          <div class="card-actions top-actions">
            <button class="secondary-button" type="button" @click="resetPlan(plan.id)">Reset</button>
            <button
              class="primary-button"
              type="button"
              :disabled="savingPlanId === plan.id"
              @click="savePlan(plan)"
            >
              {{ savingPlanId === plan.id ? 'Saving...' : 'Save Plan' }}
            </button>
          </div>
        </div>

        <label class="field-group">
          <span class="field-label">Short Description</span>
          <input
            v-model="plan.description"
            class="field-input"
            type="text"
            placeholder="Example: Ideal for growing organizations"
          />
        </label>

        <div class="editor-columns">
          <section class="editor-panel school-panel">
            <div class="panel-header">
              <div class="panel-title">
                <AcademicCapIcon class="panel-icon school" />
                <span>School Plan</span>
              </div>
              <span class="feature-counter">{{ featureCountLabel(plan.school.features) }}</span>
            </div>

            <label class="field-group">
              <span class="field-label">Price</span>
              <div class="price-input-wrap">
                <span class="prefix">PHP</span>
                <input v-model.number="plan.school.price" class="field-input price-input" type="number" min="0" />
              </div>
            </label>

            <div class="limits-box">
              <div class="limits-header">
                <span class="field-label">Quick Limits</span>
                <div class="mini-actions">
                  <button class="mini-button" type="button" @click="applyStarter(plan, 'school')">Starter Limits</button>
                  <button class="mini-button" type="button" @click="applyUnlimited(plan, 'school')">Unlimited</button>
                </div>
              </div>

              <div class="limit-grid">
                <label class="field-group">
                  <span class="field-label">Coordinators</span>
                  <input v-model.number="plan.school.limits.first" class="field-input" type="number" min="0" />
                </label>
                <label class="field-group">
                  <span class="field-label">Students</span>
                  <input v-model.number="plan.school.limits.second" class="field-input" type="number" min="0" />
                </label>
              </div>
            </div>

            <div class="features-box">
              <div class="features-header">
                <span class="field-label">School Features</span>
                <div class="mini-actions">
                  <button class="mini-button" type="button" @click="duplicateFeatures(plan, 'company', 'school')">
                    Copy Company Features
                  </button>
                  <button class="mini-button primary-mini" type="button" @click="addFeature(plan, 'school')">
                    <PlusIcon class="mini-icon" />
                    Add Feature
                  </button>
                </div>
              </div>

              <div class="feature-list">
                <div v-for="(feature, index) in plan.school.features" :key="`school-${plan.id}-${index}`" class="feature-row">
                  <input
                    v-model="plan.school.features[index]"
                    class="field-input feature-input"
                    type="text"
                    :placeholder="`School feature ${index + 1}`"
                  />
                  <button class="icon-button" type="button" @click="removeFeature(plan, 'school', index)">
                    <TrashIcon class="trash-icon" />
                  </button>
                </div>
              </div>
            </div>
          </section>

          <section class="editor-panel company-panel">
            <div class="panel-header">
              <div class="panel-title">
                <BuildingOfficeIcon class="panel-icon company" />
                <span>Company Plan</span>
              </div>
              <span class="feature-counter">{{ featureCountLabel(plan.company.features) }}</span>
            </div>

            <label class="field-group">
              <span class="field-label">Price</span>
              <div class="price-input-wrap">
                <span class="prefix">PHP</span>
                <input v-model.number="plan.company.price" class="field-input price-input" type="number" min="0" />
              </div>
            </label>

            <div class="limits-box">
              <div class="limits-header">
                <span class="field-label">Quick Limits</span>
                <div class="mini-actions">
                  <button class="mini-button" type="button" @click="applyStarter(plan, 'company')">Starter Limits</button>
                  <button class="mini-button" type="button" @click="applyUnlimited(plan, 'company')">Unlimited</button>
                </div>
              </div>

              <div class="limit-grid">
                <label class="field-group">
                  <span class="field-label">Accounts</span>
                  <input v-model.number="plan.company.limits.first" class="field-input" type="number" min="0" />
                </label>
                <label class="field-group">
                  <span class="field-label">Internships</span>
                  <input v-model.number="plan.company.limits.second" class="field-input" type="number" min="0" />
                </label>
              </div>
            </div>

            <div class="features-box">
              <div class="features-header">
                <span class="field-label">Company Features</span>
                <div class="mini-actions">
                  <button class="mini-button" type="button" @click="duplicateFeatures(plan, 'school', 'company')">
                    Copy School Features
                  </button>
                  <button class="mini-button primary-mini" type="button" @click="addFeature(plan, 'company')">
                    <PlusIcon class="mini-icon" />
                    Add Feature
                  </button>
                </div>
              </div>

              <div class="feature-list">
                <div v-for="(feature, index) in plan.company.features" :key="`company-${plan.id}-${index}`" class="feature-row">
                  <input
                    v-model="plan.company.features[index]"
                    class="field-input feature-input"
                    type="text"
                    :placeholder="`Company feature ${index + 1}`"
                  />
                  <button class="icon-button" type="button" @click="removeFeature(plan, 'company', index)">
                    <TrashIcon class="trash-icon" />
                  </button>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="footer-note">
          <ClipboardDocumentListIcon class="footer-icon" />
          <span>Tip: set a limit to `999` when you want it to behave like unlimited access.</span>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.subscription-editor {
  padding: 24px;
  max-width: 1440px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 20px;
}

.header-copy {
  max-width: 800px;
}

.eyebrow {
  margin: 0 0 8px;
  color: #2563eb;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.page-header h1 {
  margin: 0;
  font-size: 32px;
  color: #0f172a;
}

.subtitle {
  margin: 10px 0 0;
  color: #475569;
  line-height: 1.6;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.plan-count {
  padding: 10px 14px;
  border-radius: 999px;
  background: #dbeafe;
  color: #1e3a8a;
  font-weight: 700;
  white-space: nowrap;
}

.tip-banner {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  padding: 18px 20px;
  border-radius: 18px;
  background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
  border: 1px solid #bfdbfe;
  margin-bottom: 24px;
}

.tip-banner p {
  margin: 4px 0 0;
  color: #475569;
}

.tip-icon {
  width: 22px;
  height: 22px;
  color: #2563eb;
  flex-shrink: 0;
}

.empty-state {
  padding: 48px 24px;
  text-align: center;
  border-radius: 18px;
  background: #f8fafc;
  color: #475569;
}

.plan-grid {
  display: grid;
  gap: 24px;
}

.plan-card {
  padding: 24px;
  border-radius: 24px;
  border: 1px solid #dbeafe;
  background:
    radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 25%),
    linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
}

.plan-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 18px;
}

.plan-id {
  margin: 0 0 8px;
  color: #2563eb;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.plan-top h2 {
  margin: 0;
  color: #0f172a;
  font-size: 28px;
}

.plan-preview {
  margin: 8px 0 0;
  color: #475569;
  font-weight: 600;
}

.editor-columns {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  margin-top: 18px;
}

.editor-panel {
  padding: 18px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid #dbeafe;
}

.panel-header,
.features-header,
.limits-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.panel-header {
  margin-bottom: 16px;
}

.panel-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 800;
  color: #0f172a;
  min-height: 24px;
}

.panel-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  display: block;
}

.panel-icon.school {
  color: #2563eb;
}

.panel-icon.company {
  color: #059669;
}

.feature-counter {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 32px;
  padding: 6px 10px;
  border-radius: 999px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 12px;
  font-weight: 700;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.field-label {
  color: #334155;
  font-size: 14px;
  font-weight: 700;
}

.field-input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 14px;
  padding: 12px 14px;
  font: inherit;
  color: #0f172a;
  background: #fff;
}

.field-input:focus {
  outline: 2px solid rgba(37, 99, 235, 0.18);
  border-color: #2563eb;
}

.price-input-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
}

.prefix {
  padding: 12px 14px;
  border-radius: 14px;
  background: #eff6ff;
  color: #1d4ed8;
  font-weight: 800;
}

.price-input {
  flex: 1;
}

.limits-box,
.features-box {
  padding: 16px;
  border-radius: 18px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  margin-bottom: 16px;
}

.limits-header,
.features-header {
  margin-bottom: 14px;
}

.limit-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.mini-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.refresh-button,
.secondary-button,
.primary-button,
.mini-button,
.icon-button {
  border: none;
  cursor: pointer;
  transition: transform 0.2s ease, opacity 0.2s ease, background 0.2s ease;
}

.refresh-button,
.secondary-button {
  padding: 12px 16px;
  border-radius: 12px;
  background: #e2e8f0;
  color: #0f172a;
  font-weight: 700;
}

.primary-button {
  padding: 12px 18px;
  border-radius: 12px;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: #fff;
  font-weight: 800;
  box-shadow: 0 12px 22px rgba(37, 99, 235, 0.2);
}

.mini-button {
  padding: 8px 10px;
  border-radius: 10px;
  background: #e2e8f0;
  color: #0f172a;
  font-size: 12px;
  font-weight: 700;
}

.primary-mini {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #dbeafe;
  color: #1d4ed8;
}

.mini-icon {
  width: 14px;
  height: 14px;
}

.icon-button {
  width: 44px;
  min-width: 44px;
  height: 44px;
  border-radius: 12px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #fee2e2;
  color: #dc2626;
}

.trash-icon {
  width: 18px;
  height: 18px;
}

.refresh-button:hover:not(:disabled),
.secondary-button:hover,
.primary-button:hover:not(:disabled),
.mini-button:hover,
.icon-button:hover {
  transform: translateY(-1px);
}

.refresh-button:disabled,
.primary-button:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.feature-list {
  display: grid;
  gap: 10px;
}

.feature-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.feature-input {
  padding-leftt: 2rem;
  flex: 1;
}

.feature-row .icon-button {
  margin-left: auto;
}

.card-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.footer-note {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
  color: #475569;
  font-size: 14px;
}

.footer-icon {
  width: 18px;
  height: 18px;
  color: #2563eb;
  flex-shrink: 0;
}

@media (max-width: 960px) {
  .page-header,
  .plan-top,
  .panel-header,
  .features-header,
  .limits-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions,
  .top-actions,
  .editor-columns,
  .limit-grid {
    width: 100%;
  }

  .editor-columns,
  .limit-grid {
    grid-template-columns: 1fr;
  }

  .header-actions > *,
  .top-actions > * {
    flex: 1;
  }

  .feature-row {
    align-items: stretch;
  }
}
</style>

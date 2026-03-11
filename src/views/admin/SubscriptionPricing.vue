<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Swal from 'sweetalert2'
import { 
  CurrencyDollarIcon,
  BuildingOfficeIcon,
  AcademicCapIcon,
  PencilIcon,
  CheckIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import { getSubscriptionPlans, updatePlanPricing, updatePlanLimits, formatPrice, type SubscriptionPlan } from '@/services/subscriptionPricing'

const plans = ref<SubscriptionPlan[]>([])
const editingPlan = ref<string | null>(null)
const editingField = ref<string | null>(null)
const tempValue = ref<number>(0)
const loading = ref(false)

// Editing states for limits
const editingLimit = ref<{ planId: string; field: string } | null>(null)
const tempLimitValue = ref<number>(0)

onMounted(() => {
  loadPricing()
})

async function loadPricing() {
  loading.value = true
  try {
    plans.value = await getSubscriptionPlans()
  } catch (error) {
    console.error('Failed to load pricing:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Loading Failed',
      text: 'Failed to load subscription pricing. Please try again.',
      confirmButtonColor: '#2563eb'
    })
  } finally {
    loading.value = false
  }
}

function startEdit(planId: string, field: 'schoolPrice' | 'companyPrice') {
  editingPlan.value = planId
  editingField.value = field
  const plan = plans.value.find(p => p.id === planId)
  if (plan) {
    tempValue.value = plan[field]
  }
}

function cancelEdit() {
  editingPlan.value = null
  editingField.value = null
  tempValue.value = 0
  editingLimit.value = null
  tempLimitValue.value = 0
}

async function savePrice(planId: string, field: 'schoolPrice' | 'companyPrice') {
  if (tempValue.value < 0) {
    await Swal.fire({
      icon: 'error',
      title: 'Invalid Price',
      text: 'Price cannot be negative.',
      confirmButtonColor: '#2563eb'
    })
    return
  }

  try {
    loading.value = true
    
    // Update via service
    await updatePlanPricing(planId, { [field]: tempValue.value })
    
    // Update local state
    const plan = plans.value.find(p => p.id === planId)
    if (plan) {
      plan[field] = tempValue.value
    }
    
    cancelEdit()
    
    await Swal.fire({
      icon: 'success',
      title: 'Price Updated',
      text: 'Subscription price has been updated successfully.',
      confirmButtonColor: '#2563eb',
      timer: 2000,
      showConfirmButton: false
    })
    
  } catch (error) {
    console.error('Failed to update price:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Update Failed',
      text: 'Failed to update subscription price. Please try again.',
      confirmButtonColor: '#2563eb'
    })
  } finally {
    loading.value = false
  }
}

function formatPriceDisplay(price: number): string {
  return formatPrice(price)
}

// Limit editing functions
function startEditLimit(planId: string, field: string, currentValue: number) {
  editingLimit.value = { planId, field }
  tempLimitValue.value = currentValue
}

function cancelEditLimit() {
  editingLimit.value = null
  tempLimitValue.value = 0
}

async function saveLimit(planId: string, field: string) {
  if (tempLimitValue.value < 0) {
    await Swal.fire({
      icon: 'error',
      title: 'Invalid Limit',
      text: 'Limit cannot be negative.',
      confirmButtonColor: '#2563eb'
    })
    return
  }

  try {
    loading.value = true
    
    // Map field names to service parameters
    const updates: any = {}
    switch (field) {
      case 'schoolCoordinators':
        updates.schoolCoordinators = tempLimitValue.value
        break
      case 'schoolStudents':
        updates.schoolStudents = tempLimitValue.value
        break
      case 'companyAccounts':
        updates.companyAccounts = tempLimitValue.value
        break
      case 'companyInternships':
        updates.companyInternships = tempLimitValue.value
        break
    }
    
    // Update via service
    await updatePlanLimits(planId, updates)
    
    // Update local state
    const plan = plans.value.find(p => p.id === planId)
    if (plan) {
      switch (field) {
        case 'schoolCoordinators':
          plan.limits.school.coordinators = tempLimitValue.value
          break
        case 'schoolStudents':
          plan.limits.school.students = tempLimitValue.value
          break
        case 'companyAccounts':
          plan.limits.company.accounts = tempLimitValue.value
          break
        case 'companyInternships':
          plan.limits.company.internships = tempLimitValue.value
          break
      }
    }
    
    cancelEditLimit()
    
    await Swal.fire({
      icon: 'success',
      title: 'Limit Updated',
      text: 'Plan limit has been updated successfully.',
      confirmButtonColor: '#2563eb',
      timer: 2000,
      showConfirmButton: false
    })
    
  } catch (error) {
    console.error('Failed to update limit:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Update Failed',
      text: 'Failed to update plan limit. Please try again.',
      confirmButtonColor: '#2563eb'
    })
  } finally {
    loading.value = false
  }
}

function formatLimit(value: number): string {
  return value === 999 ? 'Unlimited' : value.toString()
}
</script>

<template>
  <div class="subscription-pricing">
    <div class="pricing-header">
      <div class="header-content">
        <div class="header-info">
          <CurrencyDollarIcon class="header-icon" />
          <div>
            <h1 class="header-title">Subscription Pricing</h1>
            <p class="header-subtitle">Manage subscription plan pricing for schools and companies</p>
          </div>
        </div>
        <button @click="loadPricing" class="refresh-btn" :disabled="loading">
          <svg class="refresh-icon" :class="{ 'spinning': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Refresh
        </button>
      </div>
    </div>

    <div class="pricing-content">
      <div class="plans-grid">
        <div v-for="plan in plans" :key="plan.id" class="plan-card" :class="{ 'featured': plan.id === 'standard' }">
          <!-- Plan Header -->
          <div class="plan-header">
            <div class="plan-badge" v-if="plan.id === 'standard'">Most Popular</div>
            <h3 class="plan-name">{{ plan.name }}</h3>
            <p class="plan-description">{{ plan.description }}</p>
          </div>

          <!-- Pricing Section -->
          <div class="pricing-section">
            <!-- School Pricing -->
            <div class="price-row">
              <div class="price-label">
                <AcademicCapIcon class="price-icon school" />
                <span>School Price</span>
              </div>
              <div class="price-value">
                <div v-if="editingPlan === plan.id && editingField === 'schoolPrice'" class="price-edit">
                  <div class="edit-input-group">
                    <span class="currency-symbol">₱</span>
                    <input 
                      v-model.number="tempValue" 
                      type="number" 
                      min="0" 
                      class="price-input"
                      @keyup.enter="savePrice(plan.id, 'schoolPrice')"
                      @keyup.escape="cancelEdit"
                    />
                  </div>
                  <div class="edit-actions">
                    <button @click="savePrice(plan.id, 'schoolPrice')" class="save-btn">
                      <CheckIcon class="btn-icon" />
                    </button>
                    <button @click="cancelEdit" class="cancel-btn">
                      <XMarkIcon class="btn-icon" />
                    </button>
                  </div>
                </div>
                <div v-else class="price-display">
                  <span class="price-text">{{ formatPriceDisplay(plan.schoolPrice) }}</span>
                  <button @click="startEdit(plan.id, 'schoolPrice')" class="edit-btn">
                    <PencilIcon class="edit-icon" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Company Pricing -->
            <div class="price-row">
              <div class="price-label">
                <BuildingOfficeIcon class="price-icon company" />
                <span>Company Price</span>
              </div>
              <div class="price-value">
                <div v-if="editingPlan === plan.id && editingField === 'companyPrice'" class="price-edit">
                  <div class="edit-input-group">
                    <span class="currency-symbol">₱</span>
                    <input 
                      v-model.number="tempValue" 
                      type="number" 
                      min="0" 
                      class="price-input"
                      @keyup.enter="savePrice(plan.id, 'companyPrice')"
                      @keyup.escape="cancelEdit"
                    />
                  </div>
                  <div class="edit-actions">
                    <button @click="savePrice(plan.id, 'companyPrice')" class="save-btn">
                      <CheckIcon class="btn-icon" />
                    </button>
                    <button @click="cancelEdit" class="cancel-btn">
                      <XMarkIcon class="btn-icon" />
                    </button>
                  </div>
                </div>
                <div v-else class="price-display">
                  <span class="price-text">{{ formatPriceDisplay(plan.companyPrice) }}</span>
                  <button @click="startEdit(plan.id, 'companyPrice')" class="edit-btn">
                    <PencilIcon class="edit-icon" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Plan Limits -->
          <div class="limits-section">
            <h4 class="limits-title">Plan Limits</h4>
            <div class="limits-grid">
              <!-- School Limits -->
              <div class="limit-item">
                <AcademicCapIcon class="limit-icon school" />
                <div class="limit-details">
                  <span class="limit-label">School</span>
                  <div class="limit-values">
                    <!-- Coordinators -->
                    <div class="limit-value-row">
                      <span class="limit-type">Coordinators:</span>
                      <div v-if="editingLimit?.planId === plan.id && editingLimit?.field === 'schoolCoordinators'" class="limit-edit">
                        <input 
                          v-model.number="tempLimitValue" 
                          type="number" 
                          min="0" 
                          max="999"
                          class="limit-input"
                          @keyup.enter="saveLimit(plan.id, 'schoolCoordinators')"
                          @keyup.escape="cancelEditLimit"
                        />
                        <div class="limit-edit-actions">
                          <button @click="saveLimit(plan.id, 'schoolCoordinators')" class="save-btn">
                            <CheckIcon class="btn-icon" />
                          </button>
                          <button @click="cancelEditLimit" class="cancel-btn">
                            <XMarkIcon class="btn-icon" />
                          </button>
                        </div>
                      </div>
                      <div v-else class="limit-display">
                        <span class="limit-text">{{ formatLimit(plan.limits.school.coordinators) }}</span>
                        <button @click="startEditLimit(plan.id, 'schoolCoordinators', plan.limits.school.coordinators)" class="edit-limit-btn">
                          <PencilIcon class="edit-icon" />
                        </button>
                      </div>
                    </div>
                    
                    <!-- Students -->
                    <div class="limit-value-row">
                      <span class="limit-type">Students:</span>
                      <div v-if="editingLimit?.planId === plan.id && editingLimit?.field === 'schoolStudents'" class="limit-edit">
                        <input 
                          v-model.number="tempLimitValue" 
                          type="number" 
                          min="0" 
                          max="999"
                          class="limit-input"
                          @keyup.enter="saveLimit(plan.id, 'schoolStudents')"
                          @keyup.escape="cancelEditLimit"
                        />
                        <div class="limit-edit-actions">
                          <button @click="saveLimit(plan.id, 'schoolStudents')" class="save-btn">
                            <CheckIcon class="btn-icon" />
                          </button>
                          <button @click="cancelEditLimit" class="cancel-btn">
                            <XMarkIcon class="btn-icon" />
                          </button>
                        </div>
                      </div>
                      <div v-else class="limit-display">
                        <span class="limit-text">{{ formatLimit(plan.limits.school.students) }}</span>
                        <button @click="startEditLimit(plan.id, 'schoolStudents', plan.limits.school.students)" class="edit-limit-btn">
                          <PencilIcon class="edit-icon" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Company Limits -->
              <div class="limit-item">
                <BuildingOfficeIcon class="limit-icon company" />
                <div class="limit-details">
                  <span class="limit-label">Company</span>
                  <div class="limit-values">
                    <!-- Accounts -->
                    <div class="limit-value-row">
                      <span class="limit-type">Accounts:</span>
                      <div v-if="editingLimit?.planId === plan.id && editingLimit?.field === 'companyAccounts'" class="limit-edit">
                        <input 
                          v-model.number="tempLimitValue" 
                          type="number" 
                          min="0" 
                          max="999"
                          class="limit-input"
                          @keyup.enter="saveLimit(plan.id, 'companyAccounts')"
                          @keyup.escape="cancelEditLimit"
                        />
                        <div class="limit-edit-actions">
                          <button @click="saveLimit(plan.id, 'companyAccounts')" class="save-btn">
                            <CheckIcon class="btn-icon" />
                          </button>
                          <button @click="cancelEditLimit" class="cancel-btn">
                            <XMarkIcon class="btn-icon" />
                          </button>
                        </div>
                      </div>
                      <div v-else class="limit-display">
                        <span class="limit-text">{{ formatLimit(plan.limits.company.accounts) }}</span>
                        <button @click="startEditLimit(plan.id, 'companyAccounts', plan.limits.company.accounts)" class="edit-limit-btn">
                          <PencilIcon class="edit-icon" />
                        </button>
                      </div>
                    </div>
                    
                    <!-- Internships -->
                    <div class="limit-value-row">
                      <span class="limit-type">Internships:</span>
                      <div v-if="editingLimit?.planId === plan.id && editingLimit?.field === 'companyInternships'" class="limit-edit">
                        <input 
                          v-model.number="tempLimitValue" 
                          type="number" 
                          min="0" 
                          max="999"
                          class="limit-input"
                          @keyup.enter="saveLimit(plan.id, 'companyInternships')"
                          @keyup.escape="cancelEditLimit"
                        />
                        <div class="limit-edit-actions">
                          <button @click="saveLimit(plan.id, 'companyInternships')" class="save-btn">
                            <CheckIcon class="btn-icon" />
                          </button>
                          <button @click="cancelEditLimit" class="cancel-btn">
                            <XMarkIcon class="btn-icon" />
                          </button>
                        </div>
                      </div>
                      <div v-else class="limit-display">
                        <span class="limit-text">{{ formatLimit(plan.limits.company.internships) }}</span>
                        <button @click="startEditLimit(plan.id, 'companyInternships', plan.limits.company.internships)" class="edit-limit-btn">
                          <PencilIcon class="edit-icon" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Info Card -->
      <div class="info-card">
        <h3 class="info-title">Pricing & Limits Management</h3>
        <p class="info-text">
          Click the edit icon next to any price or limit to modify it. Changes will be reflected immediately on the guest registration page and subscription selection.
        </p>
        <div class="info-notes">
          <div class="note-item">
            <span class="note-label">Pricing:</span>
            <span class="note-text">Set different prices for schools and companies</span>
          </div>
          <div class="note-item">
            <span class="note-label">Limits:</span>
            <span class="note-text">Control account and resource limits for each plan</span>
          </div>
          <div class="note-item">
            <span class="note-label">Unlimited:</span>
            <span class="note-text">Set limit to 999 for unlimited access</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.subscription-pricing {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

.pricing-header {
  margin-bottom: 32px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.header-info {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  width: 32px;
  height: 32px;
  color: #2563eb;
}

.header-title {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.header-subtitle {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
}

.refresh-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.refresh-btn:hover:not(:disabled) {
  background: #1d4ed8;
  transform: translateY(-1px);
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.refresh-icon {
  width: 16px;
  height: 16px;
}

.refresh-icon.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.pricing-content {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.plans-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 24px;
}

.plan-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
  position: relative;
  transition: all 0.2s ease;
}

.plan-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.plan-card.featured {
  border: 2px solid #2563eb;
  box-shadow: 0 8px 24px rgba(37, 99, 235, 0.15);
}

.plan-header {
  text-align: center;
  margin-bottom: 24px;
  position: relative;
  padding-top: 16px;
}

.plan-badge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: white;
  padding: 4px 16px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.plan-name {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 20px 0 8px 0;
}

.plan-description {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.pricing-section {
  margin-bottom: 24px;
}

.price-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid #f3f4f6;
}

.price-row:last-child {
  border-bottom: none;
}

.price-label {
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 600;
  color: #374151;
}

.price-icon {
  width: 20px;
  height: 20px;
}

.price-icon.school {
  color: #2563eb;
}

.price-icon.company {
  color: #059669;
}

.price-value {
  display: flex;
  align-items: center;
}

.price-display {
  display: flex;
  align-items: center;
  gap: 8px;
}

.price-text {
  font-size: 18px;
  font-weight: 700;
  color: #1f2937;
  min-width: 100px;
  text-align: right;
}

.edit-btn {
  padding: 4px;
  background: transparent;
  border: none;
  color: #6b7280;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.edit-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

.edit-icon {
  width: 16px;
  height: 16px;
}

.price-edit {
  display: flex;
  align-items: center;
  gap: 8px;
}

.edit-input-group {
  display: flex;
  align-items: center;
  background: white;
  border: 2px solid #2563eb;
  border-radius: 6px;
  padding: 0 8px;
}

.currency-symbol {
  color: #6b7280;
  font-weight: 600;
}

.price-input {
  border: none;
  outline: none;
  padding: 8px 4px;
  width: 100px;
  font-size: 16px;
  font-weight: 600;
}

.edit-actions {
  display: flex;
  gap: 4px;
}

.save-btn, .cancel-btn {
  padding: 6px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.save-btn {
  background: #10b981;
  color: white;
}

.save-btn:hover {
  background: #059669;
}

.cancel-btn {
  background: #ef4444;
  color: white;
}

.cancel-btn:hover {
  background: #dc2626;
}

.btn-icon {
  width: 14px;
  height: 14px;
}

.limits-section {
  border-top: 1px solid #f3f4f6;
  padding-top: 16px;
}

.limits-title {
  font-size: 16px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 12px 0;
}

.limits-grid {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.limit-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.limit-icon {
  width: 20px;
  height: 20px;
  margin-top: 2px;
  flex-shrink: 0;
}

.limit-icon.school {
  color: #2563eb;
}

.limit-icon.company {
  color: #059669;
}

.limit-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.limit-label {
  font-size: 16px;
  font-weight: 600;
  color: #374151;
}

.limit-values {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.limit-value-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #e5e7eb;
}

.limit-value-row:last-child {
  border-bottom: none;
}

.limit-type {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
  min-width: 100px;
}

.limit-display {
  display: flex;
  align-items: center;
  gap: 8px;
}

.limit-text {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  min-width: 80px;
  text-align: right;
}

.edit-limit-btn {
  padding: 4px;
  background: transparent;
  border: none;
  color: #6b7280;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.edit-limit-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

.limit-edit {
  display: flex;
  align-items: center;
  gap: 8px;
}

.limit-input {
  width: 80px;
  padding: 6px 8px;
  border: 2px solid #2563eb;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  outline: none;
}

.limit-edit-actions {
  display: flex;
  gap: 4px;
}

.info-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  border: 1px solid #e5e7eb;
}

.info-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 12px 0;
}

.info-text {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0 0 16px 0;
}

.info-notes {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.note-item {
  display: flex;
  gap: 8px;
}

.note-label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  min-width: 100px;
}

.note-text {
  font-size: 14px;
  color: #6b7280;
}

/* Responsive Design */
@media (max-width: 768px) {
  .subscription-pricing {
    padding: 16px;
  }
  
  .plans-grid {
    grid-template-columns: 1fr;
  }
  
  .header-content {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .price-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .price-text {
    text-align: left;
    min-width: auto;
  }
}
</style>
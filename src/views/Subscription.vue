<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Swal from 'sweetalert2'
import { 
  ArrowLeftIcon,
  CheckIcon,
  BuildingOfficeIcon,
  AcademicCapIcon
} from '@heroicons/vue/24/outline'
import { getPricingForRole } from '@/services/subscriptionPricing'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const selectedRole = ref<string | null>(null)
const selectedPlan = ref<'Free' | 'Standard' | 'Premium' | null>(null)
const currentPlans = ref<any>({})
const loading = ref(false)

// Get role from route params or query
onMounted(async () => {
  selectedRole.value = (route.params.role as string) || (route.query.role as string) || null
  if (!selectedRole.value) {
    router.push('/guest')
    return
  }
  
  // Load dynamic pricing
  await loadPricing()
})

async function loadPricing() {
  if (!selectedRole.value) return
  
  loading.value = true
  try {
    currentPlans.value = await getPricingForRole(selectedRole.value as 'school' | 'company')
  } catch (error) {
    console.error('Failed to load pricing:', error)
    // Fallback to default pricing if API fails
    currentPlans.value = getDefaultPlans()
  } finally {
    loading.value = false
  }
}

function getDefaultPlans() {
  if (selectedRole.value === 'school') {
    return {
      'Free': { 
        price: '₱0',
        description: 'Perfect for small schools getting started',
        features: [
          '1 School coordinator account',
          'Up to 5 student accounts',
          'Basic student management',
          'Simple reporting dashboard',
          'Email support',
          'Basic internship matching'
        ],
        limits: { coordinators: 1, students: 5 }
      },
      'Standard': { 
        price: '₱1,999',
        description: 'Ideal for growing educational institutions',
        features: [
          '5 School coordinator accounts',
          'Up to 100 student accounts',
          'Advanced student analytics',
          'Bulk student management',
          'Priority email & chat support',
          'Custom reports & exports',
          'Advanced matching algorithms'
        ],
        limits: { coordinators: 5, students: 100 }
      },
      'Premium': { 
        price: '₱3,999',
        description: 'Complete solution for large institutions',
        features: [
          'Unlimited coordinator accounts',
          'Unlimited student accounts',
          'Advanced analytics dashboard',
          'Custom integrations & API access',
          'Dedicated account manager',
          'White-label options',
          'Priority phone support'
        ],
        limits: { coordinators: 999, students: 999 }
      }
    }
  } else {
    return {
      'Free': { 
        price: '₱0',
        description: 'Great for startups and small businesses',
        features: [
          '1 Company account',
          'Post up to 3 internships',
          'Basic applicant management',
          'Simple reporting dashboard',
          'Email support',
          'Basic candidate matching'
        ],
        limits: { accounts: 1, internships: 3 }
      },
      'Standard': { 
        price: '₱2,499',
        description: 'Perfect for growing companies',
        features: [
          '5 Company accounts',
          'Unlimited internship postings',
          'Advanced applicant tracking',
          'Analytics dashboard',
          'Priority email & chat support',
          'Integration support',
          'Advanced filtering & search'
        ],
        limits: { accounts: 5, internships: 999 }
      },
      'Premium': { 
        price: '₱4,999',
        description: 'Enterprise solution for large organizations',
        features: [
          'Unlimited company accounts',
          'Unlimited internship postings',
          'Advanced analytics & reporting',
          'Custom integrations & API access',
          'Dedicated account manager',
          'Custom branding options',
          'Priority phone support'
        ],
        limits: { accounts: 999, internships: 999 }
      }
    }
  }
}

const userName = computed(() => {
  return authStore.user?.displayName || authStore.user?.email?.split('@')[0] || 'Guest'
})

// Get current plans based on selected role
const plansArray = computed(() => {
  return Object.entries(currentPlans.value).map(([name, plan]: [string, any]) => ({
    name,
    price: plan.price,
    description: plan.description,
    features: plan.features,
    limits: plan.limits
  }))
})

const roleInfo = computed(() => {
  if (selectedRole.value === 'school') {
    return {
      title: 'School Plans',
      subtitle: 'Choose the perfect plan for your educational institution',
      icon: AcademicCapIcon,
      color: 'text-blue-600'
    }
  } else {
    return {
      title: 'Company Plans',
      subtitle: 'Select the ideal plan for your organization',
      icon: BuildingOfficeIcon,
      color: 'text-green-600'
    }
  }
})

function goBack() {
  router.push('/guest')
}

async function selectPlan(planName: string, price: string) {
  selectedPlan.value = planName as 'Free' | 'Standard' | 'Premium'
  
  const result = await Swal.fire({
    icon: 'success',
    title: `${planName} Plan Selected`,
    html: `
      <div style="text-align: left;">
        <p><strong>Plan:</strong> ${planName}</p>
        <p><strong>Price:</strong> ${price}/month</p>
        <p><strong>Role:</strong> ${selectedRole.value === 'company' ? 'Company' : 'School'}</p>
        <br>
        <p>Ready to configure your account setup?</p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Continue Setup',
    cancelButtonText: 'Change Plan',
    confirmButtonColor: '#2563eb',
    cancelButtonColor: '#6b7280'
  })
  
  if (result.isConfirmed) {
    // Navigate to account setup with selected role and plan
    router.push({
      path: '/guest',
      query: {
        role: selectedRole.value,
        plan: planName,
        step: 'setup'
      }
    })
  } else {
    selectedPlan.value = null
  }
}
</script>

<template>
  <div class="subscription-page">
    <!-- Header -->
    <header class="subscription-header">
      <div class="header-content">
        <button class="back-btn" @click="goBack">
          <ArrowLeftIcon class="back-icon" />
          Back
        </button>
        <div class="header-info">
          <h1 class="header-title">Choose Your Plan</h1>
          <p class="header-subtitle">Welcome, {{ userName }}!</p>
        </div>
        <div class="header-spacer"></div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="subscription-main">
      <!-- Role Info Section -->
      <div class="role-info-section">
        <div class="role-info-card">
          <div class="role-icon-wrapper">
            <component :is="roleInfo.icon" class="role-icon" :class="roleInfo.color" />
          </div>
          <div class="role-text">
            <h2 class="role-title">{{ roleInfo.title }}</h2>
            <p class="role-subtitle">{{ roleInfo.subtitle }}</p>
          </div>
        </div>
      </div>

      <!-- Pricing Plans -->
      <div class="pricing-section" v-if="!loading">
        <div class="pricing-grid">
          <div 
            v-for="plan in plansArray" 
            :key="plan.name"
            class="pricing-card" 
            :class="{ 
              'selected': selectedPlan === plan.name,
              'featured': plan.name === 'Standard'
            }"
          >
            <!-- Popular Badge -->
            <div v-if="plan.name === 'Standard'" class="popular-badge">
              Most Popular
            </div>
            
            <!-- Selected Badge -->
            <div v-if="selectedPlan === plan.name" class="selected-badge">
              <CheckIcon class="check-icon" />
              Selected
            </div>
            
            <!-- Plan Header -->
            <div class="plan-header">
              <h3 class="plan-name">{{ plan.name }}</h3>
              <div class="plan-price">
                {{ plan.price }}
                <span class="period" v-if="plan.price !== '₱0'">/month</span>
              </div>
              <p class="plan-description">{{ plan.description }}</p>
            </div>
            
            <!-- Plan Features -->
            <div class="plan-features">
              <ul class="features-list">
                <li v-for="feature in plan.features" :key="feature" class="feature-item">
                  <CheckIcon class="feature-check" />
                  <span>{{ feature }}</span>
                </li>
              </ul>
            </div>
            
            <!-- Plan Button -->
            <div class="plan-footer">
              <button 
                class="plan-btn" 
                :class="{ 
                  'selected-btn': selectedPlan === plan.name,
                  'featured-btn': plan.name === 'Standard' && selectedPlan !== plan.name
                }" 
                @click="selectPlan(plan.name, plan.price)"
              >
                {{ selectedPlan === plan.name ? 'Selected' : 'Choose Plan' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="loading-section">
        <div class="loading-spinner"></div>
        <p class="loading-text">Loading subscription plans...</p>
      </div>

      <!-- Additional Info -->
      <div class="info-section">
        <div class="info-card">
          <h3 class="info-title">Need Help Choosing?</h3>
          <p class="info-text">
            Not sure which plan is right for you? Our team is here to help you find the perfect solution for your {{ selectedRole === 'company' ? 'company' : 'institution' }}.
          </p>
          <button class="contact-btn">Contact Sales</button>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
.subscription-page {
  min-height: 100vh;
  background:
    radial-gradient(circle at bottom left, #9ec5ff 0 30%, transparent 31%),
    radial-gradient(circle at top right, #9ec5ff 0 25%, transparent 26%), #f5f7fb;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.subscription-header {
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid #e5e7eb;
  padding: 16px 0;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.header-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.back-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: white;
  color: #6b7280;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.back-btn:hover {
  background: #f9fafb;
  border-color: #d1d5db;
  color: #374151;
}

.back-icon {
  width: 16px;
  height: 16px;
}

.header-info {
  text-align: center;
  flex: 1;
}

.header-title {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.header-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.header-spacer {
  width: 100px; /* Same width as back button to center the title */
}

.subscription-main {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 32px 80px;
}

.role-info-section {
  margin-bottom: 48px;
}

.role-info-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  display: flex;
  align-items: center;
  gap: 24px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.role-icon-wrapper {
  width: 64px;
  height: 64px;
  background: rgba(37, 99, 235, 0.1);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.role-icon {
  width: 32px;
  height: 32px;
}

.role-text {
  flex: 1;
}

.role-title {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.role-subtitle {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
}

.pricing-section {
  margin-bottom: 48px;
}

.pricing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 24px;
}

.pricing-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
  position: relative;
  display: flex;
  flex-direction: column;
}

.pricing-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px rgba(37, 99, 235, 0.15);
  border-color: #2563eb;
}

.pricing-card.featured {
  border: 2px solid #2563eb;
  transform: scale(1.05);
}

.pricing-card.featured:hover {
  transform: scale(1.05) translateY(-8px);
}

.pricing-card.selected {
  border: 2px solid #10b981;
  box-shadow: 0 20px 40px rgba(16, 185, 129, 0.2);
}

.popular-badge {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: white;
  padding: 6px 20px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
}

.selected-badge {
  position: absolute;
  top: -12px;
  right: 20px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
  display: flex;
  align-items: center;
  gap: 4px;
}

.check-icon {
  width: 14px;
  height: 14px;
}

.plan-header {
  text-align: center;
  margin-bottom: 32px;
  position: relative;
  padding-top: 16px;
}

.plan-name {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 20px 0 16px 0;
}

.plan-price {
  font-size: 48px;
  font-weight: 800;
  color: #2563eb;
  margin: 0 0 16px 0;
  line-height: 1;
}

.period {
  font-size: 16px;
  font-weight: 400;
  color: #6b7280;
}

.plan-description {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

.plan-features {
  flex: 1;
  margin-bottom: 32px;
}

.features-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 0;
  font-size: 15px;
  color: #374151;
  line-height: 1.5;
}

.feature-check {
  width: 20px;
  height: 20px;
  color: #10b981;
  flex-shrink: 0;
  margin-top: 2px;
}

.plan-footer {
  margin-top: auto;
}

.plan-btn {
  width: 100%;
  padding: 16px 24px;
  background: white;
  color: #2563eb;
  border: 2px solid #2563eb;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.plan-btn:hover {
  background: #2563eb;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
}

.featured-btn {
  background: #2563eb;
  color: white;
}

.featured-btn:hover {
  background: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
}

.selected-btn {
  background: #10b981 !important;
  color: white !important;
  border-color: #10b981 !important;
  cursor: default;
}

.selected-btn:hover {
  background: #10b981 !important;
  transform: none !important;
  box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3) !important;
}

.info-section {
  display: flex;
  justify-content: center;
}

.info-card {
  background: white;
  border-radius: 12px;
  padding: 40px;
  text-align: center;
  max-width: 600px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.info-title {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 16px 0;
}

.info-text {
  font-size: 16px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0 0 24px 0;
}

.contact-btn {
  padding: 12px 32px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.contact-btn:hover {
  background: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.loading-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #e5e7eb;
  border-top: 3px solid #2563eb;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 16px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.loading-text {
  font-size: 16px;
  color: #6b7280;
  margin: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
  .subscription-main {
    padding: 24px 20px 60px;
  }
  
  .header-content {
    padding: 0 20px;
  }
  
  .role-info-card {
    flex-direction: column;
    text-align: center;
    padding: 24px;
  }
  
  .role-title {
    font-size: 24px;
  }
  
  .pricing-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  
  .pricing-card.featured {
    transform: scale(1);
  }
  
  .pricing-card.featured:hover {
    transform: translateY(-8px);
  }
  
  .plan-price {
    font-size: 36px;
  }
  
  .info-card {
    padding: 24px;
  }
  
  .header-spacer {
    display: none;
  }
  
  .header-info {
    text-align: left;
  }
}
</style>
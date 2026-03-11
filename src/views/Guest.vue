<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Swal from 'sweetalert2'
import { 
  BriefcaseIcon, 
  BuildingOfficeIcon,
  ArrowRightIcon
} from '@heroicons/vue/24/outline'
import { getSubscriptionPlans, type SubscriptionPlan } from '@/services/subscriptionPricing'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const userName = computed(() => {
  return authStore.user?.displayName || authStore.user?.email?.split('@')[0] || 'Guest'
})

// State for subscription and role selection flow
const selectedRole = ref<string | null>(null)
const selectedPlan = ref<'Free' | 'Standard' | 'Premium' | null>(null)
const showAccountDistribution = ref(false)
const subscriptionPlans = ref<SubscriptionPlan[]>([])

// Check if returning from subscription page
onMounted(async () => {
  // Load dynamic pricing
  try {
    subscriptionPlans.value = await getSubscriptionPlans()
  } catch (error) {
    console.error('Failed to load subscription plans:', error)
  }
  
  const queryRole = route.query.role as string
  const queryPlan = route.query.plan as string
  const queryStep = route.query.step as string
  
  if (queryRole && queryPlan && queryStep === 'setup') {
    selectedRole.value = queryRole
    selectedPlan.value = queryPlan as 'Free' | 'Standard' | 'Premium'
    showAccountDistribution.value = true
    
    // Initialize department distribution
    departmentDistribution.value = [
      { department: '', accounts: 0 }
    ]
    
    // Scroll to account distribution section with enhanced visibility
    setTimeout(() => {
      const section = document.querySelector('.account-distribution-section')
      if (section) {
        // First scroll to top to ensure clean transition
        window.scrollTo({ top: 0, behavior: 'smooth' })
        
        // Then scroll to the account distribution section after a brief delay
        setTimeout(() => {
          section.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start',
            inline: 'nearest'
          })
          
          // Add a subtle highlight effect to draw attention
          section.classList.add('highlight-section')
          setTimeout(() => {
            section.classList.remove('highlight-section')
          }, 3000)
        }, 500)
      }
    }, 100)
  }
})

// Account limits based on role and plan
const planLimits = computed(() => {
  if (!selectedRole.value || !selectedPlan.value || subscriptionPlans.value.length === 0) {
    return { company: 0, school: 0, students: 0 }
  }
  
  const plan = subscriptionPlans.value.find(p => p.name === selectedPlan.value)
  if (!plan) return { company: 0, school: 0, students: 0 }
  
  if (selectedRole.value === 'school') {
    return {
      company: 0,
      school: plan.limits.school.coordinators,
      students: plan.limits.school.students
    }
  } else {
    return {
      company: plan.limits.company.accounts,
      school: 0,
      students: 0
    }
  }
})

// Department distribution state
const departmentDistribution = ref<Array<{ department: string; accounts: number }>>([
  { department: '', accounts: 0 }
])

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

function goToProfile() {
  router.push('/profile')
}

async function selectRole(role: string) {
  selectedRole.value = role
  
  await Swal.fire({
    icon: 'success',
    title: `${role === 'company' ? 'Company' : 'School'} Role Selected`,
    html: `
      <p>You've selected the <strong>${role === 'company' ? 'Company' : 'School'}</strong> role.</p>
      <p>You'll now be taken to our subscription plans page to choose the perfect plan for your needs.</p>
    `,
    confirmButtonText: 'Continue to Plans',
    confirmButtonColor: '#2563eb'
  })
  
  // Navigate to subscription page with selected role
  router.push(`/subscription/${role}`)
}

function addDepartment() {
  // Check if all current departments have names
  const hasEmptyDepartment = departmentDistribution.value.some(dept => !dept.department.trim())
  
  if (hasEmptyDepartment) {
    Swal.fire({
      icon: 'warning',
      title: 'Please Complete Current Fields',
      text: selectedRole.value === 'school' 
        ? 'Please fill in all department names before adding a new one.'
        : 'Please fill in all role/position names before adding a new one.',
      confirmButtonColor: '#2563eb'
    })
    return
  }
  
  departmentDistribution.value.push({ department: '', accounts: 0 })
}

function removeDepartment(index: number) {
  if (departmentDistribution.value.length > 1) {
    departmentDistribution.value.splice(index, 1)
  }
}

const totalAllocatedAccounts = computed(() => {
  return departmentDistribution.value.reduce((sum, dept) => sum + (dept.accounts || 0), 0)
})

const maxStudentAccounts = computed(() => {
  if (!selectedRole.value || !selectedPlan.value || subscriptionPlans.value.length === 0) return 0
  if (selectedRole.value === 'school') {
    const plan = subscriptionPlans.value.find(p => p.name === selectedPlan.value)
    return plan ? plan.limits.school.students : 0
  }
  return 0
})

const remainingAccounts = computed(() => {
  return maxStudentAccounts.value - totalAllocatedAccounts.value
})

// Check for duplicate department names
function isDuplicateDepartment(index: number): boolean {
  const dept = departmentDistribution.value[index]
  if (!dept) return false
  
  const currentDept = dept.department.trim().toLowerCase()
  if (!currentDept) return false
  
  return departmentDistribution.value.some((dept, i) => 
    i !== index && dept.department.trim().toLowerCase() === currentDept
  )
}

// Check if all current departments/roles have names before allowing new ones
const canAddNewDepartment = computed(() => {
  // Check if all existing departments have names
  const allHaveNames = departmentDistribution.value.every(dept => dept.department.trim().length > 0)
  
  // Check if we haven't exceeded the limit
  const withinLimit = selectedPlan.value && selectedRole.value ? 
    (selectedRole.value === 'school' ? 
      (planLimits.value.school === 999 || departmentDistribution.value.length < planLimits.value.school) :
      (planLimits.value.company === 999 || departmentDistribution.value.length < planLimits.value.company)
    ) : false
  
  return allHaveNames && withinLimit
})

async function submitAccountDistribution() {
  // Validation
  if (selectedRole.value === 'school') {
    const hasEmptyDepartment = departmentDistribution.value.some(d => !d.department.trim())
    if (hasEmptyDepartment) {
      await Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'Please fill in all department names.',
        confirmButtonColor: '#2563eb'
      })
      return
    }
    
    // Check for duplicate department names
    const departmentNames = departmentDistribution.value.map(d => d.department.trim().toLowerCase())
    const hasDuplicates = departmentNames.some((name, index) => departmentNames.indexOf(name) !== index)
    
    if (hasDuplicates) {
      await Swal.fire({
        icon: 'error',
        title: 'Duplicate Department',
        text: 'Each department name must be unique. Please remove or rename duplicate departments.',
        confirmButtonColor: '#2563eb'
      })
      return
    }
    
    // Check coordinator account limit
    const maxSchoolAccounts = selectedPlan.value ? planLimits.value.school : 0
    if (maxSchoolAccounts !== 999 && departmentDistribution.value.length > maxSchoolAccounts) {
      await Swal.fire({
        icon: 'error',
        title: 'Coordinator Limit Exceeded',
        text: `You can only create up to ${maxSchoolAccounts} coordinator accounts with the ${selectedPlan.value} plan.`,
        confirmButtonColor: '#2563eb'
      })
      return
    }
    
    if (totalAllocatedAccounts.value > maxStudentAccounts.value) {
      await Swal.fire({
        icon: 'error',
        title: 'Student Account Limit Exceeded',
        text: `You can only allocate up to ${maxStudentAccounts.value} student accounts with the ${selectedPlan.value} plan.`,
        confirmButtonColor: '#2563eb'
      })
      return
    }
  }
  
  if (selectedRole.value === 'company') {
    const hasEmptyDepartment = departmentDistribution.value.some(d => !d.department.trim())
    if (hasEmptyDepartment) {
      await Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'Please fill in all role/position names.',
        confirmButtonColor: '#2563eb'
      })
      return
    }
    
    // Check for duplicate department names
    const departmentNames = departmentDistribution.value.map(d => d.department.trim().toLowerCase())
    const hasDuplicates = departmentNames.some((name, index) => departmentNames.indexOf(name) !== index)
    
    if (hasDuplicates) {
      await Swal.fire({
        icon: 'error',
        title: 'Duplicate Role/Position',
        text: 'Each role/position must be unique. Please remove or rename duplicates.',
        confirmButtonColor: '#2563eb'
      })
      return
    }
    
    const maxCompanyAccounts = selectedPlan.value ? planLimits.value.company : 0
    if (maxCompanyAccounts !== 999 && departmentDistribution.value.length > maxCompanyAccounts) {
      await Swal.fire({
        icon: 'error',
        title: 'Account Limit Exceeded',
        text: `You can only create up to ${maxCompanyAccounts} company accounts with the ${selectedPlan.value} plan.`,
        confirmButtonColor: '#2563eb'
      })
      return
    }
  }
  
  const departmentSummary = selectedRole.value === 'school'
    ? departmentDistribution.value.map(d => `<li><strong>${d.department}:</strong> 1 coordinator + ${d.accounts} students</li>`).join('')
    : departmentDistribution.value.map(d => `<li><strong>${d.department}:</strong> 1 account</li>`).join('')
  
  await Swal.fire({
    icon: 'success',
    title: 'Setup Complete!',
    html: `
      <p><strong>Plan:</strong> ${selectedPlan.value}</p>
      <p><strong>Role:</strong> ${selectedRole.value === 'company' ? 'Company' : 'School'}</p>
      ${selectedRole.value === 'school' ? `
        <p><strong>Departments:</strong> ${departmentDistribution.value.length}</p>
        <p><strong>Total Coordinator Accounts:</strong> ${departmentDistribution.value.length}</p>
        <p><strong>Total Student Accounts:</strong> ${totalAllocatedAccounts.value}</p>
        <div style="text-align: left; margin-top: 16px;">
          <strong>Department Breakdown:</strong>
          <ul style="margin-top: 8px;">${departmentSummary}</ul>
        </div>
      ` : ''}
      ${selectedRole.value === 'company' ? `
        <p><strong>Total Accounts:</strong> ${departmentDistribution.value.length}</p>
        <div style="text-align: left; margin-top: 16px;">
          <strong>Role/Position Breakdown:</strong>
          <ul style="margin-top: 8px;">${departmentSummary}</ul>
        </div>
      ` : ''}
      <p style="margin-top: 16px;">Please contact your administrator to activate your account.</p>
    `,
    confirmButtonColor: '#2563eb',
    width: '600px'
  })
}
</script>

<template>
  <div class="guest-page">
    <!-- Header -->
    <header class="guest-header">
      <div class="header-content">
        <div class="logo-section">
          <img src="/icons/logo-main.png" alt="OJT Intern Path" class="logo" />
          <span class="logo-text">OJT Intern Path</span>
        </div>
        <div class="nav-actions">
          <button class="logout-btn" @click="handleLogout">Logout</button>
          <button class="profile-btn" @click="goToProfile" title="Profile">
            <svg class="profile-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="guest-main">
      <!-- Welcome Section -->
      <div class="welcome-section">
        <h1 class="welcome-title">Welcome, {{ userName }}! 👋</h1>
        <p class="welcome-subtitle">Your account has been successfully created.</p>
      </div>

      <!-- Info Card -->
      <div class="info-card">
        <div class="info-icon">
          <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="info-content">
          <h3 class="info-title">Guest Mode Active</h3>
          <p class="info-text">
            You're currently in guest mode with limited access. To unlock full features, please contact your administrator to assign you a role and activate a subscription plan.
          </p>
        </div>
      </div>

      <!-- Available Roles Section -->
      <section class="content-section">
        <h2 class="section-title">Choose Your Role</h2>
        <p class="section-subtitle">
          First, select your role to see relevant subscription plans
        </p>
        
        <div class="roles-grid">
          <!-- Company Role -->
          <div 
            class="role-card" 
            :class="{ 'selected': selectedRole === 'company' }"
            @click="selectRole('company')"
          >
            <div class="role-icon company">
              <BuildingOfficeIcon class="icon" />
            </div>
            <h3 class="role-title">Company</h3>
            <p class="role-description">
              Post internship opportunities and manage your intern programs.
            </p>
            <ul class="role-features">
              <li>Post internship positions</li>
              <li>Review student applications</li>
              <li>Manage interns</li>
              <li>Track performance</li>
            </ul>
            <div v-if="selectedRole === 'company'" class="role-selected-badge">
              <span class="selected-text">✓ Selected</span>
            </div>
          </div>

          <!-- School Role -->
          <div 
            class="role-card"
            :class="{ 'selected': selectedRole === 'school' }"
            @click="selectRole('school')"
          >
            <div class="role-icon school">
              <BriefcaseIcon class="icon" />
            </div>
            <h3 class="role-title">School</h3>
            <p class="role-description">
              Oversee student placements and monitor internship programs.
            </p>
            <ul class="role-features">
              <li>Manage student applications</li>
              <li>Partner with companies</li>
              <li>Monitor placements</li>
              <li>Generate reports</li>
            </ul>
            <div v-if="selectedRole === 'school'" class="role-selected-badge">
              <span class="selected-text">✓ Selected</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Subscription Plans Section (shown after role selection) -->
      <section v-if="showAccountDistribution && selectedRole && selectedPlan" class="content-section subscription-plans-section">
        <h2 class="section-title">
          Setup Complete - {{ selectedRole === 'company' ? 'Company' : 'School' }} {{ selectedPlan }} Plan
        </h2>
        <p class="section-subtitle">
          Configure your account distribution below to get started
        </p>
        
        <div class="selected-plan-info">
          <div class="plan-info-card">
            <div class="plan-info-header">
              <h3 class="plan-info-title">Selected Plan: {{ selectedPlan }}</h3>
              <span class="plan-info-badge">{{ selectedRole === 'company' ? 'Company' : 'School' }}</span>
            </div>
            <p class="plan-info-text">
              You can now configure your account distribution based on your selected plan limits.
            </p>
          </div>
        </div>
      </section>

      <!-- Account Distribution Section (shown after role selection) -->
      <section v-if="showAccountDistribution && selectedRole === 'school' && selectedPlan" class="content-section account-distribution-section">
        <!-- Progress Indicator -->
        <div class="progress-indicator">
          <div class="progress-step completed">
            <div class="step-number">1</div>
            <span class="step-label">Role Selected</span>
          </div>
          <div class="progress-line completed"></div>
          <div class="progress-step completed">
            <div class="step-number">2</div>
            <span class="step-label">Plan Selected</span>
          </div>
          <div class="progress-line active"></div>
          <div class="progress-step active">
            <div class="step-number">3</div>
            <span class="step-label">Account Setup</span>
          </div>
        </div>

        <!-- Success Banner -->
        <div class="success-banner">
          <div class="banner-icon">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="banner-content">
            <h3 class="banner-title">Great! {{ selectedPlan }} Plan Selected</h3>
            <p class="banner-text">Now configure your {{ selectedRole === 'school' ? 'department' : 'role' }} distribution below to complete your setup.</p>
          </div>
        </div>

        <h2 class="section-title">Account Distribution</h2>
        <p class="section-subtitle">Create department coordinator accounts and allocate students (1 coordinator per department)</p>
        
        <div class="distribution-card">
          <div class="distribution-header">
            <div class="distribution-info">
              <h3 class="distribution-title">Department Setup</h3>
              <p class="distribution-subtitle">Each department gets 1 coordinator account with assigned students</p>
            </div>
            <div class="account-summary">
              <div class="summary-item">
                <span class="summary-label">Coordinators Available:</span>
                <span class="summary-value">{{ planLimits.school === 999 ? 'Unlimited' : planLimits.school }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Departments Created:</span>
                <span class="summary-value allocated">{{ departmentDistribution.length }}</span>
              </div>
              <div class="summary-item" v-if="planLimits.school !== 999">
                <span class="summary-label">Remaining:</span>
                <span class="summary-value" :class="{ 'warning': departmentDistribution.length > planLimits.school }">
                  {{ planLimits.school - departmentDistribution.length }}
                </span>
              </div>
            </div>
          </div>

          <div class="account-summary" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
            <div class="summary-item">
              <span class="summary-label">Total Students Available:</span>
              <span class="summary-value">{{ maxStudentAccounts === 999 ? 'Unlimited' : maxStudentAccounts }}</span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Students Allocated:</span>
              <span class="summary-value allocated">{{ totalAllocatedAccounts }}</span>
            </div>
            <div class="summary-item" v-if="maxStudentAccounts !== 999">
              <span class="summary-label">Students Remaining:</span>
              <span class="summary-value" :class="{ 'warning': remainingAccounts < 0 }">{{ remainingAccounts }}</span>
            </div>
          </div>

          <div class="department-list">
            <div v-for="(dept, index) in departmentDistribution" :key="index" class="department-item">
              <div class="department-input-group">
                <div class="input-with-label">
                  <label class="input-label">Department Name</label>
                  <input 
                    v-model="dept.department" 
                    type="text" 
                    placeholder="e.g., Computer Science, Engineering" 
                    class="department-input"
                    :class="{ 'error': isDuplicateDepartment(index) }"
                  />
                  <span v-if="isDuplicateDepartment(index)" class="error-message">
                    This department name already exists
                  </span>
                </div>
                <div class="input-with-label">
                  <label class="input-label">Students Assigned</label>
                  <input 
                    v-model.number="dept.accounts" 
                    type="number" 
                    min="0" 
                    :max="maxStudentAccounts === 999 ? 9999 : maxStudentAccounts"
                    placeholder="# of students" 
                    class="accounts-input"
                  />
                </div>
                <button 
                  v-if="departmentDistribution.length > 1"
                  @click="removeDepartment(index)" 
                  class="remove-dept-btn"
                  title="Remove department"
                >
                  ×
                </button>
              </div>
              <div class="department-note">
                <span class="note-icon">ℹ️</span>
                <span class="note-text">1 coordinator account will be created for this department</span>
              </div>
            </div>
          </div>

          <div class="distribution-actions">
            <button 
              @click="addDepartment" 
              class="add-dept-btn"
              :disabled="!canAddNewDepartment"
              :title="!canAddNewDepartment ? 'Please fill in all current department names first' : ''"
            >
              + Add Department
            </button>
            <button @click="submitAccountDistribution" class="submit-distribution-btn">
              Complete Setup
            </button>
          </div>
        </div>
      </section>

      <!-- Company Account Distribution (simpler, no departments) -->
      <section v-if="showAccountDistribution && selectedRole === 'company' && selectedPlan" class="content-section account-distribution-section">
        <!-- Progress Indicator -->
        <div class="progress-indicator">
          <div class="progress-step completed">
            <div class="step-number">1</div>
            <span class="step-label">Role Selected</span>
          </div>
          <div class="progress-line completed"></div>
          <div class="progress-step completed">
            <div class="step-number">2</div>
            <span class="step-label">Plan Selected</span>
          </div>
          <div class="progress-line active"></div>
          <div class="progress-step active">
            <div class="step-number">3</div>
            <span class="step-label">Account Setup</span>
          </div>
        </div>

        <!-- Success Banner -->
        <div class="success-banner">
          <div class="banner-icon">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="banner-content">
            <h3 class="banner-title">Great! {{ selectedPlan }} Plan Selected</h3>
            <p class="banner-text">Now configure your role distribution below to complete your setup.</p>
          </div>
        </div>

        <h2 class="section-title">Account Distribution</h2>
        <p class="section-subtitle">Distribute your {{ planLimits.company === 999 ? 'unlimited' : planLimits.company }} company accounts by role/position</p>
        
        <div class="distribution-card">
          <div class="distribution-header">
            <div class="distribution-info">
              <h3 class="distribution-title">Account Role Setup</h3>
              <p class="distribution-subtitle">Create accounts for different roles (e.g., HR Manager, Recruitment Officer, Operations Manager)</p>
            </div>
            <div class="account-summary">
              <div class="summary-item">
                <span class="summary-label">Total Available:</span>
                <span class="summary-value">{{ planLimits.company === 999 ? 'Unlimited' : planLimits.company }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Created:</span>
                <span class="summary-value allocated">{{ departmentDistribution.length }}</span>
              </div>
              <div class="summary-item" v-if="planLimits.company !== 999">
                <span class="summary-label">Remaining:</span>
                <span class="summary-value" :class="{ 'warning': departmentDistribution.length > planLimits.company }">
                  {{ planLimits.company - departmentDistribution.length }}
                </span>
              </div>
            </div>
          </div>

          <div class="department-list">
            <div v-for="(dept, index) in departmentDistribution" :key="index" class="department-item">
              <div class="department-input-group">
                <div class="input-with-label">
                  <label class="input-label">Account Role/Position</label>
                  <input 
                    v-model="dept.department" 
                    type="text" 
                    placeholder="e.g., HR Manager, Recruitment Officer, Operations Manager" 
                    class="department-input"
                    :class="{ 'error': isDuplicateDepartment(index) }"
                  />
                  <span v-if="isDuplicateDepartment(index)" class="error-message">
                    This role/position already exists
                  </span>
                </div>
                <button 
                  v-if="departmentDistribution.length > 1"
                  @click="removeDepartment(index)" 
                  class="remove-dept-btn"
                  title="Remove role"
                >
                  ×
                </button>
              </div>
              <div class="department-note">
                <span class="note-icon">ℹ️</span>
                <span class="note-text">1 account will be created for this role/position</span>
              </div>
            </div>
          </div>

          <div class="distribution-actions">
            <button 
              @click="addDepartment" 
              class="add-dept-btn"
              :disabled="!canAddNewDepartment"
              :title="!canAddNewDepartment ? 'Please fill in all current role/position names first' : ''"
            >
              + Add Role/Position
            </button>
            <button @click="submitAccountDistribution" class="submit-distribution-btn">
              Complete Setup
            </button>
          </div>
        </div>
      </section>

      <!-- Subscription Plans Section -->
      <section class="content-section">
        <h2 class="section-title">How It Works</h2>
        <p class="section-subtitle">Simple steps to get started with OJT Intern Path</p>
        
        <div class="steps-grid">
          <div class="step-card">
            <div class="step-number">1</div>
            <h3 class="step-title">Choose Your Role</h3>
            <p class="step-description">Select whether you're representing a Company or School</p>
          </div>
          <div class="step-card">
            <div class="step-number">2</div>
            <h3 class="step-title">Pick a Plan</h3>
            <p class="step-description">Choose a subscription plan that fits your needs</p>
          </div>
          <div class="step-card">
            <div class="step-number">3</div>
            <h3 class="step-title">Configure Accounts</h3>
            <p class="step-description">Set up your account distribution and get started</p>
          </div>
        </div>
      </section>

      <!-- Contact Support Section -->
      <section class="content-section">
        <div class="contact-card">
          <h3 class="contact-title">Need Help?</h3>
          <p class="contact-text">
            If you believe you should have access to specific features, please contact your administrator or reach out to our support team.
          </p>
          <button class="contact-btn">
            Contact Support
            <ArrowRightIcon class="btn-icon" />
          </button>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
/* Base Styles */
.guest-page {
  min-height: 100vh;
  background:
    radial-gradient(circle at bottom left, #9ec5ff 0 30%, transparent 31%),
    radial-gradient(circle at top right, #9ec5ff 0 25%, transparent 26%), #f5f7fb;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header */
.guest-header {
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

.logo-section {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

.logo-text {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.profile-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  background: white;
  color: #2563eb;
  border: 2px solid #2563eb;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s ease;
}

.profile-btn:hover {
  background: #2563eb;
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.profile-icon {
  width: 20px;
  height: 20px;
}

.logout-btn {
  padding: 10px 24px;
  background: white;
  color: #dc2626;
  border: 2px solid #dc2626;
  border-radius: 6px;
  font-weight: 600;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.logout-btn:hover {
  background: #dc2626;
  color: white;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

/* Main Content */
.guest-main {
  max-width: 1200px;
  margin: 0 auto;
  padding: 56px 32px 80px;
}

/* Welcome Section */
.welcome-section {
  text-align: center;
  margin-bottom: 40px;
}

.welcome-title {
  font-size: 42px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 12px 0;
  letter-spacing: -0.02em;
}

.welcome-subtitle {
  font-size: 18px;
  color: #6b7280;
  margin: 0;
  font-weight: 400;
}

/* Info Card */
.info-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  margin-bottom: 56px;
  display: flex;
  gap: 24px;
  align-items: flex-start;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.info-icon {
  width: 56px;
  height: 56px;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-icon .icon {
  width: 32px;
  height: 32px;
  color: white;
}

.info-content {
  flex: 1;
}

.info-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 10px 0;
}

.info-text {
  font-size: 15px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0;
  font-weight: 400;
}

/* Content Sections */
.content-section {
  margin-bottom: 56px;
}

.section-title {
  font-size: 32px;
  font-weight: 700;
  color: #1f2937;
  text-align: center;
  margin: 0 0 12px 0;
  letter-spacing: -0.02em;
}

.section-subtitle {
  font-size: 16px;
  color: #6b7280;
  text-align: center;
  margin: 0 0 40px 0;
  font-weight: 400;
}

/* Role Cards */
.roles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
  max-width: 800px;
  margin: 0 auto;
}

.role-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
  cursor: pointer;
  position: relative;
}

.role-card:hover:not(.disabled) {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px rgba(37, 99, 235, 0.15);
  border-color: #2563eb;
}

.role-card.selected {
  border: 2px solid #2563eb;
  box-shadow: 0 20px 40px rgba(37, 99, 235, 0.2);
}

.role-card.disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.role-card.selected {
  border: 2px solid #2563eb;
  box-shadow: 0 20px 40px rgba(37, 99, 235, 0.2);
}

.role-selected-badge {
  position: absolute;
  top: 16px;
  right: 16px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
}

.selected-text {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Selected Plan Info */
.selected-plan-info {
  margin-bottom: 32px;
}

.plan-info-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  max-width: 600px;
  margin: 0 auto;
}

.plan-info-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  flex-wrap: wrap;
  gap: 12px;
}

.plan-info-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.plan-info-badge {
  display: inline-block;
  padding: 4px 12px;
  background: #dbeafe;
  color: #1e40af;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.plan-info-text {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

/* Steps Grid */
.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 24px;
  max-width: 900px;
  margin: 0 auto;
}

.step-card {
  background: white;
  border-radius: 12px;
  padding: 32px 24px;
  text-align: center;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.step-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 20px 40px rgba(37, 99, 235, 0.1);
}

.step-number {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  margin: 0 auto 20px;
}

.step-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 12px 0;
}

.step-description {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0;
}

.role-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

.role-icon.student {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.role-icon.company {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.role-icon.school {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.role-icon .icon {
  width: 28px;
  height: 28px;
  color: white;
}

.role-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 12px 0;
}

.role-description {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0 0 20px 0;
  font-weight: 400;
}

.role-features {
  list-style: none;
  padding: 0;
  margin: 0;
}

.role-features li {
  font-size: 14px;
  color: #374151;
  padding: 10px 0;
  padding-left: 28px;
  position: relative;
  font-weight: 400;
}

.role-features li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: #2563eb;
  font-weight: 700;
  font-size: 16px;
}

.role-account-info {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.account-badge {
  display: inline-block;
  padding: 6px 12px;
  background: #dbeafe;
  color: #1e40af;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.account-badge.student {
  background: #dcfce7;
  color: #166534;
}

/* Account Distribution Section */
.account-distribution-section {
  scroll-margin-top: 100px;
  transition: all 0.3s ease;
}

.account-distribution-section.highlight-section {
  animation: highlightPulse 3s ease-in-out;
}

@keyframes highlightPulse {
  0% { 
    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4);
    transform: scale(1);
  }
  50% { 
    box-shadow: 0 0 0 20px rgba(37, 99, 235, 0.1);
    transform: scale(1.02);
  }
  100% { 
    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
    transform: scale(1);
  }
}

/* Progress Indicator */
.progress-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 40px;
  padding: 24px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
  margin-bottom: 40px;
}

.progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  position: relative;
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 16px;
  transition: all 0.3s ease;
  border: 2px solid #e5e7eb;
  background: white;
  color: #9ca3af;
}

.progress-step.completed .step-number {
  background: #10b981;
  border-color: #10b981;
  color: white;
}

.progress-step.active .step-number {
  background: #2563eb;
  border-color: #2563eb;
  color: white;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(37, 99, 235, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
  }
}

.step-label {
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  text-align: center;
  white-space: nowrap;
}

.progress-step.completed .step-label {
  color: #10b981;
}

.progress-step.active .step-label {
  color: #2563eb;
}

.progress-line {
  width: 60px;
  height: 2px;
  background: #e5e7eb;
  margin: 0 16px;
  transition: all 0.3s ease;
}

.progress-line.completed {
  background: #10b981;
}

.progress-line.active {
  background: linear-gradient(90deg, #10b981 0%, #2563eb 100%);
}

/* Success Banner */
.success-banner {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: linear-gradient(135deg, #ecfdf5 0%, #f0f9ff 100%);
  border: 1px solid #10b981;
  border-radius: 12px;
  margin-bottom: 32px;
  animation: slideInFromTop 0.5s ease-out;
}

@keyframes slideInFromTop {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.banner-icon {
  width: 48px;
  height: 48px;
  background: #10b981;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.banner-icon .icon {
  width: 24px;
  height: 24px;
  color: white;
}

.banner-content {
  flex: 1;
}

.banner-title {
  font-size: 18px;
  font-weight: 600;
  color: #065f46;
  margin: 0 0 4px 0;
}

.banner-text {
  font-size: 14px;
  color: #047857;
  margin: 0;
  line-height: 1.5;
}

.distribution-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
  max-width: 900px;
  margin: 0 auto;
}

.distribution-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 2px solid #e5e7eb;
  flex-wrap: wrap;
  gap: 20px;
}

.distribution-info {
  flex: 1;
  min-width: 250px;
}

.distribution-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.distribution-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.account-summary {
  display: flex;
  gap: 24px;
  flex-wrap: wrap;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.summary-label {
  font-size: 12px;
  color: #6b7280;
  font-weight: 500;
}

.summary-value {
  font-size: 24px;
  font-weight: 700;
  color: #1f2937;
}

.summary-value.allocated {
  color: #2563eb;
}

.summary-value.warning {
  color: #dc2626;
}

.department-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.department-item {
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.department-input-group {
  display: flex;
  gap: 12px;
  align-items: flex-end;
}

.input-with-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.input-label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.department-input {
  flex: 1;
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: all 0.2s;
}

.department-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.department-input.error {
  border-color: #dc2626 !important;
  border-width: 2px;
  background-color: #fef2f2;
}

.department-input.error:focus {
  border-color: #dc2626 !important;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2) !important;
}

.error-message {
  font-size: 12px;
  color: #dc2626;
  margin-top: 4px;
  font-weight: 500;
}

.accounts-input {
  width: 150px;
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: all 0.2s;
}

.accounts-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.department-note {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #eff6ff;
  border-radius: 6px;
  margin-top: 8px;
  font-size: 12px;
  color: #1e40af;
}

.note-icon {
  font-size: 14px;
}

.note-text {
  font-weight: 500;
}

.remove-dept-btn {
  width: 36px;
  height: 36px;
  background: #fee2e2;
  color: #dc2626;
  border: none;
  border-radius: 6px;
  font-size: 24px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.remove-dept-btn:hover {
  background: #dc2626;
  color: white;
}

.distribution-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.add-dept-btn {
  padding: 12px 24px;
  background: white;
  color: #2563eb;
  border: 2px solid #2563eb;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.add-dept-btn:hover:not(:disabled) {
  background: #eff6ff;
}

.add-dept-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f3f4f6;
  color: #9ca3af;
  border-color: #d1d5db;
}

.add-dept-btn:disabled:hover {
  background: #f3f4f6;
  color: #9ca3af;
  border-color: #d1d5db;
  transform: none;
}

.submit-distribution-btn {
  padding: 12px 32px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.submit-distribution-btn:hover {
  background: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Company Setup Info */
.company-setup-info {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 32px;
  padding: 24px;
  background: #f8fafc;
  border-radius: 8px;
}

.setup-detail {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #e5e7eb;
}

.setup-detail:last-child {
  border-bottom: none;
}

.detail-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.detail-value {
  font-size: 16px;
  color: #1f2937;
  font-weight: 600;
}

/* Pricing Cards */
.pricing-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
  max-width: 1000px;
  margin: 0 auto;
}

.pricing-card {
  background: white;
  border-radius: 12px;
  padding: 40px 32px;
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
  box-shadow: 0 20px 40px rgba(37, 99, 235, 0.15);
}

.pricing-card.featured:hover {
  transform: scale(1.05) translateY(-8px);
  box-shadow: 0 20px 40px rgba(37, 99, 235, 0.25);
}

.pricing-card.selected {
  border: 3px solid #10b981;
  box-shadow: 0 20px 40px rgba(16, 185, 129, 0.2);
}

.pricing-card.selected:hover {
  border-color: #10b981;
  box-shadow: 0 20px 40px rgba(16, 185, 129, 0.3);
}

.selected-badge {
  position: absolute;
  top: -14px;
  right: 20px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  padding: 6px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
}

.popular-badge {
  position: absolute;
  top: -14px;
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

.plan-name {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 20px 0 16px 0;
  text-align: center;
}

.plan-price {
  font-size: 42px;
  font-weight: 700;
  color: #2563eb;
  text-align: center;
  margin: 0 0 8px 0;
  letter-spacing: -0.02em;
}

.period {
  font-size: 16px;
  font-weight: 400;
  color: #6b7280;
}

.plan-description {
  font-size: 14px;
  color: #6b7280;
  text-align: center;
  margin: 0 0 24px 0;
  padding-bottom: 24px;
  border-bottom: 1px solid #e5e7eb;
  font-weight: 400;
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0 0 32px 0;
  flex: 1;
}

.plan-features li {
  font-size: 14px;
  color: #374151;
  padding: 10px 0;
  padding-left: 28px;
  position: relative;
  font-weight: 400;
}

.plan-features li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: #2563eb;
  font-weight: 700;
  font-size: 16px;
}

.plan-btn {
  width: 100%;
  padding: 12px 24px;
  background: white;
  color: #2563eb;
  border: 2px solid #2563eb;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.plan-btn:hover {
  background: #2563eb;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.featured-btn {
  background: #2563eb;
  color: white;
  border: 2px solid #2563eb;
}

.featured-btn:hover {
  background: #1d4ed8;
  border-color: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
}

.selected-btn {
  background: #10b981 !important;
  color: white !important;
  border-color: #10b981 !important;
  cursor: default;
}

.selected-btn:hover {
  background: #10b981 !important;
  border-color: #10b981 !important;
  transform: none !important;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3) !important;
}

/* Contact Card */
.contact-card {
  background: white;
  border-radius: 12px;
  padding: 48px 40px;
  text-align: center;
  max-width: 700px;
  margin: 0 auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  border: 1px solid #e5e7eb;
}

.contact-title {
  font-size: 26px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 16px 0;
}

.contact-text {
  font-size: 15px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0 0 28px 0;
  font-weight: 400;
}

.contact-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 32px;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.contact-btn:hover {
  background: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-icon {
  width: 20px;
  height: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
  .guest-main {
    padding: 40px 20px 60px;
  }

  .welcome-title {
    font-size: 28px;
  }
  
  .welcome-subtitle {
    font-size: 16px;
  }
  
  .info-card {
    flex-direction: column;
    padding: 24px;
    margin-bottom: 40px;
  }
  
  .content-section {
    margin-bottom: 40px;
  }

  .section-title {
    font-size: 24px;
  }
  
  .section-subtitle {
    font-size: 15px;
    margin-bottom: 32px;
  }
  
  .roles-grid,
  .pricing-grid {
    grid-template-columns: 1fr;
  }
  
  .pricing-card.featured {
    transform: scale(1);
  }
  
  .pricing-card.featured:hover {
    transform: translateY(-8px);
  }

  .contact-card {
    padding: 32px 24px;
  }

  .header-content {
    padding: 0 20px;
  }

  .nav-actions {
    gap: 8px;
  }

  .profile-btn,
  .logout-btn {
    padding: 8px 16px;
    font-size: 13px;
  }

  .profile-icon {
    width: 16px;
    height: 16px;
  }

  .distribution-header {
    flex-direction: column;
  }

  .account-summary {
    width: 100%;
    justify-content: space-between;
  }

  .department-input-group {
    flex-direction: column;
    align-items: stretch;
  }

  .input-with-label {
    width: 100%;
  }

  .accounts-input {
    width: 100%;
  }

  .distribution-actions {
    flex-direction: column;
  }

  .add-dept-btn,
  .submit-distribution-btn {
    width: 100%;
  }

  .distribution-card {
    padding: 24px;
  }

  .progress-indicator {
    padding: 16px;
    margin-bottom: 24px;
  }

  .step-label {
    font-size: 10px;
  }

  .progress-line {
    width: 40px;
    margin: 0 8px;
  }

  .success-banner {
    flex-direction: column;
    text-align: center;
    padding: 16px;
    gap: 12px;
  }

  .banner-title {
    font-size: 16px;
  }

  .banner-text {
    font-size: 13px;
  }
}
</style>

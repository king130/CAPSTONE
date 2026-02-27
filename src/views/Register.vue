<script setup lang="ts">
import { ref } from 'vue'
import Swal from 'sweetalert2'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const agreed = ref(false)
const currentStep = ref(1)
const totalSteps = 2
const showPassword = ref(false)
const showConfirmPassword = ref(false)

// Form data
const formData = ref({
  fullName: '',
  email: '',
  password: '',
  confirmPassword: ''
})

const router = useRouter()
const authStore = useAuthStore()

function handleFileUpload(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value.resume = target.files[0]
  }
}

function handleStudentFileUpload(event: Event, field: 'proofOfEnrollment') {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value[field] = target.files[0]
  }
}

function handleCompanyFileUpload(event: Event, field: 'businessPermit' | 'companyIdDoc' | 'organizationProfile') {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value[field] = target.files[0]
  }
}

function handleSchoolFileUpload(event: Event, field: 'schoolPermit' | 'endorsementLetter') {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value[field] = target.files[0]
  }
}

function fillDemo(role: 'student' | 'company' | 'school') {
  demoMode.value = true
  selectedRole.value = role
  formData.value.fullName = role === 'student' ? 'Alex Student' : role === 'company' ? 'Jordan Employer' : 'Taylor Coordinator'
  formData.value.email = role === 'student' ? 'student@demo.com' : role === 'company' ? 'company@demo.com' : 'school@demo.com'
  formData.value.password = 'DemoPass123!'
  formData.value.confirmPassword = 'DemoPass123!'

  if (role === 'student') {
    formData.value.studentId = '2026-0001'
    formData.value.schoolName = 'Cavite State University'
    formData.value.schoolSubscriptionCode = 'CSV-2026-DEMO'
    formData.value.course = 'Computer Science'
    formData.value.yearLevel = '4th Year'
    formData.value.preferredField = 'Software Development'
    formData.value.contactNumber = '09171234567'
  }

  if (role === 'company') {
    formData.value.companyName = 'Cavite Tech Hub'
    formData.value.companyType = 'Corporation'
    formData.value.industryType = 'Technology'
    formData.value.companyAddress = 'Imus, Cavite'
    formData.value.companyEmail = 'hr@cavitetech.demo'
    formData.value.contactPersonName = 'Jordan Reyes'
    formData.value.contactPersonTitle = 'HR Manager'
    formData.value.contactPersonEmail = 'jordan@cavitetech.demo'
    formData.value.companyContactNumber = '09181234567'
    formData.value.businessRegNumber = 'SEC-2026-00123'
    formData.value.taxIdentificationNumber = '123-456-789-000'
  }

  if (role === 'school') {
    formData.value.institutionName = 'Cavite State University'
    formData.value.institutionType = 'State University/College'
    formData.value.department = 'Career Services'
    formData.value.position = 'OJT Coordinator'
    formData.value.officialSchoolEmail = 'ojt@cvsu.demo'
    formData.value.schoolIdNumber = 'CHED-CVSU-001'
    formData.value.chedDepedNumber = 'CHED-REG4A-2026-001'
    formData.value.schoolContactNumber = '046-123-4567'
    formData.value.registrarName = 'Dr. Jamie Cruz'
    formData.value.registrarEmail = 'registrar@cvsu.demo'
    formData.value.schoolAddress = 'Indang, Cavite'
  }
}

function isEmpty(value: string) {
  return !value || !value.trim()
}

function isStepValid() {
  if (currentStep.value === 1) {
    return (
      !isEmpty(formData.value.fullName) &&
      !isEmpty(formData.value.email) &&
      !isEmpty(formData.value.password) &&
      formData.value.password.length >= 6 &&
      formData.value.password === formData.value.confirmPassword
    )
  }

  if (currentStep.value === 2) {
    return !!selectedRole.value
  }

  if (currentStep.value === 3) {
    if (selectedRole.value === 'student') {
      return (
        !isEmpty(formData.value.studentId) &&
        !isEmpty(formData.value.schoolName) &&
        !isEmpty(formData.value.schoolSubscriptionCode) &&
        !isEmpty(formData.value.course) &&
        !isEmpty(formData.value.yearLevel) &&
        !isEmpty(formData.value.preferredField) &&
        !isEmpty(formData.value.contactNumber)
      )
    }

    if (selectedRole.value === 'company') {
      return (
        !isEmpty(formData.value.companyName) &&
        !isEmpty(formData.value.companyType) &&
        !isEmpty(formData.value.industryType) &&
        !isEmpty(formData.value.companyAddress) &&
        !isEmpty(formData.value.companyEmail) &&
        !isEmpty(formData.value.contactPersonName) &&
        !isEmpty(formData.value.contactPersonTitle) &&
        !isEmpty(formData.value.contactPersonEmail) &&
        !isEmpty(formData.value.companyContactNumber)
      )
    }

    if (selectedRole.value === 'school') {
      return (
        !isEmpty(formData.value.institutionName) &&
        !isEmpty(formData.value.institutionType) &&
        !isEmpty(formData.value.department) &&
        !isEmpty(formData.value.position) &&
        !isEmpty(formData.value.officialSchoolEmail) &&
        !isEmpty(formData.value.schoolIdNumber) &&
        !isEmpty(formData.value.chedDepedNumber) &&
        !isEmpty(formData.value.schoolContactNumber)
      )
    }
  }

  if (currentStep.value === 4) {
    if (demoMode.value) {
      return true
    }

    if (selectedRole.value === 'student') {
      return !!formData.value.resume && !!formData.value.proofOfEnrollment
    }

    if (selectedRole.value === 'company') {
      return (
        !isEmpty(formData.value.businessRegNumber) &&
        !isEmpty(formData.value.taxIdentificationNumber) &&
        !!formData.value.businessPermit &&
        !!formData.value.companyIdDoc &&
        !!formData.value.organizationProfile
      )
    }

    if (selectedRole.value === 'school') {
      return (
        !isEmpty(formData.value.registrarName) &&
        !isEmpty(formData.value.registrarEmail) &&
        !isEmpty(formData.value.schoolAddress) &&
        !!formData.value.schoolPermit &&
        !!formData.value.endorsementLetter
      )
    }
  }

  if (currentStep.value === 5) {
    return agreed.value
  }

  return false
}

function goNext() {
  if (!isStepValid()) return
  if (currentStep.value < totalSteps) currentStep.value++
}

function goBack() {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

async function onRegister() {
  if (!selectedRole.value) return
  try {
    const profile = await authStore.register({
      fullName: formData.value.fullName,
      email: formData.value.email,
      password: formData.value.password,
      role: selectedRole.value,
      profile: {
        studentId: formData.value.studentId,
        schoolName: formData.value.schoolName,
        schoolSubscriptionCode: formData.value.schoolSubscriptionCode,
        course: formData.value.course,
        yearLevel: formData.value.yearLevel,
        preferredField: formData.value.preferredField,
        contactNumber: formData.value.contactNumber,
        companyName: formData.value.companyName,
        companyType: formData.value.companyType,
        industryType: formData.value.industryType,
        companyAddress: formData.value.companyAddress,
        companyEmail: formData.value.companyEmail,
        contactPersonName: formData.value.contactPersonName,
        contactPersonTitle: formData.value.contactPersonTitle,
        contactPersonEmail: formData.value.contactPersonEmail,
        companyContactNumber: formData.value.companyContactNumber,
        businessRegNumber: formData.value.businessRegNumber,
        taxIdentificationNumber: formData.value.taxIdentificationNumber,
        institutionName: formData.value.institutionName,
        institutionType: formData.value.institutionType,
        department: formData.value.department,
        position: formData.value.position,
        officialSchoolEmail: formData.value.officialSchoolEmail,
        schoolContactNumber: formData.value.schoolContactNumber,
        schoolAddress: formData.value.schoolAddress,
        schoolIdNumber: formData.value.schoolIdNumber,
        chedDepedNumber: formData.value.chedDepedNumber,
        schoolAccreditation: formData.value.schoolAccreditation,
        registrarName: formData.value.registrarName,
        registrarEmail: formData.value.registrarEmail,
      },
      subscriptionPlan: formData.value.subscriptionPlan,
      billingCycle: formData.value.billingCycle,
      schoolSubscriptionCode: formData.value.schoolSubscriptionCode,
    })

    const message =
      selectedRole.value === 'school' && profile?.subscription?.subscriptionCode
        ? `Your school subscription code is ${profile.subscription.subscriptionCode}. Share this with students to register.`
        : 'You can now login with your new credentials.'

    const result = await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Account Created\nSuccessfully!',
      text: message,
      confirmButtonText: 'Proceed to Login',
      confirmButtonColor: '#2563eb',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })

    if (result.isConfirmed) {
      await router.push('/login')
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Registration failed.'
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Registration Failed',
      text: message,
      confirmButtonText: 'Try Again',
      confirmButtonColor: '#2563eb',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <div class="back-link">
      </div>
      
      <div class="icon-row">
        <div class="icon-bubbles">
          <img src="/icons/logo-main.png" alt="OJT Intern Path" class="register-logo" />
        </div>
      </div>

      <h1>Create Your Account</h1>
      <p class="subtitle">Register as a Guest to get started</p>

      <form class="form" @submit.prevent="onRegister">
        <div class="section-label">Step {{ currentStep }} of {{ totalSteps }}</div>

        <div v-if="currentStep === 1">
          <div class="section-label">Account Basics</div>

          <label class="field">
            <span>Full Name</span>
            <input v-model="formData.fullName" type="text" placeholder="Enter your full name" />
          </label>

          <label class="field">
            <span>Email Address</span>
            <input v-model="formData.email" type="email" placeholder="Enter your email address" />
          </label>

          <label class="field">
            <span>Password</span>
            <div class="password-field">
              <input
                v-model="formData.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Create a password (min 6 characters)"
              />
              <button type="button" class="toggle-password" @click="showPassword = !showPassword">
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
            <small v-if="formData.password && formData.password.length < 6" style="color: #dc2626; font-size: 11px;">
              Password must be at least 6 characters
            </small>
          </label>

          <label class="field">
            <span>Confirm Password</span>
            <div class="password-field">
              <input
                v-model="formData.confirmPassword"
                :type="showConfirmPassword ? 'text' : 'password'"
                placeholder="Re-enter your password"
              />
              <button
                type="button"
                class="toggle-password"
                @click="showConfirmPassword = !showConfirmPassword"
              >
                {{ showConfirmPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
          </label>
        </div>

        <div v-else-if="currentStep === 2">
          <div class="section-label">Terms & Conditions</div>

          <label class="terms">
            <input v-model="agreed" type="checkbox" />
            <span>
              I agree to the
              <a href="#">Terms &amp; Conditions</a>
            </span>
          </label>

          <button type="submit" class="primary-btn" :disabled="!isStepValid()">Create Account</button>
        </div>

        <div class="step-actions">
          <button type="button" class="step-btn ghost" @click="goBack" :disabled="currentStep === 1">
            Back
          </button>
          <button type="button" class="step-btn primary" @click="goNext" v-if="currentStep < totalSteps" :disabled="!isStepValid()">
            Next
          </button>
        </div>
      </form>

      <p class="footer-link">
        Already have an account?
        <RouterLink to="/login">Login</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped src="../styles/Register.css">
</style>



<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Swal from 'sweetalert2'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const showPassword = ref(false)
const showConfirmPassword = ref(false)

// Simple form data - email and password only
const formData = ref({
  fullName: '',
  email: '',
  password: '',
  confirmPassword: ''
})

// Check if already logged in - redirect to dashboard or find-internships
onMounted(() => {
  if (authStore.user) {
    if (authStore.user.mustChangePassword) {
      router.replace('/change-password')
      return
    }
    const role = authStore.user?.role
    if (role && role !== 'guest' && role !== null) {
      const dash = role === 'admin' ? '/admin/overview' : role === 'company' ? '/dashboard' : role === 'school' ? '/school' : role === 'student' ? '/intern' : '/find-internships'
      router.replace(dash)
    } else {
      router.replace('/find-internships')
    }
  }
})

function isEmpty(value: string) {
  return !value || !value.trim()
}

function isFormValid() {
  return (
    !isEmpty(formData.value.fullName) &&
    !isEmpty(formData.value.email) &&
    !isEmpty(formData.value.password) &&
    formData.value.password.length >= 6 &&
    formData.value.password === formData.value.confirmPassword
  )
}

async function onRegister() {
  if (!isFormValid()) {
    await Swal.fire({
      icon: 'warning',
      title: 'Incomplete Form',
      text: 'Please fill in all fields correctly.',
      confirmButtonColor: '#2563eb'
    })
    return
  }

  try {
    // Register as guest by default. Role is chosen after account creation.
    const registerPayload: Parameters<typeof authStore.register>[0] = {
      fullName: formData.value.fullName,
      email: formData.value.email,
      password: formData.value.password,
    }

    const profile = await authStore.register(registerPayload)

    if (profile.role === 'student' && profile.mustChangePassword) {
      await Swal.fire({
        icon: 'success',
        iconColor: '#16a34a',
        title: 'Student Account Ready!',
        text: 'Your student account is now active. Please change your default password to continue.',
        confirmButtonText: 'Continue',
        confirmButtonColor: '#2563eb'
      })
      router.push('/change-password')
      return
    }

    await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Account Created!',
      text: 'Welcome to OJT Intern Path! Your account has been created successfully.',
      confirmButtonText: 'Continue',
      confirmButtonColor: '#2563eb'
    })

    router.push('/guest')
    
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Registration failed.'
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Registration Failed',
      text: message,
      confirmButtonText: 'Try Again',
      confirmButtonColor: '#2563eb'
    })
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <div class="back-link">
        <RouterLink to="/" class="back-btn" aria-label="Back to Home">&larr;</RouterLink>
      </div>
      
      <div class="icon-row">
        <img src="/icons/logo-main.png" alt="OJT Intern Path" class="register-logo" />
      </div>

      <h1>Create Your Account</h1>
      <p class="subtitle">Sign up as a guest first, then choose your role</p>

      <form class="form" @submit.prevent="onRegister">
        <label class="field">
          <span>Full Name</span>
          <input 
            v-model="formData.fullName" 
            type="text" 
            placeholder="Enter your full name" 
            required
          />
        </label>

        <label class="field">
          <span>Email Address</span>
          <input 
            v-model="formData.email" 
            type="email" 
            placeholder="Enter your email address" 
            required
          />
        </label>

        <label class="field">
          <span>Password</span>
          <div class="password-field">
            <input
              v-model="formData.password"
              :type="showPassword ? 'text' : 'password'"
              placeholder="Create a password (min 6 characters)"
              required
            />
            <button type="button" class="toggle-password" @click="showPassword = !showPassword">
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
          <small v-if="formData.password && formData.password.length < 6" class="error-text">
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
              required
            />
            <button
              type="button"
              class="toggle-password"
              @click="showConfirmPassword = !showConfirmPassword"
            >
              {{ showConfirmPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
          <small v-if="formData.confirmPassword && formData.password !== formData.confirmPassword" class="error-text">
            Passwords do not match
          </small>
        </label>

        <button type="submit" class="primary-btn" :disabled="!isFormValid() || authStore.loading">
          {{ authStore.loading ? 'Creating Account...' : 'Create Account' }}
        </button>
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

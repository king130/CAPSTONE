<script setup lang="ts">
import Swal from 'sweetalert2'
import { RouterLink, useRouter } from 'vue-router'
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const email = ref('')
const password = ref('')
const showPassword = ref(false)

async function handleAuthSuccess(profile: { role?: string } | null) {
  if (!profile?.role) {
    throw new Error('Account setup incomplete. Your user profile is missing. Please contact support or re-register.')
  }
  const message = profile.role === 'company' 
    ? 'Welcome to the Company Dashboard.' 
    : profile.role === 'school' 
    ? 'Welcome to the School Dashboard.'
    : 'Welcome back!'

  await Swal.fire({
    icon: 'success',
    iconColor: '#16a34a',
    title: 'Login Successful!',
    text: message,
    confirmButtonText: 'Continue',
    confirmButtonColor: '#2563eb',
    customClass: {
      popup: 'capstone-swal-popup',
      title: 'capstone-swal-title',
      htmlContainer: 'capstone-swal-text',
      confirmButton: 'capstone-swal-confirm',
    },
  })

  if (profile.role === 'company') {
    router.push('/dashboard')
  } else if (profile.role === 'school') {
    router.push('/school')
  } else {
    router.push('/intern')
  }
}

async function onLogin() {
  try {
    const profile = await authStore.login(email.value.trim(), password.value)
    await handleAuthSuccess(profile)
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Invalid credentials.'
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Login Failed!',
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

async function useDemoAccount() {
  const demoEmail = 'student@demo.com'
  const demoPassword = 'DemoPass123!'
  email.value = demoEmail
  password.value = demoPassword

  try {
    const profile = await authStore.login(demoEmail, demoPassword)
    await handleAuthSuccess(profile)
  } catch (error) {
    try {
      const profile = await authStore.register({
        fullName: 'Alex Student',
        email: demoEmail,
        password: demoPassword,
        role: 'student',
        profile: {
          studentId: '2026-0001',
          schoolName: 'Cavite State University',
          course: 'Computer Science',
          yearLevel: '4th Year',
          preferredField: 'Software Development',
          contactNumber: '09171234567',
        },
      })
      await handleAuthSuccess(profile)
    } catch (registerError) {
      const message =
        registerError instanceof Error ? registerError.message : 'Unable to create demo account.'
      await Swal.fire({
        icon: 'error',
        iconColor: '#dc2626',
        title: 'Demo Login Failed',
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
}
</script>

<template>
  <div class="page">
    <div class="card">
      <!-- Left Illustration Panel -->
      <div class="left">
        <h2>Let’s get Started!</h2>

        <!-- Illustration Image -->
        <div class="illustration">
          <img
            src="/undraw_working-remotely_ivtz-1024x815.webp"
            alt="Working remotely illustration"
            class="illustration-img"
          />
        </div>
      </div>

      <!-- Right Login Panel -->
      <div class="right">
        <div class="back-link">
          <RouterLink to="/" class="back-btn" title="Back to Home">←</RouterLink>
        </div>

        <div class="icon">
          <img src="/icons/logo-main.png" alt="OJT Intern Path" class="login-logo" />
        </div>

        <h1>Welcome!</h1>
        <p class="subtitle">Log in to manage opportunities and application</p>

        <form @submit.prevent="onLogin">
          <input type="email" placeholder="Email Address:" v-model="email" />
          <div class="password-field">
            <input
              :type="showPassword ? 'text' : 'password'"
              placeholder="Password:"
              v-model="password"
            />
            <button type="button" class="toggle-password" @click="showPassword = !showPassword">
              {{ showPassword ? 'Hide' : 'Show' }}
            </button>
          </div>

          <div class="row">
            <label>
              <input type="checkbox" />
              Remember me
            </label>
            <a href="#">Forgot password</a>
          </div>

          <button type="submit" :disabled="authStore.loading">Log In</button>
          <button type="button" class="demo-login" @click="useDemoAccount" :disabled="authStore.loading">
            Use Demo Account
          </button>

          <p class="signup">
            Don’t have an account?
            <RouterLink to="/register">Sign up</RouterLink>
          </p>
          <p class="admin-link">
            <RouterLink to="/admin/login">Admin Login</RouterLink>
          </p>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped src="../styles/Login.css">
</style>


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

// Helper function to get role dashboard
function getRoleDashboard(role: string | null): string {
  switch (role) {
    case 'admin':
      return '/admin/overview'
    case 'company':
      return '/dashboard'
    case 'school':
      return '/school'
    case 'student':
      return '/intern'
    case 'guest':
    case null:
      return '/find-internships'
    default:
      return '/find-internships'
  }
}

async function onLogin() {
  try {
    const profile = await authStore.login(email.value.trim(), password.value)
    
    // Check if user has a role
    if (!profile?.role || profile.role === 'guest' || profile.role === null) {
      router.push('/find-internships')
      return
    }

    // Show success message
    const roleMessages: Record<string, string> = {
      admin: 'Welcome back, Administrator!',
      company: 'Welcome to the Company Dashboard',
      school: 'Welcome to the School Dashboard',
      student: 'Welcome back!'
    }

    await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Login Successful!',
      text: roleMessages[profile.role] || 'Welcome back!',
      timer: 1500,
      showConfirmButton: false
    })

    // Router will handle redirect based on role
    router.push(getRoleDashboard(profile.role))
    
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Invalid credentials.'
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Login Failed!',
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

          <button type="submit" :disabled="authStore.loading">
            {{ authStore.loading ? 'Logging in...' : 'Log In' }}
          </button>

          <p class="signup">
            Don’t have an account?
            <RouterLink to="/register">Sign up</RouterLink>
          </p>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped src="../styles/Login.css">
</style>


<script setup lang="ts">
import Swal from 'sweetalert2'
import { RouterLink, useRouter } from 'vue-router'
import { ref } from 'vue'

const router = useRouter()
const email = ref('')
const password = ref('')

async function onLogin() {
  console.log('Email entered:', email.value)
  if (email.value.trim().toLowerCase() === 'company@gmail.com') {
    console.log('Login successful, navigating to dashboard')
    await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Login Successful!',
      text: 'Welcome to the Company Dashboard.',
      confirmButtonText: 'Continue',
      confirmButtonColor: '#2563eb',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
    router.push('/dashboard')
  } else {
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Login Failed!',
      text: 'Invalid credentials.',
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
          <input type="password" placeholder="Password:" v-model="password" />

          <div class="row">
            <label>
              <input type="checkbox" />
              Remember me
            </label>
            <a href="#">Forgot password</a>
          </div>

          <button type="submit">Log In</button>

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


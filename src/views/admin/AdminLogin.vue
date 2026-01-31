<script setup lang="ts">
import { ref } from 'vue'
import Swal from 'sweetalert2'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const email = ref('')
const password = ref('')
const showPassword = ref(false)

async function onLogin() {
  try {
    const profile = await authStore.login(email.value.trim(), password.value)
    if (profile?.role !== 'admin') {
      await authStore.logout()
      throw new Error('Not authorized for admin access.')
    }
    await router.push('/admin/overview')
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Invalid credentials.'
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Admin Login Failed',
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
      <h1>Admin Login</h1>
      <p class="subtitle">Restricted access for system administrators.</p>
      <form @submit.prevent="onLogin">
        <input type="email" placeholder="Email Address" v-model="email" />
        <div class="password-field">
          <input :type="showPassword ? 'text' : 'password'" placeholder="Password" v-model="password" />
          <button type="button" class="toggle-password" @click="showPassword = !showPassword">
            {{ showPassword ? 'Hide' : 'Show' }}
          </button>
        </div>
        <button type="submit" class="primary" :disabled="authStore.loading">Log In</button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #0f172a;
  font-family:
    Inter,
    system-ui,
    -apple-system,
    BlinkMacSystemFont;
}

.card {
  width: 420px;
  padding: 28px;
  border-radius: 12px;
  background: #ffffff;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
  text-align: center;
}

h1 {
  margin-bottom: 6px;
  font-size: 22px;
}

.subtitle {
  color: #64748b;
  font-size: 13px;
  margin-bottom: 18px;
}

form {
  text-align: left;
}

input[type='email'],
input[type='password'],
input[type='text'] {
  width: 100%;
  padding: 12px 14px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  margin-bottom: 16px;
  font-size: 14px;
}

.password-field {
  position: relative;
  margin-bottom: 16px;
}

.password-field input {
  padding-right: 72px;
  margin-bottom: 0;
}

.toggle-password {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: #2563eb;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 6px 8px;
  width: auto;
  z-index: 1;
}

button.primary {
  width: 100%;
  padding: 12px;
  background: #2563eb;
  border: none;
  border-radius: 6px;
  color: white;
  font-weight: 600;
  cursor: pointer;
}
</style>

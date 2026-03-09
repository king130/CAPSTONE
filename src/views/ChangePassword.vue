<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { RouterLink } from 'vue-router'
import Swal from 'sweetalert2'
import { useAuthStore } from '@/stores/auth'
import { updateCurrentUserPassword } from '@/services/auth'

const router = useRouter()
const authStore = useAuthStore()

const newPassword = ref('')
const confirmPassword = ref('')
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)
const saving = ref(false)

const passwordMismatch = computed(() => {
  return !!confirmPassword.value && newPassword.value !== confirmPassword.value
})

const isValid = computed(() => {
  return newPassword.value.length >= 6 && newPassword.value === confirmPassword.value
})

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
    default:
      return '/find-internships'
  }
}

onMounted(() => {
  if (!authStore.user) {
    router.replace('/login')
    return
  }
  if (!authStore.user.mustChangePassword) {
    router.replace(getRoleDashboard(authStore.user.role))
  }
})

async function onSubmit() {
  if (!isValid.value) return
  saving.value = true
  try {
    await updateCurrentUserPassword(newPassword.value)
    await Swal.fire({
      icon: 'success',
      title: 'Password Updated',
      text: 'Your account is now secured. You can continue to your dashboard.',
      confirmButtonColor: '#2563eb',
    })
    router.replace(getRoleDashboard(authStore.user?.role || null))
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Update Failed',
      text: error instanceof Error ? error.message : 'Could not change password.',
      confirmButtonColor: '#2563eb',
    })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <div class="back-link">
        <RouterLink to="/login" class="back-btn" aria-label="Back to Login">&larr;</RouterLink>
      </div>

      <div class="icon-row">
        <img src="/icons/logo-main.png" alt="OJT Intern Path" class="register-logo" />
      </div>

      <h1>Change Password</h1>
      <p class="subtitle">Use a new password before continuing to your student account</p>

      <div class="notice">
        This is your first login using a default school password.
      </div>

      <form class="form" @submit.prevent="onSubmit">
        <label class="field">
          <span>New Password</span>
          <div class="password-field">
            <input
              v-model="newPassword"
              :type="showNewPassword ? 'text' : 'password'"
              placeholder="Enter new password"
              required
            />
            <button type="button" class="toggle-password" @click="showNewPassword = !showNewPassword">
              {{ showNewPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
        </label>

        <label class="field">
          <span>Confirm New Password</span>
          <div class="password-field">
            <input
              v-model="confirmPassword"
              :type="showConfirmPassword ? 'text' : 'password'"
              placeholder="Confirm new password"
              required
            />
            <button type="button" class="toggle-password" @click="showConfirmPassword = !showConfirmPassword">
              {{ showConfirmPassword ? 'Hide' : 'Show' }}
            </button>
          </div>
        </label>

        <small v-if="newPassword && newPassword.length < 6" class="error-text">
          Password must be at least 6 characters.
        </small>
        <small v-if="passwordMismatch" class="error-text">
          Passwords do not match.
        </small>

        <button type="submit" class="primary-btn" :disabled="!isValid || saving">
          {{ saving ? 'Saving...' : 'Save Password' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped src="../styles/Register.css"></style>
<style scoped>
.notice {
  margin: 0 0 16px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e3a8a;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 12px;
}

.error-text {
  color: #dc2626;
  font-size: 11px;
}
</style>

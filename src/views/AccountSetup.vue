<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import { useAuthStore } from '@/stores/auth'
import { completeAccountSetup, validateAccountSetupToken } from '@/services/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const token = computed(() => String(route.query.token ?? '').trim())
const loading = ref(true)
const saving = ref(false)
const valid = ref(false)
const inviteEmail = ref('')
const inviteName = ref('')
const errorMessage = ref('')
const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const passwordMismatch = computed(() => !!confirmPassword.value && password.value !== confirmPassword.value)
const isValid = computed(() => password.value.length >= 8 && password.value === confirmPassword.value)

onMounted(async () => {
  if (!token.value) {
    errorMessage.value = 'This account setup link is missing its token.'
    loading.value = false
    return
  }

  try {
    const data = await validateAccountSetupToken(token.value)
    inviteEmail.value = data.email
    inviteName.value = data.displayName
    valid.value = true
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'This account setup link is invalid or expired.'
  } finally {
    loading.value = false
  }
})

async function onSubmit() {
  if (!isValid.value || !token.value) return

  saving.value = true
  try {
    const profile = await completeAccountSetup(token.value, password.value)
    authStore.setUserProfile(profile)

    await Swal.fire({
      icon: 'success',
      title: 'Account Confirmed',
      text: 'Your student account is ready. You can now continue to your dashboard.',
      confirmButtonColor: '#2563eb',
    })

    router.replace('/intern')
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Setup Failed',
      text: error instanceof Error ? error.message : 'Could not finish account setup.',
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
      <div class="icon-row">
        <img src="/icons/logo-main.png" alt="OJT Intern Path" class="register-logo" />
      </div>

      <h1>Create Your Password</h1>
      <p class="subtitle">Finish setting up your student account through the secure email link.</p>

      <div v-if="loading" class="notice">
        Validating your setup link...
      </div>

      <div v-else-if="!valid" class="error-panel">
        <p>{{ errorMessage }}</p>
        <p class="helper-copy">Ask your school admin to send you a new setup link.</p>
      </div>

      <template v-else>
        <div class="notice">
          Account for {{ inviteName || 'student' }} ({{ inviteEmail }})
        </div>

        <form class="form" @submit.prevent="onSubmit">
          <label class="field">
            <span>New Password</span>
            <div class="password-field">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Create your password"
                required
              />
              <button type="button" class="toggle-password" @click="showPassword = !showPassword">
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
          </label>

          <label class="field">
            <span>Confirm Password</span>
            <div class="password-field">
              <input
                v-model="confirmPassword"
                :type="showConfirmPassword ? 'text' : 'password'"
                placeholder="Confirm your password"
                required
              />
              <button type="button" class="toggle-password" @click="showConfirmPassword = !showConfirmPassword">
                {{ showConfirmPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
          </label>

          <small v-if="password && password.length < 8" class="error-text">
            Password must be at least 8 characters.
          </small>
          <small v-if="passwordMismatch" class="error-text">
            Passwords do not match.
          </small>

          <button type="submit" class="primary-btn" :disabled="!isValid || saving">
            {{ saving ? 'Finishing Setup...' : 'Confirm Account' }}
          </button>
        </form>
      </template>
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

.error-panel {
  margin: 0 0 16px;
  border-radius: 12px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #b91c1c;
  padding: 14px;
  font-size: 13px;
}

.helper-copy {
  margin-top: 8px;
  color: #7f1d1d;
}

.error-text {
  color: #dc2626;
  font-size: 11px;
}
</style>

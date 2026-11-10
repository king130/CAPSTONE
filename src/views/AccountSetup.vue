<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from '@/services/swal'
import { useAuthStore } from '@/stores/auth'
import { completeAccountSetup, validateAccountSetupToken, type UserRole } from '@/services/auth'
import { medianAppUrl } from '@/utils/medianAppLink'
import PasswordInput from '@/components/ui/password-input/PasswordInput.vue'
import { isChangePasswordFormValid, passwordPolicyError } from '@/utils/passwordPolicy'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const token = computed(() => String(route.query.token ?? '').trim())
const loading = ref(true)
const saving = ref(false)
const valid = ref(false)
const inviteEmail = ref('')
const inviteName = ref('')
const inviteRole = ref<string | null>(null)
const errorMessage = ref('')
const password = ref('')
const confirmPassword = ref('')
const continueInBrowser = ref(false)

/** Student invites keep the exact existing copy and /intern redirect. */
const isStudentInvite = computed(() => {
  const role = (inviteRole.value || 'student').toLowerCase()
  return role === 'student'
})

function dashboardForRole(role: UserRole): string {
  switch (role) {
    case 'admin':
      return '/admin/overview'
    case 'company':
    case 'municipal_hall':
      return '/dashboard'
    case 'school':
      return '/school'
    case 'student':
      return '/intern'
    default:
      return '/guest'
  }
}

const passwordMismatch = computed(() => !!confirmPassword.value && password.value !== confirmPassword.value)
const passwordError = computed(() => passwordPolicyError(password.value))
const isValid = computed(() => isChangePasswordFormValid(password.value, confirmPassword.value))

const insideApp = computed(() => {
  if (typeof window === 'undefined') return false
  return Boolean((window as Window & { median?: unknown }).median)
})

const canOfferAppOpen = computed(() => {
  if (typeof window === 'undefined' || insideApp.value) return false
  return /Android|iPhone|iPad|iPod/i.test(window.navigator.userAgent)
})

const showChooser = computed(() => canOfferAppOpen.value && !continueInBrowser.value && valid.value)

const appDeepLink = computed(() => {
  if (typeof window === 'undefined') return null
  return medianAppUrl(String(import.meta.env.VITE_MEDIAN_APP_SCHEME ?? ''), window.location.href)
})

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
    inviteRole.value = data.role ?? 'student'
    valid.value = true
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : 'This account setup link is invalid or expired.'
  } finally {
    loading.value = false
  }
})

function openInApp() {
  const target = appDeepLink.value
  if (!target) return
  window.location.href = target
}

function stayInBrowser() {
  continueInBrowser.value = true
}

async function onSubmit() {
  if (!isValid.value || !token.value) return

  saving.value = true
  try {
    const profile = await completeAccountSetup(token.value, password.value)
    authStore.setUserProfile(profile)

    if (isStudentInvite.value) {
      await Swal.fire({
        icon: 'success',
        title: 'Account Confirmed',
        text: 'Your student account is ready. You can now continue to your dashboard.',
        confirmButtonColor: '#2563eb',
      })
      router.replace('/intern')
    } else {
      await Swal.fire({
        icon: 'success',
        title: 'Account Confirmed',
        text: 'Your account is ready. You can now continue to your dashboard.',
        confirmButtonColor: '#2563eb',
      })
      router.replace(dashboardForRole(profile.role))
    }
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
      <p class="subtitle">
        {{
          isStudentInvite
            ? 'Finish setting up your student account through the secure email link.'
            : 'Finish setting up your account through the secure email link.'
        }}
      </p>

      <div v-if="loading" class="notice">
        Validating your setup link...
      </div>

      <div v-else-if="!valid" class="error-panel">
        <p>{{ errorMessage }}</p>
        <p class="helper-copy">
          {{
            isStudentInvite
              ? 'Ask your school admin to send you a new setup link.'
              : 'Ask your organization admin to send you a new setup link.'
          }}
        </p>
      </div>

      <template v-else-if="showChooser">
        <div class="notice">
          Account for {{ inviteName || (isStudentInvite ? 'student' : 'user') }} ({{ inviteEmail }})
        </div>
        <p class="chooser-copy">
          Prefer the installed app for a smoother phone experience, or continue here in your browser.
        </p>
        <div class="chooser-actions">
          <button type="button" class="primary-btn" @click="openInApp">Open in app</button>
          <button type="button" class="secondary-btn" @click="stayInBrowser">Continue in browser</button>
        </div>
      </template>

      <template v-else>
        <div class="notice">
          Account for {{ inviteName || (isStudentInvite ? 'student' : 'user') }} ({{ inviteEmail }})
        </div>

        <form class="form" @submit.prevent="onSubmit">
          <label class="field">
            <span>New Password</span>
            <PasswordInput
              v-model="password"
              autocomplete="new-password"
              inputmode="text"
              placeholder="Create your password"
              required
              minlength="8"
            />
            <small v-if="passwordError" class="error-text">{{ passwordError }}</small>
          </label>

          <label class="field">
            <span>Confirm Password</span>
            <PasswordInput
              v-model="confirmPassword"
              autocomplete="new-password"
              inputmode="text"
              placeholder="Confirm your password"
              required
              minlength="8"
            />
            <small v-if="passwordMismatch" class="error-text">Passwords do not match.</small>
          </label>

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
  display: block;
  margin-top: 6px;
  color: #dc2626;
  font-size: 12px;
}

.chooser-copy {
  margin: 0 0 16px;
  color: #475569;
  font-size: 14px;
  line-height: 1.45;
}

.chooser-actions {
  display: grid;
  gap: 10px;
}

.secondary-btn {
  min-height: 44px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #0f172a;
  font-weight: 600;
  padding: 12px 16px;
}

.primary-btn {
  min-height: 44px;
}

.field :deep(input) {
  font-size: 16px;
}
</style>

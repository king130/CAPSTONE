<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, Clock3, LogOut, RefreshCw, ShieldCheck } from 'lucide-vue-next'

import OrganizationVerificationDocumentsPanel from '@/components/OrganizationVerificationDocumentsPanel.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Input from '@/components/ui/input/Input.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { pendingOrganizationNavItems, type LayoutRole } from '@/layouts/navigation'
import { resendEmailOtp, verifyEmailOtp } from '@/services/auth'
import {
  organizationTypeLabel,
  verificationStatusLabel,
  type OrganizationVerificationMeta,
} from '@/services/organizationVerification'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const { success, error } = useToast()

const organizationType = computed(() =>
  String(authStore.user?.activeOrganization?.type || authStore.user?.profile?.organizationType || '').toLowerCase(),
)
const isMunicipalHall = computed(() => organizationType.value === 'municipal_hall')
const isHost = computed(() => organizationType.value === 'company' || isMunicipalHall.value)
const role = computed<LayoutRole>(() => (isHost.value ? 'company' : 'school'))
const status = computed(() => String(authStore.user?.verificationStatus || 'pending').toLowerCase())
const isRejected = computed(() => status.value === 'rejected')
const isVerified = computed(() => status.value === 'approved')
const isUnderReview = computed(() => status.value === 'under_review')
const needsEmailOtp = computed(() => Boolean(authStore.user?.emailVerificationRequired))
const orgName = computed(() => {
  const profile = authStore.user?.profile || {}
  if (role.value === 'school') {
    return String(profile.institutionName || authStore.user?.activeOrganization?.name || 'Your school')
  }
  if (isMunicipalHall.value) {
    return String(
      profile.officialName || profile.companyName || authStore.user?.activeOrganization?.name || 'Your municipal hall',
    )
  }
  return String(profile.companyName || authStore.user?.activeOrganization?.name || 'Your company')
})
const rejectionReason = computed(() => {
  return (
    authStore.user?.verificationRejectionReason ||
    (typeof authStore.user?.profile?.verificationRejectionReason === 'string'
      ? authStore.user.profile.verificationRejectionReason
      : '') ||
    ''
  )
})

const panelRef = ref<{ reload: () => Promise<void> } | null>(null)
const otpCode = ref('')
const otpSubmitting = ref(false)
const otpResending = ref(false)
const resendCooldown = ref(0)
let resendTimer: number | null = null

const statusBadgeVariant = computed(() => {
  if (isVerified.value) return 'success' as const
  if (isRejected.value) return 'destructive' as const
  return 'warning' as const
})

function startResendCooldown(seconds = 60) {
  resendCooldown.value = seconds
  if (resendTimer !== null) window.clearInterval(resendTimer)
  resendTimer = window.setInterval(() => {
    resendCooldown.value = Math.max(0, resendCooldown.value - 1)
    if (resendCooldown.value <= 0 && resendTimer !== null) {
      window.clearInterval(resendTimer)
      resendTimer = null
    }
  }, 1000)
}

async function onDocsRefreshed(meta: OrganizationVerificationMeta) {
  if (meta.verificationStatus && meta.verificationStatus !== status.value) {
    await authStore.refreshUser()
  }
}

async function refreshStatus() {
  try {
    const profile = await authStore.refreshUser()
    const next = String(profile?.verificationStatus || '').toLowerCase()
    if (next === 'approved') {
      success('Organization verified.', {
        description: 'You can now use organization features.',
      })
      await router.replace(isHost.value ? '/dashboard' : '/school')
      return
    }
    await panelRef.value?.reload()
    success('Status refreshed.')
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to refresh verification status.' })
  }
}

async function submitOtp() {
  const code = otpCode.value.replace(/\D+/g, '')
  if (code.length !== 6) {
    error(new Error('Enter the 6-digit code from your email.'), {
      fallback: 'Enter the 6-digit code from your email.',
    })
    return
  }
  otpSubmitting.value = true
  try {
    const profile = await verifyEmailOtp(code)
    authStore.setUserProfile(profile)
    otpCode.value = ''
    success('Email verified.', {
      description: 'You can now upload verification documents.',
    })
  } catch (caughtError) {
    error(caughtError, { fallback: 'Could not verify that code.' })
  } finally {
    otpSubmitting.value = false
  }
}

async function resendOtp() {
  if (resendCooldown.value > 0 || otpResending.value) return
  otpResending.value = true
  try {
    await resendEmailOtp()
    startResendCooldown(60)
    success('Verification code sent.', {
      description: 'Check your email for a new 6-digit code.',
    })
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Could not send the verification email. Please try Resend again.',
    })
  } finally {
    otpResending.value = false
  }
}

/** Stale sessions keep pending after admin approve until profile is re-fetched. */
onMounted(async () => {
  try {
    const profile = await authStore.refreshUser()
    const next = String(profile?.verificationStatus || '').toLowerCase()
    if (next === 'approved' && !profile?.emailVerificationRequired) {
      await router.replace(isHost.value ? '/dashboard' : '/school')
    }
  } catch {
    /* keep verification page if refresh fails */
  }
})

onUnmounted(() => {
  if (resendTimer !== null) window.clearInterval(resendTimer)
})

async function logout() {
  await authStore.logout()
  await router.replace('/login')
}
</script>

<template>
  <MainLayout
    :role="role"
    title="Organization Verification"
    active-item="verification"
    :nav-items="pendingOrganizationNavItems"
  >
    <div class="mx-auto flex min-h-[70vh] max-w-3xl items-center justify-center px-4 py-10">
      <Card class="w-full border-border/80 shadow-sm">
        <CardContent class="space-y-6 p-6 sm:p-8">
          <div class="flex items-start gap-4">
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-muted text-foreground"
            >
              <ShieldCheck v-if="isVerified" class="h-6 w-6" />
              <AlertTriangle v-else-if="isRejected" class="h-6 w-6" />
              <Clock3 v-else class="h-6 w-6" />
            </div>
            <div class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                  {{ organizationTypeLabel(organizationType) }} verification
                </p>
                <Badge :variant="statusBadgeVariant">{{ verificationStatusLabel(status) }}</Badge>
              </div>
              <h1 class="text-2xl font-semibold text-foreground">
                <template v-if="needsEmailOtp">Verify your email first</template>
                <template v-else-if="isVerified">{{ orgName }} is verified</template>
                <template v-else-if="isRejected">{{ orgName }} was not approved</template>
                <template v-else-if="isUnderReview">{{ orgName }} is under review</template>
                <template v-else>{{ orgName }} needs verification documents</template>
              </h1>
              <p class="text-sm leading-6 text-muted-foreground">
                <template v-if="needsEmailOtp">
                  Enter the 6-digit code sent to
                  <span class="font-medium text-foreground">{{ authStore.user?.email }}</span>
                  before uploading documents.
                </template>
                <template v-else-if="isRejected">
                  Review the reason below, fix or replace the documents that need attention, then submit again for
                  review.
                </template>
                <template v-else-if="isUnderReview">
                  An administrator is reviewing your uploaded documents. You can still manage account settings and
                  notifications while you wait.
                </template>
                <template v-else-if="isVerified">
                  Your {{ organizationTypeLabel(organizationType).toLowerCase() }} can use protected features. You may
                  optionally upload any missing documents below.
                </template>
                <template v-else>
                  Upload the required documents for your
                  {{ organizationTypeLabel(organizationType).toLowerCase() }} before an administrator can verify the
                  account. Verification is granted only by an administrator — not automatically when documents are
                  uploaded.
                </template>
              </p>
            </div>
          </div>

          <div
            v-if="needsEmailOtp"
            class="space-y-4 rounded-2xl border border-border bg-muted/40 px-4 py-4"
            data-testid="email-otp-step"
          >
            <p class="text-sm font-semibold text-foreground">Email verification code</p>
            <Input
              id="email-otp-code"
              v-model="otpCode"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              placeholder="6-digit code"
              class="max-w-xs tracking-[0.3em]"
              @keyup.enter="submitOtp"
            />
            <div class="flex flex-wrap gap-3">
              <Button :disabled="otpSubmitting" @click="submitOtp">
                {{ otpSubmitting ? 'Verifying…' : 'Verify email' }}
              </Button>
              <Button variant="outline" :disabled="otpResending || resendCooldown > 0" @click="resendOtp">
                <template v-if="resendCooldown > 0">Resend in {{ resendCooldown }}s</template>
                <template v-else-if="otpResending">Sending…</template>
                <template v-else>Resend code</template>
              </Button>
            </div>
            <p class="text-xs text-muted-foreground">
              Didn’t get the email? Check spam, then use Resend. Document upload stays locked until the code is
              verified.
            </p>
          </div>

          <template v-else>
            <div
              v-if="isRejected"
              class="rounded-2xl border border-destructive/40 bg-destructive/10 px-4 py-4 text-sm text-foreground"
            >
              <p class="font-semibold">Rejection / fix reason</p>
              <p class="mt-1 leading-6 text-muted-foreground">
                {{ rejectionReason || 'No reason was provided. Contact the platform administrator for details.' }}
              </p>
            </div>

            <OrganizationVerificationDocumentsPanel
              ref="panelRef"
              :show-approved-notice="true"
              @refreshed="onDocsRefreshed"
            />

            <div class="rounded-2xl border border-border bg-muted px-4 py-4 text-sm text-foreground">
              <p class="font-semibold text-foreground">What you can do now</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 leading-6 text-muted-foreground">
                <li>Upload or replace supporting documents for verification</li>
                <li>Open Settings to review your account details</li>
                <li>Check Notifications for verification updates</li>
                <li>Use Refresh status after an administrator reviews your organization</li>
              </ul>
            </div>
          </template>

          <div class="flex flex-wrap gap-3">
            <Button v-if="!needsEmailOtp" @click="refreshStatus">
              <RefreshCw class="mr-2 h-4 w-4" />
              Refresh status
            </Button>
            <Button variant="outline" @click="router.push('/settings')">Open settings</Button>
            <Button variant="outline" @click="router.push('/notifications')">Notifications</Button>
            <Button variant="ghost" @click="logout">
              <LogOut class="mr-2 h-4 w-4" />
              Log out
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { AlertTriangle, Clock3, LogOut, RefreshCw } from 'lucide-vue-next'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import type { LayoutNavItem } from '@/layouts/navigation'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()
const { success, error } = useToast()

const role = computed(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const status = computed(() => String(authStore.user?.verificationStatus || 'pending').toLowerCase())
const isRejected = computed(() => status.value === 'rejected')
const orgName = computed(() => {
  const profile = authStore.user?.profile || {}
  if (role.value === 'school') {
    return String(profile.institutionName || authStore.user?.activeOrganization?.name || 'Your school')
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

const navItems = computed<LayoutNavItem[]>(() => [
  { key: 'verification', label: 'Verification', icon: 'clock', to: { name: 'organization-verification' } },
  { key: 'notifications', label: 'Notifications', icon: 'file-text', to: { name: 'notifications' } },
  { key: 'settings', label: 'Settings', icon: 'settings', to: { name: 'settings' } },
])

async function refreshStatus() {
  try {
    const profile = await authStore.refreshUser()
    const next = String(profile?.verificationStatus || '').toLowerCase()
    if (next === 'approved') {
      success('Organization approved.', {
        description: 'You can now use organization features.',
      })
      await router.replace(role.value === 'company' ? '/dashboard' : '/school')
      return
    }
    success('Status refreshed.')
  } catch (caughtError) {
    error(caughtError, { fallback: 'Unable to refresh verification status.' })
  }
}

async function logout() {
  await authStore.logout()
  await router.replace('/login')
}

onMounted(() => {
  void refreshStatus()
})
</script>

<template>
  <MainLayout :role="role" title="Organization Verification" active-item="verification" :nav-items="navItems">
    <div class="mx-auto flex min-h-[70vh] max-w-3xl items-center justify-center px-4 py-10">
      <Card class="w-full border-border/80 shadow-sm">
        <CardContent class="space-y-6 p-8">
          <div class="flex items-start gap-4">
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
              :class="isRejected ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'"
            >
              <AlertTriangle v-if="isRejected" class="h-6 w-6" />
              <Clock3 v-else class="h-6 w-6" />
            </div>
            <div class="space-y-2">
              <p class="text-sm font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                {{ isRejected ? 'Verification rejected' : 'Verification pending' }}
              </p>
              <h1 class="text-2xl font-semibold text-foreground">
                {{ isRejected ? `${orgName} was not approved` : `${orgName} is awaiting admin verification` }}
              </h1>
              <p class="text-sm leading-6 text-muted-foreground">
                <template v-if="isRejected">
                  Your organization cannot access protected organization features while verification remains rejected.
                  You can still manage your account settings, review notifications, and contact the platform administrator.
                </template>
                <template v-else>
                  An administrator still needs to review your organization before you can manage internships, students,
                  agreements, and other organization tools. You can still update your profile, change your password, and
                  check notifications while you wait.
                </template>
              </p>
            </div>
          </div>

          <div
            v-if="isRejected"
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-800"
          >
            <p class="font-semibold">Rejection reason</p>
            <p class="mt-1 leading-6">
              {{ rejectionReason || 'No rejection reason was provided. Contact the platform administrator for details.' }}
            </p>
          </div>

          <div class="rounded-2xl border border-border bg-muted px-4 py-4 text-sm text-foreground">
            <p class="font-semibold text-foreground">What you can do now</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 leading-6">
              <li>Open Settings to review your account details</li>
              <li>Check Notifications for verification updates</li>
              <li>Use Refresh status after an administrator reviews your organization</li>
              <li v-if="isRejected">Contact the platform administrator if you need the decision revisited</li>
            </ul>
          </div>

          <div class="flex flex-wrap gap-3">
            <Button @click="refreshStatus">
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

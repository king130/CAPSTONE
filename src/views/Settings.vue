<script setup lang="ts">
import { computed } from 'vue'

import SettingsPanel from '@/components/layout/SettingsPanel.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import {
  isPendingOrRejectedOrganization,
  pendingOrganizationNavItems,
  type LayoutRole,
} from '@/layouts/navigation'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

const layoutRole = computed<LayoutRole>(() => {
  if (authStore.user?.role === 'admin') return 'admin'
  if (authStore.user?.role === 'company') return 'company'
  if (authStore.user?.role === 'school') return 'school'
  return 'student'
})

const usePendingOrgNav = computed(() => isPendingOrRejectedOrganization(authStore.user))
</script>

<template>
  <MainLayout
    :role="layoutRole"
    title="Settings"
    :active-item="usePendingOrgNav ? 'settings' : undefined"
    :nav-items="usePendingOrgNav ? pendingOrganizationNavItems : undefined"
  >
    <SettingsPanel />
  </MainLayout>
</template>

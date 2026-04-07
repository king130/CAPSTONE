<script setup lang="ts">
  import { defineAsyncComponent, computed } from 'vue'
  import { RouterView } from 'vue-router'
  import { Toaster } from 'vue-sonner'

  import { useTheme } from '@/composables/useTheme'
  import { useAuthStore } from '@/stores/auth'

  const FloatingChatWidget = defineAsyncComponent(() => import('@/components/FloatingChatWidget.vue'))

  const { isDark } = useTheme()
  const toasterTheme = computed(() => (isDark.value ? 'dark' : 'light'))
  const authStore = useAuthStore()
  const showChat = computed(() => {
    const role = authStore.user?.role
    return role === 'student' || role === 'school' || role === 'company'
  })
</script>

<template>
  <RouterView />
  <FloatingChatWidget v-if="showChat" />
  <Toaster
    close-button
    rich-colors
    position="top-right"
    :theme="toasterTheme"
  />
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import { onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { AUTH_DISABLED } from '@/config/auth'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()

onMounted(() => {
  if (!AUTH_DISABLED) authStore.init()
})

watch(
  () => authStore.user?.uid,
  (uid) => {
    if (AUTH_DISABLED) return // Skip notifications when auth disabled (avoids Firestore index errors)
    if (uid) {
      try {
        notificationStore.start(uid)
      } catch {
        // Ignore - index may still be building
      }
    } else {
      notificationStore.stop()
    }
  },
  { immediate: true },
)
</script>

<template>
  <RouterView />
</template>

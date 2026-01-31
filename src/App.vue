<script setup lang="ts">
import { RouterView } from 'vue-router'
import { onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'

const authStore = useAuthStore()
const notificationStore = useNotificationStore()

onMounted(() => {
  authStore.init()
})

watch(
  () => authStore.user?.uid,
  (uid) => {
    if (uid) {
      notificationStore.start(uid)
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

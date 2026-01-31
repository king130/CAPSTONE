<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const message = computed(() => authStore.blockedReason || 'Your account has been disabled.')
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>Account Disabled</h1>
      <p>{{ message }}</p>
      <RouterLink to="/login" class="btn">Back to Login</RouterLink>
    </div>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f5f7fb;
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
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
  text-align: center;
}

h1 {
  margin: 0 0 8px;
  font-size: 22px;
}

p {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 20px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 14px;
  border-radius: 8px;
  background: #2563eb;
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  font-size: 14px;
}
</style>

<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

async function handleLogout() {
  await authStore.logout()
  router.push('/admin/login')
}
</script>

<template>
  <header class="admin-nav">
    <div class="brand">Admin Console</div>
    <nav class="links">
      <RouterLink to="/admin/overview">Dashboard</RouterLink>
      <RouterLink to="/admin/users">Users</RouterLink>
      <RouterLink to="/admin/temporary">Temporary Accounts</RouterLink>
    </nav>
    <button class="logout" type="button" @click="handleLogout">Logout</button>
  </header>
</template>

<style scoped>
.admin-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  background: #1e3a8a;
  color: #fff;
}

.brand {
  font-size: 16px;
  font-weight: 700;
}

.links {
  display: flex;
  gap: 16px;
}

.links a {
  color: #e2e8f0;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
}

.links a.router-link-active {
  color: #fff;
  text-decoration: underline;
}

.logout {
  padding: 8px 12px;
  border-radius: 8px;
  border: none;
  background: #2563eb;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}
</style>

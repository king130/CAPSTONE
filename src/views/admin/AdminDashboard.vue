<script setup lang="ts">
import AdminNavbar from '@/components/AdminNavbar.vue'
</script>

<template>
  <div class="admin-layout">
    <AdminNavbar />
    <main class="content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.admin-layout {
  min-height: 100vh;
  background: #f9fafb;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.content {
  padding: 24px;
  margin-top: 60px;
  min-height: calc(100vh - 60px);
}

@media (max-width: 768px) {
  .content {
    padding: 16px;
    margin-top: 60px;
  }
}
</style>

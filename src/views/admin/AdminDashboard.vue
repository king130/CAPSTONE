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
  background: #f8fafc;
}

.content {
  padding: 24px;
}
</style>

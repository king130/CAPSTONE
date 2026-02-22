<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// Define props for active menu item
const props = defineProps<{
  activeItem?: string
}>()

// Define emits for menu item clicks
const emit = defineEmits<{
  menuClick: [item: string]
}>()

async function handleMenuClick(itemId: string) {
  if (itemId === 'logout') {
    await authStore.logout()
  }
  emit('menuClick', itemId)
}
</script>

<template>
  <aside class="intern-sidebar">
    <div class="sidebar-logo">
      <img src="/icons/logo-main.png" alt="OJT Intern Path" class="logo-icon" />
      <span>OJT Intern Path</span>
    </div>

    <nav class="sidebar-nav">
      <div
        class="nav-item"
        :class="{ active: activeItem === 'dashboard' }"
        @click="handleMenuClick('dashboard')"
      >
        <img src="/icons/icon-dashboard.png" alt="Dashboard" class="nav-icon-img" />
        <span>Dashboard</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeItem === 'internship' }"
        @click="handleMenuClick('internship')"
      >
        <img src="/icons/icon-internship.png" alt="Internship" class="nav-icon-img" />
        <span>Internship</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeItem === 'documents' }"
        @click="handleMenuClick('documents')"
      >
        <img src="/icons/icon-journal.png" alt="Documents" class="nav-icon-img" />
        <span>Documents</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeItem === 'readiness-check' }"
        @click="handleMenuClick('readiness-check')"
      >
        <img src="/icons/icon-evaluation.png" alt="Readiness Check" class="nav-icon-img" />
        <span>Readiness Check</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeItem === 'community' }"
        @click="handleMenuClick('community')"
      >
        <img src="/icons/icon-community.png" alt="Community" class="nav-icon-img" />
        <span>Community</span>
      </div>
    </nav>

    <div class="sidebar-bottom">
      <div
        class="nav-item"
        @click="handleMenuClick('logout')"
      >
        <img src="/icons/icons-logout.png" alt="Logout" class="nav-icon-img" />
        <span>Logout</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeItem === 'settings' }"
        @click="handleMenuClick('settings')"
      >
        <img src="/icons/icon-settings.png" alt="Settings" class="nav-icon-img" />
        <span>Settings</span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.intern-sidebar {
  width: 280px;
  background: #1e3a8a;
  color: #fff;
  display: flex;
  flex-direction: column;
  padding: 20px 0;
  position: fixed !important;
  left: 0 !important;
  top: 0 !important;
  height: 100vh;
  overflow-y: auto;
  z-index: 1000;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 20px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  margin-bottom: 20px;
}

.logo-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.sidebar-logo span {
  font-weight: 700;
  font-size: 16px;
}

.sidebar-nav {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  color: #cbd5e1;
  text-decoration: none;
  transition: all 0.2s;
  font-size: 14px;
  cursor: pointer;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

.nav-item.active {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  border-right: 3px solid #60a5fa;
}

.nav-icon-img {
  width: 20px;
  height: 20px;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(84%) sepia(8%) saturate(1458%) hue-rotate(202deg) brightness(92%) contrast(92%);
}

.nav-item.active .nav-icon-img {
  filter: brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(7500%) hue-rotate(109deg) brightness(107%) contrast(107%);
}

.nav-item:hover .nav-icon-img {
  filter: brightness(0) saturate(100%) invert(100%) sepia(0%) saturate(7500%) hue-rotate(109deg) brightness(107%) contrast(107%);
}

.sidebar-bottom {
  padding: 0 0 20px 0;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* Responsive */
@media (max-width: 768px) {
  .intern-sidebar {
    width: 60px;
    padding: 16px 0;
  }
  
  .sidebar-logo {
    padding: 0 16px 16px;
    justify-content: center;
  }
  
  .sidebar-logo span {
    display: none;
  }
  
  .nav-item {
    padding: 12px;
    justify-content: center;
  }
  
  .nav-item span {
    display: none;
  }
}
</style>
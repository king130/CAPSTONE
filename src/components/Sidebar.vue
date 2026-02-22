<template>
  <aside class="sidebar">
    <div class="sidebar-logo">
      <img src="/icons/logo-main.png" alt="OJT Intern Path" class="logo-icon" />
      <span>OJT Intern Path</span>
    </div>

    <nav class="sidebar-nav">
      <div
        class="nav-item"
        :class="{ active: activeNav === 'dashboard' }"
        @click="setActiveNav('dashboard')"
      >
        <img src="/icons/icon-dashboard.png" alt="Dashboard" class="nav-icon-img" />
        <span>Dashboard</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'internships' }"
        @click="setActiveNav('internships')"
      >
        <img src="/icons/icon-internship.png" alt="Internships" class="nav-icon-img" />
        <span>Internships</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'applications' }"
        @click="setActiveNav('applications')"
      >
        <img src="/icons/icon-application.png" alt="Applications" class="nav-icon-img" />
        <span>Applications</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'journals' }"
        @click="setActiveNav('journals')"
      >
        <img src="/icons/icon-journal.png" alt="Journals" class="nav-icon-img" />
        <span>Journals</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'time-tracking' }"
        @click="setActiveNav('time-tracking')"
      >
        <img src="/icons/icon-timetracking.png" alt="Time tracking" class="nav-icon-img" />
        <span>Time tracking</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'evaluation' }"
        @click="setActiveNav('evaluation')"
      >
        <img src="/icons/icon-evaluation.png" alt="Evaluation" class="nav-icon-img" />
        <span>Evaluation</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'reports' }"
        @click="setActiveNav('reports')"
      >
        <img src="/icons/icon-reports.png" alt="Reports" class="nav-icon-img" />
        <span>Reports</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'profile' }"
        @click="setActiveNav('profile')"
      >
        <img src="/icons/icon-profile.png" alt="Profile" class="nav-icon-img" />
        <span>Profile</span>
      </div>
    </nav>

    <div class="sidebar-bottom">
      <div
        class="nav-item"
        @click="setActiveNav('logout')"
      >
        <img src="/icons/icons-logout.png" alt="Logout" class="nav-icon-img" />
        <span>Logout</span>
      </div>
      <div
        class="nav-item"
        :class="{ active: activeNav === 'settings' }"
        @click="setActiveNav('settings')"
      >
        <img src="/icons/icon-settings.png" alt="Settings" class="nav-icon-img" />
        <span>Settings</span>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const activeNav = ref('dashboard')

const setActiveNav = (nav: string) => {
  if (nav === 'logout') {
    // Handle logout - redirect to login page
    handleLogout()
    return
  }
  
  activeNav.value = nav
  // Emit event to parent component
  emit('nav-changed', nav)
}

const handleLogout = async () => {
  await authStore.logout()
  localStorage.removeItem('authToken')
  localStorage.removeItem('userSession')
  sessionStorage.clear()
  router.push('/login')
}

// Define emits
const emit = defineEmits<{
  'nav-changed': [nav: string]
}>()

// Expose activeNav for parent component
defineExpose({
  activeNav,
  setActiveNav
})
</script>

<style scoped>
.sidebar {
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

.nav-icon {
  font-size: 18px;
  width: 20px;
  text-align: center;
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

@media (max-width: 768px) {
  .sidebar {
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
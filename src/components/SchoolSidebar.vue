<template>
  <aside class="school-sidebar">
    <!-- Logo Section -->
    <div class="sidebar-logo">
      <img src="/icons/logo-main.png" alt="OJT Intern Path" class="logo-icon" />
      <span class="logo-text">OJT Intern Path</span>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
      <div
        class="nav-item"
        :class="{ active: activeItem === 'dashboard' }"
        @click="setActiveItem('dashboard')"
      >
        <img src="/icons/icon-dashboard.png" alt="Dashboard" class="nav-icon-img" />
        <span>Dashboard</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'internship-post' }"
        @click="setActiveItem('internship-post')"
      >
        <img src="/icons/icon-post_new_internship.png" alt="Internship post" class="nav-icon-img" />
        <span>Internship post</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'applications' }"
        @click="setActiveItem('applications')"
      >
        <img src="/icons/icon-application.png" alt="Internship Applications" class="nav-icon-img" />
        <span>Internship Applications</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'companies' }"
        @click="setActiveItem('companies')"
      >
        <img src="/icons/company-icon.png" alt="Companies" class="nav-icon-img" />
        <span>Companies</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'community' }"
        @click="setActiveItem('community')"
      >
        <img src="/icons/icon-community.png" alt="Community" class="nav-icon-img" />
        <span>Community</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'documents' }"
        @click="setActiveItem('documents')"
      >
        <img src="/icons/icon-docs.png" alt="Documents" class="nav-icon-img" />
        <span>Documents</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'reports' }"
        @click="setActiveItem('reports')"
      >
        <img src="/icons/icon-reports.png" alt="Reports" class="nav-icon-img" />
        <span>Reports</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'student-interns' }"
        @click="setActiveItem('student-interns')"
      >
        <img src="/icons/icon-student.png" alt="Student Interns" class="nav-icon-img" />
        <span>Student Interns</span>
      </div>
    </nav>

    <!-- Bottom Section -->
    <div class="sidebar-bottom">
      <div
        class="nav-item"
        @click="logout"
      >
        <img src="/icons/icons-logout.png" alt="Logout" class="nav-icon-img" />
        <span>Logout</span>
      </div>

      <div
        class="nav-item"
        :class="{ active: activeItem === 'settings' }"
        @click="setActiveItem('settings')"
      >
        <img src="/icons/icon-settings.png" alt="Settings" class="nav-icon-img" />
        <span>Settings</span>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

interface Props {
  activeItem?: string
}

const props = withDefaults(defineProps<Props>(), {
  activeItem: 'dashboard'
})

const emit = defineEmits<{
  menuClick: [item: string]
}>()

const router = useRouter()
const authStore = useAuthStore()

function setActiveItem(item: string) {
  emit('menuClick', item)
}

async function logout() {
  try {
    await authStore.logout()
    await router.push('/login')
  } catch (error) {
    console.error('Logout failed:', error)
  }
}
</script>

<style scoped>
.school-sidebar {
  width: 280px;
  height: 100vh;
  background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
  position: fixed;
  left: 0;
  top: 0;
  display: flex;
  flex-direction: column;
  z-index: 1000;
  overflow-y: auto;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.logo-text {
  color: white;
  font-size: 14px;
  font-weight: 500;
  letter-spacing: 0.5px;
}

.sidebar-nav {
  flex: 1;
  padding: 20px 0;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  color: rgba(255, 255, 255, 0.8);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 14px;
  font-weight: 500;
  margin: 2px 0;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.nav-item.active {
  background: rgba(255, 255, 255, 0.15);
  color: white;
  border-right: 3px solid #60a5fa;
}

.nav-icon-img {
  width: 20px;
  height: 20px;
  filter: brightness(0) invert(1);
  opacity: 0.8;
}

.nav-item.active .nav-icon-img,
.nav-item:hover .nav-icon-img {
  opacity: 1;
}

.sidebar-bottom {
  padding: 20px 0;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-bottom .nav-item {
  margin: 2px 0;
}

/* Scrollbar Styling */
.school-sidebar::-webkit-scrollbar {
  width: 4px;
}

.school-sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
}

.school-sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 2px;
}

.school-sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Responsive */
@media (max-width: 1024px) {
  .school-sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }

  .school-sidebar.open {
    transform: translateX(0);
  }
}
</style>
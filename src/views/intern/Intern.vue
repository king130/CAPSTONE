<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import InternSidebar from '@/components/InternSidebar.vue'
import InternDashboard from '@/components/InternDashboard.vue'
import InternDocuments from '@/components/InternDocuments.vue'
import InternReadinessCheck from '@/components/InternReadinessCheck.vue'
import InternInternship from '@/components/InternInternship.vue'
import InternMessages from '@/components/InternMessages.vue'
import InternSettings from '@/components/InternSettings.vue'
import FloatingChatWidget from '@/components/FloatingChatWidget.vue'
 
const authStore = useAuthStore()

const userInitials = computed(() => {
  const name = authStore.user?.displayName || authStore.user?.email || 'User'
  return name
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

// TEMPORARY DATA: Notification dropdown state - this is a UI state variable
const showNotifications = ref(false)

// TEMPORARY DATA: Static notifications for dropdown - replace with real data from backend
const notifications = ref([
  {
    id: 1,
    title: 'Application Update',
    message: 'Your application to TechCorp has been reviewed',
    time: '2 hours ago',
    unread: true
  },
  {
    id: 2,
    title: 'Interview Scheduled',
    message: 'Interview scheduled for May 20 at 2:00 PM',
    time: '5 hours ago',
    unread: true
  },
  {
    id: 3,
    title: 'Document Reminder',
    message: 'Please upload your resume',
    time: '1 day ago',
    unread: false
  },
  {
    id: 4,
    title: 'New Internship Match',
    message: 'You have 3 new internship matches',
    time: '2 days ago',
    unread: false
  }
])

function toggleNotifications() {
  showNotifications.value = !showNotifications.value
}

const organizationName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.schoolName as string) || authStore.user?.displayName || 'Account'
})

// TEMPORARY DATA: UI state variable for tracking active view - this is a UI state variable
const activeView = ref('dashboard')

// Handle sidebar menu clicks
function handleMenuClick(menuItem: string) {
  if (menuItem === 'logout') {
    // Handle logout
    console.log('Logging out...')
    return
  }
  
  activeView.value = menuItem
}
</script>

<template>
  <div class="intern-layout">
    <!-- Sidebar Component -->
    <InternSidebar 
      :activeItem="activeView" 
      @menuClick="handleMenuClick" 
    />

    <!-- Main Content Area -->
    <div class="main-content-area">
      <!-- Dashboard View -->
      <InternDashboard
        v-if="activeView === 'dashboard'"
        @openMessages="activeView = 'messages'"
        @navigateToProfile="activeView = 'settings'"
        @navigateToSection="activeView = $event"
      />
      
      <!-- Internship View -->
      <InternInternship v-else-if="activeView === 'internship'" @openMessages="activeView = 'messages'" @navigateToProfile="activeView = 'settings'" />
      
      <!-- Documents View -->
      <InternDocuments v-else-if="activeView === 'documents'" @openMessages="activeView = 'messages'" @navigateToProfile="activeView = 'settings'" />
      
      <!-- Readiness Check View -->
      <InternReadinessCheck v-else-if="activeView === 'readiness-check'" @openMessages="activeView = 'messages'" @navigateToProfile="activeView = 'settings'" />
      
      <!-- Messages View -->
      <InternMessages v-else-if="activeView === 'messages'" />
      
      <!-- Settings View -->
      <InternSettings v-else-if="activeView === 'settings'" />
      
      <!-- Other Views Placeholder -->
      <div v-else class="placeholder-view">
        <h2>{{ activeView.charAt(0).toUpperCase() + activeView.slice(1) }} View</h2>
        <p>This view is under development.</p>
      </div>
    </div>

    <!-- Floating Chat Widget -->
    <FloatingChatWidget userType="intern" />
  </div>
</template>

<style scoped>
.intern-layout {
  display: flex;
  min-height: 100vh;
  background: #f8fafc;
  font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
}

.main-content-area {
  flex: 1;
  margin-left: 280px;
  min-height: 100vh;
}

.placeholder-view {
  padding: 48px 24px;
  text-align: center;
  color: #6b7280;
}

.placeholder-view h2 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.placeholder-view p {
  font-size: 16px;
  margin: 0;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-content-area {
    margin-left: 0;
  }
}

@media (max-width: 768px) {
  .main-content-area {
    margin-left: 0;
  }
}
</style>

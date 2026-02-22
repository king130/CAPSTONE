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
import { 
  CameraIcon, 
  VideoCameraIcon, 
  DocumentTextIcon, 
  HandThumbUpIcon, 
  ChatBubbleLeftIcon, 
  ArrowPathIcon,
  BellIcon
} from '@heroicons/vue/24/outline'

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

// TEMPORARY DATA: User profile data for community view - replace with real data from backend/auth store
const currentUser = ref({
  name: 'Alex Doe',
  title: 'Full Stack Developer',
  avatar: '/icons/profiles/alex-doe.jpg',
  connections: 500,
  views: 25
})

// TEMPORARY DATA: Posts/updates data for community feed - replace with real data from backend
const posts = ref([
  {
    id: 1,
    author: {
      name: 'Maria Santos',
      title: 'Software Engineer',
      avatar: '/icons/profiles/maria-santos.jpg'
    },
    content: 'Just completed my first week at TechCorp! Excited to be working on cutting-edge projects and learning from amazing mentors. The team has been incredibly welcoming.',
    timestamp: '2h',
    likes: 12,
    comments: 3,
    shares: 1
  },
  {
    id: 2,
    author: {
      name: 'John Smith',
      title: 'UI/UX Designer',
      avatar: '/icons/profiles/john-smith.jpg'
    },
    content: 'Working on a new design system for our mobile app. The challenge is creating something that\'s both beautiful and accessible. Any tips from fellow designers?',
    timestamp: '4h',
    likes: 8,
    comments: 5,
    shares: 2
  },
  {
    id: 3,
    author: {
      name: 'Sarah Chen',
      title: 'Data Analyst',
      avatar: '/icons/profiles/sophia-martinez.jpg'
    },
    content: 'Fascinating insights from today\'s data analysis workshop! Machine learning is opening up so many possibilities for our internship projects.',
    timestamp: '6h',
    likes: 15,
    comments: 7,
    shares: 3
  }
])

// TEMPORARY DATA: Suggestions data for community sidebar - replace with real data from backend
const suggestions = ref([
  { name: 'Jessica Williams', title: 'Product Manager', avatar: '/icons/profiles/emily-johnson.jpg' },
  { name: 'Mike Rodriguez', title: 'Software Engineer', avatar: '/icons/profiles/robert-taylor.jpg' },
  { name: 'Emily Johnson', title: 'UX Designer', avatar: '/icons/profiles/emily-johnson.jpg' },
  { name: 'David Kim', title: 'Data Scientist', avatar: '/icons/profiles/david-kim.jpg' },
  { name: 'Lisa Wang', title: 'Frontend Developer', avatar: '/icons/profiles/sophia-martinez.jpg' },
  { name: 'Robert Taylor', title: 'DevOps Engineer', avatar: '/icons/profiles/robert-taylor.jpg' },
  { name: 'Sophia Martinez', title: 'Marketing Specialist', avatar: '/icons/profiles/sophia-martinez.jpg' }
])

// TEMPORARY DATA: Job listings data for community sidebar - replace with real data from backend
const jobListings = ref([
  {
    title: 'UI/UX Designer',
    company: 'TechCorp Solutions',
    location: 'Remote',
    type: 'Internship'
  },
  {
    title: 'Software Engineer',
    company: 'Innovation Labs',
    location: 'San Francisco, CA',
    type: 'Full-time'
  },
  {
    title: 'Data Analyst',
    company: 'DataFlow Inc.',
    location: 'New York, NY',
    type: 'Internship'
  },
  {
    title: 'Product Manager',
    company: 'StartupXYZ',
    location: 'Austin, TX',
    type: 'Full-time'
  },
  {
    title: 'Backend Developer',
    company: 'CloudTech',
    location: 'Seattle, WA',
    type: 'Internship'
  }
])

// TEMPORARY DATA: Most viewed profiles data for community sidebar - replace with real data from backend
const mostViewed = ref([
  { name: 'Jessica Williams', title: 'Product Manager', avatar: '/icons/profiles/emily-johnson.jpg' },
  { name: 'Mike Rodriguez', title: 'Software Engineer', avatar: '/icons/profiles/john-smith.jpg' },
  { name: 'Emily Johnson', title: 'UX Designer', avatar: '/icons/profiles/emily-johnson.jpg' },
  { name: 'David Kim', title: 'Data Scientist', avatar: '/icons/profiles/david-kim.jpg' },
  { name: 'Lisa Wang', title: 'Frontend Developer', avatar: '/icons/profiles/maria-santos.jpg' },
  { name: 'Robert Taylor', title: 'DevOps Engineer', avatar: '/icons/profiles/robert-taylor.jpg' },
  { name: 'Sophia Martinez', title: 'Marketing Specialist', avatar: '/icons/profiles/sophia-martinez.jpg' },
  { name: 'James Wilson', title: 'Business Analyst', avatar: '/icons/profiles/james-wilson.jpg' }
])

// Function to get initials for fallback avatars
function getInitials(name: string): string {
  return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

// Function to handle image load errors - simplified approach
function handleImageError(event: Event) {
  const img = event.target as HTMLImageElement
  const name = img.alt || 'User'
  const initials = getInitials(name)
  
  // Generate a simple colored background with CSS instead of canvas
  const colors = ['#2563eb', '#7c3aed', '#dc2626', '#059669', '#d97706', '#0891b2']
  const colorIndex = name.length % colors.length
  const color = colors[colorIndex]
  
  // Create a simple data URL for a colored square with initials
  const svg = `
    <svg width="100" height="100" xmlns="http://www.w3.org/2000/svg">
      <rect width="100" height="100" fill="${color}"/>
      <text x="50" y="50" font-family="Arial, sans-serif" font-size="36" font-weight="bold" 
            fill="white" text-anchor="middle" dominant-baseline="central">${initials}</text>
    </svg>
  `
  
  const dataUrl = 'data:image/svg+xml;base64,' + btoa(svg)
  img.src = dataUrl
}

function likePost(postId: number) {
  const post = posts.value.find(p => p.id === postId)
  if (post) {
    post.likes++
  }
}

function sharePost(postId: number) {
  const post = posts.value.find(p => p.id === postId)
  if (post) {
    post.shares++
  }
}

function connectWithUser(userName: string) {
  console.log('Connecting with:', userName)
}

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
      <InternDashboard v-if="activeView === 'dashboard'" @openMessages="activeView = 'messages'" @navigateToProfile="activeView = 'settings'" />
      
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
      
      <!-- Community View (Social Media Feed) -->
      <div v-else-if="activeView === 'community'" class="community-view">
        <!-- Simple Header -->
        <header class="community-header">
          <div class="header-left">
            <img src="/icons/icon-community.png" alt="Community" class="header-icon" />
            <h1 class="header-title">Community</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <div class="avatar" title="View Profile">{{ userInitials }}</div>
          </div>
        </header>

        <div class="community-container">
          <!-- Left Sidebar -->
          <aside class="left-sidebar">
            <!-- User Profile Card -->
            <div class="profile-card">
              <div class="profile-header">
                <img :src="currentUser.avatar" :alt="currentUser.name" class="profile-avatar" @error="handleImageError" />
                <h3 class="profile-name">{{ currentUser.name }}</h3>
                <p class="profile-title">{{ currentUser.title }}</p>
              </div>
              <div class="profile-stats">
                <div class="stat">
                  <span class="stat-number">{{ currentUser.connections }}</span>
                  <span class="stat-label">Connections</span>
                </div>
                <div class="stat">
                  <span class="stat-number">{{ currentUser.views }}</span>
                  <span class="stat-label">Profile views</span>
                </div>
              </div>
              <button class="view-profile-btn">View Profile</button>
            </div>

            <!-- Suggestions -->
            <div class="suggestions-card">
              <h4 class="suggestions-title">Suggestions</h4>
              <div class="suggestions-list">
                <div v-for="person in suggestions" :key="person.name" class="suggestion-item">
                  <img :src="person.avatar" :alt="person.name" class="suggestion-avatar" @error="handleImageError" />
                  <div class="suggestion-info">
                    <span class="suggestion-name">{{ person.name }}</span>
                    <span class="suggestion-title">{{ person.title }}</span>
                  </div>
                  <button class="connect-btn" @click="connectWithUser(person.name)" disabled title="Coming soon">+</button>
                </div>
              </div>
            </div>
          </aside>

          <!-- Main Content -->
          <main class="main-content">
            <!-- Post Creation -->
            <div class="post-creation">
              <div class="post-input-area">
                <img :src="currentUser.avatar" :alt="currentUser.name" class="post-avatar" @error="handleImageError" />
                <input type="text" placeholder="What's on your mind?" class="post-input" />
              </div>
              <div class="post-actions">
                <button class="post-action-btn">
                  <CameraIcon class="post-action-icon" />
                  Photo
                </button>
                <button class="post-action-btn">
                  <VideoCameraIcon class="post-action-icon" />
                  Video
                </button>
                <button class="post-action-btn">
                  <DocumentTextIcon class="post-action-icon" />
                  Document
                </button>
                <button class="post-btn">Post</button>
              </div>
            </div>

            <!-- Posts Feed -->
            <div class="posts-feed">
              <article v-for="post in posts" :key="post.id" class="post">
                <div class="post-header">
                  <img :src="post.author.avatar" :alt="post.author.name" class="post-author-avatar" @error="handleImageError" />
                  <div class="post-author-info">
                    <h4 class="post-author-name">{{ post.author.name }}</h4>
                    <p class="post-author-title">{{ post.author.title }}</p>
                    <span class="post-timestamp">{{ post.timestamp }}</span>
                  </div>
                </div>
                <div class="post-content">
                  <p>{{ post.content }}</p>
                </div>
                <div class="post-actions-bar">
                  <button @click="likePost(post.id)" class="post-action">
                    <HandThumbUpIcon class="post-action-bar-icon" />
                    {{ post.likes }}
                  </button>
                  <button class="post-action">
                    <ChatBubbleLeftIcon class="post-action-bar-icon" />
                    {{ post.comments }} Comment
                  </button>
                  <button @click="sharePost(post.id)" class="post-action">
                    <ArrowPathIcon class="post-action-bar-icon" />
                    {{ post.shares }} Share
                  </button>
                </div>
              </article>
            </div>
          </main>

          <!-- Right Sidebar -->
          <aside class="right-sidebar">
            <!-- OJT Path Logo -->
            <div class="ojt-logo-card">
              <img src="/icons/logo-main.png" alt="OJT Path" class="ojt-logo" />
              <span class="ojt-text">OJT Path</span>
            </div>

            <!-- Top Jobs -->
            <div class="jobs-card">
              <h4 class="jobs-title">Top Jobs</h4>
              <div class="jobs-list">
                <div v-for="job in jobListings" :key="job.title" class="job-item">
                  <h5 class="job-title">{{ job.title }}</h5>
                  <p class="job-company">{{ job.company }}</p>
                  <p class="job-location">{{ job.location }}</p>
                  <span class="job-type">{{ job.type }}</span>
                </div>
              </div>
            </div>

            <!-- Most People Viewed -->
            <div class="most-viewed-card">
              <h4 class="most-viewed-title">Most People Viewed</h4>
              <div class="most-viewed-list">
                <div v-for="person in mostViewed" :key="person.name" class="viewed-item">
                  <img :src="person.avatar" :alt="person.name" class="viewed-avatar" @error="handleImageError" />
                  <div class="viewed-info">
                    <span class="viewed-name">{{ person.name }}</span>
                    <span class="viewed-title">{{ person.title }}</span>
                  </div>
                </div>
              </div>
            </div>
          </aside>
        </div>
      </div>
      
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

/* Community View Styles */
.community-view {
  min-height: 100vh;
  background: #f5f7fb;
}

/* Community Header */
.community-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 32px;
  background: #dbeafe;
  border-bottom: 1px solid #bfdbfe;
}

.community-header .header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.community-header .header-icon {
  width: 24px;
  height: 24px;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(27%) sepia(51%) saturate(2878%) hue-rotate(220deg) brightness(104%) contrast(97%);
}

.community-header .header-title {
  font-size: 24px;
  font-weight: 700;
  color: #1e40af;
  margin: 0;
}

.community-header .header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.community-header .notification-icon-bell {
  width: 24px;
  height: 24px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.community-header .notification-icon-bell:hover {
  color: #2563eb;
  transform: scale(1.1);
}

.community-header .avatar {
  width: 36px;
  height: 36px;
  background: #3b82f6;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.community-header .avatar:hover {
  background: #2563eb;
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Community Container */
.community-container {
  display: grid;
  grid-template-columns: 280px 1fr 320px;
  gap: 24px;
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
}

/* Left Sidebar */
.left-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.profile-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  text-align: center;
}

.profile-header {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 12px;
}

.profile-name {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.profile-title {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.profile-stats {
  display: flex;
  justify-content: space-around;
  margin-bottom: 16px;
  padding: 16px 0;
  border-top: 1px solid #f3f4f6;
  border-bottom: 1px solid #f3f4f6;
}

.stat {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-number {
  font-size: 18px;
  font-weight: 600;
  color: #2563eb;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
}

.view-profile-btn {
  width: 100%;
  padding: 10px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.view-profile-btn:hover {
  background: #1d4ed8;
}

.suggestions-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.suggestions-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px 0;
}

.suggestions-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.suggestion-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.suggestion-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.suggestion-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.suggestion-name {
  font-size: 14px;
  font-weight: 500;
  color: #111827;
}

.suggestion-title {
  font-size: 12px;
  color: #6b7280;
}

.connect-btn {
  width: 24px;
  height: 24px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 50%;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}

.connect-btn:hover {
  background: #1d4ed8;
}

/* Main Content */
.main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.post-creation {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.post-input-area {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}

.post-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.post-input {
  flex: 1;
  padding: 12px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 24px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.post-input:focus {
  border-color: #2563eb;
}

.post-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.post-action-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  color: #6b7280;
  font-size: 14px;
  cursor: pointer;
  padding: 8px 12px;
  border-radius: 6px;
  transition: background 0.2s;
}

.post-action-icon {
  width: 18px;
  height: 18px;
}

.post-action-btn:hover {
  background: #f3f4f6;
}

.post-btn {
  padding: 8px 20px;
  background: #2563eb;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.post-btn:hover {
  background: #1d4ed8;
}

/* Posts Feed */
.posts-feed {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.post {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.post-header {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
}

.post-author-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
}

.post-author-info {
  flex: 1;
}

.post-author-name {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 2px 0;
}

.post-author-title {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 4px 0;
}

.post-timestamp {
  font-size: 12px;
  color: #9ca3af;
}

.post-content {
  margin-bottom: 16px;
}

.post-content p {
  font-size: 14px;
  line-height: 1.6;
  color: #374151;
  margin: 0;
}

.post-actions-bar {
  display: flex;
  gap: 24px;
  padding-top: 16px;
  border-top: 1px solid #f3f4f6;
}

.post-action {
  display: flex;
  align-items: center;
  gap: 6px;
  background: none;
  border: none;
  color: #6b7280;
  font-size: 14px;
  cursor: pointer;
  padding: 8px 0;
  transition: color 0.2s;
}

.post-action-bar-icon {
  width: 18px;
  height: 18px;
}

.post-action:hover {
  color: #2563eb;
}

/* Right Sidebar */
.right-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.ojt-logo-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 12px;
  justify-content: center;
}

.ojt-logo {
  width: 40px;
  height: 40px;
  object-fit: contain;
}

.ojt-text {
  font-size: 18px;
  font-weight: 700;
  color: #2563eb;
}

.jobs-card,
.most-viewed-card {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.jobs-title,
.most-viewed-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 16px 0;
}

.jobs-list,
.most-viewed-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.job-item {
  padding-bottom: 16px;
  border-bottom: 1px solid #f3f4f6;
}

.job-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.job-title {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin: 0 0 4px 0;
}

.job-company {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 2px 0;
}

.job-location {
  font-size: 12px;
  color: #9ca3af;
  margin: 0 0 8px 0;
}

.job-type {
  font-size: 11px;
  padding: 2px 8px;
  background: #dbeafe;
  color: #1e40af;
  border-radius: 12px;
  font-weight: 500;
}

.viewed-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.viewed-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.viewed-info {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.viewed-name {
  font-size: 13px;
  font-weight: 500;
  color: #111827;
}

.viewed-title {
  font-size: 11px;
  color: #6b7280;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-content-area {
    margin-left: 0;
  }
  
  .community-container {
    grid-template-columns: 1fr;
    gap: 20px;
  }
  
  .left-sidebar,
  .right-sidebar {
    display: none;
  }
}

@media (max-width: 768px) {
  .main-content-area {
    margin-left: 0;
  }
  
  .community-container {
    padding: 16px;
  }
  
  .post-actions {
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
  }
  
  .community-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
}
</style>
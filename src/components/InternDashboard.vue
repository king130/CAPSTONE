<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { buildProfileAvatarUrl } from '@/services/profileMedia'
import { subscribeApplications, type ApplicationRecord } from '@/services/applications'
import { listDocuments, type DocumentRecord } from '@/services/documents'
import { subscribeInternships, type InternshipRecord } from '@/services/internships'
import { 
  BellIcon, 
  PencilSquareIcon, 
  ChartBarIcon, 
  CheckIcon,
  HandRaisedIcon,
  SparklesIcon,
  ComputerDesktopIcon,
  BookOpenIcon,
  RocketLaunchIcon,
  DocumentTextIcon,
  MagnifyingGlassIcon,
  ChartPieIcon,
  ChatBubbleLeftRightIcon
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

const userAvatarUrl = computed(() => {
  const currentUser = authStore.user
  const profile = currentUser?.profile as Record<string, unknown> | undefined
  if (currentUser?.uid && profile?.avatarPath) {
    return buildProfileAvatarUrl(currentUser.uid, currentUser.updatedAt)
  }
  return ''
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

// Emit event to parent
const emit = defineEmits<{
  navigateToProfile: []
  navigateToSection: [section: string]
}>()

function handleAvatarClick() {
  emit('navigateToProfile')
}

function navigateToSection(section: string) {
  emit('navigateToSection', section)
}

const studentApplications = ref<ApplicationRecord[]>([])
const studentDocuments = ref<DocumentRecord[]>([])
const internships = ref<InternshipRecord[]>([])
let unsubApplications: (() => void) | null = null
let unsubInternships: (() => void) | null = null

function formatDateLabel(value?: string) {
  if (!value) return 'Recently updated'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Recently updated'
  return `Applied: ${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`
}

function normalizeApplicationStatus(status?: string) {
  const normalized = String(status || 'pending').trim().toLowerCase()
  if (normalized === 'approved' || normalized === 'accepted') return 'Accepted'
  if (normalized === 'rejected' || normalized === 'declined') return 'Rejected'
  if (normalized === 'reviewed' || normalized === 'under_review') return 'Under Review'
  return 'Application Sent'
}

const profileCompleteness = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  const checks = [
    !!authStore.user?.displayName,
    !!authStore.user?.email,
    !!(profile?.contactNumber as string),
    !!(profile?.studentNumber as string) || !!(profile?.studentId as string),
    !!(profile?.schoolName as string),
    !!(profile?.course as string),
    !!(profile?.yearLevel as string),
  ]
  const completed = checks.filter(Boolean).length
  return Math.round((completed / checks.length) * 100)
})

const documentReadiness = computed(() => {
  if (studentDocuments.value.length === 0) return 0
  const approvedCount = studentDocuments.value.filter((doc) => doc.status === 'approved').length
  return Math.round((approvedCount / studentDocuments.value.length) * 100)
})

const readinessScore = computed(() => {
  const documentsScore = studentDocuments.value.length === 0 ? 0 : documentReadiness.value
  const applicationScore = studentApplications.value.length === 0 ? 0 : 100
  return Math.round((profileCompleteness.value * 0.45) + (documentsScore * 0.35) + (applicationScore * 0.2))
})

const user = computed(() => ({
  name: authStore.user?.displayName || authStore.user?.email?.split('@')[0] || 'Student',
  internshipProgress: readinessScore.value,
}))

const internshipReadiness = computed(() => [
  { task: 'Profile Complete', completed: profileCompleteness.value >= 85 },
  { task: 'Documents Uploaded', completed: studentDocuments.value.length > 0 },
  { task: 'Applications Started', completed: studentApplications.value.length > 0 },
])

const topSkills = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  const course = String(profile?.course || '')
  const courseMap: Record<string, Array<{ name: string; percentage: number }>> = {
    bsit: [
      { name: 'Technical Support', percentage: 88 },
      { name: 'Web Development', percentage: 84 },
      { name: 'Database Basics', percentage: 79 },
      { name: 'Networking', percentage: 76 },
      { name: 'Documentation', percentage: 72 },
    ],
    bscs: [
      { name: 'Programming', percentage: 90 },
      { name: 'Problem Solving', percentage: 87 },
      { name: 'Algorithms', percentage: 82 },
      { name: 'Database Design', percentage: 76 },
      { name: 'Software Testing', percentage: 71 },
    ],
  }
  const key = course.toLowerCase().replace(/[^a-z]/g, '')
  return courseMap[key] ?? [
    { name: 'Communication', percentage: 82 },
    { name: 'Teamwork', percentage: 80 },
    { name: 'Documentation', percentage: 76 },
    { name: 'Problem Solving', percentage: 74 },
    { name: 'Adaptability', percentage: 72 },
  ]
})

const internshipsById = computed(() => {
  const map = new Map<string, InternshipRecord>()
  for (const internship of internships.value) {
    map.set(String(internship.id), internship)
  }
  return map
})

const skillsToImprove = computed(() => {
  const missingDocuments = studentDocuments.value.length === 0
  const noApplications = studentApplications.value.length === 0
  const items = [
    { name: 'Profile Completion', gap: `${Math.max(0, 100 - profileCompleteness.value)}%`, color: '#dbeafe' },
    { name: 'Document Readiness', gap: `${Math.max(0, 100 - documentReadiness.value)}%`, color: '#fef3c7' },
    { name: 'Application Activity', gap: noApplications ? '100%' : '25%', color: missingDocuments ? '#fee2e2' : '#dcfce7' },
  ]
  return items
})

const recentApplications = computed(() =>
  studentApplications.value
    .slice()
    .sort((a, b) => new Date(b.createdAt || '').getTime() - new Date(a.createdAt || '').getTime())
    .slice(0, 5)
    .map((app) => {
      const internship = internshipsById.value.get(String(app.internshipId))
      return {
        company: internship?.hostName || internship?.companyName || 'Host Organization',
        position: app.internshipTitle || internship?.title || 'Internship Application',
        status: normalizeApplicationStatus(app.status),
        appliedDate: formatDateLabel(app.createdAt),
      }
    })
)

const readinessTitle = computed(() =>
  profileCompleteness.value >= 85 ? "You're ready to apply!" : 'Complete your profile to apply faster'
)

const readinessDescription = computed(() => {
  if (studentDocuments.value.length === 0) {
    return 'Upload your internship documents so companies and schools can review your application faster.'
  }
  if (studentApplications.value.length === 0) {
    return 'Your profile is almost ready. Start applying to internships to build momentum.'
  }
  return 'Your profile and documents are in place. Keep tracking applications and updates here.'
})

const bestMatchTitle = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return String(profile?.preferredField || profile?.course || 'Internship Opportunities')
})

const bestMatchSubtitle = computed(() =>
  studentApplications.value.length > 0 ? 'Based on your current applications and profile' : 'Based on your student profile'
)

async function loadStudentDocuments() {
  try {
    studentDocuments.value = await listDocuments()
  } catch {
    studentDocuments.value = []
  }
}

function setupDashboardSubscriptions() {
  if (unsubApplications) {
    unsubApplications()
    unsubApplications = null
  }
  if (unsubInternships) {
    unsubInternships()
    unsubInternships = null
  }

  const uid = authStore.user?.uid
  if (!uid || authStore.user?.role !== 'student') {
    studentApplications.value = []
    studentDocuments.value = []
    internships.value = []
    return
  }

  unsubApplications = subscribeApplications(uid, (items) => {
    studentApplications.value = items
  })
  unsubInternships = subscribeInternships((items) => {
    internships.value = items
  })
  loadStudentDocuments()
}

onMounted(() => {
  setupDashboardSubscriptions()
})

watch(
  () => [authStore.initializing, authStore.user?.uid, authStore.user?.role] as const,
  () => {
    if (!authStore.initializing) {
      setupDashboardSubscriptions()
    }
  },
  { immediate: true }
)

onUnmounted(() => {
  if (unsubApplications) unsubApplications()
  if (unsubInternships) unsubInternships()
})
</script>

<template>
  <div class="dashboard-content">
    <!-- Header -->
    <div class="dashboard-header">
      <div class="header-left">
        <img src="/icons/icon-dashboard.png" alt="Dashboard" class="header-icon" />
        <h1 class="header-title">Dashboard Overview</h1>
      </div>
      <div class="header-right">
        <div class="notification-wrapper">
          <BellIcon class="notification-icon-bell" />
        </div>
        <div class="avatar" @click="handleAvatarClick" title="View Profile"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-image" /><span v-else>{{ userInitials }}</span></div>
      </div>
    </div>

    <!-- Main Content with Padding -->
    <div class="dashboard-main">
      <!-- Welcome Banner -->
      <div class="welcome-banner">
        <div class="banner-content">
          <div class="greeting-section">
            <h2 class="welcome-title">
              Welcome back, {{ user.name }}! 
              <HandRaisedIcon class="welcome-icon" />
            </h2>
            <p class="welcome-subtitle">Here's what's happening with your internship journey today</p>
          </div>
            <div class="quick-stats">
              <div class="quick-stat-item">
                <div class="stat-value">{{ user.internshipProgress }}%</div>
                <div class="stat-label">Readiness Score</div>
              </div>
            <div class="quick-stat-divider"></div>
            <div class="quick-stat-item">
              <div class="stat-value">{{ studentDocuments.length }}</div>
              <div class="stat-label">Documents</div>
            </div>
            <div class="quick-stat-divider"></div>
              <div class="quick-stat-item">
                <div class="stat-value">{{ studentApplications.length }}</div>
                <div class="stat-label">Applications</div>
              </div>
            </div>
        </div>
      </div>

      <!-- Main Grid Layout -->
      <div class="main-grid">
        <!-- Left Column -->
        <div class="left-column">
          <!-- Internship Progress Card -->
          <div class="progress-card">
            <div class="card-header-row">
              <div>
                <h3 class="card-title">Readiness Progress</h3>
                <p class="card-subtitle">Built from your profile, documents, and application activity</p>
              </div>
            </div>
            
            <div class="large-progress-circle">
              <svg class="progress-ring" width="140" height="140">
                <circle
                  class="progress-ring-bg"
                  cx="70"
                  cy="70"
                  r="60"
                />
                <circle
                  class="progress-ring-fill"
                  cx="70"
                  cy="70"
                  r="60"
                  :style="{ 
                    strokeDasharray: `${2 * Math.PI * 60}`,
                    strokeDashoffset: `${2 * Math.PI * 60 * (1 - user.internshipProgress / 100)}`
                  }"
                />
              </svg>
              <div class="progress-center">
                <div class="progress-percentage">{{ user.internshipProgress }}%</div>
                <div class="progress-label">Complete</div>
              </div>
            </div>

            <div class="progress-details">
              <div class="detail-row">
                <span class="detail-label">Documents uploaded</span>
                <span class="detail-value">{{ studentDocuments.length }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Approved documents</span>
                <span class="detail-value">{{ studentDocuments.filter((doc) => doc.status === 'approved').length }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Applications submitted</span>
                <span class="detail-value">{{ studentApplications.length }}</span>
              </div>
            </div>

            <button class="card-button primary" @click="handleAvatarClick">
              <ChartBarIcon class="btn-icon" />
              Update Profile
            </button>
          </div>

          <!-- Skills Overview Card -->
          <div class="skills-overview-card">
            <div class="card-header-row">
              <h3 class="card-title">Top Skills</h3>
              <a href="#" class="view-all-link">View all →</a>
            </div>

            <div class="skills-list-modern">
              <div v-for="skill in topSkills.slice(0, 5)" :key="skill.name" class="skill-item-modern">
                <div class="skill-header">
                  <span class="skill-name-modern">{{ skill.name }}</span>
                  <span class="skill-percentage-modern">{{ skill.percentage }}%</span>
                </div>
                <div class="skill-bar-modern">
                  <div 
                    class="skill-fill-modern" 
                    :style="{ 
                      width: skill.percentage + '%',
                      background: skill.percentage >= 85 ? '#10b981' : skill.percentage >= 75 ? '#3b82f6' : '#f59e0b'
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Skills to Develop Card -->
          <div class="skills-develop-card">
            <div class="card-header-row">
              <h3 class="card-title">Skills to Develop</h3>
            </div>

            <div class="develop-skills-list">
              <div v-for="skill in skillsToImprove" :key="skill.name" class="develop-skill-item">
                <div class="develop-skill-header">
                  <span class="develop-skill-name">{{ skill.name }}</span>
                  <span class="develop-skill-gap" :style="{ color: skill.gap === '62%' ? '#dc2626' : skill.gap === '50%' ? '#ea580c' : '#f59e0b' }">
                    Gap: {{ skill.gap }}
                  </span>
                </div>
                <div class="develop-skill-bar" :style="{ background: skill.color }">
                  <div class="develop-skill-fill"></div>
                </div>
              </div>
            </div>

            <div class="recommendations-section">
              <h4 class="recommendations-title">Recommendations:</h4>
              <p class="recommendations-subtitle">Focus on the next gaps to improve your internship readiness:</p>
              <ul class="recommendations-list">
                <li class="recommendation-item" v-if="profileCompleteness < 100">Complete your student profile details.</li>
                <li class="recommendation-item" v-if="studentDocuments.length === 0">Upload your internship requirements.</li>
                <li class="recommendation-item" v-if="studentApplications.length === 0">Submit your first internship application.</li>
                <li class="recommendation-item" v-if="profileCompleteness === 100 && studentDocuments.length > 0 && studentApplications.length > 0">Keep monitoring application updates and feedback.</li>
              </ul>
            </div>

            <button class="card-button primary" @click="navigateToSection('readiness-check')">View Full Analysis</button>
          </div>
        </div>

        <!-- Right Column -->
        <div class="right-column">
          <!-- Readiness Score Card -->
          <div class="readiness-score-card">
            <div class="score-badge">
              <div class="score-number">{{ user.internshipProgress }}</div>
              <div class="score-label">Readiness Score</div>
            </div>
            <h3 class="readiness-title">
              {{ readinessTitle }}
              <SparklesIcon class="readiness-icon" />
            </h3>
            <p class="readiness-description">{{ readinessDescription }}</p>
            
            <div class="readiness-checklist">
              <div v-for="item in internshipReadiness" :key="item.task" class="checklist-item">
                <div class="check-circle">
                  <CheckIcon class="check-icon-svg" />
                </div>
                <span class="checklist-text">{{ item.task }}</span>
              </div>
            </div>

            <button class="card-button secondary" @click="handleAvatarClick">
              <PencilSquareIcon class="btn-icon" />
              Update Profile
            </button>
          </div>

          <!-- Best Match Card -->
          <div class="best-match-card">
            <div class="match-badge">88% Match</div>
            <h3 class="match-title">{{ bestMatchTitle }}</h3>
            <p class="match-subtitle">{{ bestMatchSubtitle }}</p>

            <div class="match-reasons">
              <div class="reason-item">
                <div class="reason-icon">
                  <ComputerDesktopIcon class="reason-icon-svg" />
                </div>
                <span class="reason-text">Strong technical skills</span>
              </div>
              <div class="reason-item">
                <div class="reason-icon">
                  <BookOpenIcon class="reason-icon-svg" />
                </div>
                <span class="reason-text">Relevant coursework</span>
              </div>
              <div class="reason-item">
                <div class="reason-icon">
                  <RocketLaunchIcon class="reason-icon-svg" />
                </div>
                <span class="reason-text">Impressive projects</span>
              </div>
            </div>

            <button class="card-button outline" @click="navigateToSection('internship')">View Internships</button>
          </div>

          <!-- Quick Actions Card -->
          <div class="quick-actions-card">
            <h3 class="card-title">Quick Actions</h3>
            <div class="action-buttons">
              <button class="action-btn" @click="navigateToSection('documents')">
                <div class="action-icon">
                  <DocumentTextIcon class="action-icon-svg" />
                </div>
                <span class="action-text">Upload Document</span>
              </button>
              <button class="action-btn" @click="navigateToSection('internship')">
                <div class="action-icon">
                  <MagnifyingGlassIcon class="action-icon-svg" />
                </div>
                <span class="action-text">Find Internships</span>
              </button>
              <button class="action-btn" @click="navigateToSection('readiness-check')">
                <div class="action-icon">
                  <ChartPieIcon class="action-icon-svg" />
                </div>
                <span class="action-text">View Analytics</span>
              </button>
              <button class="action-btn" @click="navigateToSection('messages')">
                <div class="action-icon">
                  <ChatBubbleLeftRightIcon class="action-icon-svg" />
                </div>
                <span class="action-text">Get Help</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Applications -->
      <div class="applications-section-modern">
        <div class="section-header-row">
          <h3 class="section-title">Recent Applications</h3>
          <a href="#" class="view-all-link">View all →</a>
        </div>
        
        <div class="applications-list">
          <div v-if="recentApplications.length === 0" class="empty-applications-state">
            <p>No internship applications yet. Start exploring companies and submit your first application.</p>
          </div>
          <div v-for="app in recentApplications" :key="app.company" class="application-item-modern">
            <div class="app-left">
              <div class="company-avatar">{{ app.company.charAt(0) }}</div>
              <div class="app-info">
                <h4 class="app-company-name">{{ app.company }}</h4>
                <p class="app-position-name">{{ app.position }}</p>
                <p class="app-date-text">{{ app.appliedDate }}</p>
              </div>
            </div>
            <div class="app-right">
              <div 
                class="status-pill" 
                :class="{
                  'status-success': app.status === 'Interview Scheduled' || app.status === 'Accepted',
                  'status-warning': app.status === 'Under Review',
                  'status-info': app.status === 'Application Sent',
                  'status-danger': app.status === 'Rejected'
                }"
              >
                {{ app.status }}
              </div>
              <button class="view-btn" @click="navigateToSection('internship')">View →</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Global focus and outline removal */
* {
  outline: none !important;
  box-shadow: none !important;
}

*:focus {
  outline: none !important;
  box-shadow: none !important;
  border: none !important;
}

/* Remove any blue borders from all elements */
*[style*="border"] {
  border: none !important;
}

.dashboard-content {
  padding: 0;
  background: #f8fafc;
  min-height: 100vh;
}

.dashboard-main {
  padding: 24px;
}

/* Header */
.dashboard-header {
  background: #dbeafe;
  padding: 16px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #bfdbfe;
  margin-bottom: 24px;
}

.dashboard-header * {
  outline: none !important;
  box-shadow: none !important;
}

.dashboard-header *:focus {
  outline: none !important;
  box-shadow: none !important;
  border: none !important;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  width: 24px;
  height: 24px;
  object-fit: contain;
  filter: brightness(0) saturate(100%) invert(27%) sepia(51%) saturate(2878%) hue-rotate(220deg) brightness(104%) contrast(97%);
}

.header-title {
  font-size: 24px;
  font-weight: 700;
  color: #1e40af;
  margin: 0;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.notification-icon-bell {
  width: 24px;
  height: 24px;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.2s;
}

.notification-icon-bell:hover {
  color: #2563eb;
}

.avatar {
  width: 36px;
  height: 36px;
  background: #3b82f6;
  color: #fff;
  border-radius: 50%;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.avatar:hover {
  background: #2563eb;
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.avatar-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

.message-icon-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  transition: background 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  outline: none;
}

.message-icon-btn:hover {
  background: rgba(59, 130, 246, 0.1);
}

.message-icon-btn:focus {
  outline: none;
  box-shadow: none;
}

.message-icon {
  width: 20px;
  height: 20px;
}

.status-indicators {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status-dot.red {
  background: #ef4444;
}

.status-dot.green {
  background: #10b981;
}

.status-text {
  font-size: 14px;
  color: #6b7280;
}

.ai-badge {
  background: #3b82f6;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

/* Welcome Banner */
.welcome-banner {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);
  border-radius: 20px;
  padding: 32px;
  margin-bottom: 24px;
  color: white;
  box-shadow: 0 10px 40px rgba(59, 130, 246, 0.3);
}

.banner-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 32px;
}

.greeting-section {
  flex: 1;
}

.welcome-title {
  font-size: 32px;
  font-weight: 700;
  margin: 0 0 8px 0;
  line-height: 1.2;
  display: flex;
  align-items: center;
  gap: 12px;
}

.welcome-icon {
  width: 32px;
  height: 32px;
  color: white;
  flex-shrink: 0;
}

.welcome-subtitle {
  font-size: 16px;
  opacity: 0.9;
  margin: 0;
}

.quick-stats {
  display: flex;
  align-items: center;
  gap: 20px;
  background: rgba(255, 255, 255, 0.1);
  padding: 16px 24px;
  border-radius: 16px;
  backdrop-filter: blur(10px);
}

.quick-stat-item {
  text-align: center;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 12px;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.quick-stat-divider {
  width: 1px;
  height: 40px;
  background: rgba(255, 255, 255, 0.3);
}

/* Main Grid Layout */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 24px;
  margin-bottom: 24px;
}

.left-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
  position: static;
}

.right-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
  position: static;
}

/* Progress Card */
.progress-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  position: static;
}

.card-header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
}

.card-title {
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.card-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.icon-button {
  width: 40px;
  height: 40px;
  background: #f3f4f6;
  border: none;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.icon-button:hover {
  background: #e5e7eb;
  transform: translateY(-2px);
}

.icon-btn {
  width: 20px;
  height: 20px;
  color: #6b7280;
}

.large-progress-circle {
  position: relative;
  width: 140px;
  height: 140px;
  margin: 0 auto 16px auto;
}

.progress-ring {
  transform: rotate(-90deg);
}

.progress-ring-bg {
  fill: none;
  stroke: #f3f4f6;
  stroke-width: 10;
}

.progress-ring-fill {
  fill: none;
  stroke: #f59e0b;
  stroke-width: 10;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.5s ease;
}

.progress-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
}

.progress-percentage {
  font-size: 32px;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
  margin-bottom: 4px;
}

.progress-label {
  font-size: 13px;
  color: #6b7280;
}

.progress-details {
  background: #f9fafb;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 16px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
}

.detail-row:not(:last-child) {
  border-bottom: 1px solid #e5e7eb;
}

.detail-label {
  font-size: 14px;
  color: #6b7280;
}

.detail-value {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

/* Skills Section */
.skills-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 32px;
}

.skills-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.skills-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 20px 0;
}

.skills-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.skill-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.skill-name {
  width: 100px;
  font-size: 14px;
  color: #374151;
  font-weight: 500;
}

.skill-bar {
  flex: 1;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.skill-progress {
  height: 100%;
  background: #1f2937;
  border-radius: 4px;
}

.skill-percentage {
  width: 40px;
  text-align: right;
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.improve-skills {
  margin-bottom: 20px;
}

.improve-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f3f4f6;
}

.improve-item:last-child {
  border-bottom: none;
}

.improve-name {
  font-size: 14px;
  color: #374151;
  font-weight: 500;
}

.improve-gap {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  color: #92400e;
}

.recommendations h4 {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.recommendations ul {
  margin: 0;
  padding-left: 16px;
}

.recommendations li {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 4px;
}

/* Applications Section */
.applications-section {
  margin-bottom: 32px;
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 20px 0;
}

.applications-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.application-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.app-company {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.app-position {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 12px 0;
}

.app-status {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  color: white;
  margin-bottom: 8px;
}

.app-date {
  font-size: 12px;
  color: #9ca3af;
  margin: 0 0 16px 0;
}

.app-button {
  width: 100%;
  padding: 8px 16px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 14px;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s;
}

.app-button:hover {
  background: #e5e7eb;
}

/* Skills Overview Card */
.skills-overview-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.view-all-link {
  font-size: 14px;
  color: #3b82f6;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s;
}

.view-all-link:hover {
  color: #2563eb;
}

.skills-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  background: #f3f4f6;
  padding: 4px;
  border-radius: 10px;
  position: relative;
  z-index: 1;
}

.skill-tab {
  flex: 1;
  padding: 8px 16px;
  background: transparent;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.skill-tab.active {
  background: white;
  color: #1f2937;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.skills-list-modern {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.skill-item-modern {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skill-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.skill-name-modern {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.skill-percentage-modern {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.skill-bar-modern {
  height: 8px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}

.skill-fill-modern {
  height: 100%;
  border-radius: 4px;
  transition: width 0.5s ease;
}

/* Skills to Develop Card */
.skills-develop-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-top: 20px;
}

.develop-skills-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 20px;
}

.develop-skill-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.develop-skill-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.develop-skill-name {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.develop-skill-gap {
  font-size: 13px;
  font-weight: 600;
}

.develop-skill-bar {
  height: 10px;
  border-radius: 6px;
  position: relative;
  overflow: hidden;
}

.develop-skill-fill {
  height: 100%;
  width: 0%;
  background: rgba(255, 255, 255, 0.4);
  border-radius: 6px;
}

.recommendations-section {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 16px;
}

.recommendations-title {
  font-size: 14px;
  font-weight: 600;
  color: #1e40af;
  margin: 0 0 8px 0;
}

.recommendations-subtitle {
  font-size: 13px;
  color: #475569;
  margin: 0 0 12px 0;
}

.recommendations-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.recommendation-item {
  font-size: 13px;
  color: #1e40af;
  padding-left: 20px;
  position: relative;
}

.recommendation-item::before {
  content: '•';
  position: absolute;
  left: 8px;
  color: #3b82f6;
  font-weight: bold;
}

/* Readiness Score Card */
.readiness-score-card {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 12px;
  padding: 24px;
  color: white;
  box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.score-badge {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255, 255, 255, 0.2);
  padding: 12px 20px;
  border-radius: 12px;
  margin-bottom: 12px;
  backdrop-filter: blur(10px);
}

.score-number {
  font-size: 40px;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 4px;
}

.score-label {
  font-size: 11px;
  opacity: 0.9;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.readiness-title {
  font-size: 20px;
  font-weight: 700;
  margin: 0 0 4px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}

.readiness-icon {
  width: 24px;
  height: 24px;
  color: white;
  flex-shrink: 0;
}

.readiness-description {
  font-size: 13px;
  opacity: 0.9;
  margin: 0 0 16px 0;
  line-height: 1.5;
}

.readiness-checklist {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.checklist-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.check-circle {
  width: 24px;
  height: 24px;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.check-icon-svg {
  width: 14px;
  height: 14px;
  stroke-width: 3;
}

.checklist-text {
  font-size: 14px;
  font-weight: 500;
}

/* Best Match Card */
.best-match-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border: 2px solid #e5e7eb;
}

.match-badge {
  display: inline-block;
  background: #dbeafe;
  color: #1e40af;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 8px;
}

.match-title {
  font-size: 20px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.match-subtitle {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.match-reasons {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.reason-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: #f9fafb;
  border-radius: 10px;
}

.reason-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border-radius: 8px;
  flex-shrink: 0;
}

.reason-icon-svg {
  width: 20px;
  height: 20px;
  color: #3b82f6;
}

.reason-text {
  font-size: 14px;
  color: #374151;
  font-weight: 500;
}

/* Quick Actions Card */
.quick-actions-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.action-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 12px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #f3f4f6;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.action-icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border-radius: 12px;
}

.action-icon-svg {
  width: 24px;
  height: 24px;
  color: #3b82f6;
}

.action-text {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  text-align: center;
}

/* Buttons */
.card-button {
  width: 100%;
  padding: 12px 16px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.card-button.primary {
  background: #3b82f6;
  color: white;
}

.card-button.primary:hover {
  background: #2563eb;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.card-button.secondary {
  background: rgba(255, 255, 255, 0.2);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.card-button.secondary:hover {
  background: rgba(255, 255, 255, 0.3);
}

.card-button.outline {
  background: transparent;
  color: #3b82f6;
  border: 2px solid #3b82f6;
}

.card-button.outline:hover {
  background: #f0f9ff;
}

.btn-icon {
  width: 18px;
  height: 18px;
}

/* Applications Section Modern */
.applications-section-modern {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.applications-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.empty-applications-state {
  padding: 18px;
  border: 1px dashed #cbd5e1;
  border-radius: 12px;
  background: #f8fafc;
  color: #64748b;
  font-size: 14px;
}

.application-item-modern {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #f9fafb;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  transition: all 0.2s;
}

.application-item-modern:hover {
  background: white;
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.app-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.company-avatar {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  flex-shrink: 0;
}

.app-info {
  flex: 1;
}

.app-company-name {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.app-position-name {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 4px 0;
}

.app-date-text {
  font-size: 12px;
  color: #9ca3af;
  margin: 0;
}

.app-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.status-pill {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.status-success {
  background: #dcfce7;
  color: #16a34a;
}

.status-warning {
  background: #fef3c7;
  color: #d97706;
}

.status-info {
  background: #dbeafe;
  color: #2563eb;
}

.status-danger {
  background: #fee2e2;
  color: #dc2626;
}

.view-btn {
  padding: 8px 16px;
  background: transparent;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.view-btn:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

/* Responsive */
@media (max-width: 1024px) {
  .main-grid {
    grid-template-columns: 1fr;
  }
  
  .skills-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .action-buttons {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 768px) {
  .welcome-banner {
    padding: 24px;
  }
  
  .banner-content {
    flex-direction: column;
    gap: 20px;
  }
  
  .quick-stats {
    flex-direction: column;
    gap: 16px;
    padding: 20px;
  }
  
  .quick-stat-divider {
    width: 100%;
    height: 1px;
  }
  
  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .application-item-modern {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .app-right {
    width: 100%;
    justify-content: space-between;
  }
  
  .action-buttons {
    grid-template-columns: 1fr;
  }
}
</style>

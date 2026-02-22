<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { 
  BellIcon,
  CheckCircleIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  DocumentTextIcon,
  AcademicCapIcon,
  ChartBarIcon,
  SparklesIcon,
  ArrowUpIcon,
  CheckIcon
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

const emit = defineEmits(['navigateToProfile'])

function handleAvatarClick() {
  emit('navigateToProfile')
}

// User profile data
const user = ref({
  name: 'Alex Doe',
  title: 'Computer Science Student',
  overallScore: 78
})

// Internship readiness journey steps
const journeySteps = ref([
  { id: 1, name: 'Profile', completed: true, active: false },
  { id: 2, name: 'Skills & Experience', completed: true, active: false },
  { id: 3, name: 'Documents', completed: false, active: true },
  { id: 4, name: 'Job Application', completed: false, active: false },
  { id: 5, name: 'Completion', completed: false, active: false }
])

// Academic Performance
const academicPerformance = ref({
  gpa: 3.82,
  status: 'Excellent academic standing',
  courses: [
    { name: 'Data Structures', grade: 'A', progress: 95 },
    { name: 'Database Systems', grade: 'A-', progress: 88 },
    { name: 'Web Development', grade: 'B+', progress: 85 },
    { name: 'Software Engineering', grade: 'A', progress: 92 },
    { name: 'Algorithms', grade: 'B+', progress: 83 }
  ]
})

// Document Submission
const documentSubmission = ref([
  { name: 'Resume/CV', status: 'submitted', icon: 'check' },
  { name: 'Cover Letter', status: 'submitted', icon: 'check' },
  { name: 'Academic Transcript', status: 'submitted', icon: 'check' },
  { name: 'Portfolio', status: 'pending', icon: 'clock' },
  { name: 'Recommendation Letters', status: 'missing', icon: 'warning' }
])

// Skills Assessment
const skillsAssessment = ref({
  score: 85,
  categories: [
    { name: 'Programming Languages', score: 90 },
    { name: 'Web Technologies', score: 85 },
    { name: 'Database Management', score: 80 },
    { name: 'Version Control', score: 88 },
    { name: 'Problem Solving', score: 82 }
  ],
  strengths: [
    'Strong programming fundamentals',
    'Excellent problem-solving skills'
  ],
  improvements: [
    'Advanced database optimization',
    'Cloud computing platforms'
  ]
})

// Compatible Courses
const compatibleCourses = ref([
  { name: 'Advanced Web Development', match: 95 },
  { name: 'Mobile App Development', match: 88 },
  { name: 'Data Science Fundamentals', match: 85 },
  { name: 'Machine Learning Basics', match: 78 },
  { name: 'DevOps and CI/CD', match: 75 },
  { name: 'Cybersecurity Fundamentals', match: 70 }
])

// Overall Readiness Summary
const readinessSummary = ref({
  score: 78,
  categories: [
    { name: 'Academic Profile', score: 85, status: 'Strong academic performance' },
    { name: 'Skills Assessment', score: 82, status: 'Good technical foundation' },
    { name: 'Document Readiness', score: 70, status: 'Missing some documents' },
    { name: 'Experience Level', score: 75, status: 'Moderate project experience' }
  ]
})

function improveReadiness() {
  console.log('Improve readiness clicked')
}

function viewDetails(section: string) {
  console.log('View details:', section)
}
</script>

<template>
  <div class="readiness-content">
    <!-- Header -->
    <header class="readiness-header">
      <div class="header-left">
        <img src="/icons/icon-evaluation.png" alt="Readiness Check" class="header-icon" />
        <h1 class="header-title">Readiness Check</h1>
      </div>
      <div class="header-right">
        <div class="notification-wrapper">
          <BellIcon class="notification-bell" />
        </div>
        <button class="user-avatar" type="button" @click="handleAvatarClick" :title="`View Profile - ${userInitials}`">
          {{ userInitials }}
        </button>
      </div>
    </header>

    <div class="readiness-main">
      <!-- Score Banner -->
      <div class="score-banner">
        <div class="banner-content">
          <div class="score-section">
            <div class="score-circle-large">
              <svg class="progress-ring" width="120" height="120">
                <circle class="progress-ring-bg" cx="60" cy="60" r="52" />
                <circle
                  class="progress-ring-fill"
                  cx="60" cy="60" r="52"
                  :style="{ 
                    strokeDasharray: `${2 * Math.PI * 52}`,
                    strokeDashoffset: `${2 * Math.PI * 52 * (1 - user.overallScore / 100)}`
                  }"
                />
              </svg>
              <div class="score-center">
                <div class="score-number">{{ user.overallScore }}%</div>
                <div class="score-label">Ready</div>
              </div>
            </div>
          </div>
          <div class="info-section">
            <h2 class="user-name">{{ user.name }}</h2>
            <p class="user-title">{{ user.title }}</p>
            <div class="readiness-badges">
              <div class="badge success">
                <SparklesIcon class="badge-icon" />
                <span>Ready to Apply</span>
              </div>
              <div class="badge info">
                <ChartBarIcon class="badge-icon" />
                <span>Good Progress</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Journey Steps -->
      <h3 class="section-title-outside">Internship Readiness Journey</h3>
      <div class="journey-card">
        <div class="journey-steps">
          <div 
            v-for="(step, index) in journeySteps" 
            :key="step.id"
            class="step-item"
            :class="{ 
              completed: step.completed, 
              active: step.active
            }"
          >
            <div class="step-wrapper">
              <div class="step-badge">
                <CheckIcon v-if="step.completed" class="check-icon" />
                <span v-else>{{ step.id }}</span>
              </div>
              <div v-if="index < journeySteps.length - 1" class="connecting-line"></div>
            </div>
            <span class="step-label">{{ step.name }}</span>
          </div>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="content-grid">
        <!-- Academic Performance Card -->
        <div class="card">
          <div class="card-header">
            <div class="card-header-left">
              <AcademicCapIcon class="header-icon-small" />
              <h3 class="card-title">Academic Performance</h3>
            </div>
            <button class="view-btn" @click="viewDetails('academic')" disabled title="Coming soon">View Details →</button>
          </div>
          <div class="gpa-section">
            <div class="gpa-display">
              <span class="gpa-number">{{ academicPerformance.gpa }}</span>
              <span class="gpa-label">GPA</span>
            </div>
            <p class="gpa-status">{{ academicPerformance.status }}</p>
          </div>
          <div class="courses-list">
            <div v-for="course in academicPerformance.courses" :key="course.name" class="course-item">
              <div class="course-header">
                <span class="course-name">{{ course.name }}</span>
                <span class="course-grade">{{ course.grade }}</span>
              </div>
              <div class="course-progress-bar">
                <div class="progress-fill" :style="{ width: course.progress + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Skills Assessment Card -->
        <div class="card">
          <div class="card-header">
            <div class="card-header-left">
              <ChartBarIcon class="header-icon-small" />
              <h3 class="card-title">Skills Assessment</h3>
            </div>
            <button class="view-btn" @click="viewDetails('skills')" disabled title="Coming soon">View Details →</button>
          </div>
          <div class="skills-score-display">
            <div class="score-badge">{{ skillsAssessment.score }}%</div>
            <span class="score-text">Overall Skills Score</span>
          </div>
          <div class="skills-list">
            <div v-for="skill in skillsAssessment.categories" :key="skill.name" class="skill-item">
              <div class="skill-header">
                <span class="skill-name">{{ skill.name }}</span>
                <span class="skill-score">{{ skill.score }}%</span>
              </div>
              <div class="skill-progress-bar">
                <div 
                  class="progress-fill" 
                  :style="{ 
                    width: skill.score + '%',
                    background: skill.score >= 85 ? '#10b981' : skill.score >= 75 ? '#3b82f6' : '#f59e0b'
                  }"
                ></div>
              </div>
            </div>
          </div>
          <div class="skills-insights">
            <div class="insight-section">
              <h4 class="insight-title">
                <SparklesIcon class="insight-icon" />
                Strengths
              </h4>
              <ul class="insight-list">
                <li v-for="strength in skillsAssessment.strengths" :key="strength">{{ strength }}</li>
              </ul>
            </div>
            <div class="insight-section">
              <h4 class="insight-title">
                <ArrowUpIcon class="insight-icon" />
                Areas for Improvement
              </h4>
              <ul class="insight-list">
                <li v-for="improvement in skillsAssessment.improvements" :key="improvement">{{ improvement }}</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Document Submission Card -->
        <div class="card">
          <div class="card-header">
            <div class="card-header-left">
              <DocumentTextIcon class="header-icon-small" />
              <h3 class="card-title">Document Submission</h3>
            </div>
            <button class="view-btn" @click="viewDetails('documents')" disabled title="Coming soon">View Details →</button>
          </div>
          <div class="documents-list">
            <div v-for="doc in documentSubmission" :key="doc.name" class="document-item">
              <div class="doc-icon-wrapper" :class="doc.status">
                <CheckCircleIcon v-if="doc.icon === 'check'" class="doc-icon" />
                <ClockIcon v-else-if="doc.icon === 'clock'" class="doc-icon" />
                <ExclamationTriangleIcon v-else class="doc-icon" />
              </div>
              <div class="doc-info">
                <span class="doc-name">{{ doc.name }}</span>
                <span class="doc-status" :class="doc.status">
                  {{ doc.status.charAt(0).toUpperCase() + doc.status.slice(1) }}
                </span>
              </div>
            </div>
          </div>
          <div class="alert-box">
            <ExclamationTriangleIcon class="alert-icon" />
            <div class="alert-content">
              <strong>Missing Documents</strong>
              <p>Complete your document submission to improve your readiness score</p>
            </div>
          </div>
          <button class="action-btn" @click="improveReadiness" disabled title="Coming soon">
            <DocumentTextIcon class="btn-icon" />
            Upload Documents
          </button>
        </div>

        <!-- Compatible Courses Card -->
        <div class="card">
          <div class="card-header">
            <div class="card-header-left">
              <AcademicCapIcon class="header-icon-small" />
              <h3 class="card-title">Compatible Courses</h3>
            </div>
            <button class="view-btn" @click="viewDetails('courses')" disabled title="Coming soon">View All →</button>
          </div>
          <div class="compatible-courses-list">
            <div v-for="course in compatibleCourses" :key="course.name" class="compatible-course-item">
              <div class="course-match-badge" :class="{ 
                high: course.match >= 85, 
                medium: course.match >= 75 && course.match < 85,
                low: course.match < 75
              }">
                {{ course.match }}%
              </div>
              <span class="course-name">{{ course.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Overall Summary Card -->
      <h3 class="section-title-outside">Overall Readiness Summary</h3>
      <div class="summary-card">
        <div class="summary-grid">
          <div v-for="category in readinessSummary.categories" :key="category.name" class="summary-item">
            <div class="summary-header">
              <span class="category-name">{{ category.name }}</span>
              <span class="category-score">{{ category.score }}%</span>
            </div>
            <div class="category-progress-bar">
              <div class="progress-fill" :style="{ width: category.score + '%' }"></div>
            </div>
            <p class="category-status">{{ category.status }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.readiness-content {
  padding: 0;
  background: #f8fafc;
  min-height: 100vh;
}

/* Header */
.readiness-header {
  background: #dbeafe;
  padding: 16px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #bfdbfe;
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

.notification-btn {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.notification-bell {
  width: 24px;
  height: 24px;
  color: #6b7280;
  transition: all 0.2s ease;
}

.notification-btn:hover .notification-bell {
  color: #2563eb;
  transform: scale(1.1);
}

.user-avatar {
  width: 36px;
  height: 36px;
  background: #3b82f6;
  color: #fff;
  border-radius: 50%;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
  padding: 0;
}

.user-avatar:hover {
  background: #2563eb;
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Main Content */
.readiness-main {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

/* Score Banner */
.score-banner {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 24px;
  box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
  overflow: hidden;
  position: relative;
  isolation: isolate;
}

.banner-content {
  display: flex;
  align-items: center;
  gap: 32px;
}

.score-section {
  flex-shrink: 0;
}

.score-circle-large {
  position: relative;
  width: 120px;
  height: 120px;
}

.progress-ring {
  transform: rotate(-90deg);
}

.progress-ring-bg {
  fill: none;
  stroke: rgba(255, 255, 255, 0.2);
  stroke-width: 8;
}

.progress-ring-fill {
  fill: none;
  stroke: #fff;
  stroke-width: 8;
  stroke-linecap: round;
  transition: stroke-dashoffset 0.5s ease;
}

.score-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
}

.score-number {
  display: block;
  font-size: 32px;
  font-weight: 700;
  color: #fff;
  line-height: 1;
}

.score-label {
  display: block;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.9);
  margin-top: 4px;
}

.info-section {
  flex: 1;
  color: #fff;
}

.user-name {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 4px 0;
  color: #fff;
}

.user-title {
  font-size: 16px;
  color: rgba(255, 255, 255, 0.9);
  margin: 0 0 16px 0;
}

.readiness-badges {
  display: flex;
  gap: 12px;
}

.badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
}

.badge.success {
  background: rgba(16, 185, 129, 0.2);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.badge.info {
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.badge-icon {
  width: 18px;
  height: 18px;
}

/* Section Title Outside Card */
.section-title-outside {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 12px 0;
}

/* Journey Card */
.journey-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 20px 0;
}

.journey-steps {
  display: flex;
  gap: 0;
  justify-content: space-between;
  align-items: flex-start;
  position: relative;
}

.step-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex: 1;
  position: relative;
}

.step-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  position: relative;
}

.step-badge {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 16px;
  color: #9ca3af;
  position: relative;
  z-index: 2;
  background: #fff;
  border: 2px solid #e5e7eb;
  flex-shrink: 0;
}

.step-item.completed .step-badge {
  background: #dcfce7;
  color: #16a34a;
  border: 2px solid #10b981;
}

.step-item.active .step-badge {
  background: #dbeafe;
  color: #2563eb;
  border: 2px solid #2563eb;
}

.connecting-line {
  position: absolute;
  top: 50%;
  left: 50%;
  right: -50%;
  height: 2px;
  background: #e5e7eb;
  z-index: 1;
  transform: translateY(-50%);
}

.step-item:last-child .connecting-line {
  display: none;
}

.step-item.completed .connecting-line {
  background: #10b981;
}

.check-icon {
  width: 18px;
  height: 18px;
  stroke-width: 3;
}

.step-label {
  font-size: 13px;
  font-weight: 500;
  color: #6b7280;
  text-align: center;
}

.step-item.completed .step-label {
  color: #16a34a;
}

.step-item.active .step-label {
  color: #2563eb;
}

/* Content Grid */
.content-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
  margin-bottom: 24px;
}

/* Card Styles */
.card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.card-icon {
  width: 20px;
  height: 20px;
  color: #3b82f6;
}

.header-icon-small {
  width: 20px;
  height: 20px;
  color: #3b82f6;
  flex-shrink: 0;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.view-btn {
  background: none;
  border: none;
  color: #3b82f6;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.2s;
}

.view-btn:hover {
  color: #2563eb;
}

/* Academic Performance */
.gpa-section {
  margin-bottom: 20px;
}

.gpa-display {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 8px;
}

.gpa-number {
  font-size: 48px;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
}

.gpa-label {
  font-size: 16px;
  color: #6b7280;
}

.gpa-status {
  font-size: 14px;
  color: #10b981;
  margin: 0;
}

.courses-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.course-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.course-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.course-name {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.course-grade {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  padding: 2px 8px;
  background: #f3f4f6;
  border-radius: 4px;
}

.course-progress-bar {
  height: 6px;
  background: #f3f4f6;
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #3b82f6;
  border-radius: 3px;
  transition: width 0.5s ease;
}

/* Skills Assessment */
.skills-score-display {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.score-badge {
  font-size: 32px;
  font-weight: 700;
  color: #3b82f6;
}

.score-text {
  font-size: 14px;
  color: #6b7280;
}

.skills-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 20px;
}

.skill-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skill-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.skill-name {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.skill-score {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.skill-progress-bar {
  height: 8px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}

.skills-insights {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  padding-top: 20px;
  border-top: 1px solid #f3f4f6;
}

.insight-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.insight-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.insight-icon {
  width: 16px;
  height: 16px;
  color: #3b82f6;
}

.insight-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.insight-list li {
  font-size: 13px;
  color: #6b7280;
  padding-left: 16px;
  position: relative;
}

.insight-list li::before {
  content: '•';
  position: absolute;
  left: 4px;
  color: #3b82f6;
}

/* Documents */
.documents-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.document-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.doc-icon-wrapper {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.doc-icon-wrapper.submitted {
  background: #dcfce7;
  color: #16a34a;
}

.doc-icon-wrapper.pending {
  background: #fef3c7;
  color: #f59e0b;
}

.doc-icon-wrapper.missing {
  background: #fee2e2;
  color: #ef4444;
}

.doc-icon {
  width: 20px;
  height: 20px;
}

.doc-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.doc-name {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.doc-status {
  font-size: 12px;
  font-weight: 500;
}

.doc-status.submitted {
  color: #16a34a;
}

.doc-status.pending {
  color: #f59e0b;
}

.doc-status.missing {
  color: #ef4444;
}

.alert-box {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 8px;
  margin-bottom: 16px;
}

.alert-icon {
  width: 20px;
  height: 20px;
  color: #f59e0b;
  flex-shrink: 0;
}

.alert-content {
  flex: 1;
}

.alert-content strong {
  display: block;
  font-size: 14px;
  color: #92400e;
  margin-bottom: 4px;
}

.alert-content p {
  font-size: 13px;
  color: #78350f;
  margin: 0;
}

.action-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 16px;
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.action-btn:hover {
  background: #2563eb;
}

.btn-icon {
  width: 18px;
  height: 18px;
}

/* Compatible Courses */
.compatible-courses-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.compatible-course-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.course-match-badge {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  flex-shrink: 0;
}

.course-match-badge.high {
  background: #dcfce7;
  color: #16a34a;
}

.course-match-badge.medium {
  background: #dbeafe;
  color: #2563eb;
}

.course-match-badge.low {
  background: #fef3c7;
  color: #f59e0b;
}

.compatible-course-item .course-name {
  font-size: 14px;
  color: #374151;
}

/* Summary Card */
.summary-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.summary-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.category-name {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.category-score {
  font-size: 16px;
  font-weight: 700;
  color: #3b82f6;
}

.category-progress-bar {
  height: 8px;
  background: #f3f4f6;
  border-radius: 4px;
  overflow: hidden;
}

.category-status {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .banner-content {
    flex-direction: column;
    text-align: center;
  }
  
  .journey-steps {
    flex-direction: column;
    align-items: stretch;
  }
  
  .step-indicator {
    flex-direction: row;
  }
  
  .step-line {
    display: none;
  }
  
  .skills-insights {
    grid-template-columns: 1fr;
  }
}
</style>

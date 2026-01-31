<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const organizationName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.schoolName as string) || authStore.user?.displayName || 'Account'
})

// User profile data
const user = ref({
  name: 'Alex Doe',
  title: 'Computer Science Student',
  avatar: '/icons/profiles/alex-doe.jpg',
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
  { name: 'Resume/CV', status: 'submitted', color: '#10b981' },
  { name: 'Cover Letter', status: 'submitted', color: '#10b981' },
  { name: 'Academic Transcript', status: 'submitted', color: '#10b981' },
  { name: 'Portfolio', status: 'pending', color: '#f59e0b' },
  { name: 'Recommendation Letters', status: 'missing', color: '#ef4444' }
])

// Skills Assessment
const skillsAssessment = ref({
  score: 85,
  categories: [
    { name: 'Programming Languages', score: 90, color: '#10b981' },
    { name: 'Web Technologies', score: 85, color: '#10b981' },
    { name: 'Database Management', score: 80, color: '#3b82f6' },
    { name: 'Version Control', score: 88, color: '#10b981' },
    { name: 'Problem Solving', score: 82, color: '#3b82f6' }
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
  { name: 'Advanced Web Development', match: 95, color: '#10b981' },
  { name: 'Mobile App Development', match: 88, color: '#10b981' },
  { name: 'Data Science Fundamentals', match: 85, color: '#10b981' },
  { name: 'Machine Learning Basics', match: 78, color: '#f59e0b' },
  { name: 'DevOps and CI/CD', match: 75, color: '#f59e0b' },
  { name: 'Cybersecurity Fundamentals', match: 70, color: '#f59e0b' }
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

function getInitials(name: string): string {
  return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

function handleImageError(event: Event) {
  const img = event.target as HTMLImageElement
  const name = img.alt || 'User'
  const initials = getInitials(name)
  
  const colors = ['#2563eb', '#7c3aed', '#dc2626', '#059669', '#d97706', '#0891b2']
  const colorIndex = name.length % colors.length
  const color = colors[colorIndex]
  
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

function improveReadiness() {
  console.log('Improve readiness clicked')
}

function viewDetails(section: string) {
  console.log('View details:', section)
}
</script>

<template>
  <div class="readiness-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-left">
        <img src="/icons/icon-evaluation.png" alt="Readiness Check" class="header-icon" />
        <h1 class="header-title">Readiness Check</h1>
      </div>
      <div class="header-right">
        <button @click="$emit('openMessages')" class="message-icon-btn">
          <img src="/icons/icon-message.png" alt="Messages" class="message-icon" />
        </button>
        <div class="company-info">
          <span class="company-name">{{ organizationName }}</span>
          <div class="user-avatar-small">AC</div>
        </div>
      </div>
    </div>

    <!-- Header Banner -->
    <div class="readiness-header">
      <div class="header-content">
        <div class="user-info">
          <div class="user-avatar">
            <img :src="user.avatar" :alt="user.name" @error="handleImageError" />
            <div class="avatar-badge">AD</div>
          </div>
          <div class="user-details">
            <h1 class="user-name">{{ user.name }}</h1>
            <p class="user-title">{{ user.title }}</p>
            <div class="readiness-stats">
              <span class="stat-item">📊 Overall Readiness</span>
              <span class="stat-item">🎯 Ready to Apply</span>
            </div>
          </div>
        </div>
        <div class="overall-score">
          <div class="score-circle">
            <div class="score-progress" :style="{ '--progress': user.overallScore }">
              <span class="score-number">{{ user.overallScore }}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="main-content">
      <!-- Internship Readiness Journey -->
      <div class="section-card">
        <h2 class="section-title">Internship Readiness Journey</h2>
        <div class="journey-progress">
          <div class="progress-line"></div>
          <div class="journey-steps">
            <div 
              v-for="step in journeySteps" 
              :key="step.id"
              class="journey-step"
              :class="{ 
                completed: step.completed, 
                active: step.active,
                pending: !step.completed && !step.active
              }"
            >
              <div class="step-circle">
                <span v-if="step.completed" class="step-icon">✓</span>
                <span v-else-if="step.active" class="step-icon">{{ step.id }}</span>
                <span v-else class="step-icon">{{ step.id }}</span>
              </div>
              <span class="step-name">{{ step.name }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="content-grid">
        <!-- Left Column -->
        <div class="left-column">
          <!-- Academic Performance -->
          <div class="section-card">
            <div class="section-header">
              <h3 class="section-title">📚 Academic Performance</h3>
              <button class="view-details-btn" @click="viewDetails('academic')" disabled title="Coming soon">View Details</button>
            </div>
            <div class="gpa-display">
              <span class="gpa-number">{{ academicPerformance.gpa }}</span>
              <span class="gpa-status">{{ academicPerformance.status }}</span>
            </div>
            <div class="courses-list">
              <div v-for="course in academicPerformance.courses" :key="course.name" class="course-item">
                <div class="course-info">
                  <span class="course-name">{{ course.name }}</span>
                  <span class="course-grade">{{ course.grade }}</span>
                </div>
                <div class="course-progress">
                  <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: course.progress + '%' }"></div>
                  </div>
                  <span class="progress-text">{{ course.progress }}%</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Skills Assessment -->
          <div class="section-card">
            <div class="section-header">
              <h3 class="section-title">🎯 Skills Assessment</h3>
              <button class="view-details-btn" @click="viewDetails('skills')" disabled title="Coming soon">View Details</button>
            </div>
            <div class="skills-score">
              <span class="score-number">{{ skillsAssessment.score }}</span>
              <span class="score-label">Overall Skills Score</span>
            </div>
            <div class="skills-categories">
              <div v-for="skill in skillsAssessment.categories" :key="skill.name" class="skill-item">
                <span class="skill-name">{{ skill.name }}</span>
                <div class="skill-progress">
                  <div class="progress-bar">
                    <div class="progress-fill" :style="{ width: skill.score + '%', backgroundColor: skill.color }"></div>
                  </div>
                  <span class="skill-score">{{ skill.score }}%</span>
                </div>
              </div>
            </div>
            <div class="skills-summary">
              <div class="strengths">
                <h4>💪 Strengths</h4>
                <ul>
                  <li v-for="strength in skillsAssessment.strengths" :key="strength">{{ strength }}</li>
                </ul>
              </div>
              <div class="improvements">
                <h4>📈 Areas for Improvement</h4>
                <ul>
                  <li v-for="improvement in skillsAssessment.improvements" :key="improvement">{{ improvement }}</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="right-column">
          <!-- Document Submission -->
          <div class="section-card">
            <div class="section-header">
              <h3 class="section-title">📄 Document Submission</h3>
              <button class="view-details-btn" @click="viewDetails('documents')" disabled title="Coming soon">View Details</button>
            </div>
            <div class="documents-list">
              <div v-for="doc in documentSubmission" :key="doc.name" class="document-item">
                <div class="doc-status" :style="{ backgroundColor: doc.color }">
                  <span v-if="doc.status === 'submitted'" class="status-icon">✓</span>
                  <span v-else-if="doc.status === 'pending'" class="status-icon">⏳</span>
                  <span v-else class="status-icon">⚠️</span>
                </div>
                <span class="doc-name">{{ doc.name }}</span>
                <span class="doc-status-text" :style="{ color: doc.color }">
                  {{ doc.status.charAt(0).toUpperCase() + doc.status.slice(1) }}
                </span>
              </div>
            </div>
            <div class="missing-docs-alert">
              <div class="alert-content">
                <span class="alert-icon">⚠️</span>
                <div class="alert-text">
                  <strong>Missing Documents</strong>
                  <p>Complete your document submission to improve your readiness score</p>
                </div>
              </div>
              <button class="improve-btn" @click="improveReadiness" disabled title="Coming soon">📤 Upload Documents</button>
            </div>
          </div>

          <!-- Compatible Courses -->
          <div class="section-card">
            <div class="section-header">
              <h3 class="section-title">🎓 Compatible Courses</h3>
              <button class="view-details-btn" @click="viewDetails('courses')" disabled title="Coming soon">View All Courses</button>
            </div>
            <div class="courses-list">
              <div v-for="course in compatibleCourses" :key="course.name" class="compatible-course">
                <div class="course-match" :style="{ backgroundColor: course.color }">
                  {{ course.match }}%
                </div>
                <span class="course-name">{{ course.name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Overall Readiness Summary -->
      <div class="section-card summary-card">
        <h2 class="section-title">Overall Readiness Summary</h2>
        <div class="summary-content">
          <div class="summary-score">
            <div class="score-circle large">
              <div class="score-progress" :style="{ '--progress': readinessSummary.score }">
                <span class="score-number">{{ readinessSummary.score }}%</span>
              </div>
            </div>
          </div>
          <div class="summary-categories">
            <div v-for="category in readinessSummary.categories" :key="category.name" class="summary-item">
              <div class="summary-header">
                <span class="category-name">{{ category.name }}</span>
                <span class="category-score">{{ category.score }}%</span>
              </div>
              <p class="category-status">{{ category.status }}</p>
              <div class="category-progress">
                <div class="progress-bar">
                  <div class="progress-fill" :style="{ width: category.score + '%' }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.readiness-content {
  padding: 24px;
  background: #f8fafc;
  min-height: 100vh;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: white;
  border-radius: 12px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.page-header .header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.page-header .header-icon {
  width: 24px;
  height: 24px;
  filter: hue-rotate(220deg) saturate(2);
}

.page-header .header-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.page-header .header-right {
  display: flex;
  align-items: center;
  gap: 16px;
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
}

.message-icon-btn:hover {
  background: rgba(59, 130, 246, 0.1);
}

.message-icon {
  width: 20px;
  height: 20px;
  filter: hue-rotate(220deg) saturate(2);
}

.company-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.company-name {
  font-size: 14px;
  color: #6b7280;
}

.user-avatar-small {
  width: 32px;
  height: 32px;
  background: #3b82f6;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
}

/* Header Banner */
.readiness-header {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 32px;
  color: white;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 20px;
}

.user-avatar {
  position: relative;
}

.user-avatar img {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(255, 255, 255, 0.3);
}

.avatar-badge {
  position: absolute;
  bottom: -5px;
  right: -5px;
  width: 32px;
  height: 32px;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  backdrop-filter: blur(10px);
}

.user-name {
  font-size: 28px;
  font-weight: 700;
  margin: 0 0 4px 0;
}

.user-title {
  font-size: 16px;
  opacity: 0.9;
  margin: 0 0 12px 0;
}

.readiness-stats {
  display: flex;
  gap: 20px;
}

.stat-item {
  font-size: 14px;
  opacity: 0.9;
}

.overall-score {
  flex-shrink: 0;
}

.score-circle {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.score-circle::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: conic-gradient(
    #fff 0deg,
    #fff calc(var(--progress) * 3.6deg),
    transparent calc(var(--progress) * 3.6deg)
  );
  mask: radial-gradient(circle at center, transparent 70%, white 70%);
  -webkit-mask: radial-gradient(circle at center, transparent 70%, white 70%);
}

.score-number {
  font-size: 24px;
  font-weight: 700;
  z-index: 2;
  position: relative;
}

/* Main Content */
.main-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 20px 0;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.view-details-btn {
  padding: 6px 12px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 12px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.view-details-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

/* Journey Progress */
.journey-progress {
  position: relative;
  padding: 20px 0;
}

.progress-line {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  height: 2px;
  background: #e5e7eb;
  transform: translateY(-50%);
}

.journey-steps {
  display: flex;
  justify-content: space-between;
  position: relative;
  z-index: 1;
}

.journey-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.step-circle {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  background: white;
  border: 2px solid #e5e7eb;
  color: #9ca3af;
}

.journey-step.completed .step-circle {
  background: #10b981;
  border-color: #10b981;
  color: white;
}

.journey-step.active .step-circle {
  background: #fbbf24;
  border-color: #fbbf24;
  color: white;
}

.step-name {
  font-size: 12px;
  color: #6b7280;
  text-align: center;
  max-width: 80px;
}

.journey-step.completed .step-name,
.journey-step.active .step-name {
  color: #374151;
  font-weight: 500;
}

/* Content Grid */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}

.left-column,
.right-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Academic Performance */
.gpa-display {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 20px;
}

.gpa-number {
  font-size: 32px;
  font-weight: 700;
  color: #10b981;
}

.gpa-status {
  font-size: 14px;
  color: #6b7280;
}

.courses-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.course-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.course-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.course-name {
  font-size: 14px;
  color: #374151;
}

.course-grade {
  font-size: 14px;
  font-weight: 600;
  color: #10b981;
}

.course-progress {
  display: flex;
  align-items: center;
  gap: 8px;
}

.progress-bar {
  flex: 1;
  height: 6px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #10b981;
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progress-text {
  font-size: 12px;
  color: #6b7280;
  min-width: 35px;
}

/* Skills Assessment */
.skills-score {
  display: flex;
  align-items: baseline;
  gap: 12px;
  margin-bottom: 20px;
}

.skills-score .score-number {
  font-size: 32px;
  font-weight: 700;
  color: #3b82f6;
}

.score-label {
  font-size: 14px;
  color: #6b7280;
}

.skills-categories {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.skill-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skill-name {
  font-size: 14px;
  color: #374151;
}

.skill-progress {
  display: flex;
  align-items: center;
  gap: 8px;
}

.skill-score {
  font-size: 12px;
  color: #6b7280;
  min-width: 35px;
}

.skills-summary {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  padding-top: 20px;
  border-top: 1px solid #f3f4f6;
}

.skills-summary h4 {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 8px 0;
}

.skills-summary ul {
  margin: 0;
  padding-left: 16px;
}

.skills-summary li {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 4px;
}

/* Document Submission */
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
}

.doc-status {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 12px;
}

.doc-name {
  flex: 1;
  font-size: 14px;
  color: #374151;
}

.doc-status-text {
  font-size: 12px;
  font-weight: 500;
}

.missing-docs-alert {
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 8px;
  padding: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.alert-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.alert-icon {
  font-size: 20px;
}

.alert-text strong {
  font-size: 14px;
  color: #92400e;
  display: block;
}

.alert-text p {
  font-size: 12px;
  color: #92400e;
  margin: 4px 0 0 0;
}

.improve-btn {
  padding: 8px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.improve-btn:hover {
  background: #2563eb;
}

/* Compatible Courses */
.courses-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.compatible-course {
  display: flex;
  align-items: center;
  gap: 12px;
}

.course-match {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  color: white;
  min-width: 45px;
  text-align: center;
}

.compatible-course .course-name {
  font-size: 14px;
  color: #374151;
}

/* Summary Card */
.summary-card {
  margin-top: 20px;
}

.summary-content {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 40px;
  align-items: center;
}

.summary-score .score-circle.large {
  width: 150px;
  height: 150px;
}

.summary-score .score-circle.large::before {
  background: conic-gradient(
    #3b82f6 0deg,
    #3b82f6 calc(var(--progress) * 3.6deg),
    transparent calc(var(--progress) * 3.6deg)
  );
}

.summary-score .score-number {
  font-size: 28px;
  color: #1f2937;
}

.summary-categories {
  display: flex;
  flex-direction: column;
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
  font-weight: 600;
  color: #374151;
}

.category-score {
  font-size: 14px;
  font-weight: 600;
  color: #3b82f6;
}

.category-status {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.category-progress .progress-bar {
  height: 8px;
}

.category-progress .progress-fill {
  background: #3b82f6;
}

/* Responsive */
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
  
  .summary-content {
    grid-template-columns: 1fr;
    text-align: center;
  }
  
  .skills-summary {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .readiness-content {
    padding: 16px;
  }
  
  .header-content {
    flex-direction: column;
    text-align: center;
    gap: 20px;
  }
  
  .journey-steps {
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
  }
  
  .progress-line {
    display: none;
  }
  
  .missing-docs-alert {
    flex-direction: column;
    gap: 12px;
    text-align: center;
  }
}
</style>
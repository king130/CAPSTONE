<script setup lang="ts">
import { ref } from 'vue'

// Dashboard data
const user = ref({
  name: 'Alex',
  internshipProgress: 92,
  totalHours: 240,
  maxHours: 400,
  hoursPercentage: 60
})

const internshipReadiness = ref([
  { task: 'Resume Complete', completed: true },
  { task: 'Skills Assessment Done', completed: true },
  { task: 'Profile Complete', completed: true }
])

const topSkills = ref([
  { name: 'React.js', percentage: 90 },
  { name: 'JavaScript', percentage: 85 },
  { name: 'Git', percentage: 82 },
  { name: 'UI Design', percentage: 78 },
  { name: 'Communication', percentage: 75 }
])

const skillsToImprove = ref([
  { name: 'Testing', gap: '62%', color: '#fecaca' },
  { name: 'System Design', gap: '50%', color: '#fed7aa' },
  { name: 'TypeScript', gap: '25%', color: '#fef3c7' }
])

const recentApplications = ref([
  {
    company: 'TechCorp',
    position: 'Software Engineering Intern',
    status: 'Interview Scheduled',
    statusColor: '#10b981',
    appliedDate: 'Applied: May 15, 2024'
  },
  {
    company: 'DataSystems Inc',
    position: 'Data Analyst Intern',
    status: 'Under Review',
    statusColor: '#f59e0b',
    appliedDate: 'Applied: May 8, 2024'
  },
  {
    company: 'CloudTech Solutions',
    position: 'DevOps Intern',
    status: 'Application Sent',
    statusColor: '#ef4444',
    appliedDate: 'Applied: May 6, 2024'
  }
])
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
        <button @click="$emit('openMessages')" class="message-icon-btn">
          <img src="/icons/icon-message.png" alt="Messages" class="message-icon" />
        </button>
        <div class="status-indicators">
          <div class="status-item">
            <div class="status-dot red"></div>
          </div>
          <div class="status-item">
            <div class="status-dot green"></div>
            <span class="status-text">Active</span>
          </div>
          
        </div>
      </div>
    </div>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="banner-content">
        <h2 class="welcome-title">Welcome, {{ user.name }}!</h2>
        <p class="welcome-subtitle">You're making great progress on your internship journey</p>
        <button class="update-profile-btn">📝 Update Profile</button>
      </div>
      <div class="banner-illustration">
        <div class="dashboard-mockup">
          <div class="mockup-header"></div>
          <div class="mockup-content">
            <div class="mockup-sidebar"></div>
            <div class="mockup-main">
              <div class="mockup-card"></div>
              <div class="mockup-card"></div>
              <div class="mockup-card"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <!-- Internship Readiness -->
      <div class="stat-card">
        <div class="card-header">
          <div class="card-icon green">🛡️</div>
          <h3 class="card-title">Internship Readiness</h3>
          <p class="card-subtitle">Ready to Apply</p>
        </div>
        <div class="readiness-list">
          <div v-for="item in internshipReadiness" :key="item.task" class="readiness-item">
            <div class="check-icon">✓</div>
            <span class="readiness-text">{{ item.task }}</span>
          </div>
        </div>
        <div class="progress-circle">
          <div class="circle-progress" :style="{ '--progress': user.internshipProgress }">
            <span class="progress-number">{{ user.internshipProgress }}</span>
          </div>
        </div>
        <button class="card-button primary">View Full Report</button>
      </div>

      <!-- Best-Fit Department -->
      <div class="stat-card">
        <div class="card-header">
          <div class="card-icon blue">💻</div>
          <h3 class="card-title">Best-Fit Department</h3>
          <p class="card-subtitle">Software Development</p>
        </div>
        <div class="match-info">
          <div class="match-bar">
            <div class="match-progress" style="width: 88%"></div>
          </div>
          <span class="match-percentage">88%</span>
        </div>
        <div class="key-reasons">
          <h4>Key Reasons:</h4>
          <p>Strong technical skills, relevant coursework, and impressive project experience</p>
        </div>
        <button class="card-button primary">View All Recommendations</button>
      </div>

      <!-- Internship Hours -->
      <div class="stat-card">
        <div class="card-header">
          <div class="card-icon orange">⏰</div>
          <h3 class="card-title">Internship Hours</h3>
          <p class="card-subtitle">Track your progress</p>
        </div>
        <div class="hours-progress">
          <div class="hours-circle">
            <div class="circle-progress" :style="{ '--progress': user.hoursPercentage }">
              <span class="progress-number">{{ user.hoursPercentage }}%</span>
            </div>
          </div>
        </div>
        <div class="hours-info">
          <p class="hours-text">{{ user.totalHours }} / {{ user.maxHours }} hours</p>
          <p class="hours-subtext">Last logged: 8 hours (May 15)</p>
        </div>
        <button class="card-button primary">📊 Log Hours</button>
      </div>
    </div>

    <!-- Skills Section -->
    <div class="skills-grid">
      <!-- Your Top Skills -->
      <div class="skills-card">
        <h3 class="skills-title">Your Top Skills</h3>
        <div class="skills-list">
          <div v-for="skill in topSkills" :key="skill.name" class="skill-item">
            <span class="skill-name">{{ skill.name }}</span>
            <div class="skill-bar">
              <div class="skill-progress" :style="{ width: skill.percentage + '%' }"></div>
            </div>
            <span class="skill-percentage">{{ skill.percentage }}%</span>
          </div>
        </div>
      </div>

      <!-- Skills to Develop -->
      <div class="skills-card">
        <h3 class="skills-title">Skills to Develop</h3>
        <div class="improve-skills">
          <div v-for="skill in skillsToImprove" :key="skill.name" class="improve-item">
            <span class="improve-name">{{ skill.name }}</span>
            <span class="improve-gap" :style="{ backgroundColor: skill.color }">Gap: {{ skill.gap }}</span>
          </div>
        </div>
        <div class="recommendations">
          <h4>Recommendations:</h4>
          <ul>
            <li>Complete 2 courses to improve your match score</li>
            <li>Java Testing Fundamentals</li>
            <li>TypeScript for Beginners</li>
          </ul>
        </div>
        <button class="card-button primary">View Full Analysis</button>
      </div>
    </div>

    <!-- Recent Applications -->
    <div class="applications-section">
      <h3 class="section-title">Recent Applications</h3>
      <div class="applications-grid">
        <div v-for="app in recentApplications" :key="app.company" class="application-card">
          <h4 class="app-company">{{ app.company }}</h4>
          <p class="app-position">{{ app.position }}</p>
          <div class="app-status" :style="{ backgroundColor: app.statusColor }">
            {{ app.status }}
          </div>
          <p class="app-date">{{ app.appliedDate }}</p>
          <button class="app-button">View Details</button>
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
  padding: 24px;
  background: #f8fafc;
  min-height: 100vh;
}

/* Header */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
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
  filter: brightness(0) saturate(100%) invert(42%) sepia(93%) saturate(1352%) hue-rotate(220deg) brightness(119%) contrast(119%);
}

.header-title {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.header-right {
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
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border-radius: 16px;
  padding: 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  color: white;
}

.banner-content {
  flex: 1;
}

.welcome-title {
  font-size: 32px;
  font-weight: 700;
  margin: 0 0 8px 0;
}

.welcome-subtitle {
  font-size: 16px;
  opacity: 0.9;
  margin: 0 0 24px 0;
}

.update-profile-btn {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: white;
  padding: 12px 20px;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.update-profile-btn:hover {
  background: rgba(255, 255, 255, 0.3);
}

.banner-illustration {
  flex-shrink: 0;
  margin-left: 32px;
}

.dashboard-mockup {
  width: 200px;
  height: 140px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 8px;
  backdrop-filter: blur(10px);
}

.mockup-header {
  height: 20px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
  margin-bottom: 8px;
}

.mockup-content {
  display: flex;
  gap: 8px;
  height: 100px;
}

.mockup-sidebar {
  width: 40px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
}

.mockup-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.mockup-card {
  height: 28px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 4px;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 24px;
  margin-bottom: 32px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.card-header {
  margin-bottom: 20px;
}

.card-icon {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-bottom: 12px;
}

.card-icon.green {
  background: #dcfce7;
}

.card-icon.blue {
  background: #dbeafe;
}

.card-icon.orange {
  background: #fed7aa;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.card-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.readiness-list {
  margin-bottom: 20px;
}

.readiness-item {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.check-icon {
  width: 20px;
  height: 20px;
  background: #10b981;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.readiness-text {
  font-size: 14px;
  color: #374151;
}

.progress-circle {
  display: flex;
  justify-content: center;
  margin-bottom: 20px;
}

.circle-progress {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.circle-progress::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: conic-gradient(
    #10b981 0deg,
    #10b981 calc(var(--progress) * 3.6deg),
    transparent calc(var(--progress) * 3.6deg)
  );
  mask: radial-gradient(circle at center, transparent 65%, white 65%);
  -webkit-mask: radial-gradient(circle at center, transparent 65%, white 65%);
}

.progress-number {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  z-index: 2;
  position: relative;
}

.match-info {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.match-bar {
  flex: 1;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.match-progress {
  height: 100%;
  background: #3b82f6;
  border-radius: 4px;
}

.match-percentage {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.key-reasons {
  margin-bottom: 20px;
}

.key-reasons h4 {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.key-reasons p {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
  line-height: 1.5;
}

.hours-progress {
  display: flex;
  justify-content: center;
  margin-bottom: 16px;
}

.hours-circle {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.hours-circle::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: conic-gradient(
    #f59e0b 0deg,
    #f59e0b calc(var(--progress) * 3.6deg),
    transparent calc(var(--progress) * 3.6deg)
  );
  mask: radial-gradient(circle at center, transparent 65%, white 65%);
  -webkit-mask: radial-gradient(circle at center, transparent 65%, white 65%);
}

.hours-info {
  text-align: center;
  margin-bottom: 20px;
}

.hours-text {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.hours-subtext {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
}

.card-button {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.card-button.primary {
  background: #3b82f6;
  color: white;
}

.card-button.primary:hover {
  background: #2563eb;
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

/* Responsive */
@media (max-width: 1024px) {
  .skills-grid {
    grid-template-columns: 1fr;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .welcome-banner {
    flex-direction: column;
    text-align: center;
  }
  
  .banner-illustration {
    margin-left: 0;
    margin-top: 24px;
  }
  
  .dashboard-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
}
</style>
<script setup lang="ts">
import { ref, computed } from 'vue'
import SchoolSidebar from '../../components/SchoolSidebar.vue'
import FloatingChatWidget from '../../components/FloatingChatWidget.vue'
import { useAuthStore } from '@/stores/auth'
import Swal from 'sweetalert2'
import { BellIcon } from '@heroicons/vue/24/outline'

const authStore = useAuthStore()
const schoolName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.institutionName as string) || authStore.user?.displayName || 'School'
})

const currentView = ref('dashboard')
const activeSettingsTab = ref('account')

const handleMenuClick = (item: string) => {
  currentView.value = item
}

const setActiveSettingsTab = (tab: string) => {
  activeSettingsTab.value = tab
}

// Student sidebar state
const showStudentSidebar = ref(false)
const selectedStudent = ref<any>(null)

// Approval modal state
const showApprovalModal = ref(false)
const approvalRemarks = ref('')

// Company sidebar state
const showCompanySidebar = ref(false)
const selectedCompany = ref<any>(null)
const activeCompanyTab = ref('general')

// Submit Report Modal state
const showSubmitReportModal = ref(false)
const reportFormData = ref({
  reportType: '',
  studentName: 'John Doe',
  internshipPeriodStart: '',
  internshipPeriodEnd: '',
  companyName: 'Tech Solutions Inc.'
})

function openSubmitReportModal() {
  console.log('Opening submit report page')
  currentView.value = 'submit-report'
}

function closeSubmitReportModal() {
  currentView.value = 'reports'
  // Reset form data
  reportFormData.value = {
    reportType: '',
    studentName: 'John Doe',
    internshipPeriodStart: '',
    internshipPeriodEnd: '',
    companyName: 'Tech Solutions Inc.'
  }
}

function saveAsDraft() {
  // Handle save as draft logic
  console.log('Saving as draft:', reportFormData.value)
  currentView.value = 'reports'
}

function nextStep() {
  // Handle next step logic
  console.log('Going to next step:', reportFormData.value)
  // For now, just go back to reports
  currentView.value = 'reports'
}

// Sample company data
const companiesData = ref([
  {
    id: 1,
    name: 'Innovate Corp.',
    avatar: 'IC',
    position: 'Software Engineer Intern',
    location: 'New York (Hybrid)',
    match: 92,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'AWS', 'Machine Learning', 'Django'],
    rating: 4.8,
    placement: 95,
    generalInfo: {
      address: 'New York (Hybrid)',
      contactEmail: 'innovative@gmail.com',
      website: 'www.InnovativeCorp.com',
      partnerStatus: 'Active'
    },
    keyContacts: [
      {
        name: 'Acme Corp.',
        title: 'Project Manager',
        email: 'manager@acme.com'
      }
    ],
    internshipOfferings: [
      {
        title: 'Software Engineering',
        requirements: 'Python, Django, SQL, Machine Learning, C++ Project management understanding',
        placement: 2,
        status: 'Active'
      },
      {
        title: 'Data Analyst Intern',
        requirements: 'Requirements: Python, SQL, Machine Learning Local data dynamic team to develop innovative',
        placement: 2,
        status: 'Active'
      },
      {
        title: 'UI/UX Designer Intern',
        requirements: 'Requirements: Figma, Prototyping, Design System Join dynamic team to develop innovative',
        placement: 2,
        status: 'Active'
      },
      {
        title: 'Business Development Intern',
        requirements: 'Requirements: Python, SQL, Machine Learning Local Join dynamic team to develop innovative',
        placement: 2,
        status: 'Active'
      }
    ],
    moaHistory: [
      {
        title: 'MOA_Innovatice Corp.',
        date: '2026-30-1',
        status: 'Active',
        expires: '2026-30-1'
      }
    ],
    internFeedback: [
      {
        name: 'Peter Jones',
        rating: 4,
        feedback: '"Hands-on Experience, learned a lot about site management and safety protocols"'
      }
    ]
  }
])

// Sample student data
const studentsData = ref([
  {
    id: 1,
    name: 'John Doe',
    studentId: '2020-0001',
    course: 'BSIT',
    yearLevel: '4th Year',
    status: 'Registered',
    eligibility: 'Eligible',
    company: 'Electro Systems',
    position: 'Software Development Intern',
    applicationDate: 'January 15, 2024',
    documents: [
      { name: 'Resume.pdf', type: 'pdf' },
      { name: 'Recommendation Letter.docx', type: 'docx' },
      { name: 'Transcript of Records.jpg', type: 'jpg' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 15, 2024 - 2:30 PM', completed: true },
      { status: 'Reviewed by Academic Advisor', date: 'January 16, 2024 - 10:15 AM', completed: true },
      { status: 'Company Interview & Approval', date: 'January 18, 2024 - 3:45 PM', completed: true },
      { status: 'Endorsed to Company', date: 'January 20, 2024 - 9:00 AM', completed: true }
    ]
  },
  {
    id: 2,
    name: 'Jane Smith',
    studentId: '2020-0002',
    course: 'BSCS',
    yearLevel: '3rd Year',
    status: 'Active Intern',
    eligibility: 'Pending',
    company: 'Tech Solutions Inc.',
    position: 'Web Developer Intern',
    applicationDate: 'January 10, 2024',
    documents: [
      { name: 'CV_JaneSmith.pdf', type: 'pdf' },
      { name: 'Portfolio.zip', type: 'zip' },
      { name: 'Grades.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 10, 2024 - 9:15 AM', completed: true },
      { status: 'Initial Screening', date: 'January 11, 2024 - 2:00 PM', completed: true },
      { status: 'Technical Interview', date: 'January 12, 2024 - 4:30 PM', completed: false },
      { status: 'Final Approval Pending', date: 'Pending', completed: false }
    ]
  },
  {
    id: 3,
    name: 'Peter Jones',
    studentId: '2020-0003',
    course: 'BSIT',
    yearLevel: '4th Year',
    status: 'Pending Application',
    eligibility: 'Not Eligible',
    company: 'DataCorp Analytics',
    position: 'Data Analyst Intern',
    applicationDate: 'January 20, 2024',
    documents: [
      { name: 'Resume_Peter.pdf', type: 'pdf' },
      { name: 'CertificationLetter.docx', type: 'docx' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 20, 2024 - 11:45 AM', completed: true },
      { status: 'Document Review', date: 'January 21, 2024 - 3:20 PM', completed: false },
      { status: 'Eligibility Check', date: 'Pending', completed: false },
      { status: 'Company Review', date: 'Pending', completed: false }
    ]
  },
  {
    id: 4,
    name: 'Alice Brown',
    studentId: '2020-0004',
    course: 'BSECE',
    yearLevel: '3rd Year',
    status: 'Registered',
    eligibility: 'Eligible',
    company: 'Innovation Labs',
    position: 'Hardware Engineer Intern',
    applicationDate: 'January 8, 2024',
    documents: [
      { name: 'AliceBrown_Resume.pdf', type: 'pdf' },
      { name: 'ProjectPortfolio.pdf', type: 'pdf' },
      { name: 'Transcript.jpg', type: 'jpg' },
      { name: 'RecommendationLetter.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'January 8, 2024 - 1:10 PM', completed: true },
      { status: 'Academic Review', date: 'January 9, 2024 - 10:30 AM', completed: true },
      { status: 'Company Screening', date: 'January 10, 2024 - 2:45 PM', completed: true },
      { status: 'Placement Confirmed', date: 'January 11, 2024 - 4:00 PM', completed: true }
    ]
  },
  {
    id: 5,
    name: 'Michael White',
    studentId: '2020-0005',
    course: 'BSIT',
    yearLevel: '4th Year',
    status: 'Active Intern',
    eligibility: 'Eligible',
    company: 'Creative Design Studio',
    position: 'UI/UX Design Intern',
    applicationDate: 'December 28, 2023',
    documents: [
      { name: 'MichaelWhite_CV.pdf', type: 'pdf' },
      { name: 'DesignPortfolio.pdf', type: 'pdf' },
      { name: 'AcademicRecords.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'December 28, 2023 - 3:25 PM', completed: true },
      { status: 'Portfolio Review', date: 'December 29, 2023 - 11:00 AM', completed: true },
      { status: 'Design Challenge', date: 'January 2, 2024 - 2:15 PM', completed: true },
      { status: 'Internship Started', date: 'January 5, 2024 - 8:00 AM', completed: true }
    ]
  },
  {
    id: 6,
    name: 'Emily Green',
    studentId: '2020-0006',
    course: 'BSCS',
    yearLevel: '4th Year',
    status: 'Completed',
    eligibility: 'Eligible',
    company: 'TechStart Solutions',
    position: 'Full Stack Developer Intern',
    applicationDate: 'November 15, 2023',
    documents: [
      { name: 'EmilyGreen_Resume.pdf', type: 'pdf' },
      { name: 'CodingProjects.zip', type: 'zip' },
      { name: 'FinalTranscript.pdf', type: 'pdf' },
      { name: 'CompletionCertificate.pdf', type: 'pdf' }
    ],
    timeline: [
      { status: 'Application Submitted', date: 'November 15, 2023 - 10:20 AM', completed: true },
      { status: 'Technical Assessment', date: 'November 16, 2023 - 2:30 PM', completed: true },
      { status: 'Final Interview', date: 'November 18, 2023 - 4:00 PM', completed: true },
      { status: 'Internship Completed', date: 'January 15, 2024 - 5:00 PM', completed: true }
    ]
  }
])

function openStudentSidebar(student: any) {
  selectedStudent.value = student
  showStudentSidebar.value = true
}

function closeStudentSidebar() {
  showStudentSidebar.value = false
  selectedStudent.value = null
}

function approveStudent() {
  showApprovalModal.value = true
}

function cancelApproval() {
  showApprovalModal.value = false
  approvalRemarks.value = ''
}

async function submitApproval() {
  if (selectedStudent.value) {
    selectedStudent.value.status = 'Approved'
    // Here you would typically send the approval with remarks to the backend
    console.log('Approved with remarks:', approvalRemarks.value)
    showApprovalModal.value = false
    approvalRemarks.value = ''
    closeStudentSidebar()

    // Show success SweetAlert
    await Swal.fire({
      icon: 'success',
      iconColor: '#10b981',
      title: 'Apply for Internship Now!',
      text: 'Application Approved With Remarks!',
      confirmButtonText: 'Confirm',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  }
}

async function rejectStudent() {
  if (selectedStudent.value) {
    selectedStudent.value.status = 'Rejected'
    closeStudentSidebar()

    // Show rejection SweetAlert
    await Swal.fire({
      icon: 'error',
      iconColor: '#ef4444',
      title: 'Not Eligible',
      text: 'Application Rejected With Remarks!',
      confirmButtonText: 'Confirm',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  }
}

function openCompanySidebar(company: any) {
  selectedCompany.value = company
  showCompanySidebar.value = true
  activeCompanyTab.value = 'general'
}

function closeCompanySidebar() {
  showCompanySidebar.value = false
  selectedCompany.value = null
  activeCompanyTab.value = 'general'
}

function setActiveCompanyTab(tab: string) {
  activeCompanyTab.value = tab
}

// Dashboard stats
const dashboardStats = ref({
  ojtHours: { current: 245, total: 400, remaining: 155 },
  applications: { active: 3, underReview: 1, scheduled: 1, accepted: 1 },
  currentInternship: {
    company: 'Tech Solutions Inc.',
    position: 'Software Development Intern',
    status: 'Active since Jan 1'
  },
  pendingTasks: [
    { task: 'Submit Weekly Journal', type: 'journal' },
    { task: 'Complete Evaluation', type: 'evaluation' }
  ]
})

// Recommended internships
const recommendedInternships = ref([
  {
    id: 1,
    title: 'Marketing Assistant',
    company: 'Digital Marketing Co.',
    location: 'Cavite City',
    match: 92,
    skills: ['Social Media Marketing', 'Content Creation', 'Analytics']
  },
  {
    id: 2,
    title: 'Web Developer',
    company: 'Creative Tech Studio',
    location: 'Bacoor',
    match: 88,
    skills: ['HTML/CSS', 'JavaScript', 'Responsive Design']
  },
  {
    id: 3,
    title: 'Graphic Designer',
    company: 'Design Hub Agency',
    location: 'Imus City',
    match: 85,
    skills: ['Adobe Photoshop', 'Illustrator', 'Branding']
  }
])

// Recent activities
const recentActivities = ref([
  {
    id: 1,
    text: 'Application accepted by Tech Solutions',
    time: '2 hours ago',
    status: 'success'
  },
  {
    id: 2,
    text: 'Weekly journal submitted',
    time: '1 day ago',
    status: 'info'
  },
  {
    id: 3,
    text: 'Time out recorded',
    time: '1 day ago',
    status: 'neutral'
  },
  {
    id: 4,
    text: 'Application submitted to Creative Agency',
    time: '3 days ago',
    status: 'info'
  }
])

// Upcoming deadlines
const upcomingDeadlines = ref([
  {
    id: 1,
    title: 'Weekly Journal Due',
    date: 'Jan 20',
    daysRemaining: 2,
    type: 'warning'
  },
  {
    id: 2,
    title: 'Evaluation Form',
    date: 'Jan 25',
    daysRemaining: 7,
    type: 'info'
  }
])

// School Info Data
const schoolInfoData = ref({
  institutionDetails: {
    schoolName: '',
    logo: null,
    officialAddress: '',
    region: 'NCR',
    province: 'Manila',
    cityMunicipality: '',
    barangay: '',
    zipCode: ''
  },
  ojtCoordinatorInfo: {
    coordinatorName: '',
    position: '',
    phoneNumber: '',
    mobileNumber: '',
    emailAddress: '',
    officeLocation: ''
  },
  schoolContactInfo: {
    mainOfficeNumber: '',
    studentAffairsNumber: '',
    registrarNumber: '',
    faxNumber: '',
    websiteUrl: '',
    officialEmail: ''
  },
  academicCalendar: {
    currentTermSemester: 'First Semester',
    termStartDate: '',
    termEndDate: ''
  },
  socialMediaLinks: {
    facebookPage: '',
    twitterAccount: '',
    linkedInPage: '',
    instagramAccount: ''
  }
})

const academicTerms = ref([
  {
    id: 1,
    termName: 'First Semester AY 2023-2024',
    startDate: 'Aug 15, 2023',
    endDate: 'Dec 15, 2023',
    status: 'Active'
  }
])

// OJT Configuration Data
const ojtConfigData = ref({
  programRequirements: {
    minimumOjtHours: 486,
    allowFlexibleHours: false,
    minimumAttendanceRate: 95,
    requiredDocuments: ['Resume', 'Recommendation Letter', 'Medical Certificate'],
    gradeRequirement: 2.5,
    prerequisiteSubjects: '',
    yearLevelRequirement: '3rd Year'
  },
  applicationSettings: {
    enableApplications: true,
    requireApproval: true,
    autoMatchStudents: false,
    allowMultipleApplications: true,
    maxSimultaneousApplications: 3,
    applicationDeadline: '',
    notifyNewApplications: true,
    notifyStatusChanges: true
  },
  monitoringEvaluation: {
    weeklyJournalRequired: true,
    journalSubmissionDay: 'Friday',
    midtermEvaluationWeek: 6,
    finalEvaluationWeek: 12,
    supervisorEvaluationRequired: true,
    studentSelfEvaluation: true,
    allowLateSubmissions: false
  },
  companyRequirements: {
    moaRequired: true,
    moaValidityPeriod: 2,
    companyVerification: true,
    minimumCompanyRating: 3.5,
    requireCompanyProfile: true,
    backgroundCheckRequired: false
  },
  studentRequirements: {
    completedUnits: 90,
    gpa: 2.5,
    noFailingGrades: true,
    clearanceRequired: true,
    parentConsent: true,
    insuranceRequired: true,
    nbiClearance: false,
    medicalCertificate: true,
    resume: true,
    recommendationLetter: true
  },
  evaluationSettings: {
    enableEvaluations: true,
    studentEvaluatesCompany: true,
    companyEvaluatesStudent: true,
    schoolEvaluatesPlacement: true,
    evaluationFrequency: 'Monthly',
    anonymousEvaluations: false
  }
})

const requiredDocumentsList = ref([
  { id: 1, name: 'Resume/CV', required: true },
  { id: 2, name: 'Recommendation Letter', required: true },
  { id: 3, name: 'Medical Certificate', required: true },
  { id: 4, name: 'NBI Clearance', required: false },
  { id: 5, name: 'Transcript of Records', required: true }
])


</script>

<template>
  <div class="dashboard">
    <!-- School Sidebar Component -->
    <SchoolSidebar :activeItem="currentView" @menuClick="handleMenuClick" />

    <!-- Main Content Area -->
    <div class="main-wrapper">
      <!-- Dashboard View -->
      <div v-if="currentView === 'dashboard'">
        <!-- Top Header -->
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-dashboard.png" alt="Dashboard" class="header-icon-img" />
            <h1>Dashboard</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
          <!-- Welcome Banner -->
          <div class="welcome-banner">
            <h2>Hello, Admin! 👋</h2>
            <p>You have {{ dashboardStats.applications.active }} pending applications and {{ recommendedInternships.length }} recommended internship.</p>
          </div>

          <!-- Stats Cards -->
          <div class="stats-grid">
            <!-- OJT Hours Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">OJT HOURS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ dashboardStats.ojtHours.current }} / {{ dashboardStats.ojtHours.total }}</div>
                <div class="progress-bar-container">
                  <div class="progress-bar" :style="{ width: (dashboardStats.ojtHours.current / dashboardStats.ojtHours.total * 100) + '%' }"></div>
                </div>
                <div class="stat-subtitle">{{ dashboardStats.ojtHours.remaining }} hours remaining</div>
              </div>
              <a href="#" class="stat-link">View details →</a>
            </div>

            <!-- Applications Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">APPLICATIONS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ dashboardStats.applications.active }} Active</div>
                <div class="stat-details">
                  <div>{{ dashboardStats.applications.underReview }} Under Review</div>
                  <div>{{ dashboardStats.applications.scheduled }} Interview Scheduled</div>
                  <div>{{ dashboardStats.applications.accepted }} Accepted</div>
                </div>
              </div>
              <a href="#" class="stat-link">View all →</a>
            </div>

            <!-- Current Internship Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">CURRENT INTERNSHIP</span>
              </div>
              <div class="stat-main">
                <div class="company-logo-card">
                  <img src="/icons/company-icon.png" alt="Company" />
                </div>
                <div class="company-info-card">
                  <div class="company-name-card">{{ dashboardStats.currentInternship.company }}</div>
                  <div class="position-title-card">{{ dashboardStats.currentInternship.position }}</div>
                  <div class="status-card">{{ dashboardStats.currentInternship.status }}</div>
                </div>
              </div>
              <a href="#" class="stat-link">View details →</a>
            </div>

            <!-- Pending Tasks Card -->
            <div class="stat-card">
              <div class="stat-header">
                <span class="stat-label">PENDING TASKS</span>
              </div>
              <div class="stat-main">
                <div class="stat-number-large">{{ dashboardStats.pendingTasks.length }}</div>
                <div class="task-list-card">
                  <div v-for="task in dashboardStats.pendingTasks" :key="task.task" class="task-item-card">
                    • {{ task.task }}
                  </div>
                </div>
              </div>
              <a href="#" class="stat-link">View tasks →</a>
            </div>
          </div>

          <!-- Recommended Section -->
          <section class="recommended-section">
            <div class="section-header">
              <h3>Recommended for You</h3>
              <p>Based on your profile</p>
            </div>

            <div class="internships-grid">
              <div v-for="internship in recommendedInternships" :key="internship.id" class="internship-card">
                <div class="internship-header">
                  <div class="company-logo-small">
                    <img src="/icons/company-icon.png" alt="Company" />
                  </div>
                  <div class="match-badge">{{ internship.match }}% Match</div>
                </div>
                
                <div class="internship-content">
                  <h4>{{ internship.title }}</h4>
                  <p class="company">{{ internship.company }}</p>
                  <p class="location">📍 {{ internship.location }}</p>
                  
                  <div class="skills-tags">
                    <span v-for="skill in internship.skills" :key="skill" class="skill-tag">
                      {{ skill }}
                    </span>
                  </div>
                </div>

                <div class="internship-actions">
                  <button class="btn-primary">View Details</button>
                  <button class="btn-secondary">
                    <img src="/icons/heart-icon.png" alt="Like" class="heart-icon" />
                  </button>
                </div>
              </div>
            </div>
          </section>

          <!-- Bottom Grid -->
          <div class="bottom-grid">
            <!-- Recent Activity -->
            <section class="activity-section">
              <h3>Recent Activity</h3>
              <div class="activity-list">
                <div v-for="activity in recentActivities" :key="activity.id" class="activity-item">
                  <div class="activity-dot" :class="activity.status"></div>
                  <div class="activity-content">
                    <p>{{ activity.text }}</p>
                    <span class="activity-time">{{ activity.time }}</span>
                  </div>
                </div>
              </div>
              <a href="#" class="view-all-link">View all activity →</a>
            </section>

            <!-- Upcoming Deadlines -->
            <section class="deadlines-section">
              <h3>Upcoming Deadlines</h3>
              <div class="deadlines-list">
                <div v-for="deadline in upcomingDeadlines" :key="deadline.id" class="deadline-item">
                  <div class="deadline-icon" :class="deadline.type">
                    <span v-if="deadline.type === 'warning'">⚠️</span>
                    <span v-else>ℹ️</span>
                  </div>
                  <div class="deadline-content">
                    <p class="deadline-title">{{ deadline.title }}</p>
                    <span class="deadline-date">{{ deadline.date }} • {{ deadline.daysRemaining }} days remaining</span>
                  </div>
                </div>
              </div>
              <a href="#" class="view-all-link">📅 View calendar</a>
            </section>
          </div>
        </main>
      </div>

      <!-- Other Views (Placeholder) -->
      <div v-else-if="currentView === 'internship-post'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-post_new_internship.png" alt="Internship Post" class="header-icon-img" />
            <h1>Pending Approvals</h1>
          </div>
          <div class="header-right">
            <div class="header-actions">
              <div class="status-dropdown">
                <select class="dropdown-select">
                  <option value="all-pending">All pending</option>
                  <option value="new">New</option>
                  <option value="under-review">Under Review</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
              <input type="search" placeholder="Search by company or position..." class="search-input" />
            </div>
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <div class="approvals-layout">
            <!-- Left Column - Pending Posts -->
            <div class="approvals-main">
              <p class="approvals-subtitle">Review and approve internship posts from partner companies</p>
              
              <!-- Alert Banner -->
              <div class="alert-banner">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                  <strong>3 New Internship Post Awaiting Review</strong>
                  <p>Review now to make them available to students</p>
                </div>
              </div>

              <!-- Internship Posts -->
              <div class="internship-posts">
                <!-- TechStart Solutions Post -->
                <div class="post-card">
                  <div class="post-header">
                    <div class="company-avatar">TS</div>
                    <div class="company-info">
                      <div class="company-name">
                        TechStart Solutions
                        <span class="verified-badge">✓</span>
                      </div>
                      <div class="company-type">Information Technology</div>
                    </div>
                    <div class="status-badge status-new">New</div>
                  </div>

                  <div class="post-content">
                    <h3 class="post-title">Full Stack Development Intern</h3>
                    <div class="post-details">
                      <div class="detail-item">
                        <span class="detail-icon">📍</span>
                        <span>Bacoor City</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">📅</span>
                        <span>3 months (June - August 2024)</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">👥</span>
                        <span>5 openings</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">💰</span>
                        <span>₱15,000/month</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">📋</span>
                        <span>Full-time Status</span>
                      </div>
                    </div>

                    <p class="post-description">
                      BS Computer Science 3rd year or higher. Proficient in React and Redux, Node.js communication skills.
                      View full Document requirements
                    </p>

                    <div class="post-tags">
                      <span class="tag tag-skill">JavaScript</span>
                      <span class="tag tag-skill">Business Portal</span>
                      <span class="tag tag-skill">Previous Postings</span>
                      <span class="tag tag-count">+3/5</span>
                    </div>
                  </div>

                  <div class="post-actions">
                    <button class="btn-approve">Review Posting</button>
                    <button class="btn-secondary-action">Quick Approve</button>
                  </div>
                </div>

                <!-- DataCorp Analytics Post -->
                <div class="post-card">
                  <div class="post-header">
                    <div class="company-avatar">TS</div>
                    <div class="company-info">
                      <div class="company-name">
                        DataCorp Analytics
                        <span class="verified-badge">✓</span>
                      </div>
                      <div class="company-type">Data Science & Analytics</div>
                    </div>
                    <div class="status-badge status-interview">Interview</div>
                  </div>

                  <div class="post-content">
                    <h3 class="post-title">Data Analytics Intern</h3>
                    <div class="post-details">
                      <div class="detail-item">
                        <span class="detail-icon">📍</span>
                        <span>BGC, Taguig</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">📅</span>
                        <span>4 months (June - August 2024)</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">👥</span>
                        <span>3 openings</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">💰</span>
                        <span>₱18,000/month</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">📋</span>
                        <span>From May 30, 2024</span>
                      </div>
                    </div>

                    <p class="post-description">
                      BS Statistics, Mathematics or Computer Science. Experience with Python, SQL, and data visualization tools.
                      View full Document requirements
                    </p>

                    <div class="post-tags">
                      <span class="tag tag-skill">Python</span>
                      <span class="tag tag-skill">Business Portal</span>
                      <span class="tag tag-skill">Previous Postings</span>
                      <span class="tag tag-count">+4/6</span>
                    </div>
                  </div>

                  <div class="post-actions">
                    <button class="btn-approve">Review Posting</button>
                    <button class="btn-secondary-action">Request Changes</button>
                  </div>
                </div>

                <!-- Creative Design Studio Post -->
                <div class="post-card">
                  <div class="post-header">
                    <div class="company-avatar">TS</div>
                    <div class="company-info">
                      <div class="company-name">
                        Creative Design Studio
                        <span class="verified-badge">✓</span>
                      </div>
                      <div class="company-type">Creative & Design</div>
                    </div>
                    <div class="status-badge status-rejected">Rejected</div>
                  </div>

                  <div class="post-content">
                    <h3 class="post-title">UI/UX Design Intern</h3>
                    <div class="post-details">
                      <div class="detail-item">
                        <span class="detail-icon">📍</span>
                        <span>Alabang, Muntinlupa</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">📅</span>
                        <span>3 months (June - August 2024)</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">👥</span>
                        <span>2 openings</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">💰</span>
                        <span>₱12,000/month</span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-icon">📋</span>
                        <span>From June 15, 2024</span>
                      </div>
                    </div>

                    <p class="post-description">
                      BS Multimedia Arts or related field. Portfolio required. Proficiency in Figma, Adobe XD, and design principles.
                      View full Document requirements
                    </p>

                    <div class="post-tags">
                      <span class="tag tag-skill">Figma</span>
                      <span class="tag tag-skill">Business Portal</span>
                      <span class="tag tag-skill">Previous Postings</span>
                      <span class="tag tag-count">+2/8</span>
                    </div>
                  </div>

                  <div class="post-actions">
                    <button class="btn-approve">Review Posting</button>
                    <button class="btn-secondary-action">Quick Approve</button>
                  </div>
                </div>
              </div>

              <div class="pagination">
                <span>Showing 3 of 5 pending posts</span>
                <a href="#" class="view-archive">View Approved/Reject Archive</a>
              </div>
            </div>

            <!-- Right Sidebar -->
            <div class="approvals-sidebar">
              <!-- Review Statistics -->
              <div class="sidebar-section">
                <h3>Review Statistics</h3>
                <div class="stats-list">
                  <div class="stat-item">
                    <span class="stat-label">This Week Reviewed</span>
                    <span class="stat-value">8</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Approved</span>
                    <span class="stat-value">7</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Rejected</span>
                    <span class="stat-value">1</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Average Review Time</span>
                    <span class="stat-value">4 hours</span>
                  </div>
                  <div class="stat-item">
                    <span class="stat-label">Pending Backlog</span>
                    <span class="stat-value">3</span>
                  </div>
                </div>
              </div>

              <!-- Quick Actions -->
              <div class="sidebar-section">
                <h3>Quick Actions</h3>
                <div class="quick-actions">
                  <button class="quick-action-btn">
                    <span class="action-icon">✓</span>
                    <span>Approve All Verified</span>
                  </button>
                  <button class="quick-action-btn">
                    <span class="action-icon">📋</span>
                    <span>Schedule Batch Meeting</span>
                  </button>
                  <button class="quick-action-btn">
                    <span class="action-icon">📧</span>
                    <span>Send Company Notification</span>
                  </button>
                </div>
              </div>

              <!-- Recent Activity -->
              <div class="sidebar-section">
                <h3>Recent Activity</h3>
                <div class="activity-feed">
                  <div class="activity-item">
                    <span class="activity-text">Approved TechHub's system post</span>
                    <span class="activity-time">2 hours ago</span>
                  </div>
                  <div class="activity-item">
                    <span class="activity-text">Rejected application from DataCorp</span>
                    <span class="activity-time">5 hours ago</span>
                  </div>
                  <div class="activity-item">
                    <span class="activity-text">Company submitted new post</span>
                    <span class="activity-time">1 day ago</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'applications'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-application.png" alt="Applications" class="header-icon-img" />
            <h1>Internship Applications</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Search and Filters -->
          <div class="applications-controls">
            <div class="search-section">
              <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="search" placeholder="Search Student..." class="search-input-table" />
              </div>
            </div>
            <div class="filter-section">
              <select class="filter-dropdown">
                <option value="all-courses">All Courses</option>
                <option value="bsit">BSIT</option>
                <option value="bscs">BSCS</option>
                <option value="bsece">BSECE</option>
              </select>
              <select class="filter-dropdown">
                <option value="all-years">All Year Level</option>
                <option value="3rd-year">3rd Year</option>
                <option value="4th-year">4th Year</option>
              </select>
              <select class="filter-dropdown">
                <option value="pending">Pending Applications</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="active">Active Intern</option>
                <option value="completed">Completed</option>
              </select>
            </div>
          </div>

          <!-- Applications Table -->
          <div class="applications-table-container">
            <table class="applications-table">
              <thead>
                <tr>
                  <th>Student Name</th>
                  <th>Student ID</th>
                  <th>Course</th>
                  <th>Year Level</th>
                  <th>Status</th>
                  <th>Eligibility</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="student in studentsData" :key="student.id" @click="openStudentSidebar(student)" class="clickable-row">
                  <td class="student-name">{{ student.name }}</td>
                  <td class="student-id">{{ student.studentId }}</td>
                  <td>{{ student.course }}</td>
                  <td>{{ student.yearLevel }}</td>
                  <td>
                    <span class="status-badge" :class="{
                      'status-registered': student.status === 'Registered',
                      'status-active': student.status === 'Active Intern',
                      'status-pending': student.status === 'Pending Application',
                      'status-completed': student.status === 'Completed'
                    }">
                      {{ student.status }}
                    </span>
                  </td>
                  <td>
                    <span class="eligibility-badge" :class="{
                      'eligible': student.eligibility === 'Eligible',
                      'pending': student.eligibility === 'Pending',
                      'not-eligible': student.eligibility === 'Not Eligible'
                    }">
                      {{ student.eligibility }}
                    </span>
                  </td>
                  <td class="actions-cell">
                    <button class="action-menu-btn" @click.stop>⋯</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </main>

        <!-- Student Details Sidebar -->
        <div v-if="showStudentSidebar" class="student-sidebar-overlay" @click="closeStudentSidebar">
          <div class="student-sidebar" @click.stop>
            <div class="sidebar-header">
              <h2>{{ selectedStudent?.name }} - Application</h2>
              <button class="close-btn" @click="closeStudentSidebar">✕</button>
            </div>

            <div class="sidebar-content">
              <!-- Student Profile Summary -->
              <section class="sidebar-section">
                <h3 class="section-title">Student Profile Summary</h3>
                <div class="profile-info">
                  <div class="info-row">
                    <span class="info-label">Student ID:</span>
                    <span class="info-value">{{ selectedStudent?.studentId }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Course:</span>
                    <span class="info-value">{{ selectedStudent?.course }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Eligibility:</span>
                    <span class="eligibility-badge eligible">{{ selectedStudent?.eligibility }}</span>
                  </div>
                </div>
              </section>

              <!-- Application Information -->
              <section class="sidebar-section">
                <h3 class="section-title">Application Information</h3>
                <div class="profile-info">
                  <div class="info-row">
                    <span class="info-label">Company:</span>
                    <span class="info-value">{{ selectedStudent?.company }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Position:</span>
                    <span class="info-value">{{ selectedStudent?.position }}</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Application Date:</span>
                    <span class="info-value">{{ selectedStudent?.applicationDate }}</span>
                  </div>
                </div>
              </section>

              <!-- Submitted Documents -->
              <section class="sidebar-section">
                <h3 class="section-title">Submitted Documents</h3>
                <div class="documents-list">
                  <div v-for="doc in selectedStudent?.documents" :key="doc.name" class="document-item">
                    <span class="doc-icon">📄</span>
                    <span class="doc-name">{{ doc.name }}</span>
                  </div>
                </div>
              </section>

              <!-- Application History Timeline -->
              <section class="sidebar-section">
                <h3 class="section-title">Application History Timeline</h3>
                <div class="timeline">
                  <div v-for="(item, index) in selectedStudent?.timeline" :key="index" class="timeline-item">
                    <div class="timeline-dot" :class="{ completed: item.completed }"></div>
                    <div class="timeline-content">
                      <div class="timeline-status">{{ item.status }}</div>
                      <div class="timeline-date">{{ item.date }}</div>
                    </div>
                  </div>
                </div>
              </section>
            </div>

            <!-- Action Buttons -->
            <div class="sidebar-actions">
              <button class="btn-approve-sidebar" @click="approveStudent">
                ✓ Approve
              </button>
              <button class="btn-reject-sidebar" @click="rejectStudent">
                ✕ Reject
              </button>
            </div>
          </div>
        </div>

        <!-- Approval Modal -->
        <div v-if="showApprovalModal" class="modal-overlay" @click="cancelApproval">
          <div class="approval-modal" @click.stop>
            <div class="modal-header">
              <h3>Approve Application - Add Remarks</h3>
            </div>

            <div class="modal-content">
              <textarea 
                v-model="approvalRemarks"
                placeholder="Enter remarks here..."
                class="remarks-textarea"
                rows="6"
              ></textarea>
            </div>

            <div class="modal-actions">
              <button class="btn-cancel" @click="cancelApproval">Cancel</button>
              <button class="btn-submit" @click="submitApproval">Submit</button>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="currentView === 'companies'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/company-icon.png" alt="Companies" class="header-icon-img" />
            <h1>Companies</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Page Header -->
          <div class="companies-page-header">
            <h2>Manage Industry Partners</h2>
            <p class="page-subtitle">Oversee company profiles, internship offerings, MOAs, and performance feedback for all partner organizations.</p>
          </div>

          <!-- Search and Filters -->
          <div class="companies-controls">
            <div class="search-section">
              <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="search" placeholder="Search companies by name or industry..." class="search-input-companies" />
              </div>
            </div>
            <div class="filter-section">
              <select class="filter-dropdown">
                <option value="all-industries">All Industries</option>
                <option value="technology">Technology</option>
                <option value="healthcare">Healthcare</option>
                <option value="finance">Finance</option>
                <option value="education">Education</option>
              </select>
              <select class="filter-dropdown">
                <option value="all-status">All Status</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <!-- Companies Grid -->
          <div class="companies-grid">
            <!-- Software Engineer Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>Software Engineer Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <span class="location-icon">📍</span>
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">92% Match</span>
                <div class="recommended-badge">✓ Recommended for You</div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Python</span>
                <span class="skill-tag">AWS</span>
                <span class="skill-tag">Machine Learning</span>
                <span class="skill-tag">Django</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">4.8/5 95% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 95%"></div>
                </div>
              </div>
            </div>

            <!-- Data Analyst Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>Data Analyst Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <span class="location-icon">📍</span>
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">89% Match</span>
                <div class="recommended-badge">✓ Recommended for You</div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Python</span>
                <span class="skill-tag">SQL</span>
                <span class="skill-tag">Machine Learning</span>
                <span class="skill-tag">Excel</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">4.2/5 70% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 70%"></div>
                </div>
              </div>
            </div>

            <!-- UI/UX Designer Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>UI/UX Designer Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <span class="location-icon">📍</span>
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">85% Match</span>
                <div class="recommended-badge">✓ Recommended for You</div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Figma</span>
                <span class="skill-tag">Prototyping</span>
                <span class="skill-tag">Design System</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">3.0/5 65% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 65%"></div>
                </div>
              </div>
            </div>

            <!-- Business Development Intern Card -->
            <div class="company-card" @click="openCompanySidebar(companiesData[0])">
              <div class="company-card-header">
                <div class="company-avatar">IC</div>
                <div class="company-details">
                  <h3>Business Development Intern</h3>
                  <p class="company-name">Innovate Corp.</p>
                </div>
              </div>

              <div class="company-location">
                <span class="location-icon">📍</span>
                <span>New York, NY</span>
              </div>

              <div class="match-info">
                <span class="match-percentage">92% Match</span>
                <div class="recommended-badge">✓ Recommended for You</div>
              </div>

              <p class="company-description">
                Join Our dynamic team to develop innovative software solutions. 
                You'll work on cutting-edge projects and contribute the projects.
              </p>

              <div class="company-skills">
                <span class="skill-tag">Python</span>
                <span class="skill-tag">AWS</span>
                <span class="skill-tag">Machine Learning</span>
                <span class="skill-tag">Django</span>
              </div>

              <div class="company-rating">
                <div class="stars">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">★</span>
                </div>
                <span class="rating-text">4.4/5 75% Placement</span>
                <div class="rating-bar">
                  <div class="rating-fill" style="width: 75%"></div>
                </div>
              </div>
            </div>
          </div>
        </main>

        <!-- Company Details Sidebar -->
        <div v-if="showCompanySidebar" class="company-sidebar-overlay" @click="closeCompanySidebar">
          <div class="company-sidebar" @click.stop>
            <div class="company-sidebar-header">
              <div class="company-header-info">
                <div class="company-avatar-large">{{ selectedCompany?.avatar }}</div>
                <div class="company-header-details">
                  <h2>{{ selectedCompany?.name }}</h2>
                  <span class="partner-status">Partner since 2019</span>
                </div>
              </div>
              <button class="close-btn" @click="closeCompanySidebar">✕</button>
            </div>

            <!-- Tab Navigation -->
            <div class="company-tabs">
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'general' }"
                @click="setActiveCompanyTab('general')"
              >
                Company Info
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'contacts' }"
                @click="setActiveCompanyTab('contacts')"
              >
                Key Contacts
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'offerings' }"
                @click="setActiveCompanyTab('offerings')"
              >
                Internship Offerings
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'moa' }"
                @click="setActiveCompanyTab('moa')"
              >
                MOA History
              </button>
              <button 
                class="tab-btn" 
                :class="{ active: activeCompanyTab === 'feedback' }"
                @click="setActiveCompanyTab('feedback')"
              >
                Intern Feedback
              </button>
            </div>

            <div class="company-sidebar-content">
              <!-- General Information Tab -->
              <div v-if="activeCompanyTab === 'general'" class="tab-content">
                <h3>General Information</h3>
                <div class="company-info-grid">
                  <div class="info-item">
                    <span class="info-label">Address:</span>
                    <span class="info-value">{{ selectedCompany?.generalInfo?.address }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Contact Email:</span>
                    <span class="info-value">{{ selectedCompany?.generalInfo?.contactEmail }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Website:</span>
                    <span class="info-value">{{ selectedCompany?.generalInfo?.website }}</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Partner Status:</span>
                    <span class="status-active">{{ selectedCompany?.generalInfo?.partnerStatus }}</span>
                  </div>
                </div>

                <h4>Performance Metrics Summary</h4>
                <div class="metrics-grid">
                  <div class="metric-item">
                    <span class="metric-label">Average Rating:</span>
                    <span class="metric-value">4.2/5 ⭐</span>
                  </div>
                  <div class="metric-item">
                    <span class="metric-label">Placement Rate:</span>
                    <span class="metric-value">70%</span>
                  </div>
                </div>
              </div>

              <!-- Key Contacts Tab -->
              <div v-if="activeCompanyTab === 'contacts'" class="tab-content">
                <h3>Key Contacts</h3>
                <div v-for="contact in selectedCompany?.keyContacts" :key="contact.name" class="contact-item">
                  <div class="contact-info">
                    <h4>{{ contact.name }}</h4>
                    <p class="contact-title">{{ contact.title }}</p>
                    <p class="contact-email">📧 {{ contact.email }}</p>
                  </div>
                </div>
              </div>

              <!-- Internship Offerings Tab -->
              <div v-if="activeCompanyTab === 'offerings'" class="tab-content">
                <h3>Internship Offerings</h3>
                <div class="offerings-list">
                  <div v-for="offering in selectedCompany?.internshipOfferings" :key="offering.title" class="offering-item">
                    <div class="offering-header">
                      <h4>{{ offering.title }}</h4>
                      <span class="expand-icon">⌄</span>
                    </div>
                    <p class="offering-requirements">{{ offering.requirements }}</p>
                    <div class="offering-details">
                      <span class="placement-info">Placement: {{ offering.placement }}</span>
                      <span class="status-active">Status: {{ offering.status }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- MOA History Tab -->
              <div v-if="activeCompanyTab === 'moa'" class="tab-content">
                <h3>MOA History</h3>
                <div v-for="moa in selectedCompany?.moaHistory" :key="moa.title" class="moa-item">
                  <h4>{{ moa.title }}</h4>
                  <div class="moa-details">
                    <p>Date: {{ moa.date }}</p>
                    <p>Status: <span class="status-active">{{ moa.status }}</span></p>
                    <p>Expires: {{ moa.expires }}</p>
                  </div>
                </div>
              </div>

              <!-- Intern Feedback Tab -->
              <div v-if="activeCompanyTab === 'feedback'" class="tab-content">
                <h3>Intern Feedback</h3>
                <div v-for="feedback in selectedCompany?.internFeedback" :key="feedback.name" class="feedback-item">
                  <div class="feedback-header">
                    <h4>{{ feedback.name }}</h4>
                    <div class="feedback-rating">
                      <span v-for="i in 5" :key="i" class="star" :class="{ filled: i <= feedback.rating }">★</span>
                    </div>
                  </div>
                  <p class="feedback-text">{{ feedback.feedback }}</p>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="company-sidebar-actions">
              <button class="btn-edit-company">📝 Edit Company Details</button>
              <button class="btn-view-moas">📄 View All MOAs</button>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="currentView === 'submit-report'">
        <header class="top-header">
          <div class="header-left">
            <div class="breadcrumb-header">
              <span class="breadcrumb-item">My Reports</span>
              <span class="breadcrumb-separator">></span>
              <span class="breadcrumb-item active">Submit Report</span>
            </div>
          </div>
          <div class="header-right">
            <span class="step-indicator">Step 1 of 3</span>
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content submit-report-main">
          <div class="submit-report-page">
            <div class="submit-report-header-page">
              <h1>Submit New Report</h1>
            </div>

            <div class="submit-report-form-container">
              <form class="report-form-page">
                <div class="form-row">
                  <div class="form-group">
                    <label for="reportType">Report Type <span class="required">*</span></label>
                    <select id="reportType" v-model="reportFormData.reportType" class="form-select" required>
                      <option value="">Select report type</option>
                      <option value="weekly">Weekly Report</option>
                      <option value="monthly">Monthly Report</option>
                      <option value="final">Final Report</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="studentName">Student Name</label>
                    <input 
                      id="studentName" 
                      v-model="reportFormData.studentName" 
                      type="text" 
                      class="form-input" 
                      readonly
                    />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="periodStart">Internship Period Start <span class="required">*</span></label>
                    <input 
                      id="periodStart" 
                      v-model="reportFormData.internshipPeriodStart" 
                      type="date" 
                      class="form-input" 
                      required
                    />
                  </div>
                  <div class="form-group">
                    <label for="periodEnd">Internship Period End <span class="required">*</span></label>
                    <input 
                      id="periodEnd" 
                      v-model="reportFormData.internshipPeriodEnd" 
                      type="date" 
                      class="form-input" 
                      required
                    />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group full-width">
                    <label for="companyName">Company Name</label>
                    <input 
                      id="companyName" 
                      v-model="reportFormData.companyName" 
                      type="text" 
                      class="form-input" 
                      readonly
                    />
                  </div>
                </div>
              </form>

              <div class="submit-report-actions-page">
                <button class="btn-save-draft" @click="saveAsDraft">
                  💾 Save as Draft
                </button>
                <div class="action-buttons">
                  <button class="btn-cancel" @click="closeSubmitReportModal">Cancel</button>
                  <button class="btn-next" @click="nextStep">Next →</button>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'community'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-community.png" alt="Community" class="header-icon-img" />
            <h1>Community</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content community-main">
          <div class="community-container-school">
            <!-- Left Sidebar -->
            <aside class="left-sidebar-school">
              <!-- User Profile Card -->
              <div class="profile-card-school">
                <div class="profile-header-school">
                  <div class="profile-avatar-school">AC</div>
                  <h3 class="profile-name-school">Admin User</h3>
                  <p class="profile-title-school">School Administrator</p>
                </div>
                <div class="profile-stats-school">
                  <div class="stat-school">
                    <span class="stat-number-school">150</span>
                    <span class="stat-label-school">Connections</span>
                  </div>
                  <div class="stat-school">
                    <span class="stat-number-school">89</span>
                    <span class="stat-label-school">Profile views</span>
                  </div>
                </div>
                <button class="view-profile-btn-school">View Profile</button>
              </div>

              <!-- Suggestions -->
              <div class="suggestions-card-school">
                <h4 class="suggestions-title-school">Suggestions</h4>
                <div class="suggestions-list-school">
                  <div class="suggestion-item-school">
                    <div class="suggestion-avatar-school">JS</div>
                    <div class="suggestion-info-school">
                      <span class="suggestion-name-school">John Smith</span>
                      <span class="suggestion-title-school">HR Manager</span>
                    </div>
                    <button class="connect-btn-school">+</button>
                  </div>
                  <div class="suggestion-item-school">
                    <div class="suggestion-avatar-school">EM</div>
                    <div class="suggestion-info-school">
                      <span class="suggestion-name-school">Emily Martinez</span>
                      <span class="suggestion-title-school">Company Supervisor</span>
                    </div>
                    <button class="connect-btn-school">+</button>
                  </div>
                  <div class="suggestion-item-school">
                    <div class="suggestion-avatar-school">DK</div>
                    <div class="suggestion-info-school">
                      <span class="suggestion-name-school">David Kim</span>
                      <span class="suggestion-title-school">Student Intern</span>
                    </div>
                    <button class="connect-btn-school">+</button>
                  </div>
                </div>
              </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content-school">
              <!-- Post Creation -->
              <div class="post-creation-school">
                <div class="post-input-area-school">
                  <div class="post-avatar-school">AC</div>
                  <input type="text" placeholder="What's on your mind?" class="post-input-school" />
                </div>
                <div class="post-actions-school">
                  <button class="post-action-btn-school">📷 Photo</button>
                  <button class="post-action-btn-school">📹 Video</button>
                  <button class="post-action-btn-school">📄 Document</button>
                  <button class="post-btn-school">Post</button>
                </div>
              </div>

              <!-- Posts Feed -->
              <div class="posts-feed-school">
                <article class="post-school">
                  <div class="post-header-school">
                    <div class="post-author-avatar-school">JS</div>
                    <div class="post-author-info-school">
                      <h4 class="post-author-name-school">John Smith</h4>
                      <p class="post-author-title-school">HR Manager at Tech Solutions Inc.</p>
                      <span class="post-timestamp-school">2 hours ago</span>
                    </div>
                  </div>
                  <div class="post-content-school">
                    <p>Great to see our intern program growing! We're excited to welcome new students this semester. Looking forward to mentoring the next generation of professionals.</p>
                  </div>
                  <div class="post-actions-bar-school">
                    <button class="post-action-school">👍 12</button>
                    <button class="post-action-school">💬 3 Comment</button>
                    <button class="post-action-school">🔄 2 Share</button>
                  </div>
                </article>

                <article class="post-school">
                  <div class="post-header-school">
                    <div class="post-author-avatar-school">EM</div>
                    <div class="post-author-info-school">
                      <h4 class="post-author-name-school">Emily Martinez</h4>
                      <p class="post-author-title-school">Student Intern at Creative Design Studio</p>
                      <span class="post-timestamp-school">5 hours ago</span>
                    </div>
                  </div>
                  <div class="post-content-school">
                    <p>Just completed my first week of internship! Learning so much about UI/UX design and working with amazing mentors. Grateful for this opportunity! 🎨</p>
                  </div>
                  <div class="post-actions-bar-school">
                    <button class="post-action-school">👍 24</button>
                    <button class="post-action-school">💬 8 Comment</button>
                    <button class="post-action-school">🔄 5 Share</button>
                  </div>
                </article>

                <article class="post-school">
                  <div class="post-header-school">
                    <div class="post-author-avatar-school">DK</div>
                    <div class="post-author-info-school">
                      <h4 class="post-author-name-school">David Kim</h4>
                      <p class="post-author-title-school">Software Development Intern</p>
                      <span class="post-timestamp-school">1 day ago</span>
                    </div>
                  </div>
                  <div class="post-content-school">
                    <p>Successfully deployed my first feature to production today! Big thanks to my supervisor and the development team for their guidance and support. 💻</p>
                  </div>
                  <div class="post-actions-bar-school">
                    <button class="post-action-school">👍 18</button>
                    <button class="post-action-school">💬 6 Comment</button>
                    <button class="post-action-school">🔄 3 Share</button>
                  </div>
                </article>
              </div>
            </main>

            <!-- Right Sidebar -->
            <aside class="right-sidebar-school">
              <!-- OJT Path Logo -->
              <div class="ojt-logo-card-school">
                <img src="/icons/logo-main.png" alt="OJT Path" class="ojt-logo-school" />
                <span class="ojt-text-school">OJT Intern Path</span>
              </div>

              <!-- Top Jobs -->
              <div class="jobs-card-school">
                <h4 class="jobs-title-school">Top Internship Opportunities</h4>
                <div class="jobs-list-school">
                  <div class="job-item-school">
                    <h5 class="job-title-school">Software Developer Intern</h5>
                    <p class="job-company-school">Tech Solutions Inc.</p>
                    <p class="job-location-school">Bacoor, Cavite</p>
                    <span class="job-type-school">Full-time</span>
                  </div>
                  <div class="job-item-school">
                    <h5 class="job-title-school">Marketing Assistant</h5>
                    <p class="job-company-school">Digital Marketing Co.</p>
                    <p class="job-location-school">Imus, Cavite</p>
                    <span class="job-type-school">Part-time</span>
                  </div>
                  <div class="job-item-school">
                    <h5 class="job-title-school">Data Analyst Intern</h5>
                    <p class="job-company-school">DataCorp Analytics</p>
                    <p class="job-location-school">BGC, Taguig</p>
                    <span class="job-type-school">Full-time</span>
                  </div>
                </div>
              </div>

              <!-- Most Active Users -->
              <div class="most-viewed-card-school">
                <h4 class="most-viewed-title-school">Most Active Users</h4>
                <div class="most-viewed-list-school">
                  <div class="viewed-item-school">
                    <div class="viewed-avatar-school">MS</div>
                    <div class="viewed-info-school">
                      <span class="viewed-name-school">Maria Santos</span>
                      <span class="viewed-title-school">Project Manager</span>
                    </div>
                  </div>
                  <div class="viewed-item-school">
                    <div class="viewed-avatar-school">RT</div>
                    <div class="viewed-info-school">
                      <span class="viewed-name-school">Robert Taylor</span>
                      <span class="viewed-title-school">Senior Developer</span>
                    </div>
                  </div>
                  <div class="viewed-item-school">
                    <div class="viewed-avatar-school">SM</div>
                    <div class="viewed-info-school">
                      <span class="viewed-name-school">Sophia Martinez</span>
                      <span class="viewed-title-school">Design Lead</span>
                    </div>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'documents'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-docs.png" alt="Documents" class="header-icon-img" />
            <h1>Documents</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <p>Document management features will be implemented here.</p>
        </main>
      </div>

      <div v-else-if="currentView === 'reports'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-reports.png" alt="Reports" class="header-icon-img" />
            <h1>Reports</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <!-- Reports Header -->
          <div class="reports-header">
            <div class="reports-title-section">
              <h2>My Reports</h2>
              <span class="reports-count">9 reports</span>
            </div>
            <button class="btn-submit-report" @click="openSubmitReportModal">+ Submit New Report</button>
          </div>

          <!-- Reports Stats Cards -->
          <div class="reports-stats">
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">PENDING REVIEW</span>
                <div class="stat-icon pending-icon">⏳</div>
              </div>
              <div class="stat-number-report">2</div>
            </div>
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">REVISION REQUESTED</span>
                <div class="stat-icon revision-icon">⚠️</div>
              </div>
              <div class="stat-number-report">1</div>
            </div>
            <div class="stat-card-report">
              <div class="stat-header-report">
                <span class="stat-label-report">APPROVED REPORTS</span>
                <div class="stat-icon approved-icon">✅</div>
              </div>
              <div class="stat-number-report">5</div>
            </div>
          </div>

          <!-- Reports Table -->
          <div class="reports-table-container">
            <table class="reports-table">
              <thead>
                <tr>
                  <th>REPORT TYPE</th>
                  <th>PERIOD</th>
                  <th>SUBMITTED</th>
                  <th>COMPANY STATUS</th>
                  <th>SCHOOL STATUS</th>
                  <th>ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="report-type">Weekly Report 10</td>
                  <td>Jul 8-14, 2024</td>
                  <td>Jul 15, 2024</td>
                  <td><span class="status-badge revision-requested">Revision Requested</span></td>
                  <td>—</td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Monthly Report 2</td>
                  <td>Jun 1-30, 2024</td>
                  <td>Jul 1, 2024</td>
                  <td><span class="status-badge under-review">Under Review</span></td>
                  <td>—</td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 9</td>
                  <td>Jul 1-7, 2024</td>
                  <td>Jul 8, 2024</td>
                  <td><span class="status-badge under-review">Under Review</span></td>
                  <td>—</td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 8</td>
                  <td>Jun 24-30, 2024</td>
                  <td>Jul 1, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 7</td>
                  <td>Jun 17-23, 2024</td>
                  <td>Jun 24, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 6</td>
                  <td>Jun 10-16, 2024</td>
                  <td>Jun 17, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Monthly Report 1</td>
                  <td>May 1-31, 2024</td>
                  <td>Jun 1, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 5</td>
                  <td>Jun 3-9, 2024</td>
                  <td>Jun 10, 2024</td>
                  <td><span class="status-badge approved">Approved</span></td>
                  <td><span class="status-badge under-review">Under Review</span></td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
                <tr>
                  <td class="report-type">Weekly Report 4</td>
                  <td>May 27 - Jun 2, 2024</td>
                  <td>Jun 3, 2024</td>
                  <td><span class="status-badge submitted">Submitted</span></td>
                  <td>—</td>
                  <td><button class="action-btn">⋯</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </main>
      </div>

      <div v-else-if="currentView === 'student-interns'">
        <header class="top-header">
          <div class="header-left">
            <img src="/icons/icon-student.png" alt="Student Interns" class="header-icon-img" />
            <h1>Student Interns</h1>
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="school-name">{{ schoolName }}</span>
            <div class="avatar">AC</div>
          </div>
        </header>
        <main class="main-content">
          <p>Student intern management features will be implemented here.</p>
        </main>
      </div>

      <div v-else-if="currentView === 'settings'" class="settings-page">
        <div class="settings-container">
          <!-- Breadcrumb -->
          <div class="breadcrumb">
            Settings / <span class="breadcrumb-active">ACCOUNT</span>
          </div>

          <!-- Settings Header -->
          <div class="settings-header">
            <div>
              <h1 class="settings-title">Settings</h1>
              <p class="settings-subtitle">Manage account information and preferences</p>
            </div>
            <div class="language-selector">
              <span class="globe-icon">🌐</span>
              <span>EN (Language name)</span>
            </div>
          </div>

          <!-- Settings Tabs -->
          <div class="settings-tabs">
            <button 
              class="settings-tab" 
              :class="{ active: activeSettingsTab === 'account' }"
              @click="setActiveSettingsTab('account')"
            >
              Account
            </button>
            <button 
              class="settings-tab"
              :class="{ active: activeSettingsTab === 'school-info' }"
              @click="setActiveSettingsTab('school-info')"
            >
              School Info
            </button>
            <button 
              class="settings-tab"
              :class="{ active: activeSettingsTab === 'ojt-config' }"
              @click="setActiveSettingsTab('ojt-config')"
            >
              OJT Config
            </button>
            <button 
              class="settings-tab"
              :class="{ active: activeSettingsTab === 'data' }"
              @click="setActiveSettingsTab('data')"
            >
              Data
            </button>
          </div>

          <!-- Account Information Section -->
          <div v-if="activeSettingsTab === 'account'">
          <div class="settings-section">
            <div class="section-header">
              <span class="section-icon">👤</span>
              <h2 class="section-title">Account Information</h2>
            </div>

            <div class="account-info-grid">
              <div class="account-avatar-section">
                <div class="avatar-large">CD</div>
                <button class="change-photo-btn">📷 Change Photo</button>
                <p class="photo-hint">JPG, PNG or GIF (Max 800k)</p>
              </div>

              <div class="account-fields">
                <div class="form-group">
                  <label>Username</label>
                  <div class="input-with-action">
                    <input type="text" value="Cebu@school.edu.ph" readonly class="form-input" />
                    <button class="link-btn">Change Username</button>
                  </div>
                </div>

                <div class="form-group">
                  <label>Email Address</label>
                  <div class="input-with-action">
                    <input type="email" value="Cebu@school.edu.ph" readonly class="form-input" />
                    <div class="verified-badge">
                      <span class="check-icon">✓</span> Verified
                    </div>
                    <button class="link-btn">Send an Email</button>
                  </div>
                </div>

                <div class="form-group">
                  <label>Password</label>
                  <div class="input-with-action">
                    <input type="password" value="••••••••••••" readonly class="form-input" />
                    <span class="password-hint">Last changed on 16, 2021</span>
                    <button class="link-btn">Change Password</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Contact Details Section -->
          <div class="settings-section">
            <div class="section-header">
              <span class="section-icon">📞</span>
              <h2 class="section-title">Contact Details</h2>
            </div>

            <div class="contact-grid">
              <div class="contact-field">
                <label>Phone Number</label>
                <p>+63 2 8-123-4567</p>
              </div>
              <div class="contact-field">
                <label>Mobile Number</label>
                <p>(63) 917 123 4567</p>
              </div>
              <div class="contact-field">
                <label>Office Extension</label>
                <p>2045</p>
              </div>
              <div class="contact-field">
                <label>Alternate Email (Optional)</label>
                <p class="text-muted">-</p>
              </div>
            </div>
          </div>

          <!-- Login & Security Section -->
          <div class="settings-section">
            <div class="section-header">
              <span class="section-icon">🔒</span>
              <h2 class="section-title">Login & Security</h2>
            </div>

            <div class="security-settings">
              <div class="security-item">
                <div>
                  <h3 class="security-item-title">Two-Factor Authentication</h3>
                  <p class="security-item-desc">Add an extra layer of security to your account</p>
                </div>
                <div class="toggle-group">
                  <span class="toggle-label">Enabled</span>
                  <label class="toggle-switch">
                    <input type="checkbox" checked />
                    <span class="toggle-slider"></span>
                  </label>
                  <button class="link-btn">Configure</button>
                </div>
              </div>

              <div class="security-item">
                <div>
                  <h3 class="security-item-title">Login Notifications</h3>
                  <p class="security-item-desc">Get notified when there's a sign-in from a new device</p>
                </div>
                <label class="toggle-switch">
                  <input type="checkbox" checked />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="security-item">
                <div>
                  <h3 class="security-item-title">Session Timeout</h3>
                  <p class="security-item-desc">Automatic logout after inactivity</p>
                </div>
              </div>

              <div class="security-item">
                <div>
                  <h3 class="security-item-title">Active Sessions <span class="badge-count">3</span></h3>
                  <p class="security-item-desc">Manage devices with a recent or active logged in</p>
                </div>
              </div>

              <div class="active-sessions">
                <div class="session-item">
                  <span class="device-icon">💻</span>
                  <div class="session-info">
                    <h4>Windows Desktop - Chrome <span class="current-badge">Current</span></h4>
                    <p>Manila, Philippines • Last active: now</p>
                  </div>
                </div>

                <div class="session-item">
                  <span class="device-icon">📱</span>
                  <div class="session-info">
                    <h4>MacBook Pro - Safari</h4>
                    <p>Quezon City, Philippines • Last active: 2 hours ago</p>
                  </div>
                </div>

                <div class="session-item">
                  <span class="device-icon">📱</span>
                  <div class="session-info">
                    <h4>iPhone 15 - Mobile Safari</h4>
                    <p>Manila, Philippines • Last active: yesterday</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Notification Preferences Section -->
          <div class="settings-section">
            <div class="section-header">
              <span class="section-icon">🔔</span>
              <h2 class="section-title">Notification Preferences</h2>
            </div>

            <div class="notification-table">
              <div class="notification-header">
                <div class="notification-col">SETTING</div>
                <div class="notification-col">EMAIL</div>
                <div class="notification-col">IN-APP</div>
              </div>

              <div class="notification-row">
                <div class="notification-col">New report submissions</div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
              </div>

              <div class="notification-row">
                <div class="notification-col">Report approval needed</div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
              </div>

              <div class="notification-row">
                <div class="notification-col">Student account updates</div>
                <div class="notification-col">
                  <input type="checkbox" />
                </div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
              </div>

              <div class="notification-row">
                <div class="notification-col">Company registrations</div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
                <div class="notification-col">
                  <input type="checkbox" />
                </div>
              </div>

              <div class="notification-row">
                <div class="notification-col">System announcements</div>
                <div class="notification-col">
                  <input type="checkbox" />
                </div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
              </div>

              <div class="notification-row">
                <div class="notification-col">Weekly summary report</div>
                <div class="notification-col">
                  <input type="checkbox" checked />
                </div>
                <div class="notification-col">
                  <input type="checkbox" />
                </div>
              </div>
            </div>
          </div>
          </div>

          <!-- School Info Tab Content -->
          <div v-else-if="activeSettingsTab === 'school-info'">
            <!-- Institution Details -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Institution Details</h2>
              </div>

              <div class="school-info-grid">
                <div class="form-group full-width">
                  <label>School Name</label>
                  <input type="text" v-model="schoolInfoData.institutionDetails.schoolName" class="form-input" placeholder="Enter school name" />
                </div>

                <div class="form-group logo-upload">
                  <label>School Logo</label>
                  <div class="upload-area">
                    <button class="upload-btn">📷 Upload New Logo</button>
                    <p class="upload-hint">Recommended size: 200x200px (Max 2MB)</p>
                  </div>
                </div>

                <div class="form-group full-width">
                  <label>Official School Address</label>
                  <textarea v-model="schoolInfoData.institutionDetails.officialAddress" class="form-textarea" rows="2" placeholder="Enter complete address"></textarea>
                </div>

                <div class="form-group">
                  <label>Region</label>
                  <select v-model="schoolInfoData.institutionDetails.region" class="form-select">
                    <option value="NCR">NCR</option>
                    <option value="Region I">Region I</option>
                    <option value="Region II">Region II</option>
                    <option value="Region III">Region III</option>
                    <option value="Region IV-A">Region IV-A</option>
                  </select>
                </div>

                <div class="form-group">
                  <label>Province</label>
                  <input type="text" v-model="schoolInfoData.institutionDetails.province" class="form-input" placeholder="Province" />
                </div>

                <div class="form-group">
                  <label>City/Municipality</label>
                  <input type="text" v-model="schoolInfoData.institutionDetails.cityMunicipality" class="form-input" placeholder="City/Municipality" />
                </div>

                <div class="form-group">
                  <label>Barangay</label>
                  <input type="text" v-model="schoolInfoData.institutionDetails.barangay" class="form-input" placeholder="Barangay" />
                </div>

                <div class="form-group">
                  <label>Postal Code</label>
                  <input type="text" v-model="schoolInfoData.institutionDetails.zipCode" class="form-input" placeholder="Zip Code" />
                </div>

                <div class="form-group">
                  <label>Website URL</label>
                  <input type="url" v-model="schoolInfoData.schoolContactInfo.websiteUrl" class="form-input" placeholder="https://www.school.edu.ph" />
                </div>
              </div>

              <button class="btn-save-changes">💾 Save Changes</button>
            </div>

            <!-- OJT Coordinator Information -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">OJT Coordinator Information</h2>
                <p class="section-subtitle">Primary point of contact for OJT matters</p>
              </div>

              <div class="school-info-grid">
                <div class="form-group">
                  <label>Coordinator Name</label>
                  <input type="text" v-model="schoolInfoData.ojtCoordinatorInfo.coordinatorName" class="form-input" placeholder="Full Name" />
                </div>

                <div class="form-group">
                  <label>Position/Title</label>
                  <input type="text" v-model="schoolInfoData.ojtCoordinatorInfo.position" class="form-input" placeholder="OJT Coordinator" />
                </div>

                <div class="form-group">
                  <label>Phone Number</label>
                  <input type="tel" v-model="schoolInfoData.ojtCoordinatorInfo.phoneNumber" class="form-input" placeholder="+63 2 8-123-4567" />
                </div>

                <div class="form-group">
                  <label>Mobile Number</label>
                  <input type="tel" v-model="schoolInfoData.ojtCoordinatorInfo.mobileNumber" class="form-input" placeholder="+63 917 123 4567" />
                </div>

                <div class="form-group">
                  <label>Email Address</label>
                  <input type="email" v-model="schoolInfoData.ojtCoordinatorInfo.emailAddress" class="form-input" placeholder="coordinator@school.edu.ph" />
                </div>

                <div class="form-group">
                  <label>Office Location</label>
                  <input type="text" v-model="schoolInfoData.ojtCoordinatorInfo.officeLocation" class="form-input" placeholder="Building, Room Number" />
                </div>
              </div>
            </div>

            <!-- School Contact Information -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">School Contact Information</h2>
                <p class="section-subtitle">General contact details for the institution</p>
              </div>

              <div class="school-info-grid">
                <div class="form-group">
                  <label>Main Office Number</label>
                  <input type="tel" v-model="schoolInfoData.schoolContactInfo.mainOfficeNumber" class="form-input" placeholder="+63 2 8-123-4567" />
                </div>

                <div class="form-group">
                  <label>Student Affairs Number</label>
                  <input type="tel" v-model="schoolInfoData.schoolContactInfo.studentAffairsNumber" class="form-input" placeholder="+63 2 8-123-4568" />
                </div>

                <div class="form-group">
                  <label>Registrar Number</label>
                  <input type="tel" v-model="schoolInfoData.schoolContactInfo.registrarNumber" class="form-input" placeholder="+63 2 8-123-4569" />
                </div>

                <div class="form-group">
                  <label>Fax Number</label>
                  <input type="tel" v-model="schoolInfoData.schoolContactInfo.faxNumber" class="form-input" placeholder="+63 2 8-123-4570" />
                </div>

                <div class="form-group">
                  <label>Website URL</label>
                  <input type="url" v-model="schoolInfoData.schoolContactInfo.websiteUrl" class="form-input" placeholder="https://www.school.edu.ph" />
                </div>

                <div class="form-group">
                  <label>Official Email</label>
                  <input type="email" v-model="schoolInfoData.schoolContactInfo.officialEmail" class="form-input" placeholder="info@school.edu.ph" />
                </div>
              </div>
            </div>

            <!-- Academic Calendar -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Academic Calendar</h2>
              </div>

              <div class="academic-calendar-section">
                <div class="calendar-header">
                  <div class="form-group inline">
                    <label>Current Term/Semester</label>
                    <select v-model="schoolInfoData.academicCalendar.currentTermSemester" class="form-select">
                      <option value="First Semester">First Semester</option>
                      <option value="Second Semester">Second Semester</option>
                      <option value="Summer Term">Summer Term</option>
                    </select>
                  </div>
                  <button class="btn-add-term">+ Add New Term</button>
                </div>

                <div class="terms-table">
                  <div class="table-header">
                    <div class="table-col">TERM NAME</div>
                    <div class="table-col">START DATE</div>
                    <div class="table-col">END DATE</div>
                    <div class="table-col">STATUS</div>
                    <div class="table-col">ACTIONS</div>
                  </div>

                  <div v-for="term in academicTerms" :key="term.id" class="table-row">
                    <div class="table-col">{{ term.termName }}</div>
                    <div class="table-col">{{ term.startDate }}</div>
                    <div class="table-col">{{ term.endDate }}</div>
                    <div class="table-col">
                      <span class="status-badge status-active">{{ term.status }}</span>
                    </div>
                    <div class="table-col table-actions">
                      <button class="icon-btn">✏️</button>
                      <button class="icon-btn">🗑️</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Social Media & External Links -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Social Media & External Links</h2>
              </div>

              <div class="school-info-grid">
                <div class="form-group">
                  <label>Facebook Page</label>
                  <input type="url" v-model="schoolInfoData.socialMediaLinks.facebookPage" class="form-input" placeholder="https://facebook.com/yourschool" />
                </div>

                <div class="form-group">
                  <label>Twitter/X Account</label>
                  <input type="url" v-model="schoolInfoData.socialMediaLinks.twitterAccount" class="form-input" placeholder="https://twitter.com/yourschool" />
                </div>

                <div class="form-group">
                  <label>LinkedIn Page</label>
                  <input type="url" v-model="schoolInfoData.socialMediaLinks.linkedInPage" class="form-input" placeholder="https://linkedin.com/school/yourschool" />
                </div>

                <div class="form-group">
                  <label>Instagram Account</label>
                  <input type="url" v-model="schoolInfoData.socialMediaLinks.instagramAccount" class="form-input" placeholder="https://instagram.com/yourschool" />
                </div>
              </div>
            </div>
          </div>

          <!-- OJT Config Tab Content -->
          <div v-else-if="activeSettingsTab === 'ojt-config'">
            <!-- OJT Program Requirements -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">OJT Program Requirements</h2>
                <p class="section-subtitle">Define the basic requirements for students to participate in OJT</p>
              </div>

              <div class="config-grid">
                <div class="config-item">
                  <label>Minimum OJT Hours Required</label>
                  <div class="input-with-unit">
                    <input type="number" v-model="ojtConfigData.programRequirements.minimumOjtHours" class="form-input" />
                    <span class="unit-label">hours</span>
                  </div>
                </div>

                <div class="config-item">
                  <label>Minimum Attendance Rate</label>
                  <div class="input-with-unit">
                    <input type="number" v-model="ojtConfigData.programRequirements.minimumAttendanceRate" class="form-input" />
                    <span class="unit-label">%</span>
                  </div>
                </div>

                <div class="config-item">
                  <label>Grade Requirement (Maximum GPA)</label>
                  <input type="number" step="0.1" v-model="ojtConfigData.programRequirements.gradeRequirement" class="form-input" placeholder="2.5" />
                </div>

                <div class="config-item">
                  <label>Year Level Requirement</label>
                  <select v-model="ojtConfigData.programRequirements.yearLevelRequirement" class="form-select">
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                  </select>
                </div>

                <div class="config-item full-width">
                  <label>Prerequisite Subjects (Optional)</label>
                  <textarea v-model="ojtConfigData.programRequirements.prerequisiteSubjects" class="form-textarea" rows="2" placeholder="List prerequisite subjects, separated by commas"></textarea>
                </div>

                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">Allow Flexible Hours</label>
                      <p class="toggle-description">Students can complete OJT hours flexibly within the term</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.programRequirements.allowFlexibleHours" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Application Settings -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Application Settings</h2>
                <p class="section-subtitle">Configure how students apply for internships</p>
              </div>

              <div class="config-grid">
                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">Enable Applications</label>
                      <p class="toggle-description">Allow students to submit internship applications</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.applicationSettings.enableApplications" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">Require School Approval</label>
                      <p class="toggle-description">Applications must be approved by school before being sent to companies</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.applicationSettings.requireApproval" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">Auto-Match Students</label>
                      <p class="toggle-description">Automatically suggest internships based on student profiles</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.applicationSettings.autoMatchStudents" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">Allow Multiple Applications</label>
                      <p class="toggle-description">Students can apply to multiple companies simultaneously</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.applicationSettings.allowMultipleApplications" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="config-item" v-if="ojtConfigData.applicationSettings.allowMultipleApplications">
                  <label>Maximum Simultaneous Applications</label>
                  <input type="number" v-model="ojtConfigData.applicationSettings.maxSimultaneousApplications" class="form-input" />
                </div>

                <div class="config-item">
                  <label>Application Deadline (Optional)</label>
                  <input type="date" v-model="ojtConfigData.applicationSettings.applicationDeadline" class="form-input" />
                </div>

                <div class="config-item full-width">
                  <label class="section-label">Notification Settings</label>
                  <div class="checkbox-group">
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.applicationSettings.notifyNewApplications" />
                      <span>Notify coordinators of new applications</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.applicationSettings.notifyStatusChanges" />
                      <span>Notify students of application status changes</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Monitoring and Evaluation -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Monitoring and Evaluation</h2>
                <p class="section-subtitle">Set up monitoring and evaluation requirements</p>
              </div>

              <div class="config-grid">
                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">Weekly Journal Required</label>
                      <p class="toggle-description">Students must submit weekly journal entries</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.monitoringEvaluation.weeklyJournalRequired" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="config-item" v-if="ojtConfigData.monitoringEvaluation.weeklyJournalRequired">
                  <label>Journal Submission Day</label>
                  <select v-model="ojtConfigData.monitoringEvaluation.journalSubmissionDay" class="form-select">
                    <option value="Monday">Monday</option>
                    <option value="Tuesday">Tuesday</option>
                    <option value="Wednesday">Wednesday</option>
                    <option value="Thursday">Thursday</option>
                    <option value="Friday">Friday</option>
                  </select>
                </div>

                <div class="config-item">
                  <label>Midterm Evaluation Week</label>
                  <div class="input-with-unit">
                    <input type="number" v-model="ojtConfigData.monitoringEvaluation.midtermEvaluationWeek" class="form-input" />
                    <span class="unit-label">week</span>
                  </div>
                </div>

                <div class="config-item">
                  <label>Final Evaluation Week</label>
                  <div class="input-with-unit">
                    <input type="number" v-model="ojtConfigData.monitoringEvaluation.finalEvaluationWeek" class="form-input" />
                    <span class="unit-label">week</span>
                  </div>
                </div>

                <div class="config-item full-width">
                  <label class="section-label">Evaluation Requirements</label>
                  <div class="checkbox-group">
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.monitoringEvaluation.supervisorEvaluationRequired" />
                      <span>Supervisor evaluation required</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.monitoringEvaluation.studentSelfEvaluation" />
                      <span>Student self-evaluation</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.monitoringEvaluation.allowLateSubmissions" />
                      <span>Allow late submissions (with penalty)</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Company Requirements -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Company Requirements</h2>
                <p class="section-subtitle">Requirements for companies to partner with your institution</p>
              </div>

              <div class="config-grid">
                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">MOA Required</label>
                      <p class="toggle-description">Companies must have a valid Memorandum of Agreement</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.companyRequirements.moaRequired" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="config-item" v-if="ojtConfigData.companyRequirements.moaRequired">
                  <label>MOA Validity Period</label>
                  <div class="input-with-unit">
                    <input type="number" v-model="ojtConfigData.companyRequirements.moaValidityPeriod" class="form-input" />
                    <span class="unit-label">years</span>
                  </div>
                </div>

                <div class="config-item">
                  <label>Minimum Company Rating</label>
                  <div class="input-with-unit">
                    <input type="number" step="0.1" v-model="ojtConfigData.companyRequirements.minimumCompanyRating" class="form-input" />
                    <span class="unit-label">/ 5.0</span>
                  </div>
                </div>

                <div class="config-item full-width">
                  <label class="section-label">Additional Requirements</label>
                  <div class="checkbox-group">
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.companyRequirements.companyVerification" />
                      <span>Company verification required</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.companyRequirements.requireCompanyProfile" />
                      <span>Complete company profile required</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.companyRequirements.backgroundCheckRequired" />
                      <span>Background check required</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Student Requirements -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Student Requirements</h2>
                <p class="section-subtitle">Documents and requirements students must submit</p>
              </div>

              <div class="config-grid">
                <div class="config-item">
                  <label>Completed Units</label>
                  <div class="input-with-unit">
                    <input type="number" v-model="ojtConfigData.studentRequirements.completedUnits" class="form-input" />
                    <span class="unit-label">units</span>
                  </div>
                </div>

                <div class="config-item">
                  <label>Minimum GPA</label>
                  <input type="number" step="0.1" v-model="ojtConfigData.studentRequirements.gpa" class="form-input" />
                </div>

                <div class="config-item full-width">
                  <label class="section-label">Required Documents</label>
                  <div class="documents-list">
                    <div v-for="doc in requiredDocumentsList" :key="doc.id" class="document-item">
                      <label class="checkbox-label">
                        <input type="checkbox" v-model="doc.required" />
                        <span>{{ doc.name }}</span>
                      </label>
                    </div>
                  </div>
                </div>

                <div class="config-item full-width">
                  <label class="section-label">Additional Requirements</label>
                  <div class="checkbox-group">
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.studentRequirements.noFailingGrades" />
                      <span>No failing grades in major subjects</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.studentRequirements.clearanceRequired" />
                      <span>School clearance required</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.studentRequirements.parentConsent" />
                      <span>Parent/Guardian consent (for minors)</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.studentRequirements.insuranceRequired" />
                      <span>Insurance coverage required</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Evaluation Settings -->
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Evaluation Settings</h2>
                <p class="section-subtitle">Configure evaluation and feedback mechanisms</p>
              </div>

              <div class="config-grid">
                <div class="config-item full-width">
                  <div class="toggle-setting">
                    <div>
                      <label class="toggle-label-main">Enable Evaluations</label>
                      <p class="toggle-description">Allow evaluations and feedback collection</p>
                    </div>
                    <label class="toggle-switch">
                      <input type="checkbox" v-model="ojtConfigData.evaluationSettings.enableEvaluations" />
                      <span class="toggle-slider"></span>
                    </label>
                  </div>
                </div>

                <div class="config-item">
                  <label>Evaluation Frequency</label>
                  <select v-model="ojtConfigData.evaluationSettings.evaluationFrequency" class="form-select">
                    <option value="Weekly">Weekly</option>
                    <option value="Bi-weekly">Bi-weekly</option>
                    <option value="Monthly">Monthly</option>
                    <option value="End of Term">End of Term</option>
                  </select>
                </div>

                <div class="config-item full-width">
                  <label class="section-label">Evaluation Types</label>
                  <div class="checkbox-group">
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.evaluationSettings.studentEvaluatesCompany" />
                      <span>Student evaluates company/supervisor</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.evaluationSettings.companyEvaluatesStudent" />
                      <span>Company evaluates student performance</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.evaluationSettings.schoolEvaluatesPlacement" />
                      <span>School evaluates placement quality</span>
                    </label>
                    <label class="checkbox-label">
                      <input type="checkbox" v-model="ojtConfigData.evaluationSettings.anonymousEvaluations" />
                      <span>Allow anonymous evaluations</span>
                    </label>
                  </div>
                </div>
              </div>

              <button class="btn-save-changes">💾 Save All Configuration</button>
            </div>
          </div>

          <!-- Data Tab Content -->
          <div v-else-if="activeSettingsTab === 'data'">
            <div class="settings-section">
              <div class="section-header">
                <h2 class="section-title">Data Settings</h2>
                <p class="section-subtitle">Manage your school data and preferences</p>
              </div>

              <div class="simple-message">
                <p>Data management features coming soon...</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Floating Chat Widget -->
    <FloatingChatWidget />
  </div>
</template>

<style scoped src="../../styles/School.css">
</style>


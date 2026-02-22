<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { 
  BellIcon,
  MapPinIcon,
  CheckIcon,
  CheckCircleIcon,
  MagnifyingGlassIcon,
  DocumentTextIcon,
  BookmarkIcon,
  BriefcaseIcon,
  LinkIcon,
  CloudArrowUpIcon,
  PaintBrushIcon,
  UserGroupIcon,
  BookOpenIcon,
  QuestionMarkCircleIcon,
  ChevronRightIcon
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

// Emit event to parent
const emit = defineEmits<{
  openMessages: []
  navigateToProfile: []
}>()

function handleAvatarClick() {
  emit('navigateToProfile')
}

// Search and filter state
const searchQuery = ref('')
const selectedFilters = ref(['Software Engineer', 'UI/UX Designer', 'Web Developer'])
const locationFilter = ref('')
const skillsFilter = ref(['Python', 'Data Analysis'])
const availabilityStart = ref('2024-06-01')
const availabilityEnd = ref('2024-12-31')
const companyTypes = ref(['Startup', 'Enterprise', 'Non-profit', 'Government'])
const selectedCompanyTypes = ref(['Startup'])
const duration = ref('4-6 Months')

// Modal state
const showDetailsModal = ref(false)
const selectedInternship = ref<any>(null)
const showApplicationModal = ref(false)
const showReviewModal = ref(false)
const applicationProgress = ref(28)

// Application form data
const applicationData = ref({
  documents: [
    { name: 'Resume/CV', status: 'completed', required: true, file: null },
    { name: 'Cover Letter', status: 'completed', required: true, file: null },
    { name: 'Academic Transcript', status: 'missing', required: true, file: null },
    { name: 'Reference Documents', status: 'missing', required: false, file: null }
  ]
})

// Available internships data
const internships = ref([
  {
    id: 1,
    title: 'Software Development Intern',
    company: 'Tech Solutions Inc.',
    location: 'New York, NY (Hybrid)',
    match: 92,
    recommended: true,
    description: 'Join our dynamic team as a Software Development Intern and contribute to cutting-edge projects. You will gain hands-on experience in full-stack development, working with experienced engineers to design, implement, and test software solutions. This internship offers a unique opportunity to learn industry best practices and make a tangible impact.',
    skills: ['Python', 'React', 'SQL', 'AWS', 'Machine Learning'],
    type: 'Full-time',
    duration: '6 months',
    posted: '2 days ago',
    responsibilities: [
      'Assist in developing and maintaining web applications using Python and React.',
      'Participate in code reviews and debugging sessions.',
      'Contribute to the design and architecture of new features.',
      'Document technical specifications and application functionality.'
    ],
    requirements: [
      'Currently pursuing a Bachelor\'s or Master\'s degree in Computer Science or a related field.',
      'Proficiency in at least one programming language (Python, Java, C++).',
      'Familiarity with web development frameworks (e.g., React, Django) and database technologies (SQL).',
      'Strong problem-solving skills and ability to work in a team environment.',
      'Excellent communication skills, both written and verbal.'
    ],
    schedule: {
      startDate: 'June 23, 2024',
      endDate: 'August 30, 2024',
      dailyHours: 'Hybrid (3 days in-office, 2 days remote)',
      totalHours: '400 hours required'
    },
    aboutCompany: 'Tech Solutions Inc. is a leading innovator in cloud-based software services, empowering businesses globally with scalable and secure solutions. We foster a culture of creativity, collaboration, and continuous learning, driving technological advancements that shape the future.',
    mission: 'Our mission is to build intelligent software that simplifies complex processes and enhances user experience. Join us to be part of team that\'s building the next generation of enterprise applications.',
    compatibility: {
      score: 92,
      matches: ['Python (Matched)', 'React (Matched)', 'SQL (Matched)'],
      missing: ['AWS (Missing)', 'Machine Learning (Missing)']
    }
  },
  {
    id: 2,
    title: 'Data Analyst Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 91,
    recommended: true,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'SQL', 'Machine Learning', 'Excel'],
    type: 'Full-time',
    duration: '4 months',
    posted: '3 days ago',
    responsibilities: [
      'Analyze large datasets to identify trends and patterns.',
      'Create data visualizations and reports.',
      'Support business decision-making with data insights.',
      'Collaborate with cross-functional teams.'
    ],
    requirements: [
      'Currently pursuing a degree in Data Science, Statistics, or related field.',
      'Proficiency in Python and SQL.',
      'Experience with data visualization tools.',
      'Strong analytical and problem-solving skills.'
    ],
    schedule: {
      startDate: 'July 1, 2024',
      endDate: 'October 31, 2024',
      dailyHours: 'Full-time (40 hours/week)',
      totalHours: '320 hours required'
    },
    aboutCompany: 'Innovate Corp. is a technology company focused on data-driven solutions.',
    mission: 'We help businesses make better decisions through advanced analytics.',
    compatibility: {
      score: 91,
      matches: ['Python (Matched)', 'SQL (Matched)'],
      missing: ['Machine Learning (Missing)', 'Excel (Missing)']
    }
  },
  {
    id: 3,
    title: 'UI/UX Designer Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 82,
    recommended: true,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Figma', 'Prototyping', 'Design Systems'],
    type: 'Full-time',
    duration: '5 months',
    posted: '1 week ago',
    responsibilities: [
      'Design user interfaces for web and mobile applications.',
      'Create wireframes and prototypes.',
      'Conduct user research and usability testing.',
      'Collaborate with development teams.'
    ],
    requirements: [
      'Currently pursuing a degree in Design, HCI, or related field.',
      'Proficiency in design tools like Figma or Sketch.',
      'Understanding of user-centered design principles.',
      'Strong portfolio showcasing design work.'
    ],
    schedule: {
      startDate: 'June 15, 2024',
      endDate: 'November 15, 2024',
      dailyHours: 'Full-time (40 hours/week)',
      totalHours: '400 hours required'
    },
    aboutCompany: 'Innovate Corp. is a technology company focused on user experience.',
    mission: 'We create intuitive and beautiful digital experiences.',
    compatibility: {
      score: 82,
      matches: ['Figma (Matched)', 'Prototyping (Matched)'],
      missing: ['Design Systems (Missing)']
    }
  },
  {
    id: 4,
    title: 'Business Development Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 87,
    recommended: true,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'AWS', 'Machine Learning', 'Django'],
    type: 'Full-time',
    duration: '6 months',
    posted: '5 days ago',
    responsibilities: [
      'Support business development initiatives.',
      'Conduct market research and analysis.',
      'Assist in client presentations and proposals.',
      'Help identify new business opportunities.'
    ],
    requirements: [
      'Currently pursuing a degree in Business, Marketing, or related field.',
      'Strong communication and presentation skills.',
      'Analytical mindset with attention to detail.',
      'Interest in technology and business strategy.'
    ],
    schedule: {
      startDate: 'July 15, 2024',
      endDate: 'January 15, 2025',
      dailyHours: 'Full-time (40 hours/week)',
      totalHours: '480 hours required'
    },
    aboutCompany: 'Innovate Corp. is a growing technology company.',
    mission: 'We help businesses grow through innovative solutions.',
    compatibility: {
      score: 87,
      matches: ['Business Strategy (Matched)', 'Communication (Matched)'],
      missing: ['Market Research (Missing)']
    }
  }
])

function removeFilter(filter: string) {
  const index = selectedFilters.value.indexOf(filter)
  if (index > -1) {
    selectedFilters.value.splice(index, 1)
  }
}

function removeSkillFilter(skill: string) {
  const index = skillsFilter.value.indexOf(skill)
  if (index > -1) {
    skillsFilter.value.splice(index, 1)
  }
}

function applyFilters() {
  console.log('Applying filters...')
}

function clearAllFilters() {
  selectedFilters.value = []
  skillsFilter.value = []
  locationFilter.value = ''
  selectedCompanyTypes.value = []
}

function quickApply(internshipId: number) {
  const internship = internships.value.find(i => i.id === internshipId)
  if (internship) {
    selectedInternship.value = internship
    showApplicationModal.value = true
  }
}

function viewDetails(internshipId: number) {
  const internship = internships.value.find(i => i.id === internshipId)
  if (internship) {
    selectedInternship.value = internship
    showDetailsModal.value = true
  }
}

function closeModal() {
  showDetailsModal.value = false
  showApplicationModal.value = false
  showReviewModal.value = false
  selectedInternship.value = null
}

function applyNow() {
  console.log('Apply now clicked')
  closeModal()
  showReviewModal.value = true
}

function saveForLater() {
  console.log('Saving internship for later:', selectedInternship.value?.title)
}

function uploadDocument(docIndex: number) {
  const doc = applicationData.value.documents[docIndex]
  if (doc) {
    console.log('Upload document:', doc.name)
  }
}

function removeDocument(docIndex: number) {
  const doc = applicationData.value.documents[docIndex]
  if (doc) {
    doc.file = null
    doc.status = 'missing'
  }
}

function submitApplication() {
  console.log('Submitting application for:', selectedInternship.value?.title)
  closeModal()
}

function goToPreviousStep() {
  console.log('Going to previous step')
}

function reviewApplication() {
  console.log('Reviewing application')
  closeModal()
  showReviewModal.value = true
}

function getInitials(company: string): string {
  return company.split(' ').map(word => word[0]).join('').toUpperCase().slice(0, 2)
}
</script>

<template>
  <div class="internship-content">
    <!-- Header -->
    <div class="internship-header">
      <div class="header-left">
        <img src="/icons/icon-internship.png" alt="Internship" class="header-icon" />
        <h1 class="header-title">Internship</h1>
      </div>
      <div class="header-right">
        <div class="notification-wrapper">
          <BellIcon class="notification-icon-bell" />
        </div>
        <div class="avatar" @click="handleAvatarClick" title="View Profile">{{ userInitials }}</div>
      </div>
    </div>

    <div class="internship-main">
      <div class="main-layout">
      <!-- Filters Sidebar -->
      <div class="filters-sidebar">
        <h3 class="filters-title">Filters</h3>
        
        <!-- Location Filter -->
        <div class="filter-group">
          <label class="filter-label">Location</label>
          <input 
            v-model="locationFilter"
            type="text" 
            placeholder="e.g. New York, Remote"
            class="filter-input"
          />
        </div>

        <!-- Skills Filter -->
        <div class="filter-group">
          <label class="filter-label">Skills</label>
          <div class="selected-skills">
            <div v-for="skill in skillsFilter" :key="skill" class="skill-tag">
              <span>{{ skill }}</span>
              <button @click="removeSkillFilter(skill)" class="remove-tag">×</button>
            </div>
          </div>
        </div>

        <!-- Availability Filter -->
        <div class="filter-group">
          <label class="filter-label">Availability</label>
          <div class="date-range">
            <input v-model="availabilityStart" type="date" class="date-input" />
            <span class="date-separator">—</span>
            <input v-model="availabilityEnd" type="date" class="date-input" />
          </div>
        </div>

        <!-- Company Type Filter -->
        <div class="filter-group">
          <label class="filter-label">Company Type</label>
          <div class="checkbox-group">
            <label v-for="type in companyTypes" :key="type" class="checkbox-label">
              <input 
                type="checkbox" 
                :value="type"
                v-model="selectedCompanyTypes"
                class="checkbox-input"
              />
              <span class="checkbox-text">{{ type }}</span>
            </label>
          </div>
        </div>

        <!-- Duration Filter -->
        <div class="filter-group">
          <label class="filter-label">Duration</label>
          <select v-model="duration" class="filter-select">
            <option value="1-3 Months">1-3 Months</option>
            <option value="4-6 Months">4-6 Months</option>
            <option value="6+ Months">6+ Months</option>
          </select>
        </div>

        <!-- Filter Actions -->
        <div class="filter-actions">
          <button @click="applyFilters" class="apply-btn" disabled title="Coming soon">Apply filters</button>
          <button @click="clearAllFilters" class="clear-btn">Clear All</button>
        </div>
      </div>

      <!-- Main Content -->
      <div class="main-content">
        <div class="content-header">
          <h2 class="content-title">Available Internships</h2>
          
          <!-- Search Bar -->
          <div class="search-container">
            <input 
              v-model="searchQuery"
              type="text" 
              placeholder="Search internship by keyword..."
              class="search-input"
            />
            <span class="search-icon"><MagnifyingGlassIcon class="search-icon-svg" /></span>
          </div>
        </div>

        <!-- Internships Grid -->
        <div class="internships-grid">
          <div v-for="internship in internships" :key="internship.id" class="internship-card">
            <div class="card-header">
              <div class="company-avatar">
                <span class="company-initials">{{ getInitials(internship.company) }}</span>
              </div>
              <div class="internship-info">
                <h3 class="internship-title">{{ internship.title }}</h3>
                <p class="company-name">{{ internship.company }}</p>
                <div class="location-info">
                  <MapPinIcon class="location-icon-svg" />
                  <span class="location-text">{{ internship.location }}</span>
                </div>
              </div>
              <div class="match-info">
                <div class="match-score">{{ internship.match }}% Match</div>
                <div v-if="internship.recommended" class="recommended-badge">
                  <CheckIcon class="badge-icon-svg" />
                  <span class="badge-text">Recommended for You</span>
                </div>
              </div>
            </div>

            <div class="card-content">
              <p class="internship-description">{{ internship.description }}</p>
              
              <div class="skills-list">
                <span v-for="skill in internship.skills" :key="skill" class="skill-badge">
                  {{ skill }}
                </span>
              </div>
            </div>

            <div class="card-actions">
              <button @click="quickApply(internship.id)" class="quick-apply-btn">
                Quick Apply
              </button>
              <button @click="viewDetails(internship.id)" class="view-details-btn">
                View Details
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Internship Details Modal -->
    <div v-if="showDetailsModal && selectedInternship" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <!-- Modal Header -->
        <div class="modal-header">
          <div class="internship-header-info">
            <div class="company-avatar large">
              <span class="company-initials">{{ getInitials(selectedInternship.company) }}</span>
            </div>
            <div class="header-details">
              <h2 class="modal-title">{{ selectedInternship.title }}</h2>
              <p class="modal-company">{{ selectedInternship.company }}</p>
              <div class="modal-location">
                <MapPinIcon class="location-icon-svg" />
                <span>{{ selectedInternship.location }}</span>
              </div>
            </div>
          </div>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>

        <div class="modal-body">
          <div class="modal-grid">
            <!-- Left Column -->
            <div class="left-column">
              <!-- Description -->
              <div class="section">
                <h3 class="section-title">Description</h3>
                <p class="section-content">{{ selectedInternship.description }}</p>
              </div>

              <!-- Responsibilities -->
              <div class="section">
                <h3 class="section-title">Responsibilities</h3>
                <ul class="responsibility-list">
                  <li v-for="responsibility in selectedInternship.responsibilities" :key="responsibility" class="responsibility-item">
                    <ChevronRightIcon class="bullet-icon-svg" />
                    {{ responsibility }}
                  </li>
                </ul>
              </div>

              <!-- Requirements -->
              <div class="section">
                <h3 class="section-title">Requirements</h3>
                <ul class="requirement-list">
                  <li v-for="requirement in selectedInternship.requirements" :key="requirement" class="requirement-item">
                    <DocumentTextIcon class="bullet-icon-svg" />
                    {{ requirement }}
                  </li>
                </ul>
              </div>

              <!-- Schedule -->
              <div class="section">
                <h3 class="section-title">Schedule</h3>
                <div class="schedule-details">
                  <div class="schedule-item">
                    <strong>Start Date:</strong> {{ selectedInternship.schedule.startDate }}
                  </div>
                  <div class="schedule-item">
                    <strong>End Date:</strong> {{ selectedInternship.schedule.endDate }}
                  </div>
                  <div class="schedule-item">
                    <strong>Daily Hours:</strong> {{ selectedInternship.schedule.dailyHours }}
                  </div>
                  <div class="schedule-item">
                    <strong>Total Hours:</strong> {{ selectedInternship.schedule.totalHours }}
                  </div>
                </div>
              </div>

              <!-- About Company -->
              <div class="section">
                <h3 class="section-title">About {{ selectedInternship.company }}</h3>
                <p class="section-content">{{ selectedInternship.aboutCompany }}</p>
                <p class="section-content">{{ selectedInternship.mission }}</p>
              </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
              <!-- Compatibility -->
              <div class="compatibility-card">
                <div class="compatibility-header">
                  <h3 class="compatibility-title">Your Compatibility</h3>
                  <div class="compatibility-score">{{ selectedInternship.compatibility.score }}% Match</div>
                </div>
                <p class="compatibility-subtitle">Based on your profile skills and course</p>
                
                <div class="skills-match">
                  <div v-for="skill in selectedInternship.compatibility.matches" :key="skill" class="skill-match-item matched">
                    <CheckIcon class="match-icon-svg" />
                    <span class="skill-text">{{ skill }}</span>
                  </div>
                  <div v-for="skill in selectedInternship.compatibility.missing" :key="skill" class="skill-match-item missing">
                    <span class="match-icon">○</span>
                    <span class="skill-text">{{ skill }}</span>
                  </div>
                </div>

                <div class="action-buttons">
                <button @click="applyNow" class="Review-Application" disabled title="Coming soon">Review Application</button>
                <button @click="saveForLater" class="save-later-btn" disabled title="Coming soon">Save for Later</button>
                </div>

                <div class="social-share">
                  <button class="share-btn"><BookmarkIcon class="share-icon" /></button>
                  <button class="share-btn"><BriefcaseIcon class="share-icon" /></button>
                  <button class="share-btn"><LinkIcon class="share-icon" /></button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Application Form Modal -->
    <div v-if="showApplicationModal && selectedInternship" class="modal-overlay" @click="closeModal">
      <div class="application-modal-content" @click.stop>
        <!-- Application Header -->
        <div class="application-header">
          <div class="header-left">
            <img src="/icons/logo-main.png" alt="Logo" class="app-logo" />
          </div>
          <div class="header-right">
            <span class="company-name">Acme Corp. Company</span>
            <div class="user-avatar">AC</div>
          </div>
        </div>

        <div class="application-body">
          <div class="application-grid">
            <!-- Left Column - Documents -->
            <div class="documents-section">
              <h2 class="section-title">Required Documents</h2>
              <p class="section-subtitle">Upload all required documents to complete your application</p>

              <!-- Document Checklist -->
              <div class="document-checklist">
                <h3 class="checklist-title">Document Checklist</h3>
                <p class="checklist-subtitle">All files combined for a single document</p>

                <div class="document-list">
                  <div v-for="(doc, index) in applicationData.documents" :key="doc.name" class="document-item">
                    <div class="doc-status-icon" :class="{ completed: doc.status === 'completed', missing: doc.status === 'missing' }">
                      <CheckIcon v-if="doc.status === 'completed'" class="status-icon-svg" />
                      <span v-else class="status-icon">○</span>
                    </div>
                    <div class="doc-info">
                      <span class="doc-name">{{ doc.name }}</span>
                      <span class="doc-status" :class="doc.status">{{ doc.status === 'completed' ? 'Required' : 'Required' }}</span>
                    </div>
                  </div>
                </div>

                <div class="upload-section">
                  <div class="upload-status">
                    <DocumentTextIcon class="upload-icon-svg" />
                    <span class="upload-text">Resume/CV</span>
                  </div>
                  <p class="upload-description">
                    Upload your latest Resume/CV. We'll use this document to match your skills with 
                    internship requirements and roles.
                  </p>
                  <div class="file-actions">
                    <button class="file-action-btn">PDF</button>
                    <button class="file-action-btn">DOC</button>
                    <button class="file-action-btn">DOCX</button>
                    <button class="file-action-btn">TXT</button>
                  </div>
                  <div class="upload-area">
                    <CloudArrowUpIcon class="upload-icon-large-svg" />
                    <p class="upload-text-large">Drag & drop your resume here, or</p>
                    <button class="browse-btn">Browse files</button>
                  </div>
                  <div class="upload-note">
                    <DocumentTextIcon class="note-icon-svg" />
                    <span class="note-text">Tips for optimal resume format</span>
                  </div>
                </div>

                <!-- Cover Letter Section -->
                <div class="upload-section">
                  <div class="upload-status">
                    <DocumentTextIcon class="upload-icon-svg" />
                    <span class="upload-text">Cover Letter</span>
                  </div>
                  <p class="upload-description">
                    Upload your cover letter. This should be tailored to the specific internship you're applying for.
                  </p>
                  <div class="file-actions">
                    <button class="file-action-btn">PDF</button>
                    <button class="file-action-btn">DOC</button>
                    <button class="file-action-btn">DOCX</button>
                    <button class="file-action-btn">TXT</button>
                  </div>
                  <div class="upload-area">
                    <CloudArrowUpIcon class="upload-icon-large-svg" />
                    <p class="upload-text-large">Drag & drop your cover letter here, or</p>
                    <button class="browse-btn">Browse files</button>
                  </div>
                </div>

                <!-- Portfolio Section -->
                <div class="upload-section">
                  <div class="upload-status">
                    <PaintBrushIcon class="upload-icon-svg" />
                    <span class="upload-text">Portfolio Samples</span>
                    <span class="optional-badge">Optional</span>
                  </div>
                  <p class="upload-description">
                    Showcase your work with portfolio samples that highlight the relevant skills, experience, 
                    and projects for this internship.
                  </p>
                  <div class="file-actions">
                    <button class="file-action-btn">PDF</button>
                    <button class="file-action-btn">PNG</button>
                    <button class="file-action-btn">DOCX</button>
                    <button class="file-action-btn">Add a link</button>
                  </div>
                  <div class="upload-area">
                    <CloudArrowUpIcon class="upload-icon-large-svg" />
                    <p class="upload-text-large">Drag & drop your files here, or</p>
                    <button class="browse-btn">Browse files</button>
                  </div>
                </div>

                <!-- Reference Documents -->
                <div class="upload-section">
                  <div class="upload-status">
                    <UserGroupIcon class="upload-icon-svg" />
                    <span class="upload-text">Reference Documents</span>
                    <span class="required-badge">Required</span>
                  </div>
                  <p class="upload-description">
                    Provide contact information for at least 2 professional references. Include their name, title, 
                    company, email, and phone number.
                  </p>
                  <div class="file-actions">
                    <button class="file-action-btn">PDF</button>
                    <button class="file-action-btn">DOC</button>
                    <button class="file-action-btn">DOCX</button>
                    <button class="file-action-btn">Add a link</button>
                  </div>
                  <div class="upload-area">
                    <CloudArrowUpIcon class="upload-icon-large-svg" />
                    <p class="upload-text-large">Drag & drop your references document here, or</p>
                    <button class="browse-btn">Browse files</button>
                  </div>
                  <div class="upload-note">
                    <DocumentTextIcon class="note-icon-svg" />
                    <span class="note-text">Download reference template</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column - Progress & Help -->
            <div class="sidebar-section">
              <!-- Progress Card -->
              <div class="progress-card">
                <h3 class="progress-title">Your Progress</h3>
                <div class="progress-circle">
                  <div class="circle-progress" :style="{ '--progress': applicationProgress }">
                    <span class="progress-number">{{ applicationProgress }}%</span>
                  </div>
                </div>
                <div class="progress-items">
                  <div class="progress-item completed">
                    <CheckIcon class="progress-icon-svg" />
                    <span class="progress-text">Personal Information</span>
                  </div>
                  <div class="progress-item current">
                    <DocumentTextIcon class="progress-icon-doc-svg" />
                    <span class="progress-text">Documents</span>
                  </div>
                </div>
              </div>

              <!-- Need Help Card -->
              <div class="help-card">
                <h3 class="help-title">Need Help?</h3>
                <div class="help-items">
                  <div class="help-item">
                    <BookOpenIcon class="help-icon-svg" />
                    <span class="help-text">DOCUMENT FORMATTING GUIDE</span>
                  </div>
                  <div class="help-item">
                    <CloudArrowUpIcon class="help-icon-svg" />
                    <span class="help-text">UPLOAD SUPPORT</span>
                  </div>
                  <div class="help-item">
                    <QuestionMarkCircleIcon class="help-icon-svg" />
                    <span class="help-text">FAQ</span>
                  </div>
                </div>
              </div>

              <!-- Quick Tips Card -->
              <div class="tips-card">
                <h3 class="tips-title">Quick Tips</h3>
                <ul class="tips-list">
                  <li>Ensure all documents are clearly formatted and easy to read</li>
                  <li>Use PDF format when possible for consistent formatting</li>
                  <li>Include all relevant experience and skills in your resume</li>
                  <li>Keep file sizes under 10MB each for faster upload</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Application Footer -->
        <div class="application-footer">
          <button @click="goToPreviousStep" class="footer-btn secondary" disabled title="Coming soon">Previous: Personal Info</button>
          <button @click="reviewApplication" class="footer-btn primary" disabled title="Coming soon">Review Application →</button>
        </div>
      </div>
    </div>

    <!-- Review Application Modal -->
    <div v-if="showReviewModal" class="modal-overlay" @click="closeModal">
      <div class="review-modal-content" @click.stop>
        <!-- Header -->
        <div class="review-header">
          <div class="header-left">
            <img src="/icons/logo-main.png" alt="Logo" class="app-logo" />
          </div>
          <div class="header-right">
            <BellIcon class="notification-icon-bell" />
            <span class="company-name">Acme Corp. Company</span>
            <div class="user-avatar">AC</div>
          </div>
        </div>

        <!-- Success Banner -->
        <div class="success-banner">
          <div class="success-icon"><CheckCircleIcon class="success-icon-svg" /></div>
          <div class="success-content">
            <h2 class="success-title">All required documents uploaded!</h2>
            <p class="success-subtitle">You can now proceed to review your application</p>
          </div>
        </div>

        <div class="review-body">
          <div class="review-grid">
            <!-- Left Column -->
            <div class="left-section">
              <!-- Document Upload Complete -->
              <div class="completion-card">
                <h2 class="completion-title">Document Upload Complete</h2>
                <p class="completion-subtitle">All your documents have been successfully uploaded and verified</p>
                
                <div class="progress-indicator">
                  <span class="step-text">Step 2 of 2</span>
                  <div class="progress-bar-full">
                    <div class="progress-fill-full"></div>
                  </div>
                </div>

                <!-- Document Checklist Complete -->
                <div class="checklist-complete">
                  <div class="checklist-header">
                    <DocumentTextIcon class="checklist-icon-svg" />
                    <h3 class="checklist-title">Document Checklist Complete</h3>
                  </div>
                  <p class="checklist-subtitle">You have completed 4 of 4 required documents. 1 optional document uploaded.</p>
                  
                  <div class="document-status-list">
                    <div class="status-item">
                      <div class="status-icon completed"><CheckIcon class="status-icon-svg" /></div>
                      <span class="status-name">Resume/CV</span>
                      <span class="status-label uploaded">Uploaded</span>
                    </div>
                    <div class="status-item">
                      <div class="status-icon completed"><CheckIcon class="status-icon-svg" /></div>
                      <span class="status-name">Cover Letter</span>
                      <span class="status-label uploaded">Uploaded</span>
                    </div>
                    <div class="status-item">
                      <div class="status-icon completed"><CheckIcon class="status-icon-svg" /></div>
                      <span class="status-name">Portfolio/Work Samples</span>
                      <span class="status-label optional">Optional</span>
                    </div>
                    <div class="status-item">
                      <div class="status-icon completed"><CheckIcon class="status-icon-svg" /></div>
                      <span class="status-name">References Document</span>
                      <span class="status-label uploaded">Uploaded</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Uploaded Documents -->
              <div class="uploaded-documents">
                <div class="document-item">
                  <div class="doc-icon-check"><CheckIcon class="doc-check-svg" /></div>
                  <div class="doc-info">
                    <span class="doc-name">Resume/CV</span>
                    <span class="doc-filename">JohnDoe_Resume.pdf</span>
                  </div>
                  <span class="doc-status uploaded">Uploaded</span>
                  <button class="doc-action">⌄</button>
                </div>

                <div class="document-item">
                  <div class="doc-icon-check"><CheckIcon class="doc-check-svg" /></div>
                  <div class="doc-info">
                    <span class="doc-name">Cover Letter</span>
                    <span class="doc-filename">JohnDoe_CoverLetter.pdf</span>
                  </div>
                  <span class="doc-status uploaded">Uploaded</span>
                  <button class="doc-action">⌄</button>
                </div>

                <div class="document-item">
                  <div class="doc-icon-check"><CheckIcon class="doc-check-svg" /></div>
                  <div class="doc-info">
                    <span class="doc-name">Portfolio/Work Samples</span>
                    <span class="doc-filename">Portfolio_3_files.zip</span>
                  </div>
                  <span class="doc-status uploaded">Uploaded</span>
                  <button class="doc-action">⌄</button>
                </div>

                <div class="document-item">
                  <div class="doc-icon-check"><CheckIcon class="doc-check-svg" /></div>
                  <div class="doc-info">
                    <span class="doc-name">References Document</span>
                    <span class="doc-filename">Professional_References.pdf</span>
                  </div>
                  <span class="doc-status uploaded">Uploaded</span>
                  <button class="doc-action">⌄</button>
                </div>

                <!-- Add More Documents -->
                <div class="add-documents">
                  <button class="add-btn">
                    <span class="add-icon">+</span>
                  </button>
                  <div class="add-content">
                    <h4 class="add-title">Want to upload additional supporting documents?</h4>
                    <p class="add-subtitle">Click here to add certifications, transcripts, or other relevant files</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column -->
            <div class="right-section">
              <!-- Your Progress -->
              <div class="progress-card">
                <h3 class="progress-title">Your Progress</h3>
                <div class="progress-circle">
                  <div class="circle-progress" style="--progress: 75">
                    <span class="progress-number">75%</span>
                  </div>
                </div>
                <div class="progress-steps">
                  <div class="progress-step completed">
                    <div class="step-icon-check"><CheckIcon class="step-check-svg" /></div>
                    <span class="step-text">Personal Information</span>
                  </div>
                  <div class="progress-step completed">
                    <div class="step-icon-check"><CheckIcon class="step-check-svg" /></div>
                    <span class="step-text">Documents</span>
                  </div>
                </div>
              </div>

              <!-- What's Next -->
              <div class="next-steps-card">
                <h3 class="next-title">What's Next?</h3>
                <div class="next-steps">
                  <div class="next-step">
                    <span class="step-number">1</span>
                    <div class="step-content">
                      <h4 class="step-title">Review your application</h4>
                      <p class="step-description">Double-check all info before submitting</p>
                    </div>
                  </div>
                  <div class="next-step">
                    <span class="step-number">2</span>
                    <div class="step-content">
                      <h4 class="step-title">Preview employer view</h4>
                      <p class="step-description">See how employers will see your profile</p>
                    </div>
                  </div>
                  <div class="next-step">
                    <span class="step-number">3</span>
                    <div class="step-content">
                      <h4 class="step-title">Submit your application</h4>
                      <p class="step-description">Complete and send your application</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Summary -->
              <div class="summary-card">
                <h3 class="summary-title">Summary</h3>
                <div class="summary-item">
                  <span class="summary-label">Files uploaded</span>
                  <span class="summary-value">4</span>
                </div>
                <div class="summary-item">
                  <span class="summary-label">Total size</span>
                  <span class="summary-value">6.2 MB</span>
                </div>
                <div class="summary-status">
                  <div class="status-icon-small-check"><CheckIcon class="status-check-small-svg" /></div>
                  <span class="status-text">All requirements met</span>
                </div>
              </div>

              <!-- Need Help -->
              <div class="help-card">
                <h3 class="help-title">Need Help?</h3>
                <div class="help-items">
                  <div class="help-item">
                    <BookOpenIcon class="help-icon-svg" />
                    <span class="help-text">Application guide</span>
                  </div>
                  <div class="help-item">
                    <QuestionMarkCircleIcon class="help-icon-svg" />
                    <span class="help-text">Contact support</span>
                  </div>
                  <div class="help-item">
                    <QuestionMarkCircleIcon class="help-icon-svg" />
                    <span class="help-text">FAQs</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="review-footer">
          <button class="footer-btn secondary">← Previous: Personal Info</button>
          <button class="footer-btn primary">Apply Now</button>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<style scoped>
.internship-content {
  padding: 0;
  background: #f8fafc;
  min-height: 100vh;
}

.internship-main {
  padding: 24px;
}

/* Header */
.internship-header {
  background: #dbeafe;
  padding: 16px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #bfdbfe;
  margin-bottom: 0;
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
  transition: all 0.2s ease;
}

.notification-icon-bell:hover {
  color: #2563eb;
  transform: scale(1.1);
}

.avatar {
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

.avatar:hover {
  background: #2563eb;
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.company-name {
  font-size: 14px;
  color: #6b7280;
}

.user-avatar {
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

/* Main Layout */
.main-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 24px;
}

/* Filters Sidebar */
.filters-sidebar {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  height: fit-content;
  min-width: 0;
}

.filters-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 24px 0;
}

.filter-group {
  margin-bottom: 24px;
}

.filter-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

.filter-input,
.filter-select {
  width: 100%;
  max-width: 100%;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.filter-input:focus,
.filter-select:focus {
  border-color: #3b82f6;
}

.selected-skills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}

.skill-tag {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: #dbeafe;
  color: #1e40af;
  border-radius: 12px;
  font-size: 12px;
}

.remove-tag {
  background: none;
  border: none;
  color: #1e40af;
  cursor: pointer;
  font-size: 14px;
  padding: 0;
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.date-range {
  display: flex;
  align-items: center;
  gap: 8px;
}

.date-input {
  flex: 1;
  min-width: 0;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
  box-sizing: border-box;
}

.date-separator {
  color: #6b7280;
}

.checkbox-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-input {
  width: 16px;
  height: 16px;
}

.checkbox-text {
  font-size: 14px;
  color: #374151;
}

.filter-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid #f3f4f6;
}

.apply-btn {
  padding: 10px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.apply-btn:hover {
  background: #2563eb;
}

.clear-btn {
  padding: 8px 16px;
  background: none;
  color: #6b7280;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}

.clear-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

/* Main Content */
.main-content {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.content-header {
  margin-bottom: 24px;
}

.content-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 16px 0;
}

.search-container {
  position: relative;
  margin-bottom: 16px;
}

.search-input {
  width: 100%;
  max-width: 100%;
  padding: 12px 40px 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}

.search-input:focus {
  border-color: #3b82f6;
}

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  color: #6b7280;
}

.search-icon-svg {
  width: 20px;
  height: 20px;
}

.location-icon-svg {
  width: 16px;
  height: 16px;
  color: #6b7280;
  flex-shrink: 0;
}

.badge-icon-svg {
  width: 14px;
  height: 14px;
  stroke-width: 3;
}

.match-icon-svg {
  width: 16px;
  height: 16px;
  stroke-width: 3;
}

.share-icon {
  width: 18px;
  height: 18px;
}

.status-icon-svg {
  width: 14px;
  height: 14px;
  stroke-width: 3;
}

.upload-icon-svg {
  width: 20px;
  height: 20px;
  color: #3b82f6;
}

.progress-icon-svg {
  width: 16px;
  height: 16px;
  stroke-width: 3;
}

.success-icon-svg {
  width: 32px;
  height: 32px;
}

.doc-icon-svg {
  width: 16px;
  height: 16px;
  stroke-width: 3;
}

.step-icon-svg {
  width: 16px;
  height: 16px;
  stroke-width: 3;
}

.status-icon-small-svg {
  width: 16px;
  height: 16px;
  stroke-width: 3;
}

.bullet-icon-svg {
  width: 16px;
  height: 16px;
  color: #3b82f6;
  flex-shrink: 0;
  margin-top: 2px;
}

.upload-icon-large-svg {
  width: 48px;
  height: 48px;
  color: #3b82f6;
  margin: 0 auto 8px;
}

.note-icon-svg {
  width: 14px;
  height: 14px;
  color: #3b82f6;
}

.checklist-icon-svg {
  width: 20px;
  height: 20px;
  color: #3b82f6;
}

.doc-icon-check {
  width: 32px;
  height: 32px;
  background: #dcfce7;
  color: #16a34a;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.doc-check-svg {
  width: 18px;
  height: 18px;
  stroke-width: 3;
}

.step-icon-check {
  width: 16px;
  height: 16px;
  background: #dcfce7;
  color: #16a34a;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.step-check-svg {
  width: 10px;
  height: 10px;
  stroke-width: 3;
}

.status-icon-small-check {
  width: 16px;
  height: 16px;
  background: #16a34a;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.status-check-small-svg {
  width: 10px;
  height: 10px;
  stroke-width: 3;
}

.help-icon-svg {
  width: 18px;
  height: 18px;
  color: #6b7280;
  flex-shrink: 0;
}

.progress-icon-doc-svg {
  width: 16px;
  height: 16px;
  color: #3b82f6;
}

.active-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.filter-tag {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: #dbeafe;
  color: #1e40af;
  border-radius: 12px;
  font-size: 12px;
}

.remove-filter {
  background: none;
  border: none;
  color: #1e40af;
  cursor: pointer;
  font-size: 14px;
  padding: 0;
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Internships Grid */
.internships-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 20px;
}

.internship-card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.2s;
  background: white;
}

.internship-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.card-header {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}

.company-avatar {
  width: 48px;
  height: 48px;
  background: #3b82f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.company-initials {
  color: white;
  font-size: 16px;
  font-weight: 600;
}

.internship-info {
  flex: 1;
}

.internship-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.company-name {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 8px 0;
}

.location-info {
  display: flex;
  align-items: center;
  gap: 4px;
}

.location-icon {
  font-size: 12px;
}

.location-text {
  font-size: 12px;
  color: #6b7280;
}

.match-info {
  text-align: right;
  flex-shrink: 0;
}

.match-score {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 4px;
}

.recommended-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  background: #dcfce7;
  color: #16a34a;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 500;
}

.badge-icon {
  font-size: 10px;
}

.card-content {
  margin-bottom: 16px;
}

.internship-description {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.5;
  margin: 0 0 16px 0;
}

.skills-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.skill-badge {
  padding: 4px 8px;
  background: #f3f4f6;
  color: #374151;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.card-actions {
  display: flex;
  gap: 12px;
}

.quick-apply-btn {
  flex: 1;
  padding: 10px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.quick-apply-btn:hover {
  background: #2563eb;
}

.view-details-btn {
  flex: 1;
  padding: 10px 16px;
  background: none;
  color: #6b7280;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.view-details-btn:hover {
  background: #f3f4f6;
  color: #374151;
  border-color: #9ca3af;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-layout {
    grid-template-columns: 1fr;
  }
  
  .filters-sidebar {
    order: 2;
  }
}

@media (max-width: 768px) {
  .internship-content {
    padding: 16px;
  }
  
  .internship-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .internships-grid {
    grid-template-columns: 1fr;
  }
  
  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .match-info {
    text-align: left;
  }
  
  .card-actions {
    flex-direction: column;
  }
  
  .date-range {
    flex-direction: column;
    gap: 12px;
  }
  
  .date-input {
    width: 100%;
  }
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  backdrop-filter: blur(4px);
}

.modal-content {
  background: white;
  border-radius: 16px;
  width: 90%;
  max-width: 1000px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 24px 24px 0 24px;
  border-bottom: 1px solid #f3f4f6;
  margin-bottom: 24px;
}

.internship-header-info {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.company-avatar.large {
  width: 64px;
  height: 64px;
  font-size: 20px;
}

.header-details {
  flex: 1;
}

.modal-title {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.modal-company {
  font-size: 16px;
  color: #6b7280;
  margin: 0 0 8px 0;
}

.modal-location {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #6b7280;
}

.modal-close {
  width: 32px;
  height: 32px;
  border: none;
  background: #f3f4f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #6b7280;
  font-size: 16px;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #e5e7eb;
  color: #374151;
}

.modal-body {
  padding: 0 24px 24px 24px;
}

.modal-grid {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 32px;
}

.left-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.section-content {
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
  margin: 0;
}

.responsibility-list,
.requirement-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.responsibility-item,
.requirement-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
}

.bullet-icon {
  font-size: 12px;
  margin-top: 2px;
  flex-shrink: 0;
}

.schedule-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.schedule-item {
  font-size: 14px;
  color: #374151;
}

.schedule-item strong {
  color: #1f2937;
}

/* Right Column - Compatibility Card */
.right-column {
  display: flex;
  flex-direction: column;
}

.compatibility-card {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  position: sticky;
  top: 20px;
}

.compatibility-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.compatibility-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.compatibility-score {
  background: #dbeafe;
  color: #1e40af;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.compatibility-subtitle {
  font-size: 12px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.skills-match {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

.skill-match-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.skill-match-item.matched {
  color: #16a34a;
}

.skill-match-item.missing {
  color: #6b7280;
}

.match-icon {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  flex-shrink: 0;
}

.skill-match-item.matched .match-icon {
  background: #dcfce7;
  color: #16a34a;
}

.skill-match-item.missing .match-icon {
  background: #f3f4f6;
  color: #9ca3af;
}

.action-buttons {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.Review-Application {
  width: 100%;
  padding: 12px 16px;
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.Review-Application:hover {
  background: #e5e7eb;
  color: #1f2937;
  border-color: #9ca3af;
}

.save-later-btn {
  width: 100%;
  padding: 10px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.save-later-btn:hover {
  background: #2563eb;
}

.social-share {
  display: flex;
  justify-content: center;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.share-btn {
  width: 32px;
  height: 32px;
  background: #f3f4f6;
  border: none;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
}

.share-btn:hover {
  background: #e5e7eb;
}

/* Modal Responsive */
@media (max-width: 768px) {
  .modal-content {
    width: 95%;
    margin: 20px;
  }
  
  .modal-grid {
    grid-template-columns: 1fr;
  }
  
  .compatibility-card {
    position: static;
    order: -1;
  }
  
  .modal-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .internship-header-info {
    width: 100%;
  }
  
  .modal-close {
    align-self: flex-end;
  }
}

/* Application Modal Styles */
.application-modal-content {
  background: white;
  border-radius: 16px;
  width: 95%;
  max-width: 1200px;
  max-height: 95vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.application-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #f8fafc;
}

.header-left {
  display: flex;
  align-items: center;
}

.app-logo {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.company-name {
  font-size: 14px;
  color: #6b7280;
}

.application-body {
  padding: 24px;
}

.application-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 32px;
}

.documents-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-title {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.section-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.document-checklist {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
}

.checklist-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.checklist-subtitle {
  font-size: 12px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.document-list {
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

.doc-status-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.doc-status-icon.completed {
  background: #dcfce7;
  color: #16a34a;
}

.doc-status-icon.missing {
  background: #f3f4f6;
  color: #9ca3af;
}

.doc-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.doc-name {
  font-size: 14px;
  color: #1f2937;
  font-weight: 500;
}

.doc-status {
  font-size: 12px;
  color: #6b7280;
}

.upload-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 16px;
}

.upload-status {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.upload-icon {
  font-size: 16px;
}

.upload-text {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
}

.optional-badge {
  background: #fef3c7;
  color: #92400e;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 500;
}

.required-badge {
  background: #fee2e2;
  color: #dc2626;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 500;
}

.upload-description {
  font-size: 13px;
  color: #6b7280;
  line-height: 1.5;
  margin: 0 0 12px 0;
}

.file-actions {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}

.file-action-btn {
  padding: 4px 8px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  font-size: 11px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.file-action-btn:hover {
  background: #e5e7eb;
  color: #374151;
}

.upload-area {
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  padding: 24px;
  text-align: center;
  background: #f9fafb;
  transition: all 0.2s;
}

.upload-area:hover {
  border-color: #3b82f6;
  background: #f0f9ff;
}

.upload-icon-large {
  font-size: 32px;
  margin-bottom: 8px;
}

.upload-text-large {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 8px 0;
}

.browse-btn {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s;
}

.browse-btn:hover {
  background: #2563eb;
}

.upload-note {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  font-size: 12px;
  color: #3b82f6;
  cursor: pointer;
}

.note-icon {
  font-size: 12px;
}

/* Sidebar Section */
.sidebar-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
  position: sticky;
  top: 20px;
  height: fit-content;
}

.progress-card,
.help-card,
.tips-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  position: relative;
}

.progress-title,
.help-title,
.tips-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 16px 0;
}

.progress-circle {
  display: flex;
  justify-content: center;
  margin-bottom: 16px;
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
    #3b82f6 0deg,
    #3b82f6 calc(var(--progress) * 3.6deg),
    transparent calc(var(--progress) * 3.6deg)
  );
  mask: radial-gradient(circle at center, transparent 65%, white 65%);
  -webkit-mask: radial-gradient(circle at center, transparent 65%, white 65%);
}

.progress-number {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  z-index: 2;
  position: relative;
}

.progress-items {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.progress-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.progress-item.completed {
  color: #16a34a;
}

.progress-item.current {
  color: #3b82f6;
}

.progress-icon {
  font-size: 14px;
}

.progress-text {
  font-size: 13px;
}

.help-items {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.help-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  background: #f8fafc;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}

.help-item:hover {
  background: #f1f5f9;
}

.help-icon {
  font-size: 16px;
}

.help-text {
  font-size: 11px;
  font-weight: 500;
  color: #374151;
}

.tips-list {
  margin: 0;
  padding-left: 16px;
}

.tips-list li {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 8px;
  line-height: 1.4;
}

/* Application Footer */
.application-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f8fafc;
}

.footer-btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.footer-btn.secondary {
  background: none;
  color: #6b7280;
  border: 1px solid #d1d5db;
}

.footer-btn.secondary:hover {
  background: #f3f4f6;
  color: #374151;
}

.footer-btn.primary {
  background: #3b82f6;
  color: white;
  border: none;
}

.footer-btn.primary:hover {
  background: #2563eb;
}

/* Application Modal Responsive */
@media (max-width: 1024px) {
  .application-grid {
    grid-template-columns: 1fr;
  }
  
  .sidebar-section {
    order: -1;
  }
}

@media (max-width: 768px) {
  .application-modal-content {
    width: 98%;
    margin: 10px;
  }
  
  .application-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .application-body {
    padding: 16px;
  }
  
  .application-footer {
    flex-direction: column;
    gap: 12px;
  }
  
  .footer-btn {
    width: 100%;
  }
}

/* Review Modal Styles */
.review-modal-content {
  background: white;
  border-radius: 16px;
  width: 95%;
  max-width: 1200px;
  max-height: 95vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.review-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #f8fafc;
}

.review-header .header-left {
  display: flex;
  align-items: center;
}

.review-header .app-logo {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.review-header .header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.notification-icon {
  font-size: 16px;
  color: #6b7280;
}

.success-banner {
  background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
  border: 1px solid #86efac;
  margin: 24px;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.success-icon {
  width: 48px;
  height: 48px;
  background: #16a34a;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: bold;
  flex-shrink: 0;
}

.success-content {
  flex: 1;
}

.success-title {
  font-size: 18px;
  font-weight: 600;
  color: #15803d;
  margin: 0 0 4px 0;
}

.success-subtitle {
  font-size: 14px;
  color: #166534;
  margin: 0;
}

.review-body {
  padding: 0 24px 24px 24px;
}

.review-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 32px;
}

.left-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.completion-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
}

.completion-title {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.completion-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 20px 0;
}

.progress-indicator {
  margin-bottom: 24px;
}

.step-text {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 8px;
  display: block;
}

.progress-bar-full {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill-full {
  width: 100%;
  height: 100%;
  background: #16a34a;
  border-radius: 4px;
}

.checklist-complete {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
}

.checklist-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.checklist-icon {
  font-size: 20px;
}

.checklist-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.checklist-subtitle {
  font-size: 13px;
  color: #6b7280;
  margin: 0 0 16px 0;
}

.document-status-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
}

.status-icon.completed {
  background: #dcfce7;
  color: #16a34a;
}

.status-name {
  flex: 1;
  font-size: 14px;
  color: #374151;
}

.status-label {
  font-size: 12px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 12px;
}

.status-label.uploaded {
  background: #dcfce7;
  color: #16a34a;
}

.status-label.optional {
  background: #fef3c7;
  color: #92400e;
}

.uploaded-documents {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.document-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.doc-icon {
  width: 32px;
  height: 32px;
  background: #dcfce7;
  color: #16a34a;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: bold;
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

.doc-filename {
  font-size: 12px;
  color: #6b7280;
}

.doc-status {
  font-size: 12px;
  font-weight: 500;
  padding: 2px 8px;
  border-radius: 12px;
}

.doc-status.uploaded {
  background: #dcfce7;
  color: #16a34a;
}

.doc-action {
  width: 24px;
  height: 24px;
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.add-documents {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #f9fafb;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  margin-top: 8px;
}

.add-btn {
  width: 48px;
  height: 48px;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.add-btn:hover {
  background: #e5e7eb;
}

.add-icon {
  font-size: 20px;
  color: #6b7280;
}

.add-content {
  flex: 1;
}

.add-title {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin: 0 0 4px 0;
}

.add-subtitle {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
}

/* Right Section */
.right-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
  position: sticky;
  top: 20px;
  height: fit-content;
}

.progress-card,
.next-steps-card,
.summary-card,
.help-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
}

.progress-title,
.next-title,
.summary-title,
.help-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 16px 0;
}

.progress-circle {
  display: flex;
  justify-content: center;
  margin-bottom: 16px;
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
    #16a34a 0deg,
    #16a34a calc(var(--progress) * 3.6deg),
    transparent calc(var(--progress) * 3.6deg)
  );
  mask: radial-gradient(circle at center, transparent 65%, white 65%);
  -webkit-mask: radial-gradient(circle at center, transparent 65%, white 65%);
}

.progress-number {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  z-index: 2;
  position: relative;
}

.progress-steps {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.progress-step {
  display: flex;
  align-items: center;
  gap: 8px;
}

.progress-step.completed {
  color: #16a34a;
}

.step-icon {
  width: 16px;
  height: 16px;
  background: #dcfce7;
  color: #16a34a;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: bold;
}

.step-text {
  font-size: 13px;
}

.next-steps {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.next-step {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.step-number {
  width: 24px;
  height: 24px;
  background: #3b82f6;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}

.step-content {
  flex: 1;
}

.step-title {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.step-description {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.summary-label {
  font-size: 13px;
  color: #6b7280;
}

.summary-value {
  font-size: 13px;
  font-weight: 600;
  color: #1f2937;
}

.summary-status {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #dcfce7;
  border-radius: 6px;
  margin-top: 12px;
}

.status-icon-small {
  width: 16px;
  height: 16px;
  background: #16a34a;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: bold;
}

.status-text {
  font-size: 12px;
  color: #15803d;
  font-weight: 500;
}

.help-items {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.help-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  background: #f8fafc;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}

.help-item:hover {
  background: #f1f5f9;
}

.help-icon {
  font-size: 16px;
}

.help-text {
  font-size: 13px;
  color: #374151;
}

.review-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f8fafc;
}

/* Review Modal Responsive */
@media (max-width: 1024px) {
  .review-grid {
    grid-template-columns: 1fr;
  }
  
  .right-section {
    order: -1;
  }
}

@media (max-width: 768px) {
  .review-modal-content {
    width: 98%;
    margin: 10px;
  }
  
  .review-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .review-body {
    padding: 16px;
  }
  
  .success-banner {
    margin: 16px;
    flex-direction: column;
    text-align: center;
  }
  
  .review-footer {
    flex-direction: column;
    gap: 12px;
  }
  
  .review-footer .footer-btn {
    width: 100%;
  }
  
  .add-documents {
    flex-direction: column;
    text-align: center;
  }
}
</style>



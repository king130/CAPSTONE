<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Swal from '@/services/swal'
import { useAuthStore } from '@/stores/auth'
import { buildProfileAvatarUrl } from '@/services/profileMedia'
import { listDocuments, uploadDocuments, type DocumentRecord } from '@/services/documents'
import { 
  BellIcon,
  MagnifyingGlassIcon,
  DocumentTextIcon,
  CheckIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  ClipboardDocumentListIcon,
  EyeIcon,
  BriefcaseIcon,
  TrophyIcon,
  CloudArrowUpIcon,
  XMarkIcon
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

const organizationName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.schoolName as string) || authStore.user?.displayName || 'Account'
})

const emit = defineEmits<{
  openMessages: []
  navigateToProfile: []
}>()

function handleAvatarClick() {
  emit('navigateToProfile')
}

const searchQuery = ref('')
const loadingDocuments = ref(false)
const uploadingDocument = ref(false)
const documentsError = ref('')
const seedingTemporaryDocuments = ref(false)

const filteredDocuments = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return documents.value
  return documents.value.filter((doc) =>
    [doc.name, doc.type, doc.status, doc.category]
      .filter(Boolean)
      .some((value) => value.toLowerCase().includes(query))
  )
})

type DocumentCard = {
  id: string
  name: string
  type: string
  size: string
  daysAgo: string
  status: 'Verified' | 'Pending Review' | 'Needs Update'
  icon: string
  bgColor: string
  usedIn: string
  viewedTimes: string
  category: string
  portfolioNote?: string
  additionalInfo?: string
  fileUrl: string
}

const documents = ref<DocumentCard[]>([])

// Modal state
const showUploadModal = ref(false)
const selectedDocumentType = ref('')
const uploadStep = ref(1)
const documentName = ref('')
const selectedFile = ref<File | null>(null)

// Document types for upload
const documentTypes = ref([
  {
    id: 'internship-application',
    name: 'Internship Application Form',
    description: 'Standard application form for internship positions',
    icon: '/icons/icon-docs.png',
    bgColor: '#dbeafe'
  },
  {
    id: 'enrollment',
    name: 'Enrollment',
    description: 'University enrollment verification documents',
    icon: '/icons/icon-word.png',
    bgColor: '#dbeafe'
  },
  {
    id: 'registration',
    name: 'Registration Form',
    description: 'Registration form for internship programs and courses',
    icon: '/icons/icon-docs.png',
    bgColor: '#dbeafe'
  },
  {
    id: 'internship-agreement',
    name: 'Internship Agreement',
    description: 'Legal agreement between student, school, and company',
    icon: '/icons/icon-docs.png',
    bgColor: '#e0f2fe'
  },
  {
    id: 'performance-evaluation',
    name: 'Performance Evaluation',
    description: 'Evaluation forms and performance assessments',
    icon: '/icons/icon-docs.png',
    bgColor: '#dbeafe'
  },
  {
    id: 'other',
    name: 'Other',
    description: 'Other document types',
    icon: '/icons/icon-docs.png',
    bgColor: '#f3f4f6'
  }
])

const categoryNameMap = computed(() =>
  Object.fromEntries(documentTypes.value.map((type) => [type.id, type.name]))
)

const approvedDocuments = computed(() => documents.value.filter((doc) => doc.status === 'Verified').length)
const pendingDocuments = computed(() => documents.value.filter((doc) => doc.status === 'Pending Review').length)

const healthCheck = computed(() => {
  const total = documents.value.length
  const verified = approvedDocuments.value
  const hasResume = documents.value.some((doc) => doc.category === 'internship-application')
  const hasEnrollment = documents.value.some((doc) => doc.category === 'enrollment')
  return {
    resumeCompleteness: total === 0 ? 0 : Math.round((verified / total) * 100),
    missingReferences: !hasResume,
    portfolioQuality: total === 0 ? 0 : Math.min(100, 45 + verified * 15),
    needsRecentProjects: !hasEnrollment,
  }
})

const recentActivity = computed(() =>
  documents.value.slice(0, 3).map((doc) => ({
    id: doc.id,
    action: `${doc.name} uploaded`,
    timeAgo: doc.daysAgo,
  }))
)

const recommendations = computed(() => {
  const items: Array<{ id: number; text: string; type: string }> = []
  if (!documents.value.some((doc) => doc.category === 'internship-application')) {
    items.push({ id: 1, text: 'Upload your internship application form or resume so companies can review you faster.', type: 'resume' })
  }
  if (!documents.value.some((doc) => doc.category === 'enrollment')) {
    items.push({ id: 2, text: 'Add your enrollment proof so schools and companies can verify your current status.', type: 'certification' })
  }
  if (pendingDocuments.value > 0) {
    items.push({ id: 3, text: 'You have documents waiting for review. Keep file names clear so reviewers can process them faster.', type: 'portfolio' })
  }
  if (items.length === 0) {
    items.push({ id: 4, text: 'Your document set is in good shape. Keep recent files updated before applying to more internships.', type: 'portfolio' })
  }
  return items
})

function fileIconFor(document: Pick<DocumentRecord, 'fileName' | 'fileType'>) {
  const lowerType = (document.fileType || '').toLowerCase()
  const lowerName = document.fileName.toLowerCase()
  if (lowerType.includes('pdf') || lowerName.endsWith('.pdf')) return '/icons/icon-pdf.png'
  if (lowerType.includes('word') || lowerName.endsWith('.doc') || lowerName.endsWith('.docx')) return '/icons/icon-word.png'
  return '/icons/icon-docs.png'
}

function bgColorFor(category: string) {
  return category === 'internship-agreement' ? '#e0f2fe' : '#dbeafe'
}

function statusLabel(status: DocumentRecord['status']): DocumentCard['status'] {
  if (status === 'approved') return 'Verified'
  if (status === 'rejected') return 'Needs Update'
  return 'Pending Review'
}

function formatBytes(size?: number | null) {
  if (!size) return 'Unknown size'
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

function relativeTime(value?: string) {
  if (!value) return 'Recently'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Recently'
  const diffMs = Date.now() - date.getTime()
  const diffHours = Math.max(1, Math.floor(diffMs / (1000 * 60 * 60)))
  if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
  const diffWeeks = Math.floor(diffDays / 7)
  if (diffWeeks < 5) return `${diffWeeks} week${diffWeeks === 1 ? '' : 's'} ago`
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function mapDocument(document: DocumentRecord): DocumentCard {
  return {
    id: document.id,
    name: document.fileName,
    type: (document.fileName.split('.').pop() || document.fileType || 'FILE').toUpperCase(),
    size: formatBytes(document.fileSize),
    daysAgo: relativeTime(document.createdAt),
    status: statusLabel(document.status),
    icon: fileIconFor(document),
    bgColor: bgColorFor(document.category),
    usedIn: document.category === 'internship-application' ? 'Used for applications' : 'Stored in your records',
    viewedTimes: document.status === 'approved' ? 'Verified' : 'Awaiting review',
    category: document.category,
    fileUrl: document.fileUrl,
  }
}

async function loadDocuments() {
  loadingDocuments.value = true
  documentsError.value = ''
  try {
    const items = await listDocuments()
    documents.value = items.map(mapDocument)
  } catch (error) {
    documents.value = []
    documentsError.value = error instanceof Error ? error.message : 'Could not load documents.'
  } finally {
    loadingDocuments.value = false
  }
}

onMounted(() => {
  loadDocuments()
})

function searchDocuments() {
  // No-op, v-model handles filtering
}

function uploadDocument() {
  showUploadModal.value = true
  uploadStep.value = 1
}

function closeModal() {
  showUploadModal.value = false
  selectedDocumentType.value = ''
  uploadStep.value = 1
  documentName.value = ''
  selectedFile.value = null
}

function selectDocumentType(typeId: string) {
  selectedDocumentType.value = typeId
}

function proceedToUpload() {
  if (selectedDocumentType.value) {
    uploadStep.value = 2
    // Set default document name based on selected type
    const selectedType = documentTypes.value.find(type => type.id === selectedDocumentType.value)
    if (selectedType) {
      documentName.value = selectedType.name
    }
  }
}

function goBackToStep1() {
  uploadStep.value = 1
}

function handleFileSelect(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0]
  }
}

function handleFileDrop(event: DragEvent) {
  event.preventDefault()
  if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
    selectedFile.value = event.dataTransfer.files[0]
  }
}

function handleDragOver(event: DragEvent) {
  event.preventDefault()
}

function triggerFileInput() {
  const fileInput = document.getElementById('file-input') as HTMLInputElement
  fileInput?.click()
}

async function uploadSingleDocument(file: File, category: string, uploadedDocumentName: string, closeAfterUpload = false) {
  uploadingDocument.value = true
  try {
    await uploadDocuments({
      files: [file],
      category,
    })
    if (closeAfterUpload) {
      closeModal()
    }
    await loadDocuments()
    await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Document Uploaded Successfully!',
      text: `${uploadedDocumentName} has been uploaded and is now being processed.`,
      confirmButtonText: 'Continue',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Upload Failed',
      text: error instanceof Error ? error.message : 'Could not upload the document.',
      confirmButtonColor: '#3b82f6',
    })
  } finally {
    uploadingDocument.value = false
  }
}

function triggerQuickSubmitInput() {
  const fileInput = document.getElementById('quick-submit-file-input') as HTMLInputElement
  fileInput?.click()
}

async function handleQuickSubmitSelect(event: Event) {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) return

  await uploadSingleDocument(file, 'other', file.name)
  target.value = ''
}

async function submitTemporaryDocuments() {
  seedingTemporaryDocuments.value = true
  try {
    const temporaryDocuments = [
      {
        category: 'internship-application',
        name: 'Temporary_Internship_Application.txt',
        content: [
          'Temporary internship application document',
          `Student: ${authStore.user?.displayName || authStore.user?.email || 'Student'}`,
          'This file was generated for temporary testing only.',
        ].join('\n'),
      },
      {
        category: 'enrollment',
        name: 'Temporary_Enrollment_Proof.txt',
        content: [
          'Temporary enrollment proof',
          `School: ${organizationName.value}`,
          'This file was generated for temporary testing only.',
        ].join('\n'),
      },
    ]

    for (const document of temporaryDocuments) {
      const file = new File([document.content], document.name, { type: 'text/plain' })
      await uploadDocuments({
        files: [file],
        category: document.category,
      })
    }

    await loadDocuments()
    await Swal.fire({
      icon: 'success',
      title: 'Temporary documents submitted',
      text: 'Test documents were added so you can continue checking the internship flow.',
      confirmButtonColor: '#3b82f6',
    })
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Temporary submit failed',
      text: error instanceof Error ? error.message : 'Could not create temporary documents.',
      confirmButtonColor: '#3b82f6',
    })
  } finally {
    seedingTemporaryDocuments.value = false
  }
}

async function confirmUpload() {
  if (documentName.value.trim() && selectedFile.value) {
    const fileToUpload = selectedFile.value
    const selectedCategory = selectedDocumentType.value || 'other'
    const uploadedDocumentName = documentName.value.trim()
    await uploadSingleDocument(fileToUpload, selectedCategory, uploadedDocumentName, true)
  }
}

function cancelUpload() {
  closeModal()
}
</script>

<template>
  <div class="documents-content">
    <!-- Header -->
    <div class="documents-header">
      <div class="header-left">
        <img src="/icons/icon-journal.png" alt="Documents" class="header-icon" />
        <h1 class="header-title">My Documents</h1>
      </div>
      <div class="header-right">
        <div class="notification-wrapper">
          <BellIcon class="notification-icon-bell" />
        </div>
        <div class="avatar" @click="handleAvatarClick" title="View Profile"><img v-if="userAvatarUrl" :src="userAvatarUrl" alt="Profile" class="avatar-image" /><span v-else>{{ userInitials }}</span></div>
      </div>
    </div>

    <div class="documents-main">
      <div class="controls-wrapper">
        <div class="search-container">
          <input 
            type="text" 
            placeholder="Search Documents..." 
            class="search-input"
            v-model="searchQuery"
          />
          <MagnifyingGlassIcon class="search-icon-svg" />
        </div>
        <button class="upload-btn" @click="uploadDocument">
          <DocumentTextIcon class="upload-btn-icon" />
          Document upload
        </button>
        <button class="quick-submit-btn" :disabled="uploadingDocument" @click="triggerQuickSubmitInput">
          {{ uploadingDocument ? 'Uploading...' : 'Quick Submit Test File' }}
        </button>
        <button class="temporary-docs-btn" :disabled="seedingTemporaryDocuments || uploadingDocument" @click="submitTemporaryDocuments">
          {{ seedingTemporaryDocuments ? 'Submitting Temp Docs...' : 'Submit Temporary Documents' }}
        </button>
        <input
          id="quick-submit-file-input"
          type="file"
          accept=".pdf,.doc,.docx"
          style="display: none"
          @change="handleQuickSubmitSelect"
        />

      </div>

    <div v-if="documentsError" class="documents-feedback error">{{ documentsError }}</div>
    <div v-else-if="loadingDocuments" class="documents-feedback">Loading your uploaded documents...</div>

    <div class="main-layout">
      <!-- Documents Grid -->
      <div class="documents-grid">
        <div v-if="!loadingDocuments && filteredDocuments.length === 0" class="empty-documents-card">
          <DocumentTextIcon class="empty-documents-icon" />
          <h3>No documents yet</h3>
          <p>Upload your internship requirements here so schools and companies can review them.</p>
        </div>
        <div v-for="doc in filteredDocuments" :key="doc.id" class="document-card">
          <div class="doc-header">
            <div class="doc-icon-container" :style="{ backgroundColor: doc.bgColor }">
              <img :src="doc.icon" :alt="doc.type" class="doc-icon" />
            </div>
            <div class="doc-status">
              <span 
                class="status-badge" 
                :class="{
                  'verified': doc.status === 'Verified',
                  'pending': doc.status === 'Pending Review',
                  'needs-update': doc.status === 'Needs Update'
                }"
              >
                <CheckIcon v-if="doc.status === 'Verified'" class="status-icon-svg" />
                <ClockIcon v-else-if="doc.status === 'Pending Review'" class="status-icon-svg" />
                <ExclamationTriangleIcon v-else class="status-icon-svg" />
                {{ doc.status }}
              </span>
            </div>
          </div>
          
          <div class="doc-content">
            <h3 class="doc-title">{{ doc.name }}</h3>
            <div class="doc-meta">
              <span class="doc-type">
                <DocumentTextIcon class="doc-meta-icon" />
                {{ doc.type }}
              </span>
              <span class="doc-size">{{ doc.size }}</span>
              <span class="doc-date">
                <ClockIcon class="doc-meta-icon" />
                {{ doc.daysAgo }}
              </span>
            </div>
            
            <div v-if="doc.portfolioNote" class="doc-note">
              <div class="note-header">
                <BriefcaseIcon class="note-icon-svg-small" />
                <span class="note-text">{{ doc.portfolioNote }}</span>
              </div>
              <p class="note-description">{{ doc.additionalInfo }}</p>
            </div>
            
            <div class="doc-stats">
              <div class="stat-item">
                <ClipboardDocumentListIcon class="stat-icon-svg" />
                <span class="stat-text">{{ doc.usedIn }}</span>
              </div>
              <div class="stat-item">
                <EyeIcon class="stat-icon-svg" />
                <span class="stat-text">{{ doc.viewedTimes }}</span>
              </div>
            </div>
            <div class="document-actions-row">
              <span class="document-category-label">{{ categoryNameMap[doc.category] || doc.category }}</span>
              <a :href="doc.fileUrl" target="_blank" rel="noopener" class="document-open-link">Open file</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Sidebar -->
      <div class="right-sidebar">
        <!-- Document Health Check -->
        <div class="health-check-card">
          <h3 class="sidebar-title">Document Health Check</h3>
          
          <div class="health-item">
            <div class="health-header">
              <span class="health-label">Resume Completeness</span>
              <span class="health-percentage">{{ healthCheck.resumeCompleteness }}%</span>
            </div>
            <div class="progress-bar">
              <div 
                class="progress-fill" 
                :style="{ width: healthCheck.resumeCompleteness + '%' }"
              ></div>
            </div>
          </div>
          
            <div class="health-item">
              <ExclamationTriangleIcon class="health-warning-icon" />
            <span class="health-warning">{{ healthCheck.missingReferences ? 'Resume or internship application form is still missing' : 'Resume requirement is covered' }}</span>
          </div>
          
          <div class="health-item">
            <div class="health-header">
              <span class="health-label">Portfolio Quality</span>
              <span class="health-percentage">{{ healthCheck.portfolioQuality }}%</span>
            </div>
            <div class="progress-bar">
              <div 
                class="progress-fill orange" 
                :style="{ width: healthCheck.portfolioQuality + '%' }"
              ></div>
            </div>
          </div>
          
          <div class="health-item">
            <ExclamationTriangleIcon class="health-warning-icon" />
            <span class="health-warning">{{ healthCheck.needsRecentProjects ? 'Enrollment proof is still missing' : 'Enrollment proof is uploaded' }}</span>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="activity-card">
          <h3 class="sidebar-title">Recent Activity</h3>
          <div class="activity-list">
            <div v-for="activity in recentActivity" :key="activity.id" class="activity-item">
              <p class="activity-text">{{ activity.action }}</p>
              <span class="activity-time">{{ activity.timeAgo }}</span>
            </div>
          </div>
        </div>

        <!-- Recommendations -->
        <div class="recommendations-card">
          <h3 class="sidebar-title">Recommendations</h3>
          <div class="recommendations-list">
            <div v-for="rec in recommendations" :key="rec.id" class="recommendation-item">
              <div class="rec-icon">
                <BriefcaseIcon v-if="rec.type === 'portfolio'" class="rec-icon-svg" />
                <TrophyIcon v-else-if="rec.type === 'certification'" class="rec-icon-svg" />
                <DocumentTextIcon v-else class="rec-icon-svg" />
              </div>
              <p class="rec-text">{{ rec.text }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->
    <div v-if="showUploadModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2 class="modal-title">Upload Document</h2>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>
        
        <div class="modal-body">
          <div class="step-indicator">
            
          </div>
          
          <!-- Step 1: Document Type Selection -->
          <div v-if="uploadStep === 1">
            <h3 class="question-title">What are you uploading?</h3>
            
            <div class="document-types-grid">
              <div 
                v-for="docType in documentTypes" 
                :key="docType.id"
                class="doc-type-card"
                :class="{ selected: selectedDocumentType === docType.id }"
                @click="selectDocumentType(docType.id)"
              >
                <div class="doc-type-icon" :style="{ backgroundColor: docType.bgColor }">
                  <img :src="docType.icon" :alt="docType.name" class="type-icon" />
                </div>
                <h4 class="doc-type-name">{{ docType.name }}</h4>
                <p class="doc-type-description">{{ docType.description }}</p>
              </div>
            </div>
            
            <div class="modal-note">
              <p class="note-text">
                <ClipboardDocumentListIcon class="note-icon-svg" />
                Are these documents not required? 
                <a href="#" class="note-link">Check what documents are required</a>
              </p>
            </div>
          </div>

          <!-- Step 2: File Upload -->
          <div v-else-if="uploadStep === 2">
            <h3 class="question-title">What are you uploading?</h3>
            
            <!-- File Upload Area -->
            <div 
              class="file-upload-area"
              @drop="handleFileDrop"
              @dragover="handleDragOver"
              @click="triggerFileInput"
            >
              <CloudArrowUpIcon class="upload-icon-svg" />
              <p class="upload-text">Tap to upload file or drag file here</p>
              <p class="upload-subtext">Supported formats: PDF, DOCX (Max 10MB)</p>
              <input 
                id="file-input"
                type="file" 
                accept=".pdf,.docx,.doc"
                style="display: none"
                @change="handleFileSelect"
              />
            </div>
            
            <!-- Selected File Display -->
            <div v-if="selectedFile" class="selected-file">
              <div class="file-info">
                <DocumentTextIcon class="file-icon-svg" />
                <div class="file-details">
                  <span class="file-name">{{ selectedFile.name }}</span>
                  <span class="file-size">{{ Math.round(selectedFile.size / 1024) }} KB</span>
                </div>
              </div>
            </div>
            
            <!-- Document Name Input -->
            <div class="form-group">
              <label class="form-label">Document name</label>
              <input 
                v-model="documentName"
                type="text" 
                class="form-input"
                placeholder="Enter document name..."
              />
            </div>
          </div>
        </div>
        
        <div class="modal-footer">
          <button v-if="uploadStep === 2" class="back-btn" @click="goBackToStep1">← Back</button>
          <button class="cancel-btn" @click="cancelUpload">Cancel</button>
          <button 
            v-if="uploadStep === 1"
            class="next-btn" 
            :disabled="!selectedDocumentType"
            @click="proceedToUpload"
          >
            Next →
          </button>
          <button 
            v-else-if="uploadStep === 2"
            class="confirm-btn" 
            :disabled="!selectedFile || !documentName.trim() || uploadingDocument"
            @click="confirmUpload"
          >
            {{ uploadingDocument ? 'Uploading...' : 'Confirm Upload' }}
          </button>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

<style scoped>
.documents-content {
  padding: 0;
  background: #f8fafc;
  min-height: 100vh;
}

.documents-main {
  padding: 24px;
}

.controls-wrapper {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.documents-feedback {
  margin-bottom: 16px;
  padding: 12px 16px;
  border-radius: 12px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 14px;
  font-weight: 600;
}

.documents-feedback.error {
  background: #fef2f2;
  color: #b91c1c;
}

/* Header */
.documents-header {
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

.search-container {
  position: relative;
}

.search-input {
  padding: 8px 40px 8px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  width: 200px;
  outline: none;
}

.search-input:focus {
  border-color: #3b82f6;
}

.search-icon-svg {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  color: #6b7280;
}

.upload-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.upload-btn-icon {
  width: 18px;
  height: 18px;
}

.upload-btn:hover {
  background: #2563eb;
}

.quick-submit-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #e0f2fe;
  color: #0f766e;
  border: 1px solid #99f6e4;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.quick-submit-btn:hover:not(:disabled) {
  background: #ccfbf1;
}

.quick-submit-btn:disabled {
  opacity: 0.7;
  cursor: wait;
}

.temporary-docs-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.temporary-docs-btn:hover:not(:disabled) {
  background: #fde68a;
}

.temporary-docs-btn:disabled {
  opacity: 0.7;
  cursor: wait;
}

.status-icon-svg {
  width: 14px;
  height: 14px;
  stroke-width: 3;
}

.doc-meta-icon {
  width: 14px;
  height: 14px;
  display: inline-block;
  vertical-align: middle;
  margin-right: 4px;
}

.stat-icon-svg {
  width: 16px;
  height: 16px;
  color: #6b7280;
  flex-shrink: 0;
}

.health-warning-icon {
  width: 16px;
  height: 16px;
  color: #f59e0b;
  flex-shrink: 0;
}

.note-icon-svg {
  width: 16px;
  height: 16px;
  display: inline-block;
  vertical-align: middle;
  margin-right: 4px;
  color: #3b82f6;
}

.note-icon-svg-small {
  width: 16px;
  height: 16px;
  color: #f59e0b;
  flex-shrink: 0;
}

.file-icon-svg {
  width: 24px;
  height: 24px;
  color: #3b82f6;
  flex-shrink: 0;
}

.rec-icon-svg {
  width: 20px;
  height: 20px;
  color: #3b82f6;
}

.upload-icon-svg {
  width: 48px;
  height: 48px;
  color: #3b82f6;
  margin: 0 auto 12px;
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

/* Main Layout */
.main-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 24px;
}

/* Documents Grid */
.documents-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.empty-documents-card {
  grid-column: 1 / -1;
  background: white;
  border: 1px dashed #bfdbfe;
  border-radius: 12px;
  padding: 32px 24px;
  text-align: center;
  color: #475569;
}

.empty-documents-card h3 {
  margin: 12px 0 8px;
  color: #0f172a;
}

.empty-documents-card p {
  margin: 0;
}

.empty-documents-icon {
  width: 40px;
  height: 40px;
  color: #2563eb;
}

.document-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s, box-shadow 0.2s;
}

.document-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.doc-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 16px;
}

.doc-icon-container {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.doc-icon {
  width: 32px;
  height: 32px;
  object-fit: contain;
}

.doc-status {
  flex-shrink: 0;
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.status-badge.verified {
  background: #dcfce7;
  color: #16a34a;
}

.status-badge.pending {
  background: #fef3c7;
  color: #d97706;
}

.status-badge.needs-update {
  background: #fee2e2;
  color: #dc2626;
}

.status-icon {
  font-size: 10px;
}

.doc-content {
  flex: 1;
}

.doc-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 12px 0;
  line-height: 1.4;
}

.doc-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 12px;
}

.doc-type,
.doc-size,
.doc-date {
  font-size: 12px;
  color: #6b7280;
  display: flex;
  align-items: center;
  gap: 4px;
}

.doc-note {
  background: #f0f9ff;
  border: 1px solid #e0f2fe;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 12px;
}

.note-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}

.note-icon {
  font-size: 14px;
}

.note-text {
  font-size: 13px;
  font-weight: 500;
  color: #0369a1;
}

.note-description {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
  line-height: 1.4;
}

.doc-stats {
  display: flex;
  justify-content: space-between;
  padding-top: 12px;
  border-top: 1px solid #f3f4f6;
}

.document-actions-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 14px;
}

.document-category-label {
  display: inline-flex;
  align-items: center;
  padding: 6px 10px;
  border-radius: 999px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 12px;
  font-weight: 700;
}

.document-open-link {
  color: #2563eb;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
}

.document-open-link:hover {
  text-decoration: underline;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.stat-icon {
  font-size: 12px;
}

.stat-text {
  font-size: 12px;
  color: #6b7280;
}

/* Right Sidebar */
.right-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.health-check-card,
.activity-card,
.recommendations-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.sidebar-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 16px 0;
}

/* Health Check */
.health-item {
  margin-bottom: 16px;
}

.health-item:last-child {
  margin-bottom: 0;
}

.health-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.health-label {
  font-size: 14px;
  color: #374151;
}

.health-percentage {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.progress-bar {
  height: 6px;
  background: #e5e7eb;
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #3b82f6;
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progress-fill.orange {
  background: #f59e0b;
}

.health-warning {
  font-size: 13px;
  color: #d97706;
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Recent Activity */
.activity-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-item {
  padding-bottom: 12px;
  border-bottom: 1px solid #f3f4f6;
}

.activity-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.activity-text {
  font-size: 13px;
  color: #374151;
  margin: 0 0 4px 0;
  line-height: 1.4;
}

.activity-time {
  font-size: 12px;
  color: #9ca3af;
}

/* Recommendations */
.recommendations-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.recommendation-item {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.rec-icon {
  width: 24px;
  height: 24px;
  background: #f0f9ff;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
}

.rec-text {
  font-size: 13px;
  color: #374151;
  margin: 0;
  line-height: 1.4;
}

/* Responsive */
@media (max-width: 1200px) {
  .main-layout {
    grid-template-columns: 1fr;
  }
  
  .right-sidebar {
    order: -1;
  }
}

@media (max-width: 768px) {
  .documents-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .header-right {
    width: 100%;
    justify-content: space-between;
  }
  
  .search-input {
    width: 150px;
  }
  
  .documents-grid {
    grid-template-columns: 1fr;
  }
  
  .doc-meta {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 8px;
  }
  
  .doc-stats {
    flex-direction: column;
    gap: 8px;
  }
}

/* Upload Modal */
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
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 24px 0 24px;
  border-bottom: 1px solid #f3f4f6;
  margin-bottom: 24px;
}

.modal-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
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

.step-indicator {
  text-align: center;
  margin-bottom: 24px;
}

.step-text {
  font-size: 14px;
  color: #6b7280;
  background: #f3f4f6;
  padding: 4px 12px;
  border-radius: 12px;
}

.question-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  text-align: center;
  margin: 0 0 32px 0;
}

.document-types-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.doc-type-card {
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px 16px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: white;
}

.doc-type-card:hover {
  border-color: #3b82f6;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.doc-type-card.selected {
  border-color: #3b82f6;
  background: #f0f9ff;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.doc-type-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px auto;
}

.type-icon {
  width: 24px;
  height: 24px;
  object-fit: contain;
}

.doc-type-name {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
  line-height: 1.3;
}

.doc-type-description {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
  line-height: 1.4;
}

.modal-note {
  background: #f0f9ff;
  border: 1px solid #e0f2fe;
  border-radius: 8px;
  padding: 16px;
  text-align: center;
}

.note-text {
  font-size: 14px;
  color: #374151;
  margin: 0;
}

.note-link {
  color: #3b82f6;
  text-decoration: none;
  font-weight: 500;
}

.note-link:hover {
  text-decoration: underline;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 24px;
  border-top: 1px solid #f3f4f6;
  background: #f9fafb;
  border-radius: 0 0 16px 16px;
}

.cancel-btn {
  padding: 8px 16px;
  border: 1px solid #e5e7eb;
  background: white;
  color: #374151;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.cancel-btn:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.next-btn {
  padding: 8px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 4px;
}

.next-btn:hover:not(:disabled) {
  background: #2563eb;
}

.next-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

/* Step 2 Styles */
.file-upload-area {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  margin-bottom: 24px;
  background: #f9fafb;
}

.file-upload-area:hover {
  border-color: #3b82f6;
  background: #f0f9ff;
}

.upload-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.upload-text {
  font-size: 16px;
  font-weight: 500;
  color: #374151;
  margin: 0 0 8px 0;
}

.upload-subtext {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.selected-file {
  background: #f0f9ff;
  border: 1px solid #e0f2fe;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 24px;
}

.file-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.file-icon {
  font-size: 24px;
}

.file-details {
  display: flex;
  flex-direction: column;
}

.file-name {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.file-size {
  font-size: 12px;
  color: #6b7280;
}

.form-group {
  margin-bottom: 24px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}

.form-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.back-btn {
  padding: 8px 16px;
  border: 1px solid #e5e7eb;
  background: white;
  color: #374151;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 4px;
}

.back-btn:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.confirm-btn {
  padding: 8px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.confirm-btn:hover:not(:disabled) {
  background: #2563eb;
}

.confirm-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

/* Modal Responsive */
@media (max-width: 640px) {
  .modal-content {
    width: 95%;
    margin: 20px;
  }
  
  .document-types-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
  }
  
  .doc-type-card {
    padding: 16px 12px;
  }
  
  .doc-type-icon {
    width: 40px;
    height: 40px;
  }
  
  .type-icon {
    width: 20px;
    height: 20px;
  }
  
  .modal-footer {
    flex-direction: column;
  }
  
  .cancel-btn,
  .next-btn {
    width: 100%;
    justify-content: center;
  }
}
</style>

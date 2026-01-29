<script setup lang="ts">
import { ref } from 'vue'
import Swal from 'sweetalert2'

// Documents data matching the screenshot
const documents = ref([
  {
    id: 1,
    name: 'Internship Application Form',
    type: 'PDF',
    size: '245 KB',
    daysAgo: '2 days ago',
    status: 'Verified',
    statusColor: '#10b981',
    icon: '/icons/icon-pdf.png',
    bgColor: '#dbeafe',
    usedIn: '5 applications',
    viewedTimes: '6 times',
    needsUpdate: false
  },
  {
    id: 2,
    name: 'Enrollment',
    type: 'DOCX',
    size: '178 KB',
    daysAgo: '5 days ago',
    status: 'Pending Review',
    statusColor: '#f59e0b',
    icon: '/icons/icon-word.png',
    bgColor: '#dbeafe',
    usedIn: '2 applications',
    viewedTimes: '5 times',
    needsUpdate: false
  },
  {
    id: 3,
    name: 'Registration Form',
    type: 'PDF',
    size: '89 KB',
    daysAgo: '1 week ago',
    status: 'Needs Update',
    statusColor: '#ef4444',
    icon: '/icons/icon-pdf.png',
    bgColor: '#dbeafe',
    portfolioNote: 'Portfolio is outdated',
    additionalInfo: 'Add recent projects from this semester',
    usedIn: '5 applications',
    viewedTimes: '24 times'
  },
  {
    id: 4,
    name: 'Internship Agreement',
    type: 'PDF',
    size: '101 KB',
    daysAgo: '1 week ago',
    status: 'Verified',
    statusColor: '#10b981',
    icon: '/icons/icon-docs.png',
    bgColor: '#e0f2fe',
    usedIn: '1 applications',
    viewedTimes: '5 times',
    needsUpdate: false
  },
  {
    id: 5,
    name: 'Performance Evaluation',
    type: 'PDF',
    size: '200 KB',
    daysAgo: '1 month ago',
    status: 'Verified',
    statusColor: '#10b981',
    icon: '/icons/icon-docs.png',
    bgColor: '#dbeafe',
    usedIn: '5 applications',
    viewedTimes: '18 times',
    needsUpdate: false
  },
  {
    id: 6,
    name: 'Certificate',
    type: 'DOCX',
    size: '150 KB',
    daysAgo: '1 week ago',
    status: 'Verified',
    statusColor: '#10b981',
    icon: '/icons/icon-word.png',
    bgColor: '#dbeafe',
    usedIn: '1 applications',
    viewedTimes: '3 times',
    needsUpdate: false
  }
])

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

// Document health check data
const healthCheck = ref({
  resumeCompleteness: 85,
  missingReferences: true,
  portfolioQuality: 72,
  needsRecentProjects: true
})

// Recent activity data
const recentActivity = ref([
  {
    id: 1,
    action: 'Cover_Letter_TechCorp.pdf downloaded by coordinator',
    timeAgo: '2 hours ago'
  },
  {
    id: 2,
    action: 'Resume viewed in application #2847',
    timeAgo: '1 day ago'
  },
  {
    id: 3,
    action: 'AWS_Certification.pdf verified by system',
    timeAgo: '3 days ago'
  }
])

// Recommendations data
const recommendations = ref([
  {
    id: 1,
    text: 'Update your portfolio with recent projects from this semester',
    type: 'portfolio'
  },
  {
    id: 2,
    text: 'Add certifications earned this semester to boost your profile',
    type: 'certification'
  },
  {
    id: 3,
    text: 'Your resume formatting could be improved for better ATS compatibility',
    type: 'resume'
  }
])

function searchDocuments() {
  console.log('Search documents')
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

async function confirmUpload() {
  if (documentName.value.trim() && selectedFile.value) {
    // Close modal first
    closeModal()
    
    // Show success alert
    await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Document Uploaded Successfully!',
      text: `${documentName.value} has been uploaded and is now being processed.`,
      confirmButtonText: 'Continue',
      confirmButtonColor: '#3b82f6',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
    
    // Here you would typically add the document to the documents list
    console.log('Document uploaded:', {
      name: documentName.value,
      file: selectedFile.value,
      type: selectedDocumentType.value
    })
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
        <button @click="$emit('openMessages')" class="message-icon-btn">
          <img src="/icons/icon-message.png" alt="Messages" class="message-icon" />
        </button>
        <div class="search-container">
          <input 
            type="text" 
            placeholder="Search Documents..." 
            class="search-input"
            @input="searchDocuments"
          />
          <span class="search-icon">🔍</span>
        </div>
        <button class="upload-btn" @click="uploadDocument">
          📄 Document upload
        </button>
        <div class="status-indicators">
          <div class="status-item">
            <div class="status-dot red"></div>
          </div>
          <div class="status-item">
            <div class="status-dot green"></div>
            <span class="status-text">Active</span>
          </div>
          <div class="ai-badge">AI</div>
        </div>
      </div>
    </div>

    <div class="main-layout">
      <!-- Documents Grid -->
      <div class="documents-grid">
        <div v-for="doc in documents" :key="doc.id" class="document-card">
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
                <span v-if="doc.status === 'Verified'" class="status-icon">✓</span>
                <span v-else-if="doc.status === 'Pending Review'" class="status-icon">⏳</span>
                <span v-else class="status-icon">⚠️</span>
                {{ doc.status }}
              </span>
            </div>
          </div>
          
          <div class="doc-content">
            <h3 class="doc-title">{{ doc.name }}</h3>
            <div class="doc-meta">
              <span class="doc-type">📄 {{ doc.type }}</span>
              <span class="doc-size">{{ doc.size }}</span>
              <span class="doc-date">⏰ {{ doc.daysAgo }}</span>
            </div>
            
            <div v-if="doc.portfolioNote" class="doc-note">
              <div class="note-header">
                <span class="note-icon">💼</span>
                <span class="note-text">{{ doc.portfolioNote }}</span>
              </div>
              <p class="note-description">{{ doc.additionalInfo }}</p>
            </div>
            
            <div class="doc-stats">
              <div class="stat-item">
                <span class="stat-icon">📋</span>
                <span class="stat-text">Used in {{ doc.usedIn }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-icon">👁️</span>
                <span class="stat-text">Viewed {{ doc.viewedTimes }}</span>
              </div>
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
            <span class="health-warning">⚠️ Missing References section</span>
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
            <span class="health-warning">⚠️ Needs Recent projects</span>
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
                <span v-if="rec.type === 'portfolio'">💼</span>
                <span v-else-if="rec.type === 'certification'">🏆</span>
                <span v-else>📄</span>
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
                📋 Are these documents not required? 
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
              <div class="upload-icon">☁️</div>
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
                <span class="file-icon">📄</span>
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
            :disabled="!selectedFile || !documentName.trim()"
            @click="confirmUpload"
          >
            Confirm Upload
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.documents-content {
  padding: 24px;
  background: #f8fafc;
  min-height: 100vh;
}

/* Header */
.documents-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  background: white;
  padding: 16px 24px;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  width: 24px;
  height: 24px;
  filter: hue-rotate(220deg) saturate(2);
}

.header-title {
  font-size: 20px;
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
}

.message-icon-btn:hover {
  background: rgba(59, 130, 246, 0.1);
}

.message-icon {
  width: 20px;
  height: 20px;
  filter: hue-rotate(220deg) saturate(2);
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

.search-icon {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
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

.upload-btn:hover {
  background: #2563eb;
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
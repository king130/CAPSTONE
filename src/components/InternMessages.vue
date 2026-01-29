<script setup lang="ts">
import { ref } from 'vue'

// Messages data - using the same design as the document upload complete view
const messagesData = ref({
  progress: 75,
  completedSteps: [
    { name: 'Personal Information', completed: true },
    { name: 'Documents', completed: true }
  ],
  nextSteps: [
    {
      number: 1,
      title: 'Review your application',
      description: 'Double-check all info before submitting'
    },
    {
      number: 2,
      title: 'Preview employer view',
      description: 'See how employers will see your profile'
    },
    {
      number: 3,
      title: 'Submit your application',
      description: 'Complete and send your application'
    }
  ],
  summary: {
    filesUploaded: 4,
    totalSize: '6.2 MB'
  },
  documents: [
    {
      name: 'Resume/CV',
      filename: 'JohnDoe_Resume.pdf',
      status: 'uploaded'
    },
    {
      name: 'Cover Letter',
      filename: 'JohnDoe_CoverLetter.pdf',
      status: 'uploaded'
    },
    {
      name: 'Portfolio/Work Samples',
      filename: 'Portfolio_3_files.zip',
      status: 'uploaded'
    },
    {
      name: 'References Document',
      filename: 'Professional_References.pdf',
      status: 'uploaded'
    }
  ]
})

function handlePreviousStep() {
  console.log('Previous step clicked')
}

function handleApplyNow() {
  console.log('Apply now clicked')
}
</script>

<template>
  <div class="messages-content">
    <!-- Header -->
    <div class="messages-header">
      <div class="header-left">
        <img src="/icons/logo-main.png" alt="Logo" class="app-logo" />
      </div>
      <div class="header-right">
        <div class="notification-icon">🔔</div>
        <span class="company-name">Acme Corp. Company</span>
        <div class="user-avatar">AC</div>
      </div>
    </div>

    <!-- Success Banner -->
    <div class="success-banner">
      <div class="success-icon">✓</div>
      <div class="success-content">
        <h2 class="success-title">All required documents uploaded!</h2>
        <p class="success-subtitle">You can now proceed to review your application</p>
      </div>
    </div>

    <div class="messages-body">
      <div class="messages-grid">
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
                <span class="checklist-icon">📋</span>
                <h3 class="checklist-title">Document Checklist Complete</h3>
              </div>
              <p class="checklist-subtitle">You have completed 4 of 4 required documents. 1 optional document uploaded.</p>
              
              <div class="document-status-list">
                <div class="status-item">
                  <div class="status-icon completed">✓</div>
                  <span class="status-name">Resume/CV</span>
                  <span class="status-label uploaded">Uploaded</span>
                </div>
                <div class="status-item">
                  <div class="status-icon completed">✓</div>
                  <span class="status-name">Cover Letter</span>
                  <span class="status-label uploaded">Uploaded</span>
                </div>
                <div class="status-item">
                  <div class="status-icon completed">✓</div>
                  <span class="status-name">Portfolio/Work Samples</span>
                  <span class="status-label optional">Optional</span>
                </div>
                <div class="status-item">
                  <div class="status-icon completed">✓</div>
                  <span class="status-name">References Document</span>
                  <span class="status-label uploaded">Uploaded</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Uploaded Documents -->
          <div class="uploaded-documents">
            <div v-for="doc in messagesData.documents" :key="doc.name" class="document-item">
              <div class="doc-icon">✓</div>
              <div class="doc-info">
                <span class="doc-name">{{ doc.name }}</span>
                <span class="doc-filename">{{ doc.filename }}</span>
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
              <div class="circle-progress" :style="{ '--progress': messagesData.progress }">
                <span class="progress-number">{{ messagesData.progress }}%</span>
              </div>
            </div>
            <div class="progress-steps">
              <div v-for="step in messagesData.completedSteps" :key="step.name" class="progress-step completed">
                <div class="step-icon">✓</div>
                <span class="step-text">{{ step.name }}</span>
              </div>
            </div>
          </div>

          <!-- What's Next -->
          <div class="next-steps-card">
            <h3 class="next-title">What's Next?</h3>
            <div class="next-steps">
              <div v-for="step in messagesData.nextSteps" :key="step.number" class="next-step">
                <span class="step-number">{{ step.number }}</span>
                <div class="step-content">
                  <h4 class="step-title">{{ step.title }}</h4>
                  <p class="step-description">{{ step.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Summary -->
          <div class="summary-card">
            <h3 class="summary-title">Summary</h3>
            <div class="summary-item">
              <span class="summary-label">Files uploaded</span>
              <span class="summary-value">{{ messagesData.summary.filesUploaded }}</span>
            </div>
            <div class="summary-item">
              <span class="summary-label">Total size</span>
              <span class="summary-value">{{ messagesData.summary.totalSize }}</span>
            </div>
            <div class="summary-status">
              <div class="status-icon-small">✓</div>
              <span class="status-text">All requirements met</span>
            </div>
          </div>

          <!-- Need Help -->
          <div class="help-card">
            <h3 class="help-title">Need Help?</h3>
            <div class="help-items">
              <div class="help-item">
                <div class="help-icon">📖</div>
                <span class="help-text">Application guide</span>
              </div>
              <div class="help-item">
                <div class="help-icon">🎧</div>
                <span class="help-text">Contact support</span>
              </div>
              <div class="help-item">
                <div class="help-icon">❓</div>
                <span class="help-text">FAQs</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="messages-footer">
      <button @click="handlePreviousStep" class="footer-btn secondary">← Previous: Personal Info</button>
      <button @click="handleApplyNow" class="footer-btn primary">Apply Now</button>
    </div>
  </div>
</template>

<style scoped>
.messages-content {
  background: white;
  min-height: 100vh;
}

.messages-header {
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

.notification-icon {
  font-size: 16px;
  color: #6b7280;
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

.messages-body {
  padding: 0 24px 24px 24px;
}

.messages-grid {
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

.messages-footer {
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

/* Responsive */
@media (max-width: 1024px) {
  .messages-grid {
    grid-template-columns: 1fr;
  }
  
  .right-section {
    order: -1;
  }
}

@media (max-width: 768px) {
  .messages-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .messages-body {
    padding: 16px;
  }
  
  .success-banner {
    margin: 16px;
    flex-direction: column;
    text-align: center;
  }
  
  .messages-footer {
    flex-direction: column;
    gap: 12px;
  }
  
  .footer-btn {
    width: 100%;
  }
  
  .add-documents {
    flex-direction: column;
    text-align: center;
  }
}
</style>
<template>
  <div class="logo-setup-container">
    <div class="setup-card">
      <div class="setup-header">
        <div class="company-icon">
          <BuildingOfficeIcon class="icon" />
        </div>
        <h1 class="setup-title">Welcome to Your Company Dashboard</h1>
        <p class="setup-subtitle">Let's set up your company logo to personalize your experience</p>
      </div>

      <div class="logo-selection-section">
        <h2 class="section-title">Choose Your Company Logo</h2>
        
        <!-- Predefined Logo Options -->
        <div class="logo-options">
          <h3 class="options-title">Select from Templates</h3>
          <div class="logo-grid">
            <div 
              v-for="logo in predefinedLogos" 
              :key="logo.id"
              class="logo-option"
              :class="{ active: selectedLogoType === 'predefined' && selectedLogoId === logo.id }"
              @click="selectPredefinedLogo(logo)"
            >
              <div class="logo-preview" :style="{ background: logo.gradient }">
                <span class="logo-text">{{ logo.text }}</span>
              </div>
              <span class="logo-name">{{ logo.name }}</span>
            </div>
          </div>
        </div>

        <!-- Custom Logo Upload -->
        <div class="upload-section">
          <h3 class="options-title">Or Upload Your Own</h3>
          <div class="upload-area" @click="triggerFileUpload" @dragover.prevent @drop.prevent="handleFileDrop">
            <input 
              ref="fileInput" 
              type="file" 
              accept="image/*" 
              @change="handleFileUpload" 
              style="display: none"
            />
            <div v-if="!uploadedLogo" class="upload-placeholder">
              <CloudArrowUpIcon class="upload-icon" />
              <p class="upload-text">Click to upload or drag and drop</p>
              <p class="upload-subtext">PNG, JPG, SVG up to 5MB</p>
            </div>
            <div v-else class="uploaded-preview">
              <img :src="uploadedLogo" alt="Uploaded logo" class="uploaded-image" />
              <button class="remove-upload" @click.stop="removeUpload">
                <XMarkIcon class="remove-icon" />
              </button>
            </div>
          </div>
        </div>

        <!-- Company Initials Option -->
        <div class="initials-section">
          <h3 class="options-title">Use Company Initials</h3>
          <div 
            class="initials-option"
            :class="{ active: selectedLogoType === 'initials' }"
            @click="selectInitials"
          >
            <div class="initials-preview">
              <span class="initials-text">{{ companyInitials }}</span>
            </div>
            <div class="initials-info">
              <span class="initials-name">{{ companyName }}</span>
              <span class="initials-desc">Auto-generated from company name</span>
            </div>
          </div>
        </div>
      </div>

      <div class="setup-actions">
        <button class="btn-skip" @click="skipSetup">Skip for Now</button>
        <button 
          class="btn-continue" 
          @click="saveAndContinue"
          :disabled="!hasSelection"
        >
          Continue to Dashboard
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { saveCompanyLogo, uploadLogoFile } from '@/services/companyLogo'
import { 
  BuildingOfficeIcon, 
  CloudArrowUpIcon, 
  XMarkIcon 
} from '@heroicons/vue/24/outline'
import Swal from 'sweetalert2'

const router = useRouter()
const authStore = useAuthStore()
const fileInput = ref<HTMLInputElement | null>(null)

const companyName = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.companyName as string) || 
         (profile?.name as string) || 
         authStore.user?.displayName || 
         'Company'
})

const companyInitials = computed(() => {
  return companyName.value
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

// Logo selection state
const selectedLogoType = ref<'predefined' | 'upload' | 'initials' | null>(null)
const selectedLogoId = ref<string | null>(null)
const uploadedLogo = ref<string | null>(null)

const hasSelection = computed(() => {
  return selectedLogoType.value !== null && (
    selectedLogoType.value === 'initials' ||
    (selectedLogoType.value === 'predefined' && selectedLogoId.value) ||
    (selectedLogoType.value === 'upload' && uploadedLogo.value)
  )
})

// Predefined logo templates
const predefinedLogos = computed(() => [
  {
    id: 'tech-blue',
    name: 'Tech Blue',
    text: companyInitials.value,
    gradient: 'linear-gradient(135deg, #2563eb, #1d4ed8)'
  },
  {
    id: 'corporate-gray',
    name: 'Corporate Gray',
    text: companyInitials.value,
    gradient: 'linear-gradient(135deg, #6b7280, #4b5563)'
  },
  {
    id: 'success-green',
    name: 'Success Green',
    text: companyInitials.value,
    gradient: 'linear-gradient(135deg, #10b981, #059669)'
  },
  {
    id: 'creative-purple',
    name: 'Creative Purple',
    text: companyInitials.value,
    gradient: 'linear-gradient(135deg, #8b5cf6, #7c3aed)'
  },
  {
    id: 'energy-orange',
    name: 'Energy Orange',
    text: companyInitials.value,
    gradient: 'linear-gradient(135deg, #f59e0b, #d97706)'
  },
  {
    id: 'professional-navy',
    name: 'Professional Navy',
    text: companyInitials.value,
    gradient: 'linear-gradient(135deg, #1e40af, #1e3a8a)'
  }
])

function selectPredefinedLogo(logo: any) {
  selectedLogoType.value = 'predefined'
  selectedLogoId.value = logo.id
  uploadedLogo.value = null
}

function selectInitials() {
  selectedLogoType.value = 'initials'
  selectedLogoId.value = null
  uploadedLogo.value = null
}

function triggerFileUpload() {
  fileInput.value?.click()
}

function handleFileUpload(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    processFile(file)
  }
}

function handleFileDrop(event: DragEvent) {
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    processFile(file)
  }
}

function processFile(file: File) {
  if (file.size > 5 * 1024 * 1024) {
    alert('File size must be less than 5MB')
    return
  }

  if (!file.type.startsWith('image/')) {
    alert('Please select an image file')
    return
  }

  const reader = new FileReader()
  reader.onload = (e) => {
    uploadedLogo.value = e.target?.result as string
    selectedLogoType.value = 'upload'
    selectedLogoId.value = null
  }
  reader.readAsDataURL(file)
}

function removeUpload() {
  uploadedLogo.value = null
  selectedLogoType.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

async function saveAndContinue() {
  if (!hasSelection.value) return

  const uid = authStore.user?.uid
  if (!uid) {
    await Swal.fire({
      icon: 'error',
      title: 'Authentication Error',
      text: 'Please log in again to continue.',
      confirmButtonColor: '#2563eb'
    })
    return
  }

  try {
    let logoUrl = uploadedLogo.value

    // If uploading a file, upload it to Firebase Storage first
    if (selectedLogoType.value === 'upload' && uploadedLogo.value && uploadedLogo.value.startsWith('data:')) {
      // Convert data URL back to file for upload
      const response = await fetch(uploadedLogo.value)
      const blob = await response.blob()
      const file = new File([blob], 'logo.png', { type: blob.type })
      
      // Show loading
      Swal.fire({
        title: 'Uploading Logo...',
        text: 'Please wait while we save your logo.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading()
        }
      })

      logoUrl = await uploadLogoFile(uid, file)
    }

    // Save logo selection to user profile
    const logoData = {
      type: selectedLogoType.value!,
      ...(selectedLogoType.value === 'predefined' && selectedLogoId.value && { logoId: selectedLogoId.value }),
      ...(selectedLogoType.value === 'upload' && logoUrl && { logoUrl }),
      logoSetupCompleted: true
    }

    await saveCompanyLogo(uid, logoData)

    // Update auth store with new profile data
    if (authStore.user?.profile) {
      authStore.user.profile = {
        ...authStore.user.profile,
        logoType: logoData.type,
        logoId: logoData.logoId,
        logoUrl: logoData.logoUrl,
        logoSetupCompleted: true
      }
    }

    await Swal.fire({
      icon: 'success',
      title: 'Logo Saved!',
      text: 'Your company logo has been set up successfully.',
      confirmButtonColor: '#2563eb'
    })

    // Navigate to main company dashboard
    router.push('/company')
  } catch (error) {
    console.error('Error saving logo:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Save Failed',
      text: 'Failed to save your logo. Please try again.',
      confirmButtonColor: '#2563eb'
    })
  }
}

async function skipSetup() {
  const uid = authStore.user?.uid
  if (!uid) return

  try {
    // Mark setup as completed but without logo (use initials as default)
    await saveCompanyLogo(uid, {
      type: 'initials',
      logoSetupCompleted: true
    })

    // Update auth store
    if (authStore.user?.profile) {
      authStore.user.profile = {
        ...authStore.user.profile,
        logoType: 'initials',
        logoSetupCompleted: true
      }
    }

    router.push('/company')
  } catch (error) {
    console.error('Error skipping setup:', error)
    router.push('/company')
  }
}

onMounted(() => {
  // Check if user has already completed logo setup
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  if (profile?.logoSetupCompleted) {
    router.push('/company')
  }
})
</script>

<style scoped>
.logo-setup-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.setup-card {
  background: white;
  border-radius: 24px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  max-width: 800px;
  width: 100%;
  padding: 40px;
}

.setup-header {
  text-align: center;
  margin-bottom: 40px;
}

.company-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px auto;
}

.company-icon .icon {
  width: 40px;
  height: 40px;
  color: white;
}

.setup-title {
  font-size: 32px;
  font-weight: 700;
  color: #1f2937;
  margin: 0 0 12px 0;
}

.setup-subtitle {
  font-size: 18px;
  color: #6b7280;
  margin: 0;
}

.logo-selection-section {
  margin-bottom: 40px;
}

.section-title {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 32px 0;
  text-align: center;
}

.options-title {
  font-size: 18px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 16px 0;
}

.logo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}

.logo-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.logo-option:hover {
  border-color: #2563eb;
  transform: translateY(-2px);
}

.logo-option.active {
  border-color: #2563eb;
  background: #eff6ff;
}

.logo-preview {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 8px;
}

.logo-text {
  color: white;
  font-size: 20px;
  font-weight: 700;
}

.logo-name {
  font-size: 14px;
  color: #6b7280;
  text-align: center;
}

.upload-section {
  margin-bottom: 32px;
}

.upload-area {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.upload-area:hover {
  border-color: #2563eb;
  background: #f8fafc;
}

.upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.upload-icon {
  width: 48px;
  height: 48px;
  color: #9ca3af;
  margin-bottom: 16px;
}

.upload-text {
  font-size: 16px;
  font-weight: 500;
  color: #374151;
  margin: 0 0 4px 0;
}

.upload-subtext {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.uploaded-preview {
  position: relative;
  display: inline-block;
}

.uploaded-image {
  max-width: 120px;
  max-height: 120px;
  border-radius: 8px;
  object-fit: contain;
}

.remove-upload {
  position: absolute;
  top: -8px;
  right: -8px;
  width: 24px;
  height: 24px;
  background: #ef4444;
  border: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.remove-icon {
  width: 14px;
  height: 14px;
  color: white;
}

.initials-section {
  margin-bottom: 32px;
}

.initials-option {
  display: flex;
  align-items: center;
  padding: 20px;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.initials-option:hover {
  border-color: #2563eb;
}

.initials-option.active {
  border-color: #2563eb;
  background: #eff6ff;
}

.initials-preview {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #6b7280, #4b5563);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 16px;
}

.initials-text {
  color: white;
  font-size: 20px;
  font-weight: 700;
}

.initials-info {
  display: flex;
  flex-direction: column;
}

.initials-name {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 4px;
}

.initials-desc {
  font-size: 14px;
  color: #6b7280;
}

.setup-actions {
  display: flex;
  gap: 16px;
  justify-content: center;
}

.btn-skip {
  padding: 12px 24px;
  background: transparent;
  color: #6b7280;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-skip:hover {
  background: #f9fafb;
  border-color: #d1d5db;
}

.btn-continue {
  padding: 12px 32px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-continue:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
}

.btn-continue:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .setup-card {
    padding: 24px;
    margin: 10px;
  }
  
  .setup-title {
    font-size: 24px;
  }
  
  .logo-grid {
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 12px;
  }
  
  .setup-actions {
    flex-direction: column;
  }
}
</style>
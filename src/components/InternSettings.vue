<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { apiFetch } from '@/services/http'
import { mapApiUserToProfile, updateCurrentUserPassword } from '@/services/auth'
import { buildProfileAvatarUrl, uploadProfileAvatar } from '@/services/profileMedia'
import Swal from 'sweetalert2'
import { BellIcon } from '@heroicons/vue/24/outline'

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

const headerAvatarUrl = computed(() => {
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

const loading = ref(true)
const saving = ref(false)
const hydrated = ref(false)

// Form data - Initialize with empty values
const personalInfo = ref({
  name: '',
  email: '',
  phoneNumber: ''
})

const security = ref({
  newPassword: '',
  confirmPassword: '',
  twoFactorAuth: false,
  loginAlerts: true
})

const fileInput = ref<HTMLInputElement | null>(null)
const uploadingPhoto = ref(false)
const localProfilePreview = ref('')

const profilePicture = computed(() => {
  if (localProfilePreview.value) {
    return localProfilePreview.value
  }

  const currentUser = authStore.user
  const profile = currentUser?.profile as Record<string, unknown> | undefined
  if (currentUser?.uid && profile?.avatarPath) {
    return buildProfileAvatarUrl(currentUser.uid, currentUser.updatedAt)
  }

  return '/icons/profiles/alex-doe.jpg'
})

watch(
  () => authStore.user,
  (user) => {
    if (!user) {
      loading.value = false
      hydrated.value = false
      return
    }
    if (hydrated.value) return

    const profile = user.profile as Record<string, unknown> | undefined
    personalInfo.value = {
      name: user.displayName || '',
      email: user.email || '',
      phoneNumber: (profile?.contactNumber as string) || (profile?.companyContactNumber as string) || (profile?.schoolContactNumber as string) || ''
    }
    
    // Load security settings from profile if available
    if (profile?.twoFactorAuth !== undefined) {
      security.value.twoFactorAuth = profile.twoFactorAuth as boolean
    }
    if (profile?.loginAlerts !== undefined) {
      security.value.loginAlerts = profile.loginAlerts as boolean
    }

    hydrated.value = true
    loading.value = false
  },
  { immediate: true }
)

// Functions
function updateProfilePicture() {
  fileInput.value?.click()
}

async function onProfilePictureSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    await Swal.fire({
      icon: 'error',
      title: 'Invalid File',
      text: 'Please choose an image file.',
      confirmButtonColor: '#3b82f6'
    })
    input.value = ''
    return
  }

  if (localProfilePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(localProfilePreview.value)
  }
  localProfilePreview.value = URL.createObjectURL(file)
  uploadingPhoto.value = true

  try {
    const updatedUser = await uploadProfileAvatar(file)
    authStore.setUserProfile(updatedUser)
    await Swal.fire({
      icon: 'success',
      title: 'Photo Updated',
      text: 'Your profile picture has been uploaded successfully.',
      confirmButtonColor: '#3b82f6',
      timer: 1800,
      showConfirmButton: false
    })
  } catch (error) {
    if (localProfilePreview.value.startsWith('blob:')) {
      URL.revokeObjectURL(localProfilePreview.value)
    }
    localProfilePreview.value = ''
    await Swal.fire({
      icon: 'error',
      title: 'Upload Failed',
      text: error instanceof Error ? error.message : 'Unable to upload your profile picture.',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    uploadingPhoto.value = false
    input.value = ''
  }
}

async function saveChanges() {
  if (!authStore.user?.uid) {
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'User not authenticated. Please log in again.',
      confirmButtonColor: '#3b82f6'
    })
    return
  }

  // Validate inputs
  if (!personalInfo.value.name.trim()) {
    await Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Name is required.',
      confirmButtonColor: '#3b82f6'
    })
    return
  }

  if (!personalInfo.value.email.trim()) {
    await Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Email is required.',
      confirmButtonColor: '#3b82f6'
    })
    return
  }

  if (security.value.newPassword || security.value.confirmPassword) {
    if (security.value.newPassword.length < 8) {
      await Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'New password must be at least 8 characters.',
        confirmButtonColor: '#3b82f6'
      })
      return
    }

    if (security.value.newPassword !== security.value.confirmPassword) {
      await Swal.fire({
        icon: 'error',
        title: 'Validation Error',
        text: 'New password and confirmation do not match.',
        confirmButtonColor: '#3b82f6'
      })
      return
    }
  }

  saving.value = true
  
  try {
    const raw = await apiFetch<Record<string, unknown>>('/profile', {
      method: 'PATCH',
      body: JSON.stringify({
        displayName: personalInfo.value.name,
        profile: {
          contactNumber: personalInfo.value.phoneNumber,
          twoFactorAuth: security.value.twoFactorAuth,
          loginAlerts: security.value.loginAlerts,
        },
      }),
    })
    authStore.setUserProfile(mapApiUserToProfile(raw))

    if (security.value.newPassword) {
      await updateCurrentUserPassword(security.value.newPassword)
      await authStore.refreshUser()
      security.value.newPassword = ''
      security.value.confirmPassword = ''
    }

    await Swal.fire({
      icon: 'success',
      title: 'Changes Saved!',
      text: 'Your profile has been updated successfully.',
      confirmButtonColor: '#3b82f6',
      timer: 2000,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Error saving changes:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Save Failed',
      text: error instanceof Error ? error.message : 'Unable to save changes. Please try again.',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    saving.value = false
  }
}

function handleImageError(event: Event) {
  if (localProfilePreview.value) {
    return
  }

  const img = event.target as HTMLImageElement
  const name = personalInfo.value.name || 'User'
  const initials = name.split(' ').map(n => n[0]).join('').toUpperCase()
  
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
</script>

<template>
  <div class="settings-content">
    <!-- Header -->
    <header class="settings-header">
      <div class="header-left">
        <img src="/icons/icon-settings.png" alt="Settings" class="header-icon" />
        <h1 class="header-title">Settings</h1>
      </div>
      <div class="header-right">
        <div class="notification-wrapper">
          <BellIcon class="notification-bell" />
        </div>
        <button class="user-avatar" type="button" @click="() => {}" :title="`View Profile`">
          <img v-if="headerAvatarUrl" :src="headerAvatarUrl" alt="Profile" class="user-avatar-image" />
          <span v-else>{{ userInitials }}</span>
        </button>
      </div>
    </header>

    <div class="settings-body">
      <div v-if="loading" class="loading-state">
        <div class="loading-spinner"></div>
        <p>Loading your profile...</p>
      </div>
      
      <div v-else class="settings-main">
        <!-- Profile Card -->
        <div class="profile-card">
          <div class="profile-picture-section">
            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              class="hidden-file-input"
              @change="onProfilePictureSelected"
            />
            <img 
              :src="profilePicture" 
              :alt="personalInfo.name" 
              class="profile-picture"
              @error="handleImageError"
            />
            <button @click="updateProfilePicture" class="update-picture-btn" :disabled="uploadingPhoto">
              {{ uploadingPhoto ? 'Uploading...' : 'Change Photo' }}
            </button>
          </div>
          <div class="profile-info">
            <h2 class="profile-name">{{ personalInfo.name || 'User' }}</h2>
            <p class="profile-email">{{ personalInfo.email }}</p>
          </div>
          <button class="save-changes-btn" @click="saveChanges" :disabled="saving || loading">
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
          <!-- Personal Information Card -->
          <div class="settings-card">
            <div class="card-header">
              <h3 class="card-title">Personal Information</h3>
              <p class="card-subtitle">Update your personal details</p>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Full Name</label>
                <input 
                  v-model="personalInfo.name"
                  type="text" 
                  class="form-input"
                  placeholder="Enter your full name"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input 
                  v-model="personalInfo.email"
                  type="email" 
                  class="form-input"
                  readonly
                />
                <p class="form-help">Email is managed from your account record and is not editable here yet.</p>
              </div>

              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input 
                  v-model="personalInfo.phoneNumber"
                  type="tel" 
                  class="form-input"
                  placeholder="Enter your phone number"
                />
              </div>
            </div>
          </div>

          <!-- Password Card -->
          <div class="settings-card">
            <div class="card-header">
              <h3 class="card-title">Change Password</h3>
              <p class="card-subtitle">Update your password regularly</p>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">New Password</label>
                <input 
                  v-model="security.newPassword"
                  type="password" 
                  class="form-input"
                  placeholder="Enter new password"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input 
                  v-model="security.confirmPassword"
                  type="password" 
                  class="form-input"
                  placeholder="Confirm new password"
                />
                <div v-if="security.newPassword" class="password-strength">
                  <div class="strength-bar">
                    <div class="strength-fill weak"></div>
                  </div>
                  <span class="strength-text">Use at least 8 characters for a stronger password</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Security Settings Card -->
          <div class="settings-card full-width">
            <div class="card-header">
              <h3 class="card-title">Security Settings</h3>
              <p class="card-subtitle">Manage your account security preferences</p>
            </div>
            <div class="card-body">
              <div class="security-option">
                <div class="option-info">
                  <h4 class="option-title">Two-Factor Authentication</h4>
                  <p class="option-description">Add an extra layer of security to your account</p>
                </div>
                <label class="toggle-switch">
                  <input 
                    v-model="security.twoFactorAuth"
                    type="checkbox" 
                    class="toggle-input"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>

              <div class="security-option">
                <div class="option-info">
                  <h4 class="option-title">Login Alert Notifications</h4>
                  <p class="option-description">Get notified when your account is accessed from a new device</p>
                </div>
                <label class="toggle-switch">
                  <input 
                    v-model="security.loginAlerts"
                    type="checkbox" 
                    class="toggle-input"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.settings-content {
  background: #f8fafc;
  min-height: 100vh;
}

/* Header */
.settings-header {
  background: #dbeafe;
  padding: 16px 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #bfdbfe;
}

.settings-header * {
  outline: none !important;
  box-shadow: none !important;
}

.settings-header *:focus {
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

.notification-bell {
  width: 24px;
  height: 24px;
  color: #6b7280;
  cursor: pointer;
  transition: color 0.2s;
}

.notification-bell:hover {
  color: #2563eb;
}

.user-avatar {
  width: 36px;
  height: 36px;
  background: #3b82f6;
  color: #fff;
  border-radius: 50%;
  overflow: hidden;
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

.user-avatar-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

/* Body */
.settings-body {
  padding: 24px 32px;
  max-width: 1200px;
  margin: 0 auto;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 16px;
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e5e7eb;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-state p {
  color: #6b7280;
  font-size: 14px;
}

/* Profile Card */
.profile-card {
  background: white;
  border-radius: 12px;
  padding: 32px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 24px;
}

.profile-picture-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.profile-picture {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #e5e7eb;
}

.hidden-file-input {
  display: none;
}

.update-picture-btn {
  padding: 8px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.update-picture-btn:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.profile-info {
  flex: 1;
}

.profile-name {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.profile-email {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.save-changes-btn {
  padding: 10px 24px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.save-changes-btn:hover:not(:disabled) {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.save-changes-btn:disabled {
  background: #93c5fd;
  cursor: not-allowed;
  opacity: 0.7;
}

/* Settings Grid */
.settings-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

.settings-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.settings-card.full-width {
  grid-column: 1 / -1;
}

.card-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f3f4f6;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.card-subtitle {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Form Elements */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.form-input {
  width: 100%;
  max-width: 100%;
  padding: 10px 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: all 0.2s;
  background: white;
  box-sizing: border-box;
}

.form-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input::placeholder {
  color: #9ca3af;
}

.form-help {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
}

/* Password Strength */
.password-strength {
  margin-top: 4px;
}

.strength-bar {
  width: 100%;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 6px;
}

.strength-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s ease;
}

.strength-fill.weak {
  width: 30%;
  background: #ef4444;
}

.strength-text {
  font-size: 12px;
  color: #6b7280;
}

/* Security Options */
.security-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid #f3f4f6;
}

.security-option:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.option-info {
  flex: 1;
  padding-right: 16px;
}

.option-title {
  font-size: 15px;
  font-weight: 500;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.option-description {
  font-size: 13px;
  color: #6b7280;
  margin: 0;
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 24px;
  flex-shrink: 0;
}

.toggle-input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #d1d5db;
  transition: 0.3s;
  border-radius: 24px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 20px;
  width: 20px;
  left: 2px;
  bottom: 2px;
  background-color: white;
  transition: 0.3s;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.toggle-input:checked + .toggle-slider {
  background-color: #3b82f6;
}

.toggle-input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

/* Responsive */
@media (max-width: 1024px) {
  .settings-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .settings-header {
    padding: 16px 24px;
  }
  
  .header-title {
    font-size: 20px;
  }
  
  .settings-body {
    padding: 16px;
  }
  
  .profile-card {
    flex-direction: column;
    text-align: center;
    padding: 24px;
  }
  
  .settings-card {
    padding: 20px;
  }
  
  .security-option {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .toggle-switch {
    align-self: flex-end;
  }
}
</style>

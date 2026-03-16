<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { doc, setDoc, updateDoc } from 'firebase/firestore'
import { db } from '@/services/firebase'
import { ensurePublicProfile } from '@/services/profilesPublic'
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

const loading = ref(true)
const saving = ref(false)
const hydrated = ref(false)

// Form data
const companyInfo = ref({
  name: '',
  email: '',
  phoneNumber: '',
  companyType: '',
  industryType: '',
  contactPersonName: '',
  contactPersonTitle: '',
  contactPersonEmail: ''
})

const addressInfo = ref({
  address: '',
  region: '',
  province: '',
  city: '',
  barangay: '',
  zipCode: ''
})

const courses = ref<string[]>([])
const newCourse = ref('')

const security = ref({
  oldPassword: '',
  newPassword: '',
  twoFactorAuth: false,
  loginAlerts: true
})

const profilePicture = ref('/icons/profiles/john-smith.jpg')

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
    companyInfo.value = {
      name: user.displayName || (profile?.companyName as string) || '',
      email: (profile?.companyEmail as string) || user.email || '',
      phoneNumber: (profile?.companyContactNumber as string) || '',
      companyType: (profile?.companyType as string) || '',
      industryType: (profile?.industryType as string) || '',
      contactPersonName: (profile?.contactPersonName as string) || '',
      contactPersonTitle: (profile?.contactPersonTitle as string) || '',
      contactPersonEmail: (profile?.contactPersonEmail as string) || ''
    }

    addressInfo.value = {
      address: (profile?.companyAddress as string) || '',
      region: (profile?.region as string) || '',
      province: (profile?.province as string) || '',
      city: (profile?.cityMunicipality as string) || '',
      barangay: (profile?.barangay as string) || '',
      zipCode: (profile?.zipCode as string) || ''
    }

    courses.value = Array.isArray(profile?.courses) ? (profile?.courses as string[]) : []

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

function updateProfilePicture() {
  Swal.fire({
    icon: 'info',
    title: 'Feature Under Development',
    text: 'Profile picture upload will be available soon.',
    confirmButtonText: 'OK',
    confirmButtonColor: '#3b82f6'
  })
}

function addCourse() {
  const value = newCourse.value.trim()
  if (!value) return
  const normalized = value.toUpperCase()
  if (!courses.value.some((c) => c.toUpperCase() === normalized)) {
    courses.value.push(value)
  }
  newCourse.value = ''
}

function removeCourse(course: string) {
  courses.value = courses.value.filter((c) => c !== course)
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

  if (!companyInfo.value.name.trim()) {
    await Swal.fire({
      icon: 'error',
      title: 'Validation Error',
      text: 'Company name is required.',
      confirmButtonColor: '#3b82f6'
    })
    return
  }

  saving.value = true
  
  try {
    const userRef = doc(db, 'users', authStore.user.uid)

    const updatePayload = {
      displayName: companyInfo.value.name,
      'profile.companyName': companyInfo.value.name,
      'profile.companyContactNumber': companyInfo.value.phoneNumber,
      'profile.companyEmail': companyInfo.value.email,
      'profile.companyType': companyInfo.value.companyType,
      'profile.industryType': companyInfo.value.industryType,
      'profile.contactPersonName': companyInfo.value.contactPersonName,
      'profile.contactPersonTitle': companyInfo.value.contactPersonTitle,
      'profile.contactPersonEmail': companyInfo.value.contactPersonEmail,
      'profile.courses': courses.value.map((c) => c.trim()).filter(Boolean),
      'profile.companyAddress': addressInfo.value.address,
      'profile.region': addressInfo.value.region,
      'profile.province': addressInfo.value.province,
      'profile.cityMunicipality': addressInfo.value.city,
      'profile.barangay': addressInfo.value.barangay,
      'profile.zipCode': addressInfo.value.zipCode,
      'profile.twoFactorAuth': security.value.twoFactorAuth,
      'profile.loginAlerts': security.value.loginAlerts,
      updatedAt: new Date()
    } as const

    try {
      await updateDoc(userRef, updatePayload)
    } catch (e) {
      const code = (e as { code?: string })?.code || ''
      if (code === 'not-found') {
        await setDoc(userRef, updatePayload, { merge: true })
      } else {
        throw e
      }
    }

    let publicProfileFailed = false
    try {
      await ensurePublicProfile(authStore.user.uid, {
        displayName: companyInfo.value.name,
        role: 'company',
        orgName: companyInfo.value.name,
        email: companyInfo.value.email || authStore.user?.email,
        courses: courses.value.map((c) => c.trim()).filter(Boolean),
      })
    } catch (e) {
      publicProfileFailed = true
      console.warn('ensurePublicProfile failed (company):', e)
    }

    if (publicProfileFailed) {
      await Swal.fire({
        icon: 'warning',
        title: 'Saved (Directory Update Failed)',
        text: 'Your profile was saved, but the public company directory could not be updated. Schools may not see your company/courses until this succeeds.',
        confirmButtonColor: '#3b82f6',
      })
    } else {
      await Swal.fire({
        icon: 'success',
        title: 'Changes Saved!',
        text: 'Your profile has been updated successfully.',
        confirmButtonColor: '#3b82f6',
        timer: 2000,
        showConfirmButton: false
      })
    }
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
  const img = event.target as HTMLImageElement
  const name = companyInfo.value.name || 'Company'
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
          <BellIcon class="notification-bell" @click="toggleNotifications" />
          
          <!-- Notification Dropdown -->
          <div v-if="showNotifications" class="notification-dropdown">
            <div class="notification-header">
              <h3>Notifications</h3>
              <span class="notification-count">{{ notifications.filter(n => n.unread).length }} new</span>
            </div>
            <div class="notification-list">
              <div 
                v-for="notification in notifications" 
                :key="notification.id" 
                class="notification-item"
                :class="{ 'unread': notification.unread }"
              >
                <div class="notification-content">
                  <h4>{{ notification.title }}</h4>
                  <p>{{ notification.message }}</p>
                  <span class="notification-time">{{ notification.time }}</span>
                </div>
                <div v-if="notification.unread" class="unread-dot"></div>
              </div>
            </div>
            <div class="notification-footer">
              <button class="view-all-btn">View All Notifications</button>
            </div>
          </div>
        </div>
        <button class="user-avatar" type="button" @click="() => {}" :title="`View Profile`">
          {{ userInitials }}
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
            <img 
              :src="profilePicture" 
              :alt="companyInfo.name" 
              class="profile-picture"
              @error="handleImageError"
            />
            <button @click="updateProfilePicture" class="update-picture-btn">Change Photo</button>
          </div>
          <div class="profile-info">
            <h2 class="profile-name">{{ companyInfo.name || 'Company' }}</h2>
            <p class="profile-email">{{ companyInfo.email }}</p>
          </div>
          <button class="save-changes-btn" @click="saveChanges" :disabled="saving || loading">
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>

        <!-- Settings Grid -->
        <div class="settings-grid">
          <!-- Company Information Card -->
          <div class="settings-card">
            <div class="card-header">
              <h3 class="card-title">Company Information</h3>
              <p class="card-subtitle">Update your company details</p>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Company Name</label>
                <input 
                  v-model="companyInfo.name"
                  type="text" 
                  class="form-input"
                  placeholder="Enter company name"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Company Type</label>
                <select 
                  v-model="companyInfo.companyType"
                  class="form-input"
                >
                  <option value="">Select type</option>
                  <option value="Corporation">Corporation</option>
                  <option value="Partnership">Partnership</option>
                  <option value="Sole Proprietorship">Sole Proprietorship</option>
                  <option value="LLC">Limited Liability Company (LLC)</option>
                  <option value="Cooperative">Cooperative</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Industry Type</label>
                <input 
                  v-model="companyInfo.industryType"
                  type="text" 
                  class="form-input"
                  placeholder="e.g., Technology, Healthcare, Finance"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input 
                  v-model="companyInfo.email"
                  type="email" 
                  class="form-input"
                  placeholder="Enter company email"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input 
                  v-model="companyInfo.phoneNumber"
                  type="tel" 
                  class="form-input"
                  placeholder="Enter phone number"
                />
              </div>
            </div>
          </div>

          <!-- Contact Person Card -->
          <div class="settings-card">
            <div class="card-header">
              <h3 class="card-title">Contact Person</h3>
              <p class="card-subtitle">Primary contact information</p>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Contact Person Name</label>
                <input 
                  v-model="companyInfo.contactPersonName"
                  type="text" 
                  class="form-input"
                  placeholder="Enter contact person name"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Title/Position</label>
                <input 
                  v-model="companyInfo.contactPersonTitle"
                  type="text" 
                  class="form-input"
                  placeholder="e.g., HR Manager, CEO"
                />
              </div>

              <div class="form-group">
                <label class="form-label">Contact Email</label>
                <input 
                  v-model="companyInfo.contactPersonEmail"
                  type="email" 
                  class="form-input"
                  placeholder="Enter contact person email"
                />
              </div>
            </div>
          </div>

          <!-- Address Information Card -->
          <div class="settings-card full-width">
            <div class="card-header">
              <h3 class="card-title">Address Information</h3>
              <p class="card-subtitle">Complete company address</p>
            </div>
            <div class="card-body">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Complete Address</label>
                  <textarea 
                    v-model="addressInfo.address"
                    class="form-textarea"
                    rows="2"
                    placeholder="Enter complete address"
                  ></textarea>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Region</label>
                  <select 
                    v-model="addressInfo.region"
                    class="form-input"
                  >
                    <option value="">Select region</option>
                    <option value="NCR">NCR</option>
                    <option value="Region I">Region I - Ilocos Region</option>
                    <option value="Region II">Region II - Cagayan Valley</option>
                    <option value="Region III">Region III - Central Luzon</option>
                    <option value="Region IV-A">Region IV-A - CALABARZON</option>
                    <option value="Region IV-B">Region IV-B - MIMAROPA</option>
                    <option value="Region V">Region V - Bicol Region</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Province</label>
                  <input 
                    v-model="addressInfo.province"
                    type="text" 
                    class="form-input"
                    placeholder="Enter province"
                  />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">City/Municipality</label>
                  <input 
                    v-model="addressInfo.city"
                    type="text" 
                    class="form-input"
                    placeholder="Enter city/municipality"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Barangay</label>
                  <input 
                    v-model="addressInfo.barangay"
                    type="text" 
                    class="form-input"
                    placeholder="Enter barangay"
                  />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Zip Code</label>
                  <input 
                    v-model="addressInfo.zipCode"
                    type="text" 
                    class="form-input"
                    placeholder="Enter zip code"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Courses Card -->
          <div class="settings-card full-width">
            <div class="card-header">
              <h3 class="card-title">Courses Accepted</h3>
              <p class="card-subtitle">Courses your company accepts for internships (visible to schools for contract alignment)</p>
            </div>
            <div class="card-body">
              <div class="form-group">
                <label class="form-label">Add Course</label>
                <div class="course-entry">
                  <input
                    v-model="newCourse"
                    type="text"
                    class="form-input"
                    placeholder="e.g., BSIT, BSHM, BSED"
                    @keydown.enter.prevent="addCourse"
                  />
                  <button type="button" class="course-add-btn" @click="addCourse">Add</button>
                </div>
                <div v-if="courses.length" class="course-chip-list">
                  <div v-for="c in courses" :key="c" class="course-chip">
                    <span>{{ c }}</span>
                    <button type="button" class="course-chip-remove" @click="removeCourse(c)">x</button>
                  </div>
                </div>
                <p v-else class="bulk-import-hint">No courses added yet.</p>
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
                <label class="form-label">Current Password</label>
                <input 
                  v-model="security.oldPassword"
                  type="password" 
                  class="form-input"
                  placeholder="Enter current password"
                />
              </div>

              <div class="form-group">
                <label class="form-label">New Password</label>
                <input 
                  v-model="security.newPassword"
                  type="password" 
                  class="form-input"
                  placeholder="Enter new password"
                />
                <div v-if="security.newPassword" class="password-strength">
                  <div class="strength-bar">
                    <div class="strength-fill weak"></div>
                  </div>
                  <span class="strength-text">Weak - Add numbers and symbols</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Security Settings Card -->
          <div class="settings-card">
            <div class="card-header">
              <h3 class="card-title">Security Settings</h3>
              <p class="card-subtitle">Manage your account security</p>
            </div>
            <div class="card-body">
              <div class="security-option">
                <div class="option-info">
                  <h4 class="option-title">Two-Factor Authentication</h4>
                  <p class="option-description">Add an extra layer of security</p>
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
                  <p class="option-description">Get notified of new device access</p>
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

.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.form-input,
.form-textarea {
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
  font-family: inherit;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.form-input:focus,
.form-textarea:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input::placeholder,
.form-textarea::placeholder {
  color: #9ca3af;
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

.course-entry {
  display: flex;
  gap: 10px;
  align-items: center;
}

.course-add-btn {
  white-space: nowrap;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #2563eb;
  background: #2563eb;
  color: white;
  font-weight: 700;
  cursor: pointer;
}

.course-add-btn:hover {
  background: #1d4ed8;
  border-color: #1d4ed8;
}

.course-chip-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.course-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1d4ed8;
  border-radius: 999px;
  font-weight: 700;
  font-size: 12px;
}

.course-chip-remove {
  border: none;
  background: transparent;
  color: #1d4ed8;
  cursor: pointer;
  font-weight: 900;
}

.course-chip-remove:hover {
  color: #1e40af;
}

.bulk-import-hint {
  margin-top: 10px;
  color: #6b7280;
  font-size: 12px;
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
  
  .form-row {
    grid-template-columns: 1fr;
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

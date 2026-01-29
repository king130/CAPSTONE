<script setup lang="ts">
import { ref } from 'vue'

// Form data
const personalInfo = ref({
  name: 'Alex Doe',
  email: 'alexdoe@gmail.com',
  phoneNumber: '+1 (123) 456-7890'
})

const security = ref({
  oldPassword: '',
  newPassword: '',
  twoFactorAuth: false,
  loginAlerts: true
})

const profilePicture = ref('/icons/profiles/alex-doe.jpg')

// Functions
function updateProfilePicture() {
  console.log('Update profile picture clicked')
}

function saveChanges() {
  console.log('Saving changes...', {
    personalInfo: personalInfo.value,
    security: security.value
  })
}

function handleImageError(event: Event) {
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
    <div class="settings-header">
      <div class="header-left">
        <div class="header-icon">🔒</div>
        <h1 class="header-title">Change Password</h1>
      </div>
      <div class="header-right">
        <div class="notification-icon">🔔</div>
        <span class="company-name">Acme Corp. Company</span>
        <div class="user-avatar">AC</div>
      </div>
    </div>

    <div class="settings-body">
      <div class="settings-main">
        <!-- Settings Title and Save Button -->
        <div class="settings-title-bar">
          <h2 class="settings-title">Settings</h2>
          <button @click="saveChanges" class="save-changes-btn">Save Changes</button>
        </div>

        <!-- Personal Information Section -->
        <div class="settings-section">
          <h3 class="section-title">Personal Information</h3>
          <p class="section-subtitle">Manage your personal details and keep your contact info up to date</p>

          <div class="personal-info-grid">
            <!-- Left Column - Form Fields -->
            <div class="form-fields">
              <!-- Profile Picture -->
              <div class="form-group profile-picture-group">
                <label class="form-label">Profile Picture</label>
                <div class="profile-picture-container">
                  <img 
                    :src="profilePicture" 
                    :alt="personalInfo.name" 
                    class="profile-picture"
                    @error="handleImageError"
                  />
                  <button @click="updateProfilePicture" class="update-picture-btn">Update</button>
                </div>
              </div>

              <!-- Name -->
              <div class="form-group">
                <label class="form-label">Name:</label>
                <input 
                  v-model="personalInfo.name"
                  type="text" 
                  class="form-input"
                  placeholder="Alex Doe"
                />
              </div>

              <!-- Email -->
              <div class="form-group">
                <label class="form-label">Email:</label>
                <input 
                  v-model="personalInfo.email"
                  type="email" 
                  class="form-input"
                  placeholder="alexdoe@gmail.com"
                />
              </div>

              <!-- Phone Number -->
              <div class="form-group">
                <label class="form-label">Phone Number:</label>
                <input 
                  v-model="personalInfo.phoneNumber"
                  type="tel" 
                  class="form-input"
                  placeholder="+1 (123) 456-7890"
                />
              </div>
            </div>

            <!-- Right Column - Security -->
            <div class="security-section">
              <h4 class="security-title">Security</h4>
              <p class="security-subtitle">Password Management</p>

              <div class="password-fields">
                <div class="form-group">
                  <label class="form-label">Old Password</label>
                  <input 
                    v-model="security.oldPassword"
                    type="password" 
                    class="form-input password-input"
                    placeholder="Enter old password"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">New Password</label>
                  <input 
                    v-model="security.newPassword"
                    type="password" 
                    class="form-input password-input"
                    placeholder="Enter new password"
                  />
                  <div class="password-strength">
                    <div class="strength-bar">
                      <div class="strength-fill weak"></div>
                    </div>
                    <span class="strength-text">Weak password with capital letter</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Security Section -->
        <div class="settings-section">
          <h3 class="section-title">Security</h3>
          <p class="section-subtitle">Keep your account secure with extra authentication and alerts.</p>

          <div class="security-options">
            <!-- Two-factor Authentication -->
            <div class="security-option">
              <div class="option-info">
                <h4 class="option-title">Two-factor authentication</h4>
                <p class="option-description">Add an extra layer of protection to your account.</p>
              </div>
              <div class="toggle-container">
                <label class="toggle-switch">
                  <input 
                    v-model="security.twoFactorAuth"
                    type="checkbox" 
                    class="toggle-input"
                  />
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>

            <!-- Login Alert Notification -->
            <div class="security-option">
              <div class="option-info">
                <h4 class="option-title">Log-in Alert Notification</h4>
                <p class="option-description">Get notified when your account is accessed from a new device</p>
              </div>
              <div class="toggle-container">
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

.settings-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: white;
  border-bottom: 1px solid #e5e7eb;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  font-size: 20px;
}

.header-title {
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
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

.settings-body {
  padding: 24px;
}

.settings-main {
  max-width: 1200px;
  margin: 0 auto;
}

.settings-title-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}

.settings-title {
  font-size: 28px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

.save-changes-btn {
  padding: 12px 24px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.save-changes-btn:hover {
  background: #2563eb;
}

.settings-section {
  background: white;
  border-radius: 12px;
  padding: 32px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-title {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
}

.section-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 24px 0;
}

.personal-info-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 48px;
}

.form-fields {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.profile-picture-group {
  flex-direction: row;
  align-items: center;
  gap: 16px;
}

.form-label {
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.profile-picture-container {
  display: flex;
  align-items: center;
  gap: 16px;
}

.profile-picture {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e5e7eb;
}

.update-picture-btn {
  padding: 8px 16px;
  background: #f3f4f6;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.update-picture-btn:hover {
  background: #e5e7eb;
  color: #1f2937;
}

.form-input {
  padding: 12px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
  background: white;
}

.form-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input::placeholder {
  color: #9ca3af;
}

.security-section {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 24px;
}

.security-title {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.security-subtitle {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 20px 0;
}

.password-fields {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.password-input {
  background: #f9fafb;
}

.password-strength {
  margin-top: 8px;
}

.strength-bar {
  width: 100%;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 4px;
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
  color: #ef4444;
}

.security-options {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.security-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 0;
  border-bottom: 1px solid #f3f4f6;
}

.security-option:last-child {
  border-bottom: none;
}

.option-info {
  flex: 1;
}

.option-title {
  font-size: 16px;
  font-weight: 500;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.option-description {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.toggle-container {
  flex-shrink: 0;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 24px;
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
  .personal-info-grid {
    grid-template-columns: 1fr;
    gap: 32px;
  }
}

@media (max-width: 768px) {
  .settings-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .settings-body {
    padding: 16px;
  }
  
  .settings-title-bar {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .save-changes-btn {
    width: 100%;
  }
  
  .settings-section {
    padding: 24px 16px;
  }
  
  .profile-picture-group {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .security-option {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
  
  .toggle-container {
    align-self: flex-end;
  }
}
</style>
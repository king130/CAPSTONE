<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { doc, updateDoc } from 'firebase/firestore'
import { db } from '@/services/firebase'
import { ensurePublicProfile } from '@/services/profilesPublic'
import Swal from 'sweetalert2'

const authStore = useAuthStore()
const router = useRouter()
const loading = ref(false)
const saving = ref(false)

const displayName = ref('')
const contactNumber = ref('')
const orgCourses = ref<string[]>([])
const newCourse = ref('')

// Role-specific form fields
const studentFields = ref({
  studentNumber: '',
  schoolName: '',
  course: '',
  yearLevel: '',
  preferredField: '',
})
const companyFields = ref({
  companyName: '',
  companyType: '',
  industryType: '',
  companyAddress: '',
  companyEmail: '',
  contactPersonName: '',
  contactPersonTitle: '',
  contactPersonEmail: '',
  companyContactNumber: '',
})
const schoolFields = ref({
  institutionName: '',
  institutionType: '',
  department: '',
  position: '',
  officialSchoolEmail: '',
  schoolContactNumber: '',
  schoolAddress: '',
})
const guestFields = ref({
  location: '',
  dateOfBirth: '',
  bio: '',
  skills: '',
  education: '',
  careerInterests: '',
  experience: '',
})

const user = computed(() => authStore.user)

function loadProfile() {
  if (!authStore.user) return
  const p = (authStore.user.profile || {}) as Record<string, unknown>
  displayName.value = authStore.user.displayName || ''
  contactNumber.value = (p.contactNumber as string) || (p.companyContactNumber as string) || (p.schoolContactNumber as string) || ''
  orgCourses.value = Array.isArray(p.courses) ? (p.courses as string[]) : []

  studentFields.value = {
    studentNumber: (p.studentNumber as string) || (p.studentId as string) || '',
    schoolName: (p.schoolName as string) || '',
    course: (p.course as string) || '',
    yearLevel: (p.yearLevel as string) || '',
    preferredField: (p.preferredField as string) || '',
  }
  companyFields.value = {
    companyName: (p.companyName as string) || '',
    companyType: (p.companyType as string) || '',
    industryType: (p.industryType as string) || '',
    companyAddress: (p.companyAddress as string) || '',
    companyEmail: (p.companyEmail as string) || '',
    contactPersonName: (p.contactPersonName as string) || '',
    contactPersonTitle: (p.contactPersonTitle as string) || '',
    contactPersonEmail: (p.contactPersonEmail as string) || '',
    companyContactNumber: (p.companyContactNumber as string) || '',
  }
  schoolFields.value = {
    institutionName: (p.institutionName as string) || '',
    institutionType: (p.institutionType as string) || '',
    department: (p.department as string) || '',
    position: (p.position as string) || '',
    officialSchoolEmail: (p.officialSchoolEmail as string) || '',
    schoolContactNumber: (p.schoolContactNumber as string) || '',
    schoolAddress: (p.schoolAddress as string) || '',
  }
  guestFields.value = {
    location: (p.location as string) || '',
    dateOfBirth: (p.dateOfBirth as string) || '',
    bio: (p.bio as string) || '',
    skills: (p.skills as string) || '',
    education: (p.education as string) || '',
    careerInterests: (p.careerInterests as string) || '',
    experience: (p.experience as string) || '',
  }
}

onMounted(loadProfile)
watch(() => authStore.user, loadProfile, { deep: true })

function buildUpdatedProfile(): Record<string, unknown> {
  const role = authStore.user?.role

  if (role === 'student') {
    const studentNumber = studentFields.value.studentNumber?.trim() || ''
    // Keep backward-compatible key `studentId` (used in other screens) while introducing `studentNumber`.
    return { contactNumber: contactNumber.value, studentNumber, studentId: studentNumber, ...studentFields.value }
  }
  if (role === 'company') {
    return { contactNumber: contactNumber.value, companyContactNumber: companyFields.value.companyContactNumber || contactNumber.value, courses: orgCourses.value, ...companyFields.value }
  }
  if (role === 'school') {
    return { contactNumber: contactNumber.value, schoolContactNumber: schoolFields.value.schoolContactNumber || contactNumber.value, courses: orgCourses.value, ...schoolFields.value }
  }
  // Guest or no role
  return { contactNumber: contactNumber.value, ...guestFields.value }
}

function addCourse() {
  const value = newCourse.value.trim()
  if (!value) return
  const normalized = value.toUpperCase()
  if (!orgCourses.value.some((c) => c.toUpperCase() === normalized)) {
    orgCourses.value.push(value)
  }
  newCourse.value = ''
}

function removeCourse(course: string) {
  orgCourses.value = orgCourses.value.filter((c) => c !== course)
}

async function saveProfile() {
  if (!authStore.user) return
  saving.value = true
  try {
    const updatedProfile = buildUpdatedProfile()
    await updateDoc(doc(db, 'users', authStore.user.uid), {
      displayName: displayName.value,
      profile: updatedProfile,
      profileSetupComplete: true,
      updatedAt: new Date()
    })
    if (authStore.user.role === 'school' || authStore.user.role === 'company') {
      const orgName = (authStore.user.role === 'school' ? updatedProfile.institutionName : updatedProfile.companyName) as string | undefined
      await ensurePublicProfile(authStore.user.uid, {
        displayName: displayName.value,
        role: authStore.user.role,
        orgName: orgName || displayName.value,
        email: authStore.user.email,
        courses: (updatedProfile.courses as string[]) || [],
      }).catch(() => {})
    }
    await Swal.fire({
      icon: 'success',
      title: 'Profile Updated',
      text: 'Your profile has been saved successfully.',
      confirmButtonColor: '#2563eb'
    })
  } catch (error) {
    console.error('Profile save error:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to save profile. Please try again.',
      confirmButtonColor: '#2563eb'
    })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="profile-page">
    <div class="profile-container">
      <h1>Settings</h1>
      <p class="subtitle">Manage your account and profile information</p>

      <div v-if="user" class="profile-form">
       
        <div class="field">
          <label>Full Name</label>
          <input v-model="displayName" type="text" placeholder="Your full name" />
        </div>
        <div class="field">
          <label>Email</label>
          <input :value="user.email" type="email" disabled class="disabled" />
        </div>
        <div class="field">
          <label>Contact Number</label>
          <input v-model="contactNumber" type="tel" placeholder="Your contact number" />
        </div>

        <!-- Student fields -->
        <template v-if="user.role === 'student'">
          <div class="field">
            <label>Student Number</label>
            <input v-model="studentFields.studentNumber" type="text" placeholder="e.g. 2026-0001" />
          </div>
          <div class="field">
            <label>School Name</label>
            <input v-model="studentFields.schoolName" type="text" placeholder="Your school" />
          </div>
          <div class="field">
            <label>Course</label>
            <input v-model="studentFields.course" type="text" placeholder="e.g. Computer Science" />
          </div>
          <div class="field">
            <label>Year Level</label>
            <input v-model="studentFields.yearLevel" type="text" placeholder="e.g. 4th Year" />
          </div>
          <div class="field">
            <label>Preferred Field</label>
            <input v-model="studentFields.preferredField" type="text" placeholder="e.g. Software Development" />
          </div>
        </template>

        <!-- Company fields -->
        <template v-if="user.role === 'company'">
          <div class="field">
            <label>Company Name</label>
            <input v-model="companyFields.companyName" type="text" placeholder="Company name" />
          </div>
          <div class="field">
            <label>Company Type</label>
            <input v-model="companyFields.companyType" type="text" placeholder="e.g. Corporation" />
          </div>
          <div class="field">
            <label>Industry</label>
            <input v-model="companyFields.industryType" type="text" placeholder="e.g. Technology" />
          </div>
          <div class="field">
            <label>Company Address</label>
            <input v-model="companyFields.companyAddress" type="text" placeholder="Address" />
          </div>
          <div class="field">
            <label>Company Email</label>
            <input v-model="companyFields.companyEmail" type="email" placeholder="company@example.com" />
          </div>
          <div class="field">
            <label>Contact Person Name</label>
            <input v-model="companyFields.contactPersonName" type="text" placeholder="Contact person" />
          </div>
          <div class="field">
            <label>Contact Person Title</label>
            <input v-model="companyFields.contactPersonTitle" type="text" placeholder="e.g. HR Manager" />
          </div>
          <div class="field">
            <label>Contact Person Email</label>
            <input v-model="companyFields.contactPersonEmail" type="email" placeholder="contact@email.com" />
          </div>
          <div class="field">
            <label>Company Phone</label>
            <input v-model="companyFields.companyContactNumber" type="tel" placeholder="Phone number" />
          </div>
          <div class="field">
            <label>Courses Accepted</label>
            <div class="course-entry-inline">
              <input v-model="newCourse" type="text" placeholder="e.g. BSIT" @keydown.enter.prevent="addCourse" />
              <button type="button" class="course-add-inline" @click="addCourse">Add</button>
            </div>
            <div v-if="orgCourses.length" class="course-chip-list-inline">
              <div v-for="c in orgCourses" :key="c" class="course-chip-inline">
                <span>{{ c }}</span>
                <button type="button" class="course-chip-remove-inline" @click="removeCourse(c)">x</button>
              </div>
            </div>
            <small class="course-hint">These are visible to schools during contract creation.</small>
          </div>
        </template>

        <!-- School fields -->
        <template v-if="user.role === 'school'">
          <div class="field">
            <label>Institution Name</label>
            <input v-model="schoolFields.institutionName" type="text" placeholder="School name" />
          </div>
          <div class="field">
            <label>Institution Type</label>
            <input v-model="schoolFields.institutionType" type="text" placeholder="e.g. State University" />
          </div>
          <div class="field">
            <label>Department</label>
            <input v-model="schoolFields.department" type="text" placeholder="e.g. Career Services" />
          </div>
          <div class="field">
            <label>Position</label>
            <input v-model="schoolFields.position" type="text" placeholder="e.g. OJT Coordinator" />
          </div>
          <div class="field">
            <label>Official School Email</label>
            <input v-model="schoolFields.officialSchoolEmail" type="email" placeholder="ojt@school.edu" />
          </div>
          <div class="field">
            <label>School Phone</label>
            <input v-model="schoolFields.schoolContactNumber" type="tel" placeholder="Phone number" />
          </div>
          <div class="field">
            <label>School Address</label>
            <input v-model="schoolFields.schoolAddress" type="text" placeholder="Address" />
          </div>
          <div class="field">
            <label>Courses Offered</label>
            <div class="course-entry-inline">
              <input v-model="newCourse" type="text" placeholder="e.g. BSIT" @keydown.enter.prevent="addCourse" />
              <button type="button" class="course-add-inline" @click="addCourse">Add</button>
            </div>
            <div v-if="orgCourses.length" class="course-chip-list-inline">
              <div v-for="c in orgCourses" :key="c" class="course-chip-inline">
                <span>{{ c }}</span>
                <button type="button" class="course-chip-remove-inline" @click="removeCourse(c)">x</button>
              </div>
            </div>
            <small class="course-hint">These are used to align courses with companies in contracts.</small>
          </div>
        </template>

        <!-- Guest fields -->
        <template v-if="!user.role || user.role === 'guest'">
          <div class="field">
            <label>Location</label>
            <input v-model="guestFields.location" type="text" placeholder="City, Country" />
          </div>
          <div class="field">
            <label>Date of Birth</label>
            <input v-model="guestFields.dateOfBirth" type="date" />
          </div>
          <div class="field">
            <label>About Me</label>
            <textarea v-model="guestFields.bio" placeholder="Tell us about yourself, your interests, and goals..." rows="4"></textarea>
          </div>
          <div class="field">
            <label>Skills</label>
            <input v-model="guestFields.skills" type="text" placeholder="e.g., Programming, Design, Marketing, Communication" />
          </div>
          <div class="field">
            <label>Education Background</label>
            <input v-model="guestFields.education" type="text" placeholder="e.g., Bachelor's in Computer Science, High School Graduate" />
          </div>
          <div class="field">
            <label>Career Interests</label>
            <input v-model="guestFields.careerInterests" type="text" placeholder="e.g., Software Development, Digital Marketing, Finance" />
          </div>
          <div class="field">
            <label>Experience</label>
            <textarea v-model="guestFields.experience" placeholder="Any work experience, projects, or relevant activities..." rows="3"></textarea>
          </div>
        </template>

        <!-- Role Display -->
        <div class="field">
          <label>Account Type</label>
          <input :value="user.role || 'Guest'" type="text" disabled class="disabled" />
        </div>
        <button class="save-btn" @click="saveProfile" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save Profile' }}
        </button>
      </div>

      <div v-else class="loading">Loading...</div>
    </div>
  </div>
</template>

<style scoped>
.profile-page {
  min-height: 100vh;
  background: #f8fafc;
  padding: 2rem 1rem;
  font-family: Inter, system-ui, sans-serif;
}
.profile-container {
  max-width: 600px;
  margin: 0 auto;
  background: white;
  border-radius: 12px;
  padding: 2.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

@media (max-width: 640px) {
  .profile-page {
    padding: 1rem 0.5rem;
  }
  .profile-container {
    padding: 1.5rem;
    border-radius: 8px;
  }
}
.profile-container h1 {
  font-size: 1.75rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.5rem 0;
}
.subtitle {
  color: #6b7280;
  margin: 0 0 2rem 0;
}
.field {
  margin-bottom: 1.25rem;
}

.course-entry-inline {
  display: flex;
  gap: 10px;
  align-items: center;
}

.course-entry-inline input {
  flex: 1;
}

.course-add-inline {
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid #2563eb;
  background: #2563eb;
  color: white;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.course-add-inline:hover {
  background: #1d4ed8;
  border-color: #1d4ed8;
}

.course-chip-list-inline {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.course-chip-inline {
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

.course-chip-remove-inline {
  border: none;
  background: transparent;
  color: #1d4ed8;
  cursor: pointer;
  font-weight: 900;
}

.course-chip-remove-inline:hover {
  color: #1e40af;
}

.course-hint {
  display: block;
  margin-top: 8px;
  color: #6b7280;
  font-size: 12px;
}
.field label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
}
.field input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 1rem;
  box-sizing: border-box;
  transition: all 0.2s ease;
}

.field textarea {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 1rem;
  box-sizing: border-box;
  transition: all 0.2s ease;
  font-family: inherit;
  resize: vertical;
  min-height: 80px;
}

.field input:focus,
.field textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.field input:hover:not(:disabled),
.field textarea:hover {
  border-color: #2563eb;
}
.field input.disabled {
  background: #f3f4f6;
  color: #6b7280;
  cursor: not-allowed;
}
.save-btn {
  width: 100%;
  padding: 0.75rem 1.5rem;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: 1rem;
}
.save-btn:hover:not(:disabled) {
  background: #1d4ed8;
}
.save-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}
.loading {
  text-align: center;
  color: #6b7280;
  padding: 2rem;
}
.back-link {
  display: inline-block;
  font-size: 0.875rem;
  color: #2563eb;
  text-decoration: none;
  margin-bottom: 1rem;
}
.back-link:hover {
  text-decoration: underline;
}
</style>

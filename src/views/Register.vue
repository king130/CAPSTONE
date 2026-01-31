<script setup lang="ts">
import { ref } from 'vue'
import Swal from 'sweetalert2'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const selectedRole = ref<'student' | 'school' | 'company' | null>(null)
const agreed = ref(false)
const demoMode = ref(false)
const currentStep = ref(1)
const totalSteps = 5
const showPassword = ref(false)
const showConfirmPassword = ref(false)

// Form data
const formData = ref({
  fullName: '',
  email: '',
  password: '',
  confirmPassword: '',
  // Student-specific fields
  studentId: '',
  schoolName: '',
  schoolSubscriptionCode: '',
  course: '',
  yearLevel: '',
  preferredField: '',
  resume: null as File | null,
  proofOfEnrollment: null as File | null,
  contactNumber: '',
  // Company-specific fields
  companyName: '',
  industryType: '',
  companyType: '',
  companyAddress: '',
  companyEmail: '',
  contactPersonName: '',
  contactPersonTitle: '',
  contactPersonEmail: '',
  companyContactNumber: '',
  website: '',
  internshipDescription: '',
  businessRegNumber: '',
  taxIdentificationNumber: '',
  businessPermit: null as File | null,
  companyIdDoc: null as File | null,
  organizationProfile: null as File | null,
  // School-specific fields
  institutionName: '',
  institutionType: '',
  department: '',
  position: '',
  officialSchoolEmail: '',
  schoolContactNumber: '',
  schoolAddress: '',
  schoolIdNumber: '',
  chedDepedNumber: '',
  schoolAccreditation: '',
  registrarName: '',
  registrarEmail: '',
  schoolPermit: null as File | null,
  endorsementLetter: null as File | null,
  // Subscription
  subscriptionPlan: 'free',
  billingCycle: 'monthly'
})

const router = useRouter()
const authStore = useAuthStore()

function handleFileUpload(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value.resume = target.files[0]
  }
}

function handleStudentFileUpload(event: Event, field: 'proofOfEnrollment') {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value[field] = target.files[0]
  }
}

function handleCompanyFileUpload(event: Event, field: 'businessPermit' | 'companyIdDoc' | 'organizationProfile') {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value[field] = target.files[0]
  }
}

function handleSchoolFileUpload(event: Event, field: 'schoolPermit' | 'endorsementLetter') {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value[field] = target.files[0]
  }
}

function fillDemo(role: 'student' | 'company' | 'school') {
  demoMode.value = true
  selectedRole.value = role
  formData.value.fullName = role === 'student' ? 'Alex Student' : role === 'company' ? 'Jordan Employer' : 'Taylor Coordinator'
  formData.value.email = role === 'student' ? 'student@demo.com' : role === 'company' ? 'company@demo.com' : 'school@demo.com'
  formData.value.password = 'DemoPass123!'
  formData.value.confirmPassword = 'DemoPass123!'

  if (role === 'student') {
    formData.value.studentId = '2026-0001'
    formData.value.schoolName = 'Cavite State University'
    formData.value.schoolSubscriptionCode = 'CSV-2026-DEMO'
    formData.value.course = 'Computer Science'
    formData.value.yearLevel = '4th Year'
    formData.value.preferredField = 'Software Development'
    formData.value.contactNumber = '09171234567'
  }

  if (role === 'company') {
    formData.value.companyName = 'Cavite Tech Hub'
    formData.value.companyType = 'Corporation'
    formData.value.industryType = 'Technology'
    formData.value.companyAddress = 'Imus, Cavite'
    formData.value.companyEmail = 'hr@cavitetech.demo'
    formData.value.contactPersonName = 'Jordan Reyes'
    formData.value.contactPersonTitle = 'HR Manager'
    formData.value.contactPersonEmail = 'jordan@cavitetech.demo'
    formData.value.companyContactNumber = '09181234567'
    formData.value.businessRegNumber = 'SEC-2026-00123'
    formData.value.taxIdentificationNumber = '123-456-789-000'
  }

  if (role === 'school') {
    formData.value.institutionName = 'Cavite State University'
    formData.value.institutionType = 'State University/College'
    formData.value.department = 'Career Services'
    formData.value.position = 'OJT Coordinator'
    formData.value.officialSchoolEmail = 'ojt@cvsu.demo'
    formData.value.schoolIdNumber = 'CHED-CVSU-001'
    formData.value.chedDepedNumber = 'CHED-REG4A-2026-001'
    formData.value.schoolContactNumber = '046-123-4567'
    formData.value.registrarName = 'Dr. Jamie Cruz'
    formData.value.registrarEmail = 'registrar@cvsu.demo'
    formData.value.schoolAddress = 'Indang, Cavite'
  }
}

function isEmpty(value: string) {
  return !value || !value.trim()
}

function isStepValid() {
  if (currentStep.value === 1) {
    return (
      !isEmpty(formData.value.fullName) &&
      !isEmpty(formData.value.email) &&
      !isEmpty(formData.value.password) &&
      formData.value.password.length >= 6 &&
      formData.value.password === formData.value.confirmPassword
    )
  }

  if (currentStep.value === 2) {
    return !!selectedRole.value
  }

  if (currentStep.value === 3) {
    if (selectedRole.value === 'student') {
      return (
        !isEmpty(formData.value.studentId) &&
        !isEmpty(formData.value.schoolName) &&
        !isEmpty(formData.value.schoolSubscriptionCode) &&
        !isEmpty(formData.value.course) &&
        !isEmpty(formData.value.yearLevel) &&
        !isEmpty(formData.value.preferredField) &&
        !isEmpty(formData.value.contactNumber)
      )
    }

    if (selectedRole.value === 'company') {
      return (
        !isEmpty(formData.value.companyName) &&
        !isEmpty(formData.value.companyType) &&
        !isEmpty(formData.value.industryType) &&
        !isEmpty(formData.value.companyAddress) &&
        !isEmpty(formData.value.companyEmail) &&
        !isEmpty(formData.value.contactPersonName) &&
        !isEmpty(formData.value.contactPersonTitle) &&
        !isEmpty(formData.value.contactPersonEmail) &&
        !isEmpty(formData.value.companyContactNumber)
      )
    }

    if (selectedRole.value === 'school') {
      return (
        !isEmpty(formData.value.institutionName) &&
        !isEmpty(formData.value.institutionType) &&
        !isEmpty(formData.value.department) &&
        !isEmpty(formData.value.position) &&
        !isEmpty(formData.value.officialSchoolEmail) &&
        !isEmpty(formData.value.schoolIdNumber) &&
        !isEmpty(formData.value.chedDepedNumber) &&
        !isEmpty(formData.value.schoolContactNumber)
      )
    }
  }

  if (currentStep.value === 4) {
    if (demoMode.value) {
      return true
    }

    if (selectedRole.value === 'student') {
      return !!formData.value.resume && !!formData.value.proofOfEnrollment
    }

    if (selectedRole.value === 'company') {
      return (
        !isEmpty(formData.value.businessRegNumber) &&
        !isEmpty(formData.value.taxIdentificationNumber) &&
        !!formData.value.businessPermit &&
        !!formData.value.companyIdDoc &&
        !!formData.value.organizationProfile
      )
    }

    if (selectedRole.value === 'school') {
      return (
        !isEmpty(formData.value.registrarName) &&
        !isEmpty(formData.value.registrarEmail) &&
        !isEmpty(formData.value.schoolAddress) &&
        !!formData.value.schoolPermit &&
        !!formData.value.endorsementLetter
      )
    }
  }

  if (currentStep.value === 5) {
    return agreed.value
  }

  return false
}

function goNext() {
  if (!isStepValid()) return
  if (currentStep.value < totalSteps) currentStep.value++
}

function goBack() {
  if (currentStep.value > 1) {
    currentStep.value--
  }
}

async function onRegister() {
  if (!selectedRole.value) return
  try {
    const profile = await authStore.register({
      fullName: formData.value.fullName,
      email: formData.value.email,
      password: formData.value.password,
      role: selectedRole.value,
      profile: {
        studentId: formData.value.studentId,
        schoolName: formData.value.schoolName,
        schoolSubscriptionCode: formData.value.schoolSubscriptionCode,
        course: formData.value.course,
        yearLevel: formData.value.yearLevel,
        preferredField: formData.value.preferredField,
        contactNumber: formData.value.contactNumber,
        companyName: formData.value.companyName,
        companyType: formData.value.companyType,
        industryType: formData.value.industryType,
        companyAddress: formData.value.companyAddress,
        companyEmail: formData.value.companyEmail,
        contactPersonName: formData.value.contactPersonName,
        contactPersonTitle: formData.value.contactPersonTitle,
        contactPersonEmail: formData.value.contactPersonEmail,
        companyContactNumber: formData.value.companyContactNumber,
        businessRegNumber: formData.value.businessRegNumber,
        taxIdentificationNumber: formData.value.taxIdentificationNumber,
        institutionName: formData.value.institutionName,
        institutionType: formData.value.institutionType,
        department: formData.value.department,
        position: formData.value.position,
        officialSchoolEmail: formData.value.officialSchoolEmail,
        schoolContactNumber: formData.value.schoolContactNumber,
        schoolAddress: formData.value.schoolAddress,
        schoolIdNumber: formData.value.schoolIdNumber,
        chedDepedNumber: formData.value.chedDepedNumber,
        schoolAccreditation: formData.value.schoolAccreditation,
        registrarName: formData.value.registrarName,
        registrarEmail: formData.value.registrarEmail,
      },
      subscriptionPlan: formData.value.subscriptionPlan,
      billingCycle: formData.value.billingCycle,
      schoolSubscriptionCode: formData.value.schoolSubscriptionCode,
    })

    const message =
      selectedRole.value === 'school' && profile?.subscription?.subscriptionCode
        ? `Your school subscription code is ${profile.subscription.subscriptionCode}. Share this with students to register.`
        : 'You can now login with your new credentials.'

    const result = await Swal.fire({
      icon: 'success',
      iconColor: '#16a34a',
      title: 'Account Created\nSuccessfully!',
      text: message,
      confirmButtonText: 'Proceed to Login',
      confirmButtonColor: '#2563eb',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })

    if (result.isConfirmed) {
      await router.push('/login')
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : 'Registration failed.'
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Registration Failed',
      text: message,
      confirmButtonText: 'Try Again',
      confirmButtonColor: '#2563eb',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <div class="icon-row">
        <div class="icon-bubbles">
          <img src="/icons/logo-main.png" alt="OJT Intern Path" class="register-logo" />
        </div>
      </div>

      <h1>Create Your Account</h1>
      <p class="subtitle">Join as a Student, Coordinator, or Company</p>

      <form class="form" @submit.prevent="onRegister">
        <div class="section-label">Step {{ currentStep }} of {{ totalSteps }}</div>

        <div v-if="currentStep === 1">
          <div class="section-label">Account Basics</div>

          <label class="field">
            <span>Full Name</span>
            <input v-model="formData.fullName" type="text" placeholder="Enter your full name" />
          </label>

          <label class="field">
            <span>Email Address</span>
            <input v-model="formData.email" type="email" placeholder="Enter your email address" />
          </label>

          <label class="field">
            <span>Password</span>
            <div class="password-field">
              <input
                v-model="formData.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Create a password (min 6 characters)"
              />
              <button type="button" class="toggle-password" @click="showPassword = !showPassword">
                {{ showPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
            <small v-if="formData.password && formData.password.length < 6" style="color: #dc2626; font-size: 11px;">
              Password must be at least 6 characters
            </small>
          </label>

          <label class="field">
            <span>Confirm Password</span>
            <div class="password-field">
              <input
                v-model="formData.confirmPassword"
                :type="showConfirmPassword ? 'text' : 'password'"
                placeholder="Re-enter your password"
              />
              <button
                type="button"
                class="toggle-password"
                @click="showConfirmPassword = !showConfirmPassword"
              >
                {{ showConfirmPassword ? 'Hide' : 'Show' }}
              </button>
            </div>
          </label>
        </div>

        <div v-else-if="currentStep === 2">
          <div class="section-label">Select Your Role</div>

          <div class="role-grid">
            <button
              type="button"
              class="role-card"
              :class="{ active: selectedRole === 'student' }"
              @click="selectedRole = 'student'"
            >
              <div class="role-icon">
                <img src="/icons/icon-student.png" alt="Student" class="role-icon-img" />
              </div>
              <div class="role-title">Student</div>
              <p>I'm looking for an internship or OJT placement.</p>
            </button>

            <button
              type="button"
              class="role-card"
              :class="{ active: selectedRole === 'school' }"
              @click="selectedRole = 'school'"
            >
              <div class="role-icon">
                <img src="/icons/icon-school.png" alt="School" class="role-icon-img" />
              </div>
              <div class="role-title">School</div>
              <p>I manage students and placements for my institution.</p>
            </button>

            <button
              type="button"
              class="role-card"
              :class="{ active: selectedRole === 'company' }"
              @click="selectedRole = 'company'"
            >
              <div class="role-icon">
                <img src="/icons/icon-company.png" alt="Company" class="role-icon-img" />
              </div>
              <div class="role-title">Company</div>
              <p>I'm offering internship opportunities to students.</p>
            </button>
          </div>

          <div class="demo-actions">
            <div class="demo-label">Quick demo accounts</div>
            <div class="demo-buttons">
              <button type="button" class="demo-btn" @click="fillDemo('student')">Use Demo Student</button>
              <button type="button" class="demo-btn" @click="fillDemo('school')">Use Demo School</button>
              <button type="button" class="demo-btn" @click="fillDemo('company')">Use Demo Company</button>
            </div>
            <p class="demo-note">Demo mode skips document uploads in step 4.</p>
          </div>
        </div>

        <div v-else-if="currentStep === 3">
          <div class="section-label">Profile Information</div>

          <div v-if="selectedRole === 'student'" class="role-specific-fields">
            <label class="field">
              <span>Student ID Number</span>
              <input v-model="formData.studentId" type="text" placeholder="Enter your student ID" />
            </label>

            <label class="field">
              <span>School / University Name</span>
              <input v-model="formData.schoolName" type="text" placeholder="Enter your school name" />
            </label>

            <label class="field">
              <span>School Subscription Code</span>
              <input v-model="formData.schoolSubscriptionCode" type="text" placeholder="Enter code provided by your school" />
            </label>

            <label class="field">
              <span>Course / Program</span>
              <input v-model="formData.course" type="text" placeholder="e.g., Computer Science, Business Administration" />
            </label>

            <label class="field">
              <span>Year Level</span>
              <select v-model="formData.yearLevel" class="select-input">
                <option value="">Select your year level</option>
                <option value="1st Year">1st Year</option>
                <option value="2nd Year">2nd Year</option>
                <option value="3rd Year">3rd Year</option>
                <option value="4th Year">4th Year</option>
                <option value="5th Year">5th Year</option>
                <option value="Graduate">Graduate</option>
              </select>
            </label>

            <label class="field">
              <span>Preferred Internship Field</span>
              <select v-model="formData.preferredField" class="select-input">
                <option value="">Select preferred field</option>
                <option value="Software Development">Software Development</option>
                <option value="Web Development">Web Development</option>
                <option value="Mobile Development">Mobile Development</option>
                <option value="Data Science">Data Science</option>
                <option value="UI/UX Design">UI/UX Design</option>
                <option value="Digital Marketing">Digital Marketing</option>
                <option value="Business Administration">Business Administration</option>
                <option value="Human Resources">Human Resources</option>
                <option value="Finance">Finance</option>
                <option value="Engineering">Engineering</option>
                <option value="Healthcare">Healthcare</option>
                <option value="Education">Education</option>
                <option value="Other">Other</option>
              </select>
            </label>

            <label class="field">
              <span>Contact Number</span>
              <input v-model="formData.contactNumber" type="tel" placeholder="Enter your contact number" />
            </label>
          </div>

          <div v-else-if="selectedRole === 'company'" class="role-specific-fields">
            <label class="field">
              <span>Company Name</span>
              <input v-model="formData.companyName" type="text" placeholder="Enter your company name" />
            </label>

            <label class="field">
              <span>Company Type</span>
              <select v-model="formData.companyType" class="select-input">
                <option value="">Select company type</option>
                <option value="Sole Proprietorship">Sole Proprietorship</option>
                <option value="Partnership">Partnership</option>
                <option value="Corporation">Corporation</option>
                <option value="Cooperative">Cooperative</option>
                <option value="Government">Government</option>
                <option value="Non-profit">Non-profit</option>
                <option value="Other">Other</option>
              </select>
            </label>

            <label class="field">
              <span>Industry Type</span>
              <select v-model="formData.industryType" class="select-input">
                <option value="">Select industry type</option>
                <option value="Technology">Technology</option>
                <option value="Healthcare">Healthcare</option>
                <option value="Finance">Finance</option>
                <option value="Education">Education</option>
                <option value="Manufacturing">Manufacturing</option>
                <option value="Retail">Retail</option>
                <option value="Consulting">Consulting</option>
                <option value="Marketing & Advertising">Marketing & Advertising</option>
                <option value="Real Estate">Real Estate</option>
                <option value="Construction">Construction</option>
                <option value="Transportation">Transportation</option>
                <option value="Hospitality">Hospitality</option>
                <option value="Non-profit">Non-profit</option>
                <option value="Government">Government</option>
                <option value="Other">Other</option>
              </select>
            </label>

            <label class="field">
              <span>Company Address</span>
              <textarea 
                v-model="formData.companyAddress" 
                placeholder="Enter your complete company address"
                class="textarea-input"
                rows="3"
              ></textarea>
            </label>

            <label class="field">
              <span>Company Email</span>
              <input v-model="formData.companyEmail" type="email" placeholder="Enter company email address" />
            </label>

            <label class="field">
              <span>Contact Person Name</span>
              <input v-model="formData.contactPersonName" type="text" placeholder="Enter contact person's full name" />
            </label>

            <label class="field">
              <span>Contact Person Title</span>
              <input v-model="formData.contactPersonTitle" type="text" placeholder="e.g., HR Manager, Internship Coordinator" />
            </label>

            <label class="field">
              <span>Contact Person Email</span>
              <input v-model="formData.contactPersonEmail" type="email" placeholder="Enter contact person's email" />
            </label>

            <label class="field">
              <span>Contact Number</span>
              <input v-model="formData.companyContactNumber" type="tel" placeholder="Enter company contact number" />
            </label>
          </div>

          <div v-else-if="selectedRole === 'school'" class="role-specific-fields">
            <label class="field">
              <span>Institution Name</span>
              <input v-model="formData.institutionName" type="text" placeholder="Enter your institution name" />
            </label>

            <label class="field">
              <span>Institution Type</span>
              <select v-model="formData.institutionType" class="select-input">
                <option value="">Select institution type</option>
                <option value="State University/College">State University/College</option>
                <option value="Private University/College">Private University/College</option>
                <option value="TESDA">TESDA</option>
                <option value="Senior High School">Senior High School</option>
                <option value="Other">Other</option>
              </select>
            </label>

            <label class="field">
              <span>Department / Office</span>
              <input v-model="formData.department" type="text" placeholder="e.g., Career Services, Academic Affairs" />
            </label>

            <label class="field">
              <span>Position / Role</span>
              <select v-model="formData.position" class="select-input">
                <option value="">Select your position/role</option>
                <option value="OJT Coordinator">OJT Coordinator</option>
                <option value="Career Services Director">Career Services Director</option>
                <option value="Academic Coordinator">Academic Coordinator</option>
                <option value="Department Head">Department Head</option>
                <option value="Dean">Dean</option>
                <option value="Vice President">Vice President</option>
                <option value="President">President</option>
                <option value="Faculty Member">Faculty Member</option>
                <option value="Administrative Staff">Administrative Staff</option>
                <option value="Other">Other</option>
              </select>
            </label>

            <label class="field">
              <span>Official School Email</span>
              <input v-model="formData.officialSchoolEmail" type="email" placeholder="Enter your official school email address" />
            </label>

            <label class="field">
              <span>School ID / Institution Code</span>
              <input v-model="formData.schoolIdNumber" type="text" placeholder="Enter institution ID or code" />
            </label>

            <label class="field">
              <span>CHED/DepEd Accreditation Number</span>
              <input v-model="formData.chedDepedNumber" type="text" placeholder="Enter CHED/DepEd accreditation number" />
            </label>

            <label class="field">
              <span>Contact Number</span>
              <input v-model="formData.schoolContactNumber" type="tel" placeholder="Enter your contact number" />
            </label>
          </div>

          <div v-else class="placeholder-view">
            <p>Select a role to continue.</p>
          </div>
        </div>

        <div v-else-if="currentStep === 4">
          <div class="section-label">Verification Documents</div>

          <div v-if="selectedRole === 'student'" class="role-specific-fields">
            <label class="field file-field">
              <span>Resume / CV Upload (PDF)</span>
              <div class="file-upload-container">
                <input 
                  type="file" 
                  accept=".pdf"
                  @change="handleFileUpload"
                  class="file-input"
                  id="resume-upload"
                />
                <label for="resume-upload" class="file-upload-btn">
                  <span class="upload-icon">📄</span>
                  <span v-if="!formData.resume">Choose PDF file</span>
                  <span v-else>{{ formData.resume.name }}</span>
                </label>
                <p class="file-help-text">Upload your resume in PDF format (max 5MB)</p>
              </div>
            </label>

            <label class="field file-field">
              <span>Certificate of Enrollment (PDF/JPG)</span>
              <div class="file-upload-container">
                <input 
                  type="file" 
                  accept=".pdf,.jpg,.jpeg,.png"
                  @change="(e) => handleStudentFileUpload(e, 'proofOfEnrollment')"
                  class="file-input"
                  id="enrollment-proof-upload"
                />
                <label for="enrollment-proof-upload" class="file-upload-btn">
                  <span class="upload-icon">📄</span>
                  <span v-if="!formData.proofOfEnrollment">Choose file</span>
                  <span v-else>{{ formData.proofOfEnrollment.name }}</span>
                </label>
                <p class="file-help-text">Used to verify enrollment with subscribed school</p>
              </div>
            </label>
          </div>

          <div v-else-if="selectedRole === 'company'" class="role-specific-fields">
            <label class="field">
              <span>Business Registration Number (SEC/DTI)</span>
              <input v-model="formData.businessRegNumber" type="text" placeholder="Enter SEC/DTI registration number" />
            </label>

            <label class="field">
              <span>Tax Identification Number (TIN)</span>
              <input v-model="formData.taxIdentificationNumber" type="text" placeholder="Enter company TIN" />
            </label>

            <label class="field file-field">
              <span>Business Permit (PDF/JPG)</span>
              <div class="file-upload-container">
                <input 
                  type="file" 
                  accept=".pdf,.jpg,.jpeg,.png"
                  @change="(e) => handleCompanyFileUpload(e, 'businessPermit')"
                  class="file-input"
                  id="business-permit-upload"
                />
                <label for="business-permit-upload" class="file-upload-btn">
                  <span class="upload-icon">📄</span>
                  <span v-if="!formData.businessPermit">Choose file</span>
                  <span v-else>{{ formData.businessPermit.name }}</span>
                </label>
                <p class="file-help-text">Upload recent business permit (max 5MB)</p>
              </div>
            </label>

            <label class="field file-field">
              <span>Authorized Representative ID (PDF/JPG)</span>
              <div class="file-upload-container">
                <input 
                  type="file" 
                  accept=".pdf,.jpg,.jpeg,.png"
                  @change="(e) => handleCompanyFileUpload(e, 'companyIdDoc')"
                  class="file-input"
                  id="company-id-upload"
                />
                <label for="company-id-upload" class="file-upload-btn">
                  <span class="upload-icon">🪪</span>
                  <span v-if="!formData.companyIdDoc">Choose file</span>
                  <span v-else>{{ formData.companyIdDoc.name }}</span>
                </label>
                <p class="file-help-text">Government-issued ID of authorized signatory</p>
              </div>
            </label>

            <label class="field file-field">
              <span>Company Profile (PDF)</span>
              <div class="file-upload-container">
                <input 
                  type="file" 
                  accept=".pdf"
                  @change="(e) => handleCompanyFileUpload(e, 'organizationProfile')"
                  class="file-input"
                  id="company-profile-upload"
                />
                <label for="company-profile-upload" class="file-upload-btn">
                  <span class="upload-icon">📄</span>
                  <span v-if="!formData.organizationProfile">Choose file</span>
                  <span v-else>{{ formData.organizationProfile.name }}</span>
                </label>
                <p class="file-help-text">Brief company profile or brochure</p>
              </div>
            </label>
          </div>

          <div v-else-if="selectedRole === 'school'" class="role-specific-fields">
            <label class="field">
              <span>Accreditation Level (optional)</span>
              <select v-model="formData.schoolAccreditation" class="select-input">
                <option value="">Select accreditation</option>
                <option value="Level I">Level I</option>
                <option value="Level II">Level II</option>
                <option value="Level III">Level III</option>
                <option value="Level IV">Level IV</option>
                <option value="COE">Center of Excellence</option>
                <option value="COD">Center of Development</option>
              </select>
            </label>

            <label class="field">
              <span>Registrar/Authorized Officer Name</span>
              <input v-model="formData.registrarName" type="text" placeholder="Enter authorized officer name" />
            </label>

            <label class="field">
              <span>Registrar/Authorized Officer Email</span>
              <input v-model="formData.registrarEmail" type="email" placeholder="Enter authorized officer email" />
            </label>

            <label class="field">
              <span>School Address</span>
              <textarea 
                v-model="formData.schoolAddress" 
                placeholder="Enter your complete school address"
                class="textarea-input"
                rows="3"
              ></textarea>
            </label>

            <label class="field file-field">
              <span>School Permit / Certificate (PDF/JPG)</span>
              <div class="file-upload-container">
                <input 
                  type="file" 
                  accept=".pdf,.jpg,.jpeg,.png"
                  @change="(e) => handleSchoolFileUpload(e, 'schoolPermit')"
                  class="file-input"
                  id="school-permit-upload"
                />
                <label for="school-permit-upload" class="file-upload-btn">
                  <span class="upload-icon">📄</span>
                  <span v-if="!formData.schoolPermit">Choose file</span>
                  <span v-else>{{ formData.schoolPermit.name }}</span>
                </label>
                <p class="file-help-text">Upload school permit or certificate</p>
              </div>
            </label>

            <label class="field file-field">
              <span>Endorsement Letter (PDF)</span>
              <div class="file-upload-container">
                <input 
                  type="file" 
                  accept=".pdf"
                  @change="(e) => handleSchoolFileUpload(e, 'endorsementLetter')"
                  class="file-input"
                  id="endorsement-letter-upload"
                />
                <label for="endorsement-letter-upload" class="file-upload-btn">
                  <span class="upload-icon">📄</span>
                  <span v-if="!formData.endorsementLetter">Choose file</span>
                  <span v-else>{{ formData.endorsementLetter.name }}</span>
                </label>
                <p class="file-help-text">Signed by authorized officer</p>
              </div>
            </label>
          </div>
        </div>

        <div v-else-if="currentStep === 5">
          <div class="section-label">Subscription</div>

          <div v-if="selectedRole === 'student'" class="role-specific-fields">
            <p class="subtitle">Student access is based on your school's active subscription.</p>
          </div>

          <div v-else class="role-specific-fields">
            <div class="role-grid">
              <button
                type="button"
                class="role-card"
                :class="{ active: formData.subscriptionPlan === 'free' }"
                @click="formData.subscriptionPlan = 'free'"
              >
                <div class="role-title">Free</div>
                <p>Basic access for students and small teams</p>
                <div class="price-tag">₱0 / month</div>
              </button>
              <button
                type="button"
                class="role-card"
                :class="{ active: formData.subscriptionPlan === 'standard' }"
                @click="formData.subscriptionPlan = 'standard'"
              >
                <div class="role-title">Standard</div>
                <p>Includes analytics and priority support</p>
                <div class="price-tag">₱1,499 / month</div>
              </button>
              <button
                type="button"
                class="role-card"
                :class="{ active: formData.subscriptionPlan === 'premium' }"
                @click="formData.subscriptionPlan = 'premium'"
              >
                <div class="role-title">Premium</div>
                <p>Full suite + DSS weight customization</p>
                <div class="price-tag">₱2,999 / month</div>
              </button>
            </div>

            <label class="field">
              <span>Billing Cycle</span>
              <select v-model="formData.billingCycle" class="select-input">
                <option value="monthly">Monthly</option>
                <option value="annual">Annual (save 20%)</option>
              </select>
            </label>
          </div>

          <label class="terms">
            <input v-model="agreed" type="checkbox" />
            <span>
              I agree to the
              <a href="#">Terms &amp; Conditions</a>
            </span>
          </label>

          <button type="submit" class="primary-btn" :disabled="!isStepValid()">Create Account</button>
        </div>

        <div class="step-actions">
          <button type="button" class="step-btn ghost" @click="goBack" :disabled="currentStep === 1">
            Back
          </button>
          <button type="button" class="step-btn primary" @click="goNext" v-if="currentStep < totalSteps" :disabled="!isStepValid()">
            Next
          </button>
        </div>
      </form>

      <p class="footer-link">
        Already have an account?
        <RouterLink to="/login">Login</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped src="../styles/Register.css">
</style>


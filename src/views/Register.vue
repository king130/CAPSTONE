<script setup lang="ts">
import { ref } from 'vue'
import Swal from 'sweetalert2'
import { RouterLink, useRouter } from 'vue-router'

const selectedRole = ref<'student' | 'school' | 'company' | null>(null)
const agreed = ref(false)

// Form data
const formData = ref({
  fullName: '',
  email: '',
  password: '',
  confirmPassword: '',
  // Student-specific fields
  studentId: '',
  schoolName: '',
  course: '',
  yearLevel: '',
  preferredField: '',
  resume: null as File | null,
  contactNumber: '',
  // Company-specific fields
  companyName: '',
  industryType: '',
  companyAddress: '',
  companyEmail: '',
  contactPersonName: '',
  companyContactNumber: '',
  website: '',
  internshipDescription: '',
  // School-specific fields
  institutionName: '',
  department: '',
  position: '',
  officialSchoolEmail: '',
  schoolContactNumber: '',
  schoolAddress: ''
})

const router = useRouter()

function handleFileUpload(event: Event) {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    formData.value.resume = target.files[0]
  }
}

async function onRegister() {
  const result = await Swal.fire({
    icon: 'success',
    iconColor: '#16a34a',
    title: 'Account Created\nSuccessfully!',
    text: 'You can now login with your new credentials.',
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
    await router.push('/')
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
          <input v-model="formData.password" type="password" placeholder="Create a password" />
        </label>

        <label class="field">
          <span>Confirm Password</span>
          <input v-model="formData.confirmPassword" type="password" placeholder="Re-enter your password" />
        </label>

        <!-- Student Role - Additional Fields -->
        <div v-if="selectedRole === 'student'" class="role-specific-fields">
          <div class="section-label">Student Information</div>
          
          <label class="field">
            <span>Student ID Number</span>
            <input v-model="formData.studentId" type="text" placeholder="Enter your student ID" />
          </label>

          <label class="field">
            <span>School / University Name</span>
            <input v-model="formData.schoolName" type="text" placeholder="Enter your school name" />
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
        </div>

        <!-- Company Role - Additional Fields -->
        <div v-if="selectedRole === 'company'" class="role-specific-fields">
          <div class="section-label">Company Information</div>
          
          <label class="field">
            <span>Company Name</span>
            <input v-model="formData.companyName" type="text" placeholder="Enter your company name" />
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
            <span>Contact Number</span>
            <input v-model="formData.companyContactNumber" type="tel" placeholder="Enter company contact number" />
          </label>

          <label class="field">
            <span>Website / LinkedIn</span>
            <input v-model="formData.website" type="url" placeholder="Enter company website or LinkedIn URL" />
          </label>

          <label class="field">
            <span>Internship Description (short)</span>
            <textarea 
              v-model="formData.internshipDescription" 
              placeholder="Briefly describe the internship opportunities your company offers"
              class="textarea-input"
              rows="4"
            ></textarea>
          </label>
        </div>

        <!-- School Role - Additional Fields -->
        <div v-if="selectedRole === 'school'" class="role-specific-fields">
          <div class="section-label">Institution Information</div>
          
          <label class="field">
            <span>Institution Name</span>
            <input v-model="formData.institutionName" type="text" placeholder="Enter your institution name" />
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
            <span>Contact Number</span>
            <input v-model="formData.schoolContactNumber" type="tel" placeholder="Enter your contact number" />
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
        </div>

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

        <label class="terms">
          <input v-model="agreed" type="checkbox" />
          <span>
            I agree to the
            <a href="#">Terms &amp; Conditions</a>
          </span>
        </label>

        <button type="submit" class="primary-btn">Create Account</button>
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


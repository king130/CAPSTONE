<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Swal from '@/services/swal'

const router = useRouter()
const authStore = useAuthStore()
const selectedRole = ref<'student' | 'school' | 'company' | null>(null)
const loading = ref(false)

async function selectRole() {
  if (!selectedRole.value || !authStore.user) return

  // School/company must use registration (POST /auth/register), not profile role promotion.
  if (selectedRole.value === 'school' || selectedRole.value === 'company') {
    const registerPath = selectedRole.value === 'school' ? '/register/school' : '/register/company'
    loading.value = true
    try {
      // RegisterSimple redirects logged-in guests away; clear session so registration can proceed.
      await authStore.logout()
      await router.push(registerPath)
    } finally {
      loading.value = false
    }
    return
  }

  // Students cannot self-promote. Accounts are school-provisioned only.
  loading.value = true
  try {
    await Swal.fire({
      icon: 'info',
      title: 'School-issued student accounts',
      text: 'Student accounts are created by your school. Use the setup link from your school email, or log in with your school-issued credentials.',
      confirmButtonColor: '#2563eb',
    })
    await authStore.logout()
    await router.push({ name: 'login' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="role-selection-page">
    <div class="role-selection-container">
      <div class="header">
        <img src="/icons/logo-main.png" alt="OJT Path" class="logo" />
        <h1>Choose Your Account Type</h1>
        <p>Select how you'll be using OJT Path</p>
      </div>

      <div class="role-grid">
        <button
          type="button"
          class="role-card"
          :class="{ active: selectedRole === 'student' }"
          @click="selectedRole = 'student'"
        >
          <div class="role-icon">👨‍🎓</div>
          <h3>Student</h3>
          <p>I already received school-issued login credentials</p>
          <ul class="features-list">
            <li>Use your school setup email/link</li>
            <li>Log in with school-issued credentials</li>
            <li>Browse eligible internship listings</li>
            <li>Track applications and OJT progress</li>
          </ul>
        </button>

        <button
          type="button"
          class="role-card"
          :class="{ active: selectedRole === 'school' }"
          @click="selectedRole = 'school'"
        >
          <div class="role-icon">🏫</div>
          <h3>School / Institution</h3>
          <p>I manage students and coordinate placements</p>
          <ul class="features-list">
            <li>Manage student interns</li>
            <li>Track placements</li>
            <li>Generate reports</li>
            <li>Partner with companies</li>
          </ul>
        </button>

        <button
          type="button"
          class="role-card"
          :class="{ active: selectedRole === 'company' }"
          @click="selectedRole = 'company'"
        >
          <div class="role-icon">🏢</div>
          <h3>Company / Employer</h3>
          <p>I'm offering internship opportunities</p>
          <ul class="features-list">
            <li>Post internship positions</li>
            <li>Review applications</li>
            <li>Manage interns</li>
            <li>Track performance</li>
          </ul>
        </button>
      </div>

      <button
        @click="selectRole"
        class="continue-btn"
        :disabled="!selectedRole || loading"
      >
        {{
          loading
            ? 'Processing...'
            : selectedRole === 'student'
              ? 'Go to Student Login'
              : 'Continue'
        }}
      </button>

      <p class="help-text">
        Students use school-issued accounts. Schools and companies register their organization here.
      </p>
    </div>
  </div>
</template>

<style scoped>
.role-selection-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  font-family: Inter, system-ui, -apple-system, sans-serif;
}

.role-selection-container {
  background: white;
  border-radius: 16px;
  padding: 3rem;
  max-width: 1200px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.header {
  text-align: center;
  margin-bottom: 3rem;
}

.logo {
  width: 64px;
  height: 64px;
  margin-bottom: 1.5rem;
  border-radius: 50%;
}

.header h1 {
  font-size: 2.5rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.5rem 0;
}

.header p {
  font-size: 1.125rem;
  color: #6b7280;
  margin: 0;
}

.role-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
  margin-bottom: 2rem;
}

.role-card {
  background: white;
  border: 3px solid #e5e7eb;
  border-radius: 12px;
  padding: 2rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s;
}

.role-card:hover {
  border-color: #667eea;
  transform: translateY(-4px);
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
}

.role-card.active {
  border-color: #667eea;
  background: #f0f4ff;
}

.role-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.role-card h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.5rem 0;
}

.role-card p {
  font-size: 0.95rem;
  color: #6b7280;
  margin: 0 0 1.5rem 0;
}

.features-list {
  list-style: none;
  padding: 0;
  margin: 0;
  text-align: left;
}

.features-list li {
  padding: 0.5rem 0;
  color: #374151;
  font-size: 0.9rem;
  position: relative;
  padding-left: 1.5rem;
}

.features-list li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: #667eea;
  font-weight: bold;
}

.continue-btn {
  width: 100%;
  padding: 1rem 2rem;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1.125rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
}

.continue-btn:hover:not(:disabled) {
  background: #5568d3;
  transform: translateY(-2px);
  box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
}

.continue-btn:disabled {
  background: #d1d5db;
  cursor: not-allowed;
}

.help-text {
  text-align: center;
  margin-top: 1.5rem;
  color: #6b7280;
  font-size: 0.9rem;
}

@media (max-width: 768px) {
  .role-selection-container {
    padding: 2rem 1.5rem;
  }

  .header h1 {
    font-size: 1.75rem;
  }

  .role-grid {
    grid-template-columns: 1fr;
  }
}
</style>

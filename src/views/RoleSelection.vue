<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { doc, updateDoc } from 'firebase/firestore'
import { db } from '@/services/firebase'
import { ensurePublicProfile } from '@/services/profilesPublic'
import Swal from 'sweetalert2'

const router = useRouter()
const authStore = useAuthStore()
const selectedRole = ref<'student' | 'school' | 'company' | null>(null)
const loading = ref(false)

async function selectRole() {
  if (!selectedRole.value || !authStore.user) return

  loading.value = true

  try {
    const updates: Record<string, unknown> = {
      role: selectedRole.value,
      profileSetupComplete: selectedRole.value === 'student',
      updatedAt: new Date()
    }

    await updateDoc(doc(db, 'users', authStore.user!.uid), updates)

    if (selectedRole.value === 'school' || selectedRole.value === 'company') {
      const profile = (authStore.user?.profile as Record<string, unknown>) || {}
      const orgName = selectedRole.value === 'school'
        ? (profile.institutionName as string | undefined)
        : (profile.companyName as string | undefined)

      await ensurePublicProfile(authStore.user.uid, {
        displayName: authStore.user.displayName || authStore.user.email || 'User',
        role: selectedRole.value,
        orgName: orgName || authStore.user.displayName || undefined,
        email: authStore.user.email || undefined,
      })
    }

    await Swal.fire({
      icon: 'success',
      title: 'Role Selected!',
      text: selectedRole.value === 'student'
        ? "You're now registered as a student. Use this email and your password to log in."
        : `You're now registered as a ${selectedRole.value}. Let's complete your profile.`,
      confirmButtonColor: '#2563eb'
    })

    if (selectedRole.value === 'student') {
      router.push('/intern')
    } else {
      router.push('/profile')
    }
  } catch (error) {
    console.error('Error selecting role:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to update your role. Please try again.',
      confirmButtonColor: '#2563eb'
    })
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
          <p>I'm looking for internship or OJT opportunities</p>
          <ul class="features-list">
            <li>Browse internship listings</li>
            <li>Apply to opportunities</li>
            <li>Track your progress</li>
            <li>Connect with companies</li>
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
        {{ loading ? 'Processing...' : 'Continue' }}
      </button>

      <p class="help-text">
        You can change this later in your account settings
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
  border-color: #2563eb;
  transform: translateY(-4px);
  box-shadow: 0 12px 24px rgba(37, 99, 235, 0.2);
}

.role-card.active {
  border-color: #2563eb;
  background: #eff6ff;
  box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
}

.role-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.role-card h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 0.5rem 0;
}

.role-card p {
  font-size: 1rem;
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
  font-size: 0.875rem;
  color: #374151;
  padding: 0.5rem 0;
  padding-left: 1.5rem;
  position: relative;
}

.features-list li::before {
  content: '✓';
  position: absolute;
  left: 0;
  color: #16a34a;
  font-weight: 700;
}

.continue-btn {
  width: 100%;
  max-width: 400px;
  display: block;
  margin: 0 auto 1rem;
  padding: 1rem 2rem;
  background: #2563eb;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1.125rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.continue-btn:hover:not(:disabled) {
  background: #1d4ed8;
}

.continue-btn:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
  opacity: 0.6;
}

.help-text {
  text-align: center;
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

@media (max-width: 768px) {
  .role-selection-container {
    padding: 2rem 1.5rem;
  }
  
  .header h1 {
    font-size: 2rem;
  }
  
  .role-grid {
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
}
</style>

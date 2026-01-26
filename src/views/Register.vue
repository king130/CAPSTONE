<script setup lang="ts">
import { ref } from 'vue'
import Swal from 'sweetalert2'
import { RouterLink, useRouter } from 'vue-router'

const selectedRole = ref<'student' | 'school' | 'company' | null>(null)
const agreed = ref(false)

const router = useRouter()

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
          <input type="text" placeholder="Enter your full name" />
        </label>

        <label class="field">
          <span>Email Address</span>
          <input type="email" placeholder="Enter your email address" />
        </label>

        <label class="field">
          <span>Password</span>
          <input type="password" placeholder="Create a password" />
        </label>

        <label class="field">
          <span>Confirm Password</span>
          <input type="password" placeholder="Re-enter your password" />
        </label>

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


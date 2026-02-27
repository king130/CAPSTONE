<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import Navbar from '@/components/Navbar.vue'
import { subscribeActiveInternships, type InternshipRecord } from '@/services/internships'

const internships = ref<Array<InternshipRecord & { logo?: string; match?: number; company?: string; skills?: string[] }>>([])
let unsub: (() => void) | null = null

onMounted(() => {
  unsub = subscribeActiveInternships((items) => {
    internships.value = items.map((i) => ({
      ...i,
      logo: (i.companyName || 'CO').slice(0, 2).toUpperCase(),
      company: i.companyName,
      match: 85,
      skills: i.requirements || [],
    }))
  })
})

onUnmounted(() => {
  if (unsub) unsub()
})
</script>

<template>
  <div class="page">
    <Navbar />

    <main>
      <!-- Banner Section -->
      <section class="banner">
        <div class="banner-content">
          <h1>Find the Right Internship</h1>
          <p>
            Find the right internship that is aligned with the student's academic
            background and interest to ensure meaningful learning, skill.
          </p>
        </div>
      </section>

      <!-- Available Internships Section -->
      <section class="main-content">
        <!-- Header with Search -->
        <div class="section-header">
          <h2 class="section-title">
            <span class="check-icon">✓</span>
            Available Internships
          </h2>
          <div class="search-container">
            <input
              type="text"
              placeholder="Internship title or keyword"
              class="search-input"
            />
            <span class="search-icon">🔍</span>
          </div>
        </div>

        <!-- Internship Cards Grid -->
        <div class="cards-grid-full">
          <div
            v-for="internship in internships"
            :key="internship.id"
            class="internship-card"
          >
            <div class="card-header">
              <div class="company-logo">{{ internship.logo }}</div>
              <div class="card-title-group">
                <h4>{{ internship.title }}</h4>
                <p class="company-name">{{ internship.company }}</p>
                <p class="location">
                  <span class="pin-icon">📍</span>
                  {{ internship.location }}
                </p>
              </div>
              <div class="match-badge">{{ internship.match }}% Match</div>
            </div>

            <p class="card-description">{{ internship.description }}</p>

            <div class="skills-tags">
              <span
                v-for="skill in internship.skills"
                :key="skill"
                class="skill-tag"
              >
                {{ skill }}
              </span>
            </div>

            <div class="card-actions">
              <button class="btn-quick-apply">Quick Apply</button>
              <button class="btn-view-details">View Details</button>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-logo">OJT Path</div>
        <div class="footer-copyright">© 2026 OJT PATH Inc. All rights reserved.</div>
        <div class="footer-links">
          <a href="#">Privacy</a>
          <a href="#">Term of Service</a>
          <a href="#">Help Center</a>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped src="../styles/FindInternships.css">
</style>
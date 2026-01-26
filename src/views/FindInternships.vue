<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

const activeFilters = ref(['Software Engineer', 'UI/UX Designer', 'Web Developer'])
const selectedFields = ref<string[]>([])
const location = ref('')

const internships = ref([
  {
    id: 1,
    title: 'Software Engineer Intern',
    company: 'Innovate Corp.',
    location: 'New York, NY',
    match: 92,
    description: 'Join Our dynamic team to develop innovative software solutions. You\'ll work on cutting-edge projects and contribute the projects.',
    skills: ['Python', 'AWS', 'Machine Learning', 'Django'],
    logo: 'IC'
  },
  {
    id: 2,
    title: 'Data Analyst Intern',
    company: 'DataTech Solutions',
    location: 'San Francisco, CA',
    match: 88,
    description: 'Analyze large datasets and create insights for business decisions. Work with cross-functional teams on data-driven projects.',
    skills: ['Python', 'SQL', 'Machine Learning', 'Excel'],
    logo: 'DT'
  },
  {
    id: 3,
    title: 'UI/UX Designer Intern',
    company: 'Design Studio Pro',
    location: 'Los Angeles, CA',
    match: 85,
    description: 'Create beautiful and intuitive user interfaces. Collaborate with product teams to design user-centered experiences.',
    skills: ['Figma', 'Prototyping', 'Design System'],
    logo: 'DS'
  },
  {
    id: 4,
    title: 'Business Development Intern',
    company: 'Growth Ventures',
    location: 'Chicago, IL',
    match: 90,
    description: 'Support business growth initiatives and client relationships. Assist in market research and strategic planning.',
    skills: ['Sales', 'Marketing', 'CRM', 'Analytics'],
    logo: 'GV'
  }
])

function removeFilter(filter: string) {
  activeFilters.value = activeFilters.value.filter(f => f !== filter)
}

function clearAllFilters() {
  selectedFields.value = []
  location.value = ''
}
</script>

<template>
  <div class="page">
    <!-- Header -->
    <header class="nav">
      
      <div class="logo">OJT Path</div>
      <nav class="links">
        <a href="#features">Features</a>
        <a href="#how-it-works">How it works</a>
        <a href="#community">Community</a>
        <RouterLink to="/find-internships">Find Internships</RouterLink>
      </nav>
      <RouterLink to="/login" class="btn btn-primary">Log in</RouterLink>
    </header>

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
          
          <!-- Active Filter Tags -->
          <div class="filter-tags" v-if="activeFilters.length > 0">
            <span
              v-for="filter in activeFilters"
              :key="filter"
              class="filter-tag"
            >
              {{ filter }}
              <button @click="removeFilter(filter)" class="tag-close">×</button>
            </span>
          </div>
        </div>

        <!-- Main Grid: Filters + Cards -->
        <div class="content-grid">
          <!-- Filter Sidebar -->
          <aside class="filter-sidebar">
            <div class="filter-section">
              <div class="filter-header">
                <h3>Internship Fields</h3>
                <button @click="clearAllFilters" class="clear-link">Clear all</button>
              </div>
              <div class="checkbox-group">
                <label v-for="field in ['IT & Software', 'Business & Management', 'Engineering', 'Education', 'Hospitality']" :key="field">
                  <input
                    type="checkbox"
                    :value="field"
                    v-model="selectedFields"
                  />
                  <span>{{ field }}</span>
                </label>
              </div>
            </div>

            <div class="filter-section">
              <h3>Location</h3>
              <input
                type="text"
                v-model="location"
                placeholder="e.g. Imus, Cavite"
                class="location-input"
              />
            </div>

            <button class="apply-filters-btn">Apply filters</button>
          </aside>

          <!-- Internship Cards Grid -->
          <div class="cards-grid">
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
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-logo">OJT Path</div>
        <div class="footer-copyright">© 2024 OJT PATH Inc. All rights reserved.</div>
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
<template>
  <header :class="['nav', backgroundStyle]">
    <div class="logo">
      <img src="/icons/logo-main.png" alt="OJT Path" class="logo-icon" />
      <span class="logo-text">OJT Path</span>
    </div>
    <nav class="links">
      <a href="#why">Why Us</a>
      <a href="#features">Features</a>
      <a href="#steps">How It Works</a>
      <RouterLink to="/find-internships">Find Internships</RouterLink>
    </nav>
    <RouterLink to="/login" :class="['btn', buttonStyle]">{{ buttonText }}</RouterLink>
  </header>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'

// Props to customize the navbar
interface Props {
  buttonStyle?: 'btn-outline' | 'btn-primary'
  buttonText?: string
  backgroundStyle?: 'nav-transparent' | 'nav-solid'
}

const props = withDefaults(defineProps<Props>(), {
  buttonStyle: 'btn-outline',
  buttonText: 'Login',
  backgroundStyle: 'nav-transparent'
})
</script>

<style scoped>
.nav {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 32px;
  border-bottom: 1px solid #e5e7eb;
}

.nav-transparent {
  background: #ffffffcc;
  backdrop-filter: blur(10px);
}

.nav-solid {
  background: #ffffff;
}

.logo {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  color: #2563eb;
}

.logo-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
}

.logo-text {
  font-size: 18px;
}

.links {
  display: flex;
  gap: 18px;
  align-items: center;
}

.links a {
  text-decoration: none;
  color: #1f2937;
  font-weight: 500;
  font-size: 14px;
  transition: color 0.2s;
}

.links a:hover {
  color: #2563eb;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-outline {
  border: 1px solid #2563eb;
  color: #2563eb;
  background: #fff;
}

.btn-outline:hover {
  background: #eff6ff;
}

.btn-primary {
  background: #2563eb;
  color: #fff;
}

.btn-primary:hover {
  background: #1d4ed8;
}

/* Responsive */
@media (max-width: 640px) {
  .nav {
    flex-wrap: wrap;
    gap: 10px;
  }
  .links {
    width: 100%;
    justify-content: center;
    flex-wrap: wrap;
  }
}
</style>
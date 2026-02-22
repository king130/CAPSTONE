<template>
  <header :class="['nav', backgroundStyle]">
    <div class="logo">
      <img src="/icons/logo-main.png" alt="OJT Path" class="logo-icon" />
      <span class="logo-text">OJT Path</span>
    </div>
    <button class="menu-toggle" type="button" @click="isMenuOpen = !isMenuOpen">
      ☰
    </button>
    <nav :class="['links', { open: isMenuOpen }]">
      <a href="#why">Why Us</a>
      <a href="#pricing">Subscriptions</a>
      <RouterLink to="/find-internships">Find Internships</RouterLink>
    </nav>
    <div class="nav-actions">
      <RouterLink to="/register" class="btn btn-primary">Register</RouterLink>
      <RouterLink to="/login" :class="['btn', buttonStyle]">{{ buttonText }}</RouterLink>
    </div>
  </header>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { ref } from 'vue'

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

const isMenuOpen = ref(false)
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
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
}

.logo-text {
  font-size: 20px;
}

.links {
  display: flex;
  gap: 18px;
  align-items: center;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.menu-toggle {
  display: none;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  padding: 6px 10px;
  cursor: pointer;
  font-size: 16px;
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
  padding: 10px 24px;
  border-radius: 24px;
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
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}

.btn-primary {
  background: #2563eb;
  color: #fff;
}

.btn-primary:hover {
  background: #1d4ed8;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
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
    display: none;
  }
  .links.open {
    display: flex;
  }
  .menu-toggle {
    display: inline-flex;
  }
}
</style>
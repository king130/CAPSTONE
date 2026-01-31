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
      <a href="#features">Features</a>
      <a href="#pricing">Pricing</a>
      <a href="#steps">How It Works</a>
      <RouterLink to="/find-internships">Find Internships</RouterLink>
    </nav>
    <div class="nav-actions">
      <div v-if="authStore.user" class="profile-chip">
        <div class="bell" role="button" @click="notificationStore.markAllRead()" title="Mark notifications as read">
          🔔
          <span v-if="notificationStore.unreadCount()" class="badge">{{ notificationStore.unreadCount() }}</span>
        </div>
        <div class="avatar" :title="authStore.user.displayName">
          <img v-if="avatarUrl" :src="avatarUrl" :alt="authStore.user.displayName" />
          <span v-else>{{ userInitials }}</span>
        </div>
      </div>
      <RouterLink v-else to="/login" :class="['btn', buttonStyle]">{{ buttonText }}</RouterLink>
    </div>
  </header>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'

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

const authStore = useAuthStore()
const notificationStore = useNotificationStore()
const isMenuOpen = ref(false)

const avatarUrl = computed(() => {
  const profile = authStore.user?.profile as Record<string, unknown> | undefined
  return (profile?.photoUrl as string) || ''
})

const userInitials = computed(() => {
  const name = authStore.user?.displayName || 'User'
  return name
    .split(' ')
    .filter(Boolean)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
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

.nav-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.profile-chip {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 13px;
  color: #1f2937;
}

.bell {
  position: relative;
  font-size: 16px;
}

.badge {
  position: absolute;
  top: -6px;
  right: -8px;
  background: #ef4444;
  color: #fff;
  border-radius: 999px;
  font-size: 10px;
  padding: 2px 6px;
}

.avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: #2563eb;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  font-size: 12px;
  font-weight: 600;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
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
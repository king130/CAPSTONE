<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

async function handleLogout() {
  try {
    await authStore.logout()
  } catch (e) {
    console.warn('Logout error:', e)
  }
  router.push('/')
}

function goToProfile() {
  router.push('/profile')
}
</script>

<template>
  <header class="admin-nav">
    <div class="nav-container">
      <div class="brand-section">
        <div class="brand-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="brand-text">
          <div class="brand-title">Admin Console</div>
          <div class="brand-subtitle">System Management</div>
        </div>
      </div>
      
      <nav class="nav-links">
        <RouterLink to="/admin/overview" class="nav-link">
          <svg class="nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
            <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
            <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
            <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
          </svg>
          Dashboard
        </RouterLink>
        <RouterLink to="/admin/users" class="nav-link">
          <svg class="nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
          </svg>
          Users
        </RouterLink>
        <RouterLink to="/admin/temporary" class="nav-link">
          <svg class="nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
          </svg>
          Temporary Accounts
        </RouterLink>
        <RouterLink to="/admin/pricing" class="nav-link">
          <svg class="nav-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <path d="M16 8L8 16" stroke="currentColor" stroke-width="2"/>
            <path d="M8 8H16V16" stroke="currentColor" stroke-width="2"/>
          </svg>
          Pricing
        </RouterLink>
      </nav>
      
      <div class="nav-actions">
        <button class="admin-profile" @click="goToProfile" type="button" title="View Profile">
          <div class="admin-avatar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
            </svg>
          </div>
          <span class="admin-label">Admin</span>
        </button>
        <button class="logout-btn" type="button" @click="handleLogout">
          <svg class="logout-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M9 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H9" stroke="currentColor" stroke-width="2"/>
            <polyline points="16,17 21,12 16,7" stroke="currentColor" stroke-width="2"/>
            <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2"/>
          </svg>
          Logout
        </button>
      </div>
    </div>
  </header>
</template>

<style scoped>
.admin-nav {
  background: #3b4cb8;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  width: 100%;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  box-sizing: border-box;
  height: 60px;
  min-height: 60px;
  max-height: 60px;
}

.nav-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  height: 60px;
  min-height: 60px;
}

.brand-section {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  height: 100%;
}

.brand-icon {
  width: 32px;
  height: 32px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.brand-text {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.brand-title {
  font-size: 16px;
  font-weight: 600;
  color: #fff;
  line-height: 1.2;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  margin: 0;
}

.brand-subtitle {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.7);
  font-weight: 400;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  margin: 0;
  line-height: 1.2;
}

.nav-links {
  display: flex;
  gap: 4px;
  flex: 1;
  justify-content: center;
  max-width: 600px;
  align-items: center;
  height: 100%;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  transition: all 0.2s ease;
  white-space: nowrap;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  height: 36px;
  box-sizing: border-box;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

.nav-link.router-link-active {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
}

.nav-icon {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
}

.nav-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  height: 100%;
}

.admin-profile {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 6px;
  color: rgba(255, 255, 255, 0.9);
  height: 32px;
  box-sizing: border-box;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.admin-profile:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
}

.admin-avatar {
  width: 20px;
  height: 20px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.admin-label {
  font-size: 12px;
  font-weight: 500;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  line-height: 1;
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  background: #dc2626;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  height: 36px;
  box-sizing: border-box;
}

.logout-btn:hover {
  background: #b91c1c;
}

.logout-icon {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
}

/* Responsive */
@media (max-width: 768px) {
  .admin-nav {
    height: auto;
    min-height: 60px;
    max-height: none;
  }
  
  .nav-container {
    padding: 10px 16px;
    flex-wrap: wrap;
    gap: 8px;
    height: auto;
    min-height: 60px;
  }
  
  .brand-subtitle {
    display: none;
  }
  
  .nav-links {
    order: 3;
    width: 100%;
    justify-content: flex-start;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    max-width: none;
    height: auto;
  }
  
  .nav-link {
    font-size: 12px;
    padding: 6px 10px;
    height: 32px;
  }
  
  .admin-label {
    display: none;
  }
  
  .logout-btn {
    height: 32px;
    padding: 6px 10px;
  }
  
  .admin-profile {
    height: 28px;
    padding: 4px 8px;
  }
}
</style>

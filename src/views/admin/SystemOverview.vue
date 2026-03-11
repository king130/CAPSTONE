<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { collection, getDocs, query, where } from 'firebase/firestore'
import { db } from '@/services/firebase'
import Swal from 'sweetalert2'

const totalUsers = ref(0)
const activeUsers = ref(0)
const tempUsers = ref(0)
const activeInternships = ref(0)
const sessions = ref('N/A')
const isRefreshing = ref(false)

async function loadMetrics() {
  isRefreshing.value = true
  
  try {
    const usersRef = collection(db, 'users')
    const totalSnap = await getDocs(usersRef)
    totalUsers.value = totalSnap.size

    const activeSnap = await getDocs(query(usersRef, where('isActive', '==', true)))
    activeUsers.value = activeSnap.size

    const tempSnap = await getDocs(query(usersRef, where('isTemporary', '==', true)))
    tempUsers.value = tempSnap.size

    const internshipsRef = collection(db, 'internships')
    const internshipsSnap = await getDocs(internshipsRef)
    activeInternships.value = internshipsSnap.size
    
    // Simulate a small delay to show loading state
    await new Promise(resolve => setTimeout(resolve, 500))
    
    // Show success notification
    await Swal.fire({
      icon: 'success',
      title: 'Data Refreshed',
      text: 'System metrics have been updated successfully.',
      timer: 2000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end'
    })
  } catch (error) {
    console.error('Error loading metrics:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Refresh Failed',
      text: 'Unable to refresh system metrics. Please try again.',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    isRefreshing.value = false
  }
}

onMounted(() => {
  loadMetrics()
})
</script>

<template>
  <section class="admin-page">
    <div class="page-header">
      <div class="header-content">
        <div class="header-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
            <rect x="14" y="3" width="7" height="7" stroke="currentColor" stroke-width="2"/>
            <rect x="14" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
            <rect x="3" y="14" width="7" height="7" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="header-text">
          <h1 class="page-title">System Overview</h1>
          <p class="page-subtitle">Monitor key metrics and system performance at a glance</p>
        </div>
      </div>
      <button class="refresh-btn" type="button" @click="loadMetrics" :disabled="isRefreshing">
        <svg v-if="!isRefreshing" class="refresh-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
          <polyline points="23,4 23,10 17,10" stroke="currentColor" stroke-width="2"/>
          <polyline points="1,20 1,14 7,14" stroke="currentColor" stroke-width="2"/>
          <path d="M20.49,9A9,9,0,0,0,5.64,5.64L1,10m22,4L18.36,18.36A9,9,0,0,1,3.51,15" stroke="currentColor" stroke-width="2"/>
        </svg>
        <svg v-else class="refresh-icon spinning" width="16" height="16" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.416" stroke-dashoffset="31.416">
            <animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416" repeatCount="indefinite"/>
            <animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416" repeatCount="indefinite"/>
          </circle>
        </svg>
        {{ isRefreshing ? 'Refreshing...' : 'Refresh Data' }}
      </button>
    </div>

    <div class="metrics-grid">
      <div class="metric-card primary">
        <div class="metric-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="metric-content">
          <div class="metric-label">Total Users</div>
          <div class="metric-value">{{ totalUsers }}</div>
          <div class="metric-trend">
            <span class="trend-indicator positive">+12%</span>
            <span class="trend-text">vs last month</span>
          </div>
        </div>
      </div>

      <div class="metric-card success">
        <div class="metric-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4905 2.02168 11.3363C2.16356 9.18218 2.99721 7.13677 4.39828 5.49707C5.79935 3.85736 7.69279 2.71548 9.79619 2.24224C11.8996 1.77 14.1003 1.98806 16.07 2.86" stroke="currentColor" stroke-width="2"/>
            <polyline points="22,4 12,14.01 9,11.01" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="metric-content">
          <div class="metric-label">Active Users</div>
          <div class="metric-value">{{ activeUsers }}</div>
          <div class="metric-trend">
            <span class="trend-indicator positive">+8%</span>
            <span class="trend-text">vs last month</span>
          </div>
        </div>
      </div>

      <div class="metric-card warning">
        <div class="metric-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="metric-content">
          <div class="metric-label">Temporary Accounts</div>
          <div class="metric-value">{{ tempUsers }}</div>
          <div class="metric-trend">
            <span class="trend-indicator neutral">0%</span>
            <span class="trend-text">vs last month</span>
          </div>
        </div>
      </div>

      <div class="metric-card info">
        <div class="metric-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
            <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2"/>
            <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="metric-content">
          <div class="metric-label">Active Internships</div>
          <div class="metric-value">{{ activeInternships }}</div>
          <div class="metric-trend">
            <span class="trend-indicator positive">+15%</span>
            <span class="trend-text">vs last month</span>
          </div>
        </div>
      </div>

      <div class="metric-card secondary">
        <div class="metric-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" stroke-width="2"/>
            <path d="M8 14S9.5 16 12 16S16 14 16 14" stroke="currentColor" stroke-width="2"/>
            <line x1="9" y1="9" x2="9.01" y2="9" stroke="currentColor" stroke-width="2"/>
            <line x1="15" y1="9" x2="15.01" y2="9" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="metric-content">
          <div class="metric-label">Active Sessions</div>
          <div class="metric-value">{{ sessions }}</div>
          <div class="metric-trend">
            <span class="trend-indicator neutral">—</span>
            <span class="trend-text">Real-time data</span>
          </div>
        </div>
      </div>
    </div>

    <div class="quick-actions">
      <h2 class="section-title">Quick Actions</h2>
      <div class="actions-grid">
        <RouterLink to="/admin/users" class="action-card">
          <div class="action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
              <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
            </svg>
          </div>
          <div class="action-content">
            <h3 class="action-title">Manage Users</h3>
            <p class="action-description">View and manage user accounts, roles, and permissions</p>
          </div>
        </RouterLink>

        <RouterLink to="/admin/temporary" class="action-card">
          <div class="action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
              <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
            </svg>
          </div>
          <div class="action-content">
            <h3 class="action-title">Temporary Accounts</h3>
            <p class="action-description">Monitor and control temporary access accounts</p>
          </div>
        </RouterLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
.admin-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  padding: 20px 24px;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon {
  width: 40px;
  height: 40px;
  background: #3b4cb8;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}

.header-text {
  display: flex;
  flex-direction: column;
}

.page-title {
  margin: 0 0 2px 0;
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.page-subtitle {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
  font-weight: 400;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.refresh-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: #f9fafb;
  color: #374151;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.refresh-btn:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #9ca3af;
  transform: translateY(-1px);
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.refresh-icon {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
  transition: transform 0.2s ease;
}

.refresh-btn:hover:not(:disabled) .refresh-icon:not(.spinning) {
  transform: rotate(180deg);
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}

.metric-card {
  background: #fff;
  border-radius: 8px;
  padding: 20px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
}

.metric-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--card-color);
}

.metric-card.primary {
  --card-color: #3b82f6;
}

.metric-card.success {
  --card-color: #10b981;
}

.metric-card.warning {
  --card-color: #f59e0b;
}

.metric-card.info {
  --card-color: #06b6d4;
}

.metric-card.secondary {
  --card-color: #8b5cf6;
}

.metric-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.metric-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.metric-icon {
  width: 40px;
  height: 40px;
  background: var(--card-color);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.metric-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.metric-label {
  color: #6b7280;
  font-size: 12px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.metric-value {
  font-size: 24px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.metric-trend {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
}

.trend-indicator {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.trend-indicator.positive {
  background: #dcfce7;
  color: #166534;
}

.trend-indicator.neutral {
  background: #f1f5f9;
  color: #64748b;
}

.trend-text {
  font-size: 11px;
  color: #9ca3af;
  font-weight: 400;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.quick-actions {
  margin-top: 8px;
}

.section-title {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}

.action-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  text-decoration: none;
  color: inherit;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.action-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border-color: #d1d5db;
}

.action-icon {
  width: 32px;
  height: 32px;
  background: #f3f4f6;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  flex-shrink: 0;
}

.action-card:hover .action-icon {
  background: #3b4cb8;
  color: #fff;
}

.action-content {
  flex: 1;
}

.action-title {
  margin: 0 0 2px 0;
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.action-description {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  line-height: 1.4;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Responsive */
@media (max-width: 768px) {
  .admin-page {
    gap: 16px;
  }
  
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    padding: 16px;
  }
  
  .metrics-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .actions-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .page-title {
    font-size: 18px;
  }
  
  .metric-value {
    font-size: 20px;
  }
}
</style>

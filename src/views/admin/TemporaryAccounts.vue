<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import Swal from 'sweetalert2'
import { collection, doc, onSnapshot, orderBy, query, updateDoc, where } from 'firebase/firestore'
import { db } from '@/services/firebase'

type TempUser = {
  uid: string
  displayName?: string
  email?: string
  role?: string
  isActive?: boolean
  createdAt?: unknown
}

const tempUsers = ref<TempUser[]>([])
let unsubscribe: null | (() => void) = null

onMounted(() => {
  const usersRef = collection(db, 'users')
  const usersQuery = query(usersRef, where('isTemporary', '==', true), orderBy('createdAt', 'desc'))
  unsubscribe = onSnapshot(usersQuery, (snapshot) => {
    tempUsers.value = snapshot.docs.map((docSnap) => ({
      uid: docSnap.id,
      ...(docSnap.data() as Omit<TempUser, 'uid'>),
    }))
  })
})

onUnmounted(() => {
  if (unsubscribe) unsubscribe()
})

function formatDate(value: unknown) {
  if (!value) return '—'
  if (typeof value === 'string') return value
  if (typeof value === 'object' && value && 'toDate' in value) {
    return (value as { toDate: () => Date }).toDate().toLocaleDateString()
  }
  return '—'
}

async function disableTemp(user: TempUser) {
  const result = await Swal.fire({
    icon: 'warning',
    iconColor: '#f59e0b',
    title: 'Disable temporary account?',
    text: 'This account will be logged out and blocked from access.',
    showCancelButton: true,
    confirmButtonText: 'Disable',
    confirmButtonColor: '#dc2626',
    cancelButtonText: 'Cancel',
    customClass: {
      popup: 'capstone-swal-popup',
      title: 'capstone-swal-title',
      htmlContainer: 'capstone-swal-text',
      confirmButton: 'capstone-swal-confirm',
      cancelButton: 'capstone-swal-cancel',
    },
  })

  if (!result.isConfirmed) return
  await updateDoc(doc(db, 'users', user.uid), { isActive: false })
}
</script>

<template>
  <section class="admin-page">
    <div class="page-header">
      <div class="header-content">
        <div class="header-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="header-text">
          <h1 class="page-title">Temporary Accounts</h1>
          <p class="page-subtitle">Monitor and control temporary access accounts across the platform</p>
        </div>
      </div>
      <div class="header-stats">
        <div class="stat-card">
          <div class="stat-value">{{ tempUsers.length }}</div>
          <div class="stat-label">Total Temporary</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ tempUsers.filter(u => u.isActive !== false).length }}</div>
          <div class="stat-label">Active</div>
        </div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h3 class="table-title">Temporary User Accounts</h3>
        <div class="table-info">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <path d="M12 16V12" stroke="currentColor" stroke-width="2"/>
            <path d="M12 8H12.01" stroke="currentColor" stroke-width="2"/>
          </svg>
          <span>These accounts have limited access and can be disabled at any time</span>
        </div>
      </div>
      <div class="table-container">
        <table class="temp-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in tempUsers" :key="user.uid" class="user-row">
              <td class="user-cell">
                <div class="user-info">
                  <div class="user-avatar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                      <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                    </svg>
                  </div>
                  <div class="user-details">
                    <div class="user-name">{{ user.displayName || 'Unnamed User' }}</div>
                    <div class="user-email">{{ user.email || '—' }}</div>
                  </div>
                </div>
              </td>
              <td class="role-cell">
                <span class="role-badge">{{ user.role || '—' }}</span>
              </td>
              <td>
                <span :class="['status-badge', user.isActive === false ? 'disabled' : 'active']">
                  <svg v-if="user.isActive !== false" width="12" height="12" viewBox="0 0 24 24" fill="none">
                    <path d="M22 11.08V12C21.9988 14.1564 21.3005 16.2547 20.0093 17.9818C18.7182 19.7088 16.9033 20.9725 14.8354 21.5839C12.7674 22.1953 10.5573 22.1219 8.53447 21.3746C6.51168 20.6273 4.78465 19.2461 3.61096 17.4371C2.43727 15.628 1.87979 13.4905 2.02168 11.3363C2.16356 9.18218 2.99721 7.13677 4.39828 5.49707C5.79935 3.85736 7.69279 2.71548 9.79619 2.24224C11.8996 1.77 14.1003 1.98806 16.07 2.86" stroke="currentColor" stroke-width="2"/>
                    <polyline points="22,4 12,14.01 9,11.01" stroke="currentColor" stroke-width="2"/>
                  </svg>
                  <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
                    <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
                  </svg>
                  {{ user.isActive === false ? 'Disabled' : 'Active' }}
                </span>
              </td>
              <td class="date-cell">{{ formatDate(user.createdAt) }}</td>
              <td class="actions-cell">
                <button 
                  class="action-btn disable" 
                  type="button" 
                  @click="disableTemp(user)" 
                  :disabled="user.isActive === false"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
                    <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
                  </svg>
                  Disable
                </button>
              </td>
            </tr>
            <tr v-if="tempUsers.length === 0">
              <td colspan="5" class="empty-state">
                <div class="empty-content">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                  </svg>
                  <p>No temporary accounts found</p>
                  <span>All accounts are permanent or have been removed</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="info-card">
      <div class="info-header">
        <div class="info-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
            <path d="M12 16V12" stroke="currentColor" stroke-width="2"/>
            <path d="M12 8H12.01" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <h3 class="info-title">About Temporary Accounts</h3>
      </div>
      <div class="info-content">
        <p>Temporary accounts are created for limited-time access and have the following characteristics:</p>
        <ul class="info-list">
          <li>Limited access duration and permissions</li>
          <li>Can be disabled immediately by administrators</li>
          <li>Automatically flagged for review and cleanup</li>
          <li>Used for trial access, demos, or short-term collaborations</li>
        </ul>
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
  background: #f59e0b;
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

.header-stats {
  display: flex;
  gap: 12px;
}

.stat-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  min-width: 80px;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.stat-label {
  font-size: 11px;
  color: #6b7280;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 2px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.table-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.table-header {
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.table-info {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #6b7280;
  font-size: 12px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.table-container {
  overflow-x: auto;
}

.temp-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.temp-table th,
.temp-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid #f3f4f6;
}

.temp-table th {
  background: #f9fafb;
  font-weight: 600;
  color: #374151;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.user-row:hover {
  background: #f9fafb;
}

.user-cell {
  min-width: 200px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar {
  width: 32px;
  height: 32px;
  background: #fef3c7;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #92400e;
  flex-shrink: 0;
}

.user-details {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-weight: 500;
  color: #111827;
  font-size: 13px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.user-email {
  font-size: 12px;
  color: #6b7280;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.role-cell {
  min-width: 100px;
}

.role-badge {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 12px;
  font-weight: 500;
  font-size: 11px;
  text-transform: capitalize;
  background: #e0e7ff;
  color: #3730a3;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 12px;
  font-weight: 500;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.disabled {
  background: #fee2e2;
  color: #991b1b;
}

.date-cell {
  color: #6b7280;
  font-size: 12px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.actions-col {
  width: 120px;
}

.actions-cell {
  width: 120px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.action-btn.disable {
  background: #dc2626;
  color: #fff;
}

.action-btn.disable:hover:not(:disabled) {
  background: #b91c1c;
}

.action-btn:disabled {
  background: #f3f4f6;
  color: #9ca3af;
  cursor: not-allowed;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
}

.empty-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #9ca3af;
}

.empty-content svg {
  opacity: 0.5;
}

.empty-content p {
  margin: 0;
  font-size: 14px;
  font-weight: 500;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.empty-content span {
  font-size: 12px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.info-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 20px 24px;
}

.info-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.info-icon {
  width: 24px;
  height: 24px;
  color: #3b82f6;
  flex-shrink: 0;
}

.info-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.info-content {
  color: #6b7280;
  font-size: 14px;
  line-height: 1.5;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.info-content p {
  margin: 0 0 12px 0;
}

.info-list {
  margin: 0;
  padding-left: 20px;
}

.info-list li {
  margin-bottom: 4px;
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
  
  .header-stats {
    width: 100%;
    justify-content: space-around;
  }
  
  .table-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  
  .table-container {
    overflow-x: scroll;
  }
  
  .temp-table {
    min-width: 600px;
  }
  
  .info-card {
    padding: 16px;
  }
}
</style>

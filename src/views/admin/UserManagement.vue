<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import Swal from 'sweetalert2'
import { collection, doc, onSnapshot, orderBy, query, setDoc, updateDoc } from 'firebase/firestore'
import { db } from '@/services/firebase'
import { useAuthStore } from '@/stores/auth'

type UserRow = {
  uid: string
  email?: string
  displayName?: string
  role?: string
  isTemporary?: boolean
  isActive?: boolean
  createdAt?: unknown
  profile?: Record<string, unknown>
}

const authStore = useAuthStore()
const users = ref<UserRow[]>([])
const selectedUser = ref<UserRow | null>(null)

const filterRole = ref('all')
const filterTemporary = ref('all')
const filterStatus = ref('all')

let unsubscribe: null | (() => void) = null

onMounted(() => {
  const usersRef = collection(db, 'users')
  const usersQuery = query(usersRef, orderBy('createdAt', 'desc'))
  unsubscribe = onSnapshot(usersQuery, (snapshot) => {
    users.value = snapshot.docs.map((docSnap) => ({
      uid: docSnap.id,
      ...(docSnap.data() as Omit<UserRow, 'uid'>),
    }))
  })
})

onUnmounted(() => {
  if (unsubscribe) unsubscribe()
})

const filteredUsers = computed(() => {
  return users.value.filter((user) => {
    if (filterRole.value !== 'all' && user.role !== filterRole.value) return false
    if (filterTemporary.value !== 'all') {
      const isTemp = !!user.isTemporary
      if (filterTemporary.value === 'temporary' && !isTemp) return false
      if (filterTemporary.value === 'permanent' && isTemp) return false
    }
    if (filterStatus.value !== 'all') {
      const isActive = user.isActive !== false
      if (filterStatus.value === 'active' && !isActive) return false
      if (filterStatus.value === 'disabled' && isActive) return false
    }
    return true
  })
})

function formatDate(value: unknown) {
  if (!value) return '—'
  if (typeof value === 'string') return value
  if (typeof value === 'object' && value && 'toDate' in value) {
    return (value as { toDate: () => Date }).toDate().toLocaleDateString()
  }
  return '—'
}

const ROLES = ['student', 'school', 'company', 'admin', 'guest'] as const

async function updateUserRole(user: UserRow, newRole: string) {
  if (user.uid === authStore.user?.uid && newRole !== 'admin') {
    await Swal.fire({
      icon: 'error',
      title: 'Action Blocked',
      text: 'You cannot remove your own admin role.',
      confirmButtonColor: '#3b4cb8'
    })
    return
  }

  const result = await Swal.fire({
    icon: 'question',
    title: 'Update Role?',
    text: `Change ${user.displayName || user.email}'s role to ${newRole}?`,
    showCancelButton: true,
    confirmButtonText: 'Update',
    confirmButtonColor: '#3b4cb8',
    cancelButtonText: 'Cancel'
  })

  if (!result.isConfirmed) return

  try {
    await updateDoc(doc(db, 'users', user.uid), {
      role: newRole,
      profileSetupComplete: newRole !== 'guest' && newRole !== null,
      updatedAt: new Date()
    })
    await Swal.fire({
      icon: 'success',
      title: 'Role Updated',
      text: `User is now ${newRole}.`,
      timer: 1500,
      showConfirmButton: false
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Update Failed',
      text: err instanceof Error ? err.message : 'Could not update role.',
      confirmButtonColor: '#3b4cb8'
    })
  }
}

async function setUserStatus(user: UserRow, enabled: boolean) {
  if (user.uid === authStore.user?.uid) {
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Action Blocked',
      text: 'You cannot disable your own account.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3b4cb8',
      customClass: {
        popup: 'capstone-swal-popup',
        title: 'capstone-swal-title',
        htmlContainer: 'capstone-swal-text',
        confirmButton: 'capstone-swal-confirm',
      },
    })
    return
  }

  const result = await Swal.fire({
    icon: 'warning',
    iconColor: '#f59e0b',
    title: enabled ? 'Enable account?' : 'Disable account?',
    text: enabled
      ? 'This user will regain access immediately.'
      : 'This user will be logged out and blocked from access.',
    showCancelButton: true,
    confirmButtonText: enabled ? 'Enable' : 'Disable',
    confirmButtonColor: enabled ? '#16a34a' : '#dc2626',
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

  await updateDoc(doc(db, 'users', user.uid), { isActive: enabled })
}

const syncingProfiles = ref(false)

async function syncPublicProfiles() {
  const toSync = users.value.filter((u) => u.role === 'school' || u.role === 'company')
  if (toSync.length === 0) {
    await Swal.fire({
      icon: 'info',
      title: 'Nothing to Sync',
      text: 'No school or company accounts found.',
      confirmButtonColor: '#3b4cb8',
    })
    return
  }
  syncingProfiles.value = true
  try {
    for (const u of toSync) {
      const p = (u.profile || {}) as Record<string, unknown>
      const orgName =
        (u.role === 'school' ? p.institutionName : p.companyName) as string | undefined
      await setDoc(
        doc(db, 'profiles_public', u.uid),
        {
          uid: u.uid,
          displayName: u.displayName || 'User',
          role: u.role,
          orgName: orgName || u.displayName || u.email?.split('@')[0] || null,
          email: u.email || null,
        },
        { merge: true }
      )
    }
    await Swal.fire({
      icon: 'success',
      title: 'Profiles Synced',
      text: `Synced ${toSync.length} school/company profile(s) to search. They will now appear when adding contracts or starting chats.`,
      confirmButtonColor: '#3b4cb8',
    })
  } catch (err) {
    await Swal.fire({
      icon: 'error',
      title: 'Sync Failed',
      text: err instanceof Error ? err.message : 'Could not sync profiles.',
      confirmButtonColor: '#3b4cb8',
    })
  } finally {
    syncingProfiles.value = false
  }
}
</script>

<template>
  <section class="admin-page">
    <div class="page-header">
      <div class="header-content">
        <div class="header-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
            <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
          </svg>
        </div>
        <div class="header-text">
          <h1 class="page-title">User Management</h1>
          <p class="page-subtitle">Manage user accounts, roles, and permissions across the platform</p>
        </div>
      </div>
      <div class="header-actions">
        <button
          class="sync-btn"
          type="button"
          :disabled="syncingProfiles"
          @click="syncPublicProfiles"
        >
          <svg v-if="!syncingProfiles" class="btn-icon" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <polyline points="23,4 23,10 17,10" stroke="currentColor" stroke-width="2"/>
            <polyline points="1,20 1,14 7,14" stroke="currentColor" stroke-width="2"/>
            <path d="M20.49,9A9,9,0,0,0,5.64,5.64L1,10m22,4L18.36,18.36A9,9,0,0,1,3.51,15" stroke="currentColor" stroke-width="2"/>
          </svg>
          <svg v-else class="btn-icon spinning" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.416" stroke-dashoffset="31.416">
              <animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416" repeatCount="indefinite"/>
              <animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416" repeatCount="indefinite"/>
            </circle>
          </svg>
          {{ syncingProfiles ? 'Syncing...' : 'Sync Profiles' }}
        </button>
      </div>
    </div>

    <div class="filters-card">
      <div class="filters-header">
        <h3 class="filters-title">Filter Users</h3>
        <div class="user-count">{{ filteredUsers.length }} of {{ users.length }} users</div>
      </div>
      <div class="filters-grid">
        <div class="filter-group">
          <label class="filter-label">Role</label>
          <select v-model="filterRole" class="filter-select">
            <option value="all">All Roles</option>
            <option value="student">Student</option>
            <option value="school">School</option>
            <option value="company">Company</option>
            <option value="admin">Admin</option>
            <option value="guest">Guest</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Account Type</label>
          <select v-model="filterTemporary" class="filter-select">
            <option value="all">All Types</option>
            <option value="temporary">Temporary</option>
            <option value="permanent">Permanent</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Status</label>
          <select v-model="filterStatus" class="filter-select">
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
          </select>
        </div>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header">
        <h3 class="table-title">User Accounts</h3>
      </div>
      <div class="table-container">
        <table class="users-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Type</th>
              <th>Status</th>
              <th>Created</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.uid" class="user-row">
              <td class="user-cell">
                <div class="user-info">
                  <div class="user-avatar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                      <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
                      <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                    </svg>
                  </div>
                  <div class="user-details">
                    <div class="user-name">{{ user.displayName || 'Unnamed User' }}</div>
                    <div class="user-email">{{ user.email || '—' }}</div>
                  </div>
                </div>
              </td>
              <td class="role-cell">
                <select
                  :value="user.role || ''"
                  @change="(e) => updateUserRole(user, (e.target as HTMLSelectElement).value)"
                  class="role-select"
                >
                  <option value="">—</option>
                  <option v-for="r in ROLES" :key="r" :value="r">{{ r }}</option>
                </select>
              </td>
              <td>
                <span :class="['type-badge', user.isTemporary ? 'temporary' : 'permanent']">
                  {{ user.isTemporary ? 'Temporary' : 'Permanent' }}
                </span>
              </td>
              <td>
                <span :class="['status-badge', user.isActive === false ? 'disabled' : 'active']">
                  {{ user.isActive === false ? 'Disabled' : 'Active' }}
                </span>
              </td>
              <td class="date-cell">{{ formatDate(user.createdAt) }}</td>
              <td class="actions-cell">
                <div class="action-buttons">
                  <button class="action-btn view" type="button" @click="selectedUser = user">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                      <path d="M1 12S5 4 12 4S23 12 23 12S19 20 12 20S1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
                      <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    View
                  </button>
                  <button
                    class="action-btn toggle"
                    type="button"
                    @click="setUserStatus(user, user.isActive === false)"
                  >
                    <svg v-if="user.isActive === false" width="14" height="14" viewBox="0 0 24 24" fill="none">
                      <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2"/>
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                      <line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
                      <line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    {{ user.isActive === false ? 'Enable' : 'Disable' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredUsers.length === 0">
              <td colspan="6" class="empty-state">
                <div class="empty-content">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                    <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
                  </svg>
                  <p>No users found matching your filters</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="selectedUser" class="details-modal">
      <div class="modal-backdrop" @click="selectedUser = null"></div>
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title">User Profile Details</h2>
          <button class="modal-close" type="button" @click="selectedUser = null">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="2"/>
              <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="2"/>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="profile-section">
            <div class="profile-avatar">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2"/>
              </svg>
            </div>
            <div class="profile-info">
              <h3 class="profile-name">{{ selectedUser.displayName || 'Unnamed User' }}</h3>
              <p class="profile-email">{{ selectedUser.email || 'No email' }}</p>
            </div>
          </div>
          <div class="details-grid">
            <div class="detail-item">
              <label class="detail-label">User ID</label>
              <div class="detail-value">{{ selectedUser.uid }}</div>
            </div>
            <div class="detail-item">
              <label class="detail-label">Role</label>
              <div class="detail-value">{{ selectedUser.role || '—' }}</div>
            </div>
            <div class="detail-item">
              <label class="detail-label">Account Type</label>
              <div class="detail-value">{{ selectedUser.isTemporary ? 'Temporary' : 'Permanent' }}</div>
            </div>
            <div class="detail-item">
              <label class="detail-label">Status</label>
              <div class="detail-value">{{ selectedUser.isActive === false ? 'Disabled' : 'Active' }}</div>
            </div>
            <div class="detail-item">
              <label class="detail-label">Created</label>
              <div class="detail-value">{{ formatDate(selectedUser.createdAt) }}</div>
            </div>
          </div>
        </div>
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

.header-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.sync-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: #3b4cb8;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.sync-btn:hover:not(:disabled) {
  background: #2d3a8c;
  transform: translateY(-1px);
}

.sync-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn-icon {
  flex-shrink: 0;
  width: 14px;
  height: 14px;
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

.filters-card {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 20px 24px;
}

.filters-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.filters-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.user-count {
  font-size: 13px;
  color: #6b7280;
  font-weight: 500;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-label {
  font-size: 12px;
  font-weight: 500;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.filter-select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #fff;
  font-size: 13px;
  font-weight: 400;
  color: #374151;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.filter-select:hover {
  border-color: #9ca3af;
}

.filter-select:focus {
  outline: none;
  border-color: #3b4cb8;
  box-shadow: 0 0 0 3px rgba(59, 76, 184, 0.1);
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
}

.table-title {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.table-container {
  overflow-x: auto;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.users-table th,
.users-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid #f3f4f6;
}

.users-table th {
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
  background: #f3f4f6;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
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
  min-width: 120px;
}

.role-select {
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: #fff;
  font-size: 12px;
  cursor: pointer;
  min-width: 100px;
  text-transform: capitalize;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.role-select:hover {
  border-color: #3b4cb8;
}

.type-badge,
.status-badge {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 12px;
  font-weight: 500;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.type-badge.temporary {
  background: #fef3c7;
  color: #92400e;
}

.type-badge.permanent {
  background: #e0e7ff;
  color: #3730a3;
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
  width: 160px;
}

.actions-cell {
  width: 160px;
}

.action-buttons {
  display: flex;
  gap: 6px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border: none;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.action-btn.view {
  background: #f3f4f6;
  color: #374151;
}

.action-btn.view:hover {
  background: #e5e7eb;
}

.action-btn.toggle {
  background: #3b4cb8;
  color: #fff;
}

.action-btn.toggle:hover {
  background: #2d3a8c;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
}

.empty-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: #9ca3af;
}

.empty-content svg {
  opacity: 0.5;
}

.empty-content p {
  margin: 0;
  font-size: 14px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.details-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal-backdrop {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal-content {
  position: relative;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  max-width: 500px;
  width: 100%;
  max-height: 80vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: #f3f4f6;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background: #e5e7eb;
  color: #374151;
}

.modal-body {
  padding: 24px;
}

.profile-section {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f3f4f6;
}

.profile-avatar {
  width: 48px;
  height: 48px;
  background: #3b4cb8;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}

.profile-info {
  flex: 1;
}

.profile-name {
  margin: 0 0 4px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.profile-email {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.details-grid {
  display: grid;
  gap: 16px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.detail-value {
  font-size: 14px;
  color: #111827;
  font-weight: 400;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  word-break: break-all;
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
  
  .filters-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .table-container {
    overflow-x: scroll;
  }
  
  .users-table {
    min-width: 700px;
  }
  
  .modal-content {
    margin: 10px;
    max-height: 90vh;
  }
  
  .modal-header,
  .modal-body {
    padding: 16px;
  }
  
  .profile-section {
    flex-direction: column;
    text-align: center;
  }
}
</style>

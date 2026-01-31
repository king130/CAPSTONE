<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import Swal from 'sweetalert2'
import { collection, doc, onSnapshot, orderBy, query, updateDoc } from 'firebase/firestore'
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

async function setUserStatus(user: UserRow, enabled: boolean) {
  if (user.uid === authStore.user?.uid) {
    await Swal.fire({
      icon: 'error',
      iconColor: '#dc2626',
      title: 'Action Blocked',
      text: 'You cannot disable your own account.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#2563eb',
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
</script>

<template>
  <section class="page">
    <div class="header">
      <div>
        <h1>User Management</h1>
        <p class="subtitle">Manage access, roles, and account status.</p>
      </div>
      <div class="filters">
        <select v-model="filterRole">
          <option value="all">All Roles</option>
          <option value="student">Student</option>
          <option value="school">School</option>
          <option value="company">Company</option>
          <option value="admin">Admin</option>
        </select>
        <select v-model="filterTemporary">
          <option value="all">Temporary & Permanent</option>
          <option value="temporary">Temporary</option>
          <option value="permanent">Permanent</option>
        </select>
        <select v-model="filterStatus">
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="disabled">Disabled</option>
        </select>
      </div>
    </div>

    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Temporary</th>
            <th>Status</th>
            <th>Created</th>
            <th class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in filteredUsers" :key="user.uid">
            <td>{{ user.displayName || '—' }}</td>
            <td>{{ user.email || '—' }}</td>
            <td class="role">{{ user.role || '—' }}</td>
            <td>{{ user.isTemporary ? 'Yes' : 'No' }}</td>
            <td>
              <span :class="['status', user.isActive === false ? 'disabled' : 'active']">
                {{ user.isActive === false ? 'Disabled' : 'Active' }}
              </span>
            </td>
            <td>{{ formatDate(user.createdAt) }}</td>
            <td class="actions">
              <button class="link" type="button" @click="selectedUser = user">View</button>
              <button
                class="link"
                type="button"
                @click="setUserStatus(user, user.isActive === false)"
              >
                {{ user.isActive === false ? 'Enable' : 'Disable' }}
              </button>
            </td>
          </tr>
          <tr v-if="filteredUsers.length === 0">
            <td colspan="7" class="empty">No users found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedUser" class="details">
      <div class="details-header">
        <h2>User Profile</h2>
        <button class="link" type="button" @click="selectedUser = null">Close</button>
      </div>
      <div class="details-grid">
        <div><strong>UID:</strong> {{ selectedUser.uid }}</div>
        <div><strong>Name:</strong> {{ selectedUser.displayName || '—' }}</div>
        <div><strong>Email:</strong> {{ selectedUser.email || '—' }}</div>
        <div><strong>Role:</strong> {{ selectedUser.role || '—' }}</div>
        <div><strong>Temporary:</strong> {{ selectedUser.isTemporary ? 'Yes' : 'No' }}</div>
        <div><strong>Status:</strong> {{ selectedUser.isActive === false ? 'Disabled' : 'Active' }}</div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
}

h1 {
  margin: 0 0 4px;
  font-size: 20px;
}

.subtitle {
  margin: 0;
  color: #6b7280;
  font-size: 13px;
}

.filters {
  display: flex;
  gap: 8px;
}

.filters select {
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  font-size: 13px;
}

.table-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

th,
td {
  padding: 12px 14px;
  text-align: left;
  border-bottom: 1px solid #e2e8f0;
}

th {
  background: #f8fafc;
  font-weight: 600;
  color: #334155;
}

.role {
  text-transform: capitalize;
}

.actions {
  width: 180px;
}

.status {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 999px;
  font-weight: 600;
  font-size: 11px;
}

.status.active {
  background: #dcfce7;
  color: #166534;
}

.status.disabled {
  background: #fee2e2;
  color: #991b1b;
}

.link {
  border: none;
  background: transparent;
  color: #2563eb;
  cursor: pointer;
  font-size: 12px;
  padding: 0 6px;
}

.empty {
  text-align: center;
  color: #94a3b8;
}

.details {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 16px;
}

.details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 8px 16px;
  font-size: 13px;
}
</style>

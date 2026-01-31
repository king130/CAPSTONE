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
  <section class="page">
    <div class="header">
      <div>
        <h1>Temporary Accounts</h1>
        <p class="subtitle">Monitor and control temporary access.</p>
      </div>
    </div>

    <div class="table-card">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in tempUsers" :key="user.uid">
            <td>{{ user.displayName || '—' }}</td>
            <td>{{ user.email || '—' }}</td>
            <td class="role">{{ user.role || '—' }}</td>
            <td>
              <span :class="['status', user.isActive === false ? 'disabled' : 'active']">
                {{ user.isActive === false ? 'Disabled' : 'Active' }}
              </span>
            </td>
            <td>{{ formatDate(user.createdAt) }}</td>
            <td class="actions">
              <button class="link" type="button" @click="disableTemp(user)" :disabled="user.isActive === false">
                Disable
              </button>
            </td>
          </tr>
          <tr v-if="tempUsers.length === 0">
            <td colspan="6" class="empty">No temporary accounts found.</td>
          </tr>
        </tbody>
      </table>
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
  width: 140px;
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

.link:disabled {
  color: #94a3b8;
  cursor: not-allowed;
}

.empty {
  text-align: center;
  color: #94a3b8;
}
</style>

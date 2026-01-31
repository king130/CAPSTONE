<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { collection, getDocs, query, where } from 'firebase/firestore'
import { db } from '@/services/firebase'

const totalUsers = ref(0)
const activeUsers = ref(0)
const tempUsers = ref(0)
const activeInternships = ref(0)
const sessions = ref('N/A')

async function loadMetrics() {
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
}

onMounted(() => {
  loadMetrics()
})
</script>

<template>
  <section class="page">
    <div class="header">
      <div>
        <h1>System Overview</h1>
        <p class="subtitle">Lightweight operational metrics.</p>
      </div>
      <button class="refresh" type="button" @click="loadMetrics">Refresh</button>
    </div>

    <div class="grid">
      <div class="card">
        <div class="label">Total Users</div>
        <div class="value">{{ totalUsers }}</div>
      </div>
      <div class="card">
        <div class="label">Active Users</div>
        <div class="value">{{ activeUsers }}</div>
      </div>
      <div class="card">
        <div class="label">Temporary Accounts</div>
        <div class="value">{{ tempUsers }}</div>
      </div>
      <div class="card">
        <div class="label">Active Internships</div>
        <div class="value">{{ activeInternships }}</div>
      </div>
      <div class="card">
        <div class="label">Active Sessions</div>
        <div class="value">{{ sessions }}</div>
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

.refresh {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  font-size: 13px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 16px;
}

.label {
  color: #64748b;
  font-size: 12px;
  margin-bottom: 8px;
}

.value {
  font-size: 22px;
  font-weight: 700;
}
</style>

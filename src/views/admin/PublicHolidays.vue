<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Swal from '@/services/swal'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Input from '@/components/ui/input/Input.vue'
import { apiFetch } from '@/services/http'

type Holiday = { id: string; date: string; name: string; type?: string; scope?: string | null }

const rows = ref<Holiday[]>([])
const loading = ref(true)
const date = ref('')
const name = ref('')
const busy = ref(false)

async function load() {
  loading.value = true
  try {
    const year = new Date().getFullYear()
    const res = await apiFetch<{ data: Holiday[] }>(`/admin/holidays?year=${year}`)
    rows.value = res.data ?? []
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Unable to load holidays',
      text: error instanceof Error ? error.message : 'Request failed',
    })
  } finally {
    loading.value = false
  }
}

async function addHoliday() {
  if (!date.value || !name.value.trim()) {
    await Swal.fire({ icon: 'info', title: 'Date and name are required' })
    return
  }
  busy.value = true
  try {
    await apiFetch('/admin/holidays', {
      method: 'POST',
      body: JSON.stringify({ date: date.value, name: name.value.trim(), type: 'national' }),
    })
    date.value = ''
    name.value = ''
    await load()
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Could not add holiday',
      text: error instanceof Error ? error.message : 'Request failed',
    })
  } finally {
    busy.value = false
  }
}

async function removeHoliday(row: Holiday) {
  const confirmed = await Swal.fire({
    title: `Delete ${row.name}?`,
    text: row.date,
    showCancelButton: true,
    confirmButtonText: 'Delete',
  })
  if (!confirmed.isConfirmed) return
  busy.value = true
  try {
    await apiFetch(`/admin/holidays/${row.id}`, { method: 'DELETE' })
    await load()
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Could not delete holiday',
      text: error instanceof Error ? error.message : 'Request failed',
    })
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="space-y-5" data-testid="admin-public-holidays">
    <div>
      <h2 class="text-2xl font-semibold tracking-tight text-foreground">Public holidays</h2>
      <p class="mt-1 text-sm text-muted-foreground">
        Fixed national holidays are seeded. Add or remove dates used by the OJT calendar (Asia/Manila).
      </p>
    </div>

    <Card class="border-border">
      <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
        <div class="flex-1 space-y-1">
          <label class="text-xs font-medium text-muted-foreground">Date</label>
          <Input v-model="date" type="date" data-testid="holiday-date" />
        </div>
        <div class="flex-[2] space-y-1">
          <label class="text-xs font-medium text-muted-foreground">Name</label>
          <Input v-model="name" placeholder="Holiday name" data-testid="holiday-name" />
        </div>
        <Button :disabled="busy" data-testid="holiday-add" @click="addHoliday">Add</Button>
      </CardContent>
    </Card>

    <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
    <div v-else class="space-y-2">
      <Card v-for="row in rows" :key="row.id" class="border-border">
        <CardContent class="flex items-center justify-between gap-3 p-3">
          <div>
            <p class="font-medium text-foreground">{{ row.name }}</p>
            <p class="text-sm text-muted-foreground">{{ row.date }} · {{ row.type || 'national' }}</p>
          </div>
          <Button size="sm" variant="destructive" :disabled="busy" @click="removeHoliday(row)">Delete</Button>
        </CardContent>
      </Card>
      <p v-if="!rows.length" class="text-sm text-muted-foreground">No holidays for this year.</p>
    </div>
  </section>
</template>

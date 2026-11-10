<script setup lang="ts">
import { onMounted, ref } from 'vue'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Input from '@/components/ui/input/Input.vue'
import {
  approveSkill,
  fetchAdminSkills,
  fetchPendingSkills,
  mergeSkill,
  rejectSkill,
  type CatalogSkill,
} from '@/services/skills'
import Swal from '@/services/swal'

type PendingRow = CatalogSkill & {
  createdByName?: string
  createdByEmail?: string
  createdAt?: string
  studentCount?: number
  jobCount?: number
}

const pending = ref<PendingRow[]>([])
const approved = ref<CatalogSkill[]>([])
const loading = ref(true)
const busyId = ref<string | null>(null)
const mergeTargetById = ref<Record<string, string>>({})
const searchApproved = ref('')

async function load() {
  loading.value = true
  try {
    const [pendingRes, approvedRes] = await Promise.all([
      fetchPendingSkills(),
      fetchAdminSkills({ status: 'approved', q: searchApproved.value.trim() || undefined }),
    ])
    pending.value = pendingRes.data || []
    approved.value = approvedRes.data || []
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Unable to load skills',
      text: error instanceof Error ? error.message : 'Request failed',
    })
  } finally {
    loading.value = false
  }
}

async function onApprove(row: PendingRow) {
  busyId.value = row.id
  try {
    const res = await approveSkill(row.id)
    await Swal.fire({ icon: 'success', title: res.message || 'Approved', timer: 1400, showConfirmButton: false })
    await load()
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Approve failed',
      text: error instanceof Error ? error.message : 'Request failed',
    })
  } finally {
    busyId.value = null
  }
}

async function onReject(row: PendingRow) {
  const confirmed = await Swal.fire({
    title: `Reject “${row.name}”?`,
    text: 'It will be removed from profiles and job postings that used it.',
    showCancelButton: true,
    confirmButtonText: 'Reject',
  })
  if (!confirmed.isConfirmed) return
  busyId.value = row.id
  try {
    const res = await rejectSkill(row.id)
    await Swal.fire({ icon: 'success', title: res.message || 'Rejected', timer: 1400, showConfirmButton: false })
    await load()
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Reject failed',
      text: error instanceof Error ? error.message : 'Request failed',
    })
  } finally {
    busyId.value = null
  }
}

async function onMerge(row: PendingRow) {
  const targetId = mergeTargetById.value[row.id]
  if (!targetId) {
    await Swal.fire({ icon: 'info', title: 'Pick an approved skill to merge into' })
    return
  }
  const target = approved.value.find((skill) => skill.id === targetId)
  const confirmed = await Swal.fire({
    title: `Merge “${row.name}” into “${target?.name || 'selected skill'}”?`,
    text: 'Profiles and jobs using the custom skill will point to the shared skill instead.',
    showCancelButton: true,
    confirmButtonText: 'Merge',
  })
  if (!confirmed.isConfirmed) return
  busyId.value = row.id
  try {
    const res = await mergeSkill(row.id, targetId)
    await Swal.fire({ icon: 'success', title: res.message || 'Merged', timer: 1600, showConfirmButton: false })
    await load()
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Merge failed',
      text: error instanceof Error ? error.message : 'Request failed',
    })
  } finally {
    busyId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-6" data-testid="admin-skills-review">
    <div>
      <h2 class="text-2xl font-semibold text-foreground">Skills review</h2>
      <p class="mt-1 text-sm text-muted-foreground">
        Approve new custom skills for the shared list, merge near-duplicates, or reject ones that should not be used.
      </p>
    </div>

    <Card>
      <CardContent class="space-y-4 p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h3 class="text-lg font-semibold text-foreground">Pending review</h3>
            <p class="text-sm text-muted-foreground">These were typed by users and are waiting for a decision.</p>
          </div>
          <Button type="button" variant="outline" @click="load">Refresh</Button>
        </div>

        <p v-if="loading" class="text-sm text-muted-foreground">Loading…</p>
        <p v-else-if="!pending.length" class="text-sm text-muted-foreground">No custom skills waiting for review.</p>

        <div v-else class="space-y-3">
          <article
            v-for="row in pending"
            :key="row.id"
            class="rounded-2xl border border-border bg-card p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h4 class="text-base font-semibold text-foreground">{{ row.name }}</h4>
                  <Badge variant="secondary">{{ row.category }}</Badge>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                  Added by {{ row.createdByName || 'Unknown' }}
                  <span v-if="row.createdByEmail">({{ row.createdByEmail }})</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                  Used on {{ row.studentCount || 0 }} profiles · {{ row.jobCount || 0 }} jobs
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <Button type="button" size="sm" :disabled="busyId === row.id" @click="onApprove(row)">
                  Approve
                </Button>
                <Button type="button" size="sm" variant="destructive" :disabled="busyId === row.id" @click="onReject(row)">
                  Reject
                </Button>
              </div>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto]">
              <select
                v-model="mergeTargetById[row.id]"
                class="flex h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
                aria-label="Merge into approved skill"
              >
                <option value="">Merge into an approved skill…</option>
                <option v-for="skill in approved" :key="skill.id" :value="skill.id">
                  {{ skill.name }} ({{ skill.category }})
                </option>
              </select>
              <Button type="button" variant="outline" :disabled="busyId === row.id" @click="onMerge(row)">
                Merge
              </Button>
            </div>
          </article>
        </div>
      </CardContent>
    </Card>

    <Card>
      <CardContent class="space-y-4 p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h3 class="text-lg font-semibold text-foreground">Shared skill list</h3>
            <p class="text-sm text-muted-foreground">Search approved skills when merging custom entries.</p>
          </div>
          <div class="flex gap-2">
            <Input v-model="searchApproved" placeholder="Search approved skills" class="w-56" @keydown.enter.prevent="load" />
            <Button type="button" variant="outline" @click="load">Search</Button>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="skill in approved.slice(0, 40)"
            :key="skill.id"
            class="rounded-full border border-border bg-muted px-3 py-1 text-xs font-medium"
          >
            {{ skill.name }}
          </span>
        </div>
      </CardContent>
    </Card>
  </div>
</template>

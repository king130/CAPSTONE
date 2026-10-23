<script setup lang="ts">
import { Building2, Search } from 'lucide-vue-next'
import { onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Select from '@/components/ui/select/Select.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import {
  listDiscoverableOrganizations,
  type DiscoverableOrganization,
} from '@/services/opportunities'

const props = defineProps<{
  kind: 'company' | 'school'
}>()

const query = ref('')
const city = ref('')
const industry = ref('')
const hasOpenings = ref(false)
const page = ref(1)
const loading = ref(true)
const errorMessage = ref('')
const rows = ref<DiscoverableOrganization[]>([])
const total = ref(0)
const lastPage = ref(1)
const cities = ref<string[]>([])
const industries = ref<string[]>([])

let timer: ReturnType<typeof setTimeout> | undefined

const emptyCopy = props.kind === 'company'
  ? 'No companies are currently available to browse.'
  : 'No schools are currently available to browse.'

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const result = await listDiscoverableOrganizations({
      type: props.kind,
      q: query.value,
      city: city.value,
      industry: props.kind === 'company' ? industry.value : '',
      hasOpenings: props.kind === 'company' ? hasOpenings.value : false,
      page: page.value,
    })
    rows.value = result.data
    total.value = result.total
    lastPage.value = result.lastPage
    if (result.cities.length) cities.value = result.cities
    if (result.industries.length) industries.value = result.industries
  } catch (err) {
    rows.value = []
    errorMessage.value = err instanceof Error ? err.message : 'Unable to load organizations.'
  } finally {
    loading.value = false
  }
}

watch([city, industry, hasOpenings], () => {
  page.value = 1
  void load()
})

watch(query, () => {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => {
    page.value = 1
    void load()
  }, 250)
})

function changePage(next: number) {
  page.value = next
  void load()
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="space-y-5">
    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_12rem]">
      <div class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="query"
          type="search"
          class="pl-9"
          :placeholder="kind === 'company' ? 'Search companies' : 'Search schools'"
          :aria-label="kind === 'company' ? 'Search companies' : 'Search schools'"
        />
      </div>
      <Select v-model="city" aria-label="Filter by location">
        <option value="">All locations</option>
        <option v-for="option in cities" :key="option" :value="option">{{ option }}</option>
      </Select>
      <Select v-if="kind === 'company'" v-model="industry" aria-label="Filter by industry">
        <option value="">All industries</option>
        <option v-for="option in industries" :key="option" :value="option">{{ option }}</option>
      </Select>
    </div>

    <label v-if="kind === 'company'" class="inline-flex items-center gap-2 text-sm text-foreground">
      <input v-model="hasOpenings" type="checkbox" class="h-4 w-4 rounded border-border" />
      Has open internship opportunities
    </label>

    <p v-if="errorMessage" class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ errorMessage }}
    </p>

    <div v-if="loading" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <Skeleton v-for="index in 6" :key="index" class="h-64 rounded-xl bg-muted" />
    </div>

    <div v-else-if="rows.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="organization in rows"
        :key="organization.id"
        class="flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground"
      >
        <div class="relative h-40 w-full shrink-0 overflow-hidden border-b border-border bg-white dark:bg-slate-100">
          <img
            v-if="organization.logoUrl"
            :src="organization.logoUrl"
            :alt="`${organization.name} profile`"
            class="absolute inset-0 m-auto max-h-full max-w-full object-contain p-5"
            loading="lazy"
          />
          <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-sm text-slate-500">
            <Building2 class="h-8 w-8 opacity-70" />
            No profile photo
          </div>
        </div>
        <div class="flex min-h-0 flex-1 flex-col gap-3 p-4">
          <div class="space-y-1">
            <h3 class="line-clamp-2 text-lg font-semibold text-foreground">{{ organization.name }}</h3>
            <p v-if="organization.industry" class="text-sm text-muted-foreground">{{ organization.industry }}</p>
            <p class="text-sm text-muted-foreground">{{ organization.address || organization.city || 'Cavite' }}</p>
            <p v-if="organization.tagline" class="line-clamp-2 text-sm text-muted-foreground">{{ organization.tagline }}</p>
          </div>
          <Badge v-if="kind === 'company'" variant="outline">
            {{ organization.openOpportunities }} internship opportunit{{ organization.openOpportunities === 1 ? 'y' : 'ies' }}
          </Badge>
          <RouterLink
            :to="{ name: 'organization-public', params: { id: organization.id } }"
            class="mt-auto inline-flex h-10 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground hover:bg-accent"
          >
            {{
              kind === 'school'
                ? 'View school'
                : organization.type === 'municipal_hall'
                  ? 'View municipal hall'
                  : 'View company'
            }}
          </RouterLink>
        </div>
      </article>
    </div>

    <div v-else class="rounded-xl border border-dashed border-border px-6 py-14 text-center">
      <p class="text-sm text-muted-foreground">{{ query || city || industry || hasOpenings ? 'No matches for those filters.' : emptyCopy }}</p>
    </div>

    <div v-if="!loading && lastPage > 1" class="flex items-center justify-between gap-3">
      <p class="text-sm text-muted-foreground">{{ total }} result{{ total === 1 ? '' : 's' }}</p>
      <div class="flex gap-2">
        <Button variant="outline" size="sm" :disabled="page <= 1" @click="changePage(page - 1)">Previous</Button>
        <Button variant="outline" size="sm" :disabled="page >= lastPage" @click="changePage(page + 1)">Next</Button>
      </div>
    </div>
  </div>
</template>

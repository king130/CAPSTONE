<script setup lang="ts">
import { BadgeCheck, Building2, ExternalLink, MapPin } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import MediaGallery from '@/components/media/MediaGallery.vue'
import PublicSiteHeader from '@/components/PublicSiteHeader.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import type { InternshipRecord } from '@/services/internships'
import {
  fetchOrganizationGallery,
  fetchPublicOrganization,
  type PublicOrganizationProfile,
} from '@/services/organizationsPublic'

const route = useRoute()

const loading = ref(true)
const notFound = ref(false)
const errorMessage = ref('')
const organization = ref<PublicOrganizationProfile | null>(null)
const gallery = ref<Array<{ id: string; url: string; caption?: string | null }>>([])

const internships = computed<InternshipRecord[]>(() => organization.value?.internships ?? [])
const isCompany = computed(() => organization.value?.type === 'company')

async function loadProfile() {
  const id = String(route.params.id || '')
  if (!id) {
    notFound.value = true
    loading.value = false
    return
  }

  loading.value = true
  notFound.value = false
  errorMessage.value = ''

  try {
    const [profile, media] = await Promise.all([
      fetchPublicOrganization(id),
      fetchOrganizationGallery(id).catch(() => []),
    ])
    organization.value = profile
    gallery.value = media.length
      ? media.map((item) => ({ id: item.id, url: item.url, caption: item.caption }))
      : profile.coverImage
        ? [{ id: 'cover', url: profile.coverImage, caption: profile.name }]
        : []
  } catch (err) {
    const message = err instanceof Error ? err.message : 'Unable to load organization.'
    if (/not found/i.test(message) || /404/.test(message)) {
      notFound.value = true
    } else {
      errorMessage.value = message
    }
    organization.value = null
    gallery.value = []
  } finally {
    loading.value = false
  }
}

function slotsLeft(item: InternshipRecord): number {
  const value = item.slotsRemaining ?? item.slotsAvailable
  return Number.isFinite(value) ? Math.max(0, Number(value)) : 0
}

watch(
  () => route.params.id,
  () => {
    void loadProfile()
  },
)

onMounted(() => {
  void loadProfile()
})
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <PublicSiteHeader />

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div v-if="loading" class="space-y-4">
        <Skeleton class="aspect-[16/10] w-full max-w-4xl rounded-xl" />
        <Skeleton class="h-8 w-1/2" />
        <Skeleton class="h-24 w-full max-w-3xl" />
      </div>

      <Card v-else-if="notFound" class="border-border bg-card">
        <CardContent class="space-y-3 px-6 py-16 text-center">
          <h1 class="text-2xl font-semibold text-foreground">Organization not found</h1>
          <p class="text-sm text-muted-foreground">This organization is inactive or unavailable.</p>
          <RouterLink
            to="/find-internships"
            class="inline-flex h-10 items-center justify-center rounded-md border border-border bg-background px-4 text-sm font-medium text-foreground hover:bg-accent"
          >
            Browse internships
          </RouterLink>
        </CardContent>
      </Card>

      <Card v-else-if="errorMessage" class="border-border bg-card">
        <CardContent class="space-y-3 px-6 py-16 text-center">
          <h1 class="text-2xl font-semibold text-foreground">Unable to load profile</h1>
          <p class="text-sm text-muted-foreground">{{ errorMessage }}</p>
          <Button variant="outline" @click="loadProfile">Retry</Button>
        </CardContent>
      </Card>

      <div v-else-if="organization" class="grid gap-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <div class="space-y-6">
          <MediaGallery :images="gallery" :alt-fallback="`${organization.name} photo`" />

          <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
              <Badge variant="outline" class="capitalize">{{ organization.type }}</Badge>
              <span
                v-if="organization.verified"
                class="inline-flex items-center gap-1 rounded-full border border-border bg-secondary px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground"
              >
                <BadgeCheck class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" />
                Verified
              </span>
            </div>
            <h1 class="text-3xl font-semibold tracking-tight text-foreground">{{ organization.name }}</h1>
            <p v-if="organization.tagline" class="text-base text-muted-foreground">{{ organization.tagline }}</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground">
              <span v-if="organization.city" class="inline-flex items-center gap-1.5">
                <MapPin class="h-3.5 w-3.5" />
                {{ organization.city }}
              </span>
              <span v-if="organization.industry" class="inline-flex items-center gap-1.5">
                <Building2 class="h-3.5 w-3.5" />
                {{ organization.industry }}
              </span>
              <a
                v-if="organization.website"
                :href="organization.website"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 text-primary underline-offset-4 hover:underline"
              >
                Website
                <ExternalLink class="h-3.5 w-3.5" />
              </a>
            </div>
          </div>

          <section class="space-y-2">
            <h2 class="text-lg font-semibold text-foreground">About</h2>
            <p class="whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
              {{ organization.description?.trim() || 'No public description yet.' }}
            </p>
          </section>

          <section v-if="organization.perks.length" class="space-y-2">
            <h2 class="text-lg font-semibold text-foreground">Perks</h2>
            <div class="flex flex-wrap gap-2">
              <Badge v-for="perk in organization.perks" :key="perk" variant="secondary">{{ perk }}</Badge>
            </div>
          </section>
        </div>

        <div v-if="isCompany" class="space-y-4">
          <div class="flex items-end justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-foreground">Active internships</h2>
              <p class="text-sm text-muted-foreground">Open roles from this company.</p>
            </div>
          </div>

          <div v-if="internships.length" class="space-y-3">
            <RouterLink
              v-for="item in internships"
              :key="item.id"
              :to="{ name: 'internship-detail', params: { id: item.id } }"
              class="block rounded-xl border border-border bg-card p-4 transition hover:border-primary/30"
            >
              <div class="flex gap-3">
                <div class="h-16 w-24 shrink-0 overflow-hidden rounded-lg border border-border bg-muted">
                  <img
                    v-if="item.coverImage"
                    :src="item.coverImage"
                    :alt="`${item.title} cover`"
                    class="h-full w-full object-cover"
                    loading="lazy"
                  />
                  <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                    <Building2 class="h-5 w-5 opacity-70" />
                  </div>
                </div>
                <div class="min-w-0">
                  <h3 class="truncate font-semibold text-foreground">{{ item.title }}</h3>
                  <p class="mt-1 truncate text-sm text-muted-foreground">
                    {{ item.location || item.workSetup || 'Cavite' }}
                  </p>
                  <p class="mt-1 text-xs text-muted-foreground">
                    {{ slotsLeft(item) }} slot{{ slotsLeft(item) === 1 ? '' : 's' }} left
                  </p>
                </div>
              </div>
            </RouterLink>
          </div>

          <Card v-else class="border-dashed border-border bg-card">
            <CardContent class="px-5 py-10 text-center text-sm text-muted-foreground">
              No active internships right now.
            </CardContent>
          </Card>
        </div>
      </div>
    </main>
  </div>
</template>

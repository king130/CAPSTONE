<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from 'lucide-vue-next'

import DynamicContractRequestForm from '@/components/DynamicContractRequestForm.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { filterAgreementDirectoryPartners, listPublicProfiles, type PublicProfile } from '@/services/profilesPublic'
import { useAuthStore } from '@/stores/auth'

type ContractRole = 'school' | 'company'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const { error } = useToast()

const currentRole = computed<ContractRole>(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const partners = ref<PublicProfile[]>([])
const loadingPartners = ref(true)

const queryPartnerUid = computed(() => String(route.query.partner || route.query.partnerId || '').trim())
const queryOrganizationId = computed(() =>
  String(route.query.organizationId || route.query.organization_id || '').trim(),
)

const preselectedPartner = computed(() => {
  if (queryPartnerUid.value) {
    const byUid = partners.value.find((partner) => partner.uid === queryPartnerUid.value)
    if (byUid) return byUid
  }
  if (queryOrganizationId.value) {
    return partners.value.find((partner) => partner.organizationId === queryOrganizationId.value) ?? null
  }
  return null
})

const lockPartner = computed(() => Boolean(preselectedPartner.value))
const initialPartnerId = computed(() => preselectedPartner.value?.uid || '')

async function loadPartners() {
  loadingPartners.value = true
  try {
    const listed = await listPublicProfiles(currentRole.value === 'school' ? 'company' : 'school')
    partners.value = filterAgreementDirectoryPartners(listed)
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Could not load directory partners for agreements.',
    })
  } finally {
    loadingPartners.value = false
  }
}

function goBack() {
  void router.push({ name: 'agreements' })
}

function handleSubmitted(_payload?: { id?: string; moaReferenceNo?: string }) {
  void router.push({ name: 'agreements' })
}

onMounted(() => {
  void loadPartners()
})
</script>

<template>
  <MainLayout :role="currentRole" title="New Agreement Request" active-item="agreements">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-primary">Agreements</p>
            <div>
              <h2 class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Create an agreement</h2>
              <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Follow the steps to choose a partner, pick a template, and send your request. Your progress is saved
                automatically as a draft.
              </p>
            </div>
          </div>
          <Button variant="outline" class="w-full sm:w-auto" @click="goBack">
            <ArrowLeft class="h-4 w-4" />
            Back to Agreements
          </Button>
        </CardHeader>
      </Card>

      <div v-if="loadingPartners" class="grid gap-4">
        <Skeleton class="h-24 rounded-2xl" />
        <Skeleton class="h-64 rounded-2xl" />
      </div>

      <Card
        v-else-if="(queryPartnerUid || queryOrganizationId) && !preselectedPartner"
        class="border-border/80 shadow-sm"
      >
        <CardContent class="space-y-3 px-6 py-10">
          <h3 class="text-lg font-semibold text-foreground">Partner unavailable</h3>
          <p class="text-sm text-muted-foreground">
            That organization is not available for a new agreement request right now. It may already have an active or
            pending agreement, or it may not be approved yet.
          </p>
          <div class="flex flex-wrap gap-2">
            <Button @click="goBack">Back to Agreements</Button>
            <Button variant="outline" @click="router.push({ name: 'agreements-new' })">Choose another partner</Button>
          </div>
        </CardContent>
      </Card>

      <DynamicContractRequestForm
        v-else
        :requester-role="currentRole"
        :partners="partners"
        :initial-partner-id="initialPartnerId"
        :selected-partner="preselectedPartner"
        :lock-partner="lockPartner"
        @submitted="handleSubmitted"
      />
    </div>
  </MainLayout>
</template>

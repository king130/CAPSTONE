<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeft, BriefcaseBusiness, Building2, FileText } from 'lucide-vue-next'

import DynamicContractRequestForm from '@/components/DynamicContractRequestForm.vue'
import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { useToast } from '@/composables/useToast'
import MainLayout from '@/layouts/MainLayout.vue'
import { listPublicProfiles, type PublicProfile } from '@/services/profilesPublic'
import { useAuthStore } from '@/stores/auth'

type ContractRole = 'school' | 'company'

const authStore = useAuthStore()
const router = useRouter()
const { error } = useToast()

const currentRole = computed<ContractRole>(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const partnerLabel = computed(() => (currentRole.value === 'school' ? 'Company' : 'School'))
const partners = ref<PublicProfile[]>([])
const loadingPartners = ref(true)
const selectedPartner = ref<PublicProfile | null>(null)

async function loadPartners() {
  loadingPartners.value = true
  try {
    partners.value = await listPublicProfiles(currentRole.value === 'school' ? 'company' : 'school')
  } catch (caughtError) {
    error(caughtError, {
      fallback: 'Could not load directory partners for contracts.',
    })
  } finally {
    loadingPartners.value = false
  }
}

function goBack() {
  void router.push({ name: 'contracts' })
}

function resetPartner() {
  selectedPartner.value = null
}

function handleSubmitted(_payload?: { id?: string; moaReferenceNo?: string }) {
  selectedPartner.value = null
  void router.push({ name: 'contracts' })
}

onMounted(() => {
  void loadPartners()
})
</script>

<template>
  <MainLayout :role="currentRole" title="New Contract Request" active-item="contracts">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-sky-700">Contracts</p>
            <div>
              <h2 class="text-3xl font-semibold tracking-tight text-foreground">Create a contract request</h2>
              <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Use a dedicated page for building agreements so you can review details comfortably without losing work to an accidental click.
              </p>
            </div>
          </div>
          <Button variant="outline" @click="goBack">
            <ArrowLeft class="h-4 w-4" />
            Back to Contracts
          </Button>
        </CardHeader>
      </Card>

      <template v-if="!selectedPartner">
        <Card class="border-border/80 shadow-sm">
          <CardHeader>
            <h3 class="text-2xl font-semibold text-foreground">Choose a {{ partnerLabel.toLowerCase() }}</h3>
            <p class="text-sm text-muted-foreground">
              Start by selecting the organization you want to send the agreement to.
            </p>
          </CardHeader>
          <CardContent>
            <div v-if="loadingPartners" class="grid gap-4 md:grid-cols-2">
              <Skeleton v-for="index in 4" :key="index" class="h-36 rounded-2xl" />
            </div>

            <div v-else-if="partners.length" class="grid gap-4 md:grid-cols-2">
              <button
                v-for="partner in partners"
                :key="partner.uid"
                type="button"
                class="rounded-2xl border border-border bg-card p-5 text-left shadow-sm transition hover:border-primary hover:bg-primary/5"
                @click="selectedPartner = partner"
              >
                <div class="flex items-start gap-4">
                  <div class="rounded-2xl bg-primary/10 p-3 text-primary">
                    <Building2 v-if="currentRole === 'school'" class="h-5 w-5" />
                    <BriefcaseBusiness v-else class="h-5 w-5" />
                  </div>
                  <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">{{ partnerLabel }}</p>
                    <p class="mt-2 text-lg font-semibold text-foreground">{{ partner.orgName || partner.displayName }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ partner.email || 'Directory profile' }}</p>
                  </div>
                </div>
              </button>
            </div>

            <Card v-else class="border-dashed shadow-none">
              <CardContent class="py-10 text-center text-muted-foreground">
                No {{ partnerLabel.toLowerCase() }} profiles are available to receive a contract right now.
              </CardContent>
            </Card>
          </CardContent>
        </Card>
      </template>

      <template v-else>
        <Card class="border-border/80 shadow-sm">
          <CardHeader class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
              <div class="rounded-2xl bg-primary/10 p-3 text-primary">
                <FileText class="h-5 w-5" />
              </div>
              <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-muted-foreground">Selected {{ partnerLabel }}</p>
                <h3 class="text-xl font-semibold text-foreground">{{ selectedPartner.orgName || selectedPartner.displayName }}</h3>
                <p class="text-sm text-muted-foreground">{{ selectedPartner.email || 'Directory profile' }}</p>
              </div>
            </div>
            <Button variant="outline" @click="resetPartner">Change {{ partnerLabel }}</Button>
          </CardHeader>
        </Card>

        <DynamicContractRequestForm
          :requester-role="currentRole"
          :partners="partners"
          :initial-partner-id="selectedPartner.uid"
          :selected-partner="selectedPartner"
          initial-contract-type-name="OJT Memorandum of Agreement (MOA)"
          lock-partner
          @change-partner="resetPartner"
          @submitted="handleSubmitted"
        />
      </template>
    </div>
  </MainLayout>
</template>

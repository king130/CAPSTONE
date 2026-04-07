<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CreditCard, ShieldCheck } from 'lucide-vue-next'

import SubscriptionManager from '@/components/SubscriptionManager.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import CardHeader from '@/components/ui/card/CardHeader.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import { useAuthStore } from '@/stores/auth'

type OrganizationRole = 'school' | 'company'

const authStore = useAuthStore()
const router = useRouter()

const role = computed<OrganizationRole>(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const title = computed(() => (role.value === 'school' ? 'School Subscription' : 'Company Subscription'))
const helperCopy = computed(() =>
  role.value === 'school'
    ? 'Manage your school plan, open a PayMongo checkout for paid upgrades, and keep coordinator/student access aligned with your subscription.'
    : 'Manage your company plan, upgrade using PayMongo test checkout, and keep your posting capacity aligned with your subscription.',
)
const subscriptionOverages = computed(() => authStore.user?.subscription?.overages?.items ?? [])

function goBack() {
  void router.push({ name: role.value === 'school' ? 'school' : 'dashboard' })
}

onMounted(() => {
  void authStore.refreshUser()
})
</script>

<template>
  <MainLayout :role="role" :title="title" active-item="subscription">
    <div class="space-y-6">
      <Card class="border-border/80 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="space-y-2">
            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-sky-700">Billing</p>
            <div>
              <h2 class="text-3xl font-semibold tracking-tight text-foreground">Subscription and billing</h2>
              <p class="mt-2 max-w-3xl text-sm text-muted-foreground">
                {{ helperCopy }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
            @click="goBack"
          >
            Back to Workspace
          </button>
        </CardHeader>
        <CardContent class="grid gap-4 md:grid-cols-2">
          <div class="rounded-2xl border border-sky-100 bg-sky-50 p-5">
            <div class="flex items-center gap-3">
              <div class="rounded-2xl bg-sky-100 p-3 text-sky-700">
                <CreditCard class="h-5 w-5" />
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-950">Temporary payment mode</p>
                <p class="text-sm text-slate-600">PayMongo test checkout</p>
              </div>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-700">
              Paid upgrades currently use PayMongo as a temporary checkout flow. This lets you test the billing journey now without locking the product to a final production payment setup.
            </p>
          </div>

          <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
            <div class="flex items-center gap-3">
              <div class="rounded-2xl bg-emerald-100 p-3 text-emerald-700">
                <ShieldCheck class="h-5 w-5" />
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-950">Verification flow</p>
                <p class="text-sm text-slate-600">Pay first, then verify in-app</p>
              </div>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-700">
              After opening PayMongo checkout, return here and use the built-in verification action so the organization subscription is updated inside the workspace.
            </p>
          </div>
        </CardContent>
      </Card>

      <Card v-if="subscriptionOverages.length" class="border-amber-200 bg-amber-50 shadow-sm">
        <CardHeader>
          <h2 class="text-xl font-semibold text-amber-950">Subscription action required</h2>
          <p class="text-sm text-amber-800">
            Your current usage is above the active plan limits. Remove extra accounts or postings so access stays aligned with the subscription.
          </p>
        </CardHeader>
        <CardContent class="space-y-2">
          <div
            v-for="item in subscriptionOverages"
            :key="item.key"
            class="rounded-xl border border-amber-200 bg-white/80 px-4 py-3 text-sm text-amber-900"
          >
            {{ item.message }}
          </div>
        </CardContent>
      </Card>

      <SubscriptionManager :role="role" />
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import SubscriptionManager from '@/components/SubscriptionManager.vue'
import Button from '@/components/ui/button/Button.vue'
import MainLayout from '@/layouts/MainLayout.vue'
import { useAuthStore } from '@/stores/auth'

type OrganizationRole = 'school' | 'company'

const authStore = useAuthStore()
const router = useRouter()

const role = computed<OrganizationRole>(() => (authStore.user?.role === 'company' ? 'company' : 'school'))
const title = computed(() => (role.value === 'school' ? 'Subscription' : 'Subscription'))
const overages = computed(() => authStore.user?.subscription?.overages?.items ?? [])

function goBack() {
  void router.push({ name: role.value === 'school' ? 'school' : 'dashboard' })
}
</script>

<template>
  <MainLayout :role="role" :title="title" active-item="subscription">
    <div class="space-y-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-2xl font-semibold tracking-tight text-foreground">Subscription</h2>
          <p class="mt-1 text-sm text-muted-foreground">
            {{
              role === 'school'
                ? 'Manage school plan limits, PayMongo upgrades, and verification.'
                : 'Manage company plan limits, PayMongo upgrades, and verification.'
            }}
          </p>
        </div>
        <Button variant="outline" size="sm" @click="goBack">Back to workspace</Button>
      </div>

      <div
        v-if="overages.length"
        class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
      >
        <p class="font-semibold">Usage over plan limits</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
          <li v-for="item in overages" :key="item.key || item.label">
            {{ item.label || item.key }}: {{ item.current }} / {{ item.limit }}
          </li>
        </ul>
      </div>

      <SubscriptionManager :role="role" />
    </div>
  </MainLayout>
</template>

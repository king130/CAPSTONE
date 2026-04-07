<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const isGuest = computed(() => !authStore.user?.role || authStore.user.role === 'guest')

onMounted(() => {
  if (!isGuest.value) {
    void router.replace('/settings')
  }
})

function handleGuestContinue() {
  void router.push('/role-selection')
}
</script>

<template>
  <div v-if="isGuest" class="flex min-h-[60vh] items-center justify-center px-4">
    <Card class="w-full max-w-xl border-border/80 shadow-sm">
      <CardContent class="space-y-4 p-8 text-center">
        <h1 class="text-2xl font-semibold text-slate-950">Complete your account setup</h1>
        <p class="text-sm leading-6 text-slate-600">
          Settings are available after you choose a role. Continue to role selection to finish setting up your account.
        </p>
        <Button @click="handleGuestContinue">Choose Role</Button>
      </CardContent>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from '@/services/swal'
import {
  ArrowLeftIcon,
  BuildingOfficeIcon,
  CheckIcon,
  AcademicCapIcon,
} from '@heroicons/vue/24/outline'

import { getPricingForRole } from '@/services/subscriptionPricing'
import { useAuthStore } from '@/stores/auth'

type PlanName = 'Free' | 'Standard' | 'Premium'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const selectedRole = ref<'school' | 'company' | null>(null)
const selectedPlan = ref<PlanName | null>(null)
const currentPlans = ref<Record<PlanName, { price: string; description: string; features: string[]; limits: Record<string, number> }> | null>(null)
const loading = ref(false)

onMounted(async () => {
  const routeRole = (route.params.role as string) || (route.query.role as string) || null
  if (routeRole !== 'school' && routeRole !== 'company') {
    void router.push('/guest')
    return
  }

  selectedRole.value = routeRole
  await loadPricing()
})

const roleInfo = computed(() => {
  if (selectedRole.value === 'school') {
    return {
      title: 'School Plans',
      subtitle: 'Set up the right subscription for coordinators, student accounts, and placement tracking.',
      icon: AcademicCapIcon,
      badge: 'School Setup',
      accent: 'from-sky-600 via-cyan-600 to-emerald-500',
      ring: 'ring-sky-200',
      button: 'bg-sky-600 hover:bg-sky-700',
      soft: 'bg-sky-100 text-sky-700',
    }
  }

  return {
    title: 'Company Plans',
    subtitle: 'Choose a subscription that fits your internship posting and applicant review workflow.',
    icon: BuildingOfficeIcon,
    badge: 'Company Setup',
    accent: 'from-emerald-600 via-teal-600 to-cyan-500',
    ring: 'ring-emerald-200',
    button: 'bg-emerald-600 hover:bg-emerald-700',
    soft: 'bg-emerald-100 text-emerald-700',
  }
})

const plansArray = computed(() =>
  Object.entries(currentPlans.value ?? {}).map(([name, plan]) => ({
    name: name as PlanName,
    ...plan,
  })),
)

const welcomeName = computed(() => authStore.user?.displayName || authStore.user?.email?.split('@')[0] || 'there')

async function loadPricing() {
  if (!selectedRole.value) {
    return
  }

  loading.value = true
  try {
    currentPlans.value = await getPricingForRole(selectedRole.value)
  } catch (error) {
    console.error('Failed to load pricing:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Unable to load plans',
      text: 'Please try again in a moment.',
      confirmButtonColor: '#0f766e',
    })
  } finally {
    loading.value = false
  }
}

function goBack() {
  void router.push('/guest')
}

function formatLimit(label: string, value: number | undefined): string {
  const n = value ?? 0
  const display = n >= 999 ? 'Unlimited' : n.toLocaleString()
  return `${label}: ${display}`
}

function planAmountValue(price: string): string {
  return price.replace(/^PHP\s*/, '')
}

function registerPathForRole(role: 'school' | 'company') {
  return role === 'school' ? '/register/school' : '/register/company'
}

async function selectPlan(planName: PlanName, price: string) {
  selectedPlan.value = planName

  const result = await Swal.fire({
    icon: 'success',
    title: `${planName} Plan Selected`,
    html: `
      <div style="text-align:left">
        <p><strong>Plan:</strong> ${planName}</p>
        <p><strong>Price:</strong> ${price}${price === 'PHP 0' ? '' : '/month'}</p>
        <p><strong>Role:</strong> ${selectedRole.value === 'school' ? 'School' : 'Company'}</p>
        <br />
        <p>Continue to account setup with this subscription?</p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Continue Setup',
    cancelButtonText: 'Change Plan',
    confirmButtonColor: selectedRole.value === 'school' ? '#0284c7' : '#059669',
    cancelButtonColor: '#64748b',
  })

  if (!result.isConfirmed) {
    selectedPlan.value = null
    return
  }

  const role = selectedRole.value
  if (!role) {
    return
  }

  void router.push({
    path: registerPathForRole(role),
    query: {
      plan: planName.toLowerCase(),
      billingCycle: 'monthly',
      source: 'subscription',
    },
  })
}
</script>

<template>
  <div class="min-h-screen bg-[linear-gradient(180deg,#f8fbff_0%,#eef6ff_42%,#f8fafc_100%)]">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <header class="rounded-[2rem] border border-white/70 bg-white/90 p-5 shadow-[0_24px_60px_-28px_rgba(15,23,42,0.28)] backdrop-blur">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-center gap-3">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
              @click="goBack"
            >
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </button>
            <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em]', roleInfo.soft]">
              {{ roleInfo.badge }}
            </span>
          </div>

          <div class="text-left lg:text-right">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Choose Your Plan</h1>
            <p class="mt-1 text-sm text-slate-600">Welcome, {{ welcomeName }}. Pick a plan before account setup.</p>
          </div>
        </div>
      </header>

      <section class="mt-6 overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-950 text-white shadow-[0_30px_80px_-38px_rgba(15,23,42,0.45)]">
        <div class="grid gap-0 xl:grid-cols-[1.45fr_0.85fr]">
          <div class="relative overflow-hidden px-6 py-8 sm:px-8">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.28),_transparent_44%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.24),_transparent_40%)]" />
            <div class="relative space-y-6">
              <div class="inline-flex items-center gap-3">
                <div :class="['rounded-2xl p-3', roleInfo.soft]">
                  <component :is="roleInfo.icon" class="h-6 w-6" />
                </div>
                <div>
                  <p class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-300">{{ roleInfo.title }}</p>
                  <p class="text-sm text-slate-400">Subscription setup flow</p>
                </div>
              </div>

              <div class="space-y-3">
                <h2 class="max-w-3xl text-3xl font-semibold tracking-tight sm:text-4xl">
                  A cleaner plan picker that matches the platform you’re building.
                </h2>
                <p class="max-w-2xl text-sm leading-7 text-slate-200/90">
                  Choose a subscription before finishing account setup. You can start small with the free plan or move to a paid plan when you need more coordinators, users, internship posts, and reporting power.
                </p>
              </div>

              <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Plans Available</p>
                  <p class="mt-3 text-2xl font-semibold text-white">{{ plansArray.length || 3 }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Billing Model</p>
                  <p class="mt-3 text-2xl font-semibold text-white">Monthly</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Best Starting Point</p>
                  <p class="mt-3 text-2xl font-semibold text-white">Standard</p>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-4 bg-white px-6 py-8 text-slate-900 sm:px-8">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
              <p class="text-sm font-semibold text-slate-950">What you’re choosing now</p>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                This step sets the default subscription plan that the rest of your registration flow will use.
              </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
              <p class="text-sm font-semibold text-slate-950">Recommended rollout</p>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                Start with <span class="font-semibold text-slate-900">Standard</span> if you want the most balanced setup for real onboarding, placement tracking, and daily operations.
              </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
              <p class="text-sm font-semibold text-slate-950">Flexible later on</p>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                You can still revisit billing later inside the workspace if the organization grows beyond its initial limits.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="mt-8">
        <div v-if="loading" class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
          <div
            v-for="index in 3"
            :key="index"
            class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm"
          >
            <div class="space-y-4">
              <div class="h-4 w-24 animate-pulse rounded bg-slate-200" />
              <div class="h-10 w-36 animate-pulse rounded bg-slate-200" />
              <div class="space-y-2">
                <div class="h-3 animate-pulse rounded bg-slate-200" />
                <div class="h-3 animate-pulse rounded bg-slate-200" />
                <div class="h-3 w-5/6 animate-pulse rounded bg-slate-200" />
              </div>
            </div>
          </div>
        </div>

        <div v-else class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
          <article
            v-for="plan in plansArray"
            :key="plan.name"
            class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-[0_22px_55px_-28px_rgba(15,23,42,0.35)]"
            :class="[
              plan.name === 'Standard' ? `ring-2 ${roleInfo.ring}` : '',
              selectedPlan === plan.name ? 'border-emerald-200 ring-2 ring-emerald-200' : '',
            ]"
          >
            <div :class="['h-1.5 bg-gradient-to-r', roleInfo.accent]" />
            <div class="space-y-5 p-6">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-lg font-semibold text-slate-950">{{ plan.name }}</p>
                  <p class="mt-1 text-sm text-slate-600">{{ plan.description }}</p>
                </div>
                <span
                  v-if="plan.name === 'Standard' || selectedPlan === plan.name"
                  class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                  :class="selectedPlan === plan.name ? 'bg-emerald-100 text-emerald-700' : roleInfo.soft"
                >
                  {{ selectedPlan === plan.name ? 'Selected' : 'Recommended' }}
                </span>
              </div>

              <div>
                <p v-if="plan.price === 'PHP 0'" class="text-4xl font-semibold tracking-tight text-slate-950">
                  {{ plan.price }}
                </p>
                <div v-else class="flex flex-wrap items-end gap-x-2 gap-y-1">
                  <span class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">PHP</span>
                  <p class="text-[2.45rem] font-semibold leading-none tracking-tight text-slate-950 sm:text-[2.7rem]">
                    {{ planAmountValue(plan.price) }}
                  </p>
                  <span class="pb-1 text-sm text-slate-500">/month</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                  {{ plan.price === 'PHP 0' ? 'No monthly charge' : 'Billed monthly' }}
                </p>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Plan Limits</p>
                <div class="mt-3 space-y-2 text-sm text-slate-700">
                  <p v-if="selectedRole === 'school'">
                    {{ formatLimit('Coordinators', plan.limits.coordinators) }}
                  </p>
                  <p v-if="selectedRole === 'school'">
                    {{ formatLimit('Students', plan.limits.students) }}
                  </p>
                  <p v-if="selectedRole === 'company'">
                    {{ formatLimit('Accounts', plan.limits.accounts) }}
                  </p>
                  <p v-if="selectedRole === 'company'">
                    {{ formatLimit('Internships', plan.limits.internships) }}
                  </p>
                </div>
              </div>

              <ul class="space-y-3">
                <li
                  v-for="feature in plan.features"
                  :key="feature"
                  class="flex items-start gap-3 text-sm leading-6 text-slate-700"
                >
                  <span class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                    <CheckIcon class="h-3.5 w-3.5" />
                  </span>
                  <span>{{ feature }}</span>
                </li>
              </ul>

              <button
                type="button"
                class="inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-70"
                :class="selectedPlan === plan.name ? 'bg-emerald-600 hover:bg-emerald-600' : roleInfo.button"
                :disabled="selectedPlan === plan.name"
                @click="selectPlan(plan.name, plan.price)"
              >
                {{ selectedPlan === plan.name ? 'Selected' : 'Continue With This Plan' }}
              </button>
            </div>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>

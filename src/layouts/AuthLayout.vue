<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

import { ArrowLeft } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'

withDefaults(
  defineProps<{
    backTo?: RouteLocationRaw
    backLabel?: string
  }>(),
  {
    backTo: '/',
    backLabel: 'Back',
  },
)
</script>

<template>
  <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(125,211,252,0.3),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(59,130,246,0.14),_transparent_28%),linear-gradient(180deg,_#f8fbff_0%,_#e7eef8_100%)] px-4 py-4 sm:px-6 lg:px-8">
    <div class="mx-auto flex min-h-[calc(100vh-2rem)] max-w-6xl items-center justify-center">
      <div class="grid w-full overflow-hidden rounded-[32px] border border-white/70 bg-white/80 shadow-[0_30px_90px_rgba(15,23,42,0.14)] backdrop-blur xl:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden min-h-[620px] overflow-hidden bg-[linear-gradient(150deg,_#eff6ff_0%,_#dbeafe_45%,_#bfdbfe_100%)] p-8 xl:flex xl:flex-col xl:gap-8">
          <div class="absolute -left-16 top-14 h-40 w-40 rounded-full bg-white/30 blur-2xl" />
          <div class="absolute bottom-10 right-8 h-56 w-56 rounded-full bg-sky-300/25 blur-3xl" />

          <div class="relative z-10">
            <div class="inline-flex items-center gap-3 rounded-full border border-white/60 bg-white/60 px-4 py-2 text-sm font-medium text-slate-700 backdrop-blur">
              <img src="/icons/logo-main.png" alt="OJT Intern Path" class="h-9 w-9 rounded-xl object-contain" />
              <span>OJT Intern Path</span>
            </div>

            <div class="mt-8 max-w-md">
              <p class="text-sm font-semibold uppercase tracking-[0.28em] text-sky-700">
                <slot name="left-eyebrow">Career Portal</slot>
              </p>
              <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">
                <slot name="left-title">Access your internship workspace with a familiar flow.</slot>
              </h2>
              <p class="mt-4 text-sm leading-6 text-slate-700">
                <slot name="left-description">
                  Manage applications, school coordination, and company updates in one place with the simpler side-by-side layout.
                </slot>
              </p>
            </div>

          </div>

          <div class="relative z-10 mt-2 rounded-[28px] border border-white/60 bg-white/55 p-5 shadow-[0_20px_50px_rgba(37,99,235,0.12)] backdrop-blur">
            <slot name="left-form">
              <slot name="left-panel">
              <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-white/80 p-4">
                  <p class="text-sm font-medium text-slate-500">Interns</p>
                  <p class="mt-2 text-xl font-semibold text-slate-900">Track applications</p>
                  <p class="mt-2 text-sm text-slate-600">Stay on top of approvals, requirements, and status changes.</p>
                </div>
                <div class="rounded-2xl bg-slate-900 p-4 text-white">
                  <p class="text-sm font-medium text-sky-100">Schools & Companies</p>
                  <p class="mt-2 text-xl font-semibold">Coordinate faster</p>
                  <p class="mt-2 text-sm text-sky-100/90">Use the shared portal to keep placement updates and onboarding aligned.</p>
                </div>
              </div>
              </slot>
            </slot>
          </div>
        </section>

        <section class="relative flex min-h-[620px] flex-col justify-center bg-white/88 px-5 py-6 sm:px-8 lg:px-10">
          <RouterLink
            :to="backTo"
            class="absolute left-5 top-5 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm transition hover:border-sky-200 hover:text-sky-700 sm:left-8 sm:top-8"
          >
            <ArrowLeft class="h-4 w-4" />
            <span>{{ backLabel }}</span>
          </RouterLink>

          <div class="mx-auto w-full max-w-xl pt-12 sm:pt-14">
            <div class="mb-6 text-center xl:text-left">
              <img src="/icons/logo-main.png" alt="OJT Intern Path" class="mx-auto h-14 w-14 rounded-2xl object-contain xl:mx-0" />
              <p class="mt-3 text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">OJT Intern Path</p>
              <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 sm:text-[2rem]">
                <slot name="title" />
              </h1>
              <p class="mt-2 text-sm leading-6 text-slate-600">
                <slot name="description" />
              </p>
            </div>

            <div class="w-full">
              <slot />
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

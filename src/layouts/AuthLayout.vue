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
  <div class="min-h-screen bg-background px-4 py-4 sm:px-6 lg:px-8">
    <div class="mx-auto flex min-h-[calc(100vh-2rem)] max-w-6xl items-center justify-center">
      <div
        class="grid w-full overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm xl:grid-cols-[1.05fr_0.95fr]"
      >
        <section
          class="relative hidden min-h-[620px] overflow-hidden border-b border-border bg-muted p-8 xl:flex xl:flex-col xl:gap-8 xl:border-b-0 xl:border-r"
        >
          <div class="relative z-10">
            <div
              class="inline-flex items-center gap-3 rounded-full border border-border bg-card px-4 py-2 text-sm font-medium text-foreground"
            >
              <img src="/icons/logo-main.png" alt="OJT Intern Path" class="h-9 w-9 rounded-xl object-contain" />
              <span>OJT Intern Path</span>
            </div>

            <div class="mt-8 max-w-md">
              <p class="text-sm font-semibold uppercase tracking-[0.28em] text-primary">
                <slot name="left-eyebrow">Career Portal</slot>
              </p>
              <h2 class="mt-4 text-3xl font-semibold tracking-tight text-foreground">
                <slot name="left-title">Access your internship workspace with a familiar flow.</slot>
              </h2>
              <p class="mt-4 text-sm leading-6 text-muted-foreground">
                <slot name="left-description">
                  Manage applications, school coordination, and company updates in one place with the simpler side-by-side layout.
                </slot>
              </p>
            </div>
          </div>

          <div class="relative z-10 mt-2 rounded-2xl border border-border bg-card p-5 shadow-sm">
            <slot name="left-form">
              <slot name="left-panel">
                <div class="grid gap-4 sm:grid-cols-2">
                  <div class="rounded-xl border border-border bg-background p-4">
                    <p class="text-sm font-medium text-muted-foreground">Interns</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">Track applications</p>
                    <p class="mt-2 text-sm text-muted-foreground">
                      Stay on top of approvals, requirements, and status changes.
                    </p>
                  </div>
                  <div class="rounded-xl bg-primary p-4 text-primary-foreground">
                    <p class="text-sm font-medium text-primary-foreground/80">Schools & Companies</p>
                    <p class="mt-2 text-xl font-semibold">Coordinate faster</p>
                    <p class="mt-2 text-sm text-primary-foreground/80">
                      Use the shared portal to keep placement updates and onboarding aligned.
                    </p>
                  </div>
                </div>
              </slot>
            </slot>
          </div>
        </section>

        <section class="relative flex min-h-[620px] flex-col justify-center bg-card px-5 py-6 sm:px-8 lg:px-10">
          <RouterLink
            :to="backTo"
            class="absolute left-5 top-5 inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-muted-foreground shadow-sm transition hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:left-8 sm:top-8"
          >
            <ArrowLeft class="h-4 w-4" />
            <span>{{ backLabel }}</span>
          </RouterLink>

          <div class="mx-auto w-full max-w-xl pt-12 sm:pt-14">
            <div class="mb-6 text-center xl:text-left">
              <img
                src="/icons/logo-main.png"
                alt="OJT Intern Path"
                class="mx-auto h-14 w-14 rounded-2xl object-contain xl:mx-0"
              />
              <p class="mt-3 text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground">
                OJT Intern Path
              </p>
              <h1 class="mt-2 text-3xl font-semibold tracking-tight text-foreground sm:text-[2rem]">
                <slot name="title" />
              </h1>
              <p class="mt-2 text-sm leading-6 text-muted-foreground">
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

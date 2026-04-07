<script setup lang="ts">
import {
  ArrowRight,
  Building2,
  CheckCircle2,
  ClipboardList,
  GraduationCap,
  LayoutDashboard,
  Menu,
  Sparkles,
} from 'lucide-vue-next'
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

import Button from '@/components/ui/button/Button.vue'
import Card from '@/components/ui/card/Card.vue'
import CardContent from '@/components/ui/card/CardContent.vue'

const mobileMenuOpen = ref(false)

const features = [
  {
    icon: Sparkles,
    title: 'Smarter Matching',
    description:
      'Connect students to the right internships faster using structured profiles, fit signals, and guided workflows.',
  },
  {
    icon: LayoutDashboard,
    title: 'Shared Visibility',
    description:
      'Give schools, companies, and interns one place to track progress, documents, and approvals in real time.',
  },
  {
    icon: ClipboardList,
    title: 'Less Manual Work',
    description:
      'Streamline applications, endorsements, and internship coordination without juggling scattered spreadsheets.',
  },
]

const steps = [
  {
    number: '01',
    title: 'Create Your Workspace',
    description: 'Sign up as a student, school, or company and set up the information needed for your role.',
  },
  {
    number: '02',
    title: 'Connect Opportunities',
    description: 'Post openings, review profiles, and endorse candidates with a cleaner collaboration flow.',
  },
  {
    number: '03',
    title: 'Track the Journey',
    description: 'Monitor applications, requirements, and internship milestones from one shared dashboard.',
  },
]

const rolePaths = [
  {
    title: 'For Schools',
    description: 'Create student accounts, manage endorsements, and track placements in one workspace.',
    to: '/register/school',
    cta: 'Register as School',
  },
  {
    title: 'For Companies',
    description: 'Post internships, review endorsed students, and manage applicant decisions clearly.',
    to: '/register/company',
    cta: 'Register as Company',
  },
  {
    title: 'For Students',
    description: 'Browse OJT opportunities, including school-hosted placements and partner-school options.',
    to: '/opportunities',
    cta: 'Browse Opportunities',
  },
]
</script>

<template>
  <div class="min-h-screen bg-[linear-gradient(180deg,#f8fafc_0%,#eff6ff_28%,#ffffff_100%)] text-slate-950">
    <header class="sticky top-0 z-30 border-b border-white/60 bg-white/80 backdrop-blur-xl">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <RouterLink to="/" class="flex items-center gap-3">
          <img src="/icons/logo-main.png" alt="OJT Intern Path" class="h-11 w-11 rounded-2xl object-contain ring-1 ring-slate-200" />
          <div>
            <p class="text-sm font-semibold tracking-wide text-slate-950">OJT Intern Path</p>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Internship Platform</p>
          </div>
        </RouterLink>

        <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
          <a href="#features" class="transition hover:text-slate-950">Features</a>
          <a href="#how-it-works" class="transition hover:text-slate-950">How It Works</a>
          <a href="#footer" class="transition hover:text-slate-950">Contact</a>
        </nav>

        <div class="hidden items-center gap-3 md:flex">
          <RouterLink to="/login">
            <Button variant="outline">Log In</Button>
          </RouterLink>
          <RouterLink to="/register">
            <Button>Get Started</Button>
          </RouterLink>
        </div>

        <Button variant="ghost" size="icon" class="md:hidden" @click="mobileMenuOpen = !mobileMenuOpen">
          <Menu class="h-5 w-5" />
        </Button>
      </div>

      <div v-if="mobileMenuOpen" class="border-t border-slate-200 bg-white px-4 py-4 md:hidden">
        <div class="flex flex-col gap-3 text-sm font-medium text-slate-600">
          <a href="#features" class="transition hover:text-slate-950" @click="mobileMenuOpen = false">Features</a>
          <a href="#how-it-works" class="transition hover:text-slate-950" @click="mobileMenuOpen = false">How It Works</a>
          <a href="#footer" class="transition hover:text-slate-950" @click="mobileMenuOpen = false">Contact</a>
          <RouterLink to="/login" class="pt-2" @click="mobileMenuOpen = false">
            <Button variant="outline" class="w-full">Log In</Button>
          </RouterLink>
          <RouterLink to="/register" @click="mobileMenuOpen = false">
            <Button class="w-full">Get Started</Button>
          </RouterLink>
        </div>
      </div>
    </header>

    <main>
      <section class="relative overflow-hidden">
        <div class="absolute inset-x-0 top-0 -z-10 h-[28rem] bg-[radial-gradient(circle_at_top_right,_rgba(59,130,246,0.18),_transparent_42%),radial-gradient(circle_at_left,_rgba(14,165,233,0.14),_transparent_32%)]" />
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 md:py-24 lg:grid-cols-[1.1fr_0.9fr] lg:px-8">
          <div class="max-w-2xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white/80 px-4 py-1.5 text-sm font-medium text-sky-800 shadow-sm">
              <Sparkles class="h-4 w-4" />
              Built for students, schools, and companies
            </div>

            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
              Run internship programs with clarity, speed, and better matches.
            </h1>

            <p class="mt-6 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">
              OJT Intern Path brings applications, endorsements, tracking, and collaboration into one responsive platform so every stakeholder can move with confidence.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <RouterLink to="/register">
                <Button class="h-11 w-full sm:w-auto">
                  Get Started
                  <ArrowRight class="h-4 w-4" />
                </Button>
              </RouterLink>
              <RouterLink to="/opportunities">
                <Button variant="outline" class="h-11 w-full sm:w-auto">Browse Opportunities</Button>
              </RouterLink>
              <RouterLink to="/login">
                <Button variant="outline" class="h-11 w-full sm:w-auto">Log In</Button>
              </RouterLink>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-3">
              <div class="rounded-2xl border border-white/80 bg-white/80 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <GraduationCap class="h-4 w-4 text-sky-600" />
                  Students
                </div>
                <p class="mt-2 text-sm text-slate-600">Discover internships that fit your goals and track your progress clearly.</p>
              </div>
              <div class="rounded-2xl border border-white/80 bg-white/80 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <CheckCircle2 class="h-4 w-4 text-sky-600" />
                  Schools
                </div>
                <p class="mt-2 text-sm text-slate-600">Coordinate endorsements, documents, and reporting with fewer manual steps.</p>
              </div>
              <div class="rounded-2xl border border-white/80 bg-white/80 p-4 shadow-sm backdrop-blur">
                <div class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                  <Building2 class="h-4 w-4 text-sky-600" />
                  Companies
                </div>
                <p class="mt-2 text-sm text-slate-600">Post roles, review applicants, and manage internship workflows in one place.</p>
              </div>
            </div>
          </div>

          <div class="relative">
            <div class="absolute -left-6 top-10 hidden h-24 w-24 rounded-full bg-sky-200/60 blur-2xl sm:block" />
            <div class="absolute -right-6 bottom-10 hidden h-32 w-32 rounded-full bg-blue-300/50 blur-3xl sm:block" />
            <div class="relative rounded-[2rem] border border-white/70 bg-white/85 p-4 shadow-2xl shadow-slate-300/30 backdrop-blur sm:p-6">
              <img
                src="/undraw_working-remotely_ivtz-1024x815.webp"
                alt="Internship collaboration illustration"
                class="w-full rounded-[1.5rem] bg-slate-50 object-cover"
              />
              <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-950 px-4 py-3 text-white">
                  <p class="text-xs uppercase tracking-[0.2em] text-slate-300">Faster Coordination</p>
                  <p class="mt-2 text-sm text-slate-100">Align applicants, schools, and companies without chasing updates manually.</p>
                </div>
                <div class="rounded-2xl bg-sky-50 px-4 py-3 text-slate-900 ring-1 ring-sky-100">
                  <p class="text-xs uppercase tracking-[0.2em] text-sky-700">Shared Tracking</p>
                  <p class="mt-2 text-sm text-slate-600">Keep documents, approvals, and progress visible from application to completion.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
          <p class="text-sm font-semibold uppercase tracking-[0.3em] text-sky-700">Choose Your Path</p>
          <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
            One platform, different workflows for schools, companies, and interns.
          </h2>
          <p class="mt-4 text-base leading-7 text-slate-600">
            The experience stays consistent, but each workspace is shaped around the actions that role needs most.
          </p>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-3">
          <Card
            v-for="rolePath in rolePaths"
            :key="rolePath.title"
            class="border-slate-200/80 bg-white/90 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl"
          >
            <CardContent class="space-y-5 p-6">
              <div>
                <h3 class="text-xl font-semibold text-slate-950">{{ rolePath.title }}</h3>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ rolePath.description }}</p>
              </div>
              <RouterLink :to="rolePath.to">
                <Button variant="outline" class="w-full justify-center">
                  {{ rolePath.cta }}
                </Button>
              </RouterLink>
            </CardContent>
          </Card>
        </div>
      </section>

      <section id="features" class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
        <div class="max-w-2xl">
          <p class="text-sm font-semibold uppercase tracking-[0.3em] text-sky-700">Features</p>
          <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
            Everything teams need to run internships with less friction.
          </h2>
          <p class="mt-4 text-base leading-7 text-slate-600">
            The platform combines role-based workflows with a cleaner interface so daily internship operations feel more organized and easier to act on.
          </p>
        </div>

        <div class="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          <Card
            v-for="feature in features"
            :key="feature.title"
            class="border-slate-200/80 bg-white/90 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl"
          >
            <CardContent class="p-6">
              <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-100 text-sky-700">
                <component :is="feature.icon" class="h-5 w-5" />
              </div>
              <h3 class="mt-5 text-xl font-semibold text-slate-950">{{ feature.title }}</h3>
              <p class="mt-3 text-sm leading-6 text-slate-600">{{ feature.description }}</p>
            </CardContent>
          </Card>
        </div>
      </section>

      <section id="how-it-works" class="border-y border-slate-200 bg-slate-950 text-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
          <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-sky-300">How It Works</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
              A simple workflow from onboarding to internship completion.
            </h2>
            <p class="mt-4 text-base leading-7 text-slate-300">
              Each step is designed to reduce confusion, improve coordination, and help everyone stay aligned.
            </p>
          </div>

          <div class="mt-10 grid gap-6 lg:grid-cols-3">
            <div
              v-for="step in steps"
              :key="step.number"
              class="rounded-3xl border border-white/10 bg-white/5 p-6 backdrop-blur"
            >
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-400/15 text-lg font-semibold text-sky-200 ring-1 ring-sky-300/20">
                {{ step.number }}
              </div>
              <h3 class="mt-6 text-xl font-semibold">{{ step.title }}</h3>
              <p class="mt-3 text-sm leading-6 text-slate-300">{{ step.description }}</p>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer id="footer" class="border-t border-slate-200 bg-white">
      <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:px-6 md:flex-row md:items-start md:justify-between lg:px-8">
        <div class="max-w-sm">
          <div class="flex items-center gap-3">
            <img src="/icons/logo-main.png" alt="OJT Intern Path" class="h-11 w-11 rounded-2xl object-contain ring-1 ring-slate-200" />
            <div>
              <p class="text-sm font-semibold text-slate-950">OJT Intern Path</p>
              <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Internship Platform</p>
            </div>
          </div>
          <p class="mt-4 text-sm leading-6 text-slate-600">
            Helping students, schools, and companies move through internship workflows with better visibility and less administrative drag.
          </p>
        </div>

        <div class="grid gap-8 sm:grid-cols-2">
          <div>
            <p class="text-sm font-semibold text-slate-950">Explore</p>
            <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600">
              <a href="#features" class="transition hover:text-slate-950">Features</a>
              <a href="#how-it-works" class="transition hover:text-slate-950">How It Works</a>
              <RouterLink to="/find-internships" class="transition hover:text-slate-950">Find Internships</RouterLink>
            </div>
          </div>
          <div>
            <p class="text-sm font-semibold text-slate-950">Access</p>
            <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600">
              <RouterLink to="/login" class="transition hover:text-slate-950">Login</RouterLink>
              <RouterLink to="/register" class="transition hover:text-slate-950">Register</RouterLink>
              <RouterLink to="/change-password" class="transition hover:text-slate-950">Change Password</RouterLink>
            </div>
          </div>
        </div>
      </div>

      <div class="border-t border-slate-200 px-4 py-4 text-center text-sm text-slate-500 sm:px-6 lg:px-8">
        © {{ new Date().getFullYear() }} OJT Intern Path. All rights reserved.
      </div>
    </footer>
  </div>
</template>

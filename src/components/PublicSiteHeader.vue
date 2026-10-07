<script setup lang="ts">
import { Menu } from 'lucide-vue-next'
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

import Button from '@/components/ui/button/Button.vue'

withDefaults(
  defineProps<{
    /** Highlight the Opportunities nav item when on that page */
    active?: 'home' | 'opportunities' | null
  }>(),
  {
    active: null,
  },
)

const mobileMenuOpen = ref(false)

function closeMenu() {
  mobileMenuOpen.value = false
}
</script>

<template>
  <header class="sticky top-0 z-30 border-b border-white/60 bg-white/80 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
      <RouterLink to="/" class="flex items-center gap-3" @click="closeMenu">
        <img
          src="/icons/logo-main.png"
          alt="OJT Intern Path"
          class="h-11 w-11 rounded-2xl object-contain ring-1 ring-slate-200"
        />
        <div>
          <p class="text-sm font-semibold tracking-wide text-slate-950">OJT Intern Path</p>
          <p class="text-xs uppercase tracking-[0.3em] text-slate-500">Internship Platform</p>
        </div>
      </RouterLink>

      <nav class="hidden items-center gap-8 text-sm font-medium text-slate-600 md:flex">
        <RouterLink
          to="/"
          class="transition hover:text-slate-950"
          :class="active === 'home' ? 'text-slate-950' : ''"
        >
          Home
        </RouterLink>
        <RouterLink
          to="/opportunities"
          class="transition hover:text-slate-950"
          :class="active === 'opportunities' ? 'text-slate-950' : ''"
        >
          Opportunities
        </RouterLink>
        <RouterLink to="/#features" class="transition hover:text-slate-950">Features</RouterLink>
        <RouterLink to="/#how-it-works" class="transition hover:text-slate-950">How It Works</RouterLink>
      </nav>

      <div class="hidden items-center gap-3 md:flex">
        <RouterLink to="/login">
          <Button variant="outline">Log In</Button>
        </RouterLink>
        <RouterLink to="/register">
          <Button>Get Started</Button>
        </RouterLink>
      </div>

      <Button
        variant="ghost"
        size="icon"
        class="md:hidden"
        aria-label="Toggle menu"
        :aria-expanded="mobileMenuOpen"
        @click="mobileMenuOpen = !mobileMenuOpen"
      >
        <Menu class="h-5 w-5" />
      </Button>
    </div>

    <div v-if="mobileMenuOpen" class="border-t border-slate-200 bg-white px-4 py-4 md:hidden">
      <div class="flex flex-col gap-3 text-sm font-medium text-slate-600">
        <RouterLink to="/" class="transition hover:text-slate-950" @click="closeMenu">Home</RouterLink>
        <RouterLink to="/opportunities" class="transition hover:text-slate-950" @click="closeMenu">
          Opportunities
        </RouterLink>
        <RouterLink to="/#features" class="transition hover:text-slate-950" @click="closeMenu">Features</RouterLink>
        <RouterLink to="/#how-it-works" class="transition hover:text-slate-950" @click="closeMenu">
          How It Works
        </RouterLink>
        <RouterLink to="/login" class="pt-2" @click="closeMenu">
          <Button variant="outline" class="w-full">Log In</Button>
        </RouterLink>
        <RouterLink to="/register" @click="closeMenu">
          <Button class="w-full">Get Started</Button>
        </RouterLink>
      </div>
    </div>
  </header>
</template>

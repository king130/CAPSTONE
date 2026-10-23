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
  <header class="sticky top-0 z-header border-b border-border bg-card/80 backdrop-blur-xl">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
      <RouterLink to="/" class="flex items-center gap-3" @click="closeMenu">
        <img
          src="/icons/logo-main.png"
          alt="OJT Intern Path"
          class="h-11 w-11 rounded-2xl object-contain ring-1 ring-slate-200"
        />
        <div>
          <p class="text-sm font-semibold tracking-wide text-foreground">OJT Intern Path</p>
          <p class="text-xs uppercase tracking-[0.3em] text-muted-foreground">Internship Platform</p>
        </div>
      </RouterLink>

      <nav class="hidden items-center gap-8 text-sm font-medium text-muted-foreground md:flex">
        <RouterLink
          to="/"
          class="transition hover:text-foreground"
          :class="active === 'home' ? 'text-foreground' : ''"
        >
          Home
        </RouterLink>
        <RouterLink
          to="/opportunities"
          class="transition hover:text-foreground"
          :class="active === 'opportunities' ? 'text-foreground' : ''"
        >
          Opportunities
        </RouterLink>
        <RouterLink to="/#features" class="transition hover:text-foreground">Features</RouterLink>
        <RouterLink to="/#how-it-works" class="transition hover:text-foreground">How It Works</RouterLink>
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

    <div v-if="mobileMenuOpen" class="border-t border-border bg-card px-4 py-4 md:hidden">
      <div class="flex flex-col gap-3 text-sm font-medium text-muted-foreground">
        <RouterLink to="/" class="transition hover:text-foreground" @click="closeMenu">Home</RouterLink>
        <RouterLink to="/opportunities" class="transition hover:text-foreground" @click="closeMenu">
          Opportunities
        </RouterLink>
        <RouterLink to="/#features" class="transition hover:text-foreground" @click="closeMenu">Features</RouterLink>
        <RouterLink to="/#how-it-works" class="transition hover:text-foreground" @click="closeMenu">
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

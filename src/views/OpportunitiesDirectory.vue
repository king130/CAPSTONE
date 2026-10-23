<script setup lang="ts">
import OrganizationDirectory from '@/components/opportunities/OrganizationDirectory.vue'
import MainLayout from '@/layouts/MainLayout.vue'
</script>

<template>
  <MainLayout role="company" title="Browse" active-item="opportunities">
    <div class="space-y-2">
      <h2 class="text-2xl font-semibold text-foreground">Schools</h2>
      <p class="max-w-2xl text-sm text-muted-foreground">
        Browse schools that take part in OJT. Open a public profile to see the information the school has published.
      </p>
    </div>
    <div class="mt-6">
      <OrganizationDirectory kind="school" />
    </div>
  </MainLayout>
</template>

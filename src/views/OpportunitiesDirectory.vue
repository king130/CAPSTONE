<script setup lang="ts">
import OrganizationDirectory from '@/components/opportunities/OrganizationDirectory.vue'
import { schoolsBrowseConfig } from '@/composables/browseDirectory/configs'
import MainLayout from '@/layouts/MainLayout.vue'
</script>

<template>
  <MainLayout role="company" title="Partner Schools" active-item="opportunities">
    <div class="space-y-2">
      <h2 class="text-2xl font-semibold text-foreground">Partner Schools</h2>
      <p class="max-w-2xl text-sm text-muted-foreground">
        {{ schoolsBrowseConfig.description }}
      </p>
    </div>
    <div class="mt-6">
      <OrganizationDirectory page-key="schools-browse" />
    </div>
  </MainLayout>
</template>

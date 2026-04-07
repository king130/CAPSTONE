<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'

import MainLayout from '@/layouts/MainLayout.vue'

const route = useRoute()

const layoutTitle = computed(() => String(route.meta.layoutTitle ?? 'Admin'))
const activeItem = computed(() => String(route.meta.navKey ?? route.name ?? 'admin-overview'))
</script>

<template>
  <MainLayout role="admin" :title="layoutTitle" :active-item="activeItem">
    <RouterView />
  </MainLayout>
</template>

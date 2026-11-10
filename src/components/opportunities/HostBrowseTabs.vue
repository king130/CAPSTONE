<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'

import OrganizationDirectory from '@/components/opportunities/OrganizationDirectory.vue'
import Button from '@/components/ui/button/Button.vue'
import type { BrowsePageKey } from '@/composables/browseDirectory/types'
import { browseConfigFor } from '@/composables/browseDirectory/configs'

const props = withDefaults(
  defineProps<{
    /** When true, titles/empty states describe school–partner hosts only (intern audience). */
    partnerScoped?: boolean
  }>(),
  { partnerScoped: false },
)

const emit = defineEmits<{
  'update:title': [title: string]
}>()

const activePage = ref<BrowsePageKey>('companies-browse')
const tabs: BrowsePageKey[] = ['companies-browse', 'municipal-browse']

function configFor(tab: BrowsePageKey) {
  return browseConfigFor(tab, { partnerScoped: props.partnerScoped })
}

function selectTab(tab: BrowsePageKey) {
  activePage.value = tab
}

function emitTitle() {
  emit('update:title', configFor(activePage.value).title)
}

watch(activePage, emitTitle)
watch(
  () => props.partnerScoped,
  () => emitTitle(),
)
onMounted(emitTitle)
</script>

<template>
  <div class="space-y-4 px-4 md:space-y-5 md:px-0">
    <div
      class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      role="tablist"
      aria-label="Browse hosts"
    >
      <Button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="min-h-11 shrink-0 whitespace-nowrap"
        role="tab"
        :aria-selected="activePage === tab"
        :variant="activePage === tab ? 'default' : 'outline'"
        @click="selectTab(tab)"
      >
        {{ configFor(tab).title }}
      </Button>
    </div>

    <div
      v-for="tab in tabs"
      v-show="activePage === tab"
      :key="tab"
      role="tabpanel"
      :aria-label="configFor(tab).title"
    >
      <div class="mb-3 space-y-1 md:mb-4">
        <h2 class="text-xl font-semibold text-foreground md:text-2xl">{{ configFor(tab).title }}</h2>
        <p class="text-sm text-muted-foreground">{{ configFor(tab).description }}</p>
      </div>
      <!-- Keep both mounted for isolated pageKey state; refresh when tab becomes active. -->
      <OrganizationDirectory
        :page-key="tab"
        :active="activePage === tab"
        :partner-scoped="partnerScoped"
      />
    </div>
  </div>
</template>

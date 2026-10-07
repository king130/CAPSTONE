<script setup lang="ts">
import { Building2, ChevronLeft, ChevronRight, X } from 'lucide-vue-next'
import { computed, onUnmounted, ref, watch } from 'vue'

import Button from '@/components/ui/button/Button.vue'

export interface GalleryImage {
  id: string
  url: string
  caption?: string | null
}

const props = withDefaults(
  defineProps<{
    images: GalleryImage[]
    altFallback?: string
  }>(),
  {
    altFallback: 'Organization photo',
  },
)

const activeIndex = ref(0)
const lightboxOpen = ref(false)

const active = computed(() => props.images[activeIndex.value] ?? null)

watch(
  () => props.images,
  () => {
    activeIndex.value = 0
  },
)

function altFor(image: GalleryImage | null): string {
  if (!image) return props.altFallback
  return image.caption?.trim() || props.altFallback
}

function select(index: number) {
  if (index < 0 || index >= props.images.length) return
  activeIndex.value = index
}

function openLightbox(index = activeIndex.value) {
  if (!props.images.length) return
  activeIndex.value = index
  lightboxOpen.value = true
}

function closeLightbox() {
  lightboxOpen.value = false
}

function step(delta: number) {
  if (!props.images.length) return
  const next = (activeIndex.value + delta + props.images.length) % props.images.length
  activeIndex.value = next
}

function onKeydown(event: KeyboardEvent) {
  if (!lightboxOpen.value) return
  if (event.key === 'Escape') closeLightbox()
  if (event.key === 'ArrowLeft') step(-1)
  if (event.key === 'ArrowRight') step(1)
}

watch(lightboxOpen, (open) => {
  if (typeof window === 'undefined') return
  if (open) window.addEventListener('keydown', onKeydown)
  else window.removeEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', onKeydown)
  }
})
</script>

<template>
  <div class="space-y-3">
    <button
      type="button"
      class="relative aspect-[16/10] w-full overflow-hidden rounded-xl border border-border bg-muted text-left"
      :disabled="!active"
      @click="openLightbox()"
    >
      <img
        v-if="active"
        :src="active.url"
        :alt="altFor(active)"
        class="h-full w-full object-cover"
        loading="lazy"
      />
      <div
        v-else
        class="flex h-full w-full flex-col items-center justify-center gap-2 text-muted-foreground"
      >
        <Building2 class="h-8 w-8 opacity-70" />
        <span class="text-sm">No photos yet</span>
      </div>
    </button>

    <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="(image, index) in images"
        :key="image.id"
        type="button"
        class="relative h-16 w-24 shrink-0 overflow-hidden rounded-lg border border-border bg-muted"
        :class="index === activeIndex ? 'ring-2 ring-ring' : 'opacity-80 hover:opacity-100'"
        @click="select(index)"
      >
        <img
          :src="image.url"
          :alt="altFor(image)"
          class="h-full w-full object-cover"
          loading="lazy"
        />
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="lightboxOpen && active"
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/90 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        @click.self="closeLightbox"
      >
        <Button
          type="button"
          size="icon"
          variant="ghost"
          class="absolute right-4 top-4"
          aria-label="Close gallery"
          @click="closeLightbox"
        >
          <X class="h-5 w-5" />
        </Button>
        <Button
          v-if="images.length > 1"
          type="button"
          size="icon"
          variant="ghost"
          class="absolute left-4 top-1/2 -translate-y-1/2"
          aria-label="Previous photo"
          @click="step(-1)"
        >
          <ChevronLeft class="h-6 w-6" />
        </Button>
        <figure class="max-h-[85vh] max-w-5xl">
          <img
            :src="active.url"
            :alt="altFor(active)"
            class="max-h-[80vh] w-full rounded-lg object-contain"
          />
          <figcaption v-if="active.caption" class="mt-3 text-center text-sm text-muted-foreground">
            {{ active.caption }}
          </figcaption>
        </figure>
        <Button
          v-if="images.length > 1"
          type="button"
          size="icon"
          variant="ghost"
          class="absolute right-4 top-1/2 -translate-y-1/2"
          aria-label="Next photo"
          @click="step(1)"
        >
          <ChevronRight class="h-6 w-6" />
        </Button>
      </div>
    </Teleport>
  </div>
</template>

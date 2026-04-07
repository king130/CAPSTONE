<script setup lang="ts">
import { computed } from 'vue'

import { cn } from '@/lib/utils'

const props = withDefaults(
  defineProps<{
    src?: string
    alt?: string
    fallback: string
    class?: string
    imageClass?: string
    fallbackClass?: string
  }>(),
  {
    src: '',
    alt: 'Avatar',
    class: '',
    imageClass: '',
    fallbackClass: '',
  },
)

const wrapperClass = computed(() =>
  cn('relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full', props.class),
)

const imageClass = computed(() => cn('aspect-square h-full w-full object-cover', props.imageClass))

const fallbackClass = computed(() =>
  cn(
    'flex h-full w-full items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary',
    props.fallbackClass,
  ),
)
</script>

<template>
  <div :class="wrapperClass">
    <img v-if="src" :src="src" :alt="alt" :class="imageClass" />
    <div v-else :class="fallbackClass">{{ fallback }}</div>
  </div>
</template>

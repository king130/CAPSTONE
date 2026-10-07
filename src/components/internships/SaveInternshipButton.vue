<script setup lang="ts">
import { Heart } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useToast } from '@/composables/useToast'
import { toggleSavedInternship } from '@/services/savedInternships'
import { useAuthStore } from '@/stores/auth'

const props = withDefaults(
  defineProps<{
    internshipId: string
    saved?: boolean | null
    size?: 'sm' | 'md'
  }>(),
  {
    saved: false,
    size: 'md',
  },
)

const emit = defineEmits<{
  'update:saved': [value: boolean]
}>()

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const { error } = useToast()

const localSaved = ref(Boolean(props.saved))
const busy = ref(false)

watch(
  () => props.saved,
  (value) => {
    localSaved.value = Boolean(value)
  },
)

async function onToggle() {
  if (!authStore.user || authStore.user.role !== 'student') {
    void router.push({
      name: 'login',
      query: { redirect: route.fullPath || `/internships/${props.internshipId}` },
    })
    return
  }

  if (busy.value) return
  const previous = localSaved.value
  localSaved.value = !previous
  emit('update:saved', localSaved.value)
  busy.value = true
  try {
    const next = await toggleSavedInternship(props.internshipId, previous)
    localSaved.value = next
    emit('update:saved', next)
  } catch (err) {
    localSaved.value = previous
    emit('update:saved', previous)
    error(err, { fallback: 'Unable to update saved internship.' })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center justify-center rounded-full border border-border bg-card/95 text-foreground shadow-sm transition hover:bg-accent"
    :class="size === 'sm' ? 'h-8 w-8' : 'h-10 w-10'"
    :aria-pressed="localSaved"
    :aria-label="localSaved ? 'Remove from saved internships' : 'Save internship'"
    :disabled="busy"
    @click.stop.prevent="onToggle"
  >
    <Heart
      class="h-4 w-4"
      :class="localSaved ? 'fill-red-500 text-red-500' : 'text-muted-foreground'"
    />
  </button>
</template>

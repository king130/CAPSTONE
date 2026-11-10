import { computed, onMounted, ref } from 'vue'

import {
  fetchActivePlacement,
  type ActivePlacementSummary,
} from '@/services/applications'
import { useAuthStore } from '@/stores/auth'

const shared = ref<ActivePlacementSummary | null>(null)
const loaded = ref(false)
let inflight: Promise<ActivePlacementSummary | null> | null = null

export async function refreshActivePlacement() {
  const auth = useAuthStore()
  if (auth.user?.role !== 'student') {
    shared.value = null
    loaded.value = true
    return null
  }
  if (!inflight) {
    inflight = fetchActivePlacement()
      .then((value) => {
        shared.value = value
        loaded.value = true
        return value
      })
      .finally(() => {
        inflight = null
      })
  }
  return inflight
}

export function useActivePlacement() {
  const auth = useAuthStore()

  onMounted(() => {
    if (auth.user?.role === 'student' && !loaded.value) {
      void refreshActivePlacement()
    }
  })

  const activePlacement = computed(() => shared.value)
  const hasActivePlacement = computed(() => !!shared.value)
  const placementBlockMessage = computed(() => {
    if (!shared.value) return ''
    return shared.value.message
      || `You already have an accepted placement at ${shared.value.hostName}.`
  })

  return {
    activePlacement,
    hasActivePlacement,
    placementBlockMessage,
    refreshActivePlacement,
  }
}

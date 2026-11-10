import { computed, ref } from 'vue'

const isOpen = ref(false)
const unreadCount = ref(0)

export function useFloatingChat() {
  function open() {
    // Guard: FloatingChatWidget listens for `chat:open` and calls open() again.
    // Without this early return, open ↔ event ↔ open recurses until stack overflow.
    if (isOpen.value) return
    isOpen.value = true
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('chat:open'))
    }
  }

  function close() {
    isOpen.value = false
  }

  function setUnread(count: number) {
    unreadCount.value = Math.max(0, count)
  }

  const badgeLabel = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)))

  return {
    isOpen,
    unreadCount,
    badgeLabel,
    open,
    close,
    setUnread,
  }
}

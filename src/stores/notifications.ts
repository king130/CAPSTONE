import { defineStore } from 'pinia'
import { ref } from 'vue'
import { markNotificationRead, subscribeToNotifications, type NotificationItem } from '@/services/notifications'

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref<NotificationItem[]>([])
  const unsubscribe = ref<null | (() => void)>(null)

  const unreadCount = () => items.value.filter((item) => !item.readAt).length

  function start(userId: string) {
    stop()
    unsubscribe.value = subscribeToNotifications(userId, (updated) => {
      items.value = updated
    })
  }

  function stop() {
    if (unsubscribe.value) {
      unsubscribe.value()
      unsubscribe.value = null
    }
    items.value = []
  }

  async function markRead(notificationId: string) {
    await markNotificationRead(notificationId)
  }

  async function markAllRead() {
    const unread = items.value.filter((item) => !item.readAt)
    await Promise.all(unread.map((item) => markNotificationRead(item.id)))
  }

  return {
    items,
    unreadCount,
    start,
    stop,
    markRead,
    markAllRead,
  }
})

import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type Notification,
} from '@/services/notifications'
import { getToken } from '@/services/http'

const POLL_INTERVAL_MS = 60000

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref<Notification[]>([])
  const loading = ref(false)
  const pollingId = ref<number | null>(null)

  const unreadCount = computed(() => items.value.filter((item) => !item.isRead).length)
  const recentItems = computed(() => items.value.slice(0, 5))
  const unreadItems = computed(() => items.value.filter((item) => !item.isRead))

  async function fetchNotifications() {
    if (!getToken()) {
      items.value = []
      loading.value = false
      return
    }

    loading.value = true
    try {
      const notifications = await listNotifications()
      items.value = notifications.sort((left, right) => {
        return new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime()
      })
    } catch (caughtError) {
      items.value = []
      throw caughtError
    } finally {
      loading.value = false
    }
  }

  function startPolling() {
    stopPolling()
    void fetchNotifications().catch(() => {})
    pollingId.value = window.setInterval(() => {
      void fetchNotifications().catch(() => {})
    }, POLL_INTERVAL_MS)
  }

  function stopPolling() {
    if (pollingId.value) {
      window.clearInterval(pollingId.value)
      pollingId.value = null
    }
  }

  async function markRead(notificationId: number) {
    await markNotificationRead(notificationId)
    items.value = items.value.map((item) => {
      if (item.id !== notificationId) return item
      return { ...item, isRead: true }
    })
  }

  async function markAllRead() {
    await markAllNotificationsRead()
    items.value = items.value.map((item) => ({ ...item, isRead: true }))
  }

  function clear() {
    stopPolling()
    items.value = []
  }

  return {
    items,
    loading,
    unreadCount,
    unreadItems,
    recentItems,
    fetchNotifications,
    startPolling,
    stopPolling,
    markRead,
    markAllRead,
    clear,
  }
})

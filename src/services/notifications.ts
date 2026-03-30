import { apiFetch } from './http'

export interface NotificationItem {
  id: string
  userId: string
  title: string
  body: string
  readAt?: string | null
  createdAt?: string
}

export function subscribeToNotifications(
  _userId: string,
  callback: (items: NotificationItem[]) => void
): () => void {
  let cancelled = false
  async function load() {
    try {
      const res = await apiFetch<{ data: NotificationItem[] }>('/notifications')
      if (!cancelled) callback(res.data ?? [])
    } catch {
      if (!cancelled) callback([])
    }
  }
  load()
  const id = window.setInterval(load, 30000)
  return () => {
    cancelled = true
    clearInterval(id)
  }
}

export async function sendNotification(_userId: string, _title: string, _body: string): Promise<void> {}

export async function markNotificationRead(notificationId: string): Promise<void> {
  await apiFetch(`/notifications/${notificationId}/read`, { method: 'PATCH' })
}

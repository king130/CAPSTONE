import { apiFetch } from './http'

export type NotificationType = 'application' | 'endorsement' | 'hours' | 'system' | 'registration'

export interface Notification {
  id: number
  type: NotificationType
  message: string
  isRead: boolean
  createdAt: string
  redirectTo?: string
}

interface NotificationResponse {
  data: NotificationApiRecord[]
}

interface NotificationApiRecord {
  id: number | string
  type?: string | null
  message?: string | null
  title?: string | null
  body?: string | null
  isRead?: boolean | null
  readAt?: string | null
  createdAt?: string | null
  created_at?: string | null
  redirectTo?: string | null
  redirect_to?: string | null
}

function normalizeType(type?: string | null): NotificationType {
  const normalized = String(type || 'system').trim().toLowerCase()

  if (normalized === 'application') return 'application'
  if (normalized === 'endorsement') return 'endorsement'
  if (normalized === 'hours') return 'hours'
  if (normalized === 'registration') return 'registration'

  return 'system'
}

function mapNotification(record: NotificationApiRecord): Notification {
  return {
    id: Number(record.id),
    type: normalizeType(record.type),
    message: String(record.message || record.body || record.title || 'Notification update'),
    isRead: Boolean(record.isRead ?? record.readAt),
    createdAt: String(record.createdAt || record.created_at || new Date().toISOString()),
    redirectTo: record.redirectTo || record.redirect_to || undefined,
  }
}

export async function listNotifications(): Promise<Notification[]> {
  const response = await apiFetch<NotificationResponse>('/notifications')
  return (response.data ?? []).map(mapNotification)
}

export async function markNotificationRead(notificationId: number): Promise<void> {
  await apiFetch(`/notifications/${notificationId}/read`, { method: 'PATCH' })
}

export async function markAllNotificationsRead(): Promise<void> {
  const notifications = await listNotifications()
  await Promise.all(
    notifications
      .filter((notification) => !notification.isRead)
      .map((notification) => markNotificationRead(notification.id)),
  )
}

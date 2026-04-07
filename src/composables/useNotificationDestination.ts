import type { UserRole } from '@/services/auth'
import type { Notification } from '@/services/notifications'

export function resolveNotificationDestination(notification: Notification, role: UserRole): string {
  if (notification.redirectTo) {
    return notification.redirectTo
  }

  if (role === 'student') {
    if (notification.type === 'hours') return '/ojt-hours'
    return '/intern?view=internship'
  }

  if (role === 'school') {
    if (notification.type === 'registration') return '/school?view=student-interns'
    if (notification.type === 'endorsement') return '/school?view=applications'
    if (notification.type === 'hours') return '/ojt-hours'
    return '/school?view=dashboard'
  }

  if (role === 'company') {
    if (notification.type === 'hours') return '/ojt-hours'
    if (notification.type === 'application') return '/dashboard?view=applications'
    return '/dashboard?view=internships'
  }

  if (role === 'admin') {
    if (notification.type === 'hours') return '/ojt-hours'
    if (notification.type === 'registration') return '/admin/overview'
    return '/admin/reports'
  }

  return '/notifications'
}

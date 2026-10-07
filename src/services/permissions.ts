import { useAuthStore } from '@/stores/auth'

const ALIASES: Record<string, string[]> = {
  'org.view_agreements': ['manage_contracts', 'org.manage_contracts', 'org.manage_agreements', 'org.view_contracts'],
  'org.manage_agreements': ['manage_contracts', 'org.manage_contracts', 'org.manage_agreements'],
  'org.approve_agreements': ['manage_contracts', 'org.manage_contracts', 'org.manage_agreements', 'org.approve_agreements'],
  'org.view_applications': ['review_applications', 'org.review_applications', 'org.approve_applications'],
  'org.approve_applications': ['review_applications', 'org.review_applications', 'org.approve_applications'],
  'org.manage_internships': ['manage_internships', 'org.manage_internships'],
  'org.view_reports': ['view_reports', 'org.view_reports', 'org.reports_view'],
  'org.reports_view': ['view_reports', 'org.view_reports', 'org.reports_view'],
  'org.reports_export': ['view_reports', 'org.view_reports', 'org.reports_export'],
  'org.view_students': ['manage_users', 'org.manage_members', 'org.view_students'],
  'org.view_ojt_progress': ['view_reports', 'org.view_reports', 'org.view_ojt_progress', 'org.view_students'],
  'org.approve_ojt_logs': ['review_applications', 'org.review_applications', 'org.approve_ojt_logs'],
  'org.manage_assessments': ['review_applications', 'org.review_applications', 'org.manage_assessments'],
  'org.approve_certificates': ['view_reports', 'org.view_reports', 'org.approve_certificates'],
  'org.manage_roles': ['manage_roles', 'manage_permissions', 'org.manage_roles'],
}

function membershipPermissions(): string[] {
  const auth = useAuthStore()
  if (auth.user?.role === 'admin') {
    return ['*']
  }
  return auth.user?.memberships?.[0]?.permissions ?? []
}

export function hasPermission(permission: string): boolean {
  const perms = membershipPermissions()
  if (perms.includes('*')) return true
  if (perms.includes(permission)) return true
  return (ALIASES[permission] ?? []).some((alias) => perms.includes(alias))
}

export function hasAnyPermission(permissions: string[]): boolean {
  return permissions.some((permission) => hasPermission(permission))
}

export function permissionDisplayLabel(key: string): string {
  return key
    .replace(/^org\./, '')
    .replace(/^platform\./, 'platform ')
    .replace(/contracts/g, 'agreements')
    .replace(/_/g, ' ')
}

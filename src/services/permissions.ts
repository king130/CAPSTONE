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
  'org.reports_export': ['org.reports_export'],
  'org.view_students': ['manage_users', 'org.manage_members', 'org.view_students'],
  'org.view_ojt_progress': ['view_reports', 'org.view_reports', 'org.view_ojt_progress', 'org.view_students'],
  'org.approve_ojt_logs': ['review_applications', 'org.review_applications', 'org.approve_ojt_logs'],
  'org.manage_assessments': ['review_applications', 'org.review_applications', 'org.manage_assessments'],
  'org.approve_certificates': ['org.approve_certificates'],
  'org.manage_students': ['manage_users', 'org.manage_members', 'org.manage_students'],
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

const DISPLAY_LABELS: Record<string, string> = {
  'org.view_reports': 'View reports',
  'org.reports_view': 'View reports',
  view_reports: 'View reports',
  'org.reports_export': 'Export reports',
  'org.view_internships': 'View internships',
  'org.view_agreements': 'View agreements',
  'org.view_applications': 'View applications',
  'org.view_students': 'View students',
  'org.view_ojt_progress': 'View OJT progress',
  'org.manage_members': 'Manage members',
  'org.manage_roles': 'Manage roles',
  'org.manage_subscription': 'Manage subscription',
  'org.manage_profile': 'Manage profile',
  'org.manage_internships': 'Manage internships',
  'org.manage_agreements': 'Manage agreements',
  'org.manage_applications': 'Manage applications',
  'org.manage_students': 'Manage students',
  'org.manage_assessments': 'Manage assessments',
  'org.approve_applications': 'Approve applications',
  'org.approve_agreements': 'Approve agreements',
  'org.approve_ojt_logs': 'Approve OJT logs',
  'org.approve_certificates': 'Approve certificates',
  upload_files: 'Upload files',
}

export function permissionDisplayLabel(key: string): string {
  if (DISPLAY_LABELS[key]) return DISPLAY_LABELS[key]

  return key
    .replace(/^org\./, '')
    .replace(/^platform\./, 'platform ')
    .replace(/contracts/g, 'agreements')
    .replace(/_/g, ' ')
}

export function permissionAliasGroup(key: string, aliases?: string[] | null): string[] {
  if (aliases && aliases.length > 0) {
    return Array.from(new Set(aliases))
  }

  const fromGate = ALIASES[key]
  if (fromGate && fromGate.length > 0) {
    return Array.from(new Set([key, ...fromGate]))
  }

  return [key]
}

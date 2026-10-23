import type { RouteLocationRaw } from 'vue-router'

import type { UserProfile } from '@/services/auth'

export type LayoutRole = 'admin' | 'student' | 'school' | 'company'
export type LayoutIconName =
  | 'layout-dashboard'
  | 'users'
  | 'school'
  | 'building'
  | 'file-text'
  | 'settings'
  | 'user'
  | 'briefcase'
  | 'activity'
  | 'clock'
  | 'file-text'

export interface LayoutNavItem {
  key: string
  label: string
  icon: LayoutIconName
  to?: RouteLocationRaw
  /** Any one of these assigned membership keys shows the item. Cross-feature aliases are not applied. */
  permissions?: string[]
}

const reportKeys = ['org.view_reports', 'org.reports_view', 'view_reports']
const studentViewKeys = ['org.view_students']
const studentManageKeys = ['org.manage_students', 'manage_users', 'org.manage_members']
const applicationKeys = ['org.view_applications', 'org.manage_applications', 'org.approve_applications', 'review_applications', 'org.review_applications']
const internshipKeys = ['org.view_internships', 'org.manage_internships', 'manage_internships']
const agreementKeys = ['org.view_agreements', 'org.manage_agreements', 'org.approve_agreements', 'manage_contracts', 'org.manage_contracts']
const ojtKeys = ['org.view_ojt_progress', 'org.approve_ojt_logs']
const dtrUploadKeys = ['org.approve_ojt_logs', 'org.manage_internships', 'manage_internships', 'org.manage_applications', 'review_applications']
const dtrReviewKeys = ['org.approve_ojt_logs']
const roleKeys = ['org.manage_roles', 'manage_roles', 'manage_permissions']
const subscriptionKeys = ['org.manage_subscription', 'manage_subscription']

export const defaultNavItems: Record<LayoutRole, LayoutNavItem[]> = {
  admin: [
    { key: 'admin-overview', label: 'Dashboard', icon: 'layout-dashboard', to: { name: 'admin-overview' } },
    { key: 'admin-manage-interns', label: 'Manage Interns', icon: 'users', to: { name: 'admin-manage-interns' } },
    { key: 'admin-manage-schools', label: 'Manage Schools', icon: 'school', to: { name: 'admin-manage-schools' } },
    { key: 'admin-manage-companies', label: 'Manage Companies', icon: 'building', to: { name: 'admin-manage-companies' } },
    { key: 'tenant-role-management', label: 'Tenant Roles', icon: 'users', to: { name: 'tenant-role-management' } },
    { key: 'tenant-permission-assignment', label: 'Permissions', icon: 'settings', to: { name: 'tenant-permission-assignment' } },
    { key: 'ojt-hours', label: 'OJT Hours', icon: 'clock', to: { name: 'ojt-hours' } },
    { key: 'admin-reports', label: 'Reports', icon: 'file-text', to: { name: 'admin-reports' } },
    { key: 'admin-subscriptions', label: 'Subscription Plans', icon: 'settings', to: { name: 'admin-subscriptions' } },
    {
      key: 'admin-organization-subscriptions',
      label: 'Org Subscriptions',
      icon: 'briefcase',
      to: { name: 'admin-organization-subscriptions' },
    },
    {
      key: 'admin-holidays',
      label: 'Public Holidays',
      icon: 'activity',
      to: { name: 'admin-holidays' },
    },
    { key: 'admin-settings', label: 'Settings', icon: 'settings', to: { name: 'admin-settings' } },
  ],
  student: [
    { key: 'settings', label: 'My Profile', icon: 'user', to: { name: 'intern' } },
    { key: 'opportunities', label: 'Companies', icon: 'briefcase', to: { name: 'intern-opportunities' } },
    { key: 'saved', label: 'Saved', icon: 'briefcase', to: { name: 'intern-saved' } },
    { key: 'documents', label: 'Documents', icon: 'file-text', to: { name: 'intern-documents' } },
    { key: 'internship', label: 'My Applications', icon: 'briefcase', to: { name: 'intern-applications' } },
    { key: 'placement', label: 'Placement', icon: 'layout-dashboard', to: { name: 'intern-placement' } },
    { key: 'ojt-hours', label: 'OJT Hours', icon: 'clock', to: { name: 'ojt-hours' } },
    { key: 'dashboard', label: 'Status Tracker', icon: 'activity', to: { name: 'intern-tracker' } },
    { key: 'ojt-calendar', label: 'OJT Calendar', icon: 'activity', to: { name: 'intern-tracker' } },
  ],
  school: [
    { key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', to: { name: 'school' }, permissions: [...reportKeys, ...applicationKeys, ...studentViewKeys, ...internshipKeys] },
    { key: 'student-accounts', label: 'Student Accounts', icon: 'users', to: { name: 'school-students' }, permissions: studentManageKeys },
    { key: 'tenant-role-management', label: 'Role Management', icon: 'users', to: { name: 'tenant-role-management' }, permissions: roleKeys },
    { key: 'tenant-permission-assignment', label: 'Permissions', icon: 'settings', to: { name: 'tenant-permission-assignment' }, permissions: roleKeys },
    { key: 'student-interns', label: 'Interns List', icon: 'users', to: { name: 'school-interns' }, permissions: studentViewKeys },
    { key: 'opportunities', label: 'Browse', icon: 'briefcase', to: { name: 'school-opportunities' } },
    { key: 'applications', label: 'Endorsements', icon: 'file-text', to: { name: 'school-endorsements' }, permissions: applicationKeys },
    { key: 'placements', label: 'Placements', icon: 'briefcase', to: { name: 'school-placements' }, permissions: [...applicationKeys, ...studentViewKeys] },
    { key: 'reports', label: 'Reports', icon: 'file-text', to: { name: 'school-reports' }, permissions: reportKeys },
    { key: 'subscription', label: 'Subscription', icon: 'settings', to: { name: 'organization-subscription' }, permissions: subscriptionKeys },
    { key: 'agreements', label: 'Agreements', icon: 'file-text', to: { name: 'agreements' }, permissions: agreementKeys },
    { key: 'ojt-hours', label: 'OJT Hours', icon: 'clock', to: { name: 'ojt-hours' }, permissions: ojtKeys },
    { key: 'dtr-review', label: 'DTR Review', icon: 'file-text', to: { name: 'school-dtr' }, permissions: dtrReviewKeys },
  ],
  company: [
    { key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', to: { name: 'dashboard' }, permissions: [...internshipKeys, ...applicationKeys] },
    { key: 'opportunities', label: 'Browse', icon: 'briefcase', to: { name: 'company-opportunities' } },
    { key: 'internships', label: 'Posted Jobs', icon: 'briefcase', to: { name: 'company-internships' }, permissions: internshipKeys },
    { key: 'applications', label: 'Applicants', icon: 'users', to: { name: 'company-applicants' }, permissions: applicationKeys },
    { key: 'tenant-role-management', label: 'Role Management', icon: 'users', to: { name: 'tenant-role-management' }, permissions: roleKeys },
    { key: 'tenant-permission-assignment', label: 'Permissions', icon: 'settings', to: { name: 'tenant-permission-assignment' }, permissions: roleKeys },
    { key: 'subscription', label: 'Subscription', icon: 'settings', to: { name: 'organization-subscription' }, permissions: subscriptionKeys },
    { key: 'agreements', label: 'Agreements', icon: 'file-text', to: { name: 'agreements' }, permissions: agreementKeys },
    { key: 'ojt-hours', label: 'OJT Hours', icon: 'clock', to: { name: 'ojt-hours' }, permissions: ojtKeys },
    { key: 'dtr-submissions', label: 'DTR Submissions', icon: 'file-text', to: { name: 'company-dtr' }, permissions: dtrUploadKeys },
  ],
}

const navPaths: Record<string, string> = {
  school: '/school',
  'school-students': '/school/students',
  'tenant-role-management': '/access/roles',
  'tenant-permission-assignment': '/access/permissions',
  'school-interns': '/school/interns',
  'school-opportunities': '/school/opportunities',
  'school-endorsements': '/school/endorsements',
  'school-placements': '/school/placements',
  'school-reports': '/school/reports',
  'organization-subscription': '/billing',
  agreements: '/agreements',
  'ojt-hours': '/ojt-hours',
  'school-dtr': '/school/dtr',
  'company-dtr': '/dashboard/dtr',
  dashboard: '/dashboard',
  'company-opportunities': '/dashboard/schools',
  'company-internships': '/dashboard/jobs',
  'company-applicants': '/dashboard/applicants',
}

export function assignedPermissionAllows(assigned: string[], required: string[]): boolean {
  if (assigned.includes('*')) return true
  return required.some((key) => assigned.includes(key))
}

export function visibleNavItems(role: LayoutRole, assigned: string[]): LayoutNavItem[] {
  const items = defaultNavItems[role]
  if (role === 'admin' || role === 'student') return items
  return items.filter((item) => !item.permissions?.length || assignedPermissionAllows(assigned, item.permissions))
}

export function firstAllowedOrgPath(role: 'school' | 'company', assigned: string[]): string {
  const match = visibleNavItems(role, assigned)
    .map((item) => {
      const name = item.to && typeof item.to === 'object' && 'name' in item.to ? String(item.to.name) : ''
      return navPaths[name] ?? null
    })
    .find((path): path is string => Boolean(path))
  return match ?? '/settings'
}

export function routePermissionRequirements(routeName: string): string[] | null {
  for (const role of ['school', 'company'] as const) {
    for (const item of defaultNavItems[role]) {
      const name = item.to && typeof item.to === 'object' && 'name' in item.to ? String(item.to.name) : ''
      if (name === routeName && item.permissions?.length) return item.permissions
    }
  }
  if (routeName === 'agreements-new' || routeName === 'agreement-types-manage') return agreementKeys
  return null
}

/**
 * Limited chrome for school/company accounts awaiting (or rejected from) org verification.
 * Logout remains available via MainLayout account menu.
 */
export const pendingOrganizationNavItems: LayoutNavItem[] = [
  { key: 'verification', label: 'Verification', icon: 'clock', to: { name: 'organization-verification' } },
  { key: 'notifications', label: 'Notifications', icon: 'file-text', to: { name: 'notifications' } },
  { key: 'settings', label: 'Settings', icon: 'settings', to: { name: 'settings' } },
]

export function isPendingOrRejectedOrganization(user: UserProfile | null | undefined): boolean {
  if (!user) return false
  if (user.role !== 'school' && user.role !== 'company') return false
  const status = String(user.verificationStatus || 'pending').toLowerCase()
  return status === 'pending' || status === 'under_review' || status === 'rejected'
}

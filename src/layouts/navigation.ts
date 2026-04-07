import type { RouteLocationRaw } from 'vue-router'

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
}

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
    { key: 'admin-settings', label: 'Settings', icon: 'settings', to: { name: 'admin-settings' } },
  ],
  student: [
    { key: 'settings', label: 'My Profile', icon: 'user', to: { name: 'intern' } },
    { key: 'opportunities', label: 'Opportunities', icon: 'briefcase', to: { name: 'intern-opportunities' } },
    { key: 'documents', label: 'Documents', icon: 'file-text', to: { name: 'intern-documents' } },
    { key: 'internship', label: 'My Applications', icon: 'briefcase', to: { name: 'intern-applications' } },
    { key: 'placement', label: 'Placement', icon: 'layout-dashboard', to: { name: 'intern-placement' } },
    { key: 'ojt-hours', label: 'OJT Hours', icon: 'clock', to: { name: 'ojt-hours' } },
    { key: 'dashboard', label: 'Status Tracker', icon: 'activity', to: { name: 'intern-tracker' } },
  ],
  school: [
    { key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', to: { name: 'school' } },
    { key: 'student-accounts', label: 'Student Accounts', icon: 'users', to: { name: 'school-students' } },
    { key: 'tenant-role-management', label: 'Role Management', icon: 'users', to: { name: 'tenant-role-management' } },
    { key: 'tenant-permission-assignment', label: 'Permissions', icon: 'settings', to: { name: 'tenant-permission-assignment' } },
    { key: 'student-interns', label: 'Interns List', icon: 'users', to: { name: 'school-interns' } },
    { key: 'opportunities', label: 'Opportunities', icon: 'briefcase', to: { name: 'school-opportunities' } },
    { key: 'applications', label: 'Endorsements', icon: 'file-text', to: { name: 'school-endorsements' } },
    { key: 'placements', label: 'Placements', icon: 'briefcase', to: { name: 'school-placements' } },
    { key: 'reports', label: 'Reports', icon: 'file-text', to: { name: 'school-reports' } },
    { key: 'subscription', label: 'Subscription', icon: 'settings', to: { name: 'organization-subscription' } },
    { key: 'contracts', label: 'Contracts', icon: 'file-text', to: { name: 'contracts' } },
    { key: 'ojt-hours', label: 'OJT Hours', icon: 'clock', to: { name: 'ojt-hours' } },
  ],
  company: [
    { key: 'dashboard', label: 'Dashboard', icon: 'layout-dashboard', to: { name: 'dashboard' } },
    { key: 'internships', label: 'Posted Jobs', icon: 'briefcase', to: { name: 'company-internships' } },
    { key: 'applications', label: 'Applicants', icon: 'users', to: { name: 'company-applicants' } },
    { key: 'tenant-role-management', label: 'Role Management', icon: 'users', to: { name: 'tenant-role-management' } },
    { key: 'tenant-permission-assignment', label: 'Permissions', icon: 'settings', to: { name: 'tenant-permission-assignment' } },
    { key: 'subscription', label: 'Subscription', icon: 'settings', to: { name: 'organization-subscription' } },
    { key: 'contracts', label: 'Contracts', icon: 'file-text', to: { name: 'contracts' } },
    { key: 'ojt-hours', label: 'OJT Hours', icon: 'clock', to: { name: 'ojt-hours' } },
  ],
}

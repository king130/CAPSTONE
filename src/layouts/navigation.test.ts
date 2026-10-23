import { describe, expect, it } from 'vitest'
import { firstAllowedOrgPath, routePermissionRequirements, visibleNavItems } from './navigation'

describe('organization nav permissions', () => {
  it('hides school pages whose permissions are off', () => {
    const labels = visibleNavItems('school', ['org.view_students', 'org.view_ojt_progress']).map((item) => item.label)

    expect(labels).toContain('Interns List')
    expect(labels).toContain('OJT Hours')
    expect(labels).not.toContain('DTR Review')
    expect(labels).not.toContain('Student Accounts')
    expect(labels).not.toContain('Role Management')
    expect(labels).not.toContain('Subscription')
    expect(labels).not.toContain('Agreements')
    expect(labels).not.toContain('Reports')
  })

  it('shows DTR review only when the school user can approve OJT logs', () => {
    const labels = visibleNavItems('school', ['org.approve_ojt_logs']).map((item) => item.label)
    expect(labels).toContain('DTR Review')
    expect(labels).toContain('OJT Hours')
  })

  it('does not treat view reports as OJT access', () => {
    const labels = visibleNavItems('school', ['org.view_reports']).map((item) => item.label)

    expect(labels).toContain('Reports')
    expect(labels).not.toContain('OJT Hours')
  })

  it('keeps the full school menu for a school admin permission set', () => {
    const assigned = [
      'org.manage_roles',
      'org.manage_subscription',
      'org.view_internships',
      'org.view_agreements',
      'org.view_applications',
      'org.view_students',
      'org.manage_students',
      'org.view_ojt_progress',
      'org.view_reports',
    ]
    expect(visibleNavItems('school', assigned)).toHaveLength(12)
  })

  it('sends a limited subuser to the first page they can open', () => {
    expect(firstAllowedOrgPath('company', ['org.view_applications'])).toBe('/dashboard')
    expect(firstAllowedOrgPath('school', [])).toBe('/school/opportunities')
    expect(routePermissionRequirements('school-students')).toContain('org.manage_students')
    expect(routePermissionRequirements('settings')).toBeNull()
  })
})

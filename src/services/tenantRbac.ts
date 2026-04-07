import { apiFetch } from './http'

export interface TenantSummary {
  id: string
  name: string
  type: 'company' | 'school'
  memberCount: number
  roleCount: number
}

export interface TenantPermission {
  id: string
  key: string
  description?: string | null
}

export interface TenantRole {
  id: string
  tenantId: string | null
  name: string
  slug: string
  description?: string | null
  memberCount: number
  permissions: string[]
}

export interface TenantRbacPayload {
  tenant: TenantSummary
  roles: TenantRole[]
  members: TenantMember[]
  permissions: TenantPermission[]
  canManageAllTenants: boolean
}

export interface TenantMember {
  id: string
  status: 'active' | 'inactive' | 'pending'
  title?: string | null
  user: {
    id: string
    name: string
    email: string
    legacyRole?: string | null
    isActive?: boolean
  }
  role: {
    id: string
    name: string
    slug: string
    permissions: string[]
  }
}

export async function listManageableTenants(): Promise<TenantSummary[]> {
  const response = await apiFetch<{ tenants: TenantSummary[] }>('/rbac/tenants')
  return response.tenants ?? []
}

export async function createManagedAccount(payload: {
  type: 'company' | 'school'
  name: string
  adminName: string
  email: string
  plan?: string
  billingCycle?: string
}): Promise<{ account: TenantSummary; temporaryPassword: string }> {
  return apiFetch<{ account: TenantSummary; temporaryPassword: string }>('/rbac/tenants', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export async function getTenantRbac(tenantId: string): Promise<TenantRbacPayload> {
  return apiFetch<TenantRbacPayload>(`/rbac/tenants/${tenantId}/roles`)
}

export async function createTenantRole(
  tenantId: string,
  payload: { name: string; slug?: string; description?: string },
): Promise<TenantRole> {
  const response = await apiFetch<{ role: TenantRole }>(`/rbac/tenants/${tenantId}/roles`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  return response.role
}

export async function deleteTenantRole(tenantId: string, roleId: string): Promise<void> {
  await apiFetch(`/rbac/tenants/${tenantId}/roles/${roleId}`, {
    method: 'DELETE',
  })
}

export async function syncTenantRolePermissions(
  tenantId: string,
  roleId: string,
  permissions: string[],
): Promise<TenantRole> {
  const response = await apiFetch<{ role: TenantRole }>(`/rbac/tenants/${tenantId}/roles/${roleId}/permissions`, {
    method: 'PUT',
    body: JSON.stringify({ permissions }),
  })

  return response.role
}

export async function updateTenantMemberRole(
  tenantId: string,
  membershipId: string,
  roleId: string,
): Promise<TenantMember> {
  const response = await apiFetch<{ member: TenantMember }>(`/rbac/tenants/${tenantId}/members/${membershipId}`, {
    method: 'PATCH',
    body: JSON.stringify({ roleId: Number(roleId) }),
  })

  return response.member
}

export async function createTenantMember(
  tenantId: string,
  payload: {
    name: string
    email: string
    roleId: string
    title?: string
  },
): Promise<{ member: TenantMember; temporaryPassword: string }> {
  return apiFetch<{ member: TenantMember; temporaryPassword: string }>(`/rbac/tenants/${tenantId}/members`, {
    method: 'POST',
    body: JSON.stringify({
      name: payload.name,
      email: payload.email,
      roleId: Number(payload.roleId),
      title: payload.title,
    }),
  })
}

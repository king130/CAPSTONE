import { apiFetch } from './http'

export interface OrganizationAccessRole {
  id: string
  name: string
  slug: string
  scope: 'organization'
  organizationType: 'school' | 'company'
  permissions: string[]
}

export interface OrganizationAccessMember {
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
  role: OrganizationAccessRole
  permissionsOverride: {
    grant: string[]
    deny: string[]
  }
  effectivePermissions: string[]
}

export interface OrganizationAccessResponse {
  organization: {
    id: string
    name: string
    type: 'school' | 'company'
  }
  roles: OrganizationAccessRole[]
  members: OrganizationAccessMember[]
}

export async function getOrganizationAccess(): Promise<OrganizationAccessResponse> {
  return apiFetch<OrganizationAccessResponse>('/organization/access')
}

export async function updateOrganizationMember(
  membershipId: string,
  payload: {
    roleId?: string
    title?: string | null
    status?: 'active' | 'inactive' | 'pending'
    grantPermissions?: string[]
    denyPermissions?: string[]
  },
): Promise<OrganizationAccessMember> {
  const res = await apiFetch<{ member: OrganizationAccessMember }>(`/organization/access/members/${membershipId}`, {
    method: 'PATCH',
    body: JSON.stringify({
      roleId: payload.roleId ? Number(payload.roleId) : undefined,
      title: payload.title,
      status: payload.status,
      grantPermissions: payload.grantPermissions,
      denyPermissions: payload.denyPermissions,
    }),
  })
  return res.member
}

export async function createOrganizationMember(payload: {
  name: string
  email: string
  roleId: string
  title?: string | null
}): Promise<{
  member: OrganizationAccessMember
  inviteSent: boolean
  email: string
  message?: string
  invite?: { sent: boolean; expiresAt?: string; errorMessage?: string }
}> {
  return apiFetch<{
    member: OrganizationAccessMember
    inviteSent: boolean
    email: string
    message?: string
    invite?: { sent: boolean; expiresAt?: string; errorMessage?: string }
  }>('/organization/access/members', {
    method: 'POST',
    body: JSON.stringify({
      name: payload.name,
      email: payload.email,
      roleId: Number(payload.roleId),
      title: payload.title,
    }),
  })
}

export async function resendOrganizationMemberSetupLink(
  membershipId: string,
): Promise<{ inviteSent: boolean; email?: string; message?: string; invite?: { sent: boolean; errorMessage?: string } }> {
  return apiFetch<{
    inviteSent: boolean
    email?: string
    message?: string
    invite?: { sent: boolean; errorMessage?: string }
  }>(`/organization/access/members/${membershipId}/resend-setup-link`, {
    method: 'POST',
  })
}

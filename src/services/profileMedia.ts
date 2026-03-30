import { apiBase, apiFetch } from './http'
import { mapApiUserToProfile, type UserProfile } from './auth'

export async function uploadProfileAvatar(file: File): Promise<UserProfile> {
  const body = new FormData()
  body.append('avatar', file)

  const response = await apiFetch<{ user: Record<string, unknown> }>('/profile/avatar', {
    method: 'POST',
    body,
  })

  return mapApiUserToProfile(response.user)
}

export function buildProfileAvatarUrl(userId: string, updatedAt?: unknown): string {
  const base = apiBase()
  const path = `/profile/avatar/${userId}`
  const url = base ? `${base}${path}` : `/api${path}`
  const version = typeof updatedAt === 'string' && updatedAt ? updatedAt : Date.now().toString()
  return `${url}?v=${encodeURIComponent(version)}`
}

import { apiFetch } from './http'

export interface PublicProfile {
  uid: string
  displayName: string
  role: 'school' | 'company' | 'student'
  orgName?: string
  email?: string
  courses?: string[]
}

/** Kept for compatibility; directory data lives on users in MySQL. */
export async function ensurePublicProfile(
  _uid: string,
  _data: {
    displayName: string
    role: string
    orgName?: string
    email?: string
    courses?: string[]
  }
): Promise<void> {
  // No separate public profile document — school/company rows expose directory data via API.
}

export async function getPublicProfile(uid: string): Promise<PublicProfile | null> {
  for (const role of ['company', 'school'] as const) {
    const list = await listPublicProfiles(role).catch(() => [])
    const hit = list.find((p) => p.uid === uid)
    if (hit) return hit
  }
  return null
}

export async function listPublicProfiles(role: 'school' | 'company'): Promise<PublicProfile[]> {
  const res = await apiFetch<{ data: PublicProfile[] }>(`/directory/${role}`)
  return res.data ?? []
}

export function subscribePublicProfiles(
  role: 'school' | 'company',
  callback: (profiles: PublicProfile[]) => void
): () => void {
  let cancelled = false
  async function load() {
    try {
      const list = await listPublicProfiles(role)
      if (!cancelled) callback(list)
    } catch {
      if (!cancelled) callback([])
    }
  }
  load()
  const id = window.setInterval(load, 20000)
  return () => {
    cancelled = true
    clearInterval(id)
  }
}

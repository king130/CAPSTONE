import { apiFetch } from '@/services/http'

export type SkillStatus = 'approved' | 'pending_review' | 'rejected'
export type SkillLevel = 'beginner' | 'intermediate' | 'advanced'
export type SkillImportance = 'required' | 'nice_to_have'

export interface CatalogSkill {
  id: string
  name: string
  category: string
  status: SkillStatus
  createdByUserId?: string | null
  mergedIntoSkillId?: string | null
}

export interface SelectedSkill extends CatalogSkill {
  level?: SkillLevel
  importance?: SkillImportance
}

export interface SkillsCatalogResponse {
  data: CatalogSkill[]
  categories: string[]
  recommendedCategories?: string[]
  limits: { student: number; job: number }
}

export async function fetchSkillsCatalog(params?: { q?: string; category?: string }) {
  const search = new URLSearchParams()
  if (params?.q) search.set('q', params.q)
  if (params?.category) search.set('category', params.category)
  const qs = search.toString()
  return apiFetch<SkillsCatalogResponse>(`/skills${qs ? `?${qs}` : ''}`)
}

export async function suggestSkills(name: string) {
  const qs = new URLSearchParams({ name }).toString()
  return apiFetch<{ data: CatalogSkill[] }>(`/skills/suggest?${qs}`)
}

export async function createCustomSkill(payload: {
  name: string
  category?: string
  force?: boolean
}): Promise<{
  data?: CatalogSkill
  message?: string
  suggestion?: CatalogSkill
  suggestions?: CatalogSkill[]
  code?: string
  conflict?: boolean
}> {
  const { getToken, apiBase } = await import('@/services/http')
  const base = apiBase()
  const url = base ? `${base}/skills` : '/api/skills'
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const res = await fetch(url, {
    method: 'POST',
    headers,
    body: JSON.stringify(payload),
  })
  const json = (await res.json().catch(() => ({}))) as {
    data?: CatalogSkill
    message?: string
    suggestion?: CatalogSkill
    suggestions?: CatalogSkill[]
    code?: string
  }

  if (res.status === 409) {
    return { ...json, conflict: true }
  }
  if (!res.ok) {
    throw new Error(json.message || 'Unable to add custom skill.')
  }
  return json
}

export async function fetchStudentSkills() {
  return apiFetch<{ data: SelectedSkill[]; limit: number }>('/student/skills')
}

export async function saveStudentSkills(skills: Array<{ id: string; level?: SkillLevel }>) {
  return apiFetch<{ data: SelectedSkill[]; message?: string; limit: number }>('/student/skills', {
    method: 'PUT',
    body: JSON.stringify({ skills }),
  })
}

export async function fetchPendingSkills() {
  return apiFetch<{
    data: Array<
      CatalogSkill & {
        createdByName?: string
        createdByEmail?: string
        createdAt?: string
        studentCount?: number
        jobCount?: number
      }
    >
  }>('/admin/skills/pending')
}

export async function fetchAdminSkills(params?: { q?: string; status?: string }) {
  const search = new URLSearchParams()
  if (params?.q) search.set('q', params.q)
  if (params?.status) search.set('status', params.status)
  const qs = search.toString()
  return apiFetch<{ data: CatalogSkill[] }>(`/admin/skills${qs ? `?${qs}` : ''}`)
}

export async function approveSkill(id: string) {
  return apiFetch<{ data: CatalogSkill; message?: string }>(`/admin/skills/${id}/approve`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
}

export async function mergeSkill(id: string, targetSkillId: string) {
  return apiFetch<{ data: CatalogSkill; message?: string }>(`/admin/skills/${id}/merge`, {
    method: 'POST',
    body: JSON.stringify({ target_skill_id: Number(targetSkillId) }),
  })
}

export async function rejectSkill(id: string) {
  return apiFetch<{ data: CatalogSkill; message?: string }>(`/admin/skills/${id}/reject`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
}

export function skillLevelLabel(level?: string | null) {
  const value = String(level || 'beginner').toLowerCase()
  if (value === 'advanced') return 'Advanced'
  if (value === 'intermediate') return 'Intermediate'
  return 'Beginner'
}

export function skillImportanceLabel(importance?: string | null) {
  return String(importance || 'required') === 'nice_to_have' ? 'Nice to have' : 'Required'
}

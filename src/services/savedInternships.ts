import { apiFetch } from './http'
import { normalizeInternshipRecord, type InternshipRecord } from './internships'

export async function listSavedInternships(params?: {
  page?: number
  per_page?: number
}): Promise<{ data: InternshipRecord[]; total: number }> {
  const search = new URLSearchParams()
  if (params?.page) search.set('page', String(params.page))
  if (params?.per_page) search.set('per_page', String(params.per_page))
  const qs = search.toString()
  const res = await apiFetch<{ data: unknown[]; meta?: { total?: number } }>(
    `/saved-internships${qs ? `?${qs}` : '?page=1'}`,
  )
  return {
    data: (res.data ?? []).map(normalizeInternshipRecord),
    total: Number(res.meta?.total ?? res.data?.length ?? 0),
  }
}

export async function saveInternship(internshipId: string | number): Promise<InternshipRecord> {
  const res = await apiFetch<{ data: unknown }>('/saved-internships', {
    method: 'POST',
    body: JSON.stringify({ internship_id: Number(internshipId) }),
  })
  return normalizeInternshipRecord(res.data)
}

export async function unsaveInternship(internshipId: string | number): Promise<void> {
  await apiFetch<void>(`/saved-internships/${internshipId}`, {
    method: 'DELETE',
  })
}

export async function toggleSavedInternship(
  internshipId: string | number,
  currentlySaved: boolean,
): Promise<boolean> {
  if (currentlySaved) {
    await unsaveInternship(internshipId)
    return false
  }
  await saveInternship(internshipId)
  return true
}

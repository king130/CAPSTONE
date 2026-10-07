import { apiFetch } from './http'

export interface OrganizationMediaItem {
  id: string
  organizationId: string
  path: string
  url: string
  caption: string | null
  sortOrder: number
  isCover: boolean
  createdAt?: string | null
  updatedAt?: string | null
}

export interface OrganizationMediaUpdatePayload {
  caption?: string | null
  sortOrder?: number
  isCover?: boolean
}

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {}
}

function asString(value: unknown, fallback = ''): string {
  return typeof value === 'string' ? value : fallback
}

function asNumber(value: unknown, fallback = 0): number {
  const n = Number(value)
  return Number.isFinite(n) ? n : fallback
}

function mapMediaItem(raw: unknown): OrganizationMediaItem {
  const row = asRecord(raw)
  return {
    id: asString(row.id),
    organizationId: asString(row.organizationId ?? row.organization_id),
    path: asString(row.path),
    url: asString(row.url),
    caption: row.caption == null ? null : asString(row.caption),
    sortOrder: asNumber(row.sortOrder ?? row.sort_order, 0),
    isCover: Boolean(row.isCover ?? row.is_cover),
    createdAt: row.createdAt == null && row.created_at == null ? null : asString(row.createdAt ?? row.created_at),
    updatedAt: row.updatedAt == null && row.updated_at == null ? null : asString(row.updatedAt ?? row.updated_at),
  }
}

export async function listOrganizationMedia(organizationId: string): Promise<OrganizationMediaItem[]> {
  const res = await apiFetch<{ data: unknown[] }>(`/organizations/${organizationId}/media`)
  return (res.data ?? []).map(mapMediaItem).sort((a, b) => a.sortOrder - b.sortOrder || a.id.localeCompare(b.id))
}

export async function uploadOrganizationMedia(
  file: File,
  options?: { caption?: string; isCover?: boolean; sortOrder?: number },
): Promise<OrganizationMediaItem> {
  const body = new FormData()
  body.append('file', file)
  if (options?.caption != null && options.caption !== '') {
    body.append('caption', options.caption)
  }
  if (options?.isCover != null) {
    body.append('is_cover', options.isCover ? '1' : '0')
  }
  if (options?.sortOrder != null) {
    body.append('sort_order', String(options.sortOrder))
  }

  const res = await apiFetch<{ data: unknown }>('/organization-media', {
    method: 'POST',
    body,
  })

  return mapMediaItem(res.data)
}

export async function updateOrganizationMedia(
  mediaId: string,
  payload: OrganizationMediaUpdatePayload,
): Promise<OrganizationMediaItem> {
  const body: Record<string, unknown> = {}
  if (payload.caption !== undefined) body.caption = payload.caption
  if (payload.sortOrder !== undefined) body.sort_order = payload.sortOrder
  if (payload.isCover !== undefined) body.is_cover = payload.isCover

  const res = await apiFetch<{ data: unknown }>(`/organization-media/${mediaId}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  })

  return mapMediaItem(res.data)
}

export async function deleteOrganizationMedia(mediaId: string): Promise<void> {
  await apiFetch<void>(`/organization-media/${mediaId}`, {
    method: 'DELETE',
  })
}

export const ORGANIZATION_PHOTO_MAX_BYTES = 3 * 1024 * 1024
export const ORGANIZATION_PHOTO_ACCEPT = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'

export function validateOrganizationPhotoFile(file: File): string | null {
  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp']
  const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
  const allowedExt = ['jpg', 'jpeg', 'png', 'webp']

  if (!allowedTypes.includes(file.type) && !allowedExt.includes(ext)) {
    return 'Only JPG, PNG, and WEBP images are allowed.'
  }
  if (file.size > ORGANIZATION_PHOTO_MAX_BYTES) {
    return 'Images must be 3 MB or smaller.'
  }
  return null
}

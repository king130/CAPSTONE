import { ApiError, apiBase, getToken } from '@/services/http'
import { needsAuthenticatedFetch } from '@/utils/filePreview'

export type FetchedFileBlob = {
  blob: Blob
  contentType: string
}

function buildApiUrl(apiPath: string): string {
  const base = apiBase()
  let path = apiPath.trim()
  if (path.startsWith('/api/')) path = path.slice(4)
  else if (path === '/api') path = '/'
  if (!path.startsWith('/')) path = `/${path}`
  return base ? `${base}${path}` : `/api${path}`
}

/** Fetch a private API file with Bearer auth. Never puts the token in the URL. */
export async function fetchApiFileBlob(apiPath: string): Promise<FetchedFileBlob> {
  const headers: HeadersInit = { Accept: '*/*' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(buildApiUrl(apiPath), { headers })
  if (response.status === 403 || response.status === 404) {
    throw new ApiError(
      response.status === 403 ? 'You do not have access to this file.' : 'File not found.',
      response.status,
    )
  }
  if (!response.ok) {
    throw new ApiError('Could not load this file.', response.status)
  }

  const blob = await response.blob()
  const contentType =
    response.headers.get('Content-Type') || blob.type || 'application/octet-stream'
  return { blob, contentType }
}

/**
 * Load a file for preview/download.
 * - `/api/...` and other private API paths: Bearer fetch → blob
 * - `/storage/...` and absolute public URLs: return null blob + use source URL directly
 *   (avoids CORS issues when the SPA is on Vite and storage is on :8000)
 */
export async function resolvePreviewSource(source: string): Promise<{
  objectUrl: string | null
  directUrl: string | null
  contentType: string
  revoke: () => void
}> {
  const value = String(source || '').trim()
  if (!value) {
    throw new ApiError('No file source provided.', 400)
  }

  if (needsAuthenticatedFetch(value)) {
    const path = value.replace(/^https?:\/\/[^/]+/i, '')
    const { blob, contentType } = await fetchApiFileBlob(path)
    const objectUrl = URL.createObjectURL(blob)
    return {
      objectUrl,
      directUrl: null,
      contentType: contentType || blob.type || '',
      revoke: () => URL.revokeObjectURL(objectUrl),
    }
  }

  return {
    objectUrl: null,
    directUrl: value,
    contentType: '',
    revoke: () => undefined,
  }
}

export async function downloadBlob(blob: Blob, fileName: string): Promise<void> {
  const objectUrl = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = objectUrl
  anchor.download = fileName || 'download'
  anchor.click()
  URL.revokeObjectURL(objectUrl)
}

export async function downloadFromSource(source: string, fileName: string): Promise<void> {
  if (needsAuthenticatedFetch(source)) {
    const path = source.replace(/^https?:\/\/[^/]+/i, '')
    const { blob } = await fetchApiFileBlob(path)
    await downloadBlob(blob, fileName)
    return
  }
  const anchor = document.createElement('a')
  anchor.href = source
  anchor.download = fileName || 'download'
  anchor.target = '_blank'
  anchor.rel = 'noopener noreferrer'
  anchor.click()
}

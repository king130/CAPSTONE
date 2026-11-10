/** MIME / extension helpers for in-app file preview (PDF + common images only). */

export type PreviewKind = 'pdf' | 'image' | 'unsupported'

const IMAGE_MIME = new Set(['image/jpeg', 'image/png', 'image/webp'])
const IMAGE_EXT = new Set(['jpg', 'jpeg', 'png', 'webp'])
const PDF_EXT = new Set(['pdf'])

export function extensionOf(fileName: string): string {
  const base = String(fileName || '').trim().split(/[\\/]/).pop() || ''
  const dot = base.lastIndexOf('.')
  if (dot < 0) return ''
  return base.slice(dot + 1).toLowerCase()
}

export function normalizeMimeType(raw: string | null | undefined): string {
  const value = String(raw || '')
    .trim()
    .toLowerCase()
    .split(';')[0]
    ?.trim()
  return value || ''
}

/** Prefer response Content-Type; fall back to file extension. Never treat svg/html as previewable. */
export function resolvePreviewKind(options: {
  contentType?: string | null
  fileName?: string | null
}): PreviewKind {
  const mime = normalizeMimeType(options.contentType)
  if (mime === 'application/pdf') return 'pdf'
  if (IMAGE_MIME.has(mime)) return 'image'
  if (mime === 'image/svg+xml' || mime === 'text/html' || mime === 'application/xhtml+xml') {
    return 'unsupported'
  }

  const ext = extensionOf(options.fileName || '')
  if (PDF_EXT.has(ext)) return 'pdf'
  if (IMAGE_EXT.has(ext)) return 'image'
  return 'unsupported'
}

export function isInlinePreviewableMime(mime: string | null | undefined): boolean {
  return resolvePreviewKind({ contentType: mime }) !== 'unsupported'
}

export function formatFileSize(bytes?: number | null): string {
  if (bytes == null || !Number.isFinite(bytes) || bytes < 0) return ''
  if (bytes < 1024) return `${Math.round(bytes)} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(bytes < 10 * 1024 ? 1 : 0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(bytes < 10 * 1024 * 1024 ? 1 : 0)} MB`
}

/** True when the source must be fetched with the Sanctum Bearer token (never put token in a URL). */
export function needsAuthenticatedFetch(source: string): boolean {
  const value = String(source || '').trim()
  if (!value) return false
  if (/^https?:\/\//i.test(value)) {
    try {
      const url = new URL(value)
      return url.pathname.startsWith('/api/') || url.pathname === '/api'
    } catch {
      return false
    }
  }
  if (value.startsWith('/storage/')) return false
  if (value.startsWith('/api/') || value === '/api') return true
  if (value.startsWith('/')) return true
  return true
}

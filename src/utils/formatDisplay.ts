/** Shared display helpers — wording only; do not use for API payloads. */

/** e.g. "Oct 23, 2026" */
export function formatDate(value?: string | number | Date | null): string {
  if (value == null || value === '') return '—'
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

/** Title-case person names for display only. */
export function toTitleCaseName(value: string | null | undefined): string {
  const raw = String(value || '').trim()
  if (!raw) return ''
  return raw
    .toLowerCase()
    .split(/\s+/)
    .map((part) => (part ? part[0]!.toUpperCase() + part.slice(1) : part))
    .join(' ')
}

/** Slug-ish field key from a question label (agreement type builder). */
export function slugifyFieldKey(label: string): string {
  return String(label || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 64)
}

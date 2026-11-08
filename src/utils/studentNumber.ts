/** Client-side mirror of App\Support\StudentNumber rules. */

const MIN_LENGTH = 3
const MAX_LENGTH = 32
const PATTERN = /^[A-Za-z0-9][A-Za-z0-9/._-]{2,31}$/

export function normalizeStudentNumber(value: string | null | undefined): string {
  if (value == null) return ''
  return String(value).trim().replace(/\s+/g, '')
}

export function studentNumberErrorMessage(value: string | null | undefined, required = true): string | null {
  const normalized = normalizeStudentNumber(value)
  if (!normalized) {
    return required ? 'Student number is required.' : null
  }
  if (normalized.length < MIN_LENGTH || normalized.length > MAX_LENGTH) {
    return 'Student number must be between 3 and 32 characters (letters, numbers, - / . _).'
  }
  if (!PATTERN.test(normalized)) {
    return 'Student number may only use letters, numbers, hyphens, slashes, periods, and underscores (no spaces).'
  }
  return null
}

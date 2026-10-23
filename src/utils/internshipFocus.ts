/**
 * Normalize the Intern opportunities `?focus=` query value.
 * Callers pass an internship id (not an application id).
 */
export function resolveInternshipFocusId(focus: unknown): string | null {
  if (typeof focus === 'string') {
    const trimmed = focus.trim()
    return trimmed || null
  }

  if (Array.isArray(focus)) {
    const first = focus.find((entry) => typeof entry === 'string' && entry.trim())
    return typeof first === 'string' ? first.trim() : null
  }

  return null
}

export function findInternshipIdForFocus<T extends { id: string | number }>(
  items: readonly T[],
  focus: unknown,
): string | null {
  const focusId = resolveInternshipFocusId(focus)
  if (!focusId) return null

  const match = items.find((item) => String(item.id) === focusId)
  return match ? String(match.id) : null
}

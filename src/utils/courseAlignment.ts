/** Course alignment helpers — keep in sync with App\Support\CourseAlignment (PHP). */

export type CourseAlignmentStatus = 'aligned' | 'none' | 'unspecified'

export function displayCourseName(course: string): string {
  return course.trim().replace(/\s+/g, ' ')
}

export function courseAlignmentKey(course: string): string {
  return displayCourseName(course).toLowerCase()
}

/** Unique display names (first-seen casing), keyed by lowercase collapsed name. */
export function normalizeCourseList(courses: unknown): string[] {
  if (!Array.isArray(courses)) return []
  const unique = new Map<string, string>()
  for (const course of courses) {
    if (typeof course !== 'string') continue
    const display = displayCourseName(course)
    if (!display) continue
    const key = courseAlignmentKey(display)
    if (!unique.has(key)) unique.set(key, display)
  }
  return [...unique.values()]
}

/** Shared courses using the viewer's display names for matches. */
export function sharedCourses(viewerCourses: unknown, otherCourses: unknown): string[] {
  const viewer = normalizeCourseList(viewerCourses)
  const other = normalizeCourseList(otherCourses)
  if (!viewer.length || !other.length) return []
  const otherKeys = new Set(other.map((course) => courseAlignmentKey(course)))
  return viewer.filter((course) => otherKeys.has(courseAlignmentKey(course)))
}

export function resolveCourseAlignment(
  viewerCourses: unknown,
  otherCourses: unknown,
): CourseAlignmentStatus {
  const other = normalizeCourseList(otherCourses)
  if (!other.length) return 'unspecified'
  return sharedCourses(viewerCourses, other).length > 0 ? 'aligned' : 'none'
}

/** Aligned-mode include rule: aligned OR unspecified OR partnered. */
export function includeWhenAlignedFilter(
  alignment: CourseAlignmentStatus,
  isPartnered: boolean,
): boolean {
  if (isPartnered) return true
  return alignment === 'aligned' || alignment === 'unspecified'
}

export function formatSharedCoursesBadge(courses: string[], maxVisible = 2): string {
  const list = normalizeCourseList(courses)
  if (!list.length) return 'Shared: —'
  const visible = list.slice(0, maxVisible)
  const rest = list.length - visible.length
  return rest > 0 ? `Shared: ${visible.join(', ')} +${rest}` : `Shared: ${visible.join(', ')}`
}

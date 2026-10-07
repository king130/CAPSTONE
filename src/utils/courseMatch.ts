import { courseGroups, openToAllCoursesLabel } from '@/config/courseCatalog'

export type CourseMatchLevel = 'strong' | 'related' | 'weak' | 'open' | 'unknown'

export interface CourseMatchResult {
  level: CourseMatchLevel
  reason: string
  matchedCourse?: string
}

/**
 * Abbreviation / alternate form → catalog canonical name.
 * Keys must be produced by `normalizeCourseKey`.
 * Keep this map small and evidence-based; extend as real data appears.
 */
const COURSE_ALIASES: Record<string, string> = {
  bsit: 'BS Information Technology',
  'bs information technology': 'BS Information Technology',
  bscs: 'BS Computer Science',
  'bs computer science': 'BS Computer Science',
  // Present alongside IT/CS in ContractTypeSeeder program options.
  bsis: 'BS Information Systems',
  'bs information systems': 'BS Information Systems',
}

/** Closely related programs — Information Technology catalog group only. */
const RELATED_COURSE_GROUPS: readonly (readonly string[])[] = (() => {
  const itGroup = courseGroups.find((group) => group.label === 'Information Technology')
  return itGroup ? [itGroup.options] : []
})()

const OPEN_COURSE_KEY = normalizeCourseKey(openToAllCoursesLabel)

function buildCanonicalLookup(): Map<string, string> {
  const map = new Map<string, string>()

  for (const [alias, canonical] of Object.entries(COURSE_ALIASES)) {
    map.set(alias, canonical)
  }

  for (const group of courseGroups) {
    for (const option of group.options) {
      map.set(normalizeCourseKey(option), option)
    }
  }

  return map
}

const CANONICAL_LOOKUP = buildCanonicalLookup()

const RELATED_IDENTITY_SETS: ReadonlySet<string>[] = RELATED_COURSE_GROUPS.map(
  (group) => new Set(group.map((course) => courseIdentity(course))),
)

/** Trim, collapse internal whitespace, lowercase — comparison key only. */
export function normalizeCourseKey(value: string): string {
  return value.trim().replace(/\s+/g, ' ').toLowerCase()
}

function collapseWhitespace(value: string): string {
  return value.trim().replace(/\s+/g, ' ')
}

/**
 * Resolve a course label to a catalog canonical name when known;
 * otherwise return the whitespace-collapsed original (for exact free-text ties).
 * Returns null for empty / open-to-all tokens.
 */
export function canonicalizeCourse(raw: string | null | undefined): string | null {
  if (raw == null) return null
  const collapsed = collapseWhitespace(raw)
  if (!collapsed) return null

  const key = normalizeCourseKey(collapsed)
  if (key === OPEN_COURSE_KEY) return null

  return CANONICAL_LOOKUP.get(key) ?? collapsed
}

function courseIdentity(raw: string): string {
  const canonical = canonicalizeCourse(raw)
  if (canonical == null) return ''
  return normalizeCourseKey(canonical)
}

function areRelated(studentIdentity: string, eligibleIdentity: string): boolean {
  if (!studentIdentity || !eligibleIdentity || studentIdentity === eligibleIdentity) {
    return false
  }

  return RELATED_IDENTITY_SETS.some(
    (group) => group.has(studentIdentity) && group.has(eligibleIdentity),
  )
}

function normalizeEligibleList(eligibleCourses: string[] | null | undefined): string[] {
  if (eligibleCourses == null) return []

  return eligibleCourses
    .filter((course): course is string => typeof course === 'string')
    .map((course) => collapseWhitespace(course))
    .filter((course) => course.length > 0 && normalizeCourseKey(course) !== OPEN_COURSE_KEY)
}

/**
 * Soft course/program match between a student and an internship's eligible courses.
 * Does not determine apply eligibility — hard gates remain school / MOA / active status.
 */
export function matchCourseProgram(
  studentCourse: string | null | undefined,
  eligibleCourses: string[] | null | undefined,
): CourseMatchResult {
  const eligible = normalizeEligibleList(eligibleCourses)

  if (eligible.length === 0) {
    return {
      level: 'open',
      reason: 'Internship has no course restrictions',
    }
  }

  const studentCollapsed =
    studentCourse == null ? '' : collapseWhitespace(String(studentCourse))

  if (!studentCollapsed) {
    return {
      level: 'unknown',
      reason: 'Student course is missing',
    }
  }

  const studentIdentity = courseIdentity(studentCollapsed)

  for (const eligibleCourse of eligible) {
    const eligibleIdentity = courseIdentity(eligibleCourse)
    if (studentIdentity && studentIdentity === eligibleIdentity) {
      const matchedCourse = canonicalizeCourse(eligibleCourse) ?? eligibleCourse
      return {
        level: 'strong',
        reason: 'Student course matches an eligible course',
        matchedCourse,
      }
    }
  }

  for (const eligibleCourse of eligible) {
    const eligibleIdentity = courseIdentity(eligibleCourse)
    if (areRelated(studentIdentity, eligibleIdentity)) {
      const matchedCourse = canonicalizeCourse(eligibleCourse) ?? eligibleCourse
      return {
        level: 'related',
        reason: 'Student course is closely related to an eligible course',
        matchedCourse,
      }
    }
  }

  return {
    level: 'weak',
    reason: 'Student course does not match internship eligible courses',
  }
}

/** Soft display order only — lower numbers appear first. Does not affect eligibility. */
export const COURSE_MATCH_SORT_PRIORITY: Record<CourseMatchLevel, number> = {
  strong: 0,
  related: 1,
  open: 2,
  weak: 3,
  unknown: 4,
}

/**
 * Stable soft sort by course match level. Ties keep the original relative order.
 */
export function stableSortByCourseMatchLevel<T>(
  items: readonly T[],
  getLevel: (item: T) => CourseMatchLevel,
): T[] {
  return items
    .map((item, index) => ({
      item,
      index,
      priority: COURSE_MATCH_SORT_PRIORITY[getLevel(item)] ?? COURSE_MATCH_SORT_PRIORITY.unknown,
    }))
    .sort((a, b) => a.priority - b.priority || a.index - b.index)
    .map(({ item }) => item)
}

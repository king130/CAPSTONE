/** Must match SkillCatalogSeeder / CourseSkillCategoryDefaults::catalogCategories(). */
export const SKILL_CATALOG_CATEGORIES = [
  'IT & Software',
  'Business',
  'Engineering',
  'Education',
  'Hospitality',
  'Healthcare',
  'Communication',
  'Creative & Design',
  'Agriculture',
] as const

export type SkillCatalogCategory = (typeof SKILL_CATALOG_CATEGORIES)[number]

function displayCourseName(courseName: string): string {
  return courseName.replace(/\s+/g, ' ').trim()
}

function matchesAny(haystack: string, needles: string[]): boolean {
  return needles.some((needle) => needle !== '' && haystack.includes(needle))
}

function matchPrimaryCategory(lower: string): SkillCatalogCategory | null {
  if (
    matchesAny(lower, [
      'information technology',
      'computer science',
      'software engineering',
      'software development',
      'data science',
      'cyber security',
      'cybersecurity',
      'information systems',
      'computer engineering',
      'bsit',
      'bscs',
      'bs cs',
      'bs it',
      ' bsit',
      ' bscs',
    ]) ||
    /\b(it|ict)\b/u.test(lower)
  ) {
    return 'IT & Software'
  }

  if (
    matchesAny(lower, [
      'business administration',
      'business management',
      'accountancy',
      'accounting',
      'marketing',
      'entrepreneurship',
      'finance',
      'economics',
      'management',
      'bsba',
      'bsa',
      'bba',
    ]) ||
    lower.includes('business')
  ) {
    return 'Business'
  }

  if (
    matchesAny(lower, [
      'engineering',
      'civil engineering',
      'mechanical engineering',
      'electrical engineering',
      'electronics engineering',
      'industrial engineering',
      'computer engineering',
      'bsce',
      'bsme',
      'bsee',
    ])
  ) {
    return 'Engineering'
  }

  if (
    matchesAny(lower, [
      'education',
      'teaching',
      'elementary education',
      'secondary education',
      'beed',
      'bsed',
    ])
  ) {
    return 'Education'
  }

  if (
    matchesAny(lower, [
      'hospitality',
      'hotel',
      'restaurant',
      'tourism',
      'culinary',
      'hrm',
      'human resource',
    ])
  ) {
    return 'Hospitality'
  }

  if (
    matchesAny(lower, [
      'nursing',
      'medical',
      'health',
      'pharmacy',
      'midwifery',
      'radiologic',
      'physical therapy',
      'bsn',
    ])
  ) {
    return 'Healthcare'
  }

  if (
    matchesAny(lower, [
      'agriculture',
      'agribusiness',
      'forestry',
      'animal science',
      'crop science',
    ])
  ) {
    return 'Agriculture'
  }

  if (
    matchesAny(lower, [
      'multimedia',
      'graphic design',
      'fine arts',
      'visual communication',
      'animation',
      'film',
      'creative',
      'design',
    ])
  ) {
    return 'Creative & Design'
  }

  return null
}

export function recommendedSkillCategoriesForCourse(courseName: string): string[] {
  const display = displayCourseName(courseName)
  if (!display) return []

  const primary = matchPrimaryCategory(display.toLowerCase())
  if (!primary) return []

  const out: string[] = [primary]
  if (primary !== 'Communication') {
    out.push('Communication')
  }
  return out
}

export function recommendedSkillCategoriesForCourses(courseNames: string[]): string[] {
  const seen = new Set<string>()
  const merged: string[] = []

  for (const raw of courseNames) {
    for (const category of recommendedSkillCategoriesForCourse(raw)) {
      if (seen.has(category)) continue
      seen.add(category)
      merged.push(category)
    }
  }

  return merged
}

export function normalizeSkillCategoryList(categories: string[] | null | undefined): string[] {
  if (!categories?.length) return []

  const allowed = new Set<string>(SKILL_CATALOG_CATEGORIES)
  const seen = new Set<string>()
  const out: string[] = []

  for (const raw of categories) {
    const name = String(raw || '').trim()
    if (!name || !allowed.has(name) || seen.has(name)) continue
    seen.add(name)
    out.push(name)
  }

  return out
}

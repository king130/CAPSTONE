import { apiFetch } from '@/services/http'

export const ALL_YEAR_LEVEL_OPTIONS = [
  '1st Year',
  '2nd Year',
  '3rd Year',
  '4th Year',
  '5th Year',
] as const

export const DEFAULT_ELIGIBLE_YEAR_LEVELS = ['3rd Year', '4th Year'] as const

export interface CourseRequirement {
  id: string
  schoolId: string
  courseName: string
  requiredHours: number
  eligibleYearLevels: string[]
  configComplete?: boolean
}

export interface CourseRequirementMeta {
  allowedYearLevels: string[]
  defaultEligibleYearLevels: string[]
  incompleteCourses: Array<{
    id: string
    courseName: string
    requiredHours: number
    eligibleYearLevels: string[]
    missingHours: boolean
    missingYearLevels: boolean
  }>
  flaggedStudentCount: number
  hasSetupWarnings: boolean
}

export interface FlaggedStudent {
  id: string
  userId: string
  name?: string | null
  email?: string | null
  course?: string | null
  yearLevel?: string | null
  eligibleYearLevels: string[]
  reasons: string[]
}

interface CourseRequirementResponse {
  data: CourseRequirement
}

interface CourseRequirementListResponse {
  data: CourseRequirement[]
  meta?: CourseRequirementMeta
}

interface FlaggedStudentListResponse {
  data: FlaggedStudent[]
}

export async function listCourseRequirements(): Promise<{
  requirements: CourseRequirement[]
  meta: CourseRequirementMeta | null
}> {
  const response = await apiFetch<CourseRequirementListResponse>('/school-course-requirements')
  return {
    requirements: (response.data ?? []).map(normalizeRequirement),
    meta: response.meta ?? null,
  }
}

export async function listFlaggedCourseStudents(): Promise<FlaggedStudent[]> {
  const response = await apiFetch<FlaggedStudentListResponse>('/school-course-requirements/flagged-students')
  return response.data ?? []
}

export async function createCourseRequirement(
  courseName: string,
  requiredHours: number,
  eligibleYearLevels: string[] = [...DEFAULT_ELIGIBLE_YEAR_LEVELS],
): Promise<CourseRequirement> {
  const response = await apiFetch<CourseRequirementResponse>('/school-course-requirements', {
    method: 'POST',
    body: JSON.stringify({ courseName, requiredHours, eligibleYearLevels }),
  })
  return normalizeRequirement(response.data)
}

export async function updateCourseRequirement(
  id: string,
  courseName: string,
  requiredHours: number,
  eligibleYearLevels: string[],
): Promise<CourseRequirement> {
  const response = await apiFetch<CourseRequirementResponse>(`/school-course-requirements/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ courseName, requiredHours, eligibleYearLevels }),
  })
  return normalizeRequirement(response.data)
}

function normalizeRequirement(requirement: CourseRequirement): CourseRequirement {
  const levels = Array.isArray(requirement.eligibleYearLevels)
    ? requirement.eligibleYearLevels
    : [...DEFAULT_ELIGIBLE_YEAR_LEVELS]

  return {
    ...requirement,
    eligibleYearLevels: levels,
    configComplete:
      requirement.configComplete ??
      (Number(requirement.requiredHours) >= 1 && levels.length > 0),
  }
}

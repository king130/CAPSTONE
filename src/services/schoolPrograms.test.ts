import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiFetch = vi.fn()

vi.mock('@/services/http', () => ({
  apiFetch: (...args: unknown[]) => apiFetch(...args),
}))

import {
  createCourseRequirement,
  listCourseRequirements,
  updateCourseRequirement,
} from '@/services/schoolPrograms'

describe('schoolPrograms API', () => {
  beforeEach(() => {
    apiFetch.mockReset()
  })

  it('loads course requirements from the school endpoint', async () => {
    apiFetch.mockResolvedValue({
      data: [
        {
          id: '1',
          schoolId: '4',
          courseName: 'BS Information Technology',
          requiredHours: 500,
          eligibleYearLevels: ['3rd Year', '4th Year'],
        },
      ],
      meta: {
        allowedYearLevels: ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'],
        defaultEligibleYearLevels: ['3rd Year', '4th Year'],
        incompleteCourses: [],
        flaggedStudentCount: 0,
        hasSetupWarnings: false,
      },
    })

    await expect(listCourseRequirements()).resolves.toMatchObject({
      requirements: [
        {
          id: '1',
          schoolId: '4',
          courseName: 'BS Information Technology',
          requiredHours: 500,
          eligibleYearLevels: ['3rd Year', '4th Year'],
        },
      ],
      meta: { flaggedStudentCount: 0 },
    })
    expect(apiFetch).toHaveBeenCalledWith('/school-course-requirements')
  })

  it('creates a course requirement with year levels', async () => {
    apiFetch.mockResolvedValue({
      data: {
        id: '2',
        schoolId: '4',
        courseName: 'BS Computer Science',
        requiredHours: 600,
        eligibleYearLevels: ['3rd Year', '4th Year'],
      },
    })

    await expect(createCourseRequirement('BS Computer Science', 600)).resolves.toMatchObject({
      courseName: 'BS Computer Science',
      requiredHours: 600,
      eligibleYearLevels: ['3rd Year', '4th Year'],
    })
    expect(apiFetch).toHaveBeenCalledWith('/school-course-requirements', {
      method: 'POST',
      body: JSON.stringify({
        courseName: 'BS Computer Science',
        requiredHours: 600,
        eligibleYearLevels: ['3rd Year', '4th Year'],
      }),
    })
  })

  it('updates course hours and year levels', async () => {
    apiFetch.mockResolvedValue({
      data: {
        id: '2',
        schoolId: '4',
        courseName: 'BS Computer Science',
        requiredHours: 486,
        eligibleYearLevels: ['2nd Year', '3rd Year'],
      },
    })

    await updateCourseRequirement('2', 'BS Computer Science', 486, ['2nd Year', '3rd Year'])
    expect(apiFetch).toHaveBeenCalledWith('/school-course-requirements/2', {
      method: 'PATCH',
      body: JSON.stringify({
        courseName: 'BS Computer Science',
        requiredHours: 486,
        eligibleYearLevels: ['2nd Year', '3rd Year'],
      }),
    })
  })

  it('surfaces API errors instead of returning empty data', async () => {
    apiFetch.mockRejectedValue(new Error('This school already has a required-hours setting for that course.'))

    await expect(createCourseRequirement('BS Information Technology', 500)).rejects.toThrow(
      'This school already has a required-hours setting for that course.',
    )
    await expect(listCourseRequirements()).rejects.toThrow(
      'This school already has a required-hours setting for that course.',
    )
  })
})

import { describe, expect, it } from 'vitest'
import {
  matchCourseProgram,
  stableSortByCourseMatchLevel,
  type CourseMatchLevel,
} from './courseMatch'

/** Mirrors the legacy Intern.vue substring badge (for contrast only). */
function legacySubstringMatch(
  studentCourse: string | null | undefined,
  eligibleCourses: string[] | null | undefined,
): boolean {
  const course = (studentCourse ?? '').trim().toLowerCase()
  const eligible = eligibleCourses ?? []
  return (
    !course ||
    eligible.length === 0 ||
    eligible.some((item) => item.toLowerCase().includes(course))
  )
}

describe('matchCourseProgram', () => {
  it('BSIT vs BS Information Technology → strong', () => {
    const result = matchCourseProgram('BSIT', ['BS Information Technology'])
    expect(result.level).toBe('strong')
    expect(result.matchedCourse).toBe('BS Information Technology')
  })

  it('BSCS vs BS Computer Science → strong', () => {
    const result = matchCourseProgram('BSCS', ['BS Computer Science'])
    expect(result.level).toBe('strong')
    expect(result.matchedCourse).toBe('BS Computer Science')
  })

  it('same normalized course with different casing/spacing → strong', () => {
    const result = matchCourseProgram('  bs   information   technology ', [
      'BS Information Technology',
    ])
    expect(result.level).toBe('strong')
    expect(result.matchedCourse).toBe('BS Information Technology')
  })

  it('known closely related IT/CS case → related', () => {
    const result = matchCourseProgram('BSIT', ['BS Computer Science'])
    expect(result.level).toBe('related')
    expect(result.matchedCourse).toBe('BS Computer Science')
  })

  it('unrelated course → weak', () => {
    const result = matchCourseProgram('BS Human Resource Management', [
      'BS Information Technology',
    ])
    expect(result.level).toBe('weak')
    expect(result.matchedCourse).toBeUndefined()
  })

  it('empty eligible_courses → open', () => {
    expect(matchCourseProgram('BSIT', []).level).toBe('open')
  })

  it('null eligible_courses → open', () => {
    expect(matchCourseProgram('BSIT', null).level).toBe('open')
  })

  it('missing student course + restricted internship → unknown', () => {
    expect(matchCourseProgram(null, ['BS Information Technology']).level).toBe('unknown')
    expect(matchCourseProgram('', ['BS Computer Science']).level).toBe('unknown')
    expect(matchCourseProgram('   ', ['BSIT']).level).toBe('unknown')
  })

  it('no false strong match from substring behavior', () => {
    // Legacy badge treats partial tokens as a match; helper must not.
    const studentPartial = 'Information'
    const eligible = ['BS Information Technology']

    expect(legacySubstringMatch(studentPartial, eligible)).toBe(true)
    expect(matchCourseProgram(studentPartial, eligible).level).toBe('weak')

    // Reverse direction that substring would also over-accept.
    expect(legacySubstringMatch('Technology', eligible)).toBe(true)
    expect(matchCourseProgram('Technology', eligible).level).toBe('weak')
  })

  it('open-to-all catalog label is treated as unrestricted', () => {
    expect(matchCourseProgram('BSIT', ['Any / Open to all courses']).level).toBe('open')
  })
})

describe('stableSortByCourseMatchLevel', () => {
  it('orders strong → related → open → weak → unknown and keeps stable ties', () => {
    const input: Array<{ id: string; level: CourseMatchLevel }> = [
      { id: 'A', level: 'weak' },
      { id: 'B', level: 'strong' },
      { id: 'C', level: 'related' },
      { id: 'D', level: 'open' },
      { id: 'E', level: 'unknown' },
      { id: 'F', level: 'strong' },
    ]

    const sorted = stableSortByCourseMatchLevel(input, (item) => item.level)

    expect(sorted.map((item) => item.id)).toEqual(['B', 'F', 'C', 'D', 'A', 'E'])
  })

  it('preserves original order when all levels match', () => {
    const input: Array<{ id: string; level: CourseMatchLevel }> = [
      { id: 'x', level: 'related' },
      { id: 'y', level: 'related' },
      { id: 'z', level: 'related' },
    ]

    expect(stableSortByCourseMatchLevel(input, (item) => item.level).map((item) => item.id)).toEqual([
      'x',
      'y',
      'z',
    ])
  })

  it('handles empty and single-item lists', () => {
    expect(stableSortByCourseMatchLevel([], () => 'strong' as CourseMatchLevel)).toEqual([])
    expect(
      stableSortByCourseMatchLevel([{ id: 'only', level: 'weak' as CourseMatchLevel }], (item) => item.level),
    ).toEqual([{ id: 'only', level: 'weak' }])
  })
})

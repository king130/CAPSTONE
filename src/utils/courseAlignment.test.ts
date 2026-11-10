import { describe, expect, it } from 'vitest'

import {
  courseAlignmentKey,
  formatSharedCoursesBadge,
  includeWhenAlignedFilter,
  normalizeCourseList,
  resolveCourseAlignment,
  sharedCourses,
} from './courseAlignment'

describe('courseAlignment', () => {
  it('normalizes trim, case, and collapsed whitespace', () => {
    expect(normalizeCourseList(['  BS  Hotel  Management ', 'bs hotel management', ''])).toEqual([
      'BS Hotel Management',
    ])
    expect(courseAlignmentKey('  BSIT ')).toBe('bsit')
  })

  it('computes shared courses from the viewer display names', () => {
    expect(sharedCourses(['BSHM', 'BSIT'], ['bsit', 'BSA'])).toEqual(['BSIT'])
  })

  it('resolves aligned / none / unspecified', () => {
    expect(resolveCourseAlignment(['BSHM'], ['BSHM'])).toBe('aligned')
    expect(resolveCourseAlignment(['BSHM'], ['BSIT'])).toBe('none')
    expect(resolveCourseAlignment(['BSHM'], [])).toBe('unspecified')
    // Viewer empty + other specified → none (unspecified only when the other org has no courses).
    expect(resolveCourseAlignment([], ['BSIT'])).toBe('none')
  })

  it('includes aligned, unspecified, and partnered in aligned mode', () => {
    expect(includeWhenAlignedFilter('aligned', false)).toBe(true)
    expect(includeWhenAlignedFilter('unspecified', false)).toBe(true)
    expect(includeWhenAlignedFilter('none', true)).toBe(true)
    expect(includeWhenAlignedFilter('none', false)).toBe(false)
  })

  it('formats shared course badges', () => {
    expect(formatSharedCoursesBadge(['A', 'B', 'C'])).toBe('Shared: A, B +1')
    expect(formatSharedCoursesBadge(['BSHM'])).toBe('Shared: BSHM')
  })
})

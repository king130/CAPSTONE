import { describe, expect, it } from 'vitest'

import { findInternshipIdForFocus, resolveInternshipFocusId } from '@/utils/internshipFocus'

describe('internshipFocus', () => {
  it('resolves a valid string focus id', () => {
    expect(resolveInternshipFocusId('42')).toBe('42')
    expect(resolveInternshipFocusId('  intern-9  ')).toBe('intern-9')
  })

  it('treats missing or blank focus as absent', () => {
    expect(resolveInternshipFocusId(undefined)).toBeNull()
    expect(resolveInternshipFocusId(null)).toBeNull()
    expect(resolveInternshipFocusId('')).toBeNull()
    expect(resolveInternshipFocusId('   ')).toBeNull()
    expect(resolveInternshipFocusId(123)).toBeNull()
  })

  it('uses the first non-empty string when vue-router provides an array', () => {
    expect(resolveInternshipFocusId(['', '77'])).toBe('77')
    expect(resolveInternshipFocusId([])).toBeNull()
  })

  it('finds a matching internship id after list load', () => {
    const items = [{ id: '10' }, { id: '20' }]
    expect(findInternshipIdForFocus(items, '20')).toBe('20')
    expect(findInternshipIdForFocus([{ id: 20 }], '20')).toBe('20')
  })

  it('returns null for invalid focus without throwing', () => {
    expect(findInternshipIdForFocus([{ id: '10' }], 'missing')).toBeNull()
    expect(findInternshipIdForFocus([], '10')).toBeNull()
    expect(findInternshipIdForFocus([{ id: '10' }], undefined)).toBeNull()
  })
})

import { describe, expect, it } from 'vitest'

import { parseDtrText } from '@/utils/dtrText'

describe('parseDtrText', () => {
  it('reads labeled hours and period dates', () => {
    const suggestion = parseDtrText(`
      Daily Time Record
      Period start: 2026-10-01
      Period end: 2026-10-05
      Total hours: 40
    `)

    expect(suggestion).toEqual({
      hours: 40,
      periodStart: '2026-10-01',
      periodEnd: '2026-10-05',
    })
  })

  it('sums clock times for a monthly DTR', () => {
    const suggestion = parseDtrText(`
      Daily Time Record
      For the month of October 2026
      1  8:00  12:00  1:00  5:00
      2  8:00  12:00  1:00  5:00
      3
      Total hours: 16
    `)

    expect(suggestion.hours).toBe(16)
    expect(suggestion.periodStart).toBe('2026-10-01')
    expect(suggestion.periodEnd).toBe('2026-10-02')
  })

  it('returns empty suggestions when the text has no hours or dates', () => {
    expect(parseDtrText('Cover sheet only. No time record is included.')).toEqual({
      hours: null,
      periodStart: null,
      periodEnd: null,
    })
  })
})

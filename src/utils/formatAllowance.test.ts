import { describe, expect, it } from 'vitest'

import { formatAllowanceDisplay } from './formatAllowance'

describe('formatAllowanceDisplay', () => {
  it('returns No allowance for empty values', () => {
    expect(formatAllowanceDisplay('')).toBe('No allowance')
    expect(formatAllowanceDisplay(null)).toBe('No allowance')
    expect(formatAllowanceDisplay(undefined)).toBe('No allowance')
    expect(formatAllowanceDisplay('   ')).toBe('No allowance')
  })

  it('leaves text without a number as-is', () => {
    expect(formatAllowanceDisplay('unpaid')).toBe('unpaid')
    expect(formatAllowanceDisplay('As agreed with host')).toBe('As agreed with host')
    expect(formatAllowanceDisplay('none')).toBe('none')
  })

  it('formats numbers with ₱ and keeps suffix', () => {
    expect(formatAllowanceDisplay('PHP 5000 / month')).toBe('₱5,000 / month')
    expect(formatAllowanceDisplay('5000 monthly')).toBe('₱5,000 monthly')
    expect(formatAllowanceDisplay('1500')).toBe('₱1,500')
    expect(formatAllowanceDisplay('₱1500')).toBe('₱1,500')
    expect(formatAllowanceDisplay('1,500.50 / day')).toBe('₱1,500.50 / day')
  })
})

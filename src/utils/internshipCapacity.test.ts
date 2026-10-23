import { describe, expect, it } from 'vitest'

import { internshipCapacity } from './internshipCapacity'

describe('internshipCapacity', () => {
  it('shows an empty posting as 0 percent', () => {
    const capacity = internshipCapacity({ slotsAvailable: 10, filledSlots: 0, slotsRemaining: 10 })
    expect(capacity).toMatchObject({ filled: 0, remaining: 10, percent: 0 })
  })

  it('uses the accepted-slot count for partial, near-full, and full postings', () => {
    expect(internshipCapacity({ slotsAvailable: 10, filledSlots: 5, slotsRemaining: 5 }).percent).toBe(50)
    expect(internshipCapacity({ slotsAvailable: 10, filledSlots: 7, slotsRemaining: 3 })).toMatchObject({
      filled: 7,
      remaining: 3,
      percent: 70,
    })
    expect(internshipCapacity({ slotsAvailable: 10, filledSlots: 9, slotsRemaining: 1 })).toMatchObject({
      remaining: 1,
      percent: 90,
      remainingLabel: '1 slot remaining',
    })
    expect(internshipCapacity({ slotsAvailable: 10, filledSlots: 10, slotsRemaining: 0 })).toMatchObject({
      filled: 10,
      remaining: 0,
      percent: 100,
      remainingLabel: 'No slots remaining',
    })
  })

  it('never reports a negative remainder or a percentage above 100', () => {
    const capacity = internshipCapacity({ slotsAvailable: 10, filledSlots: 14, slotsRemaining: 0 })
    expect(capacity.percent).toBe(100)
    expect(capacity.remaining).toBe(0)
    expect(capacity.filled).toBe(10)
  })

  it('does not treat a zero slot limit as unlimited', () => {
    const capacity = internshipCapacity({ slotsAvailable: 0, filledSlots: 0, slotsRemaining: 0 })
    expect(capacity).toMatchObject({
      limit: 0,
      filled: 0,
      remaining: 0,
      percent: 0,
      remainingLabel: 'No slots remaining',
    })
  })
})

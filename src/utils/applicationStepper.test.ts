import { describe, expect, it } from 'vitest'

import type { AssessmentRecord } from '@/services/assessments'
import type { InterviewRecord } from '@/services/interviews'
import type { OJTLog } from '@/services/ojtService'
import { deriveApplicationStepper } from '@/utils/applicationStepper'

function interview(status: InterviewRecord['status'], scheduledAt = '2026-10-20T06:00:00.000Z'): InterviewRecord {
  return {
    id: '1',
    applicationId: '9',
    scheduledAt,
    durationMinutes: 60,
    mode: 'online',
    status,
    createdByUserId: '2',
  }
}

function assessment(): AssessmentRecord {
  return {
    id: '3',
    applicationId: '9',
    assessedByUserId: '4',
    stage: 'final',
    rubricScores: { technical: 80 },
    overallScore: 88,
    createdAt: '2026-10-21T00:00:00.000Z',
  }
}

function log(status: OJTLog['status'], hours: number): OJTLog {
  return {
    id: 1,
    internId: 1,
    internName: 'Intern',
    date: '2026-10-01',
    timeIn: '08:00',
    timeOut: '12:00',
    hoursRendered: hours,
    tasksDone: 'Work',
    status,
    createdAt: '2026-10-01T00:00:00.000Z',
    applicationId: '9',
  }
}

function keys(status: string, extra: Parameters<typeof deriveApplicationStepper>[0] = {}) {
  return deriveApplicationStepper({ status, ...extra }).steps.map((step) => step.key)
}

describe('deriveApplicationStepper', () => {
  it('shows school review as the current step after applying', () => {
    const result = deriveApplicationStepper({ status: 'submitted' })
    expect(result.steps.find((step) => step.key === 'applied')?.complete).toBe(true)
    expect(result.steps.find((step) => step.current)?.key).toBe('school')
    expect(result.steps.find((step) => step.key === 'school')?.message).toContain('Waiting for school endorsement')
    expect(result.stopped).toBe(false)
  })

  it('stops at school rejection and hides later steps', () => {
    const result = deriveApplicationStepper({ status: 'school_rejected' })
    expect(keys('school_rejected')).toEqual(['applied', 'school'])
    expect(result.steps.find((step) => step.key === 'school')?.tone).toBe('error')
    expect(result.stopped).toBe(true)
  })

  it('waits for a company interview after endorsement', () => {
    const result = deriveApplicationStepper({ status: 'endorsed' })
    expect(result.steps.find((step) => step.current)?.key).toBe('interview')
    expect(result.steps.find((step) => step.key === 'interview')?.message).toContain('Waiting for the host organization')
  })

  it('shows a scheduled interview without treating confirmation as a result', () => {
    const scheduled = deriveApplicationStepper({ status: 'endorsed', interviews: [interview('proposed')] })
    expect(scheduled.steps.find((step) => step.current)?.key).toBe('interview')
    expect(scheduled.steps.find((step) => step.key === 'interview')?.message).toContain('Interview scheduled')

    const confirmed = deriveApplicationStepper({ status: 'endorsed', interviews: [interview('confirmed')] })
    expect(confirmed.steps.find((step) => step.key === 'interview')?.complete).toBe(true)
    // Confirmed ≠ marked complete; host still finishes interview then assesses before Accept/Reject.
    expect(confirmed.steps.find((step) => step.current)?.key).toBe('interview')
    expect(confirmed.nextAction).toMatch(/interview|assessment|Schedule/i)
  })

  it('puts assessment before host decision and unlocks accept after assessment', () => {
    const assessed = deriveApplicationStepper({
      status: 'endorsed',
      trackerStage: 'assessed',
      interviews: [interview('completed')],
      assessments: [assessment()],
      audience: 'company',
    })
    expect(assessed.steps.map((step) => step.key)).toEqual([
      'applied',
      'school',
      'interview',
      'assessment',
      'company',
      'ojt',
      'completed',
    ])
    expect(assessed.steps.find((step) => step.current)?.key).toBe('company')
    expect(assessed.nextAction).toContain('Accept or reject')
  })

  it('marks a completed interview status when the record says completed', () => {
    const result = deriveApplicationStepper({ status: 'endorsed', interviews: [interview('completed')] })
    expect(result.steps.find((step) => step.key === 'interview')?.label).toBe('Interview completed')
  })

  it('stops at company rejection', () => {
    const result = deriveApplicationStepper({
      status: 'rejected',
      interviews: [interview('confirmed')],
    })
    expect(result.steps.map((step) => step.key)).toEqual(['applied', 'school', 'interview', 'company'])
    expect(result.steps.find((step) => step.key === 'company')?.tone).toBe('error')
    expect(result.stopped).toBe(true)
  })

  it('moves to assessment after acceptance when no score exists', () => {
    const result = deriveApplicationStepper({ status: 'accepted', interviews: [interview('confirmed')] })
    expect(result.steps.find((step) => step.current)?.key).toBe('assessment')
    expect(result.steps.find((step) => step.key === 'assessment')?.message).toContain('Not started')
  })

  it('reflects an evaluated assessment without inventing a pass or fail', () => {
    const result = deriveApplicationStepper({
      status: 'accepted',
      interviews: [interview('confirmed')],
      assessments: [assessment()],
    })
    const item = result.steps.find((step) => step.key === 'assessment')
    expect(item?.complete).toBe(true)
    expect(item?.message).toContain('Evaluated')
    expect(item?.message).not.toMatch(/pass|fail/i)
    expect(result.steps.find((step) => step.current)?.key).toBe('ojt')
  })

  it('shows recorded OJT hours and DTR counts', () => {
    const result = deriveApplicationStepper({
      status: 'accepted',
      assessments: [assessment()],
      ojtLogs: [log('approved', 8), log('pending', 4)],
      requiredHours: 500,
      dtrApproved: 3,
      dtrPending: 1,
    })
    const item = result.steps.find((step) => step.key === 'ojt')
    expect(item?.message).toContain('8 / 500')
    expect(item?.message).toContain('3 approved')
    expect(item?.message).toContain('1 pending')
    expect(result.steps.find((step) => step.key === 'completed')?.complete).toBe(false)
  })

  it('marks completion only when a certificate exists', () => {
    const result = deriveApplicationStepper({
      status: 'accepted',
      assessments: [assessment()],
      ojtLogs: [log('approved', 500)],
      requiredHours: 500,
      certificateIssued: true,
    })
    expect(result.steps.find((step) => step.current)?.key).toBe('completed')
    expect(result.steps.find((step) => step.key === 'completed')?.message).toContain('certificate')
  })
})

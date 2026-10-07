import type { InterviewRecord } from '@/services/interviews'
import type { OJTLog } from '@/services/ojtService'

export type ApplicationStepperTone = 'pending' | 'active' | 'complete' | 'error' | 'warning'

export interface ApplicationStepperStep {
  key: 'submitted' | 'school_endorsed' | 'company_accepted' | 'interview' | 'ojt'
  label: string
  tone: ApplicationStepperTone
  complete: boolean
  current: boolean
  message?: string
}

export interface ApplicationStepperResult {
  steps: ApplicationStepperStep[]
  stopped: boolean
  nextAction: string
  nextActionTo?: string
}

function normalizeStatus(status?: string | null): string {
  const raw = String(status || '').trim().toLowerCase()
  if (raw === 'pending') return 'submitted'
  if (raw === 'approved' || raw === 'active') return 'accepted'
  if (raw === 'declined') return 'rejected'
  return raw
}

function pickInterview(interviews: InterviewRecord[]): InterviewRecord | null {
  if (!interviews.length) return null
  const ranked = [...interviews].sort((a, b) => {
    const rank = (status: InterviewRecord['status']) => {
      if (status === 'confirmed' || status === 'completed') return 3
      if (status === 'proposed') return 2
      if (status === 'cancelled') return 1
      return 0
    }
    return rank(b.status) - rank(a.status) || String(b.scheduledAt).localeCompare(String(a.scheduledAt))
  })
  return ranked[0] ?? null
}

export function deriveApplicationStepper(input: {
  status?: string | null
  interviews?: InterviewRecord[]
  ojtLogs?: OJTLog[]
}): ApplicationStepperResult {
  const status = normalizeStatus(input.status)
  const interview = pickInterview(input.interviews ?? [])
  const logs = input.ojtLogs ?? []
  const hasApprovedHours = logs.some((log) => log.status === 'approved' && Number(log.hoursRendered) > 0)
  const hasProgress = hasApprovedHours || logs.some((log) => Number(log.hoursRendered) > 0)

  const schoolRejected = status === 'school_rejected'
  const companyRejected = status === 'rejected'
  const endorsedOrBeyond = ['endorsed', 'accepted', 'rejected'].includes(status) || schoolRejected
  const accepted = status === 'accepted'
  const pastSchool = ['endorsed', 'accepted', 'rejected'].includes(status)

  const interviewComplete = interview?.status === 'confirmed' || interview?.status === 'completed'
  const interviewCancelled = interview?.status === 'cancelled'
  const interviewProposed = interview?.status === 'proposed'

  const steps: ApplicationStepperStep[] = [
    {
      key: 'submitted',
      label: 'Submitted',
      tone: 'complete',
      complete: true,
      current: status === 'submitted',
      message: 'Application submitted.',
    },
    {
      key: 'school_endorsed',
      label: 'School endorsed',
      tone: schoolRejected ? 'error' : pastSchool ? 'complete' : status === 'submitted' ? 'active' : 'pending',
      complete: pastSchool,
      current: status === 'submitted' || schoolRejected,
      message: schoolRejected
        ? 'Your school did not endorse this application.'
        : pastSchool
          ? 'Your school endorsed this application.'
          : 'Waiting for your school to endorse.',
    },
    {
      key: 'company_accepted',
      label: 'Company accepted',
      tone: schoolRejected
        ? 'pending'
        : companyRejected
          ? 'error'
          : accepted
            ? 'complete'
            : status === 'endorsed'
              ? 'active'
              : 'pending',
      complete: accepted,
      current: !schoolRejected && (status === 'endorsed' || companyRejected),
      message: schoolRejected
        ? 'Company review did not start.'
        : companyRejected
          ? 'The company did not accept this application.'
          : accepted
            ? 'The company accepted your application.'
            : status === 'endorsed'
              ? 'Waiting for the company decision.'
              : 'Company review comes after school endorsement.',
    },
    {
      key: 'interview',
      label: 'Interview',
      tone: schoolRejected || companyRejected
        ? 'pending'
        : interviewComplete
          ? 'complete'
          : interviewCancelled
            ? 'warning'
            : interviewProposed || accepted
              ? 'active'
              : 'pending',
      complete: interviewComplete,
      current: !schoolRejected && !companyRejected && (interviewProposed || interviewCancelled || (accepted && !interviewComplete && !hasProgress)),
      message: interviewCancelled
        ? 'Interview was cancelled. Wait for a new schedule or contact the host.'
        : interviewComplete
          ? 'Interview confirmed.'
          : interviewProposed
            ? 'Confirm your interview schedule.'
            : accepted
              ? 'Interview details will appear when scheduled.'
              : 'Interview follows company acceptance.',
    },
    {
      key: 'ojt',
      label: 'OJT',
      tone: schoolRejected || companyRejected
        ? 'pending'
        : hasApprovedHours || hasProgress
          ? 'complete'
          : accepted
            ? 'active'
            : 'pending',
      complete: hasApprovedHours || hasProgress,
      current: accepted && (hasProgress || (!interviewProposed && !interviewCancelled)),
      message: hasApprovedHours || hasProgress
        ? 'OJT hours are in progress.'
        : accepted
          ? 'Log your hours to start OJT tracking.'
          : 'OJT starts after you are accepted.',
    },
  ]

  // Keep only one "current" marker on the furthest relevant step.
  let currentKey: ApplicationStepperStep['key'] | null = null
  if (schoolRejected) currentKey = 'school_endorsed'
  else if (companyRejected) currentKey = 'company_accepted'
  else if (status === 'submitted') currentKey = 'submitted'
  else if (status === 'endorsed') currentKey = 'company_accepted'
  else if (interviewProposed || interviewCancelled) currentKey = 'interview'
  else if (accepted && !hasProgress) currentKey = interviewComplete || !interview ? 'ojt' : 'interview'
  else if (accepted && hasProgress) currentKey = 'ojt'
  else currentKey = 'submitted'

  for (const step of steps) {
    step.current = step.key === currentKey
  }

  let nextAction = 'Keep an eye on updates for this application.'
  let nextActionTo: string | undefined

  if (schoolRejected) {
    nextAction = 'This application stopped after school review. Browse other opportunities.'
    nextActionTo = '/intern/opportunities'
  } else if (companyRejected) {
    nextAction = 'This application was not selected. Browse other opportunities.'
    nextActionTo = '/intern/opportunities'
  } else if (status === 'submitted') {
    nextAction = 'Waiting for your school to endorse'
  } else if (status === 'endorsed') {
    nextAction = 'Waiting for the company to accept'
  } else if (interviewProposed) {
    nextAction = 'Confirm your interview'
  } else if (interviewCancelled) {
    nextAction = 'Interview cancelled — watch for a new schedule'
  } else if (accepted) {
    nextAction = 'Log your hours'
    nextActionTo = '/ojt-hours'
  }

  return {
    steps,
    stopped: schoolRejected || companyRejected,
    nextAction,
    nextActionTo,
  }
}

import type { AssessmentRecord } from '@/services/assessments'
import type { InterviewRecord } from '@/services/interviews'
import type { OJTLog } from '@/services/ojtService'

export type ApplicationStepperTone = 'pending' | 'active' | 'complete' | 'error' | 'warning'

export type ApplicationStepperKey =
  | 'applied'
  | 'school'
  | 'interview'
  | 'company'
  | 'assessment'
  | 'ojt'
  | 'completed'

export interface ApplicationStepperStep {
  key: ApplicationStepperKey
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

export interface ApplicationStepperInput {
  status?: string | null
  /** Preferred when present — legacy status stays "endorsed" through interview/assessment. */
  trackerStage?: string | null
  interviews?: InterviewRecord[]
  assessments?: AssessmentRecord[]
  ojtLogs?: OJTLog[]
  requiredHours?: number | null
  dtrApproved?: number | null
  dtrPending?: number | null
  certificateIssued?: boolean
  audience?: 'intern' | 'school' | 'company'
}

function normalizeStatus(status?: string | null): string {
  const raw = String(status || '').trim().toLowerCase()
  if (raw === 'pending') return 'submitted'
  if (raw === 'approved' || raw === 'active') return 'accepted'
  if (raw === 'declined') return 'rejected'
  return raw
}

function interviewRank(status: InterviewRecord['status']): number {
  if (status === 'completed') return 4
  if (status === 'confirmed') return 3
  if (status === 'proposed') return 2
  if (status === 'cancelled') return 1
  return 0
}

function pickInterview(interviews: InterviewRecord[]): InterviewRecord | null {
  if (!interviews.length) return null
  return [...interviews].sort(
    (a, b) => interviewRank(b.status) - interviewRank(a.status) || String(b.scheduledAt).localeCompare(String(a.scheduledAt)),
  )[0] ?? null
}

function formatWhen(value?: string | null): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

function stageLabel(stage?: string | null): string {
  const raw = String(stage || '').trim().toLowerCase()
  if (raw === 'pre_screen') return 'Pre-screen'
  if (raw === 'interview') return 'Interview'
  if (raw === 'final') return 'Final'
  return raw ? raw.split('_').join(' ') : 'Assessment'
}

function latestAssessment(assessments: AssessmentRecord[]): AssessmentRecord | null {
  if (!assessments.length) return null
  return [...assessments].sort((a, b) => String(b.createdAt || '').localeCompare(String(a.createdAt || '')))[0] ?? null
}

function approvedHours(logs: OJTLog[]): number {
  return logs.reduce((sum, log) => {
    if (log.status !== 'approved') return sum
    const hours = Number(log.hoursRendered)
    return sum + (Number.isFinite(hours) ? hours : 0)
  }, 0)
}

function step(
  key: ApplicationStepperKey,
  label: string,
  tone: ApplicationStepperTone,
  message: string,
): ApplicationStepperStep {
  return {
    key,
    label,
    tone,
    complete: tone === 'complete',
    current: false,
    message,
  }
}

export function deriveApplicationStepper(input: ApplicationStepperInput): ApplicationStepperResult {
  const status = normalizeStatus(input.status)
  const trackerStage = String(input.trackerStage || '').trim().toLowerCase()
  const interview = pickInterview(input.interviews ?? [])
  const assessment = latestAssessment(input.assessments ?? [])
  const logs = input.ojtLogs ?? []
  const hours = approvedHours(logs)
  const required = input.requiredHours != null && Number.isFinite(Number(input.requiredHours))
    ? Number(input.requiredHours)
    : null
  const certificateIssued = input.certificateIssued === true

  const schoolRejected = status === 'school_rejected' || trackerStage === 'school_rejected'
  const companyRejected = status === 'rejected' || trackerStage === 'rejected'
  const endorsed = ['endorsed', 'accepted', 'rejected'].includes(status)
    || ['endorsed', 'interview_scheduled', 'interviewed', 'assessed', 'accepted', 'rejected'].includes(trackerStage)
  const accepted = status === 'accepted' || trackerStage === 'accepted'
  const submitted = (status === 'submitted' || status === '') && (!trackerStage || trackerStage === 'applied')

  const interviewCompleted = interview?.status === 'completed'
    || trackerStage === 'interviewed'
    || trackerStage === 'assessed'
    || accepted
  const interviewConfirmed = interview?.status === 'confirmed' || interviewCompleted
  const interviewScheduled = interview?.status === 'proposed' || interviewConfirmed
    || trackerStage === 'interview_scheduled'
  const interviewCancelled = interview?.status === 'cancelled' && !interviewScheduled
  const when = formatWhen(interview?.scheduledAt)
  const hasAssessment = Boolean(assessment) || trackerStage === 'assessed'
  const readyForHostDecision = (trackerStage === 'assessed' || hasAssessment)
    && !accepted
    && !companyRejected

  const schoolTone: ApplicationStepperTone = schoolRejected ? 'error' : endorsed ? 'complete' : 'active'
  const interviewTone: ApplicationStepperTone = interviewCancelled
    ? 'warning'
    : interviewConfirmed
      ? 'complete'
      : interview?.status === 'proposed' || trackerStage === 'interview_scheduled'
        ? 'active'
        : status === 'endorsed' || trackerStage === 'endorsed'
          ? 'active'
          : 'pending'
  const assessmentTone: ApplicationStepperTone = hasAssessment
    ? 'complete'
    : interviewCompleted || interviewConfirmed
      ? 'active'
      : accepted
        ? 'active'
        : 'pending'
  const companyTone: ApplicationStepperTone = companyRejected
    ? 'error'
    : accepted
      ? 'complete'
      : readyForHostDecision
        ? 'active'
        : 'pending'

  const ojtMessage = (() => {
    if (!accepted && !certificateIssued) return 'OJT starts after the company accepts the application.'
    const parts: string[] = []
    if (required != null) {
      const percent = required > 0 ? Math.round((hours / required) * 100) : 0
      parts.push(`Approved hours ${hours} / ${required} (${percent}%)`)
    } else if (logs.length) {
      parts.push(`Approved hours ${hours}`)
    }
    if (input.dtrApproved != null || input.dtrPending != null) {
      const approvedCount = input.dtrApproved ?? 0
      const pendingCount = input.dtrPending ?? 0
      parts.push(`DTRs ${approvedCount} approved${pendingCount ? `, ${pendingCount} pending` : ''}`)
    }
    return parts.join('. ') || 'Log hours to start OJT tracking.'
  })()

  const steps: ApplicationStepperStep[] = [
    step('applied', 'Applied', 'complete', 'Application submitted.'),
    step(
      'school',
      schoolRejected ? 'School rejected' : 'School endorsement',
      submitted ? 'active' : schoolTone,
      schoolRejected
        ? 'Your school did not endorse this application.'
        : endorsed
          ? 'Your application was endorsed by the school.'
          : 'Waiting for school endorsement.',
    ),
  ]

  if (!schoolRejected) {
    steps.push(
      step(
        'interview',
        interviewCompleted ? 'Interview completed' : interviewConfirmed ? 'Interview confirmed' : 'Interview',
        !endorsed && !interview ? 'pending' : interviewTone,
        interviewCancelled
          ? 'Interview was cancelled. The host organization can propose a new time.'
          : interviewCompleted
            ? `Interview completed${when ? ` · ${when}` : ''}.`
            : interviewConfirmed
              ? `Interview confirmed${when ? ` · ${when}` : ''}.`
                : interview?.status === 'proposed' || trackerStage === 'interview_scheduled'
                ? `Interview scheduled${when ? ` · ${when}` : ''}.`
                : status === 'endorsed' || trackerStage === 'endorsed'
                  ? 'Waiting for the host organization to schedule an interview.'
                  : accepted
                    ? 'No interview was recorded.'
                    : 'Interview is scheduled by the host organization after school endorsement.',
      ),
    )

    if (companyRejected) {
      steps.push(
        step(
          'company',
          'Host rejected',
          'error',
          'The host organization did not accept this application.',
        ),
      )
    } else {
      const score = assessment?.overallScore
      steps.push(
        step(
          'assessment',
          'Assessment',
          assessmentTone,
          hasAssessment
            ? `Evaluated · ${stageLabel(assessment?.stage)}${score != null ? ` · score ${score}` : ''}.`
            : accepted
              ? 'Not started. An evaluator can record a score when ready.'
              : interviewCompleted || interviewConfirmed
                ? 'Host submits technical, communication, and attitude scores next.'
                : 'Assessment opens after the interview is marked complete.',
        ),
      )
      steps.push(
        step(
          'company',
          'Host decision',
          companyTone,
          accepted
            ? 'The host organization accepted this application.'
            : readyForHostDecision
              ? 'Accept or reject this application.'
              : 'Accept/Reject unlock after the host submits an assessment.',
        ),
      )
      steps.push(
        step(
          'ojt',
          'OJT',
          certificateIssued ? 'complete' : accepted ? 'active' : 'pending',
          ojtMessage,
        ),
      )
      steps.push(
        step(
          'completed',
          'Completed',
          certificateIssued ? 'complete' : 'pending',
          certificateIssued
            ? 'A certificate has been issued for this placement.'
            : 'Completion follows the existing OJT and certificate rules.',
        ),
      )
    }
  }

  let currentKey: ApplicationStepperKey = 'applied'
  if (schoolRejected) currentKey = 'school'
  else if (companyRejected) currentKey = 'company'
  else if (submitted || trackerStage === 'applied') currentKey = 'school'
  else if (!interviewScheduled && (status === 'endorsed' || trackerStage === 'endorsed')) currentKey = 'interview'
  else if (interviewScheduled && !interviewCompleted) currentKey = 'interview'
  else if ((interviewCompleted || interviewConfirmed) && !hasAssessment && !accepted) currentKey = 'assessment'
  else if (readyForHostDecision) currentKey = 'company'
  else if (accepted && !hasAssessment) currentKey = 'assessment'
  else if (accepted && !certificateIssued) currentKey = 'ojt'
  else if (certificateIssued) currentKey = 'completed'
  else currentKey = 'applied'

  const currentIndex = steps.findIndex((item) => item.key === currentKey)
  for (const [index, item] of steps.entries()) {
    item.current = item.key === currentKey
    if (item.current && item.tone === 'pending') item.tone = 'active'
    if (!item.current && item.tone === 'active' && index !== currentIndex) item.tone = 'pending'
  }

  const audience = input.audience ?? 'intern'
  let nextAction = 'Keep an eye on updates for this application.'
  let nextActionTo: string | undefined
  if (schoolRejected || companyRejected) {
    nextAction = audience === 'intern'
      ? 'This application has stopped. Browse other opportunities.'
      : 'This application has stopped.'
    if (audience === 'intern') nextActionTo = '/intern/opportunities'
  } else if (submitted) {
    nextAction = audience === 'school' ? 'Endorse or reject this application.' : 'Waiting for school endorsement.'
  } else if ((status === 'endorsed' || trackerStage === 'endorsed' || trackerStage === 'interview_scheduled') && interview?.status === 'proposed') {
    nextAction = audience === 'intern' ? 'Confirm your interview.' : 'Interview is waiting for confirmation.'
  } else if ((status === 'endorsed' || trackerStage === 'endorsed') && interviewCancelled) {
    nextAction = audience === 'company'
      ? 'Interview cancelled. Propose a new time.'
      : 'Interview cancelled. Waiting for a new time.'
  } else if (readyForHostDecision) {
    nextAction = audience === 'company'
      ? 'Accept or reject this application in the interview tracker.'
      : 'Waiting for the host organization decision.'
  } else if (interviewCompleted && !hasAssessment) {
    nextAction = audience === 'company'
      ? 'Submit the assessment, then Accept or Reject will appear.'
      : 'Waiting for the host to submit an assessment.'
  } else if (status === 'endorsed' || trackerStage === 'endorsed' || trackerStage === 'interview_scheduled') {
    nextAction = audience === 'company'
      ? 'Schedule the interview (Accept/Reject unlock after assessment).'
      : 'Waiting for the host organization to schedule an interview.'
  } else if (accepted && !certificateIssued) {
    nextAction = audience === 'intern' ? 'Track your OJT hours.' : 'OJT is in progress.'
    if (audience === 'intern') nextActionTo = '/ojt-hours'
  } else if (certificateIssued) {
    nextAction = 'A certificate has been issued for this placement.'
  }

  return {
    steps,
    stopped: schoolRejected || companyRejected,
    nextAction,
    nextActionTo,
  }
}

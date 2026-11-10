/**
 * Shared human labels for backend status / stage enums.
 * Unknown values fall back to readable title case — never crash, never show raw snake_case.
 */

const STATUS_LABELS: Record<string, string> = {
  // Applications / tracker
  applied: 'Applied',
  submitted: 'Submitted',
  pending: 'Pending',
  endorsed: 'Endorsed',
  interview_scheduled: 'Interview scheduled',
  interviewed: 'Interviewed',
  assessed: 'Assessed',
  accepted: 'Accepted',
  rejected: 'Rejected',
  school_rejected: 'Not endorsed',
  withdrawn_placed_elsewhere: 'Withdrawn - placed elsewhere',
  placement_cancelled: 'Placement cancelled',
  placement_declined: 'Placement declined',
  placement_ended: 'Placement ended',
  pending_amendment: 'Amendment pending',
  active: 'Active',
  cancelled: 'Cancelled',
  completed: 'Completed',
  confirmed: 'Confirmed',
  proposed: 'Proposed',
  scheduled: 'Scheduled',
  draft: 'Draft',
  closed: 'Closed',
  filled: 'Filled',

  // Hours agreement
  agreed: 'Agreed',
  declined: 'Declined',

  // Placement schedule
  default: 'Default',
  requested: 'Requested',
  approved: 'Approved',
  system_default: 'System default',

  // OJT progress / logs
  on_track: 'On track',
  at_risk: 'At risk',
  not_started: 'Not yet started',

  // Assessment stages
  pre_screen: 'Pre-screen',
  interview: 'Interview',
  final: 'Final',

  // Account / misc
  none: 'None',
  no_application: 'No application',
}

/** Title-case a snake_case / kebab / spaced token without throwing. */
export function titleCaseStatus(value: string): string {
  return value
    .split(/[_\s-]+/)
    .filter(Boolean)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase())
    .join(' ')
}

/** Map any backend status/stage code to a user-facing label. */
export function statusLabel(value?: string | null): string {
  const raw = String(value ?? '').trim()
  if (!raw) return '—'
  const key = raw.toLowerCase()
  if (STATUS_LABELS[key]) return STATUS_LABELS[key]
  return titleCaseStatus(raw)
}

/** Tracker flow labels (same map, exported for existing imports). */
export const TRACKER_STAGE_LABELS: Record<string, string> = {
  applied: STATUS_LABELS.applied!,
  endorsed: STATUS_LABELS.endorsed!,
  interview_scheduled: STATUS_LABELS.interview_scheduled!,
  interviewed: STATUS_LABELS.interviewed!,
  assessed: STATUS_LABELS.assessed!,
  accepted: STATUS_LABELS.accepted!,
  rejected: STATUS_LABELS.rejected!,
  school_rejected: STATUS_LABELS.school_rejected!,
  withdrawn_placed_elsewhere: STATUS_LABELS.withdrawn_placed_elsewhere!,
  placement_cancelled: STATUS_LABELS.placement_cancelled!,
  placement_declined: STATUS_LABELS.placement_declined!,
  placement_ended: STATUS_LABELS.placement_ended!,
}

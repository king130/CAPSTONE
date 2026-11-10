export type JobStatusValue = 'draft' | 'active' | 'closed' | 'filled'

export interface JobStatusOption {
  value: JobStatusValue
  label: string
  description?: string
}

export const jobStatusOptions: JobStatusOption[] = [
  {
    value: 'draft',
    label: 'Draft',
    description: 'Only visible to your company. Not shown to students and not applicable.',
  },
  { value: 'active', label: 'Open', description: 'Accepting new applications.' },
  {
    value: 'closed',
    label: 'Closed',
    description: 'Stops new applications. Existing applicants keep their current progress.',
  },
  {
    value: 'filled',
    label: 'Filled',
    description: 'All internship slots are taken. Existing applicants are unchanged.',
  },
]

export function normalizeJobStatus(status?: string | null): JobStatusValue {
  const normalized = String(status || 'active').trim().toLowerCase()
  if (normalized === 'draft') return 'draft'
  if (normalized === 'closed') return 'closed'
  if (normalized === 'filled') return 'filled'
  return 'active'
}

export function jobStatusLabel(status?: string | null) {
  const normalized = normalizeJobStatus(status)
  if (normalized === 'draft') return 'Draft'
  if (normalized === 'closed') return 'Closed'
  if (normalized === 'filled') return 'Filled'
  return 'Open'
}

/** Draft=gray, Open=green, Closed=gray muted, Filled=blue */
export function jobStatusBadgeClass(status?: string | null) {
  const normalized = normalizeJobStatus(status)
  if (normalized === 'active') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200'
  }
  if (normalized === 'filled') {
    return 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-200'
  }
  if (normalized === 'draft') {
    return 'border-border bg-muted text-muted-foreground'
  }
  // closed
  return 'border-border bg-secondary text-secondary-foreground'
}

export function statusOptionsForJob(options: {
  current: JobStatusValue
  hasApplications?: boolean
}): JobStatusOption[] {
  const { current, hasApplications = false } = options
  if (current === 'draft') {
    return jobStatusOptions.filter((option) => option.value === 'draft' || option.value === 'active')
  }
  return jobStatusOptions.filter((option) => {
    if (option.value !== 'draft') return true
    // Published jobs with applications cannot become drafts again.
    return !hasApplications
  })
}

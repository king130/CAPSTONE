export interface InternshipCapacityInput {
  slotsAvailable?: number | null
  slotsRemaining?: number | null
  filledSlots?: number | null
}

export interface InternshipCapacity {
  limit: number
  filled: number
  remaining: number
  percent: number
  label: string
  remainingLabel: string
}

export function internshipCapacity(input: InternshipCapacityInput): InternshipCapacity {
  const limit = Math.max(0, Math.floor(Number(input.slotsAvailable ?? 0)) || 0)
  const reportedFilled = input.filledSlots == null ? null : Math.max(0, Math.floor(Number(input.filledSlots)) || 0)
  const reportedRemaining = input.slotsRemaining == null ? null : Math.max(0, Math.floor(Number(input.slotsRemaining)) || 0)

  const filled = reportedFilled ?? (reportedRemaining == null ? 0 : Math.max(0, limit - reportedRemaining))
  const occupied = limit > 0 ? Math.min(filled, limit) : 0
  const remaining = limit > 0 ? Math.max(limit - occupied, 0) : 0
  const percent = limit > 0 ? Math.round((occupied / limit) * 100) : 0

  const label = limit > 0
    ? `${occupied} of ${limit} application slots filled`
    : 'No application slots'

  let remainingLabel = 'No slots remaining'
  if (limit > 0 && remaining === 1) remainingLabel = '1 slot remaining'
  else if (limit > 0 && remaining > 1) remainingLabel = `${remaining} slots remaining`

  return {
    limit,
    filled: occupied,
    remaining,
    percent,
    label,
    remainingLabel,
  }
}

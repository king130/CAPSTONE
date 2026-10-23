export type ScheduleDay = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun'

export interface ScheduleSlot {
  day: ScheduleDay
  start: string // HH:mm 24h
  end: string
}

export const DAY_OPTIONS: { value: ScheduleDay; label: string; short: string }[] = [
  { value: 'mon', label: 'Monday', short: 'Mon' },
  { value: 'tue', label: 'Tuesday', short: 'Tue' },
  { value: 'wed', label: 'Wednesday', short: 'Wed' },
  { value: 'thu', label: 'Thursday', short: 'Thu' },
  { value: 'fri', label: 'Friday', short: 'Fri' },
  { value: 'sat', label: 'Saturday', short: 'Sat' },
  { value: 'sun', label: 'Sunday', short: 'Sun' },
]

export const TIME_PRESETS = [
  { label: '8:00 AM – 5:00 PM', start: '08:00', end: '17:00' },
  { label: '9:00 AM – 6:00 PM', start: '09:00', end: '18:00' },
  { label: '8:00 AM – 12:00 PM', start: '08:00', end: '12:00' },
  { label: '1:00 PM – 5:00 PM', start: '13:00', end: '17:00' },
]

const STEP_MINUTES = 30

export function buildTimeOptions(stepMinutes = STEP_MINUTES): { value: string; label: string }[] {
  const options: { value: string; label: string }[] = []
  for (let minutes = 0; minutes < 24 * 60; minutes += stepMinutes) {
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    const value = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`
    options.push({ value, label: formatAmPm(value) })
  }
  return options
}

export function formatAmPm(time: string): string {
  const [hs, ms] = time.split(':')
  let h = Number(hs)
  const m = Number(ms)
  if (Number.isNaN(h) || Number.isNaN(m)) return time
  const suffix = h >= 12 ? 'PM' : 'AM'
  h = h % 12
  if (h === 0) h = 12
  return `${h}:${String(m).padStart(2, '0')} ${suffix}`
}

export function slotsLabel(slots: ScheduleSlot[]): string {
  if (!slots.length) return 'Not set'
  const dayMap = Object.fromEntries(DAY_OPTIONS.map((d) => [d.value, d.short]))
  const days = slots.map((s) => dayMap[s.day] || s.day).join(', ')
  const starts = new Set(slots.map((s) => s.start))
  const ends = new Set(slots.map((s) => s.end))
  const time =
    starts.size === 1 && ends.size === 1
      ? `${formatAmPm(slots[0].start)} – ${formatAmPm(slots[0].end)}`
      : 'varied times'
  return `${days} · ${time}`
}

export function validateSlots(slots: ScheduleSlot[], maxDailyHours = 12): string | null {
  if (!slots.length) return 'Select at least one day.'
  for (const slot of slots) {
    const start = toMinutes(slot.start)
    const end = toMinutes(slot.end)
    if (end <= start) return `End time must be after start time (${slot.day}).`
    if ((end - start) / 60 > maxDailyHours) return `Daily hours cannot exceed ${maxDailyHours}.`
  }
  return null
}

function toMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number)
  return h * 60 + m
}

export function defaultWeekdaySlots(start = '08:00', end = '17:00'): ScheduleSlot[] {
  return (['mon', 'tue', 'wed', 'thu', 'fri'] as ScheduleDay[]).map((day) => ({ day, start, end }))
}

export function slotsFromLegacy(input: {
  scheduleDays?: string[]
  timeIn?: string | null
  timeOut?: string | null
  scheduleSlots?: ScheduleSlot[] | null
}): ScheduleSlot[] {
  if (input.scheduleSlots?.length) return input.scheduleSlots.map((s) => ({ ...s }))
  const start = normalizeTime(input.timeIn)
  const end = normalizeTime(input.timeOut)
  if (!start || !end || !input.scheduleDays?.length) return []
  const slots: ScheduleSlot[] = []
  for (const day of input.scheduleDays) {
    const key = normalizeDay(day)
    if (key) slots.push({ day: key, start, end })
  }
  return slots
}

function normalizeDay(raw: string): ScheduleDay | null {
  const v = raw.trim().toLowerCase().slice(0, 3)
  const map: Record<string, ScheduleDay> = {
    mon: 'mon',
    tue: 'tue',
    wed: 'wed',
    thu: 'thu',
    fri: 'fri',
    sat: 'sat',
    sun: 'sun',
  }
  if (map[v]) return map[v]
  const full = raw.trim().toLowerCase()
  for (const opt of DAY_OPTIONS) {
    if (opt.label.toLowerCase() === full) return opt.value
  }
  return null
}

function normalizeTime(raw?: string | null): string | null {
  if (!raw) return null
  const t = raw.trim()
  if (/^\d{2}:\d{2}/.test(t)) return t.slice(0, 5)
  return null
}

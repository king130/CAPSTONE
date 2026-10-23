export interface DtrSuggestion {
  hours: number | null
  periodStart: string | null
  periodEnd: string | null
}

const MONTHS: Record<string, number> = {
  january: 1,
  jan: 1,
  february: 2,
  feb: 2,
  march: 3,
  mar: 3,
  april: 4,
  apr: 4,
  may: 5,
  june: 6,
  jun: 6,
  july: 7,
  jul: 7,
  august: 8,
  aug: 8,
  september: 9,
  sept: 9,
  sep: 9,
  october: 10,
  oct: 10,
  november: 11,
  nov: 11,
  december: 12,
  dec: 12,
}

const MONTH_PATTERN = Object.keys(MONTHS)
  .sort((left, right) => right.length - left.length)
  .join('|')

export function parseDtrText(raw: string): DtrSuggestion {
  const text = raw.replace(/\r/g, '\n').replace(/[|]/g, ' ')
  const explicitHours = explicitTotalHours(text)
  const labeled = labeledPeriod(text)
  const absoluteDates = collectAbsoluteDates(text)
  const daily = dailyRows(text)

  let hours = explicitHours
  if (hours == null && daily.hours.length) {
    hours = roundHours(daily.hours.reduce((sum, value) => sum + value, 0))
  }

  let periodStart = labeled.start
  let periodEnd = labeled.end
  if (!periodStart || !periodEnd) {
    const dates = [...absoluteDates]
    if (daily.dates.length) dates.push(...daily.dates)
    dates.sort()
    if (!periodStart && dates.length) periodStart = dates[0] ?? null
    if (!periodEnd && dates.length) periodEnd = dates[dates.length - 1] ?? null
  }
  if (periodStart && !periodEnd) periodEnd = periodStart
  if (periodEnd && !periodStart) periodStart = periodEnd

  return {
    hours: hours != null && hours >= 0.25 && hours <= 500 ? hours : null,
    periodStart,
    periodEnd,
  }
}

function explicitTotalHours(text: string): number | null {
  const patterns = [
    /total\s*hours?\s*[:\-]?\s*(\d{1,3}(?:\.\d{1,2})?)/i,
    /hours?\s*(?:rendered|worked)\s*[:\-]?\s*(\d{1,3}(?:\.\d{1,2})?)/i,
    /grand\s*total\s*[:\-]?\s*(\d{1,3}(?:\.\d{1,2})?)\s*(?:hours|hrs)?/i,
  ]
  for (const pattern of patterns) {
    const match = text.match(pattern)
    const hours = match ? acceptHours(match[1]) : null
    if (hours != null) return hours
  }
  return null
}

function labeledPeriod(text: string): { start: string | null; end: string | null } {
  const start = firstDate(snippet(text, /period\s*start\s*[:\-]?\s*([^\n]{4,48})/i))
  const end = firstDate(snippet(text, /period\s*end\s*[:\-]?\s*([^\n]{4,48})/i))
  if (start || end) return { start, end }

  const range = text.match(
    new RegExp(
      `\\b((?:\\d{4}-\\d{2}-\\d{2})|(?:\\d{1,2}[\\/\\-]\\d{1,2}[\\/\\-]20\\d{2})|(?:(?:${MONTH_PATTERN})\\.?\\s+\\d{1,2},?\\s+20\\d{2})|(?:\\d{1,2}\\s+(?:${MONTH_PATTERN})\\.?\\s+20\\d{2}))\\s*(?:to|through|–|—|-)\\s*((?:\\d{4}-\\d{2}-\\d{2})|(?:\\d{1,2}[\\/\\-]\\d{1,2}[\\/\\-]20\\d{2})|(?:(?:${MONTH_PATTERN})\\.?\\s+\\d{1,2},?\\s+20\\d{2})|(?:\\d{1,2}\\s+(?:${MONTH_PATTERN})\\.?\\s+20\\d{2}))`,
      'i',
    ),
  )
  if (!range) return { start: null, end: null }
  return { start: parseDateToken(range[1] ?? ''), end: parseDateToken(range[2] ?? '') }
}

function snippet(text: string, pattern: RegExp): string {
  return text.match(pattern)?.[1] ?? ''
}

function firstDate(value: string): string | null {
  return collectAbsoluteDates(value)[0] ?? parseDateToken(value)
}

function collectAbsoluteDates(text: string): string[] {
  const found = new Set<string>()
  for (const match of text.matchAll(/\b(20\d{2})-(\d{2})-(\d{2})\b/g)) {
    const iso = toIso(Number(match[1]), Number(match[2]), Number(match[3]))
    if (iso) found.add(iso)
  }
  for (const match of text.matchAll(/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](20\d{2})\b/g)) {
    const iso = toIso(Number(match[3]), Number(match[1]), Number(match[2]))
    if (iso) found.add(iso)
  }
  const monthFirst = new RegExp(`\\b(${MONTH_PATTERN})\\.?\\s+(\\d{1,2}),?\\s+(20\\d{2})\\b`, 'gi')
  for (const match of text.matchAll(monthFirst)) {
    const month = MONTHS[match[1]!.toLowerCase()]
    const iso = month ? toIso(Number(match[3]), month, Number(match[2])) : null
    if (iso) found.add(iso)
  }
  const dayFirst = new RegExp(`\\b(\\d{1,2})\\s+(${MONTH_PATTERN})\\.?\\s+(20\\d{2})\\b`, 'gi')
  for (const match of text.matchAll(dayFirst)) {
    const month = MONTHS[match[2]!.toLowerCase()]
    const iso = month ? toIso(Number(match[3]), month, Number(match[1])) : null
    if (iso) found.add(iso)
  }
  return [...found].sort()
}

function parseDateToken(value: string): string | null {
  return collectAbsoluteDates(value)[0] ?? null
}

function dailyRows(text: string): { hours: number[]; dates: string[] } {
  const month = text.match(
    new RegExp(`(?:for the month of|month of)\\s+(${MONTH_PATTERN})\\.?\\s+(20\\d{2})`, 'i'),
  )
  const monthNumber = month ? MONTHS[month[1]!.toLowerCase()] : null
  const year = month ? Number(month[2]) : null
  const hours: number[] = []
  const dates: string[] = []

  for (const line of text.split('\n')) {
    if (/\b20\d{2}-\d{2}-\d{2}\b/.test(line)) continue
    const day = line.match(/^\s*(\d{1,2})\b/)
    if (!day) continue
    const dayNumber = Number(day[1])
    if (dayNumber < 1 || dayNumber > 31) continue
    const fromTimes = hoursFromTimes(line)
    if (fromTimes == null) continue
    hours.push(fromTimes)
    if (monthNumber && year) {
      const iso = toIso(year, monthNumber, dayNumber)
      if (iso) dates.push(iso)
    }
  }

  return { hours, dates }
}

function hoursFromTimes(line: string): number | null {
  const matches = [...line.matchAll(/\b(\d{1,2}):(\d{2})\s*(am|pm)?\b/gi)]
  if (matches.length < 2) return null
  const minutes = matches.map((match) => {
    let hour = Number(match[1])
    const minute = Number(match[2])
    const meridiem = match[3]?.toLowerCase()
    if (meridiem === 'pm' && hour < 12) hour += 12
    if (meridiem === 'am' && hour === 12) hour = 0
    if (minute > 59 || hour > 23) return null
    return hour * 60 + minute
  })
  if (minutes.some((value) => value == null)) return null

  let total = 0
  let pairs = 0
  for (let index = 0; index + 1 < minutes.length; index += 2) {
    let diff = (minutes[index + 1] as number) - (minutes[index] as number)
    if (diff < 0 && !matches[index]?.[3] && !matches[index + 1]?.[3]) diff += 12 * 60
    if (diff <= 0 || diff > 12 * 60) continue
    total += diff
    pairs += 1
  }
  if (!pairs) return null
  return roundHours(total / 60)
}

function acceptHours(raw: string | undefined): number | null {
  if (!raw) return null
  const hours = Number(raw)
  if (!Number.isFinite(hours) || hours < 0.25 || hours > 500) return null
  if (hours >= 1900 && hours <= 2100 && !raw.includes('.')) return null
  return roundHours(hours)
}

function roundHours(value: number): number {
  return Math.round(value * 100) / 100
}

function toIso(year: number, month: number, day: number): string | null {
  if (month < 1 || month > 12 || day < 1 || day > 31) return null
  const date = new Date(Date.UTC(year, month - 1, day))
  if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
    return null
  }
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

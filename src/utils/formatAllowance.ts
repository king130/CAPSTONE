/** Display-only formatting for internship allowance strings. Does not change stored values. */

const EMPTY_LABEL = 'No allowance'

function formatAmount(amount: number, fractionDigits: number): string {
  const fixed = amount.toFixed(fractionDigits)
  const [intPart = '0', fracPart] = fixed.split('.')
  const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  return fractionDigits > 0 ? `${grouped}.${fracPart}` : grouped
}

/**
 * Show ₱ with comma grouping when a number is present; keep any suffix.
 * Empty → "No allowance". No digits → leave text as-is.
 */
export function formatAllowanceDisplay(value: string | null | undefined): string {
  const raw = String(value ?? '').trim()
  if (!raw) return EMPTY_LABEL

  const match = raw.match(/(\d[\d,]*(?:\.\d+)?)/)
  if (!match || match.index === undefined || match[1] === undefined) return raw

  const digits = match[1].replace(/,/g, '')
  const amount = Number(digits)
  if (!Number.isFinite(amount)) return raw

  const fraction = digits.includes('.') ? (digits.split('.')[1]?.length ?? 0) : 0
  const formatted = formatAmount(amount, fraction)
  const suffix = raw.slice(match.index + match[1].length)

  return `₱${formatted}${suffix}`
}

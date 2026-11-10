const DEFAULT_SCHEME = 'ojtintern'

export function medianAppScheme(configured?: string): string {
  const scheme = (configured || DEFAULT_SCHEME).trim().toLowerCase()
  return /^[a-z]+$/.test(scheme) ? scheme : DEFAULT_SCHEME
}

/** Median opens `scheme.https://host/path` inside the installed app. */
export function medianAppUrl(scheme: string, href: string): string | null {
  const cleaned = medianAppScheme(scheme)
  if (!/^https?:\/\//i.test(href)) return null
  return `${cleaned}.${href}`
}

export function shouldHandOffToMedianApp(input: {
  userAgent: string
  alreadyInsideApp: boolean
  alreadyAttempted: boolean
}): boolean {
  if (input.alreadyInsideApp || input.alreadyAttempted) return false
  if (/median|gonative/i.test(input.userAgent)) return false
  return /Android|iPhone|iPad|iPod/i.test(input.userAgent)
}

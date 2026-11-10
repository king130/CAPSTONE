import { describe, expect, it } from 'vitest'

import { medianAppUrl, shouldHandOffToMedianApp } from '@/utils/medianAppLink'

describe('medianAppUrl', () => {
  it('prefixes the https setup link with the Median scheme', () => {
    expect(medianAppUrl('ojtintern', 'https://example.com/account-setup?token=abc')).toBe(
      'ojtintern.https://example.com/account-setup?token=abc',
    )
  })

  it('ignores a scheme that Median would reject', () => {
    expect(medianAppUrl('App1', 'https://example.com/account-setup?token=abc')).toBe(
      'ojtintern.https://example.com/account-setup?token=abc',
    )
  })
})

describe('shouldHandOffToMedianApp', () => {
  it('hands a phone browser off once', () => {
    expect(shouldHandOffToMedianApp({
      userAgent: 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0.0.0 Mobile',
      alreadyInsideApp: false,
      alreadyAttempted: false,
    })).toBe(true)
  })

  it('stays on the page inside the Median app and on desktop', () => {
    expect(shouldHandOffToMedianApp({
      userAgent: 'Mozilla/5.0 MedianAndroid',
      alreadyInsideApp: false,
      alreadyAttempted: false,
    })).toBe(false)
    expect(shouldHandOffToMedianApp({
      userAgent: 'Mozilla/5.0 (Windows NT 10.0)',
      alreadyInsideApp: false,
      alreadyAttempted: false,
    })).toBe(false)
  })
})

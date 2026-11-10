import { describe, expect, it } from 'vitest'

import {
  extensionOf,
  formatFileSize,
  needsAuthenticatedFetch,
  normalizeMimeType,
  resolvePreviewKind,
} from '@/utils/filePreview'

describe('filePreview helpers', () => {
  it('maps mime types to preview kinds', () => {
    expect(resolvePreviewKind({ contentType: 'application/pdf' })).toBe('pdf')
    expect(resolvePreviewKind({ contentType: 'application/pdf; charset=binary' })).toBe('pdf')
    expect(resolvePreviewKind({ contentType: 'image/jpeg' })).toBe('image')
    expect(resolvePreviewKind({ contentType: 'image/png' })).toBe('image')
    expect(resolvePreviewKind({ contentType: 'image/webp' })).toBe('image')
    expect(resolvePreviewKind({ contentType: 'image/svg+xml' })).toBe('unsupported')
    expect(resolvePreviewKind({ contentType: 'text/html' })).toBe('unsupported')
    expect(resolvePreviewKind({ contentType: 'application/msword' })).toBe('unsupported')
  })

  it('falls back to extension when mime is missing', () => {
    expect(resolvePreviewKind({ fileName: 'report.PDF' })).toBe('pdf')
    expect(resolvePreviewKind({ fileName: 'photo.JPEG' })).toBe('image')
    expect(resolvePreviewKind({ fileName: 'photo.webp' })).toBe('image')
    expect(resolvePreviewKind({ fileName: 'notes.docx' })).toBe('unsupported')
    expect(resolvePreviewKind({ fileName: 'icon.svg' })).toBe('unsupported')
  })

  it('prefers mime over a conflicting extension', () => {
    expect(resolvePreviewKind({ contentType: 'application/pdf', fileName: 'scan.png' })).toBe('pdf')
  })

  it('parses extensions and formats sizes', () => {
    expect(extensionOf('folder/resume.PDF')).toBe('pdf')
    expect(extensionOf('noext')).toBe('')
    expect(formatFileSize(512)).toBe('512 B')
    expect(formatFileSize(2048)).toContain('KB')
    expect(formatFileSize(null)).toBe('')
    expect(normalizeMimeType(' Image/PNG ; charset=utf-8 ')).toBe('image/png')
  })

  it('detects which sources need authenticated fetch', () => {
    expect(needsAuthenticatedFetch('/api/documents/1/file')).toBe(true)
    expect(needsAuthenticatedFetch('/ojt-dtrs/3/file')).toBe(true)
    expect(needsAuthenticatedFetch('/organization-verification/documents/1/file')).toBe(true)
    expect(needsAuthenticatedFetch('/storage/contracts/1/a.pdf')).toBe(false)
    expect(needsAuthenticatedFetch('http://127.0.0.1:8000/storage/documents/1/a.pdf')).toBe(false)
    expect(needsAuthenticatedFetch('http://127.0.0.1:8000/api/documents/1/file')).toBe(true)
  })
})

/** API base URL, e.g. http://localhost:8000/api — or leave empty and use Vite proxy to /api */
const TOKEN_KEY = 'capstone_auth_token'

function normalizeApiBase(raw: unknown): string | null {
  const value = String(raw ?? '').trim().replace(/\/$/, '')
  if (!value) return null

  if (value.startsWith('/')) return value
  if (/^https?:\/\//i.test(value)) return value

  const protocol =
    typeof window !== 'undefined' && window.location?.protocol
      ? window.location.protocol
      : 'http:'
  const hostname =
    typeof window !== 'undefined' && window.location?.hostname
      ? window.location.hostname
      : '127.0.0.1'

  if (/^:\d+(\/|$)/.test(value)) return `${protocol}//${hostname}${value}`
  if (/^[^/]+:\d+(\/|$)/.test(value)) return `${protocol}//${value}`

  if (
    /^(localhost|127\.0\.0\.1|0\.0\.0\.0|\d{1,3}(?:\.\d{1,3}){3})(\/|$)/.test(value) ||
    /^[a-z0-9.-]+\.[a-z]{2,}(\/|$)/i.test(value)
  ) {
    return `${protocol}//${value}`
  }

  return value
}

function tryParseJson(text: string): Record<string, unknown> | null {
  if (!text.trim()) {
    return null
  }

  try {
    return JSON.parse(text) as Record<string, unknown>
  } catch {
    return null
  }
}

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export function apiBase(): string {
  const explicit = normalizeApiBase(import.meta.env.VITE_API_BASE_URL)
  if (explicit) return explicit

  if (import.meta.env.DEV && typeof window !== 'undefined') {
    const hostname = window.location.hostname
    if (hostname === '127.0.0.1' || hostname === 'localhost') {
      return 'http://127.0.0.1:8000/api'
    }
  }

  return ''
}

export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const base = apiBase()
  const pathPart = path.startsWith('/') ? path : `/${path}`
  const url = base ? `${base}${pathPart}` : `/api${pathPart}`

  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  const body = init.body
  if (body && !(body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  const token = getToken()
  if (token) {
    headers.set('Authorization', `Bearer ${token}`)
  }

  const res = await fetch(url, { ...init, headers })
  if (res.status === 204) {
    return undefined as T
  }
  const text = await res.text()
  const json = tryParseJson(text)
  if (!res.ok) {
    const msg = json?.message
    const err =
      Array.isArray(msg) ? JSON.stringify(msg)
      : typeof msg === 'string' ? msg
      : text.trim() || res.statusText
    throw new Error(typeof err === 'string' ? err : 'Request failed')
  }

  if (json !== null) {
    return json as T
  }

  return text as T
}

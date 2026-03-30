/** API base URL, e.g. http://localhost:8000/api — or leave empty and use Vite proxy to /api */
const TOKEN_KEY = 'capstone_auth_token'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

export function apiBase(): string {
  const b = import.meta.env.VITE_API_BASE_URL
  if (b && String(b).trim() !== '') {
    return String(b).replace(/\/$/, '')
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
  const json = text ? (JSON.parse(text) as Record<string, unknown>) : null
  if (!res.ok) {
    const msg = json?.message
    const err = Array.isArray(msg) ? JSON.stringify(msg) : (msg as string) || res.statusText
    throw new Error(typeof err === 'string' ? err : 'Request failed')
  }
  return json as T
}

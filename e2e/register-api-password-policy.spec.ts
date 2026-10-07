import { test, expect } from '@playwright/test'

const API_BASE = process.env.PLAYWRIGHT_API_BASE || 'http://127.0.0.1:8000/api'

function uniqueEmail(tag: string) {
  return `e2e.api.pw.${tag}.${Date.now()}@example.com`
}

function schoolRegisterBody(email: string, password: string) {
  return {
    email,
    password,
    fullName: 'API Password Policy Tester',
    role: 'school',
    profile: {
      institutionName: 'API Policy Test School',
      contactPerson: 'Coordinator',
      officialSchoolEmail: email,
      address: 'Alapan I-A, Imus City, Cavite',
      contactPhone: '09171234567',
    },
    subscriptionPlan: 'free',
    billingCycle: 'monthly',
  }
}

test.describe('Register API password policy (bypasses UI)', () => {
  test('rejects weak password 12345678 with HTTP 400', async ({ request }) => {
    const response = await request.post(`${API_BASE}/auth/register`, {
      data: schoolRegisterBody(uniqueEmail('weak'), '12345678'),
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })

    expect(response.status(), await response.text()).toBe(400)
    const body = await response.json()
    expect(String(body.message || '')).toMatch(/at least 8 characters and include a letter and a number/i)
    expect(body.errors?.password?.[0] || '').toMatch(/letter and a number/i)
  })

  test('rejects password with letters only', async ({ request }) => {
    const response = await request.post(`${API_BASE}/auth/register`, {
      data: schoolRegisterBody(uniqueEmail('noletterdigit'), 'PasswordOnly'),
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })

    expect(response.status()).toBe(400)
  })

  test('accepts strong password Passw0rd1', async ({ request }) => {
    const email = uniqueEmail('strong')
    const response = await request.post(`${API_BASE}/auth/register`, {
      data: schoolRegisterBody(email, 'Passw0rd1'),
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })

    expect(response.status(), await response.text()).toBe(201)
    const body = await response.json()
    expect(body.token).toBeTruthy()
    expect(body.user?.email?.toLowerCase()).toBe(email.toLowerCase())
  })
})

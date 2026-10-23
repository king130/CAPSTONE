/**
 * Smoke only: demo school Endorsements must fetch /api/applications via Vite proxy.
 * Does NOT register new orgs.
 */
import { test, expect } from '@playwright/test'
import { DEMO, loginAsFresh, newRoleContext, waitForAppShell } from './helpers/auth'

test('school endorsements applications fetch succeeds', async ({ browser }) => {
  test.setTimeout(90_000)
  const { context, page } = await newRoleContext(browser)
  const failures: string[] = []

  page.on('response', (res) => {
    const url = res.url()
    if (!/\/api\/applications(\?|$)/.test(url)) return
    if (!res.ok()) failures.push(`${res.status()} ${url}`)
  })
  page.on('pageerror', (err) => failures.push(`pageerror: ${err.message}`))
  page.on('console', (msg) => {
    if (msg.type() === 'error' && /failed to (fetch|reach api)/i.test(msg.text())) {
      failures.push(`console: ${msg.text()}`)
    }
  })

  await loginAsFresh(page, DEMO.school.email, DEMO.school.password)
  await waitForAppShell(page)

  const appsResponse = page.waitForResponse(
    (r) => /\/api\/applications(\?|$)/.test(r.url()) && r.request().method() === 'GET',
    { timeout: 30_000 }
  )
  await page.goto('/school/endorsements')
  const res = await appsResponse
  expect(res.ok(), `applications GET ${res.status()} url=${res.url()}`).toBeTruthy()
  expect(res.url()).toMatch(/127\.0\.0\.1:5173\/api\/applications/)

  await expect(page.getByRole('heading', { name: /Endorsements/i }).first()).toBeVisible({
    timeout: 20_000,
  })
  await expect(page.getByText(/Failed to fetch|Failed to reach API|Unable to load endorsements/i)).toHaveCount(0)

  expect(failures, failures.join('\n')).toEqual([])
  await context.close()
})

import { test, expect } from '@playwright/test'
import {
  type RegisterRole,
  VALID,
  uniqueEmail,
  gotoRegister,
  fillValidRegistration,
  fillCommonValid,
  fillSchoolOnly,
  fillCompanyOnly,
  submitRegister,
  expectStillOnRegister,
  expectFieldError,
  expectAnyValidationError,
  expectServerOrClientError,
} from './helpers/register'

/**
 * Public account creation covers School and Company only.
 * Admin is seeded / platform-managed; students are created by schools — not this form.
 */
const roles: RegisterRole[] = ['school', 'company']

for (const role of roles) {
  test.describe(`Register ${role}`, () => {
    test(`successful account creation with valid data (${role})`, async ({ page }) => {
      const email = uniqueEmail(role, 'success')
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, email)
      await submitRegister(page)
      await expect(page).toHaveURL(/\/login/, { timeout: 20_000 })
      await expect(page.getByText(/Account created|registered|sign in/i).first()).toBeVisible({ timeout: 10_000 }).catch(() => {
        // Login page alone after redirect is enough success signal
      })
    })

    test(`blocks empty fullName (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'empty-name'))
      await page.locator('#fullName').fill('')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /full name|Enter the full name/i)
    })

    test(`blocks empty email (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'empty-email'))
      await page.locator('#registerEmail').fill('')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /Email is required|valid email/i)
    })

    test(`blocks empty password (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'empty-pass'))
      await page.locator('#registerPassword').fill('')
      await page.locator('#confirmPassword').fill('')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectAnyValidationError(page)
    })

    test(`blocks empty confirmPassword (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'empty-confirm'))
      await page.locator('#confirmPassword').fill('')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /confirm your password|Passwords do not match/i)
    })

    test(`blocks empty contactPhone (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'empty-phone'))
      await page.locator('#contactPhone').fill('')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /Contact number is required|valid PH mobile/i)
    })

    test(`blocks empty locationArea (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await page.locator('#fullName').fill(VALID.fullName)
      await page.locator('#registerEmail').fill(uniqueEmail(role, 'empty-loc'))
      await page.locator('#registerPassword').fill(VALID.password)
      await page.locator('#confirmPassword').fill(VALID.password)
      await page.locator('#contactPhone').fill(VALID.phone)
      await page.locator('#addressLine').fill(VALID.addressLine)
      if (role === 'school') await fillSchoolOnly(page)
      else await fillCompanyOnly(page)
      // Leave city/municipality and barangay unset
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /city or municipality|Select a barangay/i)
    })

    test(`blocks empty barangay (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'empty-brgy'))
      await page.evaluate(() => {
        const el = document.querySelector('#barangay') as HTMLSelectElement | null
        if (!el) return
        el.value = ''
        el.dispatchEvent(new Event('change', { bubbles: true }))
      })
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /Select a barangay/i)
    })

    if (role === 'school') {
      test('blocks empty schoolName', async ({ page }) => {
        await gotoRegister(page, role)
        await fillValidRegistration(page, role, uniqueEmail(role, 'empty-school'))
        await page.locator('#schoolName').fill('')
        await submitRegister(page)
        await expectStillOnRegister(page)
        await expectFieldError(page, /School name is required/i)
      })

      test('blocks empty contactPerson', async ({ page }) => {
        await gotoRegister(page, role)
        await fillValidRegistration(page, role, uniqueEmail(role, 'empty-contact'))
        await page.locator('#contactPerson').fill('')
        await submitRegister(page)
        await expectStillOnRegister(page)
        await expectFieldError(page, /Contact person is required/i)
      })
    }

    if (role === 'company') {
      test('blocks empty companyName', async ({ page }) => {
        await gotoRegister(page, role)
        await fillValidRegistration(page, role, uniqueEmail(role, 'empty-company'))
        await page.locator('#companyName').fill('')
        await submitRegister(page)
        await expectStillOnRegister(page)
        await expectFieldError(page, /Company name is required/i)
      })

      test('blocks empty industry', async ({ page }) => {
        await gotoRegister(page, role)
        await fillValidRegistration(page, role, uniqueEmail(role, 'empty-industry'))
        await page.evaluate(() => {
          const el = document.querySelector('#industry') as HTMLSelectElement | null
          if (!el) return
          el.value = ''
          el.dispatchEvent(new Event('change', { bubbles: true }))
        })
        await submitRegister(page)
        await expectStillOnRegister(page)
        await expectFieldError(page, /Select an industry/i)
      })
    }

    test(`rejects invalid email format (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, 'not-an-email')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /valid email/i)
    })

    test(`rejects password too short (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'short-pass'))
      await page.locator('#registerPassword').fill('Ab1')
      await page.locator('#confirmPassword').fill('Ab1')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /at least 8 characters/i)
    })

    test(`rejects password missing letter (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'pass-noletter'))
      await page.locator('#registerPassword').fill('12345678')
      await page.locator('#confirmPassword').fill('12345678')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /at least one letter/i)
    })

    test(`rejects password missing number (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'pass-nonumber'))
      await page.locator('#registerPassword').fill('PasswordOnly')
      await page.locator('#confirmPassword').fill('PasswordOnly')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /at least one number/i)
    })

    test(`rejects duplicate email (${role})`, async ({ page }) => {
      const email = uniqueEmail(role, 'dup')
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, email)
      await submitRegister(page)
      await expect(page).toHaveURL(/\/login/, { timeout: 20_000 })

      await gotoRegister(page, role)
      await fillValidRegistration(page, role, email)
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectServerOrClientError(page, /already registered|already been taken|email/i)
    })

    test(`rejects overly long addressLine (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'long-addr'))
      await page.locator('#addressLine').fill('A'.repeat(500))
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /under 120 characters/i)
    })

    test(`rejects whitespace-only fullName (${role})`, async ({ page }) => {
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, uniqueEmail(role, 'ws-name'))
      await page.locator('#fullName').fill('   ')
      await submitRegister(page)
      await expectStillOnRegister(page)
      await expectFieldError(page, /full name|Enter the full name/i)
    })

    test(`script injection in name is not executed (${role})`, async ({ page }) => {
      const xss = '<script>alert(1)</script>'
      const dialogs: string[] = []
      page.on('dialog', async (dialog) => {
        dialogs.push(dialog.message())
        await dialog.dismiss()
      })

      const email = uniqueEmail(role, 'xss')
      await gotoRegister(page, role)
      await fillValidRegistration(page, role, email)
      await page.locator('#fullName').fill(xss)
      if (role === 'school') {
        await page.locator('#schoolName').fill(xss)
        await page.locator('#contactPerson').fill(xss)
      } else {
        await page.locator('#companyName').fill(xss)
      }

      await submitRegister(page)
      // Either client accepts (min length ok) and redirects, or server rejects
      await page.waitForTimeout(2000)
      expect(dialogs, 'XSS should not execute alert()').toEqual([])

      // Value should remain text in the input if still on form; if redirected, no script ran
      if (page.url().includes('/register')) {
        await expect(page.locator('#fullName')).toHaveValue(xss)
        const scriptNodes = await page.locator('script').evaluateAll((nodes) =>
          nodes.some((n) => (n.textContent || '').includes('alert(1)')),
        )
        expect(scriptNodes).toBeFalsy()
      } else {
        // Logged-out login page after success — confirm no injected script executed
        expect(dialogs).toEqual([])
      }
    })
  })
}

test.describe('Register role scope', () => {
  test('student accounts are not created on this form', async ({ page }) => {
    await page.goto('/register')
    await expect(page.getByText(/Student accounts are not created here/i)).toBeVisible()
    await expect(page.getByRole('button', { name: 'School Manage student access' })).toBeVisible()
    await expect(page.getByRole('button', { name: /Company/i }).filter({ hasText: 'Company' }).first()).toBeVisible()
    await expect(page.getByRole('group', { name: 'Account type' }).getByRole('button', { name: /Admin/i })).toHaveCount(0)
  })
})

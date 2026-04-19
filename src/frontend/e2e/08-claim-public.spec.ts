import { test, expect } from '@playwright/test'

test.describe('Public Claim Landing', () => {
  test('claim page with invalid UUID shows error', async ({ page }) => {
    await page.goto('/claim/nonexistent-uuid-12345?from=1')
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toMatch(/no longer active|invalid|error/i)
  })

  test('claim page is public (no auth redirect)', async ({ page }) => {
    // Should NOT redirect to /login — it's a public route
    await page.goto('/claim/some-uuid?from=1')
    await page.waitForTimeout(1000)
    const url = page.url()
    expect(url).toContain('/claim/')
    expect(url).not.toContain('/login')
  })
})

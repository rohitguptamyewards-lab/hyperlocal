import { test, expect } from '@playwright/test'
import { merchantLogin } from './helpers'

test.describe('Network Module', () => {
  test.beforeEach(async ({ page }) => {
    await merchantLogin(page)
  })

  test('network list page loads', async ({ page }) => {
    await page.goto('/networks')
    await page.waitForTimeout(1000)
    const body = await page.textContent('body')
    expect(body).toMatch(/Network|Create|No networks/i)
  })

  test('create network flow', async ({ page }) => {
    await page.goto('/networks')
    await page.waitForTimeout(500)
    const createBtn = page.locator('button:has-text("Create"), button:has-text("New")')
    if (await createBtn.isVisible()) {
      await createBtn.click()
      await page.waitForTimeout(500)
      // Should show create form/modal
      const body = await page.textContent('body')
      expect(body).toMatch(/name|Name|network/i)
    }
  })

  test('network join page requires auth', async ({ page }) => {
    // Clear auth
    await page.goto('/login')
    await page.evaluate(() => localStorage.clear())
    await page.goto('/networks/join/fake-token-123')
    await page.waitForURL('**/login')
  })
})

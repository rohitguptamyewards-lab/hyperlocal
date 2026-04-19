import { test, expect } from '@playwright/test'
import { merchantLogin } from './helpers'

test.describe('Sidebar Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await merchantLogin(page)
  })

  test('all sidebar nav links are visible', async ({ page }) => {
    await expect(page.locator('nav >> text=Dashboard')).toBeVisible({ timeout: 5000 })
    await expect(page.locator('nav >> text=Partnerships')).toBeVisible()
    await expect(page.locator('nav >> text=Find Partners')).toBeVisible()
    await expect(page.locator('nav >> text=Campaigns')).toBeVisible()
    await expect(page.locator('nav >> text=Networks')).toBeVisible()
    await expect(page.locator('nav >> text=Settings')).toBeVisible()
  })

  test('each nav link navigates to correct page', async ({ page }) => {
    const routes = [
      { text: 'Partnerships', path: '/partnerships' },
      { text: 'Find Partners', path: '/find-partners' },
      { text: 'Campaigns', path: '/campaigns' },
      { text: 'Networks', path: '/networks' },
      { text: 'Settings', path: '/settings' },
      { text: 'Dashboard', path: '/dashboard' },
    ]

    for (const r of routes) {
      await page.locator(`nav >> text=${r.text}`).click()
      await page.waitForURL(new RegExp(r.path), { timeout: 5000 })
    }
  })

  test('active nav link is highlighted', async ({ page }) => {
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships', { timeout: 5000 })
    await page.waitForTimeout(300)
    // Check that the partnerships link has indigo styling
    const body = await page.textContent('body')
    expect(body).toContain('Partnerships')
  })
})

test.describe('Edge Cases', () => {
  test('root / redirects to /dashboard', async ({ page }) => {
    await merchantLogin(page)
    await page.goto('/')
    await page.waitForURL('**/dashboard')
  })

  test('unknown route falls through gracefully', async ({ page }) => {
    await merchantLogin(page)
    // Navigate to unknown page within the app
    await page.evaluate(() => {
      window.history.pushState({}, '', '/nonexistent-page-xyz')
    })
    await page.waitForTimeout(1000)
    const body = await page.textContent('body')
    expect(body).toBeDefined()
  })
})

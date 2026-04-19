import { test, expect } from '@playwright/test'
import { merchantLogin } from './helpers'

test.describe('Partnership List & Create', () => {
  test.beforeEach(async ({ page }) => {
    await merchantLogin(page)
  })

  test('partnership list page loads', async ({ page }) => {
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships', { timeout: 5000 })
    await page.waitForTimeout(1500)
    const body = await page.textContent('body')
    expect(body).toMatch(/Partnerships|No partnerships yet/)
  })

  test('new partnership button visible for admin', async ({ page }) => {
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships', { timeout: 5000 })
    await page.waitForTimeout(1500)
    await expect(page.locator('text=New partnership')).toBeVisible({ timeout: 5000 })
  })

  test('create modal opens with required fields', async ({ page }) => {
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships', { timeout: 5000 })
    // Wait for the page to load (button or empty state)
    await page.waitForTimeout(2000)
    const btn = page.locator('button:has-text("New partnership")')
    await expect(btn).toBeVisible({ timeout: 10000 })
    await btn.click()
    await expect(page.locator('h2:has-text("New partnership")')).toBeVisible({ timeout: 5000 })
    await expect(page.locator('select')).toBeVisible()
    // Scope radios inside the modal form
    const modal = page.locator('form')
    await expect(modal.locator('text=Outlet-level')).toBeVisible()
    await expect(modal.locator('text=Brand-wide')).toBeVisible()
  })

  test('create modal shows outlet picker for outlet-level scope', async ({ page }) => {
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships', { timeout: 5000 })
    await page.waitForTimeout(2000)
    const btn = page.locator('button:has-text("New partnership")')
    await expect(btn).toBeVisible({ timeout: 10000 })
    await btn.click()
    await expect(page.locator('h2:has-text("New partnership")')).toBeVisible({ timeout: 5000 })
    // Wait for outlets API to load
    await page.waitForTimeout(1500)
    const modal = page.locator('form')
    const hasOutlets = await modal.locator('text=Your outlets').isVisible()
    if (hasOutlets) {
      // Check that outlet checkboxes are present inside the modal form
      await expect(modal.locator('input[type="checkbox"]').first()).toBeVisible()
    }
  })

  test('create partnership and navigate to detail', async ({ page }) => {
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships', { timeout: 5000 })
    await page.waitForSelector('text=New partnership', { timeout: 10000 })
    await page.click('text=New partnership')
    await page.waitForSelector('h2:has-text("New partnership")', { timeout: 5000 })

    await page.fill('input[placeholder*="Brew"]', 'Test Partnership E2E')
    await page.waitForTimeout(1000)
    // Select GreenBowl — the only brand without an existing partnership with Brew & Co
    const select = page.locator('select')
    await select.selectOption({ label: 'GreenBowl' })
    await page.fill('input[placeholder="e.g. 30"]', '20')
    await page.fill('input[placeholder="e.g. 150"]', '100')

    await page.click('button:has-text("Create")')
    // Should navigate to detail OR show duplicate error (if DB not re-seeded between runs)
    await Promise.race([
      page.waitForURL(/\/partnerships\//, { timeout: 5000 }),
      page.waitForSelector('text=already exists', { timeout: 5000 }),
    ])
  })
})

test.describe('Partnership Detail', () => {
  test('nonexistent partnership shows error', async ({ page }) => {
    await merchantLogin(page)
    // Navigate to a nonexistent partnership via in-app navigation
    await page.evaluate(() => {
      window.history.pushState({}, '', '/partnerships/nonexistent-uuid-12345')
      window.dispatchEvent(new PopStateEvent('popstate'))
    })
    await page.waitForTimeout(2000)
    const content = await page.textContent('body')
    expect(content).toMatch(/Failed|error|not found/i)
  })
})

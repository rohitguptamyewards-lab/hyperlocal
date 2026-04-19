import { test, expect } from '@playwright/test'
import { merchantLogin } from './helpers'

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await merchantLogin(page)
  })

  test('displays key metric cards', async ({ page }) => {
    // Wait a bit for the analytics API to return
    await page.waitForTimeout(1000)
    const body = await page.textContent('body')
    expect(body).toContain('New Customers')
    expect(body).toContain('Network Revenue')
    expect(body).toContain('Benefit Given')
    expect(body).toContain('Net Value')
  })

  test('displays campaign and sent metrics', async ({ page }) => {
    await page.waitForTimeout(1000)
    const body = await page.textContent('body')
    expect(body).toContain('Campaigns Sent')
    expect(body).toContain('Messages Delivered')
    expect(body).toContain('Customers Sent')
    expect(body).toContain('Live Partnerships')
  })

  test('quick links navigate correctly', async ({ page }) => {
    await page.waitForTimeout(500)
    await page.locator('text=View partnerships').click()
    await expect(page).toHaveURL(/\/partnerships/)
  })
})

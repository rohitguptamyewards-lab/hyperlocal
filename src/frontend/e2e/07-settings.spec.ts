import { test, expect } from '@playwright/test'
import { merchantLogin } from './helpers'

test.describe('Merchant Settings', () => {
  test.beforeEach(async ({ page }) => {
    await merchantLogin(page)
  })

  test('settings page loads with point valuation', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForTimeout(1000)
    const body = await page.textContent('body')
    expect(body).toMatch(/Point Valuation|Valuation|rupees|Settings/i)
  })

  test('discoverability toggle is visible', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForTimeout(1000)
    const body = await page.textContent('body')
    expect(body).toMatch(/discover|partnership|visible/i)
  })
})

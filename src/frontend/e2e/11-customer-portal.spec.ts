import { test, expect } from '@playwright/test'

/** Helper: complete OTP login flow for a given phone */
async function customerLogin(page: import('@playwright/test').Page, phone: string) {
  // Clear any existing customer session
  await page.goto('/my-rewards')
  await page.evaluate(() => localStorage.removeItem('customer_token'))
  await page.goto('/my-rewards')
  await page.waitForSelector('input[type="tel"]', { timeout: 5000 })
  await page.fill('input[type="tel"]', phone)
  await page.locator('button[type="submit"]').click()
  await page.waitForSelector('text=Enter OTP', { timeout: 10000 })
  // Get OTP from dev hint — extract 6-digit code from the amber box text
  const devBox = page.locator('text=Dev mode')
  await expect(devBox).toBeVisible({ timeout: 5000 })
  const fullText = await devBox.locator('..').textContent()
  const match = fullText?.match(/(\d{6})/)
  if (!match) {
    throw new Error(`Failed to extract OTP from dev hint: "${fullText}"`)
  }
  const otpText = match[1]
  await page.fill('input[inputmode="numeric"]', otpText)
  await page.locator('button[type="submit"]').click()
  await page.waitForURL('**/my-rewards/dashboard', { timeout: 10000 })
}

test.describe('Customer Portal — OTP Login + Rewards', () => {
  test('login page loads at /my-rewards', async ({ page }) => {
    await page.goto('/my-rewards')
    await expect(page.locator('text=My')).toBeVisible()
    await expect(page.locator('input[type="tel"]')).toBeVisible()
  })

  test('customer portal is public (no merchant auth redirect)', async ({ page }) => {
    await page.goto('/my-rewards')
    await page.waitForTimeout(500)
    expect(page.url()).toContain('/my-rewards')
    expect(page.url()).not.toContain('/login')
  })

  test('send OTP shows OTP entry form', async ({ page }) => {
    await page.goto('/my-rewards')
    await page.waitForSelector('input[type="tel"]', { timeout: 5000 })
    await page.fill('input[type="tel"]', '9700001111')
    await page.locator('button[type="submit"]').click()
    await expect(page.locator('text=Enter OTP')).toBeVisible({ timeout: 10000 })
    await expect(page.locator('text=Dev mode')).toBeVisible()
  })

  test('full OTP flow → rewards dashboard with data', async ({ page }) => {
    // Use the seeded demo customer (9900001111 → normalised to 919900001111)
    await customerLogin(page, '9900001111')
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toMatch(/Total Rewards Value|No rewards yet/)
  })

  test('wrong OTP shows error', async ({ page }) => {
    await page.goto('/my-rewards')
    await page.waitForSelector('input[type="tel"]', { timeout: 5000 })
    await page.fill('input[type="tel"]', '9700002222')
    await page.locator('button[type="submit"]').click()
    await page.waitForSelector('text=Enter OTP', { timeout: 10000 })
    await page.fill('input[inputmode="numeric"]', '000000')
    await page.locator('button[type="submit"]').click()
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toMatch(/Invalid|expired/i)
  })

  test('customer with no rewards sees empty state', async ({ page }) => {
    await customerLogin(page, '9700003333')
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toContain('No rewards yet')
  })
})

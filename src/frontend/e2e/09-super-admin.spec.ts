import { test, expect } from '@playwright/test'
import { SUPER_ADMIN, superAdminLogin } from './helpers'

test.describe('Super Admin Auth', () => {
  test('SA login page loads', async ({ page }) => {
    await page.goto('/super-admin/login')
    await expect(page.locator('input[type="email"]')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
  })

  test('SA login redirects to dashboard', async ({ page }) => {
    await superAdminLogin(page)
    await expect(page).toHaveURL(/\/super-admin\/dashboard/)
  })

  test('unauthenticated SA access redirects to SA login', async ({ page }) => {
    await page.goto('/super-admin/dashboard')
    await page.waitForURL('**/super-admin/login')
  })

  test('SA and merchant auth are isolated', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'alice@brewco.com')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.goto('/super-admin/dashboard')
    await page.waitForURL('**/super-admin/login')
  })
})

test.describe('Super Admin Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await superAdminLogin(page)
  })

  test('dashboard shows platform stats', async ({ page }) => {
    await expect(page.locator('text=Platform Overview')).toBeVisible()
    await expect(page.locator('text=Total Brands')).toBeVisible()
    await expect(page.locator('text=Ecosystem Active')).toBeVisible()
    await expect(page.locator('text=Live Partnerships')).toBeVisible()
  })

  test('navigation links work', async ({ page }) => {
    await page.click('text=Manage Brands')
    await expect(page).toHaveURL(/\/super-admin\/merchants/)
    await page.goBack()
    await page.waitForSelector('text=Review eWards Requests', { timeout: 5000 })
    await page.click('text=Review eWards Requests')
    await expect(page).toHaveURL(/\/super-admin\/requests/)
  })
})

test.describe('Super Admin Merchant Management', () => {
  test.beforeEach(async ({ page }) => {
    await superAdminLogin(page)
  })

  test('merchant list loads with seeded data', async ({ page }) => {
    await page.click('text=Manage Brands')
    await page.waitForURL('**/super-admin/merchants', { timeout: 5000 })
    // Wait for a table row to appear (means data loaded)
    await expect(page.locator('table tbody tr').first()).toBeVisible({ timeout: 15000 })
    const body = await page.textContent('body')
    expect(body).toMatch(/Brew|FitZone|Bella|BookNook|GreenBowl/i)
  })

  test('merchant detail page loads on click', async ({ page }) => {
    await page.click('text=Manage Brands')
    await page.waitForURL('**/super-admin/merchants', { timeout: 5000 })
    await expect(page.locator('table tbody tr').first()).toBeVisible({ timeout: 15000 })
    // Click the Brew & Co button
    const brewBtn = page.locator('button:has-text("Brew")').first()
    await expect(brewBtn).toBeVisible({ timeout: 5000 })
    await brewBtn.click()
    await page.waitForURL(/\/super-admin\/merchants\/\d+/, { timeout: 5000 })
    await page.waitForTimeout(1500)
    const body = await page.textContent('body')
    expect(body).toMatch(/Brew|credit|Credit|WhatsApp/i)
  })
})

test.describe('Super Admin Integration Requests', () => {
  test('requests page loads', async ({ page }) => {
    await superAdminLogin(page)
    await page.click('text=Review eWards Requests')
    await page.waitForURL('**/super-admin/requests', { timeout: 5000 })
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toMatch(/eWards|Integration|request|No|pending/i)
  })
})

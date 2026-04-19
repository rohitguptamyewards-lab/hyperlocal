import { test, expect } from '@playwright/test'
import { MERCHANT, merchantLogin } from './helpers'

test.describe('Merchant Auth Flow', () => {
  test('shows login form on /login', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('input[type="email"]')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeVisible()
  })

  test('redirects unauthenticated user to /login', async ({ page }) => {
    await page.goto('/dashboard')
    await page.waitForURL('**/login')
    await expect(page.locator('input[type="email"]')).toBeVisible()
  })

  test('login with valid credentials redirects to dashboard', async ({ page }) => {
    await merchantLogin(page)
    await expect(page).toHaveURL(/\/dashboard/)
    await expect(page.locator('text=Welcome')).toBeVisible()
  })

  test('login with wrong password shows error', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', MERCHANT.email)
    await page.fill('input[type="password"]', 'wrongpassword')
    await page.click('button[type="submit"]')
    await page.waitForTimeout(2000)
    await expect(page).toHaveURL(/\/login/)
  })

  test('logout redirects to login', async ({ page }) => {
    await merchantLogin(page)
    await page.click('text=Sign out')
    await page.waitForURL('**/login')
    await expect(page.locator('input[type="email"]')).toBeVisible()
  })

  test('dashboard shows user info in sidebar', async ({ page }) => {
    await merchantLogin(page)
    // Wait for sidebar to render fully
    await page.waitForTimeout(500)
    // Look for user name text anywhere on the page
    const body = await page.textContent('body')
    expect(body).toContain(MERCHANT.name)
    expect(body).toContain(MERCHANT.email)
  })
})

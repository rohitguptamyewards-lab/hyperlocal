import { type Page, expect } from '@playwright/test'

/** Merchant test credentials from DevelopmentSeeder */
export const MERCHANT = {
  email: 'alice@brewco.com',
  password: 'password',
  name: 'Alice (Brew & Co Admin)',
  merchantName: 'Brew & Co',
}

export const MERCHANT_B = {
  email: 'bob@fitzone.com',
  password: 'password',
  name: 'Bob (FitZone Admin)',
  merchantName: 'FitZone Gyms',
}

/** Super Admin credentials from SuperAdminSeeder */
export const SUPER_ADMIN = {
  email: 'admin@hyperlocal.internal',
  password: 'changeme123',
  name: 'Platform Admin',
}

/** Login as a merchant via the /login page and wait for dashboard to fully load */
export async function merchantLogin(page: Page, email = MERCHANT.email, password = MERCHANT.password) {
  await page.goto('/login')
  await page.waitForSelector('input[type="email"]')
  await page.fill('input[type="email"]', email)
  await page.fill('input[type="password"]', password)
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard', { timeout: 10000 })
  // Wait for the dashboard to actually render content
  await page.waitForSelector('text=Welcome', { timeout: 10000 })
}

/** Login as super admin via /super-admin/login */
export async function superAdminLogin(page: Page) {
  await page.goto('/super-admin/login')
  await page.waitForSelector('input[type="email"]')
  await page.fill('input[type="email"]', SUPER_ADMIN.email)
  await page.fill('input[type="password"]', SUPER_ADMIN.password)
  await page.click('button[type="submit"]')
  await page.waitForURL('**/super-admin/dashboard', { timeout: 10000 })
  await page.waitForSelector('text=Platform Overview', { timeout: 10000 })
}

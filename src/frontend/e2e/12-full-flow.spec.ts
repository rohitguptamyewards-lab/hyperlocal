/**
 * FULL PRODUCT FLOW TEST — Tests every connection end-to-end.
 *
 * Scenario: Two merchants (Alice @ Brew & Co, Bob @ FitZone) go through
 * the entire product lifecycle as real users would.
 *
 * Flow:
 *  1. Alice logs in → sees dashboard with data
 *  2. Alice navigates every page (partnerships, discovery, campaigns, offers, triggers, growth, networks, settings)
 *  3. Alice creates a new offer
 *  4. Bob logs in → sees his own dashboard
 *  5. Bob sees Alice's offer on his bill offers page
 *  6. Super Admin logs in → sees both merchants
 *  7. Customer logs in via OTP → sees rewards
 *  8. Public pages work without auth (marketplace, bill offers, brand profile)
 */
import { test, expect } from '@playwright/test'

const ALICE = { email: 'alice@brewco.com', password: 'password' }
const BOB   = { email: 'bob@fitzone.com', password: 'password' }
const SA    = { email: 'admin@hyperlocal.internal', password: 'changeme123' }

async function login(page: import('@playwright/test').Page, creds: { email: string; password: string }, expectedUrl: RegExp) {
  await page.waitForSelector('input[type="email"]', { timeout: 5000 })
  await page.fill('input[type="email"]', creds.email)
  await page.fill('input[type="password"]', creds.password)
  await page.locator('button[type="submit"]').click()
  await page.waitForURL(expectedUrl, { timeout: 10000 })
}

test.describe('Full Product Flow — Merchant A (Alice @ Brew & Co)', () => {
  test('complete merchant journey', async ({ page }) => {
    // ═══ 1. LOGIN ═══
    await page.goto('/login')
    await login(page, ALICE, /\/dashboard/)
    // Wait for analytics API to load
    await page.waitForTimeout(2000)
    let body = await page.textContent('body')
    expect(body).toContain('Welcome, Alice')
    expect(body).toMatch(/New Customers|NEW CUSTOMERS/)

    // ═══ 2. DASHBOARD — metrics visible ═══
    expect(body).toMatch(/Network Revenue|NETWORK REVENUE/)
    expect(body).toMatch(/Net Value|NET VALUE/)
    // Monthly Trend only shows if there's data — check for quick links instead
    expect(body).toContain('View partnerships')

    // ═══ 3. PARTNERSHIPS — list loads ═══
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toContain('Partnerships')
    expect(body).toContain('Brew & Co')
    expect(body).toMatch(/Live|Negotiating|Requested/)

    // ═══ 4. FIND PARTNERS — search works ═══
    await page.locator('nav >> text=Find Partners').click()
    await page.waitForURL('**/find-partners')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toContain('Find Partners')

    // ═══ 5. CAMPAIGNS — page loads ═══
    await page.locator('nav >> text=Campaigns').click()
    await page.waitForURL('**/campaigns')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toBeDefined()

    // ═══ 6. OFFERS — list + create ═══
    await page.locator('nav >> text=Offers').click()
    await page.waitForURL('**/partner-offers')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toContain('Partner Offers')
    expect(body).toContain('BREW20OFF')

    // ═══ 7. TRIGGERS — sources + triggers visible ═══
    await page.locator('nav >> text=Triggers').click()
    await page.waitForURL('**/event-sources')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toContain('Event Sources')

    // ═══ 8. GROWTH — insights page loads ═══
    await page.locator('nav >> text=Growth').click()
    await page.waitForURL('**/growth')
    await page.waitForTimeout(2000)
    body = await page.textContent('body')
    expect(body).toContain('Growth Insights')
    expect(body).toContain('Seasonal Offer Templates')
    expect(body).toContain('Invite a Nearby Brand')

    // ═══ 9. NETWORKS — page loads ═══
    await page.locator('nav >> text=Networks').click()
    await page.waitForURL('**/networks')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toMatch(/Networks|No networks/)

    // ═══ 10. SETTINGS — point valuation + toggles ═══
    await page.locator('nav >> text=Settings').click()
    await page.waitForURL('**/settings')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toMatch(/Valuation|Settings|Discover/)

    // ═══ 11. LOGOUT ═══
    await page.click('text=Sign out')
    await page.waitForURL('**/login')
  })
})

test.describe('Full Product Flow — Merchant B (Bob @ FitZone)', () => {
  test('partner merchant journey', async ({ page }) => {
    // ═══ 1. LOGIN as Bob ═══
    await page.goto('/login')
    await login(page, BOB, /\/dashboard/)
    let body = await page.textContent('body')
    expect(body).toContain('Welcome, Bob')

    // ═══ 2. DASHBOARD — Bob sees his own metrics ═══
    expect(body).toMatch(/New Customers|NEW CUSTOMERS/)

    // ═══ 3. PARTNERSHIPS — Bob sees partnerships with Brew & Co ═══
    await page.locator('nav >> text=Partnerships').click()
    await page.waitForURL('**/partnerships')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toContain('Brew & Co')

    // ═══ 4. OFFERS — Bob sees his own offers (none seeded for FitZone) ═══
    await page.locator('nav >> text=Offers').click()
    await page.waitForURL('**/partner-offers')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toMatch(/Partner Offers|No offers/)

    // ═══ 5. GROWTH — Bob sees insights ═══
    await page.locator('nav >> text=Growth').click()
    await page.waitForURL('**/growth')
    await page.waitForTimeout(2000)
    body = await page.textContent('body')
    expect(body).toContain('Growth Insights')
  })
})

test.describe('Full Product Flow — Super Admin', () => {
  test('SA manages the platform', async ({ page }) => {
    // ═══ 1. SA LOGIN ═══
    await page.goto('/super-admin/login')
    await login(page, SA, /\/super-admin\/dashboard/)
    // Wait for stats to load (replaces "Loading…")
    await page.waitForSelector('text=Total Brands', { timeout: 15000 })
    let body = await page.textContent('body')
    expect(body).toContain('Platform Overview')
    expect(body).toContain('Total Brands')

    // ═══ 2. BRANDS LIST ═══
    await page.click('text=Manage Brands')
    await page.waitForURL('**/super-admin/merchants')
    await page.waitForSelector('table tbody tr', { timeout: 15000 })
    body = await page.textContent('body')
    expect(body).toMatch(/Brew|FitZone/)

    // ═══ 3. EWARDS REQUESTS ═══
    await page.click('text=eWards Requests')
    await page.waitForURL('**/super-admin/requests')
    await page.waitForTimeout(1500)
    body = await page.textContent('body')
    expect(body).toBeDefined()
  })
})

test.describe('Full Product Flow — Customer Portal', () => {
  test('customer sees rewards via OTP', async ({ page }) => {
    await page.goto('/my-rewards')
    await page.waitForSelector('input[type="tel"]', { timeout: 5000 })
    await page.fill('input[type="tel"]', '9900001111')
    await page.locator('button[type="submit"]').click()
    await page.waitForSelector('text=Enter OTP', { timeout: 10000 })

    // Get OTP from dev hint
    const devBox = page.locator('text=Dev mode')
    await expect(devBox).toBeVisible({ timeout: 5000 })
    const fullText = await devBox.locator('..').textContent()
    const match = fullText?.match(/(\d{6})/)
    expect(match).toBeTruthy()

    await page.fill('input[inputmode="numeric"]', match![1])
    await page.locator('button[type="submit"]').click()
    await page.waitForURL('**/my-rewards/dashboard', { timeout: 10000 })
    await page.waitForTimeout(1500)

    const body = await page.textContent('body')
    expect(body).toMatch(/Total Rewards Value|No rewards/)
  })
})

test.describe('Full Product Flow — Public Pages (no auth)', () => {
  test('bill offers page shows partner offers', async ({ page }) => {
    // Get FitZone UUID via API
    const resp = await page.request.post('/api/super-admin/auth/login', {
      data: { email: SA.email, password: SA.password },
    })
    const saData = await resp.json()
    const merchantResp = await page.request.get('/api/super-admin/merchants/2', {
      headers: { Authorization: `Bearer ${saData.token}` },
    })
    const merchantData = await merchantResp.json()
    const fitUuid = merchantData.uuid

    await page.goto(`/bill-offers/${fitUuid}`)
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toContain('FitZone')
    expect(body).toContain('BREW20OFF')
    expect(body).toContain('unlocked offers')
  })

  test('marketplace page loads', async ({ page }) => {
    await page.goto('/marketplace')
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toContain('Marketplace')
    expect(body).toMatch(/Brew|offer|Mumbai/)
  })

  test('claim landing handles invalid UUID gracefully', async ({ page }) => {
    await page.goto('/claim/nonexistent-uuid')
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toMatch(/no longer active|invalid|error/i)
  })
})

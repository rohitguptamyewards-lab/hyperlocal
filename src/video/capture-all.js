/**
 * Full 3-layer screenshot capture for demo video.
 * Captures: SuperAdmin, Merchant Dashboard, Customer Portal.
 * Run: node capture-all.js (requires both servers running)
 */
const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const OUT = path.join(__dirname, 'public/screenshots/final');
const BASE = 'http://localhost:3000';
const API = 'http://localhost:8000/api';

if (!fs.existsSync(OUT)) fs.mkdirSync(OUT, { recursive: true });

async function capture(page, name, waitMs = 1500) {
  await new Promise(r => setTimeout(r, waitMs));
  await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: false });
  console.log(`  ✅ ${name}.png`);
}

async function scrollAndCapture(page, name, scrollY = 500) {
  await page.evaluate((y) => {
    const main = document.querySelector('main') || document.documentElement;
    main.scrollTo(0, y);
  }, scrollY);
  await capture(page, name, 600);
}

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 800 });

  // ═══════════════════════════════════════════════════════════
  // LAYER 1: SUPER ADMIN
  // ═══════════════════════════════════════════════════════════
  console.log('\n🔷 LAYER 1: Super Admin');

  // SA Login
  await page.goto(`${BASE}/super-admin/login`, { waitUntil: 'networkidle2' });
  await capture(page, 'SA-01-login');

  // SA Login → Dashboard
  await page.type('input[type="email"]', 'admin@hyperlocal.internal');
  await page.type('input[type="password"]', 'changeme123');
  await page.click('button[type="submit"]');
  await new Promise(r => setTimeout(r, 3000));
  await capture(page, 'SA-02-dashboard');

  // SA Brands list
  await page.click('a[href="/super-admin/merchants"]');
  await new Promise(r => setTimeout(r, 3000));
  await capture(page, 'SA-03-brands');

  // SA Brand detail (click first brand)
  const brandBtn = await page.$('table tbody button');
  if (brandBtn) {
    await brandBtn.click();
    await new Promise(r => setTimeout(r, 2000));
    await capture(page, 'SA-04-brand-detail');
  }

  // SA eWards Requests
  await page.goto(`${BASE}/super-admin/requests`, { waitUntil: 'networkidle2' });
  await capture(page, 'SA-05-ewards-requests');

  // ═══════════════════════════════════════════════════════════
  // LAYER 2: MERCHANT DASHBOARD
  // ═══════════════════════════════════════════════════════════
  console.log('\n🔷 LAYER 2: Merchant Dashboard (Brew & Co)');

  // Merchant Login
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle2' });
  await capture(page, 'M-01-login');

  await page.type('input[type="email"]', 'alice@brewco.com');
  await page.type('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await new Promise(r => setTimeout(r, 3000));

  // Dashboard
  await capture(page, 'M-02-dashboard');
  await scrollAndCapture(page, 'M-02b-dashboard-scroll', 500);
  await scrollAndCapture(page, 'M-02c-dashboard-scroll2', 1000);

  // Partnerships list
  await page.goto(`${BASE}/partnerships`, { waitUntil: 'networkidle2' });
  await capture(page, 'M-03-partnerships');

  // Create partnership modal
  const newBtn = await page.$('button');
  if (newBtn) {
    const text = await page.evaluate(el => el.textContent, newBtn);
    // Find the "New partnership" button specifically
    const buttons = await page.$$('button');
    for (const b of buttons) {
      const t = await page.evaluate(el => el.textContent.trim(), b);
      if (t === 'New partnership') { await b.click(); break; }
    }
    await new Promise(r => setTimeout(r, 1500));
    await capture(page, 'M-03b-create-modal');
    // Close modal
    await page.keyboard.press('Escape');
    await new Promise(r => setTimeout(r, 300));
  }

  // Partnership detail (click first partnership)
  const partnershipLink = await page.$('div[class*="cursor-pointer"]');
  if (partnershipLink) {
    await partnershipLink.click();
    await new Promise(r => setTimeout(r, 2000));
    await capture(page, 'M-04-partnership-detail');
    await scrollAndCapture(page, 'M-04b-partnership-scroll', 600);
  }

  // Find Partners
  await page.goto(`${BASE}/find-partners`, { waitUntil: 'networkidle2' });
  await new Promise(r => setTimeout(r, 1500));
  // Trigger search
  await page.evaluate(() => {
    document.querySelectorAll('button').forEach(b => {
      if (b.textContent.trim() === 'Search') b.click();
    });
  });
  await new Promise(r => setTimeout(r, 2000));
  await capture(page, 'M-05-find-partners');

  // Campaigns
  await page.goto(`${BASE}/campaigns`, { waitUntil: 'networkidle2' });
  await capture(page, 'M-06-campaigns');

  // Networks
  await page.goto(`${BASE}/networks`, { waitUntil: 'networkidle2' });
  await capture(page, 'M-07-networks');

  // Settings
  await page.goto(`${BASE}/settings`, { waitUntil: 'networkidle2' });
  await capture(page, 'M-08-settings');

  // ═══════════════════════════════════════════════════════════
  // LAYER 3: CUSTOMER PORTAL
  // ═══════════════════════════════════════════════════════════
  console.log('\n🔷 LAYER 3: Customer Portal');

  // Clear all auth tokens before customer section
  await page.evaluate(() => {
    localStorage.removeItem('token');
    localStorage.removeItem('sa_token');
    localStorage.removeItem('customer_token');
  });

  // Customer login page
  await page.goto(`${BASE}/my-rewards`, { waitUntil: 'networkidle2' });
  await new Promise(r => setTimeout(r, 1000));
  await capture(page, 'C-01-login');

  // Enter phone and get OTP
  await page.evaluate(() => {
    const input = document.querySelector('input[type="tel"]');
    const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(input, '9900001111');
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await new Promise(r => setTimeout(r, 500));
  // Click Get OTP — use evaluate to find and click the button
  await page.evaluate(() => {
    const btn = Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Get OTP'));
    if (btn) { btn.disabled = false; btn.click(); }
  });
  await new Promise(r => setTimeout(r, 3000));
  await capture(page, 'C-02-otp');

  // Extract OTP from dev hint — find the 6-digit code anywhere on the page
  const otpText = await page.evaluate(() => {
    const match = document.body.innerText.match(/OTP:\s*(\d{6})/);
    return match ? match[1] : null;
  });

  console.log(`  OTP extracted: "${otpText}"`);
  if (otpText && /^\d{6}$/.test(otpText)) {
    // Fill OTP using evaluate to trigger Vue reactivity
    await page.evaluate((code) => {
      const input = document.querySelector('input[inputmode="numeric"]');
      if (input) {
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        setter.call(input, code);
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }, otpText);
    await new Promise(r => setTimeout(r, 500));
    // Click Verify button
    await page.evaluate(() => {
      const btn = Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Verify'));
      if (btn) { btn.disabled = false; btn.click(); }
    });
    await new Promise(r => setTimeout(r, 3000));

    // Rewards dashboard
    await capture(page, 'C-03-rewards');

    // Expand first brand
    const brandCards = await page.$$('button');
    for (const b of brandCards) {
      const t = await page.evaluate(el => el.textContent.trim(), b);
      if (t.includes('Brew')) { await b.click(); break; }
    }
    await new Promise(r => setTimeout(r, 500));
    await capture(page, 'C-04-rewards-expanded');
  } else {
    console.log('  ⚠️ Could not extract OTP — skipping rewards capture');
  }

  await browser.close();

  // Summary
  const files = fs.readdirSync(OUT).filter(f => f.endsWith('.png'));
  console.log(`\n✅ Captured ${files.length} screenshots in ${OUT}`);
  files.forEach(f => console.log(`   ${f}`));
})();

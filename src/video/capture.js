const puppeteer = require('puppeteer');
const path = require('path');

const OUT = '/Volumes/Abhishek SSD/Hyper local/src/video/public/screenshots';
const BASE = 'http://localhost:3000';

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 720 });

  // Login
  console.log('Logging in...');
  await page.goto(BASE, { waitUntil: 'networkidle2' });
  await page.type('input[placeholder="you@merchant.com"]', 'alice@brewco.com');
  await page.type('input[type="password"]', 'password');
  await page.click('button');
  await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
  await new Promise(r => setTimeout(r, 2000));
  console.log('Logged in. URL:', page.url());

  // Capture each page
  const pages = [
    { path: '/dashboard', name: '01-dashboard' },
    { path: '/find-partners', name: '02-find-partners' },
    { path: '/partnerships', name: '03-partnerships' },
    { path: '/partnerships/e6e1e866-46fa-4ce2-ad26-55cf5ec522d5', name: '04-partnership-detail' },
    { path: '/campaigns', name: '05-campaigns' },
    { path: '/settings', name: '06-settings' },
    { path: '/networks', name: '07-networks' },
    { path: '/promo-codes', name: '08-promo-codes' },
  ];

  for (const p of pages) {
    console.log(`Capturing ${p.name}...`);
    await page.goto(BASE + p.path, { waitUntil: 'networkidle2' }).catch(() => {});
    await new Promise(r => setTimeout(r, 1500));
    await page.screenshot({ path: path.join(OUT, p.name + '.png'), fullPage: false });
    console.log(`  Saved ${p.name}.png`);
  }

  // Also capture partnership detail scrolled down
  console.log('Capturing partnership detail scrolled...');
  await page.goto(BASE + '/partnerships/e6e1e866-46fa-4ce2-ad26-55cf5ec522d5', { waitUntil: 'networkidle2' }).catch(() => {});
  await new Promise(r => setTimeout(r, 1500));
  await page.evaluate(() => window.scrollTo(0, 500));
  await new Promise(r => setTimeout(r, 500));
  await page.screenshot({ path: path.join(OUT, '04b-partnership-terms.png'), fullPage: false });
  console.log('  Saved 04b-partnership-terms.png');

  // Find partners with search results
  console.log('Capturing find partners with results...');
  await page.goto(BASE + '/find-partners', { waitUntil: 'networkidle2' }).catch(() => {});
  await new Promise(r => setTimeout(r, 1000));
  await page.evaluate(() => {
    const inp = document.querySelector('input[placeholder="e.g. Mumbai"]');
    if (inp) {
      const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
      setter.call(inp, 'Mumbai');
      inp.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
  await new Promise(r => setTimeout(r, 300));
  await page.evaluate(() => {
    document.querySelectorAll('button').forEach(b => { if (b.textContent.trim() === 'Search') b.click(); });
  });
  await new Promise(r => setTimeout(r, 2000));
  await page.screenshot({ path: path.join(OUT, '02b-find-results.png'), fullPage: false });
  console.log('  Saved 02b-find-results.png');

  await browser.close();
  console.log('Done!');
})();

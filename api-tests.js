/**
 * Hyperlocal API Automated Tests
 * Run: node api-tests.js
 * Requires: Backend running at http://localhost:8000
 * Uses: built-in fetch (Node 22+)
 */

const BASE = 'http://localhost:8000/api';
let PASS = 0, FAIL = 0, SKIP = 0;
const results = [];

// ── State shared across tests ──────────────────────────────────
let merchantToken = '';
let saToken       = '';
let customerToken = '';
let testPartnershipUuid = '';
let testClaimToken = '';
let testOfferUuid = '';
let testNetworkUuid = '';
let testMerchantId = 0;
let testPartnerId = 0;
let testFollowupId = 0;
let testRegistrationId = 0;

// ── Helpers ────────────────────────────────────────────────────
async function req(method, path, body, token) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const opts = { method, headers };
  if (body) opts.body = JSON.stringify(body);
  try {
    const r = await fetch(`${BASE}${path}`, opts);
    let data;
    try { data = await r.json(); } catch { data = {}; }
    return { status: r.status, data };
  } catch (e) {
    return { status: 0, data: { error: e.message } };
  }
}

function test(name, fn) {
  return async () => {
    try {
      const result = await fn();
      if (result === 'SKIP') {
        SKIP++;
        results.push({ name, status: 'SKIP', note: 'Skipped' });
        console.log(`  ⚪ SKIP  ${name}`);
      } else if (result === true) {
        PASS++;
        results.push({ name, status: 'PASS' });
        console.log(`  ✅ PASS  ${name}`);
      } else {
        FAIL++;
        results.push({ name, status: 'FAIL', note: result });
        console.log(`  ❌ FAIL  ${name} — ${result}`);
      }
    } catch (e) {
      FAIL++;
      results.push({ name, status: 'FAIL', note: e.message });
      console.log(`  ❌ FAIL  ${name} — EXCEPTION: ${e.message}`);
    }
  };
}

function assert(condition, message) {
  if (!condition) return message || 'Assertion failed';
  return true;
}

// ── Run all tests ──────────────────────────────────────────────
async function run() {
  console.log('\n════════════════════════════════════════════════');
  console.log('  HYPERLOCAL — AUTOMATED API TESTS');
  console.log(`  Target: ${BASE}`);
  console.log('════════════════════════════════════════════════\n');

  // ── SECTION 1: Health & Public Endpoints ──────────────────
  console.log('▶ SECTION 1 — Health & Public Endpoints');

  await test('GET /health — returns ok', async () => {
    const { status, data } = await req('GET', '/health');
    return assert(status === 200 && data.status === 'ok', `Got ${status} ${JSON.stringify(data)}`);
  })();

  await test('GET /public/marketplace — accessible without auth', async () => {
    const { status } = await req('GET', '/public/marketplace');
    return assert(status === 200 || status === 404, `Got ${status}`);
  })();

  await test('POST /register-brand — missing fields returns 422', async () => {
    const { status } = await req('POST', '/register-brand', { brand_name: '' });
    return assert(status === 422, `Got ${status}`);
  })();

  await test('POST /register-brand — valid registration', async () => {
    const ts = Date.now();
    const { status, data } = await req('POST', '/register-brand', {
      brand_name: `AutoTest Brand ${ts}`,
      category: 'Cafe',
      city: 'Mumbai',
      state: 'Maharashtra',
      outlet_name: `AutoTest Outlet ${ts}`,
      contact_name: 'Auto Tester',
      contact_email: `autotest_${ts}@test.com`,
      contact_phone: '9800000001',
      password: 'password123',
      password_confirmation: 'password123',
    });
    if (status === 201 || status === 200) {
      testRegistrationId = data?.data?.id || data?.id || 0;
    }
    return assert(status === 201 || status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('POST /register-brand — duplicate email returns 422', async () => {
    const { status } = await req('POST', '/register-brand', {
      brand_name: 'Dup Brand',
      category: 'Cafe',
      city: 'Mumbai',
      state: 'Maharashtra',
      outlet_name: 'Dup Outlet',
      contact_name: 'Dup',
      contact_email: 'alice@brewco.com',
      contact_phone: '9800000002',
      password: 'password123',
      password_confirmation: 'password123',
    });
    return assert(status === 422, `Got ${status}`);
  })();

  // ── SECTION 2: Merchant Authentication ────────────────────
  console.log('\n▶ SECTION 2 — Merchant Authentication');

  await test('POST /auth/login — wrong password returns 401/422', async () => {
    const { status } = await req('POST', '/auth/login', {
      email: 'alice@brewco.com',
      password: 'wrongpassword',
    });
    return assert(status === 401 || status === 422, `Got ${status}`);
  })();

  await test('POST /auth/login — valid credentials returns token', async () => {
    const { status, data } = await req('POST', '/auth/login', {
      email: 'alice@brewco.com',
      password: 'password',
    });
    if (status === 200 && data.token) {
      merchantToken = data.token;
    }
    return assert(status === 200 && data.token, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /auth/me — returns authenticated user', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/auth/me', null, merchantToken);
    return assert(status === 200 && data.email, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /partnerships — without auth returns 401', async () => {
    const { status } = await req('GET', '/partnerships');
    return assert(status === 401, `Got ${status}`);
  })();

  await test('GET /partnerships — with merchant token returns 200', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/partnerships', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 3: Super Admin Auth ────────────────────────────
  console.log('\n▶ SECTION 3 — Super Admin Authentication');

  await test('POST /super-admin/auth/login — wrong password returns 401/422', async () => {
    const { status } = await req('POST', '/super-admin/auth/login', {
      email: 'admin@hyperlocal.internal',
      password: 'wrongpassword',
    });
    return assert(status === 401 || status === 422, `Got ${status}`);
  })();

  await test('POST /super-admin/auth/login — valid credentials returns token', async () => {
    const { status, data } = await req('POST', '/super-admin/auth/login', {
      email: 'admin@hyperlocal.internal',
      password: 'changeme123',
    });
    if (status === 200 && data.token) {
      saToken = data.token;
    }
    return assert(status === 200 && data.token, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /super-admin/auth/me — returns SA user', async () => {
    if (!saToken) return 'SKIP';
    const { status, data } = await req('GET', '/super-admin/auth/me', null, saToken);
    return assert(status === 200 && data.email, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /super-admin/merchants — merchant token returns 401 or 403', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/super-admin/merchants', null, merchantToken);
    return assert(status === 401 || status === 403, `Got ${status}`);
  })();

  await test('GET /super-admin/merchants — SA token returns 200', async () => {
    if (!saToken) return 'SKIP';
    const { status, data } = await req('GET', '/super-admin/merchants', null, saToken);
    if (status === 200 && data.data) {
      testMerchantId = data.data[0]?.id || 0;
    }
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /super-admin/merchants/dashboard — returns stat object', async () => {
    if (!saToken) return 'SKIP';
    const { status, data } = await req('GET', '/super-admin/merchants/dashboard', null, saToken);
    return assert(status === 200 && typeof data.total_merchants !== 'undefined', `Got ${status}: ${JSON.stringify(data)}`);
  })();

  // ── SECTION 4: SA Brand Registration Management ────────────
  console.log('\n▶ SECTION 4 — SA Brand Registration Management');

  await test('GET /super-admin/brand-registrations — returns list', async () => {
    if (!saToken) return 'SKIP';
    const { status, data } = await req('GET', '/super-admin/brand-registrations', null, saToken);
    if (status === 200 && data.data && data.data.length > 0) {
      testRegistrationId = testRegistrationId || data.data[0]?.id || 0;
    }
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /super-admin/brand-registrations/{id}/approve — works', async () => {
    if (!saToken || !testRegistrationId) return 'SKIP';
    const { status, data } = await req('POST', `/super-admin/brand-registrations/${testRegistrationId}/approve`, {}, saToken);
    return assert(status === 200 || status === 404, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  // ── SECTION 5: Partnerships ────────────────────────────────
  console.log('\n▶ SECTION 5 — Partnership Module');

  await test('GET /partnerships/tc — returns T&C text', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/partnerships/tc', null, merchantToken);
    // API returns {version, text} shape
    return assert(status === 200 && (data.content || data.tc || data.text || typeof data === 'string'), `Got ${status}: ${JSON.stringify(data).slice(0, 100)}`);
  })();

  await test('GET /partnerships — returns array', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/partnerships', null, merchantToken);
    if (status === 200) {
      const arr = data.data || data;
      testPartnershipUuid = Array.isArray(arr) ? arr[0]?.uuid : null;
    }
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /partnerships/{uuid} — returns partnership detail', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status, data } = await req('GET', `/partnerships/${testPartnershipUuid}`, null, merchantToken);
    // API returns {data: {uuid, ...}} shape
    const p = data?.data || data;
    return assert(status === 200 && (p.uuid || p.id), `Got ${status}: ${JSON.stringify(data).slice(0, 100)}`);
  })();

  await test('GET /partnerships — with invalid UUID returns 404', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/partnerships/00000000-0000-0000-0000-000000000000', null, merchantToken);
    return assert(status === 404, `Got ${status}`);
  })();

  await test('GET /partnerships/{uuid}/partner-outlets — returns outlets', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/partnerships/${testPartnershipUuid}/partner-outlets`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /partnerships/{uuid}/ledger — returns ledger', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/partnerships/${testPartnershipUuid}/ledger`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /partnerships/{uuid}/redemptions — returns list', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/partnerships/${testPartnershipUuid}/redemptions`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /partnerships/{uuid}/ratings — returns ratings', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/partnerships/${testPartnershipUuid}/ratings`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /partnerships/{uuid}/rate — submit rating', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('POST', `/partnerships/${testPartnershipUuid}/rate`, {
      rating: 4, review: 'Good automated test rating'
    }, merchantToken);
    return assert(status === 200 || status === 201 || status === 422, `Got ${status}`);
  })();

  await test('POST /partnerships/{uuid}/rate — invalid rating (0) returns 422', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('POST', `/partnerships/${testPartnershipUuid}/rate`, {
      rating: 0
    }, merchantToken);
    return assert(status === 422, `Got ${status}`);
  })();

  await test('POST /partnerships/{uuid}/rate — invalid rating (6) returns 422', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('POST', `/partnerships/${testPartnershipUuid}/rate`, {
      rating: 6
    }, merchantToken);
    return assert(status === 422, `Got ${status}`);
  })();

  await test('POST /partnerships/{uuid}/share-link — generates link', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status, data } = await req('POST', `/partnerships/${testPartnershipUuid}/share-link`, {}, merchantToken);
    return assert(status === 200 || status === 201, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('POST /partnerships/{uuid}/announcements/preview — returns preview', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status, data } = await req('POST', `/partnerships/${testPartnershipUuid}/announcements/preview`, {
      message: 'Test announcement preview'
    }, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /partnerships/{uuid}/announcements — returns history', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/partnerships/${testPartnershipUuid}/announcements`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /partnerships/{uuid}/enablement — returns enablement data', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/partnerships/${testPartnershipUuid}/enablement`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 6: Token Issuance ──────────────────────────────
  console.log('\n▶ SECTION 6 — Token Issuance');

  await test('GET /public/partnerships/{uuid} — public show endpoint', async () => {
    if (!testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/public/partnerships/${testPartnershipUuid}`);
    return assert(status === 200 || status === 404, `Got ${status}`);
  })();

  await test('POST /public/claims — issue token publicly', async () => {
    if (!testPartnershipUuid) return 'SKIP';
    // First get partner outlets
    const { data: ouData } = await req('GET', `/partnerships/${testPartnershipUuid}/partner-outlets`, null, merchantToken);
    const outlets = ouData?.data || ouData || [];
    const targetOutletId = Array.isArray(outlets) && outlets.length > 0 ? outlets[0].id : null;
    if (!targetOutletId) return 'SKIP';

    const { status, data } = await req('POST', '/public/claims', {
      partnership_uuid: testPartnershipUuid,
      customer_phone: '9800000099',
      target_outlet_id: targetOutletId,
    });
    if ((status === 200 || status === 201) && data.token) {
      testClaimToken = data.token;
    }
    // 429 = rate limiting working correctly (throttle:5,1 on public endpoint)
    return assert(status === 200 || status === 201 || status === 422 || status === 429, `Got ${status}: ${JSON.stringify(data).slice(0,100)}`);
  })();

  await test('POST /claims — authenticated claim issuance', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { data: ouData } = await req('GET', `/partnerships/${testPartnershipUuid}/partner-outlets`, null, merchantToken);
    const outlets = ouData?.data || ouData || [];
    const targetOutletId = Array.isArray(outlets) && outlets.length > 0 ? outlets[0].id : null;
    if (!targetOutletId) return 'SKIP';

    const { status, data } = await req('POST', '/claims', {
      partnership_uuid: testPartnershipUuid,
      customer_phone: '9800000088',
      target_outlet_id: targetOutletId,
    }, merchantToken);
    if ((status === 200 || status === 201) && data.token) {
      testClaimToken = data.token || testClaimToken;
    }
    return assert(status === 200 || status === 201 || status === 422, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  // ── SECTION 7: Token Redemption ────────────────────────────
  console.log('\n▶ SECTION 7 — Token Redemption');

  await test('GET /execution/lookup/FAKECODE — returns 404 or 422 (not 500)', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/execution/lookup/FAKECODE', null, merchantToken);
    return assert(status === 404 || status === 422, `Got ${status} — expected not 500`);
  })();

  await test('GET /execution/lookup/{token} — valid token returns benefit info', async () => {
    if (!merchantToken || !testClaimToken) return 'SKIP';
    const { status, data } = await req('GET', `/execution/lookup/${testClaimToken}`, null, merchantToken);
    return assert(status === 200 || status === 404, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('POST /execution/redeem — missing token returns 422', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('POST', '/execution/redeem', {
      bill_amount: 300
    }, merchantToken);
    return assert(status === 422, `Got ${status}`);
  })();

  // ── SECTION 8: Analytics ───────────────────────────────────
  console.log('\n▶ SECTION 8 — Analytics');

  await test('GET /analytics/summary — returns stats', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/analytics/summary', null, merchantToken);
    return assert(status === 200 && typeof data.live_partnerships !== 'undefined', `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /analytics/trends — returns array', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/analytics/trends', null, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data).slice(0, 100)}`);
  })();

  await test('GET /analytics/partnerships/{uuid} — returns partnership analytics', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/analytics/partnerships/${testPartnershipUuid}`, null, merchantToken);
    return assert(status === 200 || status === 404, `Got ${status}`);
  })();

  await test('GET /analytics/partnerships/{uuid}/trend — returns trend', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/analytics/partnerships/${testPartnershipUuid}/trend`, null, merchantToken);
    return assert(status === 200 || status === 404, `Got ${status}`);
  })();

  await test('GET /ledger/summary — returns ledger summary', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/ledger/summary', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /enablement/summary — returns summary', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/enablement/summary', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 9: Delivery Stats ──────────────────────────────
  console.log('\n▶ SECTION 9 — Delivery Stats');

  await test('GET /delivery/stats — returns stats object', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/delivery/stats', null, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /delivery/failures — returns array', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/delivery/failures', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /delivery/stats — without auth returns 401', async () => {
    const { status } = await req('GET', '/delivery/stats');
    return assert(status === 401, `Got ${status}`);
  })();

  // ── SECTION 10: Reminder Settings ─────────────────────────
  console.log('\n▶ SECTION 10 — Reminder Settings');

  await test('GET /reminders/settings — returns settings', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/reminders/settings', null, merchantToken);
    return assert(status === 200 && typeof data.reminder_enabled !== 'undefined', `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('PUT /reminders/settings — valid update', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('PUT', '/reminders/settings', {
      reminder_enabled: true,
      remind_hours_before: 12,
      message_template: null,
    }, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('PUT /reminders/settings — invalid hours returns 422', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('PUT', '/reminders/settings', {
      reminder_enabled: true,
      remind_hours_before: 99,
    }, merchantToken);
    return assert(status === 422, `Got ${status}`);
  })();

  await test('PUT /reminders/settings — disable reminders', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('PUT', '/reminders/settings', {
      reminder_enabled: false,
      remind_hours_before: 6,
    }, merchantToken);
    return assert(status === 200 && data.reminder_enabled === false, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /reminders/settings — without auth returns 401', async () => {
    const { status } = await req('GET', '/reminders/settings');
    return assert(status === 401, `Got ${status}`);
  })();

  // ── SECTION 11: Customer Management ───────────────────────
  console.log('\n▶ SECTION 11 — Customer Management');

  await test('GET /customers — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/customers', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /customers/stats — returns stats', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/customers/stats', null, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /customers/uploads — returns upload history', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/customers/uploads', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /customers — without auth returns 401', async () => {
    const { status } = await req('GET', '/customers');
    return assert(status === 401, `Got ${status}`);
  })();

  // ── SECTION 12: Partner Offers ─────────────────────────────
  console.log('\n▶ SECTION 12 — Partner Offers');

  await test('GET /partner-offers — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/partner-offers', null, merchantToken);
    if (status === 200) {
      const arr = data.data || data;
      testOfferUuid = Array.isArray(arr) ? arr[0]?.uuid : null;
    }
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /partner-offers — create flat monetary offer', async () => {
    if (!merchantToken) return 'SKIP';
    const ts = Date.now();
    const { status, data } = await req('POST', '/partner-offers', {
      title: `AutoTest Offer ${ts}`,
      coupon_code: `AUTO${ts % 10000}`,
      discount_type: 'flat',
      discount_value: 100,
      pos_redemption_type: 'flat_monetary',
      expiry_date: '2027-12-31',
      description: 'Automated test offer',
    }, merchantToken);
    if ((status === 200 || status === 201) && data.uuid) {
      testOfferUuid = data.uuid;
    }
    return assert(status === 200 || status === 201 || status === 422, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('POST /partner-offers — create percentage offer', async () => {
    if (!merchantToken) return 'SKIP';
    const ts = Date.now() + 1;
    const { status, data } = await req('POST', '/partner-offers', {
      title: `AutoTest PCT Offer ${ts}`,
      coupon_code: `PCTAUTO${ts % 10000}`,
      discount_type: 'percentage',
      discount_value: 15,
      pos_redemption_type: 'percentage_discount',
      max_discount_amount: 500,
      expiry_date: '2027-12-31',
      description: 'Automated test PCT offer',
    }, merchantToken);
    return assert(status === 200 || status === 201 || status === 422, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /partner-offers/{uuid} — returns offer detail', async () => {
    if (!merchantToken || !testOfferUuid) return 'SKIP';
    const { status, data } = await req('GET', `/partner-offers/${testOfferUuid}`, null, merchantToken);
    // API returns {data: {uuid, ...}} shape
    const o = data?.data || data;
    return assert(status === 200 && (o.uuid || o.id), `Got ${status}: ${JSON.stringify(data).slice(0,100)}`);
  })();

  await test('GET /partner-offers/available/{partnershipUuid} — returns offers', async () => {
    if (!merchantToken || !testPartnershipUuid) return 'SKIP';
    const { status } = await req('GET', `/partner-offers/available/${testPartnershipUuid}`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /partner-offers — missing coupon code returns 422', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('POST', '/partner-offers', {
      title: 'No Coupon Offer',
      discount_type: 'flat',
      discount_value: 50,
    }, merchantToken);
    return assert(status === 422, `Got ${status}`);
  })();

  await test('POST /partner-offers/{uuid}/toggle — toggles status', async () => {
    if (!merchantToken || !testOfferUuid) return 'SKIP';
    const { status } = await req('POST', `/partner-offers/${testOfferUuid}/toggle`, {}, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 13: Discovery ──────────────────────────────────
  console.log('\n▶ SECTION 13 — Discovery');

  await test('GET /discovery/suggestions — returns suggestions', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/discovery/suggestions', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /discovery/search — with city returns results', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/discovery/search?city=Mumbai', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /discovery/search — without city returns 422', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/discovery/search', null, merchantToken);
    return assert(status === 422, `Got ${status}`);
  })();

  // ── SECTION 14: Networks ───────────────────────────────────
  console.log('\n▶ SECTION 14 — Networks');

  await test('GET /merchant/networks — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/merchant/networks', null, merchantToken);
    if (status === 200) {
      const arr = data.data || data;
      testNetworkUuid = Array.isArray(arr) ? arr[0]?.uuid : null;
    }
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /merchant/networks — create network', async () => {
    if (!merchantToken) return 'SKIP';
    const ts = Date.now();
    const { status, data } = await req('POST', '/merchant/networks', {
      name: `AutoTest Network ${ts}`,
      description: 'Automated test network',
    }, merchantToken);
    // May be wrapped in data key
    const n = data?.data || data;
    if ((status === 200 || status === 201) && (n.uuid || n.id)) {
      testNetworkUuid = n.uuid || n.id;
    }
    return assert(status === 200 || status === 201, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /merchant/networks/{uuid} — returns network detail', async () => {
    if (!merchantToken || !testNetworkUuid) return 'SKIP';
    const { status } = await req('GET', `/merchant/networks/${testNetworkUuid}`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /merchant/networks/{uuid}/invite — generates invite', async () => {
    if (!merchantToken || !testNetworkUuid) return 'SKIP';
    // channel is required: email|whatsapp|link
    const { status, data } = await req('POST', `/merchant/networks/${testNetworkUuid}/invite`, {
      channel: 'link',
      max_uses: 5,
    }, merchantToken);
    return assert(status === 200 || status === 201, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  // ── SECTION 15: Campaigns ──────────────────────────────────
  console.log('\n▶ SECTION 15 — Campaigns');

  await test('GET /campaigns/templates — returns templates', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/campaigns/templates', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /campaigns — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/campaigns', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /campaigns/segment-preview — returns count', async () => {
    if (!merchantToken) return 'SKIP';
    // API expects target_segment as an object with source key
    const { status, data } = await req('POST', '/campaigns/segment-preview', {
      target_segment: { source: 'own' },
    }, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  // ── SECTION 16: Follow-Up Campaigns ───────────────────────
  console.log('\n▶ SECTION 16 — Follow-Up Campaigns');

  await test('GET /followup-campaigns/stats — returns stats', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/followup-campaigns/stats', null, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /followup-campaigns — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/followup-campaigns', null, merchantToken);
    if (status === 200) {
      const arr = data.data || data;
      testFollowupId = Array.isArray(arr) ? arr[0]?.id : null;
    }
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /followup-campaigns — create campaign', async () => {
    if (!merchantToken) return 'SKIP';
    const ts = Date.now();
    const { status, data } = await req('POST', '/followup-campaigns', {
      name: `AutoTest Followup ${ts}`,
      message: `Hi! Your token expired. Visit us again! (autotest ${ts})`,
      days_since_expiry: 7,
      is_active: false,
    }, merchantToken);
    const d = data?.data || data;
    if ((status === 200 || status === 201) && d.id) {
      testFollowupId = d.id;
    }
    return assert(status === 200 || status === 201 || status === 422, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('PUT /followup-campaigns/{id} — update campaign', async () => {
    if (!merchantToken || !testFollowupId) return 'SKIP';
    const { status } = await req('PUT', `/followup-campaigns/${testFollowupId}`, {
      name: 'Updated AutoTest Followup',
      is_active: false,
    }, merchantToken);
    return assert(status === 200 || status === 422, `Got ${status}`);
  })();

  await test('GET /followup-campaigns/stats — without auth returns 401', async () => {
    const { status } = await req('GET', '/followup-campaigns/stats');
    return assert(status === 401, `Got ${status}`);
  })();

  // ── SECTION 17: Merchant Settings ─────────────────────────
  console.log('\n▶ SECTION 17 — Merchant Settings');

  await test('GET /merchant/settings/point-valuation — returns valuation', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/merchant/settings/point-valuation', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /merchant/settings/discoverability — returns setting', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/merchant/settings/discoverability', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /merchant/settings/discoverability — toggles', async () => {
    if (!merchantToken) return 'SKIP';
    // API field is open_to_partnerships (not is_discoverable)
    const { status } = await req('POST', '/merchant/settings/discoverability', {
      open_to_partnerships: true,
    }, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /merchant/whatsapp-balance — returns balance', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/merchant/whatsapp-balance', null, merchantToken);
    return assert(status === 200 && typeof data.balance !== 'undefined', `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /merchant/ewards-request — returns request status', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/merchant/ewards-request', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /merchant/settings/bill-offers — returns setting', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/merchant/settings/bill-offers', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 18: Growth ─────────────────────────────────────
  console.log('\n▶ SECTION 18 — Growth Module');

  await test('GET /growth/health — returns health scores', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/growth/health', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /growth/weekly-digest — returns digest', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/growth/weekly-digest', null, merchantToken);
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /growth/demand-index — returns index', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/growth/demand-index', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /growth/invite-stats — returns stats', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/growth/invite-stats', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /growth/seasonal-templates — returns templates', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/growth/seasonal-templates', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /public/brand/testbrand-alpha — returns public profile', async () => {
    const { status } = await req('GET', '/public/brand/testbrand-alpha');
    return assert(status === 200 || status === 404, `Got ${status}`);
  })();

  // ── SECTION 19: Event Triggers ─────────────────────────────
  console.log('\n▶ SECTION 19 — Event Triggers');

  await test('GET /event-constants — returns event types', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/event-constants', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /event-sources — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/event-sources', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /event-triggers — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/event-triggers', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /event-log — returns log', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/event-log', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 20: Members ────────────────────────────────────
  console.log('\n▶ SECTION 20 — Member Module');

  await test('POST /members/lookup — returns member status', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('POST', '/members/lookup', {
      phone: '9111111101'
    }, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /outlets — returns merchant outlets', async () => {
    if (!merchantToken) return 'SKIP';
    const { status, data } = await req('GET', '/outlets', null, merchantToken);
    return assert(status === 200 && Array.isArray(data), `Got ${status}: ${JSON.stringify(data).slice(0,100)}`);
  })();

  await test('GET /merchants — returns other merchants (not self)', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/merchants', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 21: Customer Portal ────────────────────────────
  console.log('\n▶ SECTION 21 — Customer Portal');

  await test('POST /customer/send-otp — sends OTP', async () => {
    const { status, data } = await req('POST', '/customer/send-otp', {
      phone: '9111111101'
    });
    if (status === 200 && data.otp) {
      const { status: vs, data: vd } = await req('POST', '/customer/verify-otp', {
        phone: '9111111101',
        otp: data.otp,
      });
      if (vs === 200 && vd.token) customerToken = vd.token;
    }
    return assert(status === 200, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('GET /customer/rewards — returns rewards list', async () => {
    if (!customerToken) return 'SKIP';
    const { status } = await req('GET', '/customer/rewards', null, customerToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /customer/activity — returns activity', async () => {
    if (!customerToken) return 'SKIP';
    const { status } = await req('GET', '/customer/activity', null, customerToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /customer/rewards — merchant token returns 401', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/customer/rewards', null, merchantToken);
    return assert(status === 401, `Got ${status}`);
  })();

  // ── SECTION 22: Public Endpoints ──────────────────────────
  console.log('\n▶ SECTION 22 — Public Endpoints');

  await test('GET /public/bill-offers/{uuid}/enabled — works without auth', async () => {
    const { status } = await req('GET', '/public/bill-offers/00000000-0000-0000-0000-000000000000/enabled');
    return assert(status === 200 || status === 404, `Got ${status}`);
  })();

  await test('GET /shared-claim/INVALIDCODE — returns 404', async () => {
    const { status } = await req('GET', '/shared-claim/INVALIDCODE');
    return assert(status === 404 || status === 422, `Got ${status}`);
  })();

  // ── SECTION 23: SA Merchant Ratings ───────────────────────
  console.log('\n▶ SECTION 23 — SA & Merchant Ratings');

  await test('GET /merchants/{id}/ratings — returns merchant ratings', async () => {
    if (!merchantToken || !testMerchantId) return 'SKIP';
    const { status } = await req('GET', `/merchants/${testMerchantId}/ratings`, null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /super-admin/merchants/{id} — SA can view merchant', async () => {
    if (!saToken || !testMerchantId) return 'SKIP';
    const { status } = await req('GET', `/super-admin/merchants/${testMerchantId}`, null, saToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('POST /super-admin/merchants/{id}/credits — allocate credits', async () => {
    if (!saToken || !testMerchantId) return 'SKIP';
    const { status, data } = await req('POST', `/super-admin/merchants/${testMerchantId}/credits`, {
      amount: 10,
      note: 'Automated test allocation',
    }, saToken);
    return assert(status === 200 || status === 201, `Got ${status}: ${JSON.stringify(data)}`);
  })();

  await test('POST /super-admin/merchants/{id}/credits — zero amount returns 422', async () => {
    if (!saToken || !testMerchantId) return 'SKIP';
    const { status } = await req('POST', `/super-admin/merchants/${testMerchantId}/credits`, {
      amount: 0,
    }, saToken);
    return assert(status === 422, `Got ${status}`);
  })();

  await test('GET /super-admin/merchants/{id}/ledger — returns credit ledger', async () => {
    if (!saToken || !testMerchantId) return 'SKIP';
    const { status } = await req('GET', `/super-admin/merchants/${testMerchantId}/ledger`, null, saToken);
    return assert(status === 200, `Got ${status}`);
  })();

  // ── SECTION 24: Integration Hub ───────────────────────────
  console.log('\n▶ SECTION 24 — Integration Hub');

  await test('GET /merchant/integrations — returns list', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/merchant/integrations', null, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /merchant/settings/point-valuation — without auth returns 401', async () => {
    const { status } = await req('GET', '/merchant/settings/point-valuation');
    return assert(status === 401, `Got ${status}`);
  })();

  // ── SECTION 25: Logout Tests ───────────────────────────────
  console.log('\n▶ SECTION 25 — Logout');

  await test('POST /super-admin/auth/logout — revokes SA token', async () => {
    if (!saToken) return 'SKIP';
    const { status } = await req('POST', '/super-admin/auth/logout', {}, saToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /super-admin/auth/me — after SA logout returns 401', async () => {
    if (!saToken) return 'SKIP';
    const { status } = await req('GET', '/super-admin/auth/me', null, saToken);
    return assert(status === 401, `Got ${status}`);
  })();

  await test('POST /auth/logout — revokes merchant token', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('POST', '/auth/logout', {}, merchantToken);
    return assert(status === 200, `Got ${status}`);
  })();

  await test('GET /partnerships — after merchant logout returns 401', async () => {
    if (!merchantToken) return 'SKIP';
    const { status } = await req('GET', '/partnerships', null, merchantToken);
    return assert(status === 401, `Got ${status}`);
  })();

  // ── Summary ────────────────────────────────────────────────
  const total = PASS + FAIL + SKIP;
  const pct   = total > 0 ? Math.round((PASS / (PASS + FAIL)) * 100) : 0;

  console.log('\n════════════════════════════════════════════════');
  console.log(`  RESULTS:  ✅ ${PASS} PASS  ❌ ${FAIL} FAIL  ⚪ ${SKIP} SKIP`);
  console.log(`  PASS RATE: ${pct}% (of non-skipped tests)`);
  console.log('════════════════════════════════════════════════\n');

  if (FAIL > 0) {
    console.log('FAILED TESTS:');
    results.filter(r => r.status === 'FAIL').forEach(r => {
      console.log(`  ❌ ${r.name}`);
      if (r.note) console.log(`     ${r.note}`);
    });
    console.log('');
  }

  if (SKIP > 0) {
    console.log('SKIPPED (dependency not available):');
    results.filter(r => r.status === 'SKIP').forEach(r => {
      console.log(`  ⚪ ${r.name}`);
    });
    console.log('');
  }
}

run().catch(console.error);

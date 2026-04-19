# Hyperlocal — Manual Test Scenarios
**Total: 110 Scenarios** | URL: http://localhost:3000 | API: http://127.0.0.1:8000

---

## TEST ACCOUNTS

| Role | Email | Password | Notes |
|------|-------|----------|-------|
| Super Admin | admin@hyperlocal.internal | changeme123 | Full platform access |
| Brand Alpha (self-reg) | alice@testbrandalpha.com | password123 | TestBrand Alpha, Mumbai |
| Brand Beta (self-reg) | bob@testbrandbeta.com | password123 | TestBrand Beta, Mumbai |
| Brand Gamma (SA-created) | gina@testbrandgamma.com | password123 | TestBrand Gamma, Delhi |

---

## MODULE 1 — SUPER ADMIN LOGIN & DASHBOARD

---

### TC-001 · Super Admin Login — Valid Credentials
**URL:** http://localhost:3000/super-admin/login

**Steps:**
1. Open http://localhost:3000/super-admin/login
2. Verify the page shows "HyperLocal — Super Admin" heading
3. Verify credentials hint is visible below Sign in button:
   - Email: admin@hyperlocal.internal
   - Password: changeme123
4. Enter Email: `admin@hyperlocal.internal`
5. Enter Password: `changeme123`
6. Click **Sign in**

**Expected Result:**
- Redirected to `/super-admin/dashboard`
- Top nav shows: Dashboard | Brand Requests | Brands | eWards Requests
- Dashboard shows stat cards: Total Brands, Ecosystem Active, Live Partnerships, Pending Registrations, Pending eWards, Low Credits, Zero Credits
- All numbers are visible (no blank/null values)

---

### TC-002 · Super Admin Login — Wrong Password
**URL:** http://localhost:3000/super-admin/login

**Steps:**
1. Enter Email: `admin@hyperlocal.internal`
2. Enter Password: `wrongpassword`
3. Click **Sign in**

**Expected Result:**
- Error message shown: "Invalid credentials" or similar
- Stays on login page
- No redirect

---

### TC-003 · Super Admin Login — Empty Fields
**URL:** http://localhost:3000/super-admin/login

**Steps:**
1. Leave both fields empty
2. Click **Sign in**

**Expected Result:**
- Browser or form validation prevents submission
- Fields highlighted as required

---

### TC-004 · Super Admin Dashboard — Stats Accuracy
**URL:** http://localhost:3000/super-admin/dashboard (logged in as SA)

**Steps:**
1. Note all stat card values
2. Open new tab, register a new brand via http://localhost:3000/register
3. Return to SA dashboard, refresh the page

**Expected Result:**
- "Pending Registrations" count increased by 1
- All other stats unchanged
- "Review Brand Registrations" button is clickable and navigates to `/super-admin/brand-registrations`

---

### TC-005 · Super Admin — Merchant Token Rejected on SA Routes
**Steps:**
1. Log into brand portal as Alice (alice@testbrandalpha.com)
2. Manually navigate to http://localhost:3000/super-admin/dashboard

**Expected Result:**
- Redirected to `/super-admin/login` (not the merchant login)
- Cannot access super admin panel with merchant credentials

---

## MODULE 2 — BRAND SELF-REGISTRATION

---

### TC-006 · Brand Registration — Successful Submission
**URL:** http://localhost:3000/register

**Steps:**
1. Open http://localhost:3000/register
2. Fill in all fields:
   - Brand Name: `My Test Cafe`
   - Category: select any (e.g., Restaurant)
   - City: `Pune`
   - State: `Maharashtra`
   - GST Number: (leave blank)
   - Outlet Name: `Pune Main Branch`
   - Contact Name: `Test Owner`
   - Contact Email: `testowner@mytestcafe.com`
   - Contact Phone: `9888877770`
   - Password: `password123`
   - Confirm Password: `password123`
3. Click **Register**

**Expected Result:**
- Success screen shown: "Registration submitted. Our team will review and activate your account."
- No redirect to login yet
- Count of pending registrations in SA panel increases

---

### TC-007 · Brand Registration — Duplicate Email
**URL:** http://localhost:3000/register

**Steps:**
1. Try to register with Email: `alice@testbrandalpha.com` (already registered)
2. Fill all other fields validly
3. Click **Register**

**Expected Result:**
- Error shown: "The contact email has already been taken" or similar
- Form stays on page
- No new brand created

---

### TC-008 · Brand Registration — Password Mismatch
**URL:** http://localhost:3000/register

**Steps:**
1. Fill all valid fields
2. Password: `password123`
3. Confirm Password: `password456`
4. Click **Register**

**Expected Result:**
- Error: "Password confirmation does not match"
- Form stays on registration page

---

### TC-009 · Brand Registration — Short Password
**URL:** http://localhost:3000/register

**Steps:**
1. Fill all valid fields
2. Password: `abc`
3. Confirm Password: `abc`
4. Click **Register**

**Expected Result:**
- Error: "Password must be at least 8 characters"
- Form not submitted

---

### TC-010 · Brand Registration — Required Fields Missing
**URL:** http://localhost:3000/register

**Steps:**
1. Leave Brand Name blank
2. Fill all other fields
3. Click **Register**

**Expected Result:**
- Error: "Brand name is required"
- Try leaving City blank — same behavior
- Try leaving Outlet Name blank — same behavior

---

## MODULE 3 — SUPER ADMIN BRAND REGISTRATION REVIEW

---

### TC-011 · View Pending Brand Registrations
**URL:** http://localhost:3000/super-admin/brand-registrations (logged in as SA)

**Steps:**
1. Navigate to **Brand Requests** in top nav
2. Default filter is "Pending"
3. Observe the list

**Expected Result:**
- Shows all brands with `Pending Review` badge (yellow)
- Each row shows: Brand name, email, phone, category, city, submitted date
- Each row has **Approve** and **Reject** buttons

---

### TC-012 · Approve Brand Registration
**URL:** http://localhost:3000/super-admin/brand-registrations

**Steps:**
1. Find a pending brand (e.g., My Test Cafe from TC-006)
2. Click **Approve**
3. Confirm modal appears with brand name and message "This will activate the brand account"
4. Click **Approve** in modal

**Expected Result:**
- Modal closes
- Brand row disappears from Pending list
- Switch filter to "Approved" — brand appears with green **Approved** badge
- Brand can now log in at http://localhost:3000/login

---

### TC-013 · Reject Brand Registration — With Reason
**URL:** http://localhost:3000/super-admin/brand-registrations

**Steps:**
1. Register another test brand via /register
2. In SA panel, find the new brand in Pending
3. Click **Reject**
4. Rejection reason field appears
5. Enter: `Incomplete documentation. Please provide valid GST number and reapply.`
6. Click **Reject**

**Expected Result:**
- Modal closes
- Brand disappears from Pending list
- Switch filter to "Rejected" — brand appears with red **Rejected** badge
- Rejection reason is visible truncated below the badge

---

### TC-014 · Reject Without Reason — Validation
**URL:** http://localhost:3000/super-admin/brand-registrations

**Steps:**
1. Click **Reject** on a pending brand
2. Leave reason field empty
3. Click **Reject**

**Expected Result:**
- Error shown: "Rejection reason is required"
- Modal stays open
- Brand not rejected

---

### TC-015 · Filter Brand Registrations by Status
**URL:** http://localhost:3000/super-admin/brand-registrations

**Steps:**
1. Select filter: **Approved** → verify only approved brands show
2. Select filter: **Rejected** → verify only rejected brands show
3. Select filter: **All** → verify all brands show regardless of status

**Expected Result:**
- Filter works correctly for each status
- Status badges match the selected filter

---

## MODULE 4 — SUPER ADMIN — BRAND MANAGEMENT

---

### TC-016 · SA Creates Brand Directly
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. Navigate to **Brands** in SA top nav
2. Click **Add Brand** button
3. Fill form:
   - Brand Name: `DirectBrand Ltd`
   - Category: `Retail`
   - City: `Hyderabad`
   - Outlet Name: `Hyderabad Main Store`
   - Admin Name: `Direct Admin`
   - Admin Email: `admin@directbrand.com`
   - Admin Password: `password123`
4. Click **Create**

**Expected Result:**
- Success message shown
- Brand appears in merchant list
- Brand is immediately active (no approval needed)
- Admin can login at /login with admin@directbrand.com / password123

---

### TC-017 · SA Merchant List — Search
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. Type "Alpha" in the search box
2. Observe results

**Expected Result:**
- Only brands matching "Alpha" appear
- "TestBrand Alpha" visible, others filtered out

---

### TC-018 · SA Merchant Detail
**URL:** http://localhost:3000/super-admin/merchants → click a merchant name

**Steps:**
1. Click on "TestBrand Alpha" in the merchant list
2. View the detail page

**Expected Result:**
- Shows: name, category, city, email, phone
- Shows WhatsApp credits balance
- Shows eWards status
- Shows Ecosystem active toggle
- Shows credit ledger (transaction history)

---

### TC-019 · SA Add WhatsApp Credits to Brand
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. Find "TestBrand Alpha" in list
2. Note current credit count
3. Click **Add Credits** button on that row
4. Enter Amount: `200`
5. Enter Note: `Monthly credit top-up`
6. Click **Allocate**

**Expected Result:**
- Modal closes
- Credit count on row updates by +200
- Visit merchant detail → credit ledger shows new entry with note

---

### TC-020 · SA Add Credits — Invalid Amount
**URL:** http://localhost:3000/super-admin/merchants → Add Credits

**Steps:**
1. Enter Amount: `0`
2. Click **Allocate**

**Expected Result:**
- Validation error: Amount must be at least 1

---

## MODULE 5 — MERCHANT LOGIN

---

### TC-021 · Brand Login — Approved Brand
**URL:** http://localhost:3000/login

**Steps:**
1. Enter Email: `alice@testbrandalpha.com`
2. Enter Password: `password123`
3. Click **Sign in**

**Expected Result:**
- Redirected to `/dashboard`
- Dashboard shows brand name "TestBrand Alpha"
- Nav bar visible with all menu items

---

### TC-022 · Brand Login — Unapproved Brand (pending)
**URL:** http://localhost:3000/login

**Steps:**
1. Register a new brand (don't approve it in SA)
2. Try to login with those credentials

**Expected Result:**
- Login fails with error: "Your account is not yet active" or "Invalid credentials"
- No dashboard access

---

### TC-023 · Brand Login — Wrong Password
**URL:** http://localhost:3000/login

**Steps:**
1. Enter Email: `alice@testbrandalpha.com`
2. Enter Password: `wrongpassword`
3. Click **Sign in**

**Expected Result:**
- Error: "Invalid credentials" or "These credentials do not match our records"
- Stays on login page

---

### TC-024 · Logout from Brand Portal
**URL:** http://localhost:3000/dashboard (logged in as Alice)

**Steps:**
1. Click **Sign out** or logout button in top nav
2. Observe behavior

**Expected Result:**
- Redirected to `/login`
- Navigating back to `/dashboard` redirects to `/login`
- Token is cleared

---

## MODULE 6 — DASHBOARD & ANALYTICS

---

### TC-025 · Dashboard — Main Stats
**URL:** http://localhost:3000/dashboard (logged in as Alice)

**Steps:**
1. Log in as Alice
2. View dashboard

**Expected Result:**
- Shows stat cards: Live Partnerships, Total Redemptions, New Customers, Existing Customers, Revenue, Benefit Given
- Monthly trend section visible (chart or table)
- Partner breakdown section shows Alpha x Beta Cross-Loyalty
- Pending/paused partnerships inbox section visible

---

### TC-026 · Dashboard — Pending Partnerships Inbox
**URL:** http://localhost:3000/dashboard (logged in as Bob / Beta brand)

**Steps:**
1. Log in as Bob (bob@testbrandbeta.com)
2. View dashboard

**Expected Result:**
- If there's a pending incoming partnership proposal, it shows in "Pending" section
- Each pending item shows: partner name, proposed date, action buttons (Accept/View)

---

### TC-027 · Partnership Analytics Detail
**URL:** http://localhost:3000/partnerships/[uuid]/analytics

**Steps:**
1. Log in as Alice
2. Go to Partnerships
3. Click on "Alpha x Beta Cross-Loyalty"
4. Click **Analytics** tab or link

**Expected Result:**
- Shows: total redemptions, new customers, existing customers, revenue, benefit cost, ROI
- Partnership trend chart visible
- Data matches dashboard summary numbers

---

## MODULE 7 — PARTNERSHIP CREATION & LIFECYCLE

---

### TC-028 · Create Partnership Proposal
**URL:** http://localhost:3000/partnerships (logged in as Alice)

**Steps:**
1. Log in as Alice
2. Click **New Partnership** or **Create** button
3. Fill form:
   - Partnership Name: `Alpha x Gamma Cross-Promo`
   - Partner: Select `TestBrand Gamma`
   - Scope: Brand-level
   - Terms:
     - Per-bill cap: ₹300
     - Min bill amount: ₹150
4. Accept T&C checkbox
5. Click **Create**

**Expected Result:**
- Partnership created with status "Proposed"
- Appears in Alpha's partnership list with yellow "Proposed" badge
- Gina (Gamma) should see it as incoming proposal in her dashboard

---

### TC-029 · Accept Partnership (Go-Live)
**URL:** http://localhost:3000/partnerships (logged in as Gina)

**Steps:**
1. Log in as Gina (gina@testbrandgamma.com / password123)
2. Go to Partnerships or check Dashboard inbox
3. Find "Alpha x Gamma Cross-Promo" with Proposed status
4. Click **Accept & Start** (or Accept → then Go Live)
5. Select outlet(s) to participate
6. Confirm

**Expected Result:**
- Partnership status changes to **Live** (green badge)
- Both Alpha and Gamma see it as Live
- Go to Alpha's dashboard — live_partnerships count increased

---

### TC-030 · Reject Partnership Proposal
**URL:** http://localhost:3000/partnerships (logged in as Gina)

**Steps:**
1. Create another partnership proposal from Alice to Gina
2. Log in as Gina
3. Find the proposal
4. Click **Reject**
5. Confirm

**Expected Result:**
- Partnership status changes to **Rejected** (red badge)
- Alpha sees it as rejected in their list
- Cannot reactivate a rejected partnership (must create new)

---

### TC-031 · Pause Partnership
**URL:** http://localhost:3000/partnerships → click Live partnership (logged in as Alice)

**Steps:**
1. Open "Alpha x Beta Cross-Loyalty" (Live status)
2. Click **Pause** button
3. Enter pause reason: `Monthly maintenance pause`
4. Confirm

**Expected Result:**
- Status changes to **Paused** (orange badge)
- No new tokens can be issued under this partnership
- Resume button appears

---

### TC-032 · Resume Partnership
**URL:** http://localhost:3000/partnerships → click Paused partnership (logged in as Alice)

**Steps:**
1. Find the paused partnership
2. Click **Resume**
3. Confirm

**Expected Result:**
- Status returns to **Live** (green badge)
- Token issuance enabled again

---

### TC-033 · Duplicate Partnership Prevention
**URL:** http://localhost:3000/partnerships (logged in as Alice)

**Steps:**
1. Try to create another partnership with TestBrand Beta (already has an active one)
2. Fill form and submit

**Expected Result:**
- Error: "An active partnership already exists with this brand"
- Partnership not created

---

### TC-034 · Partnership T&C
**URL:** http://localhost:3000/partnerships → Create modal (logged in as Alice)

**Steps:**
1. Open Create Partnership modal
2. Find the T&C section or link
3. Click to view T&C

**Expected Result:**
- T&C text shown (HYPERLOCAL PARTNERSHIP — STANDARD TERMS & CONDITIONS v1.0)
- Must accept before creating

---

### TC-035 · Partnership Outlet Configuration
**URL:** http://localhost:3000/partnerships/[uuid] (Alpha x Beta, logged in as Alice)

**Steps:**
1. Open the Alpha x Beta partnership detail
2. Look at Participants section

**Expected Result:**
- Alice's outlet: "Alpha Main Outlet" shown
- Bob's outlet: "Beta Main Outlet" shown
- Roles shown: Proposer / Acceptor
- Issuing/Redemption enabled toggle visible per participant

---

### TC-036 · Partnership Ledger
**URL:** http://localhost:3000/partnerships/[uuid]/ledger (logged in as Alice)

**Steps:**
1. Navigate to Alpha x Beta partnership
2. Click **Ledger** tab

**Expected Result:**
- Shows list of credit/debit entries
- Each entry: date, type (debit/credit), amount, balance after
- Redemptions appear as debits (benefit given)
- Revenue appears as credits

---

### TC-037 · Partnership Redemptions History
**URL:** http://localhost:3000/partnerships/[uuid]/redemptions (logged in as Alice)

**Steps:**
1. Navigate to Alpha x Beta partnership
2. Click **Redemptions** tab

**Expected Result:**
- List of all redemptions
- Each shows: customer (phone masked), benefit amount, date, transaction ID
- Paginated if many redemptions

---

### TC-038 · Partner Rating
**URL:** http://localhost:3000/partnerships/[uuid] (logged in as Alice)

**Steps:**
1. Open Alpha x Beta partnership detail
2. Find rating section
3. Give rating: 5 stars
4. Add comment: "Excellent partner, great referral quality"
5. Submit

**Expected Result:**
- Rating saved
- Rating visible in partnership detail
- Bob can see received rating in his view

---

## MODULE 8 — TOKEN ISSUANCE

---

### TC-039 · Issue Token — Manual (Merchant Dashboard)
**URL:** http://localhost:3000/partnerships/[uuid] (logged in as Alice)

**Steps:**
1. Open Alpha x Beta partnership detail
2. Click **Issue Token** or find the claim issuance section
3. Fill:
   - Source outlet: Alpha Main Outlet
   - Target outlet: Beta Main Outlet
   - Customer phone: `9111111101`
4. Click **Issue**

**Expected Result:**
- Token shown: e.g., `HLPXXXXX` (8 chars)
- Expiry shown: 48 hours from now
- Token copied or displayed prominently for customer

---

### TC-040 · Issue Token — Without Customer Phone
**URL:** http://localhost:3000/partnerships/[uuid] (logged in as Alice)

**Steps:**
1. Open Alpha x Beta partnership detail
2. Click **Issue Token**
3. Leave phone blank
4. Click **Issue**

**Expected Result:**
- Token still issued (phone is optional for anonymous customers)
- OR error shown if phone required — consistent with backend behavior

---

### TC-041 · Issue Token — Paused Partnership
**URL:** http://localhost:3000/partnerships/[uuid] (logged in as Alice)

**Steps:**
1. Pause the Alpha x Beta partnership first (TC-031)
2. Try to issue a token

**Expected Result:**
- Error: "Cannot issue tokens for a paused partnership"
- OR Issue button disabled/hidden when paused

---

### TC-042 · QR Code Token Claim — Public Page
**URL:** http://localhost:3000/claim/[partnership-uuid]

**Steps:**
1. Get the partnership UUID from Alpha x Beta
2. Open in incognito/private window: `http://localhost:3000/claim/[uuid]`
3. Enter phone: `9111111101`
4. Select target outlet: Beta Main Outlet
5. Click **Get Token**

**Expected Result:**
- Token issued (e.g., HLPXXXXX)
- Expiry shown
- No login required — fully public

---

### TC-043 · QR Code — Invalid Partnership UUID
**URL:** http://localhost:3000/claim/INVALID-UUID

**Steps:**
1. Navigate to `http://localhost:3000/claim/00000000-0000-0000-0000-000000000000`

**Expected Result:**
- Error page or "Partnership not found"
- Cannot issue a token

---

### TC-044 · Shareable Claim Link Generation
**URL:** http://localhost:3000/partnerships/[uuid] (logged in as Alice)

**Steps:**
1. Open Alpha x Beta partnership
2. Click **Generate Share Link** or similar button
3. Copy the generated link

**Expected Result:**
- Link generated: `http://localhost:3000/shared/[8-char-code]`
- Open link in incognito → claim form loads
- Claim works same as QR claim flow

---

## MODULE 9 — TOKEN REDEMPTION

---

### TC-045 · Redeem Token — Full Flow
**URL:** http://localhost:3000/redeem (logged in as Bob/Beta)

**Steps:**
1. First issue a token as Alice (TC-039): get token `HLPXXXXX`
2. Log in as Bob (Beta brand — where customer redeems)
3. Navigate to `/redeem`
4. Step 1 — Lookup:
   - Enter Token: `HLPXXXXX`
   - Enter Bill Amount: `350`
   - Click **Look up**
5. Step 2 — Confirm:
   - Verify benefit amount, customer type, partnership name shown
   - Click **Confirm Redemption**
6. Step 3 — Complete:
   - Enter Transaction ID: `TXN-TEST-001`
   - Click **Redeem**

**Expected Result:**
- Step 1: Token valid — shows benefit amount (e.g., ₹300 cap applied), customer type (New/Existing)
- Step 2: Confirmation screen with clear benefit summary
- Step 3: Success — "Redemption complete!" with redemption ID
- Token cannot be used again

---

### TC-046 · Redeem Token — Duplicate Prevention
**URL:** http://localhost:3000/redeem (logged in as Bob)

**Steps:**
1. Use the same token from TC-045 (already redeemed)
2. Enter the token again
3. Enter bill amount
4. Click **Look up**

**Expected Result:**
- Error shown: "This token has already been redeemed"
- OR lookup shows status as "Redeemed" — cannot proceed
- Second redemption blocked

---

### TC-047 · Redeem Token — Wrong Store
**URL:** http://localhost:3000/redeem (logged in as Alice — not the target)

**Steps:**
1. Issue a token intended for Beta (target = Beta outlet)
2. Log in as Alice (Alpha brand)
3. Navigate to /redeem
4. Enter the token
5. Click Look up

**Expected Result:**
- Error: "This token is not valid at your store" or "Token not found"
- Cannot redeem at wrong store

---

### TC-048 · Redeem Token — Fake Token
**URL:** http://localhost:3000/redeem (logged in as Bob)

**Steps:**
1. Enter Token: `FAKECODE`
2. Enter Bill Amount: `200`
3. Click **Look up**

**Expected Result:**
- Error: "Invalid or unknown claim token"
- No crash (500 error fixed — should be clean 422 response)

---

### TC-049 · Redeem Token — Bill Amount Below Minimum
**URL:** http://localhost:3000/redeem (logged in as Bob)

**Steps:**
1. Partnership has min_bill_amount of ₹100
2. Issue a new token
3. Try to redeem with Bill Amount: `50`

**Expected Result:**
- Lookup returns: benefit = 0 or error "Bill amount below minimum"
- OR redemption allowed but benefit = ₹0 (depends on business rule)

---

### TC-050 · Redemption — Manager Approval Flow
**URL:** http://localhost:3000/redeem (logged in as Bob)

**Steps:**
1. If partnership has `approval_mode = 2` (manual approval required)
2. Enter valid token and bill amount
3. Click Look up
4. If "requires_approval" is true:
   - Click **Request Approval**
   - Approval code generated
   - Enter the code in the approval field
5. Proceed to redeem

**Expected Result:**
- Approval code shown (for manager to enter)
- After correct code: proceeds to redemption
- Wrong code: rejected

---

## MODULE 10 — CUSTOMER MANAGEMENT

---

### TC-051 · CSV Customer Upload — Valid File
**URL:** http://localhost:3000/customers (logged in as Alice)

**Steps:**
1. Create a CSV file with content:
   ```
   name,phone,email
   Test Customer 1,9700000001,tc1@test.com
   Test Customer 2,9700000002,tc2@test.com
   Test Customer 3,9700000003,tc3@test.com
   ```
2. Navigate to `/customers`
3. Click **Upload CSV**
4. Select the file
5. Click **Upload**

**Expected Result:**
- Success: "Upload completed. Imported: 3, Failed: 0"
- Customers appear in the customer list
- Upload appears in upload history

---

### TC-052 · CSV Upload — Wrong Headers
**URL:** http://localhost:3000/customers (logged in as Alice)

**Steps:**
1. Create CSV:
   ```
   wrong,headers,here
   data1,data2,data3
   ```
2. Upload this file

**Expected Result:**
- Error: "Phone column not found in CSV header" or similar
- Import fails gracefully with meaningful error message

---

### TC-053 · CSV Upload — Non-CSV File
**URL:** http://localhost:3000/customers (logged in as Alice)

**Steps:**
1. Create a .txt file with text content
2. Try to upload it

**Expected Result:**
- Error: "File must be a CSV" or "Invalid file type"
- File rejected before upload (now fixed to only accept .csv)

---

### TC-054 · CSV Upload — Duplicate Customers
**URL:** http://localhost:3000/customers (logged in as Alice)

**Steps:**
1. Upload a CSV with 3 customers (TC-051)
2. Upload the same CSV again

**Expected Result:**
- Second upload: "Imported: 0, Skipped: 3" (duplicates detected by phone)
- OR "Imported: 3, Updated: 0" (upsert behavior)
- No duplicate customers in list

---

### TC-055 · Customer List — Search & View
**URL:** http://localhost:3000/customers (logged in as Alice)

**Steps:**
1. Navigate to /customers
2. Search for phone: `9111111101`

**Expected Result:**
- Priya Sharma appears in results
- Shows name, phone, email, source, upload date

---

### TC-056 · Upload History
**URL:** http://localhost:3000/customers (logged in as Alice)

**Steps:**
1. Navigate to /customers
2. View upload history section

**Expected Result:**
- All past uploads listed
- Each shows: filename, date, imported count, failed count, status

---

## MODULE 11 — PARTNER OFFERS

---

### TC-057 · Create Partner Offer — Percentage Discount
**URL:** http://localhost:3000/partner-offers/create (logged in as Alice)

**Steps:**
1. Navigate to `/partner-offers/create`
2. Fill form:
   - Offer Title: `15% Off for Beta Customers`
   - Coupon Code: `BETA15`
   - Discount Type: **Percentage (%)**
   - Discount Value: `15`
   - Expiry Date: 6 months from today
   - Description: `Show your Beta token and save 15% at Alpha`
3. Click **Save**

**Expected Result:**
- Offer created and visible in `/partner-offers` list
- Coupon BETA15, 15% discount, status Active

---

### TC-058 · Create Partner Offer — Flat Discount
**URL:** http://localhost:3000/partner-offers/create (logged in as Alice)

**Steps:**
1. Create offer with:
   - Discount Type: **Flat Amount (₹)**
   - Discount Value: `75`
   - Coupon Code: `SAVE75`
2. Save

**Expected Result:**
- Offer created: ₹75 flat discount
- Appears correctly in list as flat type

---

### TC-059 · Create Offer — Missing Coupon Code
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Fill all fields except Coupon Code
2. Click **Save**

**Expected Result:**
- Validation error: "Coupon code is required"
- Form not submitted

---

### TC-060 · Edit Partner Offer
**URL:** http://localhost:3000/partner-offers/[uuid]/edit (logged in as Alice)

**Steps:**
1. Go to partner offers list
2. Click edit on an existing offer
3. Change title to: `Updated — 15% Off for Beta Customers`
4. Change discount value to: `20`
5. Save

**Expected Result:**
- Offer updated with new title and 20% discount
- Detail page shows updated values

---

### TC-061 · Attach Offer to Partnership
**URL:** http://localhost:3000/partner-offers/[uuid] (logged in as Alice)

**Steps:**
1. Open offer detail for BETA15 offer
2. Find "Attach to Partnership" section
3. Select "Alpha x Beta Cross-Loyalty" from dropdown
4. Click **Attach**

**Expected Result:**
- Offer appears in attached partnerships list
- Partnership shows offer association
- Beta outlet customers would see this offer on digital bill

---

### TC-062 · Toggle Offer Active/Inactive
**URL:** http://localhost:3000/partner-offers (logged in as Alice)

**Steps:**
1. Find an active offer in the list
2. Toggle the active/inactive switch to Inactive
3. Toggle back to Active

**Expected Result:**
- Status badge changes: Active ↔ Inactive
- Inactive offer not shown on bill-offers public page

---

## MODULE 12 — FIND PARTNERS (DISCOVERY)

---

### TC-063 · Search Partners by City
**URL:** http://localhost:3000/find-partners (logged in as Alice)

**Steps:**
1. Navigate to `/find-partners`
2. City field should be pre-filled with "Mumbai"
3. Click **Search**

**Expected Result:**
- List of merchants in Mumbai shown
- Each card shows: name, category, city, outlet count, fit score
- TestBrand Beta visible in results
- TestBrand Alpha NOT shown (can't partner with self)

---

### TC-064 · Search Partners by City + Category
**URL:** http://localhost:3000/find-partners (logged in as Alice)

**Steps:**
1. City: `Mumbai`
2. Category: `Cafe`
3. Click **Search**

**Expected Result:**
- Only Cafe-category brands in Mumbai shown
- TestBrand Beta (Cafe) should appear
- Other categories filtered out

---

### TC-065 · Search Partners — No City
**URL:** http://localhost:3000/find-partners

**Steps:**
1. Clear the city field
2. Click **Search**

**Expected Result:**
- Error: "City is required" or city field highlighted
- No results shown

---

### TC-066 · Send Proposal from Discovery Results
**URL:** http://localhost:3000/find-partners (logged in as Alice)

**Steps:**
1. Search for partners in Mumbai
2. Find a brand not yet partnered with (e.g., a demo brand like Brew & Co)
3. Click **Send Proposal** or **Partner with X**

**Expected Result:**
- Navigated to `/partnerships` with that brand pre-selected in the create modal
- OR create modal opens with partner already filled

---

## MODULE 13 — NETWORKS

---

### TC-067 · Create Network
**URL:** http://localhost:3000/networks (logged in as Alice)

**Steps:**
1. Navigate to `/networks`
2. Click **Create Network**
3. Fill:
   - Name: `Mumbai Food Alliance`
   - Description: `A network of food & beverage brands in Mumbai`
4. Click **Create**

**Expected Result:**
- Network created
- Redirected to network detail page
- Alice is shown as Owner
- Member count: 1 (just Alice)

---

### TC-068 · Invite Merchant to Network
**URL:** http://localhost:3000/networks/[uuid] (logged in as Alice, as owner)

**Steps:**
1. Open the network detail
2. Click **Invite Merchant**
3. Fill invite form (max uses: 3)
4. Click **Generate Invite**

**Expected Result:**
- Invite link generated: `http://localhost:3000/networks/join/[token]`
- Max uses, remaining uses, expiry shown
- Copy button works

---

### TC-069 · Join Network via Invite Link
**URL:** http://localhost:3000/networks/join/[token] (logged in as Bob)

**Steps:**
1. Get the invite link from TC-068
2. Log in as Bob (Beta brand)
3. Navigate to the invite link
4. Click **Join Network**

**Expected Result:**
- Beta joins the network
- Navigate to Alice's network detail — Bob (TestBrand Beta) appears as member
- Network member count = 2

---

### TC-070 · Leave Network
**URL:** http://localhost:3000/networks/[uuid] (logged in as Bob)

**Steps:**
1. Bob is a member of Mumbai Food Alliance
2. Click **Leave Network**
3. Confirm

**Expected Result:**
- Bob removed from member list
- Alice's network detail shows member count -1

---

### TC-071 · Invite Link — Exceed Max Uses
**URL:** http://localhost:3000/networks/join/[token]

**Steps:**
1. Generate invite with max_uses: `1`
2. Have one merchant join (uses up the 1 invite)
3. Try to use the same link again with a different account

**Expected Result:**
- Error: "Invite link has been used the maximum number of times" or "Invalid invite"
- Second merchant cannot join with expired invite

---

## MODULE 14 — CAMPAIGNS

---

### TC-072 · View Campaign Templates
**URL:** http://localhost:3000/campaigns (logged in as Alice)

**Steps:**
1. Navigate to `/campaigns`
2. Click **Create Campaign**
3. Open the template dropdown

**Expected Result:**
- List of templates shown (e.g., welcome_partner, new_offer, expiry_reminder, etc.)
- Each template shows required variables and description

---

### TC-073 · Create Campaign — Segment Preview
**URL:** http://localhost:3000/campaigns (logged in as Alice)

**Steps:**
1. Click **Create Campaign**
2. Select a template
3. Fill required template variables
4. Set segment: **Own Customers**
5. Click **Preview Segment**

**Expected Result:**
- Customer count shown (e.g., "3 customers will receive this")
- Helps estimate message cost

---

### TC-074 · Create Campaign and Schedule
**URL:** http://localhost:3000/campaigns (logged in as Alice)

**Steps:**
1. Create campaign with:
   - Name: `Test Campaign Alpha`
   - Template: any available
   - Segment: Own customers
   - Schedule: **Later** → set tomorrow's date+time
2. Click **Save**

**Expected Result:**
- Campaign created with status **Scheduled**
- Appears in campaigns list with schedule date
- Cancel button visible

---

### TC-075 · Create Campaign — Insufficient WhatsApp Credits
**URL:** http://localhost:3000/campaigns (logged in as Gina / Gamma — zero credits)

**Steps:**
1. Log in as Gina (no credits allocated yet)
2. Try to create and run a campaign

**Expected Result:**
- Warning: "Insufficient WhatsApp credits"
- OR campaign fails to send with error about credits
- Credits balance shown as 0

---

### TC-076 · Cancel Campaign
**URL:** http://localhost:3000/campaigns (logged in as Alice)

**Steps:**
1. Find a Scheduled or Draft campaign
2. Click **Cancel**
3. Confirm

**Expected Result:**
- Campaign status changes to **Cancelled**
- Cannot be run or scheduled again
- Appears in list with red Cancelled badge

---

## MODULE 15 — MERCHANT SETTINGS

---

### TC-077 · View & Set Point Valuation
**URL:** http://localhost:3000/settings (logged in as Alice)

**Steps:**
1. Navigate to `/settings`
2. Find Point Valuation section
3. Click **Change** or **Set Value**
4. Enter Rate: `1.5` (₹1.50 per point)
5. Enter Reason: `Initial rate setup`
6. Click **Confirm**

**Expected Result:**
- New rate saved: ₹1.50/point
- Valuation history table shows the new entry with date, rate, set-by
- Example calculations shown (e.g., 100 pts = ₹150)

---

### TC-078 · Point Valuation — Without Reason
**URL:** http://localhost:3000/settings → Point Valuation

**Steps:**
1. Click **Change**
2. Enter Rate: `2.0`
3. Leave Reason blank
4. Click **Confirm**

**Expected Result:**
- Error: "Reason is required (minimum 3 characters)"
- Rate not saved

---

### TC-079 · Toggle Discoverability
**URL:** http://localhost:3000/settings (logged in as Alice)

**Steps:**
1. Navigate to settings
2. Find Partner Discoverability section
3. Toggle to **Hidden**
4. Save

**Expected Result:**
- Status shows "Hidden from partner search"
- Log in as Bob → go to Find Partners → search Mumbai → Alice's brand NOT visible

---

### TC-080 · Discoverability — Re-enable
**URL:** http://localhost:3000/settings (logged in as Alice)

**Steps:**
1. Toggle discoverability back to **Visible**
2. Save

**Expected Result:**
- Status shows "Visible to partners"
- Bob can find Alpha in search results again

---

### TC-081 · WhatsApp Credits — View Balance
**URL:** http://localhost:3000/settings (logged in as Alice)

**Steps:**
1. Navigate to settings
2. Find WhatsApp Credits section

**Expected Result:**
- Current balance shown (e.g., 598 credits)
- If balance ≤ 50: warning message shown in orange
- If balance = 0: error/red warning shown
- "Contact admin to top up" message visible

---

### TC-082 · eWards Integration Request — Submit
**URL:** http://localhost:3000/settings (logged in as Alice)

**Steps:**
1. Navigate to settings
2. Find eWards Integration section
3. If status is "Not Requested":
   - Enter notes: `We use eWards POS for all outlets`
   - Click **Submit Request**

**Expected Result:**
- Status changes to **Pending Review**
- SA can now see it in `/super-admin/requests`

---

### TC-083 · eWards Integration Request — SA Approves
**URL:** http://localhost:3000/super-admin/requests (logged in as SA)

**Steps:**
1. Find Alice's eWards request (status: Pending)
2. Click **Approve**
3. Enter API credentials (any test values):
   - API Key: `TEST-API-KEY-123`
   - Base URL: `https://api.ewards.test`
   - Brand ID: `ALPHA001`
4. Click **Approve**

**Expected Result:**
- Request status: **Approved**
- Alice's settings page shows eWards as Active

---

### TC-084 · Token Expiry Reminder Settings
**URL:** http://localhost:3000/settings/reminders (logged in as Alice)

**Steps:**
1. Navigate to `/settings/reminders`
2. Enable reminders: ON
3. Set days before expiry: `2`
4. Save settings

**Expected Result:**
- Settings saved successfully
- Tokens expiring within 2 days would trigger reminder (requires WhatsApp connected)

---

## MODULE 16 — GROWTH INSIGHTS

---

### TC-085 · Growth Dashboard — Weekly Digest
**URL:** http://localhost:3000/growth (logged in as Alice)

**Steps:**
1. Navigate to `/growth`
2. View Weekly Digest section

**Expected Result:**
- Shows this week's stats: New customers, Revenue, Benefit cost, Net value
- Best performing partnership this week shown
- All numbers are non-null

---

### TC-086 · Partnership Health Leaderboard
**URL:** http://localhost:3000/growth (logged in as Alice)

**Steps:**
1. View Partnership Health section in growth page

**Expected Result:**
- Partnerships ranked by health score (0-100)
- Health level badge: Strong/Good/Needs Attention
- Alpha x Beta shows a score

---

### TC-087 · Brand Profile Setup
**URL:** http://localhost:3000/growth (logged in as Alice)

**Steps:**
1. Navigate to growth page
2. Find Brand Profile section
3. Edit profile:
   - Description: `We are a premium restaurant in Mumbai offering authentic cuisine`
   - Logo URL: (any image URL)
4. Save

**Expected Result:**
- Profile saved
- Visit http://localhost:3000/b/testbrand-alpha (or slug) — description shows on public profile

---

## MODULE 17 — CUSTOMER PORTAL (OTP-BASED)

---

### TC-088 · Customer OTP Login
**URL:** http://localhost:3000/my-rewards

**Steps:**
1. Open http://localhost:3000/my-rewards in incognito
2. Enter phone: `9111111101` (Priya — uploaded customer)
3. Click **Send OTP**
4. (Dev mode) note the OTP from response or check API
5. Enter OTP
6. Click **Verify**

**Expected Result:**
- Logged in as Priya
- Redirected to `/my-rewards/dashboard`
- Shows loyalty balances from all merchants she has tokens with

---

### TC-089 · Customer Rewards Dashboard
**URL:** http://localhost:3000/my-rewards/dashboard

**Steps:**
1. Log in as customer Priya (9111111101)
2. View Rewards tab

**Expected Result:**
- Shows partner merchants where she has rewards
- Points balance and ₹ value shown per merchant
- Expand each → shows which outlet to redeem at
- Activity tab shows her claim/redemption history

---

### TC-090 · Customer — Non-Registered Phone
**URL:** http://localhost:3000/my-rewards

**Steps:**
1. Enter phone: `9000000000` (never registered)
2. Click **Send OTP**

**Expected Result:**
- OTP sent (member auto-created on first visit)
- OR error: "Phone not found" (depends on implementation)
- Either way — no crash

---

## MODULE 18 — SUPER ADMIN LOGOUT & SESSION

---

### TC-091 · Super Admin Logout
**URL:** http://localhost:3000/super-admin/dashboard (logged in as SA)

**Steps:**
1. Click **Sign out** in SA top nav
2. Observe redirect
3. Try navigating back to `/super-admin/dashboard`

**Expected Result:**
- Redirected to `/super-admin/login`
- Back navigation also redirects to login
- Token cleared from browser

---

### TC-092 · SA Session Persistence on Refresh
**URL:** http://localhost:3000/super-admin/dashboard (logged in as SA)

**Steps:**
1. Log in as SA
2. Navigate to any SA page
3. Refresh the page (F5)

**Expected Result:**
- Stays on the page — not logged out
- Token persisted in localStorage

---

## MODULE 19 — DATA INTEGRITY & EDGE CASES

---

### TC-093 · Token Expiry — 48-Hour Limit
**Steps:**
1. Issue a token
2. Note expiry time (48 hours after issue)
3. (Cannot test without time travel — verify expiry is shown correctly in UI)

**Expected Result:**
- Token detail shows: "Expires in 48 hours" or exact expiry datetime
- Backend enforces expiry on redemption attempt

---

### TC-094 · Partnership — Update Terms After Live
**URL:** http://localhost:3000/partnerships/[uuid] (logged in as Alice, partnership is Live)

**Steps:**
1. Open Alpha x Beta (Live status)
2. Try to edit partnership terms
3. Change min bill amount

**Expected Result:**
- Terms updatable OR locked after going live (verify which behavior applies)
- If locked: Edit button disabled or hidden
- If editable: changes applied to future claims only

---

### TC-095 · Credits — Attempt to Allocate 0
**URL:** http://localhost:3000/super-admin/merchants → Add Credits

**Steps:**
1. Enter Amount: `0`
2. Click **Allocate**

**Expected Result:**
- Validation error: "Amount must be at least 1"
- No credit record created

---

### TC-096 · Concurrent Session — Two Browsers
**Steps:**
1. Log in as Alice in Browser A (Chrome)
2. Log in as Alice in Browser B (Firefox)
3. Log out from Browser A
4. Try to use Browser B (same token)

**Expected Result:**
- Browser B also logged out (or continues until next API call fails)
- Next protected API call in Browser B → 401 → redirect to login

---

### TC-097 · SA Can View/Update Any Merchant
**URL:** http://localhost:3000/super-admin/merchants (logged in as SA)

**Steps:**
1. Go to SA merchant list
2. Click on "TestBrand Alpha"
3. View detail — all data visible
4. Try editing: toggle ecosystem_active OFF → Save

**Expected Result:**
- SA can view full merchant details
- SA can update merchant flags (is_active, ecosystem_active, open_to_partnerships)
- Changes reflected immediately

---

### TC-098 · Public Pages — No Auth Required
**Steps:**
1. In incognito window (no login):
2. Visit: http://localhost:3000/register → loads ✓
3. Visit: http://localhost:3000/login → loads ✓
4. Visit: http://localhost:3000/marketplace → loads ✓
5. Visit: http://localhost:3000/my-rewards → loads ✓
6. Visit: http://localhost:3000/dashboard → **redirected to /login** ✓
7. Visit: http://localhost:3000/partnerships → **redirected to /login** ✓
8. Visit: http://localhost:3000/super-admin/dashboard → **redirected to /super-admin/login** ✓

**Expected Result:**
- Public pages: accessible without login
- Protected pages: redirect to appropriate login

---

### TC-099 · eWards Integration Requests — Filter
**URL:** http://localhost:3000/super-admin/requests (logged in as SA)

**Steps:**
1. Select filter: **Pending** → see pending requests
2. Select filter: **Approved** → see approved requests
3. Select filter: **Rejected** → see rejected requests
4. Select filter: **All** → see everything

**Expected Result:**
- Each filter shows correct subset
- Status badges match filter

---

### TC-100 · SA Add Credits — Credit Ledger Verification
**URL:** http://localhost:3000/super-admin/merchants/[id] (logged in as SA)

**Steps:**
1. Note current credit balance for Alpha (e.g., 598)
2. Add 100 credits with note "Audit top-up"
3. Navigate to merchant detail → credit ledger

**Expected Result:**
- New balance: 698
- Ledger shows new entry: +100, "Audit top-up", date, balance after = 698
- All previous entries still visible (immutable history)

---

## MODULE 20 — FULL END-TO-END FLOW

---

### TC-101 · Complete New Brand Onboarding
**Steps:**
1. [TC-006] New brand registers at /register
2. [TC-011] SA sees it in Brand Requests → Pending
3. [TC-019] SA adds credits (200)
4. [TC-012] SA approves the brand
5. [TC-021] Brand logs in at /login
6. [TC-077] Brand sets point valuation
7. [TC-079] Brand enables discoverability
8. Complete — brand is fully onboarded and ready for partnerships

**Expected Result:**
- All steps succeed in sequence
- Brand has: active account, credits, point value set, visible to partners

---

### TC-102 · Complete Partnership + Token + Redemption Flow
**Steps:**
1. Alice logs in, creates partnership with Gina (Gamma)
2. Gina logs in, accepts → goes Live
3. Alice uploads customers for Gamma partnership
4. Alice issues token to customer (phone: 9700000001)
5. Gina logs in → /redeem → enters token + bill amount ₹300
6. Gina completes redemption
7. Alice checks dashboard → redemptions count +1
8. Alice checks partnership analytics → data updated

**Expected Result:**
- Full flow works end-to-end
- Data consistent across dashboard, analytics, ledger, redemption history

---

### TC-103 · Cross-Brand Token Redemption
**Steps:**
1. Alpha issues token → customer redeems at Beta ✓
2. Beta issues token → customer redeems at Alpha ✓
3. Both should work

**Expected Result:**
- Tokens flow both directions between partnered brands
- Each brand's analytics shows their side (sent vs received customers)

---

### TC-104 · Network Partnership Chain
**Steps:**
1. Alice creates network "Food Alliance"
2. Alice invites Bob → Bob joins
3. Bob invites Gina → Gina joins
4. From network, Alice creates partnership with Gina
5. Partnership shows up for both

**Expected Result:**
- Network has 3 members
- Partnership created within network context
- All members can see each other

---

### TC-105 · Campaign Segment from Partnership
**URL:** http://localhost:3000/campaigns (logged in as Alice)

**Steps:**
1. Create campaign
2. Set segment: **Partner Segment**
3. Select: Alpha x Beta partnership
4. Preview segment → shows Beta's customers who redeemed at Alpha

**Expected Result:**
- Segment count matches customers who visited from Beta
- Campaign targets only that cross-brand segment

---

### TC-106 · Offer on Digital Bill — Full Flow
**Steps:**
1. Alice creates offer: "10% Off with BETA10 coupon"
2. Alice attaches offer to Alpha x Beta partnership
3. Open public bill URL: http://localhost:3000/bill-offers/[alpha-uuid]
4. Observe offer appears
5. Click on offer → see details
6. Claim the offer (logs click)

**Expected Result:**
- Offer visible on public bill page without login
- Impression logged when page opens
- Claim logged when customer interacts with offer

---

### TC-107 · Customer Portal — Full Rewards View
**Steps:**
1. Customer Priya (9111111101) has tokens issued at Alpha for Beta
2. Login at /my-rewards
3. View Rewards tab
4. View Activity tab

**Expected Result:**
- Rewards tab: shows balance at Beta (pending redemption)
- After redeeming: Activity tab shows claim issued and redeemed
- ₹ values calculated based on merchant's point valuation rate

---

### TC-108 · SA Reject + Merchant Re-applies
**Steps:**
1. Merchant self-registers
2. SA rejects with reason "Missing GST"
3. Merchant registers again with different email but same brand name

**Expected Result:**
- Rejection visible in SA rejected list with reason
- New registration creates a new pending entry
- SA can approve the second attempt separately

---

### TC-109 · Discoverability + Partnership Proposal Chain
**Steps:**
1. Alice hides herself (discoverability OFF)
2. Bob searches for partners in Mumbai — Alice NOT visible
3. Alice re-enables discoverability
4. Bob searches again — Alice visible
5. Bob clicks "Send Proposal" to Alice from results
6. Alice gets proposal in her inbox
7. Alice accepts

**Expected Result:**
- Discoverability ON/OFF correctly controls visibility in discovery
- Discovery → Proposal → Accept chain works smoothly

---

### TC-110 · Multi-Outlet Partnership
**Steps:**
1. SA creates a brand with 2 outlets (manually via DB or UI if supported)
2. That brand creates a partnership — outlet-level scope
3. Select only outlet 1 (not outlet 2)
4. Issue token at outlet 1
5. Try to redeem at outlet 2 of same brand

**Expected Result:**
- Token issued at outlet 1 (participating)
- Redemption at outlet 2 blocked (not in partnership)
- Redemption at partner's outlet works correctly

---

---

## MODULE 21 — eWARDS OFFER STRUCTURE

---

### TC-111 · Create Offer — POS Redemption Type: Flat Monetary Discount
**URL:** http://localhost:3000/partner-offers/create (logged in as Alice)

**Steps:**
1. Navigate to `/partner-offers/create`
2. Fill basic fields: Title, Coupon Code, Expiry Date
3. Set **POS Redemption Type**: `Flat Monetary Discount`
4. Set **Discount Value**: `100`
5. Set **Issuance Limit**: `Unlimited`
6. Set **Redemption Limit**: `Unlimited`
7. Click **Save**

**Expected Result:**
- Offer created with pos_redemption_type = flat_monetary
- Detail page shows "Flat ₹100 Discount" clearly
- No percentage fields visible in flat mode

---

### TC-112 · Create Offer — POS Redemption Type: Percentage Discount
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set **POS Redemption Type**: `Percentage Discount`
2. Set **Discount %**: `20`
3. Enable **Maximum Cap**: ON
4. Set **Cap Amount**: `500`
5. Set Coupon Code: `PCT20`
6. Save

**Expected Result:**
- Offer created: 20% off, max cap ₹500
- Detail shows: "20% Off (up to ₹500)"
- pos_redemption_type = percentage_discount in API response

---

### TC-113 · Create Offer — POS Redemption Type: Offer (Generic)
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set **POS Redemption Type**: `Offer`
2. Fill all standard fields
3. Set Coupon Code: `OFFER001`
4. Save

**Expected Result:**
- Offer created as generic "Offer" type
- Detail page shows offer type: Offer
- No numeric discount field required for this type

---

### TC-114 · Create Offer — Percentage Without Max Cap
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set type: Percentage Discount
2. Set Discount %: `15`
3. Leave **Maximum Cap**: OFF (disabled)
4. Save

**Expected Result:**
- Offer created: 15% off, no cap
- API: max_discount_amount = null
- Detail shows: "15% Off (no cap)"

---

### TC-115 · Create Offer — With Issuance Limit
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set **Issuance Limit**: `Limited`
2. Set **Max Issuances**: `100`
3. Fill all other fields
4. Save

**Expected Result:**
- Offer created: max_issuance_count = 100
- When 100 tokens issued under this offer — offer auto-pauses or shows "limit reached"
- Detail page shows: "Issued: 0 / 100"

---

### TC-116 · Create Offer — With Redemption Limit
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set **Redemption Limit**: `Limited`
2. Set **Max Redemptions**: `50`
3. Fill other fields
4. Save

**Expected Result:**
- Offer created: max_redemption_count = 50
- After 50 redemptions — offer blocks further redemptions
- Detail page shows: "Redeemed: 0 / 50"

---

### TC-117 · Create Offer — Both Issuance + Redemption Limits
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set Issuance Limit: Limited → 200
2. Set Redemption Limit: Limited → 100
3. Save

**Expected Result:**
- Offer created with both limits
- Issuance stops at 200, redemptions stop at 100
- Both counters visible on detail page

---

### TC-118 · Edit Offer — Change POS Type
**URL:** http://localhost:3000/partner-offers/[uuid]/edit

**Steps:**
1. Open edit form for a Flat Monetary offer
2. Change POS type to Percentage Discount
3. Set %: 10
4. Save

**Expected Result:**
- Offer updated to percentage type
- Old flat_amount fields cleared
- New percentage + cap fields take effect

---

### TC-119 · Offer — Zero Discount Value Validation
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set POS type: Flat Monetary Discount
2. Set Discount Value: `0`
3. Click Save

**Expected Result:**
- Validation error: "Discount value must be greater than 0"
- Offer not created

---

### TC-120 · Offer — Negative Discount Validation
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set Discount Value: `-50`
2. Click Save

**Expected Result:**
- Validation error: "Discount value must be positive"
- Offer not created

---

### TC-121 · Offer — Percentage Above 100% Validation
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set POS type: Percentage Discount
2. Set Discount %: `150`
3. Click Save

**Expected Result:**
- Validation error: "Percentage cannot exceed 100"
- Offer not created

---

### TC-122 · Offer — Duplicate Coupon Code Validation
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Create offer with Coupon Code: `UNIQUE001`
2. Create another offer with the same Coupon Code: `UNIQUE001`
3. Click Save on second offer

**Expected Result:**
- Validation error: "Coupon code already in use"
- Second offer not created

---

### TC-123 · Offer — Attach to Partnership During Creation (Link Existing Offer)
**URL:** http://localhost:3000/networks/[uuid] or /partnerships (create modal)

**Steps:**
1. Open partnership creation modal
2. Switch to **"Link Existing Offer"** tab
3. Select an existing offer from dropdown
4. Confirm terms are auto-filled from offer
5. Submit

**Expected Result:**
- Partnership created with linked offer
- Partnership detail shows offer in "Linked Offers" section
- Offer's discount terms reflected in partnership terms

---

### TC-124 · Offer — View on Partnership Detail (Linked Offers Section)
**URL:** http://localhost:3000/partnerships/[uuid] (Live partnership, logged in as Alice)

**Steps:**
1. Open Live partnership detail
2. Scroll to "Linked Offers" section

**Expected Result:**
- Offers attached to this partnership listed
- Each offer shows: title, type, coupon code, discount, status
- Link to offer detail visible

---

### TC-125 · Offer — Detach from Partnership
**URL:** http://localhost:3000/partner-offers/[uuid]

**Steps:**
1. Open offer detail
2. Find "Attached Partnerships" list
3. Click **Detach** for a specific partnership
4. Confirm

**Expected Result:**
- Offer removed from that partnership
- Partnership detail no longer shows that offer
- Offer still exists — just not attached

---

### TC-126 · Offer — Publish to Network
**URL:** http://localhost:3000/partner-offers/[uuid]

**Steps:**
1. Open offer detail
2. Find "Publish to Network" section
3. Select a network (Mumbai Food Alliance)
4. Click **Publish**

**Expected Result:**
- Offer visible to all network members
- Network members can attach it to their partnerships
- Offer shows "Published" badge for that network

---

### TC-127 · Offer — Unpublish from Network
**URL:** http://localhost:3000/partner-offers/[uuid]

**Steps:**
1. Open offer detail for a published offer
2. Click **Unpublish** for the network
3. Confirm

**Expected Result:**
- Offer no longer visible in network offers list
- Network members cannot attach it
- Offer itself still exists and active

---

### TC-128 · Offer List — Filter by POS Type
**URL:** http://localhost:3000/partner-offers (logged in as Alice)

**Steps:**
1. Navigate to `/partner-offers`
2. Filter by POS Type: "Percentage Discount"

**Expected Result:**
- Only percentage-type offers shown
- Flat and Offer-type entries hidden

---

### TC-129 · Offer — Network Offers Tab
**URL:** http://localhost:3000/partner-offers (logged in as Bob)

**Steps:**
1. Bob is member of Mumbai Food Alliance
2. Navigate to `/partner-offers`
3. Switch to "Network Offers" tab

**Expected Result:**
- Offers published by other network members visible
- Alice's published offers shown to Bob
- Bob can attach them to his own partnerships

---

### TC-130 · Offer — Issuance Limit Exhausted
**Steps (API):**
1. Create offer with max_issuance_count = 2
2. Attach to a partnership
3. Issue 2 tokens under this offer
4. Try to issue a 3rd token

**Expected Result:**
- 3rd token issuance fails: "Offer issuance limit reached"
- Offer status shows as exhausted or auto-inactive
- Existing 2 tokens still valid

---

### TC-131 · Offer Detail — View All Counters
**URL:** http://localhost:3000/partner-offers/[uuid]

**Steps:**
1. Open any offer with both limits set
2. View the statistics section

**Expected Result:**
- Shows: Issuances (used/max), Redemptions (used/max)
- Shows: Attached partnerships count
- Shows: Created date, last modified

---

### TC-132 · Create Offer — Title Too Long Validation
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set Title: 256-character string
2. Click Save

**Expected Result:**
- Validation error: "Title must be under 255 characters"
- Offer not saved

---

### TC-133 · Offer — Expired Date Blocks Creation
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Set Expiry Date: yesterday's date
2. Click Save

**Expected Result:**
- Validation error: "Expiry date must be in the future"
- Offer not created

---

### TC-134 · Offer — Toggle Inactive → Cannot be Attached
**Steps:**
1. Toggle an offer to Inactive
2. Try to attach it to a partnership (via API or UI)

**Expected Result:**
- Error: "Cannot attach an inactive offer"
- Partnership remains without that offer

---

### TC-135 · Offer — Available for Partnership Endpoint
**API:** GET /api/partner-offers/available/{partnershipUuid}

**Steps:**
1. Have 3 offers: 2 active, 1 inactive
2. Call the endpoint with a valid partnership UUID
3. Check response

**Expected Result:**
- Only active offers returned
- Inactive offer excluded
- Response is array of offer objects with id, title, type, discount fields

---

### TC-136 · Offer — Create with No POS Type Selected
**URL:** http://localhost:3000/partner-offers/create

**Steps:**
1. Fill all fields except POS Redemption Type
2. Click Save

**Expected Result:**
- Validation error: "POS Redemption Type is required"
- OR defaults to first type automatically

---

### TC-137 · Offer — Max Cap Higher Than 100% Discount Value
**Steps:**
1. Set POS type: Percentage 50%
2. Set Max Cap: ₹200
3. Bill amount: ₹300 → 50% = ₹150 (under cap) → discount = ₹150
4. Bill amount: ₹500 → 50% = ₹250 (over cap) → discount = ₹200

**Expected Result:**
- System applies lower of: calculated % or max cap
- This logic visible in redemption lookup result

---

### TC-138 · Offer List — Search by Coupon Code
**URL:** http://localhost:3000/partner-offers

**Steps:**
1. Navigate to offer list
2. Search for coupon code: `BETA15`

**Expected Result:**
- Only offer with coupon BETA15 returned
- All other offers hidden
- Search is case-insensitive

---

### TC-139 · Offer — Merchant A Cannot Edit Merchant B's Offer
**API:** PUT /api/partner-offers/[bob-uuid]

**Steps:**
1. Log in as Alice
2. Try to update an offer that belongs to Bob

**Expected Result:**
- 403 Forbidden returned
- Alice's token cannot modify Bob's offers
- Error: "Unauthorized"

---

### TC-140 · Offer — Delete Not Allowed if Attached
**Steps:**
1. Create offer, attach to a partnership
2. Try to delete the offer

**Expected Result:**
- Error: "Cannot delete offer that is attached to partnerships"
- Offer still exists
- Must detach from all partnerships before deleting

---

## MODULE 22 — FOLLOW-UP CAMPAIGNS

---

### TC-141 · View Follow-Up Campaign Stats
**URL:** http://localhost:3000/followup-campaigns (logged in as Alice)

**Steps:**
1. Navigate to `/followup-campaigns`
2. View stats section at top

**Expected Result:**
- Stats shown: Total expired unredeemed tokens, Campaigns sent, Customers re-engaged
- Numbers are non-null (may be 0 if no data)

---

### TC-142 · Create Follow-Up Campaign — Basic
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Click **Create Follow-Up Campaign**
2. Fill:
   - Name: `Expiry Nudge Campaign`
   - Target: Expired unredeemed tokens (last 7 days)
   - Message: `Your token has expired. Visit us again to get a new one!`
3. Click **Create**

**Expected Result:**
- Campaign created with status Draft or Scheduled
- Appears in campaigns list
- Target count shown (customers with expired unredeemed tokens)

---

### TC-143 · Follow-Up Campaign — Partnership-Specific Target
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Create follow-up campaign
2. Set Target: Specific partnership (Alpha x Beta)
3. Set look-back window: last 14 days

**Expected Result:**
- Campaign targets only Beta customers who received Alpha tokens and didn't redeem
- Preview count shows correct segment size

---

### TC-144 · Follow-Up Campaign — Edit Message
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Open an existing draft follow-up campaign
2. Click **Edit**
3. Change message to: `Don't miss out! Your exclusive offer is waiting at Beta.`
4. Save

**Expected Result:**
- Message updated
- Campaign still in Draft status
- Old message replaced

---

### TC-145 · Follow-Up Campaign — Toggle Active/Inactive
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Find an active follow-up campaign
2. Toggle to Inactive
3. Observe behavior

**Expected Result:**
- Campaign paused — no messages sent
- Toggle back to Active — resumes
- Status badge updates correctly

---

### TC-146 · Follow-Up Campaign — Required Fields Validation
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Try to create follow-up campaign without a name
2. Try without a message
3. Try without target

**Expected Result:**
- Error: "Campaign name is required"
- Error: "Message is required"
- Error: "Target segment required"

---

### TC-147 · Follow-Up Campaign — View List
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Navigate to `/followup-campaigns`
2. View all campaigns

**Expected Result:**
- List shows: name, status, target segment, sent count, last run
- Each row has Edit and Toggle buttons
- Sorted by created_at descending

---

### TC-148 · Follow-Up Campaign — Zero Recipients Warning
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Create follow-up campaign targeting: last 1 day (probably no expired tokens today)
2. Try to send

**Expected Result:**
- Warning: "No customers match this segment"
- Campaign not dispatched
- Helpful message to broaden the date range

---

### TC-149 · Follow-Up Campaign — Credits Check Before Send
**URL:** http://localhost:3000/followup-campaigns (logged in as Gina — no credits)

**Steps:**
1. Gina has 0 WhatsApp credits
2. Try to send a follow-up campaign

**Expected Result:**
- Error: "Insufficient WhatsApp credits to send campaign"
- Campaign not sent
- Link to top-up credits shown

---

### TC-150 · Follow-Up Campaign — Message Variables
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Create follow-up campaign with message:
   `Hi {{customer_name}}, your token {{token}} at {{partnership_name}} expired. Visit again!`
2. Save and view preview

**Expected Result:**
- Preview replaces variables with sample data
- Variables: customer_name, token, partnership_name are supported
- Unknown variables shown as-is or flagged

---

### TC-151 · Follow-Up Stats — Update After Campaign
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Note initial stats (sent count, re-engaged count)
2. Send a follow-up campaign to 5 customers
3. Return to stats view

**Expected Result:**
- Sent count increases by 5
- Re-engaged count updates when those customers return

---

### TC-152 · Follow-Up Campaign — Update via API
**API:** PUT /api/followup-campaigns/{id}

**Steps:**
1. Get list of follow-up campaigns
2. Note a campaign ID
3. PUT update with new message

**Expected Result:**
- 200 OK returned
- Campaign message updated in response
- GET list shows updated message

---

### TC-153 · Follow-Up Campaign — Multiple Campaigns Same Segment
**Steps:**
1. Create 3 follow-up campaigns targeting the same expired token segment
2. Check if overlap handling exists

**Expected Result:**
- Multiple campaigns can target same segment
- Each campaign is independent
- Customer may receive multiple messages (no dedup by default) OR system deduplicates
- Behavior documented/visible in UI

---

### TC-154 · Follow-Up Campaign — Name Length Validation
**Steps:**
1. Create follow-up campaign with name: 256-char string
2. Click Save

**Expected Result:**
- Validation error: "Name must be under 255 characters"

---

### TC-155 · Follow-Up Campaign — Message Length Validation
**Steps:**
1. Create follow-up campaign with message: 1001+ characters
2. Click Save

**Expected Result:**
- Validation error: "Message must be under 1000 characters"
- Character counter visible in textarea

---

## MODULE 23 — PARTNERSHIP ANNOUNCEMENTS

---

### TC-156 · Announce Partnership — Preview
**URL:** http://localhost:3000/partnerships/[uuid] (Live partnership, logged in as Alice)

**Steps:**
1. Open a Live partnership
2. Find "Announce to Your Customers" section
3. Customize the message or use default
4. Click **Preview**

**Expected Result:**
- Preview modal shows the WhatsApp message template
- Customer name placeholder shown
- Partnership name, benefit details filled in
- "Send to X customers" count shown

---

### TC-157 · Announce Partnership — Send
**URL:** http://localhost:3000/partnerships/[uuid]

**Steps:**
1. Open Live partnership
2. Preview announcement
3. Click **Send Announcement**
4. Confirm send

**Expected Result:**
- Announcement dispatched (logged — WhatsApp not connected in dev)
- Success message: "Announcement sent to X customers"
- History section shows the sent announcement

---

### TC-158 · Announcement — View History
**URL:** http://localhost:3000/partnerships/[uuid]

**Steps:**
1. Open partnership with at least one announcement sent
2. View Announcement History section

**Expected Result:**
- List of past announcements
- Each shows: date sent, message excerpt, recipients count
- Sorted newest first

---

### TC-159 · Announcement — Cannot Send to Paused Partnership
**URL:** http://localhost:3000/partnerships/[uuid] (Paused partnership)

**Steps:**
1. Pause a Live partnership
2. Try to click **Send Announcement**

**Expected Result:**
- Button disabled or hidden when partnership is Paused
- OR error: "Cannot announce for a paused partnership"

---

### TC-160 · Announcement — Custom Message
**URL:** http://localhost:3000/partnerships/[uuid]

**Steps:**
1. Open Live partnership
2. Enter custom message: `Exciting news! Alpha x Beta partnership is live. Show this message at Beta for exclusive discount!`
3. Click Preview then Send

**Expected Result:**
- Custom message used instead of default template
- History shows custom message content
- Character limit enforced (max 1000 chars)

---

### TC-161 · Announcement — Credits Deducted After Send
**Steps:**
1. Note Alice's WhatsApp credit balance
2. Send announcement to N customers
3. Check credits again

**Expected Result:**
- Credits reduced by N (1 per customer)
- If credits insufficient: error before sending
- Ledger shows deduction entry

---

### TC-162 · Announcement — Zero Customers Warning
**Steps:**
1. Alice has 0 customers in her CRM
2. Try to send partnership announcement

**Expected Result:**
- Warning: "No customers to announce to"
- OR sends to 0 recipients (shown in history as 0)

---

### TC-163 · Announcement Preview API
**API:** POST /api/partnerships/{uuid}/announcements/preview

**Steps:**
1. Send authenticated POST with body: `{"message": "Custom announcement"}`
2. Check response

**Expected Result:**
- 200 OK
- Response: `{preview: "...", recipient_count: N}`
- Message rendered with variables filled

---

### TC-164 · Announcement History API
**API:** GET /api/partnerships/{uuid}/announcements

**Steps:**
1. Send authenticated GET to announcements endpoint
2. Check response

**Expected Result:**
- 200 OK
- Array of announcement records
- Each: id, message, sent_at, recipient_count

---

### TC-165 · Announcement — Default Template Used When No Custom Message
**Steps:**
1. Open partnership detail
2. Leave message field blank
3. Click Preview

**Expected Result:**
- Default template loaded automatically
- Template includes partnership name and benefit details
- Preview shows rendered default

---

## MODULE 24 — WHATSAPP DELIVERY TRACKING

---

### TC-166 · Delivery Stats — View Summary
**URL:** http://localhost:3000/settings (logged in as Alice) or API

**API:** GET /api/delivery/stats

**Steps:**
1. Authenticate as Alice
2. GET /api/delivery/stats
3. Review response

**Expected Result:**
- 200 OK
- Response: `{total_sent: N, delivered: N, failed: N, pending: N}`
- All counts are integers

---

### TC-167 · Delivery Stats — Recent Failures
**API:** GET /api/delivery/failures

**Steps:**
1. Authenticate as Alice
2. GET /api/delivery/failures
3. Review response

**Expected Result:**
- 200 OK
- Array of recent failed delivery attempts
- Each: phone, timestamp, failure_reason, partnership_name

---

### TC-168 · Delivery Status — Token Issued
**Steps:**
1. Issue a token to a customer phone
2. Check delivery stats

**Expected Result:**
- delivery_status on claim = 'sent' (logged in DB)
- Stats show total_sent +1
- No actual WhatsApp in dev mode

---

### TC-169 · Delivery Stats — Unauthenticated Access
**API:** GET /api/delivery/stats (no token)

**Steps:**
1. Call endpoint without Authorization header

**Expected Result:**
- 401 Unauthorized
- No stats returned

---

### TC-170 · Delivery Stats — Different Merchant Isolation
**Steps:**
1. Get delivery stats as Alice
2. Get delivery stats as Bob
3. Compare

**Expected Result:**
- Each merchant sees only their own delivery stats
- Alice's stats don't include Bob's messages
- Data isolation enforced by auth middleware

---

### TC-171 · Delivery — Failed Status on Bad Phone Number
**Steps (API):**
1. Issue token to phone: `0000000000` (invalid format)
2. Check delivery_status in DB

**Expected Result:**
- Claim created but delivery_status = 'failed'
- Stats shows failed +1
- Phone validation may reject before saving

---

### TC-172 · Delivery Failures List — Pagination
**API:** GET /api/delivery/failures?page=2

**Steps:**
1. Have more than 20 failures
2. Request page 2

**Expected Result:**
- 200 OK
- Second page of failures returned
- Pagination metadata in response

---

### TC-173 · Delivery Stats — Date Range Filter
**API:** GET /api/delivery/stats?from=2026-01-01&to=2026-01-31

**Steps:**
1. Call with date range parameters
2. Check if filtering is applied

**Expected Result:**
- Stats scoped to the date range
- Totals only count records in that period

---

### TC-174 · Delivery Stats Refresh
**URL:** http://localhost:3000 → settings (if stats widget exists in UI)

**Steps:**
1. View delivery stats widget
2. Issue 3 new tokens
3. Refresh stats

**Expected Result:**
- total_sent increases by 3
- UI refreshes without full page reload

---

### TC-175 · Delivery — Token With No Phone (Anonymous)
**Steps:**
1. Issue token without providing a customer phone
2. Check delivery_status

**Expected Result:**
- Claim created with customer_phone = null
- delivery_status = 'not_applicable' or 'skipped'
- Stats: neither sent nor failed for anonymous tokens

---

## MODULE 25 — SHAREABLE CLAIM LINKS

---

### TC-176 · Generate Shareable Link
**URL:** http://localhost:3000/partnerships/[uuid] (Live partnership, logged in as Alice)

**Steps:**
1. Open Live partnership detail
2. Find "Shareable Link" section
3. Click **Generate Link**

**Expected Result:**
- Link generated: `http://localhost:3000/shared/XXXXXXXX` (8-char code)
- Code is alphanumeric
- Copy button works
- WhatsApp share button present

---

### TC-177 · Use Shareable Link — Claim Token
**URL:** http://localhost:3000/shared/[code] (incognito window)

**Steps:**
1. Open shareable link in incognito
2. Enter phone number
3. Select target outlet
4. Click **Get Token**

**Expected Result:**
- Token issued without requiring login
- Token code displayed
- Expiry shown
- Same flow as QR claim

---

### TC-178 · Shareable Link — Invalid Code
**URL:** http://localhost:3000/shared/BADCODE

**Steps:**
1. Navigate to URL with invalid/random code

**Expected Result:**
- Error page: "This link is invalid or has expired"
- No token issued
- No crash

---

### TC-179 · Shareable Link — Expired Partnership
**Steps:**
1. Generate shareable link for a Live partnership
2. Pause the partnership
3. Try to use the shareable link

**Expected Result:**
- Error: "This partnership is no longer active"
- Token not issued
- User redirected with informative message

---

### TC-180 · Shareable Link — Rate Limited
**Steps:**
1. Send 30+ requests to `/api/shared-claim/{code}` within 1 minute

**Expected Result:**
- After limit: 429 Too Many Requests
- Retry-After header present
- Earlier requests succeed normally

---

### TC-181 · Shareable Link API — Generate
**API:** POST /api/partnerships/{uuid}/share-link

**Steps:**
1. Authenticated POST to generate link
2. Check response

**Expected Result:**
- 200 OK
- Response: `{code: "XXXXXXXX", url: "http://...", partnership_name: "..."}`

---

### TC-182 · Shareable Link — Multiple Links for Same Partnership
**Steps:**
1. Generate 3 shareable links for same partnership
2. Use each link

**Expected Result:**
- Each generates different unique code
- All 3 work independently
- Each claim tracked to respective link (for analytics)

---

### TC-183 · Shareable Link — Copy to Clipboard
**URL:** http://localhost:3000/partnerships/[uuid]

**Steps:**
1. Click **Copy Link** button after generating

**Expected Result:**
- Browser clipboard updated with full URL
- "Copied!" confirmation shown briefly
- Link visible in field

---

### TC-184 · Shareable Link — WhatsApp Share Button
**URL:** http://localhost:3000/partnerships/[uuid]

**Steps:**
1. After generating link, click **Share on WhatsApp** button

**Expected Result:**
- Opens WhatsApp with pre-filled message containing the link
- Message includes partnership name and benefit description
- Works on mobile (opens app) and desktop (opens WhatsApp Web)

---

### TC-185 · Shareable Link Landing Page — No Outlet Required
**URL:** http://localhost:3000/shared/[code] (single-outlet partnership)

**Steps:**
1. Generate link for single-outlet partnership
2. Open link
3. Observe form

**Expected Result:**
- If only one outlet: outlet auto-selected, no dropdown shown
- User just enters phone and claims
- Simpler UX for single-outlet scenario

---

## MODULE 26 — PARTNER RATINGS

---

### TC-186 · Rate Partner — 5 Stars
**URL:** http://localhost:3000/partnerships/[uuid] (Live partnership, logged in as Alice)

**Steps:**
1. Open partnership detail
2. Find "Rate Your Partner" section
3. Click 5th star
4. Add review: `Excellent partnership quality. High referral volume.`
5. Click **Submit Rating**

**Expected Result:**
- Rating saved: 5 stars
- Review text saved
- Section shows: "Your rating: ★★★★★"
- Submit button changes to "Update Rating"

---

### TC-187 · Rate Partner — 1 Star (Bad Experience)
**URL:** http://localhost:3000/partnerships/[uuid]

**Steps:**
1. Click 1st star
2. Add review: `Poor redemption rate. Customers not returning.`
3. Submit

**Expected Result:**
- 1-star rating saved
- Review saved
- No minimum star requirement enforced

---

### TC-188 · Rate Partner — Update Rating
**URL:** http://localhost:3000/partnerships/[uuid]

**Steps:**
1. Alice already gave 3-star rating
2. Click 5th star to update
3. Submit

**Expected Result:**
- Rating updated to 5 stars
- Old rating replaced (upsert behavior)
- Updated timestamp visible

---

### TC-189 · View Received Ratings
**URL:** http://localhost:3000/partnerships/[uuid] (logged in as Bob — being rated by Alice)

**Steps:**
1. Open partnership detail from Bob's perspective
2. Find "Partner's Rating of You" or ratings section

**Expected Result:**
- Shows: rating stars, review text, given by (partner brand name), date
- Bob can see Alice rated him

---

### TC-190 · Ratings API — Rate Endpoint
**API:** POST /api/partnerships/{uuid}/rate

**Steps:**
1. POST: `{rating: 4, review: "Good partnership"}`
2. Authenticated as Alice

**Expected Result:**
- 200 OK
- Response: `{message: "Rating submitted", rating: 4}`

---

### TC-191 · Ratings API — Get Ratings
**API:** GET /api/partnerships/{uuid}/ratings

**Steps:**
1. GET ratings for a partnership

**Expected Result:**
- 200 OK
- Array of ratings with: id, rating, review, created_by_merchant, created_at

---

### TC-192 · Rating — Invalid Score (0 or 6+)
**API:** POST /api/partnerships/{uuid}/rate

**Steps:**
1. POST: `{rating: 0}` or `{rating: 6}`

**Expected Result:**
- 422 Validation error
- Message: "Rating must be between 1 and 5"

---

### TC-193 · Rating — Without Active Partnership
**Steps:**
1. Try to rate a Proposed or Rejected partnership

**Expected Result:**
- Error: "Can only rate active or paused partnerships"
- OR endpoint returns 422

---

### TC-194 · Rating — Cannot Rate Own Partnership (Self)
**Steps:**
1. A brand tries to rate themselves
2. POST to own partnership as both sides

**Expected Result:**
- Not applicable (rating is always of the OTHER brand)
- System identifies Alice's rating as Alice's view of Bob
- No self-rating possible

---

### TC-195 · Merchant Trust Score — Updated After Rating
**API:** GET /api/super-admin/merchants/{id}

**Steps:**
1. Note TestBrand Beta's trust_score before any ratings
2. Alice gives Beta a 5-star rating
3. Check trust_score again

**Expected Result:**
- Trust score increases (based on average of received ratings)
- SA merchant detail shows updated trust_score
- Score visible to super admin

---

### TC-196 · Rating — Review Text is Optional
**Steps:**
1. Submit rating: 4 stars, no review text
2. Check response

**Expected Result:**
- Rating saved: 4 stars, review = null
- No validation error for missing review
- Partnership shows star rating without review text

---

### TC-197 · Rating — Review Max Length
**Steps:**
1. Submit rating with review text: 1001+ characters
2. Check response

**Expected Result:**
- Validation error: "Review must be under 1000 characters"
- Rating not saved

---

### TC-198 · Merchant Ratings — Public View
**API:** GET /api/merchants/{id}/ratings

**Steps:**
1. Get all ratings received by a merchant across all partnerships

**Expected Result:**
- 200 OK
- Array of ratings from all partnerships
- Each: rating, review, partnership_name, from_brand, date

---

### TC-199 · Rating — Average Calculation
**Steps:**
1. Brand Beta receives ratings: 5, 3, 4 from 3 different partnerships
2. Average = (5+3+4)/3 = 4.0
3. Check trust_score

**Expected Result:**
- trust_score = 4.0 (or scaled version)
- Correctly calculates average across all received ratings

---

### TC-200 · Rating — After Partnership Rejection
**Steps:**
1. Partnership proposed and rejected
2. Try to rate the other party

**Expected Result:**
- Rating not possible for rejected partnerships
- Error: "Cannot rate this partnership"

---

## MODULE 27 — TOKEN EXPIRY REMINDER SETTINGS

---

### TC-201 · View Reminder Settings — Default State
**URL:** http://localhost:3000/settings/reminders (logged in as Alice)

**Steps:**
1. Navigate to `/settings/reminders`
2. Observe default state

**Expected Result:**
- Page loads correctly
- Default: reminders disabled (toggle = OFF)
- Default hours_before: 12
- Message template: empty (shows placeholder text)

---

### TC-202 · Enable Reminders
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Toggle "Enable expiry reminders" to ON
2. Click **Save Settings**

**Expected Result:**
- Success: "Settings saved successfully"
- Toggle stays ON after save
- Reminder system will run for this merchant

---

### TC-203 · Set Hours Before Expiry — 3 Hours
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Enable reminders
2. Select "3 hours before expiry" from dropdown
3. Save

**Expected Result:**
- remind_hours_before = 3 saved
- Reminders sent 3 hours before token expiry

---

### TC-204 · Set Hours Before Expiry — 24 Hours
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Select "24 hours before expiry"
2. Save

**Expected Result:**
- remind_hours_before = 24 saved
- Dropdown shows 24 hours selected after save

---

### TC-205 · Custom Message Template
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Enable reminders
2. Enter custom message: `Hi! Don't forget your token {{token}} for {{partnership_name}} expires in {{hours}} hours!`
3. Save

**Expected Result:**
- Template saved
- Preview shows rendered template with sample values
- Characters count shows correct number / 1000

---

### TC-206 · Clear Custom Template — Uses Default
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Open reminder settings with custom template set
2. Clear the message template field (leave empty)
3. Save

**Expected Result:**
- Settings saved with null template
- System uses default template for reminders
- Placeholder text visible in empty textarea

---

### TC-207 · Reminder Settings — GET API
**API:** GET /api/reminders/settings

**Steps:**
1. Authenticate as Alice
2. GET /api/reminders/settings

**Expected Result:**
- 200 OK
- Response: `{reminder_enabled: true/false, remind_hours_before: 12, message_template: null}`

---

### TC-208 · Reminder Settings — PUT API
**API:** PUT /api/reminders/settings

**Steps:**
1. PUT: `{reminder_enabled: true, remind_hours_before: 6, message_template: "Test template"}`

**Expected Result:**
- 200 OK
- Updated settings returned in response
- GET confirms new values

---

### TC-209 · Reminder Settings — Invalid Hours Value
**API:** PUT /api/reminders/settings

**Steps:**
1. PUT: `{remind_hours_before: 99}` (not in allowed list of 3,6,12,24)

**Expected Result:**
- 422 Validation error
- Message: "Invalid hours value. Allowed: 3, 6, 12, 24"

---

### TC-210 · Reminder Settings — Disable Stops Reminders
**Steps:**
1. Reminder enabled, set to 6 hours
2. Disable reminder (toggle OFF)
3. Save
4. Token expiry passes within 6 hours

**Expected Result:**
- No reminder sent (disabled)
- Settings saved: reminder_enabled = false
- Toggle shows OFF state

---

### TC-211 · Reminder Settings — Unauthenticated Access
**API:** GET /api/reminders/settings (no token)

**Steps:**
1. Call without Authorization header

**Expected Result:**
- 401 Unauthorized
- Settings not returned

---

### TC-212 · Reminder Settings — Merchant Isolation
**Steps:**
1. Alice enables reminders: 6 hours, custom message
2. Bob checks his own reminder settings
3. Compare

**Expected Result:**
- Bob's settings are independent (default = disabled)
- Alice's settings don't affect Bob
- Each merchant has own reminder configuration

---

### TC-213 · Reminder Message Template — Character Counter
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Start typing in message template field
2. Observe character counter

**Expected Result:**
- Counter shows: "X / 1000" as typing
- Counter turns red near limit (e.g., > 900 chars)
- Save blocked or warned at 1000 chars

---

### TC-214 · Reminder Settings — Auto-Disabled at Zero Credits
**Steps:**
1. Merchant has 0 WhatsApp credits
2. Try to enable reminders and save

**Expected Result:**
- Warning shown: "You have 0 credits. Reminders won't be sent until credits are added."
- Settings can still be saved (for future use)
- No error blocking save

---

### TC-215 · Lead Time Options — Dropdown Shows Correct Values
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Open lead time dropdown
2. Observe all available options

**Expected Result:**
- Exactly 4 options: 3 hours, 6 hours, 12 hours, 24 hours
- No other values available
- Each shows "X hours before expiry" text

---

## MODULE 28 — API SECURITY & AUTHORIZATION

---

### TC-216 · Unauthenticated Access — Protected Routes Return 401
**API:** Multiple endpoints

**Steps:**
1. Without any Authorization header, call:
   - GET /api/partnerships
   - GET /api/analytics/summary
   - GET /api/customers
   - GET /api/reminders/settings
   - GET /api/delivery/stats
2. Check each response

**Expected Result:**
- All return 401 Unauthorized
- Body: `{message: "Unauthenticated"}`
- No data leakage

---

### TC-217 · Cross-Merchant Data Access — Partnerships
**Steps:**
1. Log in as Alice, note Alice's partnership UUID
2. Log in as Bob
3. Try GET /api/partnerships/{alice-uuid} as Bob

**Expected Result:**
- 404 Not Found OR 403 Forbidden
- Bob cannot see Alice's private partnership data

---

### TC-218 · Cross-Merchant Data Access — Customers
**Steps:**
1. Alice uploads customers
2. Log in as Bob
3. GET /api/customers as Bob

**Expected Result:**
- Returns only Bob's customers (empty if none)
- Alice's uploaded customers NOT returned

---

### TC-219 · Cross-Merchant Data Access — Analytics
**Steps:**
1. Get Alice's partnership UUID
2. Authenticate as Bob
3. GET /api/analytics/partnerships/{alice-partnership-uuid}

**Expected Result:**
- 403 Forbidden or 404
- Bob cannot view Alice's partnership analytics

---

### TC-220 · Expired Token — 401 After Logout
**Steps:**
1. Log in as Alice, note the JWT token
2. Log out (POST /api/auth/logout)
3. Try to use the old token for GET /api/partnerships

**Expected Result:**
- 401 Unauthorized
- Token invalidated on logout (Sanctum)

---

### TC-221 · Super Admin Cannot Access Merchant Routes
**Steps:**
1. Log in as Super Admin
2. Use SA token for: GET /api/partnerships
3. Use SA token for: GET /api/analytics/summary

**Expected Result:**
- 401 Unauthorized (SA token has different guard)
- Merchant routes require merchant Sanctum token

---

### TC-222 · Merchant Token Cannot Access SA Routes
**Steps:**
1. Log in as Alice (merchant)
2. Use merchant token for: GET /api/super-admin/merchants

**Expected Result:**
- 403 Forbidden (middleware `super_admin` blocks non-SA users)

---

### TC-223 · CORS — API Accessible from Frontend Origin
**Steps:**
1. Frontend at http://localhost:3000
2. Make API call to http://localhost:8000/api/partnerships
3. Check response headers

**Expected Result:**
- Access-Control-Allow-Origin: * or http://localhost:3000
- No CORS error in browser console
- API call succeeds

---

### TC-224 · Rate Limiting — Public Claim (5 per minute)
**Steps:**
1. Send 5 POST /api/public/claims requests within 1 minute
2. Send 6th request

**Expected Result:**
- Requests 1-5: 200 OK (or 422 if invalid data)
- Request 6: 429 Too Many Requests
- Retry-After header indicates wait time

---

### TC-225 · Rate Limiting — Customer OTP (20 per minute)
**Steps:**
1. Send 20 POST /api/customer/send-otp requests within 1 minute
2. Send 21st request

**Expected Result:**
- First 20: processed (or 422 if bad phone)
- 21st: 429 Too Many Requests

---

### TC-226 · SQL Injection — Login Endpoint
**API:** POST /api/auth/login

**Steps:**
1. POST: `{email: "'; DROP TABLE users; --", password: "anything"}`

**Expected Result:**
- 422 Validation error (email format invalid)
- OR 401 Invalid credentials
- Database not corrupted
- Tables still exist after request

---

### TC-227 · XSS — Brand Name in Response
**Steps:**
1. Register brand with name: `<script>alert('xss')</script>`
2. Fetch the brand in SA list
3. Display in browser

**Expected Result:**
- Brand name stored and returned as escaped string
- No JavaScript execution in browser
- Rendered as literal text: `&lt;script&gt;...`

---

### TC-228 · Mass Assignment Protection — Cannot Set Admin Role
**API:** POST /api/auth/register or PUT /api/merchant/settings

**Steps:**
1. Send registration with extra field: `{is_admin: true, role: "super_admin"}`

**Expected Result:**
- Extra fields ignored by Laravel model
- User created as regular merchant
- No privilege escalation

---

### TC-229 · Authorization — Cannot Pause Another Brand's Partnership
**Steps:**
1. Note Alice's partnership UUID (she created with Bob)
2. Authenticate as Gina (third party, not in partnership)
3. POST /api/partnerships/{uuid}/pause as Gina

**Expected Result:**
- 403 Forbidden or 404
- Partnership not paused
- Only participants can manage their partnerships

---

### TC-230 · Authorization — Network Owner Can Invite
**Steps:**
1. Alice creates network
2. Bob tries POST /api/merchant/networks/{uuid}/invite
3. Gina (owner) tries POST /api/merchant/networks/{uuid}/invite

**Expected Result:**
- Bob (non-owner): 403 Forbidden
- Gina (owner): 200 OK, invite link generated

---

### TC-231 · Token Format Validation — Redemption
**API:** GET /api/execution/lookup/{token}

**Steps:**
1. Lookup: `HLPTEST` (7 chars, not 8)
2. Lookup: `hlp12345` (lowercase)
3. Lookup: `HLP12345` (correct format)

**Expected Result:**
- 7-char token: 404 or 422
- Lowercase: 404 (tokens are uppercase) or normalized
- Correct format: 200 if exists, 404 if not

---

### TC-232 · Content-Type Header — JSON Required
**API:** POST /api/auth/login

**Steps:**
1. Send POST without Content-Type: application/json header
2. Check response

**Expected Result:**
- Request still processed (Laravel handles it)
- OR 415 Unsupported Media Type
- No server crash

---

### TC-233 · Large Payload Rejection
**API:** POST /api/partner-offers

**Steps:**
1. Send POST with offer description: 100,000-character string

**Expected Result:**
- 422 Validation error (max length exceeded)
- OR 413 Payload Too Large
- Server not overwhelmed

---

### TC-234 · Authorization — Customer Token Isolation
**Steps:**
1. Get customer_token from OTP login for Priya (phone: 9111111101)
2. Use customer_token to call GET /api/partnerships

**Expected Result:**
- 401 Unauthorized
- Customer tokens only valid for /api/customer/* routes

---

### TC-235 · Health Check — Public Endpoint
**API:** GET /api/health

**Steps:**
1. Call without any authentication

**Expected Result:**
- 200 OK
- Response: `{status: "ok", service: "hyperlocal-api"}`
- Fast response (< 100ms)

---

### TC-236 · Sanctum Token Naming — Multiple Devices
**Steps:**
1. Log in as Alice from Browser A — token named "api_[timestamp]"
2. Log in as Alice from Browser B — different token
3. Check that both tokens work independently

**Expected Result:**
- Both tokens valid simultaneously
- Neither login invalidates the other
- Fixed in recent session (old bug was tokens deleted on same device_name)

---

### TC-237 · Webhook Verification — Invalid Signature
**API:** POST /api/webhooks/ecosystem/merchant-exit

**Steps:**
1. Send webhook without proper signature header
2. Or send with wrong signature

**Expected Result:**
- 401 or 403 returned
- Webhook not processed
- Event not logged

---

### TC-238 · Pagination — Default Page Size
**API:** GET /api/partnerships

**Steps:**
1. Create 25+ partnerships (use seeder data)
2. GET /api/partnerships without pagination params

**Expected Result:**
- Default page of results returned
- Response includes pagination metadata: total, per_page, current_page
- Not all 25+ returned in one response (paged)

---

### TC-239 · Pagination — Custom Page Size
**API:** GET /api/partnerships?per_page=5

**Steps:**
1. Request 5 per page
2. Check response

**Expected Result:**
- 5 partnerships returned
- total reflects all partnerships
- pages calculated correctly

---

### TC-240 · Sorting — Partnerships by Created Date
**API:** GET /api/partnerships?sort=created_at&direction=desc

**Steps:**
1. Request sorted partnerships

**Expected Result:**
- Partnerships in descending created_at order
- Newest first
- OR default sort is always newest — check API behavior

---

## MODULE 29 — ANALYTICS DEEP DIVE

---

### TC-241 · Analytics Summary — All Fields Present
**API:** GET /api/analytics/summary

**Steps:**
1. Authenticate as Alice
2. GET /api/analytics/summary

**Expected Result:**
- 200 OK
- Response contains: live_partnerships, total_redemptions, new_customers,
  existing_customers, revenue, benefit_given, pending_partnerships
- All values are integers or decimals (no nulls)

---

### TC-242 · Analytics Summary — After New Redemption
**Steps:**
1. Note current total_redemptions
2. Issue and redeem one token
3. GET /api/analytics/summary again

**Expected Result:**
- total_redemptions incremented by 1
- revenue updated (bill amount added)
- benefit_given updated (discount amount added)

---

### TC-243 · Analytics Trends — Monthly Data
**API:** GET /api/analytics/trends

**Steps:**
1. Authenticate as Alice
2. GET /api/analytics/trends
3. Check response structure

**Expected Result:**
- 200 OK
- Array of monthly data points
- Each: month (YYYY-MM), redemptions, new_customers, revenue

---

### TC-244 · Analytics Trends — Date Range Filter
**API:** GET /api/analytics/trends?from=2026-01-01&to=2026-03-31

**Steps:**
1. Request trends for Q1 2026

**Expected Result:**
- Data filtered to that quarter only
- Months outside range not included

---

### TC-245 · Partnership Analytics — Specific Partnership
**API:** GET /api/analytics/partnerships/{uuid}

**Steps:**
1. Get UUID of Alpha x Beta partnership
2. GET analytics for that partnership

**Expected Result:**
- 200 OK
- Stats: redemptions, new_customers, existing_customers, revenue, benefit_cost
- Scoped to that partnership only

---

### TC-246 · Partnership Analytics Trend
**API:** GET /api/analytics/partnerships/{uuid}/trend

**Steps:**
1. GET trend data for a specific partnership

**Expected Result:**
- 200 OK
- Array of trend data for that partnership over time

---

### TC-247 · Analytics — New vs Existing Customer Classification
**Steps:**
1. Issue token to phone 9111111101 (Priya — has prior redemption)
2. Issue token to phone 9999999999 (first-time user)
3. Redeem both tokens at Beta
4. Check analytics summary

**Expected Result:**
- new_customers +1 (9999999999 is new to Beta)
- existing_customers +1 (9111111101 was at Beta before)
- Classification visible in redemption record

---

### TC-248 · Analytics — Benefit Cost Calculation
**Steps:**
1. Redeem token with bill amount ₹500, 20% discount → benefit = ₹100
2. Check analytics: benefit_given

**Expected Result:**
- benefit_given increases by 100
- revenue increases by 500
- Net ROI = revenue - benefit_given = 400 tracked

---

### TC-249 · Analytics — Zero State (New Merchant)
**API:** GET /api/analytics/summary (newly created merchant)

**Steps:**
1. Create a brand-new merchant
2. Immediately GET analytics summary

**Expected Result:**
- All zeros: 0 partnerships, 0 redemptions, 0 customers
- No null values — all initialized to 0
- No 500 error on empty data

---

### TC-250 · Dashboard — Stats Match API
**Steps:**
1. Note values from GET /api/analytics/summary
2. Navigate to http://localhost:3000/dashboard
3. Compare displayed values to API response

**Expected Result:**
- Dashboard shows same numbers as API
- No discrepancy between API and UI
- Real-time data (not cached stale data)

---

### TC-251 · Analytics — Multiple Partnerships Aggregated
**Steps:**
1. Alice has 3 partnerships: Alpha x Beta, Alpha x Gamma, Alpha x Delta
2. Each has some redemptions
3. GET /api/analytics/summary

**Expected Result:**
- total_redemptions = sum across all 3 partnerships
- Summary aggregates correctly
- Individual partnership analytics accessible per UUID

---

### TC-252 · Ledger — Partnership Statement
**API:** GET /api/partnerships/{uuid}/ledger

**Steps:**
1. GET ledger for Alpha x Beta partnership

**Expected Result:**
- 200 OK
- Chronological list of credit/debit entries
- Each: type (claim/redemption), amount, balance_after, timestamp

---

### TC-253 · Ledger — Merchant Summary
**API:** GET /api/ledger/summary

**Steps:**
1. GET /api/ledger/summary

**Expected Result:**
- 200 OK
- Overview: total_benefit_given, total_revenue, net_value, credits_spent

---

### TC-254 · Analytics — Enablement Summary
**API:** GET /api/enablement/summary

**Steps:**
1. GET /api/enablement/summary

**Expected Result:**
- 200 OK
- Shows: trained_outlets, total_outlets, training_completion_rate

---

### TC-255 · Weekly Digest API
**API:** GET /api/growth/weekly-digest

**Steps:**
1. GET /api/growth/weekly-digest

**Expected Result:**
- 200 OK
- This week's metrics: new_customers, revenue, benefit_cost, best_partnership

---

## MODULE 30 — EVENT TRIGGERS

---

### TC-256 · View Event Constants
**API:** GET /api/event-constants

**Steps:**
1. Authenticate as Alice
2. GET /api/event-constants

**Expected Result:**
- 200 OK
- List of available event types: order.completed, customer.signup, etc.
- Each event has: key, label, description

---

### TC-257 · Create Event Source
**URL:** http://localhost:3000/event-sources/setup (logged in as Alice)

**Steps:**
1. Navigate to event sources setup
2. Create a new source:
   - Name: `Website Orders`
   - Type: `webhook`
3. Save

**Expected Result:**
- Source created
- API key / webhook URL provided for integration
- Source appears in event-sources list

---

### TC-258 · Toggle Event Source Active/Inactive
**URL:** http://localhost:3000/event-sources

**Steps:**
1. Find an active event source
2. Toggle to inactive
3. Toggle back to active

**Expected Result:**
- Status badge updates
- Inactive source: events not processed
- Active source: events processed

---

### TC-259 · Create Event Trigger
**URL:** http://localhost:3000/event-triggers/create

**Steps:**
1. Navigate to create trigger
2. Fill:
   - Event: `order.completed`
   - Action: Issue cross-loyalty token
   - Partnership: Alpha x Beta
   - Min order amount: ₹200
3. Save

**Expected Result:**
- Trigger created: status Active
- Appears in event-triggers list
- When order.completed event with amount ≥200 fires → token auto-issued

---

### TC-260 · Edit Event Trigger
**URL:** http://localhost:3000/event-triggers/{uuid}/edit

**Steps:**
1. Edit existing trigger
2. Change min_order_amount to ₹500
3. Save

**Expected Result:**
- Trigger updated
- New threshold: ₹500
- Previous threshold no longer applies

---

### TC-261 · Toggle Event Trigger
**URL:** http://localhost:3000/event-triggers

**Steps:**
1. Find active trigger
2. Toggle to inactive

**Expected Result:**
- Trigger paused
- Events no longer auto-process this trigger
- Toggle back re-activates

---

### TC-262 · Delete Event Source
**API:** DELETE /api/event-sources/{uuid}

**Steps:**
1. Create a test source
2. Delete it

**Expected Result:**
- 200 OK
- Source removed from list
- Triggers associated with this source also removed or orphaned

---

### TC-263 · Delete Event Trigger
**API:** DELETE /api/event-triggers/{uuid}

**Steps:**
1. DELETE a trigger

**Expected Result:**
- 200 OK
- Trigger removed from list
- Auto-issuances using this trigger stop

---

### TC-264 · Test Event — Fire Test
**API:** POST /api/event-triggers/test

**Steps:**
1. POST: `{event_type: "order.completed", payload: {amount: 300, customer_phone: "9111111101"}}`

**Expected Result:**
- 200 OK
- Response shows which triggers would fire
- Test token issued (or simulated issue shown)

---

### TC-265 · Event Log — View Recent Events
**URL:** http://localhost:3000/event-sources (or dedicated event log page)

**API:** GET /api/event-log

**Steps:**
1. GET /api/event-log

**Expected Result:**
- 200 OK
- List of recent event ingestions
- Each: event_type, source, timestamp, status (processed/failed), trigger_fired

---

### TC-266 · Event Log — Detail View
**API:** GET /api/event-log/{id}

**Steps:**
1. Get ID from event log
2. GET detail

**Expected Result:**
- 200 OK
- Full event payload, processing steps, outcome

---

### TC-267 · Shopify Orders Webhook
**API:** POST /api/connectors/shopify/{merchantKey}/orders

**Steps:**
1. Get merchant API key from event source
2. POST Shopify-format order payload

**Expected Result:**
- 200 OK
- Order processed as event
- If trigger matches: token issued

---

### TC-268 · WooCommerce Orders Webhook
**API:** POST /api/connectors/woocommerce/{merchantKey}/orders

**Steps:**
1. POST WooCommerce-format order payload

**Expected Result:**
- 200 OK
- Order processed
- Same trigger logic as Shopify

---

### TC-269 · Event Pixel — GET Request
**API:** GET /api/events/pixel/{merchantKey}

**Steps:**
1. GET /api/events/pixel/{key} (like a tracking pixel call)

**Expected Result:**
- 200 OK
- Returns 1x1 transparent GIF or 204 No Content
- Event logged in event_log

---

### TC-270 · Event Triggers — Validation: Missing Event Type
**API:** POST /api/event-triggers

**Steps:**
1. POST without event_type field

**Expected Result:**
- 422 Validation error
- Message: "event_type is required"

---

### TC-271 · Event Source — Duplicate Name
**Steps:**
1. Create source with name: `Main Source`
2. Create another source with same name

**Expected Result:**
- Second creation succeeds (names not unique)
- OR validation error if names must be unique
- Document the actual behavior

---

### TC-272 · Trigger — Min Amount Zero
**Steps:**
1. Create trigger with min_order_amount = 0 (no minimum)

**Expected Result:**
- All orders trigger token issuance
- OR validation requires > 0

---

### TC-273 · Trigger — Partnership Must Be Live
**Steps:**
1. Create event trigger linked to a Paused partnership
2. Fire test event

**Expected Result:**
- Trigger fires but token issuance fails: "Partnership is paused"
- OR trigger only works with Live partnerships

---

### TC-274 · Event Rate Limit — 60 per minute
**Steps:**
1. POST 60 events to /api/events/trigger within 1 minute
2. Send 61st

**Expected Result:**
- First 60: 200 OK
- 61st: 429 Too Many Requests

---

### TC-275 · Event Trigger List — Filter by Status
**API:** GET /api/event-triggers?status=active

**Steps:**
1. GET with status filter

**Expected Result:**
- Only active triggers returned
- Inactive triggers excluded

---

## MODULE 31 — BILL OFFERS (PUBLIC)

---

### TC-276 · Bill Offers — Check if Enabled for Merchant
**API:** GET /api/public/bill-offers/{merchantUuid}/enabled

**Steps:**
1. Get UUID for TestBrand Alpha
2. Call endpoint (no auth required)

**Expected Result:**
- 200 OK
- Response: `{enabled: true/false, merchant_name: "TestBrand Alpha"}`

---

### TC-277 · Bill Offers — List Active Offers
**API:** GET /api/public/bill-offers/{merchantUuid}

**Steps:**
1. Alice has active partner offers attached to partnerships
2. GET bill offers for Alice's UUID

**Expected Result:**
- 200 OK
- Array of active offers: title, coupon_code, discount, description
- Only active offers shown (inactive/expired excluded)

---

### TC-278 · Bill Offers — Record Impressions
**API:** POST /api/public/bill-offers/{merchantUuid}/impressions

**Steps:**
1. POST: `{offer_uuids: ["uuid1", "uuid2"]}` 

**Expected Result:**
- 200 OK
- Impression counts updated for each offer
- No auth required

---

### TC-279 · Bill Offers — Record Claim
**API:** POST /api/public/bill-offers/{merchantUuid}/claims/{offerUuid}

**Steps:**
1. POST a claim for a specific offer

**Expected Result:**
- 200 OK
- Claim logged
- Offer claim count incremented

---

### TC-280 · Bill Offers — Unknown Merchant UUID
**API:** GET /api/public/bill-offers/00000000-0000-0000-0000-000000000000

**Steps:**
1. Call with nonexistent UUID

**Expected Result:**
- 404 Not Found
- No offers returned
- Graceful error

---

### TC-281 · Bill Offers — Toggle in Settings
**URL:** http://localhost:3000/settings (logged in as Alice)

**Steps:**
1. Find Bill Offers section in settings
2. Toggle "Show partner offers on digital bill" to ON
3. Save

**Expected Result:**
- bill_offers_enabled = true for Alice
- /api/public/bill-offers/{alice-uuid}/enabled returns enabled: true

---

### TC-282 · Bill Offers Public Page
**URL:** http://localhost:3000/bill-offers/{merchantUuid}

**Steps:**
1. Get Alice's merchant UUID
2. Open /bill-offers/{uuid} in incognito

**Expected Result:**
- Page loads without login
- Shows merchant name and active partner offers
- Each offer: title, coupon code, discount description

---

### TC-283 · Bill Offers — No Offers State
**URL:** http://localhost:3000/bill-offers/{merchantUuid}

**Steps:**
1. Navigate to bill-offers page for a merchant with NO attached offers

**Expected Result:**
- Page loads without error
- Shows empty state: "No offers currently available"
- Merchant name still shown

---

### TC-284 · Bill Offers — Expired Offer Not Shown
**Steps:**
1. Create offer with expiry: yesterday
2. Attach to partnership
3. Load bill-offers page

**Expected Result:**
- Expired offer NOT shown in public list
- Only valid/active offers displayed

---

### TC-285 · Bill Offers — Inactive Offer Not Shown
**Steps:**
1. Toggle offer to Inactive
2. Load bill-offers page

**Expected Result:**
- Inactive offer not in list
- Toggle back to Active → appears again

---

## MODULE 32 — CUSTOMER PORTAL DEEP DIVE

---

### TC-286 · Customer OTP — Correct OTP Accepted
**API:** POST /api/customer/verify-otp

**Steps:**
1. POST /api/customer/send-otp: `{phone: "9111111101"}`
2. Note OTP from response (dev mode)
3. POST /api/customer/verify-otp: `{phone: "9111111101", otp: "XXXXX"}`

**Expected Result:**
- 200 OK
- Response: `{customer_token: "..."}`
- Token valid for customer routes

---

### TC-287 · Customer OTP — Wrong OTP
**API:** POST /api/customer/verify-otp

**Steps:**
1. Send OTP to phone
2. Verify with wrong OTP: `{otp: "00000"}`

**Expected Result:**
- 422 Validation error or 401
- Message: "Invalid or expired OTP"
- customer_token NOT returned

---

### TC-288 · Customer OTP — Expired OTP
**Steps:**
1. Request OTP
2. Wait for OTP to expire (dev: check TTL, usually 10 minutes)
3. Try to verify with expired OTP

**Expected Result:**
- 422 error: "OTP has expired"
- Must request new OTP

---

### TC-289 · Customer OTP — Resend OTP
**Steps:**
1. Request OTP for phone
2. Request again (resend)
3. Verify with the second OTP

**Expected Result:**
- Second OTP issued
- First OTP invalidated (or both valid within TTL)
- Verification works with latest OTP

---

### TC-290 · Customer Rewards — View All Balances
**API:** GET /api/customer/rewards (with customer_token)

**Steps:**
1. Log in as customer Priya (9111111101)
2. GET /api/customer/rewards

**Expected Result:**
- 200 OK
- Array of reward balances per merchant
- Each: merchant_name, points_balance, inr_value, outlet_to_redeem

---

### TC-291 · Customer Rewards — Activity History
**API:** GET /api/customer/activity

**Steps:**
1. GET /api/customer/activity

**Expected Result:**
- 200 OK
- Chronological list of: claim issued, claim redeemed
- Each: event_type, partnership_name, amount, timestamp

---

### TC-292 · Customer Rewards — Empty State
**API:** GET /api/customer/rewards (new customer, no claims)

**Steps:**
1. Login new phone (never had tokens)
2. GET rewards

**Expected Result:**
- 200 OK
- Empty array or: `{balances: [], message: "No rewards yet"}`
- No crash on empty

---

### TC-293 · Customer Portal — Invalid Customer Token
**API:** GET /api/customer/rewards (with bad token)

**Steps:**
1. Send request with Authorization: Bearer INVALID_TOKEN
2. Check response

**Expected Result:**
- 401 Unauthorized
- Redirected to /my-rewards in browser

---

### TC-294 · Customer Portal — Token Isolation from Merchant
**Steps:**
1. Alice logs in as merchant, gets merchant token
2. Use merchant token for GET /api/customer/rewards

**Expected Result:**
- 401 Unauthorized (different auth guard)
- Merchant token cannot access customer routes

---

### TC-295 · Customer Portal — UX: Mobile Responsive
**URL:** http://localhost:3000/my-rewards (mobile viewport)

**Steps:**
1. Open /my-rewards on mobile device (or DevTools mobile view, 375px)
2. Log in via OTP
3. View rewards dashboard

**Expected Result:**
- Full UI fits in mobile viewport
- Cards stack vertically
- Text readable, buttons tappable
- No horizontal overflow

---

### TC-296 · Customer Rewards — Points to INR Calculation
**Steps:**
1. Merchant has point_valuation = ₹1.50 per point
2. Customer has 100 points balance
3. GET /api/customer/rewards

**Expected Result:**
- inr_value = 150 (100 × 1.50)
- Both points and INR shown in response

---

### TC-297 · Customer OTP — Rate Limit
**Steps:**
1. Send OTP 20 times in 1 minute from same IP

**Expected Result:**
- After limit: 429 Too Many Requests
- Prevents OTP abuse / SMS bombing

---

### TC-298 · Customer Portal — Brand Logo Visible
**URL:** http://localhost:3000/my-rewards/dashboard

**Steps:**
1. Login as customer
2. View rewards from a brand with logo set

**Expected Result:**
- Brand logo URL loaded from growth profile
- Logo displayed as thumbnail next to brand name
- Fallback initials shown if no logo set

---

### TC-299 · Customer Portal — Claim Code Display
**URL:** http://localhost:3000/my-rewards/dashboard

**Steps:**
1. Customer has unclaimed token
2. View active tokens section

**Expected Result:**
- Token code visible (e.g., HLPXXXXX)
- Expiry countdown or date shown
- "Redeem at [outlet name]" visible

---

### TC-300 · Customer — Redeemed Token in History
**Steps:**
1. Token issued and redeemed for customer
2. Customer logs into portal
3. Check activity

**Expected Result:**
- Activity shows: Claim Issued (date), Claim Redeemed (date)
- Both events visible with timestamps
- Benefit amount shown for redemption event

---

## MODULE 33 — GROWTH MODULE

---

### TC-301 · Growth Health Scores — All Partnerships
**API:** GET /api/growth/health

**Steps:**
1. GET /api/growth/health

**Expected Result:**
- 200 OK
- Array of health scores per partnership
- Each: partnership_name, health_score (0-100), health_level (Strong/Good/Needs Attention)

---

### TC-302 · Growth Health — Single Partnership
**API:** GET /api/growth/health/{partnershipUuid}

**Steps:**
1. GET health for specific partnership UUID

**Expected Result:**
- 200 OK
- Single object: health_score, factors breakdown

---

### TC-303 · Referral Link — Generate
**API:** GET /api/growth/referral/{partnershipUuid}

**Steps:**
1. GET referral link for Alpha x Beta

**Expected Result:**
- 200 OK
- Response: `{referral_url: "http://...", code: "REF_XXXXXX"}`

---

### TC-304 · Create Invite (Growth)
**API:** POST /api/growth/invite

**Steps:**
1. POST: `{partner_email: "newbrand@example.com", message: "Join our network!"}`

**Expected Result:**
- 200 OK
- Invite created, email record logged
- invite_stats incremented

---

### TC-305 · Invite Stats
**API:** GET /api/growth/invite-stats

**Steps:**
1. GET invite stats

**Expected Result:**
- 200 OK
- Response: `{total_sent: N, accepted: N, pending: N, conversion_rate: X%}`

---

### TC-306 · Demand Index
**API:** GET /api/growth/demand-index

**Steps:**
1. GET demand index

**Expected Result:**
- 200 OK
- Response: `{demand_index: 0-100, category_rank: N, city_rank: N}`

---

### TC-307 · Seasonal Templates
**API:** GET /api/growth/seasonal-templates

**Steps:**
1. GET seasonal templates

**Expected Result:**
- 200 OK
- Array of campaign templates relevant to current season
- Each: name, body, required_variables

---

### TC-308 · Update Brand Profile
**API:** POST /api/growth/profile

**Steps:**
1. POST: `{description: "Premium cafe in Mumbai", logo_url: "https://example.com/logo.png", tagline: "Where every cup tells a story"}`

**Expected Result:**
- 200 OK
- Profile updated
- GET /api/public/brand/{slug} shows new profile data

---

### TC-309 · Public Brand Profile
**API:** GET /api/public/brand/{slug}

**Steps:**
1. GET /api/public/brand/testbrand-alpha (or correct slug)

**Expected Result:**
- 200 OK
- Response: `{name, description, logo_url, tagline, city, category}`
- No auth required

---

### TC-310 · Marketplace — Public Listing
**API:** GET /api/public/marketplace

**Steps:**
1. GET /api/public/marketplace (no auth)

**Expected Result:**
- 200 OK
- Array of brands visible on marketplace
- Only brands with open_to_partnerships = true shown

---

### TC-311 · Growth Page — Weekly Digest UI
**URL:** http://localhost:3000/growth

**Steps:**
1. Navigate to /growth
2. View Weekly Digest card

**Expected Result:**
- This week's stats shown: new customers, redemptions, revenue
- Comparison to last week (delta percentage)
- Best performing partnership highlighted

---

### TC-312 · Brand Profile — Public Page View
**URL:** http://localhost:3000/b/{slug}

**Steps:**
1. Open public brand profile URL in incognito
2. Verify no login required

**Expected Result:**
- Page loads
- Shows: brand name, description, logo, tagline
- "Partner with us" CTA button visible
- Clicking CTA → navigates to /register or /login with intent

---

### TC-313 · Marketplace — No Auth Needed
**URL:** http://localhost:3000/marketplace

**Steps:**
1. Open in incognito (no login)
2. Browse marketplace

**Expected Result:**
- Full marketplace browsable without login
- Each brand card: name, category, city
- "View profile" or "Partner with us" links work

---

### TC-314 · Growth — Discovery Suggestions
**API:** GET /api/discovery/suggestions

**Steps:**
1. GET /api/discovery/suggestions

**Expected Result:**
- 200 OK
- Array of suggested partner merchants
- Each: merchant_id, name, category, fit_score

---

### TC-315 · Discovery — Dismiss Suggestion
**API:** POST /api/discovery/suggestions/{id}/dismiss

**Steps:**
1. Get a suggestion ID
2. POST to dismiss it

**Expected Result:**
- 200 OK
- Suggestion removed from future GET /api/discovery/suggestions
- Will not reappear

---

### TC-316 · Discovery — Search
**API:** GET /api/discovery/search?city=Mumbai&category=Cafe

**Steps:**
1. Search with city and category

**Expected Result:**
- 200 OK
- Only merchants in Mumbai, Cafe category shown
- Caller's own merchant excluded

---

### TC-317 · Growth — Referral Redirect
**API:** GET /api/public/r/{code}

**Steps:**
1. Generate referral code
2. GET /api/public/r/{code}

**Expected Result:**
- 301 Redirect or 200 OK
- Tracks referral click
- Redirects to target partnership claim page

---

### TC-318 · Growth — Health Score Range Validation
**Steps:**
1. Check health_score in API response

**Expected Result:**
- Always between 0 and 100
- No negative values
- No values > 100

---

### TC-319 · Growth — Demand Index Range Validation
**Steps:**
1. Check demand_index in API response

**Expected Result:**
- Always 0-100
- Based on city/category partnership density

---

### TC-320 · Growth — Profile Update Validation
**API:** POST /api/growth/profile

**Steps:**
1. POST: `{description: 5001-char string}`

**Expected Result:**
- 422 Validation error
- "Description must be under 5000 characters"

---

## MODULE 34 — SUPER ADMIN ADVANCED

---

### TC-321 · SA Dashboard — Count Verification
**URL:** http://localhost:3000/super-admin/dashboard

**Steps:**
1. Note: Total Brands count
2. Register a new brand
3. SA dashboard: Pending Registrations increases
4. Approve brand
5. SA dashboard: Total Brands increases, Pending Registrations decreases

**Expected Result:**
- Real-time counts as brands are added/approved
- Consistent across page refreshes

---

### TC-322 · SA Merchant List — Pagination
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. If more than 20 merchants exist
2. Check pagination controls at bottom

**Expected Result:**
- Paginated view
- Next/Previous page buttons
- Page number shown

---

### TC-323 · SA Merchant Detail — Full View
**URL:** http://localhost:3000/super-admin/merchants/{id}

**Steps:**
1. Open any merchant detail

**Expected Result:**
- Shows: name, category, city, admin email, admin phone
- Shows: ecosystem_active toggle, open_to_partnerships toggle
- Shows: WhatsApp credit balance
- Shows: eWards status
- Shows: trust score
- Credit ledger at bottom

---

### TC-324 · SA Update Merchant — Toggle Ecosystem Active
**URL:** http://localhost:3000/super-admin/merchants/{id}

**Steps:**
1. Open merchant detail
2. Toggle `Ecosystem Active` to OFF
3. Save

**Expected Result:**
- Merchant's ecosystem_active = false
- Merchant cannot issue/receive tokens while inactive
- Toggle visible as OFF

---

### TC-325 · SA Update Merchant — Toggle Open to Partnerships
**URL:** http://localhost:3000/super-admin/merchants/{id}

**Steps:**
1. Toggle `Open to Partnerships` to OFF
2. Save

**Expected Result:**
- Merchant no longer appears in partner discovery results
- Cannot receive new partnership proposals

---

### TC-326 · SA Merchant — Credit Ledger History
**URL:** http://localhost:3000/super-admin/merchants/{id}

**Steps:**
1. View credit ledger section

**Expected Result:**
- Full history of credits allocated
- Each: amount, note, allocated_by (SA email), timestamp, balance_after
- Sorted newest first

---

### TC-327 · SA Credits — Allocate Large Amount
**URL:** http://localhost:3000/super-admin/merchants → Add Credits

**Steps:**
1. Enter Amount: `10000`
2. Note: `Bulk annual credit allocation`
3. Click Allocate

**Expected Result:**
- 10,000 credits allocated
- Balance updated correctly
- No overflow or truncation

---

### TC-328 · SA — Cannot Login as Merchant
**Steps:**
1. Login as SA at /super-admin/login
2. Navigate to /login
3. Try to access merchant dashboard

**Expected Result:**
- SA login does NOT give access to merchant portal
- Redirected to merchant login
- Two separate authentication systems

---

### TC-329 · SA Integration Requests — Approve with Credentials
**URL:** http://localhost:3000/super-admin/requests

**Steps:**
1. Merchant submitted eWards integration request
2. SA approves with valid credentials
3. Check merchant's settings page

**Expected Result:**
- Request status: Approved
- Merchant's eWards status: Active
- API credentials stored (not visible to merchant for security)

---

### TC-330 · SA Integration Requests — Reject with Reason
**URL:** http://localhost:3000/super-admin/requests

**Steps:**
1. Find a pending eWards request
2. Click Reject
3. Enter reason: `Not eligible for eWards integration at this time.`
4. Confirm

**Expected Result:**
- Request status: Rejected
- Reason stored and visible in request detail
- Merchant sees rejected status in their settings

---

### TC-331 · SA — View Brand Registrations Sorted by Date
**URL:** http://localhost:3000/super-admin/brand-registrations

**Steps:**
1. View pending registrations list

**Expected Result:**
- Sorted by submitted_at descending (newest first)
- Date visible in each row
- Sort toggle available

---

### TC-332 · SA — Multiple Approvals in Sequence
**Steps:**
1. Register 5 new brands in quick succession
2. Approve all 5 one by one from SA panel

**Expected Result:**
- All 5 approvals succeed
- No state corruption between approvals
- Each brand can login after approval

---

### TC-333 · SA — API: Dashboard Stats
**API:** GET /api/super-admin/merchants/dashboard

**Steps:**
1. Authenticate as SA
2. GET /api/super-admin/merchants/dashboard

**Expected Result:**
- 200 OK
- Response: `{total_merchants, ecosystem_active, live_partnerships, pending_registrations, pending_ewards, low_credits, zero_credits}`

---

### TC-334 · SA — Brand Details via API
**API:** GET /api/super-admin/merchants/{id}

**Steps:**
1. GET specific merchant by ID

**Expected Result:**
- 200 OK
- Full merchant object including balance, trust_score, ewards_status

---

### TC-335 · SA — Update Merchant via API
**API:** PUT /api/super-admin/merchants/{id}

**Steps:**
1. PUT: `{is_active: true, ecosystem_active: false}`

**Expected Result:**
- 200 OK
- Merchant updated
- Change reflected in GET /api/super-admin/merchants/{id}

---

### TC-336 · SA — Create Merchant via API
**API:** POST /api/super-admin/merchants

**Steps:**
1. POST new merchant data with admin user credentials

**Expected Result:**
- 201 Created
- Merchant and admin user created
- Admin can login immediately

---

### TC-337 · SA Auth — Me Endpoint
**API:** GET /api/super-admin/auth/me

**Steps:**
1. GET with SA token

**Expected Result:**
- 200 OK
- Response: SA admin object with email, name, role

---

### TC-338 · SA Auth — Logout
**API:** POST /api/super-admin/auth/logout

**Steps:**
1. POST with SA token

**Expected Result:**
- 200 OK
- Token revoked
- Subsequent calls with same token: 401

---

### TC-339 · SA — List All Integration Requests
**API:** GET /api/super-admin/integration-requests

**Steps:**
1. GET all requests
2. Filter: ?status=pending

**Expected Result:**
- 200 OK
- Array of requests
- Filtering works

---

### TC-340 · SA — Integration Request Detail
**API:** GET /api/super-admin/integration-requests/{id}

**Steps:**
1. GET detail for a specific request

**Expected Result:**
- 200 OK
- Full request object: merchant, notes, requested_at, status, reviewed_by

---

## MODULE 35 — DATA VALIDATION & EDGE CASES

---

### TC-341 · Partnership — Missing Required Fields
**API:** POST /api/partnerships

**Steps:**
1. POST without partnership_name
2. POST without partner_merchant_id
3. POST without terms (per_bill_cap)

**Expected Result:**
- 422 Validation error for each
- Clear field-level error messages
- Partnership not created

---

### TC-342 · Token Redemption — Bill Amount Zero
**API:** GET /api/execution/lookup/{token} + POST /api/execution/redeem

**Steps:**
1. Lookup valid token with bill_amount: 0

**Expected Result:**
- Benefit = 0 (no discount on zero bill)
- OR validation error: "Bill amount must be positive"

---

### TC-343 · Token Redemption — Very Large Bill Amount
**Steps:**
1. Redeem with bill_amount: `9999999`

**Expected Result:**
- No server crash
- Per-bill cap applied correctly (e.g., max ₹300 benefit even on ₹9,999,999 bill)

---

### TC-344 · Token — Already Redeemed Before Expiry
**Steps:**
1. Issue token, redeem it
2. Before expiry, try to redeem the same token again

**Expected Result:**
- Error: "Token already redeemed"
- Status shows: redeemed_at timestamp
- Not an expiry error — specifically a "duplicate redemption" error

---

### TC-345 · Token — Expired Before Redemption
**Steps (API forced):**
1. Create token, manually set expires_at to past in DB (or use test helper)
2. Try to redeem

**Expected Result:**
- Error: "Token has expired"
- redemption_failed_reason = 'expired'
- No benefit given

---

### TC-346 · CSV Upload — Empty File
**Steps:**
1. Upload a .csv file with only the header row, no data rows

**Expected Result:**
- Response: "Imported: 0, Failed: 0"
- OR message: "CSV is empty — no records to import"

---

### TC-347 · CSV Upload — Invalid Phone Numbers
**Steps:**
1. Upload CSV:
   ```
   name,phone,email
   Test,abc123,test@test.com
   Test2,111,test2@test.com
   ```
2. Upload

**Expected Result:**
- Row 1: failed — invalid phone format
- Row 2: failed — phone too short
- Report: Imported: 0, Failed: 2
- No partial import of invalid rows

---

### TC-348 · CSV Upload — Mixed Valid/Invalid
**Steps:**
1. Upload CSV with 5 rows: 3 valid, 2 with invalid phones

**Expected Result:**
- Imported: 3, Failed: 2
- Errors reported per row
- Valid rows imported successfully

---

### TC-349 · Network — Name Too Long
**API:** POST /api/merchant/networks

**Steps:**
1. POST: `{name: 256-char string, description: "test"}`

**Expected Result:**
- 422 Validation error: "Name must be under 255 characters"

---

### TC-350 · Campaign — Template Variables Missing
**Steps:**
1. Campaign uses template requiring `{{partnership_name}}` variable
2. Send without providing that variable

**Expected Result:**
- Validation error or preview shows unfilled variable
- Campaign not sent with broken template

---

### TC-351 · Auth Register — Phone Validation
**API:** POST /api/auth/register or /api/register-brand

**Steps:**
1. Register with phone: `NOTAPHONE`

**Expected Result:**
- 422 Validation error: "Phone must be numeric"

---

### TC-352 · Partnership — Cannot Create with Self
**API:** POST /api/partnerships

**Steps:**
1. Alice tries to create partnership where partner = Alice (same merchant_id)

**Expected Result:**
- 422 Validation error: "Cannot create partnership with yourself"

---

### TC-353 · Analytics — Future Date Range
**API:** GET /api/analytics/trends?from=2030-01-01&to=2030-12-31

**Steps:**
1. Query analytics for a future date

**Expected Result:**
- 200 OK
- Empty array (no data for future dates)
- No server error

---

### TC-354 · Discovery — Empty City Search
**API:** GET /api/discovery/search?city=

**Steps:**
1. GET with empty city parameter

**Expected Result:**
- 422 Validation error: "City is required"
- Empty string not accepted

---

### TC-355 · Member Lookup — Phone Not Found
**API:** POST /api/members/lookup

**Steps:**
1. POST: `{phone: "9999999999"}` (not in any membership DB)

**Expected Result:**
- 200 OK with `{found: false}` or `{is_member: false}`
- OR 404 Not Found
- No crash

---

### TC-356 · Member Opt-Out
**API:** POST /api/members/opt-out

**Steps:**
1. POST: `{phone: "9111111101"}`

**Expected Result:**
- 200 OK
- Customer opted out of communications
- Future tokens/campaigns won't be sent to this phone

---

### TC-357 · Outlet Picker — Returns Only Own Outlets
**API:** GET /api/outlets

**Steps:**
1. Authenticate as Alice
2. GET /api/outlets

**Expected Result:**
- Returns only Alpha's outlets
- Beta's outlets NOT included
- Merchant isolation enforced

---

### TC-358 · Merchant List — Excludes Self
**API:** GET /api/merchants

**Steps:**
1. Authenticate as Alice
2. GET /api/merchants

**Expected Result:**
- Returns all merchants EXCEPT Alice's own brand
- Alice's brand not shown (cannot partner with self)

---

### TC-359 · Partnership T&C Endpoint
**API:** GET /api/partnerships/tc

**Steps:**
1. GET /api/partnerships/tc

**Expected Result:**
- 200 OK
- T&C text returned (HYPERLOCAL PARTNERSHIP — STANDARD TERMS & CONDITIONS)
- Rendered as markdown or plain text

---

### TC-360 · Partnership — Update My Settings
**API:** POST /api/partnerships/{uuid}/my-settings

**Steps:**
1. POST: `{is_issuing: true, is_receiving: false}`

**Expected Result:**
- 200 OK
- Merchant's side of partnership updated
- Can independently control issuing vs receiving

---

### TC-361 · Partnership — Notify Customers
**API:** POST /api/partnerships/{uuid}/notify-customers

**Steps:**
1. POST to notify customers of partnership

**Expected Result:**
- 200 OK
- Notification scheduled/sent to merchant's customers
- Uses WhatsApp credits

---

### TC-362 · Enablement — Partnership Outlets
**API:** GET /api/partnerships/{uuid}/enablement

**Steps:**
1. GET enablement info for a partnership

**Expected Result:**
- 200 OK
- List of outlets with training status

---

### TC-363 · Enablement — Mark Training Complete
**API:** POST /api/partnerships/{uuid}/enablement/{outletId}/training

**Steps:**
1. POST to mark an outlet as trained

**Expected Result:**
- 200 OK
- Training status updated for that outlet
- Enablement summary shows completion rate

---

### TC-364 · Partner Outlets Picker
**API:** GET /api/partnerships/{uuid}/partner-outlets

**Steps:**
1. GET available partner outlets for a partnership

**Expected Result:**
- 200 OK
- Array of the PARTNER brand's outlets (not own)
- Used for token issuance target selection

---

### TC-365 · Integration — Upsert
**API:** POST /api/merchant/integrations

**Steps:**
1. POST: `{provider: "shopify", api_key: "KEY123", config: {}}`

**Expected Result:**
- 200 or 201
- Integration created or updated
- Appears in GET /api/merchant/integrations list

---

### TC-366 · Integration — Deactivate
**API:** DELETE /api/merchant/integrations/{provider}

**Steps:**
1. DELETE /api/merchant/integrations/shopify

**Expected Result:**
- 200 OK
- Integration deactivated
- No longer in active integrations list

---

### TC-367 · WhatsApp Balance — Merchant View
**API:** GET /api/merchant/whatsapp-balance

**Steps:**
1. GET balance as Alice

**Expected Result:**
- 200 OK
- Response: `{balance: 598}` (or current balance)

---

### TC-368 · eWards Request — Duplicate Submission
**Steps:**
1. Alice submits eWards request
2. Alice tries to submit another request while first is pending

**Expected Result:**
- Error: "You already have a pending eWards request"
- Only one active request per merchant

---

### TC-369 · eWards Request — View Status
**API:** GET /api/merchant/ewards-request

**Steps:**
1. GET eWards request status

**Expected Result:**
- 200 OK
- Response: `{status: "pending/approved/rejected/none", notes: "...", reviewed_at: null}`

---

### TC-370 · Point Valuation — History
**API:** GET /api/merchant/settings/point-valuation

**Steps:**
1. GET point valuation

**Expected Result:**
- 200 OK
- Current rate + history of rate changes
- Each history item: rate, set_at, set_by, reason

---

## MODULE 36 — PERFORMANCE & CONCURRENCY

---

### TC-371 · Login Response Time — Under 500ms
**Steps:**
1. POST /api/auth/login with valid credentials
2. Measure response time

**Expected Result:**
- Response in < 500ms
- No slow DB queries on login
- Token issued quickly

---

### TC-372 · Dashboard Load — Under 1 Second
**URL:** http://localhost:3000/dashboard

**Steps:**
1. Log in
2. Navigate to /dashboard
3. Note time until data loads

**Expected Result:**
- Page renders within 1 second
- Stats cards all populated within 1 second
- No spinner lasting > 2 seconds

---

### TC-373 · Partnership List — 20 Partnerships
**Steps:**
1. Have 20+ partnerships
2. GET /api/partnerships
3. Measure response time

**Expected Result:**
- Response < 500ms
- Pagination applied (not all 20+ in one response)

---

### TC-374 · Concurrent Token Issuance — No Duplicate Tokens
**Steps:**
1. Simultaneously issue 5 tokens (parallel API calls)
2. Check that all 5 have unique codes

**Expected Result:**
- All 5 tokens have unique HLP codes
- No two tokens share the same code
- DB unique constraint enforced

---

### TC-375 · Concurrent Redemption — Same Token
**Steps:**
1. Issue one token
2. Simultaneously send 2 redemption requests for the same token

**Expected Result:**
- Exactly 1 redemption succeeds
- 1 redemption fails: "Token already redeemed" or 409 Conflict
- No double-redemption (credit given twice)

---

### TC-376 · CSV Upload — Large File (1000 rows)
**Steps:**
1. Create CSV with 1000 customer rows
2. Upload it

**Expected Result:**
- Upload completes (may take a few seconds)
- Imported: ~1000 (minus duplicates)
- No timeout or memory error
- Progress shown if available

---

### TC-377 · Analytics API — Complex Query Performance
**API:** GET /api/analytics/summary

**Steps:**
1. With 100+ redemptions in DB
2. GET analytics summary
3. Measure time

**Expected Result:**
- Response < 1000ms
- Aggregation queries optimized
- No N+1 query issues

---

### TC-378 · Page Load — Network Detail with Many Members
**URL:** http://localhost:3000/networks/{uuid} (network with 20+ members)

**Steps:**
1. Open network with many members

**Expected Result:**
- Page loads < 2 seconds
- Member list paginated or virtualized
- No browser freeze

---

### TC-379 · Delivery Failures — Large Dataset
**API:** GET /api/delivery/failures

**Steps:**
1. With 1000+ delivery records
2. GET failures

**Expected Result:**
- Paginated response
- First page loads < 500ms
- total count accurate

---

### TC-380 · Customer Upload History — Many Uploads
**API:** GET /api/customers/uploads

**Steps:**
1. Have 50+ upload records
2. GET upload history

**Expected Result:**
- Paginated
- Sorted newest first
- Fast response

---

## MODULE 37 — REGRESSION TESTS (BUG FIXES VERIFICATION)

---

### TC-381 · Regression: Login Does NOT Logout Other Sessions
**Steps:**
1. Log in as Alice in Browser A → Token A
2. Log in as Alice in Browser B → Token B
3. Use both tokens simultaneously

**Expected Result:**
- BOTH tokens work
- Browser A NOT logged out when Browser B logs in
- Fix verified: tokens no longer deleted on login (timestamp suffix added)

---

### TC-382 · Regression: 401 Interceptor Does NOT Redirect Prematurely
**Steps:**
1. Log in as Alice
2. Navigate across pages: dashboard → partnerships → settings → dashboard
3. Observe for unexpected logouts

**Expected Result:**
- NO unexpected redirects to /login
- Token stays valid across navigation
- Fix verified: interceptor only redirects when NOT on login page

---

### TC-383 · Regression: Vite Proxy Forwards API Calls
**Steps:**
1. Frontend at localhost:3000, backend at localhost:8000
2. Any API call from frontend (e.g., GET /api/partnerships)

**Expected Result:**
- Network tab: request to /api/* proxied to backend
- No CORS error
- Fix verified: proxy target changed from 127.0.0.1 to localhost

---

### TC-384 · Regression: SA Login Works
**Steps:**
1. Navigate to /super-admin/login
2. Enter: admin@hyperlocal.internal / changeme123
3. Click Sign in

**Expected Result:**
- Login succeeds
- Redirected to /super-admin/dashboard
- Fix verified: password reset via tinker

---

### TC-385 · Regression: Fake Token Returns 422 Not 500
**Steps:**
1. GET /api/execution/lookup/FAKECODE (nonexistent token)

**Expected Result:**
- 422 Unprocessable Entity
- Body: `{message: "Invalid or unknown claim token"}`
- NOT 500 Internal Server Error
- Fix verified: null check added to lookup

---

### TC-386 · Regression: Non-CSV Upload Rejected
**Steps:**
1. Try to upload a .txt or .docx file to /api/customers/upload

**Expected Result:**
- 422 Validation error: "File must be a CSV"
- File rejected at upload
- Fix verified: mime type validation added

---

### TC-387 · Regression: Linked Offers Show on Live Partnership
**URL:** http://localhost:3000/partnerships/{uuid} (Live = status 5)

**Steps:**
1. Open a Live partnership (status = 5)
2. Scroll to "Linked Offers" section

**Expected Result:**
- Section visible
- Offers shown if attached
- Fix verified: v-if changed from `status === 4` to `status === 5 || status === 6`

---

### TC-388 · Regression: SA Credentials Shown on Login Page
**URL:** http://localhost:3000/super-admin/login

**Steps:**
1. Open SA login page
2. Look below the Sign in button

**Expected Result:**
- Dev credentials shown: Email / Password hint
- Fix verified: hint text added to login template

---

### TC-389 · Regression: ReminderSettingsView Loads Without Build Error
**URL:** http://localhost:3000/settings/reminders

**Steps:**
1. Navigate to /settings/reminders
2. Observe if page loads

**Expected Result:**
- Page loads without white screen
- No JavaScript build error
- Fix verified: template literal apostrophe fixed (backtick used)

---

### TC-390 · Regression: Multi-Tab SA Session Persistence
**Steps:**
1. Log in as SA in Tab A
2. Open Tab B, navigate to /super-admin/dashboard

**Expected Result:**
- Tab B loads dashboard without re-login
- SA token shared via localStorage
- No unexpected logout

---

### TC-391 · Regression: Customer Portal 401 Redirects to /my-rewards Only
**Steps:**
1. Customer token expires
2. Access /api/customer/rewards with expired token

**Expected Result:**
- 401 returned
- Frontend redirects to /my-rewards (not /login)
- Fix verified: customerApi interceptor checks path prefix

---

### TC-392 · Regression: Super Admin 401 Redirects to /super-admin/login Only
**Steps:**
1. SA token expires
2. Attempt to access SA route

**Expected Result:**
- Redirect to /super-admin/login (not /login)
- Fix verified: superAdminApi interceptor uses correct path

---

### TC-393 · Regression: Merchant Login Guard Skip
**URL:** http://localhost:3000/login (already logged in)

**Steps:**
1. Log in as Alice
2. Navigate to /login directly

**Expected Result:**
- Login page renders (no infinite redirect loop)
- Fix verified: `if (to.name === 'login') return` added to router guard

---

### TC-394 · Regression: Partnership Creation with Linked Offer
**Steps:**
1. Create a partner offer
2. Create partnership using "Link Existing Offer" tab
3. Check partnership detail for linked offer

**Expected Result:**
- Partnership created successfully with offer linked
- Detail page shows offer in "Linked Offers" section
- No console errors

---

### TC-395 · Regression: Customer Rewards Page Load
**URL:** http://localhost:3000/my-rewards/dashboard

**Steps:**
1. Login as customer via OTP
2. Dashboard loads

**Expected Result:**
- Page renders without errors
- Rewards tab and Activity tab both work
- No blank white screen

---

### TC-396 · Regression: Brand Registration Page Public
**URL:** http://localhost:3000/register (incognito)

**Steps:**
1. Open in incognito (no session)
2. Navigate to /register

**Expected Result:**
- Page loads without redirect to /login
- Registration form visible
- Fix verified: route has `meta: { public: true }`

---

### TC-397 · Regression: Shared Claim Link Public Page
**URL:** http://localhost:3000/shared/{code} (incognito)

**Steps:**
1. Generate a shareable link
2. Open in incognito

**Expected Result:**
- Page loads without redirect to /login
- Claim form visible
- Fix verified: route has `meta: { public: true }`

---

### TC-398 · Regression: Network Join Page Requires Auth
**URL:** http://localhost:3000/networks/join/{token}

**Steps:**
1. Try to access in incognito (no login)

**Expected Result:**
- Redirected to /login first
- After login, redirected back to join page
- Route: `meta: { requiresAuth: true }` — correct

---

### TC-399 · Regression: Follow-Up Campaign Page Loads
**URL:** http://localhost:3000/followup-campaigns

**Steps:**
1. Login as Alice
2. Navigate to /followup-campaigns

**Expected Result:**
- Page loads showing campaigns list
- Stats visible at top
- Create button visible

---

### TC-400 · Regression: Customer List Page Loads
**URL:** http://localhost:3000/customers

**Steps:**
1. Login as Alice
2. Navigate to /customers

**Expected Result:**
- Customer list loads
- Upload CSV button visible
- Upload history section present
- No blank screen or JS errors

---

## MODULE 38 — COMPLETE END-TO-END FLOWS (ADVANCED)

---

### TC-401 · Full eWards Offer Flow: Create → Partner → Issue → Redeem
**Steps:**
1. Alice creates offer: `15% Off (max ₹300)`, code: `ALPHA15`
2. Alice creates partnership with Beta, links this offer
3. Beta accepts partnership → goes Live
4. Customer visits Alpha, gets a cross-loyalty token
5. Customer goes to Beta
6. Beta redeems the token → 15% applied, capped at ₹300
7. Check analytics

**Expected Result:**
- End-to-end flow works
- Discount correctly calculated with cap
- Analytics updated: redemption + benefit recorded

---

### TC-402 · Full Network Flow: Create → Invite → Join → Partner → Token → Redeem
**Steps:**
1. Alice creates "Mumbai Food Alliance" network
2. Bob joins via invite link
3. Within network, Bob creates partnership with Alice
4. Alice accepts
5. Bob issues token to customer
6. Customer redeems at Alice
7. Analytics show cross-partnership redemption

**Expected Result:**
- Network membership enabled discovery
- Partnership created in network context
- Full token lifecycle works

---

### TC-403 · Full Announcement Flow: Partnership Live → Announce → History
**Steps:**
1. Partnership goes Live (Alpha x Beta)
2. Alice uploads 5 customers
3. Alice goes to partnership detail → Announces
4. Announcement sent to 5 customers
5. History shows announcement

**Expected Result:**
- Announcement created and logged
- 5 credits deducted from Alice's WhatsApp balance
- History shows: message excerpt, 5 recipients, timestamp

---

### TC-404 · Full Reminder Flow: Settings → Token Issue → Reminder Due
**Steps:**
1. Alice enables reminders: 6 hours before expiry
2. Token issued with 48-hour validity
3. At T-6h from expiry: reminder dispatched (logged in dev)
4. Check delivery stats

**Expected Result:**
- Reminder logged as "sent" in delivery stats
- delivery_status on claim updated
- No reminder sent if token already redeemed

---

### TC-405 · Full Customer Journey: Register → Upload → Issue → Portal → Redeem
**Steps:**
1. Alice uploads customer: Priya (9111111101)
2. Alice issues cross-loyalty token to Priya for Beta
3. Priya logs into /my-rewards → sees pending token
4. Priya visits Beta, token redeemed
5. Priya checks /my-rewards/dashboard → activity shows redemption

**Expected Result:**
- Full customer journey tracked
- Portal shows real-time status
- Activity history accurate

---

### TC-406 · Full Campaign → Segment → Send → Analytics
**Steps:**
1. Alice creates campaign: "Welcome to our partnership!"
2. Segment: customers who visited from Beta
3. Preview: 3 customers
4. Send campaign
5. Campaign appears in history with Sent status

**Expected Result:**
- Campaign reaches correct segment
- Credits deducted (3 credits)
- Campaign status: Sent

---

### TC-407 · SA Full Workflow: Register → Review → Approve → Monitor
**Steps:**
1. Brand registers at /register
2. SA logs in, reviews in brand-registrations
3. SA adds 500 credits before approval
4. SA approves the brand
5. Brand logs in and sets up profile
6. SA monitors in merchant list

**Expected Result:**
- Full SA workflow in sequence
- Merchant visible in list with correct credit balance
- Merchant can operate immediately after approval

---

### TC-408 · Shareable Link Full Flow: Generate → Share → Claim → View in Analytics
**Steps:**
1. Alice generates shareable link for Alpha x Beta
2. Bob shares with customer via WhatsApp
3. Customer opens link, enters phone, gets token
4. Customer redeems at Beta
5. Check analytics

**Expected Result:**
- Token issued via shared link
- Analytics shows redemption
- Link source trackable (future enhancement)

---

### TC-409 · Partner Rating Flow: Live → Rate → Trust Score Update
**Steps:**
1. Alpha x Beta partnership goes Live
2. After some activity, Alice rates Bob: 4 stars
3. Bob rates Alice: 5 stars
4. Check SA view: both trust scores updated

**Expected Result:**
- Both ratings visible in partnership detail
- Each brand's trust_score updated in SA merchant view
- Average calculated correctly

---

### TC-410 · Multi-Portal Session: Merchant + Customer Simultaneous
**Steps:**
1. Open Browser A: Login as Alice (merchant portal)
2. Open Browser B (incognito): Login as customer Priya (my-rewards)
3. In A: Alice issues token to Priya (9111111101)
4. In B: Priya's portal updates (refresh or real-time)
5. In A: Gina redeems token
6. In B: Priya's activity history shows redemption

**Expected Result:**
- Two completely independent auth sessions work simultaneously
- merchant_token and customer_token don't interfere
- Customer sees real-time updates to their rewards

---

### TC-411 · Event Trigger → Auto Token Issuance
**Steps:**
1. Create event trigger: on `order.completed` (min ₹200) → issue cross-loyalty token
2. POST /api/events/trigger: `{event: "order.completed", amount: 250, customer_phone: "9111111101"}`
3. Check if token auto-issued

**Expected Result:**
- Token automatically issued to 9111111101
- Token visible in customer's portal
- Event logged in event_log with status: processed

---

### TC-412 · Ecosystem Webhook Flow
**Steps:**
1. Simulate merchant exit webhook: POST /api/webhooks/ecosystem/merchant-exit
2. Check merchant's ecosystem_active status

**Expected Result:**
- With valid signature: merchant deactivated
- Without valid signature: 401/403 returned

---

### TC-413 · Bill Offers Full Flow: Create → Attach → Public View → Claim
**Steps:**
1. Alice creates offer: "20% Off at Alpha for Beta customers"
2. Alice attaches offer to Alpha x Beta partnership
3. Alice enables bill offers in settings
4. Customer opens http://localhost:3000/bill-offers/{alice-uuid}
5. Customer clicks offer → claim logged

**Expected Result:**
- Offer visible on public bill page
- Impression recorded on page load
- Claim recorded on interaction
- Analytics show impression + claim counts

---

### TC-414 · Network Offer Flow: Publish → Network Members See → Attach
**Steps:**
1. Alice publishes offer to Mumbai Food Alliance network
2. Bob (network member) opens partner-offers → Network Offers tab
3. Bob attaches Alice's offer to his partnership with Alice
4. Partnership detail shows offer linked

**Expected Result:**
- Cross-network offer sharing works
- Bob can attach Alice's offer to their joint partnership
- Both see it in partnership detail

---

### TC-415 · Follow-Up Campaign Full Flow: Expired Tokens → Campaign → Track
**Steps:**
1. Issue 10 tokens
2. Let them expire (or manually expire in DB)
3. Create follow-up campaign targeting expired unredeemed tokens
4. Send campaign
5. Stats show: 10 customers targeted

**Expected Result:**
- Campaign targets correct segment
- 10 credits deducted
- Stats update: 10 sent
- If customers return: re_engaged count increases

---

### TC-416 · Discovery → Send Proposal → Accept → Token → Redeem
**Steps:**
1. Bob searches for partners: Mumbai, Cafe
2. Alice visible in results
3. Bob sends proposal to Alice from discovery
4. Alice accepts
5. Issue → redeem full token flow

**Expected Result:**
- Discovery → Partnership → Token → Redemption all work in sequence
- Clean UX with no broken states

---

### TC-417 · Merchant Settings Full Validation
**Steps:**
1. Try to set point valuation: rate = 0 → error
2. Set rate = 1.0, reason blank → error
3. Set rate = 1.5, reason = "Initial" → success
4. Toggle discoverability ON → search shows merchant
5. Toggle discoverability OFF → search hides merchant
6. Enable bill offers → bill-offers endpoint returns enabled: true
7. Disable bill offers → endpoint returns enabled: false

**Expected Result:**
- All validations working as documented
- Toggles take immediate effect
- Settings persisted correctly

---

### TC-418 · SA Credit Management Full Flow
**Steps:**
1. SA adds 100 credits to Alpha
2. Alpha's balance: +100
3. Alpha runs campaign to 50 customers → 50 credits deducted
4. SA views Alpha's credit ledger
5. Ledger shows: +100 (allocation) and -50 (campaign)
6. Final balance: 50

**Expected Result:**
- Credits tracked correctly
- Ledger shows all transactions
- SA can see full history

---

### TC-419 · Partnership Lifecycle — Full State Machine
**States:** Proposed → Accepted → Live → Paused → Resumed (Live) → Terminated

**Steps:**
1. Alice proposes to Gina
2. Gina accepts
3. Partnership goes Live
4. Alice pauses with reason
5. Alice resumes
6. Verify state at each step

**Expected Result:**
- Each state transition works
- Correct status code at each step
- Correct UI badge at each step

---

### TC-420 · Customer Management Full Flow: Upload → Issue → Track
**Steps:**
1. Upload 10 customers via CSV
2. Stats show: 10 customers, 0 tokens issued
3. Issue tokens to 5 customers
4. Stats show: 5 tokens issued, 5 pending
5. 3 redeemed, 2 expired
6. Stats show: 3 redeemed, 2 expired

**Expected Result:**
- Customer management tracks full customer journey
- Stats accurate at each stage
- Customer list shows token status per customer

---

---

## MODULE 39 — MOBILE & RESPONSIVENESS

---

### TC-421 · Login Page — Mobile Viewport
**URL:** http://localhost:3000/login (375px viewport)

**Steps:**
1. Open Chrome DevTools → iPhone SE (375×667)
2. Navigate to /login

**Expected Result:**
- Form centered vertically
- Input fields full width, legible
- Button full width
- No horizontal scroll

---

### TC-422 · Dashboard — Mobile Viewport
**URL:** http://localhost:3000/dashboard (375px)

**Steps:**
1. Mobile viewport
2. Navigate to dashboard

**Expected Result:**
- Stat cards stack vertically (not horizontal overflow)
- Side navigation collapses to hamburger menu
- Charts fit within screen

---

### TC-423 · Partnership List — Mobile Scroll
**URL:** http://localhost:3000/partnerships (375px)

**Steps:**
1. Mobile viewport
2. Navigate to /partnerships
3. Scroll through list

**Expected Result:**
- Partnership cards stack vertically
- No text overflow
- Buttons reachable

---

### TC-424 · Redeem Token — Mobile Flow
**URL:** http://localhost:3000/redeem (375px)

**Steps:**
1. Mobile viewport
2. Enter token and bill amount
3. Complete redemption

**Expected Result:**
- Multi-step form usable on mobile
- Keyboard doesn't cover inputs
- Confirmation step clear

---

### TC-425 · Customer Portal — Mobile OTP Login
**URL:** http://localhost:3000/my-rewards (375px)

**Steps:**
1. Mobile viewport
2. Enter phone number
3. Enter OTP
4. View rewards

**Expected Result:**
- Number pad opens for phone input
- OTP input accepts numeric keyboard
- Rewards cards stack cleanly

---

### TC-426 · QR Claim Page — Mobile Friendly
**URL:** http://localhost:3000/claim/{uuid} (375px)

**Steps:**
1. Mobile viewport
2. Load claim page
3. Enter phone and submit

**Expected Result:**
- Page fully functional on mobile
- No zoom required
- Token displayed in large, easy-to-read font

---

### TC-427 · SA Dashboard — Tablet Viewport (768px)
**URL:** http://localhost:3000/super-admin/dashboard

**Steps:**
1. Set viewport to iPad (768×1024)
2. Navigate to SA dashboard

**Expected Result:**
- Stat cards in grid layout
- Navigation visible as sidebar or top nav
- Tables readable without horizontal scroll

---

### TC-428 · Partner Offers Create — Mobile Form
**URL:** http://localhost:3000/partner-offers/create (375px)

**Steps:**
1. Mobile viewport
2. Fill out create form
3. Submit

**Expected Result:**
- All form fields accessible
- Dropdowns work on mobile
- Save button reachable without scroll

---

### TC-429 · Registration Page — Mobile
**URL:** http://localhost:3000/register (375px)

**Steps:**
1. Mobile viewport
2. Navigate to /register
3. Fill out multi-section form

**Expected Result:**
- All sections visible by scrolling
- No fields cut off
- Submit button accessible

---

### TC-430 · Shareable Link Page — Mobile
**URL:** http://localhost:3000/shared/{code} (375px)

**Steps:**
1. Mobile viewport
2. Open shareable claim link

**Expected Result:**
- Claim form mobile-friendly
- WhatsApp share opens WhatsApp app (not web)
- Token displayed clearly after claim

---

## MODULE 40 — NOTIFICATIONS & MESSAGING

---

### TC-431 · WhatsApp Credit — Low Balance Warning
**URL:** http://localhost:3000/settings

**Steps:**
1. Set WhatsApp balance to 10 (low)
2. Navigate to settings

**Expected Result:**
- Orange warning: "Low balance — 10 credits remaining"
- Suggest topping up
- Warning appears in relevant UI sections

---

### TC-432 · WhatsApp Credit — Zero Balance Warning
**URL:** http://localhost:3000/settings or campaigns

**Steps:**
1. Set balance to 0
2. Navigate to campaigns or try to send

**Expected Result:**
- Red error: "No WhatsApp credits. Campaigns cannot be sent."
- Campaign send button disabled
- Clear CTA to contact admin

---

### TC-433 · Campaign — Send Now vs Schedule
**URL:** http://localhost:3000/campaigns (create)

**Steps:**
1. Create campaign
2. Select "Send Now" option
3. Submit

**Expected Result:**
- Campaign dispatched immediately (or queued)
- Status: Sent / Processing
- Separate from "Scheduled" status

---

### TC-434 · Campaign — Run Endpoint
**API:** POST /api/campaigns/{uuid}/run

**Steps:**
1. POST to run a draft campaign

**Expected Result:**
- 200 OK
- Campaign status changes to Running or Sent
- Credits deducted

---

### TC-435 · Campaign — Schedule then Cancel
**Steps:**
1. Schedule campaign for tomorrow
2. Before scheduled time, cancel it

**Expected Result:**
- Campaign cancelled
- Credits NOT deducted (not yet sent)
- Status: Cancelled

---

### TC-436 · Campaign — View Sent Campaign Detail
**URL:** http://localhost:3000/campaigns/{uuid}

**Steps:**
1. Open a Sent campaign

**Expected Result:**
- Shows: name, template, segment, scheduled_at, sent_at
- Recipient count
- Delivery status breakdown (if available)

---

### TC-437 · Notification — Token Issued to Customer
**Steps:**
1. Issue token with customer phone
2. Check delivery stats

**Expected Result:**
- delivery_status = 'sent' (logged)
- Token message logged in system
- No actual WhatsApp in dev — event logged only

---

### TC-438 · Notification — Partnership Announcement Preview
**Steps:**
1. Open Live partnership
2. Write custom announcement message
3. Click Preview

**Expected Result:**
- Modal shows exact WhatsApp message format
- Character count shown (≤ 1024 chars recommended for WhatsApp)
- Variables replaced with real values

---

### TC-439 · Campaign Templates — List All
**API:** GET /api/campaigns/templates

**Steps:**
1. GET templates

**Expected Result:**
- 200 OK
- Array of templates: welcome_partner, new_offer, expiry_reminder, winback
- Each: name, body, required_variables

---

### TC-440 · Campaign Segment Preview — Zero Customers
**API:** POST /api/campaigns/segment-preview

**Steps:**
1. POST: `{segment: "partner_customers", partnership_uuid: "new-empty-partnership"}`

**Expected Result:**
- 200 OK
- `{count: 0, message: "No customers match this segment"}`
- No error, clean empty state

---

### TC-441 · Campaign — Segment: All Own Customers
**Steps:**
1. Create campaign
2. Segment: "Own Customers"
3. Preview

**Expected Result:**
- Count = all uploaded customers
- Includes all customers regardless of partnership
- Used for direct promotions

---

### TC-442 · Campaign — Segment: Partner Customers
**Steps:**
1. Create campaign
2. Segment: "Customers from [Partner]"
3. Select: Alpha x Beta partnership
4. Preview

**Expected Result:**
- Count = customers who came from Beta (claimed token from Beta and redeemed at Alpha)
- Segment is cross-partnership specific

---

### TC-443 · Message Character Limit — Campaign vs Announcement
**Steps:**
1. Campaign message: max 1000 chars (custom template)
2. Announcement message: max 1000 chars

**Expected Result:**
- Character counters visible in both UIs
- Both enforce limit
- Warning shown near limit

---

### TC-444 · Follow-Up Campaign — Credits per Message
**Steps:**
1. Follow-up campaign targets 20 customers
2. Note credit balance before send
3. Send campaign

**Expected Result:**
- Credits deducted: 20 (1 per customer)
- Balance updated: balance - 20
- Insufficient balance → send blocked

---

### TC-445 · Delivery Stats — UI Widget
**URL:** http://localhost:3000/settings or dashboard

**Steps:**
1. View delivery stats widget (if in UI)
2. Check: total sent, delivered, failed counts

**Expected Result:**
- Stats shown in clean format
- Link to full failures list
- Data matches GET /api/delivery/stats

---

## MODULE 41 — SA REPORTING

---

### TC-446 · SA Dashboard — Live Partnership Count
**URL:** http://localhost:3000/super-admin/dashboard

**Steps:**
1. Note live_partnerships count
2. Create and go-live a new partnership
3. Refresh SA dashboard

**Expected Result:**
- live_partnerships count increased by 1
- Count is accurate platform-wide

---

### TC-447 · SA Dashboard — Low Credits Alert
**URL:** http://localhost:3000/super-admin/dashboard

**Steps:**
1. Set a merchant's balance to 5 credits
2. View SA dashboard "Low Credits" stat

**Expected Result:**
- Low Credits count shows how many merchants have < threshold
- Threshold configurable (e.g., < 50)
- Count accurate

---

### TC-448 · SA Dashboard — Zero Credits Alert
**URL:** http://localhost:3000/super-admin/dashboard

**Steps:**
1. Merchant has 0 credits
2. View SA dashboard "Zero Credits" stat

**Expected Result:**
- Zero Credits count includes that merchant
- SA can quickly identify merchants needing top-up

---

### TC-449 · SA — Search Merchant by Name
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. Type "Alpha" in search box
2. Press enter or search

**Expected Result:**
- Only brands matching "Alpha" shown
- Case-insensitive search

---

### TC-450 · SA — Search Merchant by Email
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. Search by email: `alice@`

**Expected Result:**
- Merchant with alice@ email shown
- Partial email search works

---

### TC-451 · SA Merchant List — Sort by Credits
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. Click "Credits" column header to sort

**Expected Result:**
- List sorted ascending or descending by credit balance
- Zero-credit merchants at bottom (asc) or top (desc)

---

### TC-452 · SA — Export Merchant List (if available)
**URL:** http://localhost:3000/super-admin/merchants

**Steps:**
1. Look for Export/Download button

**Expected Result:**
- If available: CSV exported with merchant data
- If not available: Feature documented as future work

---

### TC-453 · SA — Ecosystem Active Count
**URL:** http://localhost:3000/super-admin/dashboard

**Steps:**
1. View "Ecosystem Active" stat
2. Toggle one merchant's ecosystem_active to OFF
3. Refresh dashboard

**Expected Result:**
- Ecosystem Active count decreases by 1
- Count is accurate

---

### TC-454 · SA — Pending Registrations Count Real-Time
**URL:** http://localhost:3000/super-admin/dashboard

**Steps:**
1. Note pending_registrations count
2. New brand registers at /register
3. Refresh dashboard

**Expected Result:**
- pending_registrations count increases by 1
- Immediate (within 1 page refresh)

---

### TC-455 · SA — Credit Ledger: All Merchants
**Steps:**
1. SA allocates credits to 5 merchants
2. Each has different amounts and notes
3. View each merchant's ledger

**Expected Result:**
- Each ledger shows only that merchant's transactions
- No cross-contamination between merchant ledgers
- Totals accurate per merchant

---

### TC-456 · SA — Reject Integration Request
**API:** POST /api/super-admin/integration-requests/{id}/reject

**Steps:**
1. POST with reason: `{reason: "Not meeting eligibility criteria"}`

**Expected Result:**
- 200 OK
- Request status: rejected
- Reason stored

---

### TC-457 · SA — Approve Brand Registration via API
**API:** POST /api/super-admin/brand-registrations/{id}/approve

**Steps:**
1. POST approve

**Expected Result:**
- 200 OK
- Brand activated
- Merchant can now login

---

### TC-458 · SA — Reject Brand Registration via API
**API:** POST /api/super-admin/brand-registrations/{id}/reject

**Steps:**
1. POST: `{reason: "Incomplete information"}`

**Expected Result:**
- 200 OK
- Registration status: rejected
- Reason stored

---

### TC-459 · SA — Integration Requests Pagination
**API:** GET /api/super-admin/integration-requests?page=2

**Steps:**
1. Have 20+ integration requests
2. Request page 2

**Expected Result:**
- 200 OK
- Second page returned
- Pagination metadata present

---

### TC-460 · SA — Merchant Credit Ledger API
**API:** GET /api/super-admin/merchants/{id}/ledger

**Steps:**
1. GET ledger for a specific merchant

**Expected Result:**
- 200 OK
- Array of credit transactions
- Each: amount, note, created_at, balance_after

---

## MODULE 42 — INTEGRATION & WEBHOOKS

---

### TC-461 · Integration Hub — List Active Integrations
**API:** GET /api/merchant/integrations

**Steps:**
1. GET /api/merchant/integrations

**Expected Result:**
- 200 OK
- Array of active integrations
- Each: provider, is_active, created_at

---

### TC-462 · Integration Hub — Upsert Shopify
**API:** POST /api/merchant/integrations

**Steps:**
1. POST: `{provider: "shopify", api_key: "shpat_test123", config: {shop: "myshop.myshopify.com"}}`

**Expected Result:**
- 200/201 OK
- Shopify integration active
- Appears in GET list

---

### TC-463 · Integration Hub — Upsert WooCommerce
**API:** POST /api/merchant/integrations

**Steps:**
1. POST: `{provider: "woocommerce", consumer_key: "ck_test", consumer_secret: "cs_test", config: {site_url: "https://mystore.com"}}`

**Expected Result:**
- 200/201 OK
- WooCommerce integration created
- Shows in list

---

### TC-464 · Integration Hub — Deactivate Integration
**API:** DELETE /api/merchant/integrations/shopify

**Steps:**
1. Deactivate Shopify integration

**Expected Result:**
- 200 OK
- Shopify removed from active integrations
- Orders from Shopify no longer trigger events

---

### TC-465 · Webhook — Ecosystem Merchant Exit
**API:** POST /api/webhooks/ecosystem/merchant-exit

**Steps:**
1. Send with valid webhook signature
2. Body: `{merchant_id: 1, reason: "Subscription expired"}`

**Expected Result:**
- 200 OK
- Merchant ecosystem_active set to false
- No error if already inactive

---

### TC-466 · Webhook — Ecosystem Merchant Reactivate
**API:** POST /api/webhooks/ecosystem/merchant-reactivate

**Steps:**
1. Send with valid signature
2. Body: `{merchant_id: 1}`

**Expected Result:**
- 200 OK
- Merchant ecosystem_active set to true

---

### TC-467 · Shopify Webhook — Process Order
**API:** POST /api/connectors/shopify/{merchantKey}/orders

**Steps:**
1. Get merchant API key
2. POST Shopify order payload with customer email and total price

**Expected Result:**
- 200 OK
- Order processed as event
- event_log shows new entry
- If trigger matches: token auto-issued

---

### TC-468 · WooCommerce Webhook — Process Order
**API:** POST /api/connectors/woocommerce/{merchantKey}/orders

**Steps:**
1. POST WooCommerce order JSON

**Expected Result:**
- 200 OK
- Processed same as Shopify
- event_log entry created

---

### TC-469 · Event Ingest — Direct API
**API:** POST /api/events/ingest

**Steps:**
1. POST with merchant_key and event data
2. Include verify_event header

**Expected Result:**
- 200 OK
- Event logged
- Triggers evaluated

---

### TC-470 · Event — Invalid Merchant Key
**API:** GET /api/events/pixel/INVALIDKEY

**Steps:**
1. Use non-existent merchant key

**Expected Result:**
- 404 Not Found
- Event NOT logged
- No crash

---

### TC-471 · Event Trigger — Multiple Triggers for Same Event
**Steps:**
1. Create 3 triggers all on `order.completed`
2. Fire one order event

**Expected Result:**
- All 3 triggers evaluated
- All matching triggers fire
- Multiple tokens issued if all conditions met

---

### TC-472 · Event Source — Generate New API Key
**URL:** http://localhost:3000/event-sources/setup

**Steps:**
1. Open event source setup
2. Create new source
3. Note the generated API key

**Expected Result:**
- Unique API key generated
- Key shown once (copy prompt)
- Used to authenticate event ingestion

---

### TC-473 · Shopify Integration — Invalid API Key
**API:** POST /api/connectors/shopify/{merchantKey}/orders

**Steps:**
1. Use invalid/expired merchant key

**Expected Result:**
- 401 or 403
- Order not processed
- No event logged

---

### TC-474 · Event Trigger — Min Amount Not Met
**Steps:**
1. Trigger: min_order_amount = 500
2. Fire event with amount = 300

**Expected Result:**
- Trigger does NOT fire
- No token issued
- Event logged: trigger_skipped reason: "amount_below_minimum"

---

### TC-475 · Event Log — Search by Event Type
**API:** GET /api/event-log?event_type=order.completed

**Steps:**
1. Filter event log by type

**Expected Result:**
- 200 OK
- Only order.completed events returned
- Other event types excluded

---

## MODULE 43 — PARTNERSHIP OFFERS ADVANCED

---

### TC-476 · Offer — Bulk Attach to Multiple Partnerships
**Steps:**
1. Create one offer
2. Attach to Partnership 1
3. Attach to Partnership 2
4. Attach to Partnership 3
5. View offer detail

**Expected Result:**
- Offer shown as attached to 3 partnerships
- Each partnership detail shows the offer
- No limit on how many partnerships an offer can attach to

---

### TC-477 · Offer — Edit While Attached to Live Partnership
**Steps:**
1. Offer attached to Live Alpha x Beta
2. Edit offer: change discount from 15% to 20%
3. Save

**Expected Result:**
- Offer updated
- Live partnership now uses 20% discount for new claims
- Existing unredeemed claims may use old or new rate (verify behavior)

---

### TC-478 · Network Offers — Member Can Attach to Own Partnership
**Steps:**
1. Alice publishes offer to network
2. Bob (network member) attaches Alice's offer to Bob's partnership with Gina
3. Check Bob's partnership detail

**Expected Result:**
- Alice's offer visible in Bob's partnership
- Bob did NOT create the offer — he's using Alice's published offer
- Attribution visible (offer created by Alice)

---

### TC-479 · Offer — Attach via API
**API:** POST /api/partner-offers/{uuid}/attach

**Steps:**
1. POST: `{partnership_id: 1}`

**Expected Result:**
- 200 OK
- Offer attached to partnership
- GET partnership shows offer in linked list

---

### TC-480 · Offer — Detach via API
**API:** DELETE /api/partner-offers/{uuid}/attach/{partnershipId}

**Steps:**
1. DELETE to remove offer from partnership

**Expected Result:**
- 200 OK
- Offer removed from partnership
- Partnership no longer shows offer

---

### TC-481 · Offer — Publish via API
**API:** POST /api/partner-offers/{uuid}/publish

**Steps:**
1. POST: `{network_id: 1}`

**Expected Result:**
- 200 OK
- Offer visible to network members

---

### TC-482 · Offer — Unpublish via API
**API:** DELETE /api/partner-offers/{uuid}/publish/{networkId}

**Steps:**
1. DELETE to unpublish from network

**Expected Result:**
- 200 OK
- Offer removed from network offers
- Network members can no longer see or attach it

---

### TC-483 · Offer — Toggle Active/Inactive via API
**API:** POST /api/partner-offers/{uuid}/toggle

**Steps:**
1. POST to toggle status

**Expected Result:**
- 200 OK
- Status flipped (active → inactive or reverse)
- New status in response

---

### TC-484 · Offer Detail — Analytics
**URL:** http://localhost:3000/partner-offers/{uuid}

**Steps:**
1. Open offer detail
2. Find analytics section

**Expected Result:**
- Shows: times attached to partnerships, impressions (bill offers), claims
- Issuance count (if used in token issuance)
- Redemption count

---

### TC-485 · Offer — Cannot Be Attached to Non-Partner Partnership
**Steps:**
1. Alpha and Gina have NO partnership
2. Alice tries to attach her offer to "Alpha x Gina" partnership (doesn't exist)

**Expected Result:**
- Error: "Partnership not found" or 404
- Cannot attach offer to partnerships you're not part of

---

### TC-486 · Partner Offers List — Pagination
**API:** GET /api/partner-offers?page=2

**Steps:**
1. Have 20+ offers
2. Request page 2

**Expected Result:**
- 200 OK
- Second page of offers returned
- Total count accurate

---

### TC-487 · Network Offers — Not Visible Without Membership
**Steps:**
1. Brand with no network membership
2. Navigate to partner-offers → Network Offers tab

**Expected Result:**
- Empty state: "Join a network to see network offers"
- No offers shown
- Network join CTA present

---

### TC-488 · Offer — Update via API
**API:** PUT /api/partner-offers/{uuid}

**Steps:**
1. PUT with updated fields

**Expected Result:**
- 200 OK
- Updated offer returned
- Changes reflected in GET

---

### TC-489 · Offer — Show via API
**API:** GET /api/partner-offers/{uuid}

**Steps:**
1. GET single offer

**Expected Result:**
- 200 OK
- Full offer object including: pos_redemption_type, discount values, limits, attached partnerships

---

### TC-490 · Offer — Available for Partnership — Only Own Offers
**API:** GET /api/partner-offers/available/{partnershipUuid}

**Steps:**
1. Alice calls this endpoint for her partnership
2. Bob's offers should NOT appear

**Expected Result:**
- Only Alice's own offers returned
- Cross-merchant offer mixing not allowed in this endpoint

---

## MODULE 44 — SYSTEM HEALTH & MONITORING

---

### TC-491 · Health Check — Response Time
**API:** GET /api/health

**Steps:**
1. GET /api/health
2. Measure response time

**Expected Result:**
- Response < 50ms
- Status: ok
- No DB queries on health check

---

### TC-492 · Health Check — Available When Backend Starting
**Steps:**
1. Backend not yet fully started
2. Call /api/health

**Expected Result:**
- Either 200 OK (if started) or connection refused
- Not a 500 error

---

### TC-493 · Backend — 404 for Unknown Routes
**API:** GET /api/nonexistent-endpoint

**Steps:**
1. Call a route that doesn't exist

**Expected Result:**
- 404 Not Found (JSON)
- `{"message": "Not Found"}` or similar
- Not HTML Laravel error page in production mode

---

### TC-494 · Backend — 405 for Wrong HTTP Method
**API:** GET /api/auth/login (should be POST)

**Steps:**
1. Send GET to a POST-only endpoint

**Expected Result:**
- 405 Method Not Allowed
- `{"message": "Method Not Allowed"}`

---

### TC-495 · Frontend — 404 Route (Unknown SPA Path)
**URL:** http://localhost:3000/nonexistent-page

**Steps:**
1. Navigate to a URL that doesn't exist in router

**Expected Result:**
- Vue router shows 404 component
- OR redirects to /dashboard (if authenticated)
- Not a blank white screen

---

### TC-496 · Backend — JSON Error Responses
**Steps:**
1. Trigger a validation error (missing required field)
2. Trigger a 404 (not found)
3. Trigger a 403 (forbidden)
4. Check response format for each

**Expected Result:**
- All errors return JSON (not HTML)
- Consistent format: `{"message": "...", "errors": {...}}`
- Content-Type: application/json on all errors

---

### TC-497 · Database — SQLite File Integrity
**Steps:**
1. Run all operations (create, read, update, delete)
2. No corruption errors

**Expected Result:**
- No "database is locked" or "disk image is malformed" errors
- All operations succeed
- SQLite file remains intact

---

### TC-498 · Session — localStorage Persistence Across Browser Restart
**Steps:**
1. Login as Alice
2. Close browser completely
3. Reopen browser, navigate to /dashboard

**Expected Result:**
- Token still in localStorage
- Stays logged in (no re-login required)
- Session persists until explicit logout

---

### TC-499 · Frontend Build — No Console Errors on Load
**Steps:**
1. Open any page
2. Open browser DevTools → Console
3. Load the page fresh

**Expected Result:**
- No red errors in console
- Maybe yellow warnings for optional things, but no errors
- Vue warnings for missing props handled gracefully

---

### TC-500 · Full System Check — All Pages Load Without Error
**Steps:**
1. Login as Alice and visit every page:
   - /dashboard, /partnerships, /find-partners, /partner-offers,
   - /event-sources, /event-triggers, /customers, /campaigns,
   - /followup-campaigns, /networks, /growth, /settings,
   - /settings/reminders, /redeem
2. Login as SA, visit:
   - /super-admin/dashboard, /super-admin/merchants,
   - /super-admin/brand-registrations, /super-admin/requests
3. Visit public pages:
   - /register, /marketplace, /my-rewards, /bill-offers/{uuid}

**Expected Result:**
- Every page loads without white screen or JS error
- Data shown (or empty state shown — not null/undefined crash)
- Navigation between pages works without logout

---

## SUMMARY TABLE

| Module | Scenarios | Count |
|--------|-----------|-------|
| Super Admin Login & Dashboard | TC-001 to TC-005 | 5 |
| Brand Self-Registration | TC-006 to TC-010 | 5 |
| SA Brand Registration Review | TC-011 to TC-015 | 5 |
| SA Brand Management | TC-016 to TC-020 | 5 |
| Merchant Login | TC-021 to TC-024 | 4 |
| Dashboard & Analytics | TC-025 to TC-027 | 3 |
| Partnership Lifecycle | TC-028 to TC-038 | 11 |
| Token Issuance | TC-039 to TC-044 | 6 |
| Token Redemption | TC-045 to TC-050 | 6 |
| Customer Management | TC-051 to TC-056 | 6 |
| Partner Offers | TC-057 to TC-062 | 6 |
| Find Partners (Discovery) | TC-063 to TC-066 | 4 |
| Networks | TC-067 to TC-071 | 5 |
| Campaigns | TC-072 to TC-076 | 5 |
| Merchant Settings | TC-077 to TC-084 | 8 |
| Growth Insights | TC-085 to TC-087 | 3 |
| Customer Portal (OTP) | TC-088 to TC-090 | 3 |
| SA Logout & Session | TC-091 to TC-092 | 2 |
| Data Integrity & Edge Cases | TC-093 to TC-100 | 8 |
| Full End-to-End Flows (Basic) | TC-101 to TC-110 | 10 |
| eWards Offer Structure | TC-111 to TC-140 | 30 |
| Follow-Up Campaigns | TC-141 to TC-155 | 15 |
| Partnership Announcements | TC-156 to TC-165 | 10 |
| WhatsApp Delivery Tracking | TC-166 to TC-175 | 10 |
| Shareable Claim Links | TC-176 to TC-185 | 10 |
| Partner Ratings | TC-186 to TC-200 | 15 |
| Token Reminder Settings | TC-201 to TC-215 | 15 |
| API Security & Authorization | TC-216 to TC-240 | 25 |
| Analytics Deep Dive | TC-241 to TC-255 | 15 |
| Event Triggers | TC-256 to TC-275 | 20 |
| Bill Offers (Public) | TC-276 to TC-285 | 10 |
| Customer Portal Deep Dive | TC-286 to TC-300 | 15 |
| Growth Module | TC-301 to TC-320 | 20 |
| Super Admin Advanced | TC-321 to TC-340 | 20 |
| Data Validation & Edge Cases | TC-341 to TC-370 | 30 |
| Performance & Concurrency | TC-371 to TC-380 | 10 |
| Regression Tests | TC-381 to TC-400 | 20 |
| Advanced End-to-End Flows | TC-401 to TC-420 | 20 |
| Mobile & Responsiveness | TC-421 to TC-430 | 10 |
| Notifications & Messaging | TC-431 to TC-445 | 15 |
| SA Reporting | TC-446 to TC-460 | 15 |
| Integration & Webhooks | TC-461 to TC-475 | 15 |
| Partnership Offers Advanced | TC-476 to TC-490 | 15 |
| System Health & Monitoring | TC-491 to TC-500 | 10 |
| **TOTAL** | | **500** |

---

## KNOWN BUGS (Already Fixed in Code)

| # | Bug | Fix Applied |
|---|-----|-------------|
| 1 | Fake token on /redeem caused 500 crash (null pointer) | Fixed — returns proper 422 |
| 2 | Non-CSV files (.txt) accepted by upload endpoint | Fixed — now only .csv allowed |
| 3 | Super admin credentials missing from login page | Fixed — shown below Sign in button |
| 4 | SA password hash mismatch (changeme123 not working) | Fixed — password reset via tinker |
| 5 | Login revoked all other sessions (token deletion bug) | Fixed — timestamp suffix on token names |
| 6 | 401 interceptor redirected all routes to /login | Fixed — path check + debounce flag |
| 7 | Vite proxy 127.0.0.1 vs localhost mismatch | Fixed — proxy target changed to localhost |
| 8 | Linked Offers not shown (v-if used wrong status code 4 vs 5) | Fixed — condition: status === 5 or 6 |
| 9 | ReminderSettingsView build error (apostrophe in string) | Fixed — backtick template literal used |
| 10 | SA token could not be validated after login | Fixed — separate Sanctum guard for SA |

## KNOWN LIMITATIONS (Not Bugs — By Design)

| # | Limitation |
|---|------------|
| 1 | WhatsApp messages not actually sent (no gateway connected) — delivery logged only |
| 2 | eWards adapter not implemented (throws error if eWards actions triggered) |
| 3 | Customer classifier uses stub data (eWards TODO) |
| 4 | Announcement sending is logged but not dispatched via WhatsApp |
| 5 | OTP via WhatsApp/SMS not connected — check API response for OTP in dev mode |
| 6 | Email notifications not sent (no mail driver configured) |
| 7 | Scheduler not running in dev mode — reminders/follow-ups need manual trigger |
| 8 | Trust score updates are synchronous — may need queue for production |
| 9 | Event pixel endpoint returns JSON, not actual 1x1 GIF (production TODO) |
| 10 | Shopify/WooCommerce webhook processing uses basic auth — production needs HMAC |

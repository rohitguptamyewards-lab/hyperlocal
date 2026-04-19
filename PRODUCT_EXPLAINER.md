# Hyperlocal Partnership Network — Product Explainer

> **Purpose:** What this platform does, who it is for, and how each feature works.
> Written for founders, investors, merchant onboarding, and internal product clarity.
> Last updated: 2026-04-10

---

## The Problem

Every independent merchant in a neighbourhood knows the same truth: the person who just left the yoga studio is exactly the kind of person who would buy their coffee. The person who just picked up dry cleaning is exactly the kind of person who would browse their bookstore.

They have always known this. They have never been able to act on it — not cleanly, not at scale, not without handing over their customer list, not without compromising their own loyalty programme, not without losing track of what was exchanged.

The platform solves this. It gives independent merchants a structured way to send each other customers, run cross-merchant campaigns, and exchange loyalty value — without merging anything, without losing control of anything, with a ledger that shows exactly what was given and what was received.

---

## The Story of Priya's Café

### Monday, 9:14 AM — The Ceiling

Priya owns a specialty coffee café in Koramangala. Good coffee. Great location. But every month looks the same — the same 200 customers, the same revenue, the same ceiling.

She has tried Instagram ads. She has tried discount aggregators. Every new customer costs ₹300 to acquire and half of them never come back.

She opens the platform for the first time.

### Monday, 9:22 AM — The Discovery

The platform has already been watching the neighbourhood.

It knows Priya's café sits in a cluster with a yoga studio, a dry cleaner, a bookstore, and a premium salon — all within 400 metres. It scores each potential partner: same customer profile, no menu overlap, complementary visit rhythm.

Three suggestions appear. At the top: Aria Wellness Studio, 200 metres away. Their customers arrive at 7 AM. Priya's café opens at 7:30 AM. The fit score is 91.

Priya clicks *Request Partnership.*

### Tuesday, 11:08 AM — The Terms

Aria's owner Meera receives the proposal. She reviews Priya's terms:

> *"Customers who visit Aria and show their claim token at my café within 48 hours get 15% off their first order. Cap: ₹80 per bill. Monthly limit: 150 redemptions."*

Meera adjusts — she wants the offer limited to weekdays only, and only to her premium-tier members. Priya accepts. Both agree.

The partnership goes **LIVE.**

### Wednesday, 7:31 AM — The First Customer

A woman named Shreya leaves Aria after her morning yoga class. Outside, she scans the small QR code on the door frame. WhatsApp opens with a pre-filled message. She taps send.

Four seconds later:

> *"Your claim token: HLC-7749. Valid at Priya's Café until 9:31 AM today. Show this to the cashier."*

She walks 200 metres and orders a flat white.

### Wednesday, 7:33 AM — The Cashier's Screen

Priya's cashier types HLC-7749. The rules engine runs silently in under 200ms:

- Token valid ✓
- Aria premium member ✓
- Weekday ✓
- Monthly cap: 1 of 150 ✓
- Bill ₹180, cap ₹80 — discount: ₹27 ✓

Approved. The redemption is recorded. Shreya pays and leaves.

She did not know about this café two days ago.

### Two Weeks Later — The Numbers

Priya opens her analytics dashboard.

47 new customers have walked in from Aria. The platform has been tracking their journey:

- 31 came once — first visit attributed to Aria
- 12 came a second time within 30 days — converted
- 4 came three or more times — retained

Her **reciprocity score** shows she has sent more customers to Aria than she has received. The platform flags this — she could renegotiate or find a more balanced second partner.

Her **ledger** shows the exact cost: ₹1,240 in discounts given. Revenue from those same customers: ₹8,460. Return on partnership: **6.8x.**

She has never had a number like this for a marketing spend.

### Month Two — The Campaign

Of the 47 Aria customers, 28 have not returned after their first visit.

Priya builds a campaign. Not to strangers. Not to her own customers. To the **exact people who visited Aria but have not come back to her café.**

She picks the template: *"We miss you."*
She sets the segment: **Partner's customers — Aria Wellness.**
She writes the offer. She registers the promo code `COLDBREWARIA` — the code lives on her own POS, the platform just knows it exists.

The platform resolves the audience: **23 eligible recipients.**

Priya has never seen this list. She will never see this list. The platform does not hand her Meera's customer database. It sends 23 WhatsApp messages on Priya's behalf, to people who have a relationship with Aria, with an offer that is valid only at Priya's café, with a promo code that Priya controls entirely.

Meera approved the partnership terms. The platform enforces them. No customer data crosses the boundary.

Thursday evening, 23 messages go out. 9 customers walk in over the next 3 days. The platform syncs the redemption count from her POS overnight: **9 codes used.**

### Month Three — The Network

Priya mentions the platform to the salon owner next door, Kiran. Kiran joins. Then the bookstore. Then two more independents from the next street.

Priya creates a **Hyperlocal Network: Koramangala Independents.**

Five merchants. One shared intelligence layer. They now see each other's customer flow patterns. They can run coordinated campaigns. When Priya targets an audience, she can reach people who have also visited the salon, the bookstore, the dry cleaner — warm leads who already trust her neighbours.

She is not advertising to strangers anymore.

### The Insight That Changes Everything

Her **90-day retention rate for partnership customers: 34%.**
Her **90-day retention rate for Instagram ad customers: 6%.**

Partnership customers are **5.6x more likely to become regulars.**

They arrive pre-qualified. They come because a trusted local business sent them. They already feel part of the neighbourhood.

Priya cancels her Instagram ad spend entirely.

---

## What the Platform Actually Did

| What happened | Feature |
|---|---|
| Suggested Aria as the right partner | Discovery — category, geo, and cluster density scoring |
| Negotiated terms, set caps, went live | Partnership state machine + Rules Engine |
| QR → WhatsApp → claim token in 4 seconds | Customer Activation layer |
| Cashier validated rules in real time | Execution layer — 10-step rules check |
| Every rupee tracked | Ledger — double-entry per redemption |
| 30/60/90 day retention, ROI, first-visit | Analytics + Attribution engine |
| Targeted 28 lapsed partner customers | Campaign — partner segment source |
| Promo code sent, count synced from POS | PromoCode registry + POS adapter sync |
| Five merchants coordinating together | Network module |

---

## How Cross-Merchant Loyalty Works

### The Starting Point

Every merchant on the platform runs their own loyalty programme independently. Their points, their rules, their customer relationships. The platform does not touch any of that.

When two merchants form a partnership, they are not merging their loyalty programmes. They are opening a **controlled channel** through which their customers can earn benefits at each other's stores — on terms that each merchant sets, owns, and can revoke at any time.

### The Basic Mechanic

Merchant A and Merchant B are partners.

A customer of Merchant A visits Merchant B. At the cashier, they present their claim token. Merchant B's system checks the partnership terms and decides what benefit to give. That benefit is recorded in the **partnership ledger** — a shared accounting layer that belongs to neither merchant but sits between them.

That is the entire mechanic. Nothing else crosses.

Merchant A's loyalty points: untouched.
Merchant B's loyalty points: untouched.
The ledger records: *on this date, Merchant B gave this customer a benefit worth this amount, under this partnership.*

### What Each Merchant Controls

#### The Benefit Structure

Each merchant decides what a visiting partner-customer receives. Three modes:

**Percentage of bill**
Give the partner's customers a percentage discount. The cap prevents unlimited exposure — e.g. maximum ₹100 off regardless of bill size.

**Fixed rupee amount**
A flat amount, triggered only if the customer spends above a minimum threshold the merchant sets.

**Points denomination**
The customer earns points — in their home merchant's loyalty programme, not the visited merchant's. The denomination (1x, 1.5x, 2x) is set by the merchant being visited. The points land in the customer's home account. No enrolment into a new scheme. No merge.

#### The Cap Structure

Five layers of cap, all independent:

| Cap | What it controls |
|---|---|
| **Per-bill cap** | Maximum benefit on a single transaction, regardless of bill size |
| **Minimum bill** | Customer must spend at least this much to qualify |
| **Monthly cap** | Total benefit given across all partner customers this calendar month |
| **Daily cap** | Maximum benefit in a single day |
| **Lifetime cap per customer** | A single customer can only receive this much in total, ever |

Any cap hit causes the redemption to fail cleanly at the cashier with a clear reason code. The partnership continues — only that transaction is blocked.

#### The Rules Layer

Beyond caps, each merchant controls the eligibility rules that govern whether a benefit is given at all:

**First-time only**
The benefit is only available to a partner's customer on their very first visit. After that they are on their own. Used for acquisition-focused partnerships where the merchant wants new faces, not subsidised repeats.

**Uses per customer**
How many times can a single customer claim the benefit — once a month, twice ever, unlimited. The merchant decides.

**Cooling period**
A customer who redeemed yesterday cannot redeem again for X days. Prevents gaming.

**Blackout weekdays**
Specific days of the week where the benefit does not apply — e.g. weekends when the merchant is already at capacity and does not need partner-driven traffic.

**Blackout dates**
Specific calendar dates blocked — public holidays, sale periods, event days.

**Time band**
The benefit only applies between set hours — e.g. 2 PM to 5 PM only, the slow period when footfall is actively wanted.

#### The Operational Controls

**Master toggle — Pause / Resume**
A single switch that suspends the entire partnership immediately. No new claims issued. No redemptions accepted. Terms preserved — resuming restores everything as it was.

**Issue tokens (on/off)**
Customers can no longer generate new claim tokens at this merchant's outlets, but existing tokens can still be redeemed.

**Accept redemptions (on/off)**
Incoming partner-customer tokens are no longer accepted at the cashier, but new tokens can still be issued.

**Allow partner campaigns (on/off)**
The partner merchant cannot use this merchant's customer base as a campaign audience. Turned off, this merchant's customers will not appear as a selectable segment in the partner's campaign tool.

#### Outlet-Level Overrides

Every control above can be set per outlet, overriding the brand-wide setting.

A merchant with three outlets can run the partnership at one quiet standalone location only, set a different per-bill cap for that outlet, and block weekends specifically there. The partner sees a live partnership. The cashier sees exactly the rules in force at that outlet at that moment.

### What the Ledger Records

Every redemption creates two entries in the partnership ledger:

A **debit** on the giving merchant's side — value given out.
A **credit** on the receiving merchant's side — value their customer received at a partner store.

At month end, each merchant sees:

> Total benefit I gave to partner customers: ₹X
> Total benefit my customers received at partner stores: ₹Y
> Revenue those partner customers generated for me: ₹Z

Neither merchant sees the other's internal loyalty balances, point totals, or customer records. They see only the exchange — what flowed between them under this partnership, under the terms they agreed to.

### The One Principle Underneath All of This

Every control is **unilateral**. Each merchant sets their own terms. Neither merchant can change the other's rules. Neither merchant can see the other's full customer base. Neither merchant's loyalty programme is modified by anything the partnership does.

The partnership is an agreement to exchange value under agreed terms. The platform enforces the terms, records the exchange, and gets out of the way.

---

## The Two Things That Make This Different

**1. Controlled cross-merchant campaigns**

When a merchant runs a campaign to their partner's customers, they are not accessing a list. They are using a targeting signal — people who have a verified relationship with that partner — to send an offer that they control, via a promo code that lives on their own POS, to be redeemed at their own store. The partner's customer base remains the partner's. The campaign goes out. The count comes back. Nothing else moves.

**2. Loyalty without merging**

Each merchant's loyalty programme — their points, their tiers, their history — stays entirely within their own system. The partnership ledger is the only thing that crosses. It is a record of exchange, not a shared database. Two independent businesses, two independent loyalty schemes, one clean accounting layer between them.

---

## The One-Line Version

> Two independent businesses. Two independent loyalty schemes. One controlled channel for sending offers across the partnership. One ledger for clearing what was exchanged. Nothing merges. Everything is accounted for.

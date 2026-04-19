# Analytics & Reporting Requirements

## Dashboard Overview (Summary Cards)

### For YOUR store (as a merchant in the network):

| Metric | Description | Source |
|--------|-------------|--------|
| **Campaigns Sent** | Total campaigns sent to partner audiences | partner_campaigns table |
| **Offers Redeemed** | Total redemptions at your POS from partner customers | partner_redemptions table |
| **New Customers Acquired** | Customers who visited for the FIRST time via network | partner_redemptions WHERE customer_type = 'new' |
| **Revenue from Network** | Total bill amount from partner-referred customers | SUM(partner_redemptions.bill_amount) |
| **Loyalty Liability Consumed** | Your loyalty points redeemed at partner stores (your cost) | SUM(partner_redemptions.benefit_amount) WHERE you are source |
| **Revenue from Partner Clients** | Money from partner's customers using their points at your store | SUM(partner_redemptions.bill_amount) WHERE you are acceptor |
| **Shared Customers** | Customers who had ALREADY transacted at your outlet before | partner_redemptions WHERE customer_type = 'existing' |
| **Network-Retained Customers** | Customers who came via network instead of going to competition | Derived: existing customers who redeemed = "bought from you, not competitor" |

## Per-Partner Deep Dive

For each partnership, show:
- All the above metrics filtered to that partnership
- Monthly trend chart (line/bar)
- Customer breakdown: new vs existing vs reactivated
- Reciprocity score: what you gave vs what you received
- ROI: revenue generated / cost of benefits given

## Time Filters
- Today / This Week / This Month / This Quarter / This Year / All Time
- Custom date range
- Monthly comparison (this month vs last month)

## Charts Needed
1. **Monthly Trend** — bar chart showing redemptions over time
2. **Revenue vs Cost** — dual-axis line chart (revenue earned vs benefits given)
3. **Customer Acquisition Funnel** — new → redeemed → returned within 30 days
4. **Partner Comparison** — which partner sends the most customers
5. **Reciprocity Balance** — are you giving more or receiving more per partner

## Key Insight for Video
The metrics tell a story:
- "Even customers who ALREADY shopped with you came back through the network"
- "This means they bought from YOU instead of your COMPETITION"
- "Your marketing budget is replaced by a structured exchange that's fully tracked"

## Merchant Fears Addressed by Analytics
1. "Am I giving away my customers?" → Show shared customers count — they ALREADY shop with you
2. "What does this replace in my budget?" → Show cost vs revenue comparison
3. "Is it worth the discount I'm giving?" → Show ROI per partnership
4. "Are my partners reciprocating?" → Show reciprocity score

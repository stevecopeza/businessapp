# BusinessApp Pricing Model and Monetization Strategy

## 1. Overview

BusinessApp uses a **freemium model** designed to:
- Remove barriers to entry for small businesses
- Generate recurring revenue through premium features
- Align pricing with customer value (revenue-based)

## 2. Pricing Tiers

### Free Tier
**Target:** Solo operators, startups, businesses testing the platform

**Limits:**
- Up to 20 transactions per month (quotes + jobs + invoices combined)
- Unlimited customers
- Basic quote/job/invoice features (Phases 1-3)
- Email support

**Included Features:**
- Quote creation and sending
- Job tracking
- Basic invoicing
- Customer management
- PDF exports
- Email notifications

### Pro Tier
**Price:** 1.5% of monthly transaction value (minimum R99/month, maximum R999/month)

**Example Pricing:**
- R10,000 monthly revenue → R150/month
- R50,000 monthly revenue → R750/month
- R100,000+ monthly revenue → R999/month (capped)

**Included Features:**
- Everything in Free
- **Unlimited transactions**
- Payment processing (Stripe integration)
- WhatsApp notifications
- Customer portal
- Advanced reporting
- Priority support
- API access

### Enterprise (Custom)
**Price:** Custom pricing for businesses >R200,000/month

**Included Features:**
- Everything in Pro
- Multi-location support
- Custom integrations
- Dedicated account manager
- SLA guarantees
- White-label options

## 3. Transaction Counting

### What Counts as a Transaction?
A transaction is counted when:
- Quote is **accepted** (not when sent, only when customer agrees)
- Job is **completed**
- Invoice is **paid**

**Not Counted:**
- Draft quotes
- Rejected quotes
- Cancelled jobs
- Voided invoices

**Rationale:** Only count revenue-generating events

### Monthly Reset
- Transaction counter resets on 1st of each month
- Free tier: 20 transactions per calendar month
- Pro tier: Unlimited, billing based on value

## 4. Revenue Calculation

### How Monthly Revenue is Calculated

**Formula:**
```
Monthly Revenue = Sum of all accepted quote totals for the month
```

**Example:**
```
Month: February 2024
Accepted Quotes:
- Quote #101: R5,000
- Quote #102: R8,000
- Quote #103: R12,000
Total: R25,000

Pro Tier Cost: R25,000 × 1.5% = R375
```

### Edge Cases

**Scenario 1: Free tier exceeds limit mid-month**
- Transaction #21 triggers upgrade prompt
- Business can:
  - Upgrade to Pro immediately (pay for current month)
  - Wait until next month (Quote #21 remains Draft, cannot send)

**Scenario 2: Pro tier customer has R0 revenue month**
- Minimum charge applies: R99/month
- Ensures server costs covered

**Scenario 3: Large single transaction**
- Quote for R200,000
- Charge: R200,000 × 1.5% = R3,000
- Capped at R999/month maximum
- Fair pricing for high-value businesses

## 5. Payment Collection

### Billing Cycle
- **Monthly recurring** (1st of each month)
- **Arrears billing**: Pay for previous month's usage

**Example Timeline:**
- Feb 1-28: Use BusinessApp, process R50,000 revenue
- Mar 1: Charged R750 (1.5% of R50,000)
- Mar 1-31: Use BusinessApp, process R30,000 revenue
- Apr 1: Charged R450 (1.5% of R30,000)

### Payment Methods
- Credit/debit card (Stripe)
- EFT/Bank transfer (manual invoice)
- South African payment methods (SnapScan, Ozow - future)

### Failed Payments
- **Day 1**: Charge fails, email sent
- **Day 3**: Retry charge, warning email
- **Day 7**: Account downgraded to Free tier (if applicable)
- **Day 14**: Account suspended (read-only)
- **Day 30**: Account deleted (with export option)

## 6. Feature Gating

### How Features are Locked

**Frontend:**
```javascript
if (userTier === 'free' && monthlyTransactions >= 20) {
    showUpgradePrompt();
    disableButton('#send-quote');
}
```

**Backend:**
```php
if (get_user_tier() === 'free' && get_monthly_transactions() >= 20) {
    return new WP_Error('limit_exceeded', 'Free tier limit reached. Upgrade to Pro.');
}
```

### Upgrade Prompts

**Soft Prompt (at 15 transactions):**
```
┌─────────────────────────────────────────────────┐
│ ℹ️  You've used 15 of 20 free transactions     │
│ Upgrade to Pro for unlimited quotes            │
│ [Learn More] [Dismiss]                          │
└─────────────────────────────────────────────────┘
```

**Hard Block (at 21 transactions):**
```
┌─────────────────────────────────────────────────┐
│ 🚫 Free Tier Limit Reached                     │
├─────────────────────────────────────────────────┤
│ You've reached the 20 transaction limit.       │
│                                                  │
│ Upgrade to Pro for:                             │
│ • Unlimited transactions                        │
│ • Payment processing                            │
│ • Advanced features                             │
│                                                  │
│ Just 1.5% of revenue (min R99/month)            │
│                                                  │
│ [Upgrade Now] [Learn More]                      │
└─────────────────────────────────────────────────┘
```

## 7. Competitive Analysis

### vs. Traditional Quoting Software
| Feature | BusinessApp Pro | Competitors |
|---------|----------------|-------------|
| Pricing | 1.5% revenue | R300-1000/month fixed |
| Free tier | 20 transactions | 0-7 days trial |
| Setup | 5 minutes | Hours/days |
| Contracts | Month-to-month | Annual |

**Advantage:** Pay only for what you use, no risk

### vs. Manual (Excel/Word)
| Aspect | BusinessApp | Manual |
|--------|------------|--------|
| Time per quote | 5 minutes | 20 minutes |
| Professional look | ✓ | Manual effort |
| Customer acceptance | One click | Phone/email back-and-forth |
| Data insights | Built-in | Manual tracking |

**ROI:** Save 15 minutes × 20 quotes = 5 hours/month = R2,500 value

## 8. Upgrade/Downgrade Flow

### Upgrade (Free → Pro)

1. User clicks "Upgrade"
2. Plan comparison shown
3. Credit card details entered (Stripe)
4. Instant activation
5. Email confirmation
6. All features unlocked immediately

### Downgrade (Pro → Free)

1. User clicks "Downgrade" in settings
2. Warning shown: "Limited to 20 transactions/month"
3. Confirmation required
4. End of billing period: downgrade takes effect
5. If already >20 transactions: read-only until next month

## 9. Regional Pricing (Future)

**Phase 7+:**
- Adjust pricing for purchasing power parity
- South Africa: 1.5% (base)
- Nigeria: 1.0% (lower)
- UK/US: 2.0% (higher)
- Currency: Charge in local currency

## 10. Discounts and Promotions

### Annual Discount
- Pay annually: 2 months free (16.7% discount)
- Pro Annual: 10 months' average instead of 12

### Referral Program (Phase 6+)
- Refer a business → 1 month free
- Referred business gets 1 month free
- No limit on referrals

### Non-Profit Discount
- 50% off Pro tier for registered non-profits
- Verification required

## 11. Metrics to Track

### Business Metrics
- Monthly Recurring Revenue (MRR)
- Customer Lifetime Value (LTV)
- Churn rate
- Conversion rate (Free → Pro)
- Average revenue per user (ARPU)

### Product Metrics
- Free tier utilization (transactions per user)
- Feature usage by tier
- Time to first transaction
- Transaction value distribution

## 12. Transparency

### Public Pricing Page
- Simple, clear pricing table
- Calculator: "Enter your monthly revenue → See your cost"
- No hidden fees
- Cancel anytime

**Example:**
```
How much do you make per month?
[Slider: R0 ────●──── R100,000]
R30,000

Your cost: R450/month (1.5%)
That's R15 per R1,000 you make.

[Start Free Trial]
```

## 13. Sales Strategy

### Inbound
- Content marketing (quoting tips, business guides)
- SEO (rank for "quoting software South Africa")
- Word of mouth (referrals)

### Outbound (Phase 6+)
- Cold email to tradespeople
- Partnerships with industry associations
- Demos at trade shows

### Free Trial Experience
- 30-day Pro trial (full features)
- After 30 days: revert to Free tier (20 transactions)
- Upsell at transaction #15

## 14. Future Monetization

### Add-Ons (Phase 7+)
- SMS notifications: R0.50/SMS
- WhatsApp Business API: R150/month
- Additional users: R50/user/month
- Premium templates: R99 one-time
- Custom branding: R199/month

### Marketplace (Phase 8+)
- Third-party integrations
- Revenue share with integration partners
- Example: Accounting software integration (R99/month, split 70/30)

## 15. Summary

BusinessApp's freemium model:
- **Low barrier to entry**: Free up to 20 transactions
- **Fair pricing**: Pay based on revenue generated
- **Predictable costs**: Capped at R999/month
- **Scalable**: Grows with the business
- **Transparent**: No hidden fees, cancel anytime

**Target:** 1,000 paying customers × R400 average = R400,000 MRR by Year 2

# Tax Calculation Rules

## 1. Overview

BusinessApp supports Value Added Tax (VAT) calculation for quotes, jobs, and invoices. The system is designed primarily for South African businesses (15% VAT) but can be configured for other tax jurisdictions.

The tax model uses **tax-exempt item flags** with a global default rate, allowing businesses to mark certain line items as exempt from tax.

## 2. Tax Strategy: Tax-Exempt Items

### Model
- **Global Default Tax Rate**: Set once in Business Settings (e.g., 15% for SA VAT)
- **Per-Item Tax Flag**: Each line item has `taxable: true/false` property
- **Default Behavior**: New line items default to `taxable: true`
- **Calculation**: Tax calculated only on items where `taxable: true`

### Why This Model?

Most small service businesses have:
- **Majority taxable items**: Labor, materials, standard services
- **Occasional exempt items**: Certain services, resold goods, special categories

This model avoids:
- Complex multi-rate systems (not needed for target market)
- Per-item rate selection (adds cognitive load)
- Separate tax categories (over-engineered for small businesses)

## 3. Tax Rates

### Default Rate
**South Africa: 15% VAT**

Set in: **Settings → BusinessApp → General → Tax Rate**
```json
{
  "tax_rate": 15.0,
  "tax_label": "VAT",
  "tax_number": "1234567890" // Business VAT registration number
}
```

### Other Jurisdictions
Businesses can configure alternative rates:
- **Australia**: 10% GST
- **UK**: 20% VAT
- **US**: Varies by state (0-10%)
- **Zero-rated**: 0% (for businesses in non-VAT jurisdictions)

## 4. Taxable vs. Non-Taxable Items

### Taxable Items (Default)
Most items are taxable:
- Labor charges
- Materials
- Service fees
- Equipment rental
- Delivery charges

### Non-Taxable Items (Manual Flag)
Items marked as `taxable: false`:
- **Financial services** (exempt in SA)
- **Educational services** (exempt in SA)
- **Medical services** (exempt in SA)
- **Export services** (zero-rated in SA)
- **Resale goods** (if business is not VAT-registered vendor)

**Important:** BusinessApp does not provide tax advice. Businesses must consult with accountants to determine which items should be exempt.

## 5. Calculation Logic

### Quote Total Calculation

```
Subtotal = Sum of (quantity × unit_price) for all line items
Tax Amount = Sum of (quantity × unit_price × tax_rate) for items where taxable = true
Total = Subtotal + Tax Amount
```

### Example 1: All Items Taxable
```
Line Item 1: Labor, 10 hours × R 500/hr = R 5,000 (taxable)
Line Item 2: Materials = R 2,000 (taxable)

Subtotal: R 7,000
VAT (15%): R 1,050
Total: R 8,050
```

### Example 2: Mixed Taxable/Exempt
```
Line Item 1: Labor, 10 hours × R 500/hr = R 5,000 (taxable)
Line Item 2: Educational consultation = R 2,000 (non-taxable)

Subtotal: R 7,000
VAT (15% on R 5,000): R 750
Total: R 7,750
```

### Example 3: All Items Exempt
```
Line Item 1: Medical consultation = R 1,000 (non-taxable)
Line Item 2: Treatment = R 2,000 (non-taxable)

Subtotal: R 3,000
VAT: R 0
Total: R 3,000
```

## 6. Line Item Schema

### Database Structure
```sql
CREATE TABLE wp_businessapp_quote_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote_id BIGINT UNSIGNED NOT NULL,
  description TEXT NOT NULL,
  quantity DECIMAL(10,2) NOT NULL,
  unit VARCHAR(50),
  unit_price DECIMAL(10,2) NOT NULL,
  taxable BOOLEAN DEFAULT TRUE, -- Tax flag
  type VARCHAR(50), -- 'labor', 'material', 'fee'
  line_total DECIMAL(10,2) GENERATED AS (quantity * unit_price),
  tax_amount DECIMAL(10,2), -- Calculated field
  FOREIGN KEY (quote_id) REFERENCES wp_businessapp_quotes(id)
);
```

### JSON Representation
```json
{
  "id": 1,
  "description": "Interior wall painting",
  "quantity": 50,
  "unit": "sqm",
  "unit_price": 120.00,
  "taxable": true,
  "line_total": 6000.00,
  "tax_amount": 900.00
}
```

## 7. UI Presentation

### Quote Line Item Entry
```
┌─────────────────────────────────────────────────────┐
│ Description: Interior wall painting                 │
│ Quantity: 50  Unit: sqm  Price: R 120/sqm          │
│ ☑ Taxable (15% VAT)                                │
│                                                      │
│ Line Total: R 6,000                                 │
│ Tax: R 900                                          │
└─────────────────────────────────────────────────────┘
```

### Quote Totals Display
```
┌─────────────────────────────────────────────────────┐
│ Subtotal:               R 7,000.00                  │
│ VAT (15%):              R 1,050.00                  │
│ ─────────────────────────────────────────────────── │
│ Total:                  R 8,050.00                  │
└─────────────────────────────────────────────────────┘
```

### Detailed Breakdown (Optional View)
```
Line Item 1: R 5,000 (taxable)     → VAT: R 750
Line Item 2: R 2,000 (non-taxable) → VAT: R 0
─────────────────────────────────────────────────
Subtotal: R 7,000
Total VAT: R 750
Total: R 7,750
```

## 8. Tax Rate Changes

### Scenario: Tax Rate Changes Mid-Year

**Example:** South African government increases VAT from 15% to 16%

**Behavior:**
1. Administrator updates tax rate in Settings: `15.0` → `16.0`
2. **Existing quotes (Draft)**: Recalculate automatically with new rate
3. **Existing quotes (Sent/Accepted)**: No change (snapshot model protects historical accuracy)
4. **New quotes**: Use new 16% rate

### Quote Snapshot Includes Tax Rate
```json
{
  "quote_id": 123,
  "snapshot_tax_rate": 15.0,
  "created_at": "2024-01-15T10:00:00Z"
}
```

Even if Settings change to 16%, Quote #123 always calculates at 15%.

### Migration Path
If business wants to update all Draft quotes to new rate:
- Provide "Recalculate Tax" button (Admin only)
- Warning: "This will update all Draft quotes to use the new 16% tax rate. Proceed?"
- Batch update all Draft quotes

## 9. Business Type-Specific Tax Considerations

### Panel Beater
- Parts: Taxable
- Labor: Taxable
- Insurance claims: May involve complex tax scenarios (consult accountant)

### Renovator
- Labor: Taxable
- Materials: Taxable
- Subcontractor charges: May be exempt (if subcontractor invoices separately)

### Gardener
- Maintenance services: Taxable
- Plant sales: Taxable
- Consultation: May be exempt (educational services)

### House Painter
- Labor: Taxable
- Paint/materials: Taxable

**Note:** BusinessApp does not enforce Business Type-specific tax rules. Businesses configure exemptions manually.

## 10. Zero-Rated vs. Exempt

### In South Africa:
- **Zero-rated**: 0% VAT but still VAT-registered (exports, basic foods)
- **Exempt**: No VAT charged, cannot reclaim input VAT (financial services)

### BusinessApp Handling:
Both are handled by marking `taxable: false`.

For true zero-rating (where VAT status matters for reporting), businesses can:
- Use custom field: `tax_status: 'standard' | 'zero-rated' | 'exempt'`
- Or rely on accounting system for detailed VAT reporting

BusinessApp is a quoting tool, not a full accounting system.

## 11. Tax Display Options

### Settings → Tax Display
**Show tax as:**
- [x] Separate line (default): "Subtotal + VAT = Total"
- [ ] Inclusive: "Total (including VAT)"
- [ ] Both: "Total: R 8,050 (includes R 1,050 VAT)"

**Customer-facing quotes:**
- [x] Always show tax breakdown (transparency)
- [ ] Hide tax (show total only)

**Recommendation:** Always show tax breakdown for legal compliance and customer clarity.

## 12. API Specification

### POST /businessapp/v1/quotes (Create)

Request includes line items with tax flags:
```json
{
  "customer_id": 123,
  "items": [
    {
      "description": "Labor",
      "quantity": 10,
      "unit": "hr",
      "unit_price": 500,
      "taxable": true
    },
    {
      "description": "Consultation",
      "quantity": 1,
      "unit": "session",
      "unit_price": 2000,
      "taxable": false
    }
  ]
}
```

Response includes calculated tax:
```json
{
  "quote_id": 456,
  "subtotal": 7000.00,
  "tax_amount": 750.00,
  "tax_rate": 15.0,
  "total": 7750.00,
  "items": [
    {
      "description": "Labor",
      "line_total": 5000.00,
      "tax_amount": 750.00,
      "taxable": true
    },
    {
      "description": "Consultation",
      "line_total": 2000.00,
      "tax_amount": 0.00,
      "taxable": false
    }
  ]
}
```

## 13. PDF Export

### Quote PDF Tax Section
```
─────────────────────────────────────────────────
Item                    Qty    Price    Subtotal
─────────────────────────────────────────────────
Interior painting       50 sqm R 120    R 6,000
Exterior painting       30 sqm R 150    R 4,500
Consultation (VAT exempt) 1    R 2,000  R 2,000
─────────────────────────────────────────────────
                              Subtotal: R 12,500
                    VAT (15% on R 10,500): R 1,575
                                   Total: R 14,075
─────────────────────────────────────────────────

VAT Registration Number: 1234567890
```

## 14. Reporting

### VAT Report (Admin)
**Period:** 2024-01-01 to 2024-03-31

```
Total Quotes Sent: 50
Total Revenue: R 500,000
Taxable Amount: R 450,000
Exempt Amount: R 50,000
VAT Collected: R 67,500
```

**Export to CSV** for accountant or SARS submission.

## 15. Multi-Currency Considerations

If business serves international clients (future feature):
- Tax rate applies to all currencies
- Currency conversion happens before tax calculation
- Example: Quote in USD, calculate VAT at 15%, convert total to ZAR

## 16. Edge Cases

### Case 1: Negative Line Items (Discounts)
```
Line Item 1: Labor = R 5,000 (taxable)
Line Item 2: Discount = -R 500 (taxable)

Subtotal: R 4,500
VAT (15%): R 675
Total: R 5,175
```

Discount inherits tax status of what it's discounting.

### Case 2: 100% Exempt Quote
If all items non-taxable:
- Subtotal: R 10,000
- VAT: R 0
- Total: R 10,000

PDF shows: "VAT Exempt Transaction" or "Zero-rated"

### Case 3: Rounding
Tax calculated per line item, summed, then rounded:
- Line 1 tax: R 750.00
- Line 2 tax: R 333.33
- Total tax: R 1,083.33 (not R 1,083.30)

Standard rounding: 2 decimal places, round half up.

## 17. Compliance & Legal

### South African Requirements
Businesses must:
- Display VAT registration number on quotes/invoices
- Separate VAT from total (cannot show inclusive only)
- Keep records for 5 years
- Submit VAT returns (BusinessApp exports data, doesn't submit)

### BusinessApp Compliance
- ✓ Stores VAT number
- ✓ Shows VAT breakdown
- ✓ Preserves historical quotes
- ✗ Does not submit VAT returns (use accounting software)

## 18. Future Enhancements

### Phase 6+:
- **Multiple tax rates**: Different rates per line item (for complex jurisdictions)
- **Tax categories**: Predefined categories (standard, reduced, zero, exempt)
- **Reverse charge**: For B2B cross-border transactions
- **Tax jurisdiction**: Different rates for different provinces/states
- **Input VAT tracking**: Track VAT paid on purchases (full accounting feature)

For now: Keep it simple. One rate, per-item exemptions.

## 19. Testing Checklist

- [ ] Create quote with all taxable items, verify tax calculation
- [ ] Create quote with all exempt items, verify zero tax
- [ ] Create quote with mixed items, verify partial tax
- [ ] Change global tax rate, verify new quotes use new rate
- [ ] Change global tax rate, verify old quotes unchanged
- [ ] Export quote PDF, verify tax breakdown displayed
- [ ] Create invoice from quote, verify tax carried over
- [ ] Generate VAT report, verify totals match

## 20. Summary

BusinessApp's tax model prioritizes **simplicity and flexibility**:
- One global tax rate (easy to configure)
- Per-item exemptions (handles edge cases)
- Snapshot protection (historical accuracy)
- Clear customer-facing display (transparency)

For businesses needing complex tax handling (multiple rates, jurisdictions, categories), recommend integration with dedicated accounting software.

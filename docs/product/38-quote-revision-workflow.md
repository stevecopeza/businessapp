# Quote Revision Workflow

## 1. Purpose

When a customer requests changes to a quote that has already been sent, the business faces a dilemma:
- Edit the existing quote → Destroys the historical record of what was originally offered
- Create a completely new quote → Loses context and relationship to the original

The Quote Revision Workflow solves this by providing a structured way to create modified versions while preserving the original.

## 2. Core Principle

**Original quotes are immutable once sent. Revisions are new quotes with explicit lineage.**

This ensures:
- Historical accuracy (what was originally proposed)
- Clear audit trail (how the agreement evolved)
- Customer trust (no "silent edits" to pricing or scope)

## 3. When to Use Revisions

### Use Revision Workflow When:
- Customer requests scope changes after quote is sent
- Pricing needs adjustment after customer negotiation
- Errors discovered in sent quote (typo, wrong item, miscalculation)
- Customer wants to add/remove services

### Do NOT Use Revisions When:
- Quote is still in Draft (just edit it directly)
- Creating quote for different project (create new independent quote)
- Customer is different (create separate quote)

## 4. Revision Creation Flow

### Step 1: User Initiates Revision
From a Sent/Discussing quote, user clicks:
**[Create Revision]** button

### Step 2: System Creates New Quote
System automatically:
1. Creates new Quote in Draft status
2. Copies all data from original quote:
   - Customer ID and snapshot data
   - All line items (descriptions, quantities, prices)
   - Attachments (reference or copy, configurable)
   - Notes (optional, configurable)
3. Sets special fields:
   - `revised_from_quote_id` = original quote ID
   - `revision_number` = auto-incremented (original = 0, first revision = 1, etc.)
   - Adds system note: "Revision of Quote #123 (created by User X on Date Y)"

### Step 3: User Edits Revision
User modifies the new Draft quote:
- Change line items
- Adjust pricing
- Update notes
- Add/remove attachments

Original quote remains unchanged in Sent state.

### Step 4: Send Revised Quote
When user sends the revision:
- New quote moves to Sent status
- Original quote receives system note: "Superseded by Quote #456"
- Original quote status can optionally change to "Superseded" (configurable)

### Step 5: Customer Responds
Customer interacts with revised quote:
- If accepted → Revised quote becomes Accepted, original remains Sent/Superseded
- If rejected → Revised quote becomes Rejected, can create another revision
- If expired → Same expiry logic applies

## 5. Revision Chain

Revisions can chain:
```
Quote #100 (Original, Sent)
  ↳ Quote #101 (Revision 1, Sent)
      ↳ Quote #102 (Revision 2, Accepted)
```

### Chain Rules:
- Any quote in chain can spawn new revision (typically from latest)
- All quotes in chain remain accessible
- Final accepted quote is authoritative for job creation
- Chain is displayed in UI timeline

## 6. UI Presentation

### Viewing Original Quote
When viewing original quote that has been revised:
```
┌─────────────────────────────────────────┐
│ Quote #100 — SENT                       │
│ ⚠️ This quote has been revised          │
│ Latest version: Quote #102 [View]       │
└─────────────────────────────────────────┘
```

### Viewing Revision
When viewing a revision:
```
┌─────────────────────────────────────────┐
│ Quote #102 — ACCEPTED                   │
│ 📝 Revision of Quote #100 [View Original]│
│ Revision History:                        │
│   • Quote #100 (Original, 2024-01-15)   │
│   • Quote #101 (Revision 1, 2024-01-18) │
│   • Quote #102 (Revision 2, 2024-01-20) │
└─────────────────────────────────────────┘
```

### Timeline View
```
2024-01-15: Quote #100 created and sent
2024-01-17: Customer requested changes
2024-01-18: Quote #101 (Revision 1) sent
2024-01-19: Customer requested more changes
2024-01-20: Quote #102 (Revision 2) sent
2024-01-22: Quote #102 accepted by customer
2024-01-23: Job #50 created from Quote #102
```

## 7. Database Schema

### Quote Table Additions
```sql
ALTER TABLE wp_businessapp_quotes ADD COLUMN revised_from_quote_id BIGINT UNSIGNED NULL;
ALTER TABLE wp_businessapp_quotes ADD COLUMN revision_number INT DEFAULT 0;
ALTER TABLE wp_businessapp_quotes ADD COLUMN is_superseded BOOLEAN DEFAULT FALSE;

ALTER TABLE wp_businessapp_quotes ADD FOREIGN KEY (revised_from_quote_id) 
  REFERENCES wp_businessapp_quotes(id);
```

### Revision Metadata
```json
{
  "quote_id": 102,
  "revised_from_quote_id": 100,
  "revision_number": 2,
  "revision_chain": [100, 101, 102],
  "is_latest_revision": true,
  "changes_summary": "Added 2 items, reduced price by 15%"
}
```

## 8. API Endpoints

### POST /businessapp/v1/quotes/:id/revise

Creates a revision of an existing quote.

**Request:**
```json
{
  "copy_attachments": true,
  "copy_notes": false,
  "reason": "Customer requested additional services"
}
```

**Response:**
```json
{
  "success": true,
  "original_quote_id": 100,
  "new_quote_id": 101,
  "revision_number": 1,
  "message": "Revision created successfully"
}
```

### GET /businessapp/v1/quotes/:id/revisions

Gets all revisions in the chain.

**Response:**
```json
{
  "original_quote_id": 100,
  "revisions": [
    {
      "quote_id": 100,
      "revision_number": 0,
      "status": "sent",
      "created_at": "2024-01-15T10:00:00Z",
      "is_superseded": true
    },
    {
      "quote_id": 101,
      "revision_number": 1,
      "status": "sent",
      "created_at": "2024-01-18T14:30:00Z",
      "is_superseded": true
    },
    {
      "quote_id": 102,
      "revision_number": 2,
      "status": "accepted",
      "created_at": "2024-01-20T09:15:00Z",
      "is_superseded": false
    }
  ],
  "latest_quote_id": 102
}
```

## 9. Superseded Status

### Definition
A quote is "superseded" when a newer revision has been sent to the customer.

### Status Options:
- **Keep Original Status + Flag**: Quote remains "Sent" but gains `is_superseded: true` flag
- **Change to "Superseded"**: Quote status changes to dedicated "Superseded" state

**Recommendation:** Use flag approach (simpler, preserves original status for reporting)

### Behavior:
- Superseded quotes are read-only (except Admin can add notes)
- Shown in UI with visual indicator: "SUPERSEDED by Quote #102"
- Not counted in "Active Quotes" metrics
- Still included in "All Quotes" list with filter option

## 10. Settings & Configuration

### Settings → BusinessApp → Revisions

**Mark original quote as superseded when revision is sent:**
- [ ] Yes, automatically mark as superseded
- [x] No, keep original status (default)

**When creating revision, copy:**
- [x] Line items (always)
- [x] Customer snapshot (always)
- [x] Attachments
- [ ] Internal notes
- [x] Custom fields

**Revision numbering:**
- [x] Automatic sequential (Revision 1, 2, 3...)
- [ ] Manual (user enters version label)

## 11. Customer-Facing Behavior

### Email Notifications
When sending a revision:
```
Subject: Updated Quote #102 from [Business Name]

Hi John,

We've updated your quote based on your request.

Previous quote: Quote #100
Updated quote: Quote #102

[View Updated Quote]

Changes made:
• Added exterior wall painting
• Removed ceiling repair
• Updated total: R 15,000 → R 18,500

Please review and let us know if you'd like to proceed.
```

### Public Quote View
Customer viewing Quote #102 sees:
```
Quote #102 (Updated Version)

This is an updated version of your original quote.
View original quote: Quote #100

Changes from original:
• Added: Exterior painting (R 3,500)
• Total: R 15,000 → R 18,500
```

Optional: Show side-by-side comparison (Phase 5+)

## 12. Reporting & Analytics

### Metrics to Track:
- Average number of revisions per quote
- Time between original and accepted revision
- Percentage of quotes requiring revisions (by Business Type)
- Most common reason for revisions (from notes)

### Reports:
**Quote Revision Rate:**
- Total quotes sent: 100
- Quotes revised at least once: 25
- Revision rate: 25%

**Insights:**
- High revision rate may indicate unclear initial quoting
- Low revision rate indicates good scope definition

## 13. Edge Cases

### Case 1: Create Revision of Rejected Quote
**Allowed:** Yes
**Use case:** Customer rejected original, but might accept if price reduced

**Flow:**
1. Quote #100 rejected
2. User creates Quote #101 (revision with lower price)
3. Quote #101 sent to customer
4. Customer accepts Quote #101

### Case 2: Create Revision of Expired Quote
**Allowed:** Yes
**Use case:** Customer comes back after expiry, needs updated pricing

**Flow:**
1. Quote #100 expired
2. User creates Quote #101 (revision with current pricing)
3. Quote #101 sent with new expiry date

### Case 3: Create Revision of Accepted Quote
**Allowed:** No
**Rationale:** Accepted quote is legally binding. Changes require new agreement.

**Alternative:**
- Create separate "Change Order" (future feature)
- Create entirely new quote for additional work

### Case 4: Multiple Revisions on Same Day
**Allowed:** Yes
**UI:** Shows timestamps to distinguish

**Example:**
- Quote #100: Original (10:00 AM)
- Quote #101: Revision 1 (2:00 PM)
- Quote #102: Revision 2 (4:00 PM)

### Case 5: Revision from Middle of Chain
**Scenario:** Quote #100 → #101 → #102. User wants to revise #101 (not latest).

**Behavior:**
- Allowed, but shows warning: "You're creating a revision from Quote #101, which is not the latest version. Latest: Quote #102."
- Creates Quote #103, sets `revised_from_quote_id = 101`
- Creates branching chain (unusual but permitted)

## 14. Best Practices

### For Businesses:
1. **Document Reason**: Always add note explaining why revision was needed
2. **Clear Communication**: Email customer explaining what changed
3. **Version Control**: Use revision system rather than manual "Quote v2" naming
4. **Archive Superseded**: Don't delete old quotes, they're your paper trail

### For Developers:
1. **Immutability**: Never modify sent quotes directly
2. **Deep Copy**: Copy line items, don't reference (prevents cascade updates)
3. **Audit Trail**: Log all revision creation events
4. **Testing**: Test revision chains 5+ deep

## 15. Future Enhancements

### Phase 6+ Features:
- **Side-by-side comparison**: Visual diff showing what changed
- **Change tracking**: Highlight modified line items in revision
- **Template-based revisions**: "Create discount version" (auto-applies 10% reduction)
- **Customer counter-offer**: Customer suggests changes, business creates revision
- **Approval workflow**: Revisions above X% change require manager approval

## 16. Migration from Legacy Systems

If importing quotes from other systems:
- Imported quotes have `revision_number = 0`
- No `revised_from_quote_id` (legacy quotes are treated as originals)
- If importing multiple versions, create revision chain manually

## 17. Testing Checklist

- [ ] Create revision from Sent quote
- [ ] Verify original quote unchanged after revision
- [ ] Send revision, verify original marked as superseded (if enabled)
- [ ] Accept revision, verify job created from revision (not original)
- [ ] Create revision chain (3+ levels), verify all links intact
- [ ] Delete middle quote in chain, verify chain breaks gracefully
- [ ] Export PDF of revision, verify shows "Revision of Quote #X"
- [ ] Test customer view of revised quote with link to original

## 18. Summary

The revision workflow balances flexibility with integrity:
- **Flexibility**: Easy to modify quotes based on customer feedback
- **Integrity**: Original agreements preserved for audit/legal purposes
- **Clarity**: Explicit lineage shows how agreement evolved
- **Simplicity**: One-click revision creation, automatic linking

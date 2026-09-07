# Customer Communication Flow

## 1. Overview

BusinessApp uses a dual-channel approach to customer communication:
- **Email + Secure Web Links** (Primary channel)
- **WhatsApp** (Alternative/supplementary channel for regions with high WhatsApp adoption)

This hybrid approach ensures broad reach while accommodating customer preferences and regional communication norms.

## 2. Communication Channels

### Channel A: Email + Secure Web Link (Primary)

**Use Cases:**
- Sending quotes to customers
- Sending invoices
- Notifying customers of quote acceptance/changes
- Professional communication requiring audit trail

**How It Works:**
1. Business sends email via `wp_mail()` (WordPress email function)
2. Email contains secure token-based link
3. Customer clicks link → Views quote in browser
4. No login required (token-based authentication)

### Channel B: WhatsApp (Alternative)

**Use Cases:**
- Quick notifications in regions with low email usage
- Customer prefers WhatsApp communication
- Follow-up reminders
- Status updates

**How It Works:**
1. Business sends WhatsApp message via configured WhatsApp Business API or web integration
2. Message contains secure link
3. Customer clicks → Views quote in browser

**Regional Context:** In South Africa and many developing markets, WhatsApp has higher engagement than email, especially for SMEs.

## 3. Email Communication Workflow

### Quote Sending Flow

**Step 1: User Clicks "Send Quote"**
- Quote status changes: Draft → Sent
- Quote locked for editing (snapshot preserved)
- System generates secure token

**Step 2: System Generates Secure Link**
```
https://yourbusiness.com/quote/view?token=abc123xyz456def789
```

**Token Properties:**
- Cryptographically random (256-bit)
- Unique per quote
- Expires after 90 days (configurable)
- Cannot be guessed or brute-forced
- Stored in database: `wp_businessapp_quote_tokens`

**Step 3: System Sends Email**

**Email Template:**
```
Subject: Quote #123 from [Business Name]

Hi John,

Your quote from [Business Name] is ready for review.

Quote Summary:
• Quote Number: #123
• Amount: R 8,050.00
• Valid Until: 2024-03-15

[View Quote] ← Secure link button

If you have any questions, reply to this email or call us at [Phone].

Thank you,
[Business Name]
```

**Step 4: Customer Receives Email**
- Opens email on any device
- Clicks "View Quote" button
- Redirected to secure quote view page

**Step 5: Customer Views Quote**
- See full quote details
- View line items, notes, attachments
- Options: Accept, Reject, Discuss (comment)

### Email Configuration

**Settings → BusinessApp → Email**

**From Address:**
- Use: `[Business Email]` (e.g., info@businessname.com)
- Configured in WordPress Settings or BusinessApp settings

**Email Provider:**
- Default: WordPress `wp_mail()` (uses server's mail function)
- Recommended: SMTP plugin (WP Mail SMTP, Mailgun, SendGrid)
- Ensures deliverability, avoids spam filters

**Email Templates:**
Customizable templates for:
- Quote sent
- Quote accepted (confirmation to business)
- Quote rejected (notification to business)
- Invoice sent
- Payment received

## 4. WhatsApp Communication Workflow

### Integration Options

**Option 1: WhatsApp Business API (Official)**
- Requires Meta Business verification
- Costs: Per-message fees
- Pros: Official, reliable, supports templates
- Cons: Setup complexity, ongoing costs

**Option 2: Web-Based Integration (Recommended for MVP)**
- Use WhatsApp Web link format: `https://wa.me/[phone]?text=[message]`
- Pros: No API setup, no costs, works immediately
- Cons: Requires manual action (click to send)

**Option 3: Third-Party Services**
- Twilio, MessageBird, etc.
- Pros: Simple integration, good deliverability
- Cons: Recurring costs

**Recommendation for MVP:** Option 2 (wa.me links) with upgrade path to API

### WhatsApp Sending Flow (wa.me Method)

**Step 1: User Clicks "Send via WhatsApp"**
- System generates secure link (same as email)
- System generates pre-filled message

**Step 2: System Opens WhatsApp**
```
https://wa.me/27829257759?text=Hi%20John,%20your%20quote%20from%20BusinessName%20is%20ready:%20https://yourbusiness.com/quote/view?token=abc123
```

**Step 3: WhatsApp Opens**
- Customer's WhatsApp contact appears
- Message pre-filled in chat
- User clicks Send

**Step 4: Customer Receives WhatsApp**
- Clicks link → Views quote in browser

### WhatsApp Templates

**Quote Notification:**
```
Hi [Customer Name],

Your quote from [Business Name] is ready 📋

Amount: R [Total]
Valid until: [Date]

View quote: [Link]

Reply here if you have questions!
```

**Quote Reminder (3 days before expiry):**
```
Hi [Customer Name],

Just a reminder – your quote expires in 3 days.

Quote #[Number]
Amount: R [Total]

View and accept: [Link]
```

## 5. Secure Token System

### Token Generation
```php
function generate_quote_token($quote_id) {
    $token = bin2hex(random_bytes(32)); // 64-character hex string
    
    // Store in database
    global $wpdb;
    $wpdb->insert('wp_businessapp_quote_tokens', [
        'quote_id' => $quote_id,
        'token' => $token,
        'created_at' => current_time('mysql'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
        'used_count' => 0
    ]);
    
    return $token;
}
```

### Token Validation
```php
function validate_quote_token($token) {
    global $wpdb;
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM wp_businessapp_quote_tokens 
         WHERE token = %s AND expires_at > NOW()",
        $token
    ));
    
    if (!$result) {
        return false; // Invalid or expired
    }
    
    // Increment usage counter
    $wpdb->update('wp_businessapp_quote_tokens', 
        ['used_count' => $result->used_count + 1],
        ['id' => $result->id]
    );
    
    return $result->quote_id;
}
```

### Security Properties
- **Unpredictable**: Cryptographically random
- **Unique**: One token per quote
- **Expiring**: 90-day default (configurable)
- **Revocable**: Can be invalidated by admin
- **Usage-tracked**: Log how many times token accessed

## 6. Public Quote View Page

### URL Structure
```
https://yourbusiness.com/quote/view?token=abc123xyz456
```

### Page Content (HTML + Print CSS)

**Header:**
```
─────────────────────────────────────────────────
[Business Logo]        Quote #123
                       Date: 2024-02-06
─────────────────────────────────────────────────
```

**Customer Details:**
```
Quote For:
John Smith
john@example.com
082-123-4567
```

**Line Items Table:**
```
Item                    Qty    Unit Price    Total
────────────────────────────────────────────────
Interior painting       50 sqm    R 120    R 6,000
Exterior painting       30 sqm    R 150    R 4,500
────────────────────────────────────────────────
                              Subtotal: R 10,500
                            VAT (15%): R 1,575
                                Total: R 12,075
────────────────────────────────────────────────
Valid Until: 2024-03-15
```

**Actions:**
```
[Accept Quote]  [Reject Quote]  [Ask Question]  [Print]
```

**Footer:**
```
[Business Name]
[Address]
[Phone] | [Email] | [Website]
VAT: [Number]
```

### Mobile Responsive
- Single-column layout on mobile
- Large touch-friendly buttons
- Print option opens native print dialog

### Print Stylesheet
```css
@media print {
    .action-buttons { display: none; }
    .header { border-bottom: 2px solid #000; }
    .totals { font-weight: bold; }
}
```

## 7. Customer Actions

### Action 1: Accept Quote

**Flow:**
1. Customer clicks "Accept Quote"
2. Confirmation modal: "Do you accept this quote for R 12,075?"
3. Customer clicks "Yes, Accept"
4. Quote status → Accepted
5. Email sent to business: "Quote #123 accepted by John Smith"
6. WhatsApp notification sent to business (optional)
7. Customer sees: "Thank you! We'll contact you to schedule the work."

**Database Update:**
```sql
UPDATE wp_businessapp_quotes 
SET status = 'accepted', 
    accepted_at = NOW(), 
    accepted_by_ip = '[IP]'
WHERE id = 123;
```

### Action 2: Reject Quote

**Flow:**
1. Customer clicks "Reject Quote"
2. Modal: "We're sorry to hear that. Can you tell us why?" (optional feedback)
3. Customer enters reason (optional) or clicks "No thanks, just decline"
4. Quote status → Rejected
5. Email sent to business: "Quote #123 rejected by John Smith. Reason: [Reason]"
6. Customer sees: "Quote declined. Thank you for considering us."

### Action 3: Ask Question / Discuss

**Flow:**
1. Customer clicks "Ask Question"
2. Text box appears: "What would you like to know?"
3. Customer enters question
4. Quote status → Discussing
5. Email sent to business with question
6. Business can reply via email or create revision

**Discussion Thread (Optional):**
- Show comment thread on quote view page
- Customer and business can comment back and forth
- Each comment triggers email notification

## 8. Business Notifications

### Notification Triggers

**When Quote Accepted:**
- Email to business owner
- WhatsApp notification (if configured)
- Dashboard notification badge

**When Quote Rejected:**
- Email to business owner
- Include reason (if provided)

**When Customer Comments:**
- Email to quote creator
- Include comment text and link to respond

### Admin Dashboard Notifications

**Quote Activity Widget:**
```
─────────────────────────────────────────────────
Recent Quote Activity:
─────────────────────────────────────────────────
• Quote #123 ACCEPTED by John Smith (2 min ago)
• Quote #122 DISCUSSED by Sarah Jones (1 hour ago)
• Quote #121 REJECTED by Mike Brown (2 hours ago)
─────────────────────────────────────────────────
```

## 9. Email Deliverability

### Best Practices

**SPF/DKIM/DMARC:**
- Configure sender domain authentication
- Prevents emails from going to spam

**Transactional Email Service:**
- Use Mailgun, SendGrid, or AWS SES
- Higher deliverability than shared hosting mail()

**Email Content:**
- Plain text + HTML multipart
- Include unsubscribe link (for marketing emails, not transactional)
- Avoid spam trigger words

### Bounce Handling
- Track bounced emails
- Mark customer email as invalid
- Notify admin: "Quote #123 email bounced. Check customer email."

## 10. Multi-Language Support (Future)

### Phase 6+:
- Customer selects language preference
- Email templates in customer's language
- Quote view page in customer's language

**South African Context:**
- English (default)
- Afrikaans (high priority)
- isiZulu, isiXhosa (future)

## 11. Settings Configuration

### Settings → BusinessApp → Communication

**Email Settings:**
- From Name: [Business Name]
- From Email: [info@business.com]
- Reply-To Email: [Optional, defaults to From Email]
- Email Provider: WordPress Mail / SMTP Plugin

**WhatsApp Settings:**
- Enable WhatsApp: ☑ Yes
- Default Country Code: +27
- WhatsApp Method: wa.me links / API

**Token Settings:**
- Token Expiry: 90 days
- Allow Multiple Views: ☑ Yes
- Track View Count: ☑ Yes

**Notification Preferences:**
- Email me when quote accepted: ☑
- Email me when quote rejected: ☑
- Email me when customer comments: ☑
- WhatsApp me for urgent notifications: ☐

## 12. Privacy & Security

### Data Protection
- Token links are private (not indexed by search engines)
- Add `<meta name="robots" content="noindex">` to quote view pages
- Tokens expire after 90 days (configurable)

### Customer Privacy
- No customer login required (frictionless)
- IP address logged for acceptance (audit trail)
- Customer can request token invalidation

### Admin Controls
- Admin can revoke token (invalidate link)
- Admin can resend quote (generates new token)
- Admin can view token usage stats

## 13. Analytics & Tracking

### Metrics to Track
- Email open rate (if using service with tracking)
- Link click rate
- Time to accept (from send to acceptance)
- Acceptance rate by communication channel
- Peak viewing times

### Reports
**Quote Engagement Report:**
- Quotes sent: 100
- Emails opened: 85 (85%)
- Links clicked: 70 (70%)
- Quotes accepted: 45 (45%)
- Quotes rejected: 15 (15%)
- No response: 40 (40%)

## 14. Customer Portal (Phase 6+)

### Future Enhancement:
- Customer creates account
- Views all their quotes in one place
- Views job progress
- Views invoices and payment history
- Manages billing information

**URL Structure:**
```
/customer-portal/login
/customer-portal/quotes
/customer-portal/jobs
/customer-portal/invoices
```

## 15. Testing Checklist

- [ ] Send test quote email, verify link works
- [ ] Click link, verify quote displays correctly
- [ ] Accept quote, verify status changes and notification sent
- [ ] Reject quote, verify notification sent
- [ ] Test token expiry (manually expire token, verify access denied)
- [ ] Test WhatsApp link generation
- [ ] Test on mobile device (responsive layout)
- [ ] Test print function (PDF generation)
- [ ] Test with invalid token (404 page)
- [ ] Test email deliverability (spam check)

## 16. Summary

BusinessApp's communication approach prioritizes:
- **Simplicity**: Token-based, no login required
- **Security**: Expiring tokens, usage tracking
- **Flexibility**: Email + WhatsApp dual-channel
- **Transparency**: Clear customer-facing quote views
- **Efficiency**: One-click accept/reject

This ensures customers can engage with quotes easily while businesses maintain full control and visibility.

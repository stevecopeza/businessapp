# Customer Data Snapshot Specification

## 1. Purpose

This document defines exactly what customer data is captured when a Quote is created, how it is stored, and how it behaves over time.

The snapshot model ensures historical accuracy: a Quote always reflects the customer information that was valid at the time of quoting, even if the customer's details change later.

## 2. Snapshot Strategy: Hybrid Model

BusinessApp uses a **hybrid approach**:
- **Customer Record**: Live, mutable source of truth (stored in `wp_businessapp_customers` table)
- **Quote Snapshot**: Point-in-time copy of essential customer data (stored in `wp_businessapp_quotes` table)

When a Quote is created, it:
1. References the Customer via `customer_id` (foreign key relationship)
2. Snapshots critical customer data into the Quote record itself

## 3. What Gets Snapshotted

When a Quote is created, the following customer fields are copied into the Quote record:

### Always Snapshotted (Core Fields)
- `customer_name` — Full name or business name
- `customer_email` — Primary email address
- `customer_phone` — Primary phone number
- `billing_address` — Complete billing address (street, city, postal code, country)
- `shipping_address` — Complete shipping/service address (if different from billing)

### Conditionally Snapshotted (Business Type Dependent)
Depending on Business Type, additional fields may be snapshotted:

**Panel Beater:**
- `vehicle_make`
- `vehicle_model`
- `vehicle_vin`
- `vehicle_registration`
- `insurance_claim_number`

**Renovator:**
- `property_address` (service location)
- `property_type` (residential/commercial)

**Gardener:**
- `service_address`
- `property_size`

**House Painter:**
- `property_address`
- `total_paintable_area`

### Never Snapshotted
The following customer data is **NOT** snapshotted (always retrieved from live Customer record):
- Customer creation date
- Customer tags/categories
- Customer notes/history
- Lifetime value calculations
- Number of quotes/jobs
- Payment history

## 4. Storage Implementation

### Customer Table
```sql
CREATE TABLE wp_businessapp_customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  phone VARCHAR(50),
  billing_address_line1 VARCHAR(255),
  billing_address_line2 VARCHAR(255),
  billing_city VARCHAR(100),
  billing_postal_code VARCHAR(20),
  billing_country VARCHAR(100),
  shipping_address_line1 VARCHAR(255),
  shipping_address_line2 VARCHAR(255),
  shipping_city VARCHAR(100),
  shipping_postal_code VARCHAR(20),
  shipping_country VARCHAR(100),
  notes TEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_email (email),
  INDEX idx_name (name)
);
```

### Quote Table (Snapshot Fields)
```sql
CREATE TABLE wp_businessapp_quotes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  -- Snapshotted customer data (at creation time)
  snapshot_customer_name VARCHAR(255) NOT NULL,
  snapshot_customer_email VARCHAR(255),
  snapshot_customer_phone VARCHAR(50),
  snapshot_billing_address JSON,
  snapshot_shipping_address JSON,
  snapshot_business_type_fields JSON, -- Vehicle info, property info, etc.
  -- Quote-specific fields
  status VARCHAR(50) NOT NULL,
  total DECIMAL(10,2),
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FOREIGN KEY (customer_id) REFERENCES wp_businessapp_customers(id)
);
```

## 5. Snapshot Behavior Over Customer Lifecycle

### Scenario 1: Customer Changes Phone Number

**Timeline:**
1. Quote #101 created for John Smith (phone: 082-111-2222)
2. Quote snapshots: `snapshot_customer_phone: "082-111-2222"`
3. John updates his phone to 082-333-4444 in Customer record
4. New Quote #102 created for John

**Result:**
- Quote #101 still displays: 082-111-2222 (snapshot preserved)
- Customer record shows: 082-333-4444 (current)
- Quote #102 snapshots: 082-333-4444 (new snapshot)

**UI Behavior:**
When viewing Quote #101, show:
- "Customer Phone (at time of quote): 082-111-2222"
- Optional indicator: "⚠️ Customer phone has changed. Current: 082-333-4444" (click to update customer record)

### Scenario 2: Customer Moves Address Between Quote and Job

**Timeline:**
1. Quote #201 created with service address: 123 Old Street
2. Quote accepted
3. Customer moves to 456 New Street, updates Customer record
4. Job created from Quote #201

**Result:**
- Quote #201 snapshot: "123 Old Street"
- Customer record: "456 New Street"
- Job inherits snapshot from Quote: "123 Old Street"

**Critical Decision Required:**
System prompts: 
> "Customer address has changed since this quote was created.  
> Quoted address: 123 Old Street  
> Current address: 456 New Street  
> Which address should this job use?"

User selects, Job captures that decision.

### Scenario 3: Customer Name Correction

**Timeline:**
1. Quote created with typo: "Jhon Smith"
2. User realizes typo, updates Customer record to "John Smith"
3. Quote #101 still shows "Jhon Smith" (snapshot)

**Solution:**
Provide "Refresh Snapshot" button on Quote (only in Draft state):
- Updates snapshot fields from current Customer record
- Logs the change: "Snapshot refreshed by User X at Time Y"
- Only available before Quote is Sent (after Sent, snapshot is immutable)

## 6. API Behavior

### GET /businessapp/v1/quotes/:id

Response includes both live and snapshot data:
```json
{
  "id": 101,
  "customer_id": 45,
  "customer": {
    "id": 45,
    "name": "John Smith",
    "email": "john@example.com",
    "phone": "082-333-4444",
    "address": "456 New Street"
  },
  "snapshot": {
    "customer_name": "John Smith",
    "customer_email": "john.old@example.com",
    "customer_phone": "082-111-2222",
    "billing_address": {
      "line1": "123 Old Street",
      "city": "Cape Town",
      "postal_code": "8001"
    }
  },
  "snapshot_differs": true,
  "total": 1500.00,
  "status": "sent"
}
```

### POST /businessapp/v1/quotes (Create)

When creating a Quote, snapshot is automatically created:
```json
{
  "customer_id": 45,
  "items": [...]
}
```

System automatically:
1. Fetches current Customer record (ID 45)
2. Copies snapshot fields into Quote record
3. Stores `snapshot_customer_name`, `snapshot_billing_address`, etc.

## 7. Display Rules

### Draft Quotes
- Show live Customer data by default
- Snapshot exists but is hidden (used only if Quote is sent)

### Sent/Accepted/Rejected Quotes
- Always display snapshot data (what was agreed)
- Show "View Current Customer Info" link (opens live Customer record in sidebar)
- If snapshot differs from current: Show warning icon with tooltip

### PDF Export
- Quote PDFs always use snapshot data
- Ensures printed quotes match what was sent

## 8. Edge Cases

### Case 1: Customer Deleted
If a Customer is deleted (soft delete recommended):
- Quote retains snapshot data (Quote remains viewable)
- `customer_id` references deleted customer (marked as deleted)
- UI shows: "Customer record no longer exists, showing snapshot data"

### Case 2: Customer Merged
If two customer records are merged:
- Old customer_id redirects to new customer_id
- Snapshots remain unchanged (historical accuracy)
- New Quotes use merged Customer record

### Case 3: Bulk Address Update
If business moves all customers to new billing address format:
- Existing Quote snapshots remain unchanged
- New Quotes use new address format
- No retroactive updates

## 9. Business Type-Specific Fields

### Vehicle Information (Panel Beater)
Snapshotted fields:
```json
{
  "vehicle_make": "Toyota",
  "vehicle_model": "Corolla",
  "vehicle_year": 2018,
  "vehicle_vin": "ABC123XYZ456",
  "vehicle_registration": "CA123456",
  "vehicle_color": "Silver"
}
```

**Why snapshot?** Customer may sell vehicle between quote and job completion.

### Property Information (Renovator/Painter)
Snapshotted fields:
```json
{
  "property_address": "123 Main Street",
  "property_type": "Residential",
  "property_size_sqm": 150,
  "property_stories": 2
}
```

**Why snapshot?** Property may be sold, renovated, or details may change.

## 10. Migration & Upgrades

When BusinessApp is upgraded and new customer fields are added:
- Existing Quote snapshots remain unchanged (no retroactive updates)
- New Quotes include new fields in snapshot
- Old Quotes show: "Field not available in this quote (created before field was added)"

## 11. Privacy & GDPR Compliance

### Right to Erasure
When a Customer requests data deletion:
- Customer record can be anonymized: name → "Deleted Customer #45"
- Quote snapshots remain (required for business records)
- Personal data in snapshots can be redacted if legally required

### Data Portability
When exporting Customer data:
- Include both live Customer record AND all Quote snapshots
- Format: JSON with clear distinction between "current" and "historical" data

## 12. Performance Considerations

### Query Optimization
- Snapshot data stored as JSON in single column (reduces JOINs)
- Indexed fields: `customer_id`, `snapshot_customer_email`
- No N+1 queries: Snapshot data retrieved with Quote in single query

### Storage Overhead
- Typical snapshot: ~500 bytes per Quote
- 10,000 Quotes = ~5MB of snapshot data (negligible)

## 13. Testing Scenarios

Required test cases:
1. Create Quote, verify snapshot matches current Customer data
2. Update Customer, verify old Quote snapshot unchanged
3. Create new Quote after Customer update, verify new snapshot
4. Delete Customer, verify Quote still displays snapshot
5. Refresh snapshot on Draft quote, verify update
6. Attempt refresh on Sent quote, verify denial
7. Export Quote PDF, verify uses snapshot data

## 14. Summary

**Golden Rule:** Snapshots preserve what was true at the time of agreement, ensuring historical integrity and preventing retroactive changes from distorting business records.

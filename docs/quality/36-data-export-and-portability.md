# Data Export and Portability

## 1. Purpose

BusinessApp provides comprehensive data export functionality to ensure:
- **Business Continuity**: Businesses can backup their data
- **Data Portability**: Businesses can migrate to other systems
- **Legal Compliance**: GDPR and POPIA (SA) "right to data portability"
- **Audit Requirements**: Export for accountants, legal review, or regulatory compliance

## 2. Export Strategy: Complete JSON Export

BusinessApp uses a **full JSON export** model that captures:
- All business configuration
- All customers
- All quotes, jobs, and invoices
- All relationships and metadata
- Attachments (as file paths or base64)

The export is **complete and self-contained**, allowing full reconstruction of business data.

## 3. What Gets Exported

### Core Entities
- **Business Settings**: All configuration from `wp_options`
- **Customers**: Complete customer records with all fields
- **Quotes**: All quotes with line items, snapshots, and metadata
- **Jobs**: All jobs with tasks and materials
- **Invoices**: All invoices with payment records
- **Attachments**: File references or embedded base64 data

### Relationships
- Quote → Customer linkage
- Job → Quote linkage
- Invoice → Job linkage
- Quote revision chains

### Metadata
- Creation timestamps
- User who created records
- Status history
- Notes and comments
- Token usage logs (quote views)

### What is NOT Exported
- WordPress user accounts (WordPress core data)
- WordPress posts/pages unrelated to BusinessApp
- Plugin configuration (WordPress plugin settings)
- Server configuration

## 4. Export File Structure

### Filename Convention
```
businessapp-export-YYYY-MM-DD-HHmmss.json
```

Example: `businessapp-export-2024-02-06-143022.json`

### JSON Schema
```json
{
  "export_metadata": {
    "export_date": "2024-02-06T14:30:22Z",
    "export_version": "1.0",
    "plugin_version": "1.2.3",
    "business_name": "Main Repair Shop",
    "exported_by_user": "admin",
    "record_counts": {
      "customers": 150,
      "quotes": 320,
      "jobs": 180,
      "invoices": 160
    }
  },
  "settings": {
    "general": { ... },
    "quote_settings": { ... },
    "business_type": { ... },
    "templates": [ ... ],
    "data_units": { ... }
  },
  "customers": [
    {
      "id": 1,
      "name": "John Smith",
      "email": "john@example.com",
      "phone": "082-123-4567",
      "created_at": "2023-05-15T10:00:00Z",
      "billing_address": { ... },
      "custom_fields": { ... }
    }
  ],
  "quotes": [
    {
      "id": 101,
      "customer_id": 1,
      "status": "accepted",
      "total": 12075.00,
      "created_at": "2023-06-20T14:30:00Z",
      "items": [ ... ],
      "snapshot": { ... },
      "attachments": [ ... ]
    }
  ],
  "jobs": [ ... ],
  "invoices": [ ... ],
  "attachments": [
    {
      "id": 1,
      "quote_id": 101,
      "filename": "photo1.jpg",
      "file_path": "/wp-content/uploads/businessapp/quotes/101/photo1.jpg",
      "file_url": "https://yoursite.com/wp-content/uploads/businessapp/quotes/101/photo1.jpg",
      "file_size": 245678,
      "mime_type": "image/jpeg",
      "base64": null // Optional: can embed base64 for complete export
    }
  ]
}
```

## 5. Export Methods

### Method 1: Full Export (Recommended)

**Use Case:** Complete backup, migration to another system

**What's Included:**
- All data
- All relationships
- Attachments as file paths (links to WordPress uploads)

**File Size:** Typically 1-5 MB for small businesses (1,000 quotes)

**How to Generate:**
Settings → BusinessApp → Export/Import → **Export All Data**

### Method 2: Full Export with Embedded Attachments

**Use Case:** Truly portable backup (includes image data)

**What's Included:**
- All data
- Attachments as base64-encoded strings (embedded in JSON)

**File Size:** Much larger (10-100 MB depending on photos)

**Trade-off:** 
- Pros: Self-contained, no external dependencies
- Cons: Large file size, slower export/import

**How to Generate:**
Settings → BusinessApp → Export/Import → ☑ Include embedded attachments

### Method 3: Filtered Export

**Use Case:** Export specific date range or entity type

**Filters:**
- Date range: "Export quotes from 2023-01-01 to 2023-12-31"
- Entity type: "Export customers only"
- Status: "Export accepted quotes only"

**How to Generate:**
Settings → BusinessApp → Export/Import → **Custom Export** → Set filters

### Method 4: CSV Export (Limited)

**Use Case:** Import to spreadsheet or accounting software

**What's Included:**
- Simple tabular data (quotes, invoices)
- No relationships or nested data

**Limitations:**
- Cannot be reimported to BusinessApp
- Lossy (does not preserve all data)

**Format:**
```csv
Quote ID,Customer Name,Date,Total,Status
101,John Smith,2023-06-20,12075.00,accepted
102,Sarah Jones,2023-06-22,8500.00,sent
```

## 6. Export UI/UX

### Export Page Location
**Settings → BusinessApp → Export/Import**

### Export Interface
```
┌─────────────────────────────────────────────────┐
│ Export BusinessApp Data                         │
├─────────────────────────────────────────────────┤
│                                                  │
│ ○ Full Export (JSON)                            │
│   Includes all data and relationships           │
│                                                  │
│ ○ Full Export with Embedded Attachments         │
│   Includes all data + base64 images (large file)│
│                                                  │
│ ○ Custom Export                                 │
│   Select date range and entity types            │
│                                                  │
│ ○ CSV Export                                    │
│   Simple tabular format (quotes or invoices)    │
│                                                  │
│ [ ] Include deleted records                     │
│ [ ] Include draft quotes                        │
│                                                  │
│ [Generate Export]                               │
└─────────────────────────────────────────────────┘
```

### Export Progress
```
Generating export...
✓ Business settings exported
✓ Customers exported (150 records)
✓ Quotes exported (320 records)
✓ Jobs exported (180 records)
⏳ Invoices exporting... (85/160)
```

### Download
```
┌─────────────────────────────────────────────────┐
│ Export Complete!                                │
├─────────────────────────────────────────────────┤
│ File: businessapp-export-2024-02-06-143022.json│
│ Size: 3.2 MB                                    │
│ Records: 810 total                              │
│                                                  │
│ [Download Export File]                          │
│                                                  │
│ This link expires in 24 hours.                  │
└─────────────────────────────────────────────────┘
```

## 7. Import Functionality

### Import Process

**Step 1: Upload Export File**
Settings → BusinessApp → Export/Import → **Import Data**

```
┌─────────────────────────────────────────────────┐
│ Import BusinessApp Data                         │
├─────────────────────────────────────────────────┤
│ Select export file to import:                   │
│                                                  │
│ [Choose File] businessapp-export.json           │
│                                                  │
│ Import Mode:                                    │
│ ○ Merge (add new records, update existing)     │
│ ○ Replace (delete all data, import fresh)      │
│                                                  │
│ ⚠️  Warning: This action cannot be undone.     │
│    Create a backup before importing.            │
│                                                  │
│ [Preview Import] [Start Import]                 │
└─────────────────────────────────────────────────┘
```

**Step 2: Validation**
- Check JSON structure
- Validate required fields
- Check for ID conflicts

**Step 3: Preview**
```
Preview Import:
• 150 customers (50 new, 100 existing)
• 320 quotes (120 new, 200 existing)
• 180 jobs (all new)
• Potential conflicts: 5 (details below)

Proceed with import?
```

**Step 4: Import Execution**
- Insert new records
- Update existing records (based on ID)
- Recreate relationships
- Download attachments (if URLs provided)

**Step 5: Completion**
```
Import Complete!
• 150 customers imported
• 320 quotes imported
• 180 jobs imported
• 5 conflicts resolved (see log)
```

### Import Modes

**Merge Mode:**
- Adds new records
- Updates existing records if IDs match
- Preserves records not in import file

**Replace Mode:**
- **Deletes ALL existing data**
- Imports from scratch
- Only use when migrating to fresh install

## 8. Automated Backups (Optional)

### Scheduled Exports

**Settings → BusinessApp → Automated Backups**

```
☑ Enable automatic backups
Frequency: Weekly (every Monday at 2:00 AM)
Retention: Keep last 4 backups
Storage: WordPress uploads directory
Email backup link: ☑ Yes
```

**Backup Storage:**
`/wp-content/uploads/businessapp/backups/`

**Files:**
```
businessapp-backup-2024-02-06.json
businessapp-backup-2024-01-30.json
businessapp-backup-2024-01-23.json
businessapp-backup-2024-01-16.json (oldest, will be deleted)
```

## 9. Third-Party Integration Exports

### Export for Accounting Software

**QuickBooks/Xero/Sage Format:**
- Export invoices as CSV
- Map BusinessApp fields to accounting fields
- Include: Invoice number, customer, date, line items, total, tax

**Example CSV:**
```csv
Invoice #,Customer,Date,Description,Amount,Tax,Total
INV-001,John Smith,2024-02-06,Job #50,10000,1500,11500
```

### Export for CRM

**Salesforce/HubSpot Format:**
- Export customers as CSV
- Include: Name, email, phone, address, total revenue, quote count

## 10. Legal Compliance

### GDPR (EU) / POPIA (South Africa)

**Right to Data Portability:**
- Customer requests their data
- Business generates custom export (customer-specific)
- JSON format with all customer's quotes, jobs, invoices
- Delivered via email or download link

**Example Customer Export:**
```json
{
  "customer": {
    "name": "John Smith",
    "email": "john@example.com",
    "phone": "082-123-4567"
  },
  "quotes": [ ... ],
  "jobs": [ ... ],
  "invoices": [ ... ],
  "total_spent": 45000.00
}
```

### Right to Erasure

**Customer requests deletion:**
- Export customer data first (for records)
- Anonymize customer record: "Deleted Customer #123"
- Preserve quote/job/invoice records (business requirement)
- Flag as deleted in export

## 11. Security Considerations

### Export Access Control
- **Only Administrators** can export data
- Check capability: `manage_businessapp_settings`
- Log all export actions: "User X exported data at Time Y"

### Download Security
- Generate temporary download link (expires in 24 hours)
- Token-based: `https://yoursite.com/download?token=abc123`
- Delete file after download (or after 24 hours)

### Sensitive Data
- Exports may contain customer personal data
- Store exports securely (encrypted storage recommended)
- Never email export files (use secure download links)

## 12. API Endpoint

### POST /businessapp/v1/export

**Request:**
```json
{
  "export_type": "full", // full | filtered | csv
  "include_attachments": false,
  "filters": {
    "date_from": "2023-01-01",
    "date_to": "2023-12-31",
    "entities": ["quotes", "invoices"]
  }
}
```

**Response:**
```json
{
  "success": true,
  "export_id": "abc123",
  "download_url": "https://yoursite.com/download?token=xyz789",
  "expires_at": "2024-02-07T14:30:00Z",
  "file_size": 3145728
}
```

## 13. Performance Considerations

### Large Datasets

**Problem:** Business with 10,000 quotes → export takes 30+ seconds

**Solutions:**
1. **Background Processing**: Generate export via cron job, email when ready
2. **Chunked Export**: Export in batches (1,000 records at a time)
3. **Compression**: Gzip JSON file (reduces size by 70%)

**Implementation:**
```php
// Queue export job
wp_schedule_single_event(time(), 'businessapp_generate_export', [$user_id, $filters]);

// Process in background
add_action('businessapp_generate_export', 'process_export_job');

// Email when complete
wp_mail($user_email, 'Export Ready', 'Download: ' . $download_url);
```

## 14. Testing Checklist

- [ ] Export full data, verify JSON structure
- [ ] Export with filters, verify only filtered data included
- [ ] Import exported data to fresh install, verify integrity
- [ ] Export with embedded attachments, verify base64 encoding
- [ ] Test download link expiry (after 24 hours)
- [ ] Test customer-specific export (GDPR scenario)
- [ ] Test CSV export, import to Excel
- [ ] Test large dataset (1,000+ quotes), verify performance
- [ ] Test export permissions (non-admin should be denied)
- [ ] Test automated backup scheduling

## 15. Migration Scenarios

### Scenario 1: Move to New WordPress Site

1. Export from old site (full JSON export)
2. Install BusinessApp on new site
3. Run initial setup wizard
4. Import exported data (replace mode)
5. Verify data integrity
6. Update attachment paths (if needed)

### Scenario 2: Migrate to Different System

1. Export from BusinessApp (full JSON)
2. Write custom import script for target system
3. Map BusinessApp fields to target fields
4. Import customers, quotes, jobs
5. Verify relationships intact

### Scenario 3: Disaster Recovery

1. Daily automated backups enabled
2. Server crash, data lost
3. Restore WordPress from backup
4. Import latest BusinessApp export
5. Verify data up to last backup

## 16. Future Enhancements

### Phase 7+:
- **Cloud Backup**: Auto-upload to Dropbox/Google Drive
- **Incremental Export**: Export only changes since last export
- **Multi-Format**: Export to Excel, PDF, or other formats
- **API Integration**: Direct export to accounting software
- **Version Control**: Track export history, diff between versions

## 17. Summary

BusinessApp's export system ensures:
- **Complete Data Portability**: Full JSON export with all relationships
- **Flexible Options**: Full, filtered, CSV exports
- **Legal Compliance**: GDPR/POPIA data portability support
- **Business Continuity**: Automated backups, disaster recovery
- **Security**: Admin-only access, temporary download links, audit logging

Businesses own their data and can export it anytime, ensuring trust and compliance.

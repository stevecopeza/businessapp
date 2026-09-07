# Attachment Storage Strategy

## 1. Storage Location

**Custom Directory Structure:**
```
/wp-content/uploads/businessapp/
  /quotes/
    /123/
      photo1.jpg
      photo2.jpg
    /124/
      damage-assessment.pdf
  /jobs/
    /50/
      before-photo.jpg
      after-photo.jpg
  /invoices/
    /200/
      receipt.pdf
```

## 2. File Management

### Upload Handling
- Max file size: 10 MB (configurable)
- Allowed types: JPEG, PNG, PDF, DOCX
- WordPress Media Library integration for admin visibility

### Naming Convention
```
{entity_type}_{entity_id}_{timestamp}_{original_name}
Example: quote_123_20240206143022_photo.jpg
```

### Deletion Policy
- Soft delete: Mark as deleted, keep file for 90 days
- Hard delete: Remove file after 90 days (cron job)
- Business can configure retention

## 3. Security

- Files stored outside web root (if possible)
- Access via PHP script with token validation
- No direct file URLs (prevents unauthorized access)
- Virus scanning on upload (ClamAV integration optional)

## 4. Backup

- Include in BusinessApp data exports
- Option: Base64 embed in JSON for complete backup
- Cloud backup integration (Dropbox, Google Drive - future)

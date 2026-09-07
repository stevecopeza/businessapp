# Disaster Recovery

## 1. Backup Strategy

### What to Backup
- WordPress database (includes BusinessApp tables)
- WordPress uploads directory (/wp-content/uploads/businessapp/)
- WordPress configuration (wp-config.php)
- BusinessApp settings (wp_options entries)

### Backup Frequency
- **Daily**: Automated database backups
- **Weekly**: Full site backups (files + database)
- **Before updates**: Manual backup before plugin updates

### Backup Tools
- WordPress backup plugins (UpdraftPlus, BackWPup)
- Server-level backups (cPanel, Plesk)
- BusinessApp JSON export (nightly automated)

## 2. Recovery Scenarios

### Scenario 1: Accidental Data Deletion
**Problem:** Admin deletes 50 quotes accidentally

**Recovery:**
1. Restore from last nightly JSON export
2. Import deleted quotes (merge mode)
3. Verify data integrity

**Time to Recovery:** < 30 minutes

### Scenario 2: Database Corruption
**Problem:** Database crashes, tables corrupted

**Recovery:**
1. Restore WordPress database from backup
2. Verify BusinessApp tables intact
3. Run data integrity check script

**Time to Recovery:** 1-2 hours

### Scenario 3: Complete Server Failure
**Problem:** Hosting provider goes offline, all data lost

**Recovery:**
1. New server setup (WordPress installation)
2. Restore database backup
3. Restore files backup
4. Install BusinessApp plugin
5. Verify all data present

**Time to Recovery:** 2-4 hours

### Scenario 4: Ransomware Attack
**Problem:** Files encrypted by ransomware

**Recovery:**
1. Do NOT pay ransom
2. Isolate affected server
3. New clean server
4. Restore from offsite backup (encrypted backups recommended)
5. Scan for malware before going live

**Time to Recovery:** 4-8 hours

## 3. Backup Verification

### Monthly Tests
- Restore backup to staging environment
- Verify all quotes, jobs, invoices present
- Test critical workflows
- Document any issues

### Automated Checks
```php
// Cron job: Verify backup integrity
function businessapp_verify_backup() {
    $backup_file = '/backups/latest.json';
    $data = json_decode(file_get_contents($backup_file), true);
    
    if (count($data['quotes']) < 100) {
        wp_mail('admin@business.com', 'Backup Warning', 'Backup may be incomplete');
    }
}
```

## 4. Offsite Backups

### Cloud Storage
- Google Drive: Daily JSON exports
- Dropbox: Weekly full backups
- AWS S3: Long-term archive (30-day retention)

### 3-2-1 Rule
- **3** copies of data (production + 2 backups)
- **2** different media types (local + cloud)
- **1** offsite backup (cloud storage)

## 5. Recovery Time Objectives (RTO)

| Scenario | Target RTO |
|----------|------------|
| Single record deletion | 15 minutes |
| Multiple records deleted | 30 minutes |
| Database corruption | 2 hours |
| Complete server failure | 4 hours |
| Ransomware attack | 8 hours |

## 6. Data Integrity Checks

### Post-Recovery Validation
```sql
-- Verify quote counts
SELECT COUNT(*) FROM wp_businessapp_quotes;

-- Check for orphaned records
SELECT * FROM wp_businessapp_quote_items 
WHERE quote_id NOT IN (SELECT id FROM wp_businessapp_quotes);

-- Verify customer linkage
SELECT COUNT(*) FROM wp_businessapp_quotes 
WHERE customer_id NOT IN (SELECT id FROM wp_businessapp_customers);
```

## 7. Business Continuity

### During Downtime
- Set up status page: "BusinessApp temporarily offline, restoration in progress"
- Email customers: "Your quotes are safe, service resuming soon"
- Provide phone support for urgent queries

### After Recovery
- Audit log: Review what was lost/recovered
- Post-mortem: Document what went wrong, how to prevent
- Update disaster recovery plan

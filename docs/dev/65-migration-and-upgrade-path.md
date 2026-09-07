# Migration and Upgrade Path

## 1. Version Upgrade Strategy

### Semantic Versioning
- Major.Minor.Patch (e.g., 1.2.3)
- Major: Breaking changes (requires migration)
- Minor: New features (backward compatible)
- Patch: Bug fixes

### Database Migrations
```php
function businessapp_upgrade_to_1_2_0() {
    global $wpdb;
    // Add new column
    $wpdb->query("ALTER TABLE wp_businessapp_quotes 
                  ADD COLUMN revision_number INT DEFAULT 0");
    update_option('businessapp_db_version', '1.2.0');
}
```

### Rollback Strategy
- Always backup before upgrade
- Keep previous version available for 30 days
- Provide downgrade SQL scripts

## 2. Business Type Migrations

### Scenario: Panel Beater v1 → Panel Beater v2

**When new fields added:**
1. New quotes use new schema
2. Old quotes retain old schema (snapshot model protects)
3. Optional: "Migrate old quotes" tool (admin-only, manual trigger)

**When fields removed:**
1. Hide field from new quotes
2. Keep field in old quotes (data preserved)
3. Mark as deprecated in schema

### Cross-Business Type Migration

**Not Supported:** Cannot change Renovator → Panel Beater

**Workaround:**
1. Export all data (JSON)
2. Fresh install with new Business Type
3. Manual data transformation
4. Import transformed data

## 3. WordPress Core Updates

### Compatibility Testing
- Test against WordPress beta releases
- Maintain compatibility matrix
- Support last 2 major WordPress versions

### Breaking Changes
- Monitor WordPress development
- Update deprecated function calls
- Test REST API changes

## 4. Data Migration Tools

### Export/Import (see quality/36-data-export-and-portability.md)

### Migration Scripts
```bash
# Migrate from competing software
php wp-cli.phar businessapp migrate --from=competitor --file=export.csv
```

## 5. Upgrade Notifications

### In-Dashboard Alerts
```
┌─────────────────────────────────────────────────┐
│ 🎉 BusinessApp 1.3.0 Available                  │
├─────────────────────────────────────────────────┤
│ New: WhatsApp notifications, customer portal    │
│ Improved: Faster quote generation               │
│ Fixed: Rare sync bug                            │
│                                                  │
│ [View Changelog] [Update Now]                   │
└─────────────────────────────────────────────────┘
```

### Email Notifications
- Critical updates: Email all admins
- Optional updates: Dashboard notification only
- Breaking changes: Multiple warnings before release

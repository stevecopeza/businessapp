# Permissions and Roles Model

## 1. Overview

BusinessApp uses WordPress's native role and capability system to control access to features and data. This approach leverages familiar WordPress concepts while adding custom capabilities specific to BusinessApp's domain.

## 2. Role Strategy

BusinessApp maps to standard WordPress roles with custom capabilities added:

### Administrator
- **Full System Access**: Can access all BusinessApp features
- **Configuration**: Can modify Business Type settings, templates, and system configuration
- **Data Management**: Can create, edit, delete all quotes, jobs, invoices, and customers
- **User Management**: Can add/remove users and assign roles
- **Financial Visibility**: Can see all pricing, costs, and revenue data

### Editor
- **Operational Access**: Can create and manage quotes, jobs, and invoices
- **Customer Management**: Can create and edit customer records
- **Limited Settings**: Can modify quote templates and basic preferences
- **Financial Visibility**: Can see all pricing and revenue data
- **Cannot**: Change Business Type, modify tax rates, or delete users

### Author
- **Own Records Only**: Can create and manage their own quotes and jobs
- **Customer View**: Can view all customers but only edit those they created
- **Limited Financial**: Can see pricing on their own records only
- **Cannot**: Access system settings, view other users' records, or delete any records

### Contributor
- **Draft Creation**: Can create quotes and jobs but cannot send/finalize them
- **Customer View**: Read-only access to customer database
- **No Financial Access**: Cannot see costs or revenue data
- **Cannot**: Send quotes, create invoices, or modify settings

### Subscriber
- **No Access**: No access to BusinessApp admin interface
- Reserved for customer-facing portal (future phase)

## 3. Custom Capabilities

BusinessApp adds the following custom capabilities to WordPress:

### Quote Capabilities
- `manage_quotes` — Create, edit, and delete quotes
- `send_quotes` — Send quotes to customers
- `accept_quotes` — Mark quotes as accepted (usually system-driven)
- `view_all_quotes` — View quotes created by other users
- `export_quotes` — Export quote data

### Job Capabilities
- `manage_jobs` — Create, edit, and complete jobs
- `view_all_jobs` — View jobs created by other users
- `assign_jobs` — Assign jobs to other users

### Invoice Capabilities
- `manage_invoices` — Create, edit, and send invoices
- `view_all_invoices` — View all invoices
- `record_payments` — Record payment transactions

### Customer Capabilities
- `manage_customers` — Create and edit customer records
- `view_all_customers` — View all customers (vs. only own customers)
- `delete_customers` — Delete customer records

### Financial Capabilities
- `view_financial_data` — See pricing, costs, and revenue
- `manage_payments` — Record and manage payment transactions
- `view_reports` — Access financial reports

### System Capabilities
- `manage_businessapp_settings` — Modify system configuration
- `export_data` — Export full system data

## 4. Capability Mapping

| Capability | Admin | Editor | Author | Contributor |
|-----------|-------|--------|--------|-------------|
| manage_quotes | ✓ | ✓ | Own only | Draft only |
| send_quotes | ✓ | ✓ | Own only | ✗ |
| view_all_quotes | ✓ | ✓ | ✗ | ✗ |
| manage_jobs | ✓ | ✓ | Own only | ✗ |
| manage_invoices | ✓ | ✓ | ✗ | ✗ |
| view_financial_data | ✓ | ✓ | Own only | ✗ |
| manage_customers | ✓ | ✓ | ✓ | ✗ |
| view_all_customers | ✓ | ✓ | ✓ | ✓ |
| manage_payments | ✓ | ✓ | ✗ | ✗ |
| manage_businessapp_settings | ✓ | ✗ | ✗ | ✗ |
| export_data | ✓ | ✗ | ✗ | ✗ |

## 5. Field Worker Considerations

For businesses with field workers who need to create quotes on-site but shouldn't see all financial data:

**Recommended Role**: Author

**Capabilities**:
- Can create new quotes in Draft state
- Can view and edit their own quotes
- Can view customer information
- **Cannot** see other workers' quotes
- **Cannot** see cost data or profit margins (optional: can be further restricted)

**Settings Toggle**: "Hide cost data from field workers" (applies to Author role)

## 6. Office Admin Considerations

For administrative staff who handle invoicing and payments but don't quote:

**Recommended Role**: Editor (with custom capability restrictions if needed)

**Capabilities**:
- Can view all quotes and jobs
- Can create and send invoices
- Can record payments
- Can manage customers
- **Cannot** modify Business Type or system settings

## 7. Permission Enforcement

### API Level
All REST API endpoints check capabilities before allowing operations:
```php
if (!current_user_can('manage_quotes')) {
    return new WP_Error('forbidden', 'You do not have permission to manage quotes', ['status' => 403]);
}
```

### UI Level
Frontend UI hides/disables features based on capabilities:
```javascript
if (!userCan('send_quotes')) {
    hideElement('#send-quote-button');
}
```

### Data Filtering
Queries automatically filter data based on permissions:
- Authors see only their own records
- Editors/Admins see all records

## 8. Future Considerations

### Customer Portal (Phase 6+)
When customer-facing portal is added:
- Customers assigned "Subscriber" role
- Custom capabilities: `view_own_quotes`, `accept_own_quotes`, `view_own_invoices`
- Customers can ONLY see their own data (strict isolation)

### Team Features (Phase 7+)
If team collaboration is added:
- `assign_users_to_quotes` capability
- `view_team_quotes` capability (see quotes from your team)
- Team-based data filtering

## 9. Implementation Notes

### Setup During Installation
On plugin activation, BusinessApp:
1. Adds custom capabilities to existing WordPress roles
2. Creates default Administrator with all capabilities
3. Provides settings page to customize role capabilities (Admin only)

### Capability Customization
Administrators can modify which capabilities are assigned to each role via:
**Settings → BusinessApp → Permissions**

This allows businesses to customize access based on their specific needs.

## 10. Security Principles

- **Least Privilege**: Users get minimum capabilities needed for their job function
- **Explicit Deny**: If capability not granted, action is denied (no implicit allows)
- **Data Isolation**: Authors cannot see others' data unless explicitly granted
- **Audit Trail**: All permission-based actions are logged (quote sent by User X)
- **No Capability Bypass**: No "superuser" code paths that skip capability checks

## 11. Common Scenarios

### Scenario: Solo Owner-Operator
- **Setup**: Single user with Administrator role
- **Access**: Full access to all features

### Scenario: Owner + 2 Field Workers
- **Owner**: Administrator (full access)
- **Worker 1**: Author (own quotes only, no financial data)
- **Worker 2**: Author (own quotes only, no financial data)

### Scenario: Owner + Office Manager + 3 Field Workers
- **Owner**: Administrator (full access)
- **Office Manager**: Editor (all quotes/invoices, no system settings)
- **Workers**: Author (own quotes only)

### Scenario: Multi-Person Office
- **Owner**: Administrator
- **Sales Team**: Editor (create/send quotes, see all quotes)
- **Operations Team**: Editor (manage jobs, see quotes)
- **Admin Staff**: Editor (invoicing, payments)

## 12. Compliance & Audit

For businesses requiring audit trails:
- All capability checks are logged
- Permission changes trigger notifications
- Audit log shows: "User X performed Action Y at Time Z"
- Available in Settings → BusinessApp → Audit Log (Admin only)

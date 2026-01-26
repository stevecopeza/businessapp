# BusinessApp — Invoice Data Model

## Database Schema

### 1. Invoices Table (`wp_businessapp_invoices`)
Stores the header information for an invoice.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BIGINT(20) | Primary Key |
| `job_id` | BIGINT(20) | FK to Jobs (nullable for direct invoices) |
| `customer_id` | BIGINT(20) | FK to Customers |
| `status` | VARCHAR(20) | `draft`, `sent`, `paid`, `void` |
| `title` | TEXT | Invoice title/reference (e.g. "Invoice #1001") |
| `issue_date` | DATETIME | Date issued to customer |
| `due_date` | DATETIME | Payment due date |
| `subtotal` | DECIMAL(10,2) | Sum of items before tax |
| `tax_total` | DECIMAL(10,2) | Total tax amount |
| `total` | DECIMAL(10,2) | Final payable amount |
| `notes` | TEXT | Public notes for the customer |
| `created_at` | DATETIME | Record creation timestamp |
| `updated_at` | DATETIME | Record update timestamp |

### 2. Invoice Items Table (`wp_businessapp_invoice_items`)
Stores the line items specific to an invoice. These are separate from Job Items to allow for final adjustments without altering the Job record.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BIGINT(20) | Primary Key |
| `invoice_id` | BIGINT(20) | FK to Invoices |
| `description` | TEXT | Item description |
| `qty` | INT | Quantity |
| `unit` | VARCHAR(50) | Unit of measure |
| `unit_price` | DECIMAL(10,2) | Price per unit |
| `amount` | DECIMAL(10,2) | Calculated line total (`qty * unit_price`) |
| `type` | VARCHAR(50) | `labor`, `material`, `fee` |
| `position` | INT | Sorting order |

## Relationships
*   **Job -> Invoice:** One-to-Many (technically), but operationally usually One-to-One.
*   **Customer -> Invoice:** One-to-Many.

## Domain Model
*   **Invoice Entity:** Encapsulates the logic for totaling, state transitions, and snapshotting.
*   **InvoiceRepository:** Handles persistence.

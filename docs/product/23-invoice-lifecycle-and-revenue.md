# BusinessApp — Invoice Lifecycle & Revenue Reporting

## 1. Overview
The Invoicing module (Phase 3) completes the operational workflow by allowing businesses to bill customers for completed jobs. It introduces a dedicated **Invoices** section in the admin, a new **Invoice Editor**, and a **Revenue Dashboard Widget**.

## 2. Core Concepts

### 2.1 The Invoice Entity
An Invoice represents a request for payment. It is linked to a **Customer** and optionally a **Job**.
- **Fields**: Title, Notes, Status, Total Amount, Created Date.
- **Line Items**: Detailed breakdown of services/labor (Description, Qty, Unit Price, Total).

### 2.2 Invoice Status Lifecycle
The invoice lifecycle is simple and linear:
1.  **Draft**: The invoice is being created/edited. Not yet visible to the customer.
2.  **Sent**: The invoice has been finalized and sent to the customer (via email/PDF - *future scope*). The invoice should generally not be changed after this point.
3.  **Paid**: The customer has paid the invoice in full.
4.  **Cancelled**: The invoice was voided or written off.

## 3. Workflows

### 3.1 Generating an Invoice from a Job
This is the primary workflow for service-based businesses.
1.  Navigate to **BusinessApp > Jobs**.
2.  Ensure the Job status is **Completed**.
3.  Click the **"Generate Invoice"** button in the Job Editor.
4.  **Result**: A new Draft Invoice is created, copying the Job's customer and title. You are automatically redirected to the new invoice to add line items.

### 3.2 Manual Invoice Creation
1.  Navigate to **BusinessApp > Invoices**.
2.  Click **"Add New Invoice"**.
3.  Select a Customer and enter basic details.
4.  Add Line Items manually using the "Add Item" button.
5.  Save the invoice.

### 3.3 Managing Revenue (The Dashboard)
A new **BusinessApp Revenue** widget has been added to the main WordPress Dashboard (`/wp-admin/index.php`).
- **Total Invoiced**: Sum of all invoices (excluding Cancelled/Draft).
- **Total Paid**: Sum of all invoices with status 'Paid'.
- **Progress Bar**: Visualizes the percentage of invoiced amount that has been collected.

## 4. Technical Implementation
- **Storage**: `wp_businessapp_invoices` and `wp_businessapp_invoice_items` tables.
- **API**: REST endpoints at `/wp-json/businessapp/v1/invoices`.
- **Frontend**: React-based Single Page Application (SPA) components embedded in WP Admin.

# BusinessApp — Invoice Lifecycle Flow

## Overview
Invoicing is the final stage of the operational workflow. It formalizes the financial obligation of the Customer based on the work completed in a Job.

## Creation Triggers
1.  **From Completed Job (Primary):**
    *   An invoice is typically generated from a Job that has reached the `Completed` status.
    *   **Data Inheritance:**
        *   **Customer:** Inherited from Job.
        *   **Line Items:** Copied from Job Items.
        *   **Reference:** Links back to the source Job ID.
    *   **One-to-One Rule:** Typically one Job results in one Invoice, though partial invoicing is a future consideration. For Phase 3, we assume full invoicing.

2.  **Direct Creation (Secondary):**
    *   Admins can create a standalone invoice without a job (e.g., for ad-hoc services).

## Lifecycle States

1.  **Draft (Mutable)**
    *   The initial state.
    *   Line items can be adjusted (e.g., adding travel fees, adjusting final labor hours).
    *   Not visible to the customer.

2.  **Sent (Immutable Snapshot)**
    *   The invoice has been issued to the customer (email/PDF).
    *   **Locking:** The financial data (items, totals, tax) is locked to ensure the document matches what the customer received.
    *   **Edits:** Requires reverting to Draft (if not paid) or issuing a Credit Note (future phase).

3.  **Paid (Terminal)**
    *   Payment has been recorded.
    *   Fully locked.

4.  **Void (Terminal)**
    *   The invoice was cancelled before payment.

5.  **Partial (Intermediate)**
    *   A payment has been received but the balance is > 0.
    *   Added in Phase 5 (Payments).

## Financial Calculations
*   **Subtotal:** Sum of all line items.
*   **Tax:** Calculated based on the global tax rate setting at the time of invoice creation/update.
*   **Total:** Subtotal + Tax.
*   **Balance Due:** Total - Sum(Payments). Updated in Phase 5 to support partial payments and tracking.

## Data Integrity
*   **Snapshotting:** Like Quotes, Invoices must snapshot their customer details and settings at the time of issuance. Changing a global tax rate should not alter historical sent invoices.

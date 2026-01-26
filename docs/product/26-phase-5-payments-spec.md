# Payment Architecture & Principles (Phase 5)

## 1. Core Philosophy
Phase 5 transforms BusinessApp from a passive record-keeping tool into an active revenue collection platform. The architecture prioritizes security, accuracy, and auditability above all else.

### 1.1 Guiding Principles
1.  **Security First**: We NEVER touch raw credit card data. All sensitive handling is offloaded to PCI-DSS compliant providers (Stripe).
2.  **Idempotency**: The system must robustly handle network interruptions, double-clicks, and page reloads without creating duplicate payment records.
3.  **Source of Truth**: The `Payment` entity is the authoritative record of money movement. The Invoice status (`Paid`/`Unpaid`) is a *derived state* based on the sum of associated payments.
4.  **Gateway Agnosticism**: While Stripe is the MVP provider, the internal domain model (`Payment` class) is generic and decoupled from specific gateway implementation details.

## 2. Payment Data Model

### 2.1 The Payment Entity
The `Payment` is an immutable record of a transaction.
-   **Table**: `wp_businessapp_payments`
-   **Key Fields**:
    -   `invoice_id`: Link to the debt being settled.
    -   `transaction_id`: The external gateway's unique identifier (e.g., `pi_3L...`).
    -   `amount`: The value in major currency units (e.g., Dollars, not Cents).
    -   `gateway`: Origin of the funds (`stripe`, `manual`, `cash`).
    -   `meta`: JSON blob for gateway-specific debug data.

### 2.2 Status Automation
Invoice status is calculated, not just set.
-   **Rule**: `IF (Sum(Payments) >= Invoice.Total) THEN Status = 'Paid'`
-   **Trigger**: This check runs on every successful payment recording.

## 3. Online Payment Flow (Stripe)

### 3.1 Intent-Based Workflow
We utilize the Stripe "Payment Intents" API to ensure strong customer authentication (SCA) compliance.
1.  **Frontend**: Requests a `client_secret` from the backend via REST API (`/create-payment-intent`).
2.  **Backend**: Calculates the *current* outstanding amount from the Invoice entity to prevent under/overpayment.
3.  **Frontend**: Uses Stripe Elements to securely collect card details and confirm the intent.
4.  **Verification**: Upon redirect/completion, the backend *independently* verifies the transaction status with Stripe before recording it locally. We do not trust client-side success messages alone.

### 3.2 Duplicate Prevention
Before creating a `Payment` record, the repository checks:
`SELECT * FROM payments WHERE transaction_id = {incoming_id}`
If a match exists, the operation is treated as a success but no new record is created (idempotent).

## 4. Manual Payment Flow
Admins must be able to record offline transactions (Cash, Checks).
-   **Mechanism**: A dedicated admin interface creates `Payment` entities with `gateway = 'manual'`.
-   **Consistency**: Manual payments trigger the same Invoice status update logic as online payments.

## 5. Security & Invariants
-   **Invariant**: A Payment record cannot be modified after creation (Immutable Ledger).
-   **Invariant**: Payment amounts are stored in the database as decimals (10,2) to prevent floating-point errors, but processed in cents (integers) when communicating with Stripe.
-   **Security**: API Keys (Secret/Publishable) are stored in `wp_options` and never exposed in public HTML source (except the Publishable key).

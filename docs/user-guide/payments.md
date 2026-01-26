# Payments User Guide

This guide explains how to configure and manage payments in BusinessApp.

## 1. Overview
BusinessApp allows you to collect payments for your invoices in two ways:
1.  **Online Payments**: Accept credit card payments securely via Stripe.
2.  **Manual Payments**: Record payments made offline (Cash, Check, Bank Transfer).

## 2. Configuration (Stripe)
To enable online payments, you must connect your Stripe account.

### Prerequisites
- A valid [Stripe account](https://stripe.com).
- Your Stripe **Publishable Key** and **Secret Key**.

### Steps
1.  Log in to your WordPress Admin dashboard.
2.  Navigate to **BusinessApp > Settings**.
3.  Click on the **Payments** tab.
4.  Enter your keys:
    -   **Stripe Publishable Key**: Starts with `pk_live_` (or `pk_test_`).
    -   **Stripe Secret Key**: Starts with `sk_live_` (or `sk_test_`).
5.  Click **Save Settings**.

> **Note**: You can use Stripe's "Test Mode" keys to test the payment flow without charging a real card.

## 3. Accepting Online Payments
Once configured, the payment flow is automatic:
1.  Create an **Invoice** in BusinessApp.
2.  Change the Invoice status to **Sent** (or send the public link to the customer).
3.  The customer opens the invoice link.
4.  They see a **Pay with Card** form on the invoice page.
5.  Upon successful payment:
    -   The payment is recorded in the system.
    -   The Invoice status automatically updates to **Paid**.
    -   The customer sees a success message.

## 4. Manual Payments
If a customer pays you offline (e.g., Cash or Bank Transfer), you can record it manually:
1.  Navigate to **BusinessApp > Invoices**.
2.  Open the Invoice.
3.  Scroll to the **Payments** section.
4.  Click **Add Payment**.
5.  Enter the payment details (Amount, Date, Method).
6.  Click **Save**.

## 5. Troubleshooting

### "Stripe is not configured" Error
-   **Cause**: The API keys are missing or invalid.
-   **Fix**: Go to Settings > Payments and double-check your keys.

### Payment Fails
-   **Cause**: Customer card declined or invalid keys.
-   **Fix**: Check your Stripe Dashboard logs for specific error messages.

### Invoice Status Not Updating
-   **Cause**: The payment amount might be less than the invoice total.
-   **Fix**: Check the recorded payment amount. Invoices only mark as "Paid" if fully paid.

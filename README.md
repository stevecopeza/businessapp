# BusinessApp

BusinessApp is a comprehensive WordPress plugin designed to streamline business operations by managing quotes, jobs, and customer relationships.

## Features

- **Quote Management**: Create and manage detailed quotes with line items and dynamic fields.
- **Job Workflow**: Seamlessly convert approved quotes into active jobs, preserving all data including line items and schema snapshots.
- **Invoicing & Payments**: Generate invoices from jobs and accept online payments via Stripe.
- **Customer Entities**: Manage associated entities for customers (e.g., vehicles, properties) with full CRUD support.
- **Dynamic Schemas**: Flexible data modeling with support for custom fields and versioned schema snapshots.
- **Admin Interface**: Integrated WordPress Admin UI for easy management of all business data.

## Installation

1. Clone the repository to your WordPress plugins directory.
2. Activate the **BusinessApp** plugin via the WordPress Admin dashboard.
3. Configure business types and settings as needed.

## Configuration

### Stripe Payments
To enable online payments for invoices:
1. Go to **BusinessApp > Settings**.
2. Click on the **Payments** tab.
3. Enter your **Stripe Publishable Key** and **Stripe Secret Key**.
4. Click **Save Settings**.

Once configured, invoices sent to customers will include a secure payment form.

## Development

- **Backend**: PHP-based architecture following Domain-Driven Design (DDD) principles.
- **Frontend**: jQuery-based Admin UI integration.
- **Testing**: Includes a unit test suite (`tests/unit_tests.php`) for verifying core business logic.

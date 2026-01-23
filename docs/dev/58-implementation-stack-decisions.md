# Implementation Stack Decisions

## Core Stack
- **Frontend:** Vanilla JS (SPA architecture) with Tailwind CSS.
- **Backend:** WordPress PHP Plugin.
- **API:** WordPress REST API (`namespace: businessapp/v1`).
- **Database:** MariaDB/MySQL.
  - **Quotes:** Custom Table `wp_businessapp_quotes`
  - **Items:** Custom Table `wp_businessapp_quote_items`
  - **Settings:** WordPress Options API (`wp_options`)

## Settings Storage Strategy
Settings are persisted via the standard WordPress Options API to ensure compatibility and ease of backup. To avoid option bloat and ensure atomic updates per domain, settings are grouped:

- `businessapp_settings_general`: Business profile, currency, tax rate.
- `businessapp_settings_workflow`: Quote expiry, acceptance requirements.
- `businessapp_settings_templates`: JSON-encoded array of service templates.
- `businessapp_settings_business_type`: Industry configuration and field toggles.
- `businessapp_settings_units`: Measurement unit preferences.

## Frontend Architecture
- **State Management:** Centralized reactive state object in `app.js`.
- **Routing:** State-based view switching (`activeView` property).
- **Styling:** Tailwind CSS (Utility-first).
- **Mobile:** Responsive design with offline-tolerant data flows.

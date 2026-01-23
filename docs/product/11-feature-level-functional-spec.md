FILENAME: 11-feature-level-functional-spec.md

# BusinessApp — Feature-Level Functional Specification (Non-Technical)

- Customer management: identify who the work is for
- Catalog: describe common types of work
- Quoting: create, discuss, revise, accept
- Attachments: photos and supporting context
- Permissions: control who can do what
- Settings: tailor the system to the business
  - **Business Profile:** Manage contact details and branding.
  - **Workflow Configuration:** Set default tax rates, quote expiry times, and approval requirements.
  - **Templates:** Pre-define common services to speed up quoting.
  - **Domain Specifics:** Toggle fields relevant to specific industries (e.g., VIN/Make/Model for automotive).
  - **Localization:** Configure measurement units and currency symbols.

## Schema Inheritance Rules

Quotes, Jobs, Tasks, and Orders inherit their input fields from the Business Type configuration active at creation time. These records do not rebind, refresh, or automatically update their schema when configuration changes occur.

Each feature exists to support quoting.

UI to domain mapping as previously provided.


---
### Implementation Clarification

Current implemented mappings include:

- Admin “BusinessApp → Dashboard”
  - Domain: Quote (draft state)
  - Actions:
    - Create draft quote with title and total amount.
    - List all quotes from the `businessapp_quotes` table.

- Front-end shortcode workspace `[businessapp_app]`
  - Domain: Quote (draft state, list of quotes)
  - Actions:
    - Create draft quote via `POST /businessapp/v1/quotes`.
    - Fetch and display quotes via `GET /businessapp/v1/quotes`.
  - Domain: Settings (options)
    - Settings mapping to WordPress Options (primarily `businessapp_settings_general`):
      - **General:** Tax Rate, Currency, Business Profile.
      - **Quote Settings:** Expiry days, acceptance requirements.
      - **Templates:** Stored as structured options for reuse.

Later iterations will extend these mappings to cover full quote lifecycle, customer-facing views, comments, attachments, and jobs.

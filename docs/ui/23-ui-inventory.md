UI inventory as previously provided.


---
### Implementation Clarification

The first implemented UI surfaces are:

- WordPress admin
  - BusinessApp top-level menu with:
    - Dashboard: create simple draft quotes and list existing quotes.
    - Settings: placeholder for future configuration.

- Front-end workspace
  - Rendered via `[businessapp_app]` shortcode on a standard WordPress page.
  - Layout uses Tailwind CSS and presents:
    - **Dashboard View:**
      - A “Create Quote” panel (title and total amount, saved as draft).
      - A “Quotes” list showing ID, title, status, and total.
    - **Settings View:**
      - **General:** Business profile (Name, Email, Phone) and Currency selection.
      - **Quote Settings:** Workflow preferences (Acceptance requirements, Revisions), Expiry defaults, and Tax Rate.
      - **Templates:** Service template management with pricing.
      - **Business Type:** Industry selection (e.g., Panel Beater) with toggleable vehicle fields and default options.
      - **Data & Units:** Measurement unit configuration (Length, Area, Weight).

These map to early versions of the quote creation, quote list, and settings screens described in the product and UI specs. Additional screens and states will be implemented to match the full inventory.

# Public Document Architecture & Principles (Phase 4)

## 1. Core Philosophy
The Public Document system allows external parties (customers) to view sensitive business documents (Quotes, Invoices) without requiring a user account or login credentials. This reduces friction while maintaining security.

### 1.1 Guiding Principles
1.  **Frictionless Access**: Customers should never need to "log in" to view a quote or invoice.
2.  **Token-Based Security**: Access is granted via cryptographically secure, unguessable tokens.
3.  **Theme Independence**: Public views render independently of the active WordPress theme to ensure consistent branding and layout control.
4.  **Print-First Design**: The HTML view is the "source of truth" and must print perfectly to PDF/Paper via standard browser controls. We explicitly avoid server-side PDF generation to reduce complexity and dependencies.

## 2. Security Architecture

### 2.1 Capability-Based URLs
We use the "Capability URL" pattern. Possession of the URL grants access to the resource.
-   **Token Generation**: 32-character random alphanumeric strings (or UUIDs) generated at document creation or "Send" time.
-   **Storage**: Stored in the `public_token` column of the `quotes` and `invoices` tables.
-   **URL Structure**: `?businessapp_invoice_token={token}` (uses query parameters to avoid permalink conflicts).

### 2.2 Input Sanitization
-   Tokens are sanitized via `sanitize_text_field()` before database queries.
-   Output is strictly escaped (`esc_html`, `esc_attr`) to prevent XSS.

## 3. Technical Implementation

### 3.1 Routing Strategy
-   **Hook**: `template_redirect`
-   **Reasoning**: This hook fires before the theme template is loaded. This allows us to intercept the request, check for our specific query parameters, and render our custom template *instead* of the WordPress theme.
-   **Result**: A clean, isolated environment free from theme styles/scripts that could break the layout.

### 3.2 View Rendering
-   **Technology**: Pure PHP/HTML mixed mode (no heavy frontend framework for these views).
-   **Styles**: In-line or specific CSS loaded only for this view.
-   **Responsive**: Mobile-friendly layout by default.

## 4. Acceptance Criteria & Invariants
-   **Invariant**: A valid token MUST resolve to exactly one document.
-   **Invariant**: Invalid tokens MUST return a 404 status (not a 200 with an error message).
-   **UX**: The page must look professional and complete when printed (Cmd+P).
-   **Isolation**: No WordPress admin bar or theme headers should ever appear on these pages.

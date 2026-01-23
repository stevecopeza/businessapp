UI state model as previously provided.

# UI State Model

The Frontend App (`app.js`) uses a centralized reactive state object.

## Core State Properties
| Property | Type | Description |
|----------|------|-------------|
| `activeView` | String | Controls the main content area. Values: `'dashboard'`, `'settings'`, `'quotes'`, `'new-quote'`. |
| `activeSettingsTab` | String | Controls the active tab within the Settings view. Values: `'general'`, `'quote-settings'`, `'templates'`, `'business-type'`, `'data-units'`. |
| `quotes` | Array | List of loaded quote objects. |
| `loading` | Boolean | Global loading state indicator. |
| `error` | String\|Null | Global error message storage. |

## State Transitions
- **Navigation:** Clicking the "Settings" nav item sets `activeView` to `'settings'`.
- **Tab Switching:** Clicking a settings tab updates `activeSettingsTab` without changing `activeView`.

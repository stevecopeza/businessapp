# UI Component Responsibilities

## Component Architecture

BusinessApp uses a component-based architecture with clear separation of concerns.

### Core Components

**QuoteForm**
- Renders quote creation/editing form
- Handles line item additions/removals
- Validates input
- Emits save/cancel events

**QuoteList**
- Displays paginated quote list
- Filters by status, date, customer
- Handles sort/search
- Emits select/delete events

**CustomerSelector**
- Autocomplete customer search
- Create new customer inline
- Validates customer data
- Emits customer-selected event

**LineItemTable**
- Editable table for quote line items
- Calculates subtotals/tax/total
- Supports drag-to-reorder
- Emits item-changed events

## Component Communication

- Parent → Child: Props
- Child → Parent: Custom events
- Sibling → Sibling: Global state (app.js)

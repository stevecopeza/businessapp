# BusinessApp — Data Mutation Logic

## Core Principle
Historical data integrity is paramount. BusinessApp strictly prohibits silent or automatic mutation of existing records. Any change to data structure or meaning must be the result of an intentional, explicit action.

## Configuration Changes
Configuration updates (e.g., changing Business Type settings) are forward-looking operations.
- **Scope:** Changes apply only to records created *after* the configuration update.
- **Effect:** Existing records retain their original data and structure exactly as they were at the time of creation.
- **Rule:** No background process shall retroactively apply new configuration settings to old records.

## Unit Change Logic
Units of measurement are intrinsic to the semantic meaning of numerical data. Changing a unit definition is a significant mutation event.

### 1. Default Behaviour: Conversion
When an administrator changes a unit setting (e.g., from Meters to Millimeters):
- The system defaults to **converting** existing numerical values to match the new unit.
- This ensures the physical reality represented by the data remains constant.

### 2. Administrator Control
- **Warning:** The system must explicitly warn the administrator that a unit change will affect data interpretation.
- **Opt-Out:** The administrator must have the option to opt out of conversion.
- **Preservation:** If conversion is declined, existing values are retained exactly as-is, preserving the historical number even if the unit label changes.

## Scope of Application
These mutation rules apply universally across all operational entities:
- Quotes
- Jobs
- Tasks
- Orders

By adhering to these rules, the system guarantees that data remains predictable, auditable, and true to the context in which it was originally recorded.

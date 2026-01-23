# BusinessApp — Primary User Happy Path

## Logical Flow
This document outlines the ideal flow of work through the system, emphasizing data stability across transitions.

1. **Quote Creation:**
   - User initiates a new quote.
   - System applies the currently active Business Type schema.
   - **Rule:** This schema is now fixed for this record.

2. **Quote Acceptance:**
   - Customer accepts the quote.
   - System prepares to convert the quote into actionable work.

3. **Task & Job Generation:**
   - Work items (Tasks/Jobs) are generated based on the accepted quote.
   - **Rule:** These items inherit the schema from the source quote (or current configuration for new items), ensuring continuity.

4. **Execution:**
   - User completes tasks and jobs.
   - **Rule:** Even if the administrator changes company-wide settings during this phase, the active work items remain unchanged.

## Configuration Impact
Configuration changes are forward-looking only. They influence records created *after* the change but never retroactively alter the structure of work already in progress.

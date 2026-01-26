# BusinessApp — Job Lifecycle Flow

## Job Creation Sources
Jobs enter the system through two primary paths, each with specific schema inheritance rules:

1. **From Accepted Quote:**
   - The job inherits the schema and data directly from the originating quote.
   - This ensures the job matches exactly what was agreed upon, even if the Business Type configuration has changed since the quote was issued.

2. **Direct Creation:**
   - The job captures the Business Type configuration active at the moment of creation.
   - The schema is locked immediately upon initialization.

3. **Manual Association (Post-Creation):**
   - Jobs created directly can be manually linked to an existing Quote and Customer.
   - This allows for "retroactive" association if the workflow was non-linear.
   - Changing the Quote link may update the Customer association to match the Quote's owner.

## Schema Stability
Once a job exists, its structure is immutable regarding configuration changes:

- **No Auto-Updates:** Modifying the global Business Type settings does not trigger updates to existing jobs.
- **Historical Accuracy:** The job remains a faithful record of the requirements as they were defined at its inception.

## Lifecycle Progression
Jobs move through standard operational states. The state determines the mutability of the job.

1. **Pending / Active (Mutable):**
   - Default state upon creation.
   - All fields (Title, Notes, Dynamic Fields) are editable.
   - Line items can be added, removed, or modified.
   - Customer and Quote associations can be updated.

2. **Completed (Immutable):**
   - Marks the job as finished.
   - **Locking Rule:** Once a job is set to 'completed', it becomes **read-only**.
   - No further changes to items, notes, or fields are permitted.
   - This ensures data integrity for downstream invoicing.
   - To correct a mistake, the job must be explicitly reopened (moved back to 'active') by an admin.

3. **Cancelled (Immutable):**
   - Marks the job as abandoned.
   - Follows the same locking rules as 'completed'.

## Job Mutation & Editing
While the Job Schema (custom fields definition) is immutable, the Job content is mutable *while the job is active*:
- **Line Items:** Can be added, removed, or modified (description, quantity, unit, **type**) at any stage before completion.
  - **Item Types:** Items are classified (e.g., 'labor', 'material') to support future financial reporting.
- **Dynamic Fields:** Values for the schema-defined fields can be updated.
- **Notes:** Internal notes can be appended or edited.

This allows the job scope to evolve as work progresses (e.g., adding unexpected materials) without breaking the initial contract of *what* type of job it is.

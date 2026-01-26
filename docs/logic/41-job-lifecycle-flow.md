# BusinessApp — Job Lifecycle Flow

## Job Creation Sources
Jobs enter the system primarily through the Quote-to-Job workflow, enforced by the user interface to ensure data consistency.

1. **From Accepted Quote (Primary):**
   - **UI Enforcement:** The "Create Job" interface requires selecting a Quote.
   - **Auto-Fill:** Selecting a Quote automatically populates the Job's Title, Customer, and initial Line Items.
   - **Status Propagation:** When a Quote is marked as `Accepted`, the system automatically creates a corresponding Job in the `Planned` status (or configured equivalent).
   - **Schema Inheritance:** The job inherits the schema and data directly from the originating quote.

2. **Direct Creation (API / Admin Override):**
   - While the standard UI enforces Quote selection, the API and repository layer support direct job creation.
   - This is used for edge cases or legacy data migration.
   - The job captures the Business Type configuration active at the moment of creation.

3. **Duplicate Prevention:**
   - The system checks for existing jobs linked to a Quote before auto-creating a new one to prevent duplicates during status transitions.

## Schema Stability
Once a job exists, its structure is immutable regarding configuration changes:

- **No Auto-Updates:** Modifying the global Business Type settings does not trigger updates to existing jobs.
- **Historical Accuracy:** The job remains a faithful record of the requirements as they were defined at its inception.

## Lifecycle Progression & Unified Workflow
The Job lifecycle is part of a larger Unified Workflow (Quote -> Job -> Invoice). Statuses are configurable via the Admin Settings.

1. **Planned (Default Initial State):**
   - Automatically entered when a Quote is Accepted.
   - Work is scheduled/planned.
   - Fully mutable.

2. **In Progress / On Hold (Active):**
   - Work is underway or temporarily paused.
   - All fields (Title, Notes, Dynamic Fields) are editable.
   - Line items can be added, removed, or modified.

3. **Completed (Immutable):**
   - Marks the job as finished.
   - **Locking Rule:** Once a job is set to 'completed', it becomes **read-only**.
   - **Invoice Trigger:** Completion signals readiness for invoicing.
   - To correct a mistake, the job must be explicitly reopened (moved back to an active status) by an admin.

4. **Cancelled (Immutable):**
   - Marks the job as abandoned.
   - Follows the same locking rules as 'completed'.

## Job Mutation & Editing
While the Job Schema (custom fields definition) is immutable, the Job content is mutable *while the job is active*:
- **Line Items:** Can be added, removed, or modified (description, quantity, unit, **type**) at any stage before completion.
  - **Item Types:** Items are classified (e.g., 'labor', 'material') to support future financial reporting.
- **Dynamic Fields:** Values for the schema-defined fields can be updated.
- **Notes:** Internal notes can be appended or edited.

This allows the job scope to evolve as work progresses (e.g., adding unexpected materials) without breaking the initial contract of *what* type of job it is.

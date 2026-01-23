# BusinessApp — Job Lifecycle Flow

## Job Creation Sources
Jobs enter the system through two primary paths, each with specific schema inheritance rules:

1. **From Accepted Quote:**
   - The job inherits the schema and data directly from the originating quote.
   - This ensures the job matches exactly what was agreed upon, even if the Business Type configuration has changed since the quote was issued.

2. **Direct Creation:**
   - The job captures the Business Type configuration active at the moment of creation.
   - The schema is locked immediately upon initialization.

## Schema Stability
Once a job exists, its structure is immutable regarding configuration changes:

- **No Auto-Updates:** Modifying the global Business Type settings does not trigger updates to existing jobs.
- **Historical Accuracy:** The job remains a faithful record of the requirements as they were defined at its inception.

## Lifecycle Progression
Jobs move through standard operational states (Pending → In Progress → Complete/Cancelled) while maintaining their original field definitions.

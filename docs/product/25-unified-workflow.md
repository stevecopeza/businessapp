# Unified Workflow & Job Creation

## 1. Overview
The BusinessApp lifecycle represents a single engagement with a customer that evolves through three distinct phases: **Quote**, **Job**, and **Invoice**. While these are separate technical entities, the user experience presents them as a unified workflow with a linear progression of statuses.

## 2. Unified Status Workflow
The system uses a configurable workflow that maps statuses to specific entities. The default workflow is defined as follows:

| Stage | Status | Entity | Meaning |
|-------|--------|--------|---------|
| **1. Negotiation** | `New` | Quote | Initial draft created. |
| | `Sent` | Quote | Emailed to customer. |
| | `Edited` | Quote | Modified after feedback. |
| | `Accepted` | Quote | Customer approved. |
| | `Rejected` | Quote | Customer declined (End of flow). |
| **2. Execution** | `Planned` | Job | Work scheduled. |
| | `In Progress` | Job | Work started. |
| | `On Hold` | Job | Work paused. |
| | `Completed` | Job | Work finished. |
| | `Cancelled` | Job | Work cancelled (End of flow). |
| **3. Billing** | `Invoiced` | Invoice | Invoice generated and sent. |
| | `Paid` | Invoice | Payment received. |
| | `Outstanding` | Invoice | Overdue or partial payment. |
| | `Writeoff` | Invoice | Uncollectible. |

### Configuration
These statuses are configurable in the Admin Settings to adapt to different business types (e.g., "Planned" might be "Scheduled" for some).

## 3. Job Creation Rule
To ensure data integrity and workflow continuity:
*   **Quote-First:** A Job must be initiated from a Quote.
*   **Creation UI:** When creating a new Job in the Admin UI, the user selects a **Quote**.
*   **Auto-Fill:** Selecting a Quote automatically populates:
    *   Customer
    *   Title (from Quote Title)
    *   Initial Line Items (if applicable/desired)
*   **Direct Creation:** Direct Job creation (without a Quote) is restricted or hidden in the default view to enforce the workflow, though the API supports it for edge cases.

## 4. Status Linking
*   **Forward Propagation:** Transitioning a Quote to `Accepted` automatically creates a Job in `Planned` status (if configured).
*   **Backward Reflection:** The Quote status remains `Accepted`. The "Job" entity now carries the active status of the engagement.
*   **Billing Trigger:** Transitioning a Job to `Completed` prompts for Invoice generation.

## 5. Technical Implementation
*   **Settings:** Stored in `businessapp_settings_workflow`.
*   **UI:** `JobEditor` component filters input to require Quote selection for new jobs.

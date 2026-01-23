# BusinessApp — Conceptual Data Model

This document defines the core conceptual entities and governing rules that
underpin BusinessApp. It is implementation-agnostic and describes *what the
system represents and guarantees*, not how it is built.

The purpose of this model is to ensure clarity, predictability, and historical
integrity across all business records.

---

## Core Domain Entities
2. Core domain entities include (but may not be limited to):
   - Business
   - Customer
   - Quote
   - Quote Item
   - Attachment
   - Comment
   - Job
   - Task
   - Order

### Business
A Business represents the organisation using BusinessApp. It is the top-level
context for all configuration and operational data.

A Business:
- Owns configuration such as Business Type and operational defaults
- Defines the scope within which all records are created
- Acts as the boundary for schema authority and configuration changes

All records in the system exist within the context of a single Business.

---

### Customer
A Customer represents an external party for whom work is performed.

A Customer:
- Is associated with one or more Quotes
- May have identifying and contact information captured at the time of quoting
- Exists independently of any single quote, but may be referenced by many

Customer details captured on a Quote represent the state of the Customer at that
moment in time and are not assumed to remain synchronised indefinitely.

## Customer Schema and Associations

- Customer schema is defined by Business Type
- Customer schema is snapshotted at creation
- Customers do not retroactively rebind to configuration changes
- Customers may have multiple associated domain entities
- Associated entities snapshot their own schema independently

---

### Quote
A Quote represents a proposed agreement for work between the Business and a
Customer.

A Quote:
- Captures the scope, pricing, and structure of proposed work
- Is created using the Business Type schema active at the time of creation
- Progresses through defined lifecycle states (e.g. draft, sent, accepted)

A Quote acts as a historical record of what was proposed and agreed at a
specific point in time.

## Quote Schema and Snapshot Behaviour

- Quotes use a hybrid schema:
  - Core attributes
  - Business Type–defined fields
- Quote schema is snapshotted at creation time
- Units and line item structure are part of the snapshot
- Existing Quotes do not retroactively change when configuration changes

---

### Quote Item
A Quote Item represents an individual line or component within a Quote.

A Quote Item:
- Belongs to a single Quote
- Describes a discrete piece of work, material, or charge
- Inherits its structure and fields from the parent Quote’s schema

Quote Items exist solely to support and clarify the Quote.

---

### Attachment
An Attachment represents supplementary material associated with a Quote or
related record.

Attachments:
- Provide context such as photos, documents, or reference material
- Are associated with a parent record
- Do not alter the meaning or structure of the record they support

---

### Comment
A Comment represents human discussion or clarification related to a record.

Comments:
- Capture communication, decisions, or explanations
- Are time-bound and contextual
- Do not modify the authoritative data of a record

---

### Job
A Job represents approved work to be executed by the Business.

A Job:
- May be created from an accepted Quote or directly
- Inherits its schema from its source (Quote or Business Type at creation)
- Represents actionable work rather than a proposal

Once created, a Job is a stable record of agreed work.

---

### Task
A Task represents a unit of work required to complete a Job or operational goal.

Tasks:
- Are created within the context of a Job or workflow
- Inherit their schema at creation time
- Represent execution steps rather than contractual agreement

---

### Order
An Order represents procurement or acquisition required to fulfil work.

Orders:
- Are associated with Jobs or Tasks
- Capture requirements as they existed at the time of creation
- Remain stable records for audit and reconciliation purposes

---

## Conceptual Rules

### Field Identity
Each data field within BusinessApp has two distinct properties:

- **Stable Internal Field ID**  
  A unique, immutable identifier used by the system to track the field across
  time and configuration changes.

- **Mutable Display Label**  
  A human-readable label that may be changed by administrators to suit business
  terminology.

Renaming a field affects the display label only and does not alter the field’s
identity or existing data.

---

### Field Lifecycle
Fields exist in one of three lifecycle states:

- **Active** — Visible and available for use in new records  
- **Hidden** — Removed from future use, but historical data is preserved  
- **Deprecated** — Retained for internal or historical reference only

Rules:
- Removing a field hides it rather than deleting it
- Existing data is never automatically deleted
- Re-adding a hidden field reuses the original field ID and restores historical
  data visibility

---

### Business Type as Schema Authority
Business Type defines the authoritative operational schema for a Business.

- Business Type is company-wide
- Only one Business Type may be active at any given time
- Business Type defines the complete set of input fields for Quotes, Jobs,
  Tasks, and Orders

---

### Snapshot Behaviour
To ensure historical accuracy and predictability:

- Quotes, Jobs, Tasks, and Orders capture their field schema at creation time
- Existing records do not automatically change when Business Type configuration
  is modified
- Draft records are treated as existing records and do not auto-update

Once created, a record’s structure is immutable with respect to configuration
changes.

---

### Units and Meaning
Units of measurement are considered an intrinsic part of a field’s semantic
meaning.

Unit change behaviour:
- Default behaviour is to convert existing values
- Administrators must be warned before conversion occurs
- Administrators may opt out of conversion to preserve historical interpretation

No silent or automatic mutation of historical data is permitted.

---

This conceptual data model establishes the foundation for BusinessApp’s behaviour,
ensuring clarity, auditability, and trust across all business records.

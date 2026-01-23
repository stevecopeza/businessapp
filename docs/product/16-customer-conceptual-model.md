# Customer Conceptual Model

## Definition
In BusinessApp, a Customer is a primary domain entity representing the individual or organization receiving services. Unlike a simple contact list, a Customer is a schema-driven record whose structure is defined by the active Business Type configuration at the moment of creation.

## Schema-Driven Nature
A Customer record is not a static collection of standard fields. While it shares common attributes (such as name and contact details), its full definition is governed by the Business Type active in the system.

### Snapshot Integrity
To ensure historical accuracy and data stability, the definition (schema) of a Customer is **snapshotted** at the exact time of creation. 

- **Immutable Definition:** Once a Customer is created, its structural definition is locked.
- **Isolation from Configuration Changes:** If the global Business Type configuration is updated later (e.g., adding or removing fields), existing Customer records remain unchanged. They retain the structure they were born with, ensuring that historical records never break or display incorrect data due to modern configuration updates.

## Relationship to Other Entities
Customers are standalone entities that exist independently of any specific transaction.

- **Independence:** A Customer record can exist without any associated Quotes or Jobs.
- **Association:** During the creation of a Quote or Job, a Customer is **selected** to be the recipient.
- **One-to-Many:** A single Customer entity may be associated with multiple Quotes, Jobs, or other domain records over time, acting as the central anchor for that relationship history.

## Scope and Boundaries

### Non-Goals
The Customer entity in its current form is designed as a robust directory and association point, **not** as a Customer Relationship Management (CRM) automation tool.

The following are explicitly **out of scope**:
- **CRM Automation:** There are no automated workflows, drip campaigns, or engagement tracking.
- **Deduplication:** The system does not automatically merge or detect duplicate customer entries; distinct records are treated as unique entities.
- **Sales Pipelines:** Concepts such as Leads, Opportunities, or Deal Stages are not part of the Customer model.

### Future Direction
While the current model focuses on structural integrity and association, future iterations of BusinessApp may introduce full CRM capabilities. These potential enhancements would layer additional functionality—such as pipeline management and advanced interaction tracking—on top of the existing, stable Customer entity foundation.

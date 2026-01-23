# Quote Conceptual Model

## 1. Definition

The Quote is the authoritative commercial agreement within BusinessApp. It represents a formal offer of services or goods provided to a Customer.

More than just a price list, the Quote serves as the **anchor entity** for the entire workflow. Once a Quote is accepted, it becomes the source of truth for all downstream activities, including the scheduling of Jobs, the assignment of Tasks, and the fulfillment of Orders. It stands as the immutable record of what was promised and agreed upon.

## 2. Schema-Driven Nature

To accommodate diverse industries, the Quote utilizes a **hybrid schema** approach that balances standardization with flexibility.

### Core Attributes
Every Quote, regardless of the business context, possesses a set of universal core attributes. These include fundamental identifiers, commercial totals, status tracking (e.g., Draft, Sent, Accepted), and key dates.

### Business Type Definition
Beyond the core attributes, the structure of a Quote is shaped by the **Business Type**. The active Business Type defines specific fields and data requirements relevant to that industry—whether it be the dimensions of a room for a painter, the vehicle make and model for a mechanic, or the specific service parameters for a landscaper. This ensures that the Quote captures exactly the information needed for the specific work at hand, without burdening the user with irrelevant fields.

## 3. Snapshot Integrity

A foundational principle of the Quote is **historical integrity**.

When a Quote is created, it captures a **snapshot** of the Business Type schema as it exists at that precise moment. This snapshot defines the structure and validation rules for that specific Quote throughout its lifecycle.

### Implications
- **Future-Proofing**: If the Business Type configuration is updated later (e.g., adding new fields or changing labels), these changes apply **only** to new Quotes created after the update.
- **Stability**: Existing Quotes are never retroactively altered by global configuration changes. This ensures that an agreement made in the past remains viewable and valid in its original form, preserving the context in which the commercial agreement was made.

## 4. Relationships

The Quote exists within a web of relationships that define its context and impact:

### Customer
Every Quote belongs to a single **Customer**. This relationship is mandatory, anchoring the commercial offer to a specific recipient.

### Customer-Associated Entities
A Quote may reference specific **Customer-Associated Entities**. For example, a Quote for auto repair will reference a specific vehicle belonging to the Customer; a Quote for home renovation may reference a specific property or room. This allows the Quote to be context-aware, pertaining not just to a person, but to the specific asset or object requiring service.

### Downstream Derivatives
The Quote is the parent record for operational activities. When a Quote transitions to an "Accepted" state, it triggers the creation or definition of:
- **Jobs**: The scheduled work units required to fulfill the Quote.
- **Tasks**: The granular checklist items derived from the line items of the Quote.

## 5. Scope and Boundaries

### Primary Goal
The primary goal of the Quote entity is to capture the commercial offer, secure Customer acceptance, and define the scope of work for operations.

### Non-Goals
To maintain focus and simplicity, the Quote model explicitly excludes:
- **Invoicing and Payments**: While the Quote tracks the agreed amount, the actual processing of invoices and payments is a distinct financial workflow, separate from the definition of the work itself.
- **Complex CRM Automation**: The Quote is an operational document, not a marketing tool. It does not handle lead scoring, drip campaigns, or automated sales pipelines.

### Future Direction
While currently focused on the initial agreement, the Quote model is designed to eventually support versioning (e.g., "Quote v2" after negotiation) and change orders, allowing for the evolution of the agreement without losing the history of the original offer.

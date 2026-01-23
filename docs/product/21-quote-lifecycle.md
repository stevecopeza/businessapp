# Quote Lifecycle

## 1. Overview

The Quote lifecycle defines the journey of a commercial agreement from its initial creation to its final resolution.

A Quote is not a static document; it is a living record that progresses through a series of defined **states**. Each state serves a specific purpose and governs what actions can be taken, ensuring that both the business and the Customer have a clear, shared understanding of the agreement's status at any given time.

## 2. Lifecycle States

The lifecycle consists of six distinct states:

### Draft
The initial state of every Quote. In this phase, the Quote is a work-in-progress visible only to the business.
- **Purpose**: To prepare the scope, pricing, and details without Customer visibility.
- **Allowed Actions**: Full editing of all fields, line items, and Customer details.

### Sent
The Quote has been finalized and shared with the Customer.
- **Purpose**: To present the formal offer for review.
- **Allowed Actions**: The Quote is effectively locked for editing to preserve the integrity of what was sent. The Customer can view the Quote via a secure link.

### Discussing
A state indicating active negotiation or questions from the Customer.
- **Purpose**: To flag Quotes that need attention or clarification before a decision is made.
- **Allowed Actions**: Similar to "Sent," the Quote remains visible to the Customer. This state helps the business prioritize follow-ups.

### Accepted
The Customer has formally agreed to the Quote.
- **Purpose**: To mark the successful conclusion of the sales process and trigger downstream operations (like scheduling Jobs).
- **Allowed Actions**: The Quote is permanently locked. No further changes to scope or price are permitted.

### Rejected
The Customer has declined the offer.
- **Purpose**: To record lost opportunities and close the loop on the specific offer.
- **Allowed Actions**: The Quote is closed. It remains in the system for historical reference but cannot be reactivated.

### Expired
The validity period of the Quote has passed without a decision.
- **Purpose**: To protect the business from honoring outdated pricing or availability.
- **Allowed Actions**: The Quote is considered void. It may be cloned to create a new, updated offer, but the original expired record remains unchanged.

## 3. Behavioural Guarantees

To ensure trust and data integrity, the lifecycle enforces specific rules:

- **Editable vs. Locked**: Quotes are fully editable *only* while in the **Draft** state. Once a Quote is **Sent**, it is locked to ensure that what the business sees matches exactly what the Customer received.
- **Scope Changes**: If a Customer requests changes to a Sent quote (e.g., adding more work), the best practice is to withdraw the current Quote or create a new one. This prevents "silent" edits where the agreement changes without a clear paper trail.
- **Permanent Access**: Even when a Quote is **Rejected** or **Expired**, it is never deleted by the system. It remains accessible as a historical record, allowing the business to review past offers and understand customer history.

## 4. Explicit Scope

The Quote lifecycle focuses strictly on the **agreement** phase of the relationship.

- **Agreement Only**: The lifecycle tracks whether the work was offered and accepted.
- **No Financial Settlement**: The "Accepted" state indicates permission to proceed, *not* that money has changed hands. Invoicing, payments, and financial reconciliation are separate processes that occur *after* the lifecycle of the Quote has concluded.

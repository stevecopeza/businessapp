FILENAME: 12-quote-lifecycle.md

# BusinessApp — Quote Lifecycle (Plain Language)

## Business Type Field Inheritance

Quote input fields are derived from the Business Type configuration active at the time the quote is created. Once created, a quote does not automatically change if the Business Type configuration is later modified. This ensures historical accuracy and auditability of issued quotes.

- Draft: being prepared
- Sent: shared with customer
- Discussing: questions or revisions underway
- Accepted: agreement reached
- Rejected: not proceeding
- Expired: no longer valid

Each state has meaning and intent.



---

## Lifecycle Closure

A quote is considered closed when it is either:
- Accepted and marked complete as a finished job
- Explicitly rejected
- Expired after a configurable period

Closed quotes remain accessible for reference but do not appear in active workflows.


---
### Implementation Clarification
Quotes sent cannot be edited. Any change requires cloning to a new Draft.
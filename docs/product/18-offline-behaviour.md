FILENAME: 18-offline-behaviour.md

# BusinessApp — Offline Behaviour Expectations

- Users can work with poor connectivity
- Drafts are preserved
- No work is silently lost
- Sync happens when possible

Reliability matters more than speed.


---
### Implementation Clarification
Offline sync reuses standard endpoints with idempotency keys.
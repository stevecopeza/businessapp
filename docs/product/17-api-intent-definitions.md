FILENAME: 17-api-intent-definitions.md

# BusinessApp — API Intent Definitions (Conceptual)

The system supports actions such as:
- create quote
- revise quote
- accept quote
- add comment
- upload attachment

Each action maps to a real-world intent.


---
### Implementation Clarification
API is exposed via WP REST, backed by internal PHP domain services.

Currently implemented endpoints:
- `GET /businessapp/v1/health` — basic health check
- `POST /businessapp/v1/quotes` — create draft quote
- `GET /businessapp/v1/quotes` — list existing quotes
- `POST /businessapp/v1/quotes/{id}/send` — move draft quote to sent
- `POSTGET /businessapp/v1/quotes/{id}/accept — mark sent quote as accepted
- POST /businessapp/v1/quotes/{id}/reject — mark sent quote as rejected

## Settings & Configuration (JSON Payload)

```json
{
  "settings": {
    "currency_symbol": "$",
    "tax_rate": 0.10,
    // business_type is established during setup and is immutable.
    "business_type": "gardening" 
  }
}
```

Other actions (revise, send, accept, comments, attachments) are defined conceptually here and will be implemented in subsequent iterations.

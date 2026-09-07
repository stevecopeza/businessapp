# Event and Activity Model

## Audit Trail

All significant actions are logged:

### Logged Events
- Quote created/updated/sent/accepted/rejected
- Job created/completed
- Invoice sent/paid
- Settings changed
- User added/removed
- Data exported

### Log Format
```json
{
  "event": "quote_accepted",
  "user_id": 5,
  "quote_id": 123,
  "timestamp": "2024-02-06T14:30:00Z",
  "ip_address": "192.168.1.1",
  "metadata": {
    "customer_name": "John Smith",
    "total": 12075.00
  }
}
```

### Retention
- Activity logs: 12 months
- Critical events (acceptance, payment): Indefinite

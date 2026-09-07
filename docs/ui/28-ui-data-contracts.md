# UI Data Contracts

## Data Flow

UI components expect data in specific formats from the API.

### Quote Object
```javascript
{
  id: 123,
  customer: {
    id: 45,
    name: "John Smith",
    email: "john@example.com"
  },
  items: [
    {
      id: 1,
      description: "Labor",
      quantity: 10,
      unit: "hr",
      unit_price: 500.00,
      taxable: true,
      line_total: 5000.00
    }
  ],
  subtotal: 5000.00,
  tax_amount: 750.00,
  total: 5750.00,
  status: "sent",
  created_at: "2024-02-06T10:00:00Z"
}
```

### Customer Object
```javascript
{
  id: 45,
  name: "John Smith",
  email: "john@example.com",
  phone: "082-123-4567",
  address: {
    line1: "123 Main St",
    city: "Cape Town",
    postal_code: "8001"
  }
}
```

## Validation Rules

- Email: RFC 5322 compliant
- Phone: International format or local (flexible)
- Currency: 2 decimal places, no symbols in input
- Dates: ISO 8601 format from API, localized display

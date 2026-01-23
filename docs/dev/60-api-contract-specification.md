Defines endpoints, payloads, error envelopes, idempotency.

## Settings Endpoints

### GET /businessapp/v1/settings
Retrieves the current application configuration.

**Response:**
```json
{
  "general": {
    "business_name": "Main Repair Shop",
    "contact_email": "info@mainrepair.com",
    "phone_number": "+27 82 123 4567",
    "currency": "ZAR"
  },
  "quote_settings": {
    "require_acceptance": true,
    "allow_revisions": false,
    "show_expiry": true,
    "default_expiry_days": 14,
    "tax_rate": 15.0
  },
  "templates": [
    { "label": "Service A", "price": 100.00 }
  ],
  "business_type": {
    "type": "Panel Beater",
    "vehicle_fields": ["Make", "Model", "VIN"],
    "default_options": ["Bumper Unit"]
  },
  "data_units": {
    "length": "Millimeters",
    "area": "Square meters",
    "weight": "Kilograms"
  }
}
```

### POST /businessapp/v1/settings
Updates the application configuration.

**Request Body:**
Same structure as the GET response. Partial updates are allowed (keys not present will remain unchanged).

**Response:**
Returns the updated settings object.


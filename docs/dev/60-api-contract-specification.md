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
    // Note: Established during Initial Setup and immutable thereafter.
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

## Job Endpoints

### GET /businessapp/v1/jobs
Lists all jobs.

**Query Parameters:**
- `status` (optional): Filter by job status (e.g., 'active', 'completed').

**Response:**
Array of Job objects.

### POST /businessapp/v1/jobs
Creates a new job.

**Request Body:**
```json
{
  "title": "Job Title",
  "customer_id": 123,
  "quote_id": 456, // Optional
  "notes": "Job notes",
  "start_date": "2023-10-01",
  "items": [
    { 
      "description": "Task 1", 
      "qty": 1, 
      "unit": "hr", 
      "unit_price": 50,
      "type": "labor" // 'labor' | 'material' | 'fee'
    }
  ],
  "dynamic_fields": { ... }
}
```

### GET /businessapp/v1/jobs/:id
Retrieves a specific job.

### POST /businessapp/v1/jobs/:id
Updates an existing job.

**Constraints:**
- If the job status is `completed` or `cancelled`, update attempts will return `409 Conflict` unless the update is changing the status back to `active`.

**Request Body:**
Partial update supported.
```json
{
  "status": "completed",
  "quote_id": 789,
  "customer_id": 456,
  "items": [
    { "description": "Updated Task", "qty": 2, "unit": "hr", "unit_price": 50, "type": "labor" }
  ]
}
```
**Note:** Providing `items` replaces the entire list of items for the job.

## Invoice Endpoints

### GET /businessapp/v1/invoices
Lists all invoices.

**Query Parameters:**
- `status` (optional): Filter by status (draft, sent, paid, void).
- `job_id` (optional): Filter by source job.

**Response:**
Array of Invoice objects.

### POST /businessapp/v1/invoices
Creates a new invoice.

**Request Body:**
```json
{
  "job_id": 123, // Optional, if creating from job
  "customer_id": 456, // Required if job_id not provided
  "title": "Invoice #1001",
  "items": [ ... ], // Optional override of job items
  "notes": "Thank you for your business"
}
```

### GET /businessapp/v1/invoices/:id
Retrieves a specific invoice.

### POST /businessapp/v1/invoices/:id
Updates an invoice.

**Constraints:**
- Only `draft` invoices can be fully edited.
- `sent` invoices allow only status updates (to `paid` or `void`) or must be reverted to `draft` (if not paid).

**Request Body:**
```json
{
  "status": "sent", // Triggers 'sent' logic (locking)
  "items": [ ... ] // Only allowed if status is 'draft'
}
```

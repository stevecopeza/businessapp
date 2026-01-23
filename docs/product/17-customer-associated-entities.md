# Customer-Associated Entities Conceptual Model

## Definition
Customer-Associated Entities are specialized, domain-specific records that belong to a Customer. These entities represent the physical objects or locations that are the subject of the service being provided. Their nature and structure are strictly defined by the active Business Type configuration.

Unlike generic notes or attachments, these are structured, schema-driven records that serve as long-lived assets within a Customer's profile.

## Examples by Business Domain
The type of associated entity varies entirely based on the industry context:

- **Panel Beater / Mechanic:**
  - **Entity:** Vehicle
  - **Attributes:** Make, Model, Registration, VIN, Paint Code.
- **Landscaper / Gardener:**
  - **Entity:** Property or Garden
  - **Attributes:** Address, Access Code, Garden Size (sqm), Soil Type.
- **Renovator / Builder:**
  - **Entity:** Site or Location
  - **Attributes:** Site Address, Building Type (Residential/Commercial), Access Restrictions.

## Core Rules & Behavior

### 1. Ownership & Multiplicity
- **Ownership:** Every associated entity is strictly owned by a single Customer.
- **One-to-Many:** A single Customer may possess multiple associated entities (e.g., a Customer owning three different Vehicles).

### 2. First-Class Status
These are not simple text fields or metadata tags. Each associated entity is a first-class record in the system with its own identity, lifecycle, and history.

### 3. Schema Snapshotting
Just like Customers and Quotes, Associated Entities adhere to the principle of **Schema Snapshotting**:
- **Creation Time Definition:** The structure (schema) of the entity is captured and locked at the moment it is created.
- **Immutable History:** If the Business Type definition changes in the future (e.g., adding a new field to "Vehicle"), existing entities remain unchanged. They preserve the data structure they were born with, ensuring historical integrity.

## Relationship to Quotes
While Associated Entities exist independently within a Customer's profile, they play a critical role during the quoting process.

- **Selection Context:** During Quote creation, a user may select one of the Customer's associated entities (e.g., "Quote for John's 2015 Ford Ranger").
- **Context Provider:** This selection links the Quote to the specific object of service, providing necessary context without duplicating data.
- **Independence:** The selection of an associated entity provides domain context but does not alter the fundamental schema rules of the Quote itself.

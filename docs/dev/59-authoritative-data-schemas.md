# BusinessApp — Authoritative Data Schemas

## Schema Authority Model
This document defines the hierarchy of schema definitions within BusinessApp.

### 1. Configuration as Authority
The **Business Type** configuration is the authoritative source for the operational schema.
- It defines the complete set of required input fields, data types, and constraints.
- It is not hard-coded; it is a dynamic definition managed by the system configuration.

### 2. Snapshot Authority
Operational records (Quotes, Jobs, Tasks, Orders) act as their own schema authority once created.
- **Capture:** At the moment of creation, a record captures a snapshot of the active Business Type schema.
- **Persistence:** This captured schema becomes the permanent, authoritative definition for that specific record.
- **Independence:** The record's schema is decoupled from the global Business Type configuration immediately after creation.

## Integrity Guarantees
This model ensures:
- **Data Integrity:** Records are never broken by subsequent configuration changes.
- **Auditability:** Every record carries the context of its creation, allowing for accurate historical reproduction.
- **Predictability:** System behavior remains consistent for existing data, regardless of how the business evolves.

## Precedence Rule
In any conflict between the current global configuration and an existing record's internal schema, the **record's internal schema** always takes precedence for that specific instance.

## Technical Implementation Strategy

To realize this model while maintaining performance and manageability, the system employs the following strategies:

### 1. Hybrid Storage Model
Data is persisted using a hybrid approach to balance queryability with flexibility:
- **Core Columns:** Fundamental, cross-business identifiers (e.g., `id`, `customer_id`, `status`, `total_amount`, `title`, `notes`, `timestamps`) remain as first-class SQL columns.
- **Dynamic Fields (JSON):** Business-specific variable data is stored in a `dynamic_fields` JSON column.
- **Snapshot Context (JSON):** The schema context required to interpret the data is stored in a `schema_snapshot` JSON column.

### 2. Minimal Snapshot Strategy
Snapshots are semantic-only, not structural duplicates.
- **Stored:** Field Keys, Units at capture time, Business Type Version/ID.
- **Not Stored:** Field labels, UI hints, validation rules (these are retrieved from the active configuration or fallbacks).
- **Benefit:** Reduces storage overhead while preserving the critical semantic meaning of the data.

### 3. Lazy Migration
Migration to this new model follows a lazy/on-demand pattern:
- **Legacy Records:** Existing records with a `null` snapshot are treated as "Legacy Schema".
- **New Records:** All new records MUST include a valid `schema_snapshot`.
- **No Backfill:** There is no forced background process to generate snapshots for historical data.

## Customer and Associated Entity Schema Authority

The principles of authoritative schema definition apply equally to Customers and their associated domain entities.

- **Business Type Definition:** The active Business Type defines the required schema for Customers and any associated entities (e.g., Vehicles, Properties) at the time of their creation.
- **Schema Snapshotting:** Both Customers and their associated entities independently capture and snapshot their schema upon creation.
- **Immutable History:** Existing Customer records and their associated entities do not change or rebind if the global Business Type configuration is subsequently modified.
- **Consistent Rule Application:** These authority rules are strictly consistent with those governing Quotes, Jobs, Tasks, and Orders, ensuring a unified data integrity model across the entire system.

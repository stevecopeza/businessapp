# Initial Setup and Business Type Selection

## 1. Purpose
This document describes the **Initial System Configuration** phase of the BusinessApp plugin. This is a **one-time, high-impact** setup process that occurs immediately after installation. Decisions made during this phase fundamentally shape the system's behavior, available features, and data structure for the entire lifecycle of the installation.

## 2. Setup Phase Overview
The "Initial System Configuration" is distinct from standard administrative settings.
*   **Timing:** It occurs **before** the system is considered "live" or operational.
*   **State:** The system remains in a "Setup Mode" until this phase is explicitly completed.
*   **Scope:** It establishes the foundational scaffolding upon which all subsequent business data will be built.

## 3. Business Type Selection
The core decision during setup is the selection of a **Business Type**.
*   **Definition:** A Business Type is a pre-packaged configuration set that acts as a starting template and a structural constraint. It is **not** merely a user preference or a workflow switch.
*   **Immutability:** Once the setup wizard is completed, the selected Business Type is **IMMUTABLE**.
*   **Changing Business Type:** Changing the Business Type after setup is not supported. It requires a complete plugin reset (fresh install) or a manual, complex data migration outside the scope of the plugin's standard tooling.

## 4. What Business Type Controls (Explicit List)
The Business Type selection determines the following elements at setup time:
*   **Core Entities:** Which data objects are enabled (e.g., Vehicles for panel beaters, Measurements for tailors).
*   **Lifecycle Paths:** Which status workflows are active for Quotes and Jobs.
*   **Default Schemas:** The initial set of custom fields and data structures installed into the database.
*   **Mandatory Fields:** Which data points are required to be present at "Go Live".
*   **UI Defaults:** Initial label terminology, tab organization, and screen layouts.
*   **Integration Assumptions:** Default settings for expected third-party connections.
*   **Scaffolding:** It lays the initial groundwork; it does not control runtime switching logic.

## 5. What Business Type Does NOT Control (Hard Boundaries)
The Business Type **NEVER** controls or overrides:
*   **Domain Invariants:** Fundamental business rules that must always hold true (e.g., "A quote must have a customer").
*   **Permission System:** User roles and capabilities are standard across all types.
*   **Data Retention:** Rules for how long data is kept or when it is deleted.
*   **Schema Editability:** The ability for admins to further customize schemas after installation (though defaults are provided, they remain editable).
*   **Upgrade Behavior:** How the plugin handles software updates.
*   **Integration Logic:** The actual code execution of integrations (only the defaults are set).

## 6. Business Type Catalogue
The system is designed to be extensible. The following list serves as **examples** of supported types and is not exhaustive:
*   **Gardening:** Optimized for recurring services, seasonal schedules, and simple materials.
*   **Panel Beater:** Optimized for vehicle tracking, insurance claims, and visual damage documentation.
*   **Renovator:** Optimized for project-based work, milestone payments, and complex variations.
*   **House Painter:** Optimized for surface area calculations, paint specifications, and scheduling.

**Note:** New Business Types may be added in future updates.

## 7. Default Schema Behavior
When a Business Type is selected, it installs a set of **Default Schemas**.
*   **Editable:** These schemas are fully editable by the administrator after installation.
*   **System Defaults:** They are flagged as "System Defaults" to distinguish them from user-created fields.
*   **Customization:** It is expected that businesses will customize these defaults to fit their specific niche.
*   **Warning:** While customization is encouraged, admins are warned that removing core fields provided by the default schema may affect specific features designed to use them (though system invariants will prevent breakage).

## 8. Setup Flow (Linear, Ordered)
The required sequence for a successful setup is:
1.  **Plugin Installed:** The plugin is activated in WordPress.
2.  **Setup Wizard Initiated:** The admin launches the configuration tool (abstracted mechanism).
3.  **Business Type Selected:** The admin chooses the most appropriate type from the catalogue.
4.  **Default Schemas Installed:** The system applies the database structures for the chosen type.
5.  **Required Fields Enforced:** The system validates that necessary configuration data is present.
6.  **Optional Schema Customization:** The admin reviews and adjusts the defaults if needed.
7.  **Explicit "Go Live" Action:** The admin confirms setup is complete, transitioning the system to "Live" mode.

## 9. Lock-In Moment
**CRITICAL WARNING:** The Business Type becomes **immutable** immediately after the "Setup Wizard" is completed.
Once the "Go Live" action is confirmed, the structural foundation is locked. There is no "Undo" for this action. Proceeding requires a deliberate acknowledgement of this constraint.

## 10. Go Live State
*   **Definition:** "Go Live" is the transition point where the system switches from "Setup Mode" to "Operational Mode".
*   **Pre-Go Live:** Warnings about missing configuration may exist; data entry may be restricted.
*   **Post-Go Live:** Domain invariants and lifecycle rules are strictly enforced. The system is ready for real-world business transactions.

## 11. Incomplete Setup Behavior
If the setup process is interrupted or not completed:
*   **Operation:** The system may allow partial operation, but strictly "Setup" related pages will remain prominent.
*   **Warnings:** Persistent, visible warnings will alert the admin that the system is not yet "Live".
*   **No Silent Failures:** The system will not fail silently; missing configuration will result in clear, blocking messages or disabled features where appropriate.
*   **No Hard Blocks:** Unless a critical invariant is violated, the admin can generally navigate the admin interface to rectify the missing setup steps.

## 12. Audience & Tone
This documentation is intended for **Business Owners** and **System Administrators** who may have limited technical expertise.
*   **Clarity:** Use plain language. Avoid developer jargon (e.g., "polymorphism", "database migration") unless absolutely necessary.
*   **Explicitness:** Be direct about the consequences of the setup choices.
*   **Constraint:** Clearly articulate what can and cannot be changed later.

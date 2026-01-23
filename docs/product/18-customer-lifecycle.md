# Customer Lifecycle

## Overview
This document outlines the lifecycle of a Customer record within BusinessApp. The approach is designed to be simple, robust, and focused on preserving historical data integrity while allowing for basic management.

## Lifecycle States
For the initial version of BusinessApp, a Customer exists in one of two primary states:

### 1. Active
- **Definition:** The default state for all newly created customers.
- **Behavior:** Active customers are fully visible in the directory and can be selected for new Quotes, Jobs, and other activities.
- **Availability:** They appear in all search results and selection lists.

### 2. Archived
- **Definition:** A state for customers who are no longer active or relevant.
- **Behavior:** Archived customers are hidden from standard selection lists to keep the workspace clean.
- **Restoration:** They can be un-archived if they return for future business.

## Core Rules

### No Hard Deletes
To ensure that business history remains complete and accurate, Customers are **never permanently deleted** from the system. 
- Even if a customer is "removed" by a user, the system simply moves them to the **Archived** state.
- This guarantees that any historical Quotes, Jobs, or Invoices associated with that customer remain intact and referenceable forever.

### Archiving Effects
- **New Work:** An Archived customer cannot be selected for *new* Quotes or Jobs until they are reactivated.
- **Historical Data:** Archiving a customer has **zero impact** on their past records. All previous Quotes and interactions remain visible and unchanged.

## Creation Paths
Customers can enter the system through two distinct pathways:

### 1. Independent Creation
- A user can navigate to the Customer directory and click "Create Customer."
- This allows for setting up a profile and adding details (like vehicles or properties) *before* any work begins.

### 2. Inline Creation
- A user can create a new Customer *during* the Quote creation process.
- If a quote is being prepared for a new client, the system allows the user to define that client on the spot, without leaving the workflow. This new record is immediately saved as a standard, reusable Customer.

## Scope and Future Direction
### Current Scope (v1)
The current lifecycle is intentionally minimal. It focuses purely on the binary state of **Active** vs. **Archived** to support operational needs without complexity.

### Future Scope (Out of Scope for v1)
Advanced CRM-style lifecycle management is **not** part of the current release. Future updates may introduce states such as:
- **Lead / Prospect:** Potential clients who haven't yet transacted.
- **Churned:** Former clients tracked for re-engagement.
- **VIP / Key Account:** Special status for high-value clients.

For now, the system remains streamlined: a customer is either ready for business (Active) or filed away (Archived).

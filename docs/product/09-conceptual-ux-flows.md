# BusinessApp — Conceptual UX Flows

- Create quote → Add items → Review → Send
- View quote → Comment → Accept
- Accepted quote → Scope change → New quote
- **Settings Configuration** → Select Tab → Edit Fields → Save Changes

Each flow is short, intentional, and interruption-tolerant.

## Inline Customer Creation During Quote Flow

This flow allows a user to define a new customer recipient without abandoning the work-in-progress quote.

1.  **Initiation:** The user begins the "Create Quote" process.
2.  **Selection Prompt:** The system asks the user to select the recipient from the existing Customer directory.
3.  **New Entry Option:** If the desired recipient does not exist, the user selects the "Add New Customer" option.
4.  **Creation:** The user defines the new Customer entity (and any necessary associated entities) in a dedicated step.
5.  **Return:** Upon completion, the user is seamlessly returned to the original "Create Quote" flow.
6.  **Pre-Selection:** The newly created Customer is automatically selected as the recipient.
7.  **Continuity:** All context of the draft quote is preserved, allowing the user to proceed immediately to item entry and review.

## Quote Creation Flow (Customer and Context Selection)

This flow outlines the end-to-end journey of creating a quote with a focus on recipient context.

- **Initiation:** The user initiates the Quote creation process.
- **Recipient Selection:** The user selects an existing Customer from the directory or chooses to create a new one inline.
- **Context Selection:** Once the Customer is identified, the user selects relevant Customer-Associated Entities (e.g., specific vehicles or locations) where applicable to the work.
- **Definition:** The user proceeds to define line items, scope, and pricing details.
- **Review:** The user reviews the complete Quote structure before finalizing.
- **Preservation:** Throughout any inline creation steps (Customer or Associated Entities), the Quote context is preserved, and the user is returned to the in-progress Quote seamlessly.

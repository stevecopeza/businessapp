# Quote Line Items Conceptual Model

## 1. Purpose

Quote Line Items serve as the detailed breakdown of a Quote. They translate the high-level commercial agreement into specific, actionable components.

Their primary purpose is twofold:
- **Defining Scope**: They clearly articulate *what* work will be done or *what* goods will be supplied, ensuring mutual understanding between the business and the Customer.
- **Explaining Pricing**: They provide transparency into how the total cost is calculated, building trust by showing the value of individual services or materials.

## 2. Structural Model

While the high-level Quote entity varies significantly between industries (a mechanic vs. a landscaper), the **Line Item** structure remains remarkably consistent across all Business Types.

Every Line Item consists of these fundamental elements:
- **Description**: A clear narrative text explaining the specific service or item (e.g., "Replace Brake Pads" or "Mow Front Lawn").
- **Quantity**: The amount of the service or item provided.
- **Unit**: The measure of that quantity (e.g., "Hours," "Parts," "Square Meters," "Flat Rate").
- **Rate / Price**: The cost per single unit.
- **Line Total**: The calculated subtotal for that specific line (Quantity × Rate).

This standardized structure ensures that any Quote, regardless of complexity, can be read and understood in a familiar format.

## 3. Business Type Influence

Although the structure is consistent, the **Business Type** influences how these fields are populated by default to streamline the user's workflow.

- **Default Units**: A "Panel Beater" Business Type might default to "Hours" and "Parts" as standard units, while a "Pool Cleaner" might default to "Visits" or "Chemicals."
- **Templates & Conventions**: The Business Type may suggest common descriptions or standard rates for frequent tasks.

Crucially, these are merely **defaults** applied at the moment of creation. The user always retains the flexibility to override these defaults for any specific Line Item to fit the unique needs of a job.

## 4. Snapshot Behaviour

Line Items are integral components of the Quote and share its **snapshot integrity**.

When a Quote is created, the Line Items—including their descriptions, units, and rates—are frozen as part of that specific agreement.
- **Historical Stability**: If the Business Type defaults are later changed (e.g., the standard rate for "Labor" is increased), existing Quotes remain untouched.
- **Context Preservation**: The Line Items on a historical Quote will always reflect the pricing and scope descriptions as they were presented to the Customer at that time, ensuring an accurate record of the original agreement.

## 5. Explicit Scope

It is important to define what Line Items are *not*:

- **Not Inventory Records**: Line Items do not track stock levels or trigger reordering. They are purely descriptive of the offer.
- **Not Billing Records**: While they inform the total amount, Line Items themselves are not invoices. Invoicing is a separate financial process that may or may not mirror the Quote's structure line-for-line.
- **Not Payment Records**: Line Items do not track partial payments or installments.

Line Items exist solely to define the **scope and value** of the proposed work within the context of the Quote.

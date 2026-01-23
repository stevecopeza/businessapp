# BusinessApp — Quote Lifecycle Flow

## Creation and Schema Inheritance
When a quote is initialized (Draft), it captures the field schema defined by the **Business Type** configuration active at that specific moment.

- **Fixed Schema:** The set of input fields is locked at creation.
- **Independence:** Subsequent changes to the Business Type configuration do not affect this quote.
- **Draft Stability:** Draft quotes are treated as existing records; they do not automatically update or rebind to new configurations.

## Lifecycle Progression
The quote progresses through defined states without altering its fundamental schema:

1. **Draft:** The quote is being prepared. Input fields reflect the schema captured at creation.
2. **Sent:** The quote is locked and shared with the customer.
3. **Decision:**
   - **Accepted:** The quote is approved.
   - **Rejected:** The quote is declined.
   - **Expired:** The validity period has elapsed.
4. **Closed:** The quote is archived for reference.

Throughout this entire lifecycle, the quote retains the data structure it inherited upon creation, ensuring historical integrity.

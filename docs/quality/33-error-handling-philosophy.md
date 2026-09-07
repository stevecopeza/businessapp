# Error Handling Philosophy

## Principles

1. **User-Friendly Messages**: Never show raw error codes to users
2. **Actionable Guidance**: Tell users what to do next
3. **Fail Gracefully**: Degrade functionality, don't crash
4. **Log Everything**: All errors logged for debugging
5. **No Silent Failures**: Always notify user of issues

## Error Message Examples

### Good
"Unable to send quote. Check customer's email address or contact support."

### Bad
"Error 500: Internal Server Error"

## Error Categories

- **User Error**: Validation failures, invalid input → Show friendly message
- **System Error**: Database issues, API failures → Log error, show generic message
- **External Error**: Email delivery failed, payment processing issue → Show specific message with retry option

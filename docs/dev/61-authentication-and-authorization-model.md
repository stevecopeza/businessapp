# Authentication and Authorization Model

## 1. Authentication Strategy

BusinessApp leverages WordPress's native authentication system for admin users and implements token-based authentication for customer-facing features.

### Admin Authentication
- **Method**: WordPress session-based authentication
- **Login**: Standard WordPress login (/wp-admin)
- **Session Management**: WordPress handles session cookies
- **Multi-Factor**: Compatible with WordPress MFA plugins

### Customer Authentication (Quote View)
- **Method**: Token-based "magic links" (stateless)
- **No Login Required**: Customers access quotes via secure tokens
- **Token Format**: 64-character cryptographically random string
- **Token Lifetime**: 90 days (configurable)

## 2. Authorization Model

Uses WordPress capability system (see product/36-permissions-and-roles-model.md)

### Capability Checks
All operations verify capabilities before execution:
```php
if (!current_user_can('manage_quotes')) {
    wp_send_json_error(['message' => 'Unauthorized'], 403);
}
```

### API Authorization
REST API endpoints check capabilities via `permission_callback`:
```php
register_rest_route('businessapp/v1', '/quotes', [
    'methods' => 'POST',
    'callback' => 'create_quote',
    'permission_callback' => function() {
        return current_user_can('manage_quotes');
    }
]);
```

## 3. Customer Token System ("Magic Links")

### Token Generation
```php
function businessapp_generate_quote_token($quote_id) {
    $token = bin2hex(random_bytes(32));
    global $wpdb;
    $wpdb->insert('wp_businessapp_quote_tokens', [
        'quote_id' => $quote_id,
        'token' => $token,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+90 days'))
    ]);
    return $token;
}
```

### Token Validation
```php
function businessapp_validate_quote_token($token) {
    global $wpdb;
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT quote_id FROM wp_businessapp_quote_tokens 
         WHERE token = %s AND expires_at > NOW()",
        $token
    ));
    return $result ? $result->quote_id : false;
}
```

## 4. Security Measures

### Rate Limiting
- API endpoints: 60 requests per minute per user
- Token validation: 100 requests per hour per IP
- Failed login attempts: WordPress handles (can use plugins)

### CSRF Protection
- WordPress nonces for form submissions
- REST API nonce verification

### XSS Prevention
- All output escaped via WordPress functions
- Content Security Policy headers

### SQL Injection Prevention
- All queries use `$wpdb->prepare()`
- No raw SQL queries

## 5. Session Management

### Admin Sessions
- WordPress handles session management
- Session timeout: WordPress default (48 hours)
- "Remember Me": WordPress default (14 days)

### Customer Access
- Stateless token-based access
- No sessions required
- Tokens can be revoked by admin


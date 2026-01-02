# SoulMirror Account Service - Integration Guide

**Version:** 1.0.0
**Last Updated:** December 6, 2025
**Audience:** Developers integrating external SoulMirror services

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Authentication Flow](#authentication-flow)
4. [Logout Flow](#logout-flow)
5. [Credit System Integration](#credit-system-integration)
6. [API Reference](#api-reference)
7. [Security Best Practices](#security-best-practices)
8. [Error Handling](#error-handling)
9. [Testing Your Integration](#testing-your-integration)
10. [Code Examples](#code-examples)

---

## 📖 Overview

The SoulMirror Account Service provides centralized authentication and credit management for all SoulMirror holistic services. Your application does NOT need to:

- ❌ Build login/registration pages
- ❌ Manage user passwords
- ❌ Handle OAuth integrations
- ❌ Store credit balances
- ❌ Process credit purchases

Instead, your application will:

- ✅ Redirect users to Account Service for authentication
- ✅ Receive and validate JWT tokens
- ✅ Check credit availability via API
- ✅ Deduct credits via API when services are used
- ✅ Display user information from JWT payload

---

## 🔧 Prerequisites

### 1. Service Registration

Contact the Account Service administrator to register your service with:

- **Service Name:** Display name (e.g., "Palm Reading Service")
- **Service Slug:** Unique identifier (e.g., "palm-reading") - used in API calls
- **Callback URL:** Where users return after authentication (e.g., `https://palm-reading.com/auth/callback`)
- **Service Type:** "specific" (for service-specific credits) or uses "universal" credits

### 2. Technical Requirements

Your application needs:

- **HTTPS enabled** (required for secure token transmission)
- **Ability to make HTTP requests** (to validate tokens and call API)
- **Session/cookie management** (to store JWT tokens securely)
- **URL parameter handling** (to receive tokens from redirects)

### 3. Configuration Values

Once registered, you'll receive:

- **Account Service URL:** Base URL of the Account Service (e.g., `https://account.soulmirror.com`)
- **Service Slug:** Your unique service identifier
- **API Namespace:** `soulmirror/v1` (standard for all services)

---

## 🔐 Authentication Flow

### Complete Login Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         AUTHENTICATION FLOW                          │
└─────────────────────────────────────────────────────────────────────┘

1. User visits your service
   https://palm-reading.com/reading
   │
   ▼
2. Check if user has valid JWT token in session
   │
   ├─ YES ──> Validate token (see step 7) ──> Continue to service
   │
   └─ NO ──> Redirect to Account Service login
              │
              ▼
3. Redirect to Account Service with callback URL
   https://account.soulmirror.com/account/login?redirect_url=https://palm-reading.com/auth/callback
   │
   ▼
4. User authenticates at Account Service
   - Email/password login, OR
   - Google OAuth, OR
   - Facebook OAuth
   │
   ▼
5. Account Service generates JWT token
   Payload: { account_id, email, name, iat, exp }
   Expiration: 24 hours
   │
   ▼
6. Account Service redirects back with token in URL
   https://palm-reading.com/auth/callback?token=eyJ0eXAiOiJKV1QiLCJhbGc...
   │
   ▼
7. Your callback endpoint receives token
   - Extract token from URL parameter
   - Validate token via API (POST /soulmirror/v1/auth/validate)
   - Store token in secure httponly cookie/session
   - Remove token from URL (redirect to clean URL)
   - Redirect user to original requested page
   │
   ▼
8. User authenticated - proceed with service
```

### Step-by-Step Implementation

#### Step 1: Check Authentication Status

When a user accesses your application, check if they have a valid JWT token:

```php
// Example: PHP implementation
function is_user_authenticated() {
    // Check if JWT token exists in session/cookie
    if (!isset($_SESSION['smas_jwt_token'])) {
        return false;
    }

    $token = $_SESSION['smas_jwt_token'];

    // Optionally: Check token expiration locally (decode JWT)
    // For security, always validate with Account Service API

    return true;
}
```

#### Step 2: Redirect to Account Service Login

If user is not authenticated, redirect them to the Account Service:

```php
function redirect_to_account_service_login() {
    // Your callback URL (where user returns after authentication)
    $callback_url = 'https://your-service.com/auth/callback';

    // Account Service login URL
    $account_service_url = 'https://account.soulmirror.com/account/login';

    // Build redirect URL with callback parameter
    $redirect_url = $account_service_url . '?redirect_url=' . urlencode($callback_url);

    // Optional: Store original requested page to redirect after auth
    $_SESSION['smas_original_page'] = $_SERVER['REQUEST_URI'];

    // Redirect user
    header('Location: ' . $redirect_url);
    exit;
}
```

#### Step 3: Handle Callback with JWT Token

Create a callback endpoint that receives the JWT token:

```php
// Route: /auth/callback
function handle_auth_callback() {
    // 1. Extract token from URL parameter
    if (!isset($_GET['token'])) {
        // No token provided - redirect to login
        redirect_to_account_service_login();
        return;
    }

    $jwt_token = sanitize_text_field($_GET['token']);

    // 2. Validate token with Account Service API
    $validation_result = validate_jwt_token($jwt_token);

    if (!$validation_result['success']) {
        // Token invalid - show error or redirect to login
        handle_auth_error($validation_result['error']);
        return;
    }

    // 3. Store token in secure session/cookie
    $_SESSION['smas_jwt_token'] = $jwt_token;
    $_SESSION['smas_user_data'] = $validation_result['data'];

    // Optional: Store in httponly cookie for additional security
    setcookie('smas_jwt', $jwt_token, [
        'expires' => time() + 86400, // 24 hours
        'path' => '/',
        'domain' => '.your-service.com',
        'secure' => true, // HTTPS only
        'httponly' => true, // Not accessible via JavaScript
        'samesite' => 'Lax'
    ]);

    // 4. Redirect to clean URL (remove token from URL)
    $original_page = $_SESSION['smas_original_page'] ?? '/';
    unset($_SESSION['smas_original_page']);

    header('Location: ' . $original_page);
    exit;
}
```

#### Step 4: Validate JWT Token via API

```php
function validate_jwt_token($token) {
    $account_service_url = 'https://account.soulmirror.com';
    $endpoint = '/wp-json/soulmirror/v1/auth/validate';

    $response = wp_remote_post($account_service_url . $endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([])
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => 'network_error'
        ];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code !== 200 || !$body['success']) {
        return [
            'success' => false,
            'error' => $body['error'] ?? 'validation_failed'
        ];
    }

    return [
        'success' => true,
        'data' => $body['data'] // Contains: account_id, email, name
    ];
}
```

### JWT Token Payload Structure

When you decode the JWT token (or receive data from validation endpoint), you'll get:

```json
{
  "account_id": "usr_abc123def456",
  "email": "user@example.com",
  "name": "John Doe",
  "iat": 1701792000,
  "exp": 1701878400
}
```

**Fields:**
- `account_id` - Unique user identifier (use this for API calls)
- `email` - User's email address
- `name` - User's full name
- `iat` - Issued at timestamp (Unix timestamp)
- `exp` - Expiration timestamp (Unix timestamp, 24 hours from iat)

---

## 🚪 Logout Flow

### Complete Logout Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                           LOGOUT FLOW                                │
└─────────────────────────────────────────────────────────────────────┘

1. User clicks "Logout" in your service
   │
   ▼
2. Clear JWT token from your session/cookies
   - Remove from $_SESSION
   - Delete cookies
   │
   ▼
3. (Optional) Redirect to Account Service logout
   https://account.soulmirror.com/account/logout?redirect_url=https://your-service.com
   │
   ▼
4. Account Service clears its session
   │
   ▼
5. User redirected back to your service (logged out state)
```

### Implementation

#### Option A: Local Logout Only (Recommended for most cases)

Simply clear the JWT token from your application:

```php
function logout_user() {
    // Clear session data
    unset($_SESSION['smas_jwt_token']);
    unset($_SESSION['smas_user_data']);

    // Clear cookie
    setcookie('smas_jwt', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => '.your-service.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Redirect to home page
    header('Location: /');
    exit;
}
```

**Note:** Token remains valid for other SoulMirror services (SSO behavior). User can still access other services without re-authenticating.

#### Option B: Full Account Service Logout (SSO Logout)

Redirect to Account Service to clear the global session:

```php
function logout_user_complete() {
    // Clear local session first
    unset($_SESSION['smas_jwt_token']);
    unset($_SESSION['smas_user_data']);

    // Build Account Service logout URL
    $account_service_url = 'https://account.soulmirror.com/account/logout';
    $return_url = 'https://your-service.com';

    $logout_url = $account_service_url . '?redirect_url=' . urlencode($return_url);

    // Redirect to Account Service logout
    header('Location: ' . $logout_url);
    exit;
}
```

**Note:** This logs the user out of ALL SoulMirror services. Use only if your service requires complete logout.

---

## 💳 Credit System Integration

### Credit System Overview

The Account Service manages two types of credits:

1. **Service-Specific Credits:** Credits for your specific service (e.g., "palm-reading" credits)
2. **Universal Credits:** Credits that work across all SoulMirror services

**Credit Deduction Priority:**
1. Check service-specific credits first
2. If insufficient, check universal credits
3. If still insufficient, return error

### Credit Operations

Your application needs to:

1. **Check Credit Availability** - Before showing service UI
2. **Deduct Credits** - When user consumes service
3. **Handle Insufficient Credits** - Redirect to purchase page

### Step 1: Check Credit Availability

Before allowing user to access your service, check if they have credits:

```php
function check_user_credits($jwt_token, $service_slug) {
    $account_service_url = 'https://account.soulmirror.com';
    $endpoint = '/wp-json/soulmirror/v1/credits/check';

    $response = wp_remote_post($account_service_url . $endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt_token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'service_slug' => $service_slug
        ])
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'has_credits' => false,
            'error' => 'network_error'
        ];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
        return [
            'success' => false,
            'has_credits' => false,
            'error' => $body['error'] ?? 'check_failed'
        ];
    }

    return [
        'success' => true,
        'has_credits' => $body['data']['has_credits'],
        'service_balance' => $body['data']['service_balance'],
        'universal_balance' => $body['data']['universal_balance'],
        'total_available' => $body['data']['total_available']
    ];
}
```

**API Response Example:**

```json
{
  "success": true,
  "data": {
    "has_credits": true,
    "service_balance": 5,
    "universal_balance": 10,
    "total_available": 15
  }
}
```

### Step 2: Deduct Credits When Service is Used

When user consumes your service (e.g., completes a palm reading), deduct 1 credit:

```php
function deduct_user_credit($jwt_token, $service_slug, $idempotency_key) {
    $account_service_url = 'https://account.soulmirror.com';
    $endpoint = '/wp-json/soulmirror/v1/credits/deduct';

    $response = wp_remote_post($account_service_url . $endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt_token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'service_slug' => $service_slug,
            'idempotency_key' => $idempotency_key
        ])
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'error' => 'network_error'
        ];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status_code = wp_remote_retrieve_response_code($response);

    // 409 = Duplicate transaction (idempotency key already used)
    if ($status_code === 409) {
        return [
            'success' => true,
            'duplicate' => true,
            'transaction' => $body['data']
        ];
    }

    if ($status_code !== 200) {
        return [
            'success' => false,
            'error' => $body['error'] ?? 'deduction_failed'
        ];
    }

    return [
        'success' => true,
        'duplicate' => false,
        'transaction' => $body['data']
    ];
}
```

**CRITICAL: Idempotency Keys**

Always provide a unique idempotency key to prevent duplicate charges if request is retried:

```php
// Generate idempotency key format: {service}_{user_id}_{session_id}_{timestamp}
function generate_idempotency_key($service_slug, $account_id, $session_id) {
    return sprintf(
        '%s_%s_%s_%d',
        $service_slug,
        $account_id,
        $session_id,
        time()
    );
}

// Example usage:
$idempotency_key = generate_idempotency_key(
    'palm-reading',
    $user_data['account_id'],
    session_id()
);
```

**API Response Example (Success):**

```json
{
  "success": true,
  "data": {
    "transaction_id": "txn_abc123def456",
    "account_id": "usr_xyz789",
    "service_slug": "palm-reading",
    "amount": -1,
    "balance_after": 4,
    "timestamp": "2025-12-06 14:30:00"
  }
}
```

### Step 3: Handle Insufficient Credits

When user doesn't have credits, redirect them to purchase page:

```php
function handle_insufficient_credits() {
    $account_service_url = 'https://account.soulmirror.com';
    $service_slug = 'palm-reading'; // Your service slug

    // Build purchase URL (Account Service shop page filtered by service)
    $purchase_url = $account_service_url . '/shop?service=' . $service_slug;

    // Optional: Add return URL to redirect back after purchase
    $return_url = 'https://your-service.com/reading';
    $purchase_url .= '&return_url=' . urlencode($return_url);

    // Redirect to purchase page
    header('Location: ' . $purchase_url);
    exit;
}
```

### Complete Credit Flow Example

```php
// Complete flow: Check credits, use service, deduct credit
function provide_service() {
    // 1. Get JWT token from session
    $jwt_token = $_SESSION['smas_jwt_token'] ?? null;
    if (!$jwt_token) {
        redirect_to_account_service_login();
        return;
    }

    // 2. Check if user has credits
    $service_slug = 'palm-reading';
    $credit_check = check_user_credits($jwt_token, $service_slug);

    if (!$credit_check['success'] || !$credit_check['has_credits']) {
        // No credits - redirect to purchase
        handle_insufficient_credits();
        return;
    }

    // 3. User has credits - provide the service
    $service_result = perform_palm_reading(); // Your service logic

    // 4. Deduct credit AFTER successful service delivery
    $account_id = $_SESSION['smas_user_data']['account_id'];
    $session_id = session_id();
    $idempotency_key = generate_idempotency_key($service_slug, $account_id, $session_id);

    $deduction_result = deduct_user_credit($jwt_token, $service_slug, $idempotency_key);

    if (!$deduction_result['success']) {
        // Log error but don't block user (service already delivered)
        error_log('Credit deduction failed: ' . print_r($deduction_result, true));
    }

    // 5. Show service result to user
    display_service_result($service_result);
}
```

---

## 📡 API Reference

### Base Configuration

```php
// Account Service Configuration
const ACCOUNT_SERVICE_URL = 'https://account.soulmirror.com';
const API_NAMESPACE = 'soulmirror/v1';
const SERVICE_SLUG = 'your-service-slug'; // Provided during registration
```

### Authentication Header

All API requests require JWT token in Authorization header:

```
Authorization: Bearer {jwt_token}
```

### Endpoint 1: Validate JWT Token

**Purpose:** Validate JWT token and get user data

**Endpoint:** `POST /wp-json/soulmirror/v1/auth/validate`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Request Body:**
```json
{}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "account_id": "usr_abc123",
    "email": "user@example.com",
    "name": "John Doe",
    "valid": true
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "error": {
    "code": "invalid_token",
    "message": "JWT token is invalid or expired"
  }
}
```

### Endpoint 2: Get User Info

**Purpose:** Get user account information and all credit balances

**Endpoint:** `GET /wp-json/soulmirror/v1/user/info`

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "account_id": "usr_abc123",
    "email": "user@example.com",
    "name": "John Doe",
    "credits": [
      {
        "service_slug": "palm-reading",
        "service_name": "Palm Reading Service",
        "balance": 5
      },
      {
        "service_slug": "universal",
        "service_name": "Universal Credits",
        "balance": 10
      }
    ]
  }
}
```

### Endpoint 3: Check Credits

**Purpose:** Check if user has credits for specific service

**Endpoint:** `POST /wp-json/soulmirror/v1/credits/check`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "service_slug": "palm-reading"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "has_credits": true,
    "service_balance": 5,
    "universal_balance": 10,
    "total_available": 15
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "error": {
    "code": "missing_parameter",
    "message": "service_slug is required"
  }
}
```

### Endpoint 4: Deduct Credit

**Purpose:** Deduct 1 credit from user's balance

**Endpoint:** `POST /wp-json/soulmirror/v1/credits/deduct`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "service_slug": "palm-reading",
  "idempotency_key": "palm-reading_usr_abc123_sess_xyz789_1701792000"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "transaction_id": "txn_abc123def456",
    "account_id": "usr_abc123",
    "service_slug": "palm-reading",
    "amount": -1,
    "balance_after": 4,
    "timestamp": "2025-12-06 14:30:00"
  }
}
```

**Duplicate Transaction (409):**
```json
{
  "success": false,
  "error": {
    "code": "duplicate_transaction",
    "message": "This transaction has already been processed"
  },
  "data": {
    "transaction_id": "txn_abc123def456",
    "original_timestamp": "2025-12-06 14:30:00"
  }
}
```

**Insufficient Credits (400):**
```json
{
  "success": false,
  "error": {
    "code": "insufficient_credits",
    "message": "User does not have enough credits for this service"
  }
}
```

### Rate Limiting

All API endpoints are rate-limited:

- **100 requests per minute** per JWT token
- Returns HTTP 429 when limit exceeded

**Rate Limit Response (429):**
```json
{
  "success": false,
  "error": {
    "code": "rate_limit_exceeded",
    "message": "Too many requests. Please try again later."
  }
}
```

---

## 🔒 Security Best Practices

### 1. Token Storage

**DO:**
- ✅ Store JWT tokens in httponly cookies (not accessible via JavaScript)
- ✅ Store tokens in server-side sessions
- ✅ Use secure flag for cookies (HTTPS only)
- ✅ Set appropriate cookie expiration (24 hours max)

**DON'T:**
- ❌ Store tokens in localStorage or sessionStorage (XSS vulnerable)
- ❌ Store tokens in regular cookies without httponly flag
- ❌ Keep tokens in URL after initial callback
- ❌ Expose tokens in client-side JavaScript

### 2. Token Transmission

**DO:**
- ✅ Use HTTPS for all communications
- ✅ Remove token from URL immediately after callback
- ✅ Validate token on every protected request
- ✅ Use Authorization header for API calls

**DON'T:**
- ❌ Send tokens over HTTP
- ❌ Keep tokens visible in browser URL
- ❌ Embed tokens in client-side code
- ❌ Log tokens in error messages

### 3. Token Validation

**DO:**
- ✅ Validate token with Account Service API on first use
- ✅ Check token expiration before making API calls
- ✅ Handle expired tokens gracefully (re-authenticate)
- ✅ Implement token refresh mechanism if needed

**DON'T:**
- ❌ Trust token without validation
- ❌ Use expired tokens
- ❌ Skip signature verification
- ❌ Decode token client-side for authorization decisions

### 4. Idempotency Keys

**DO:**
- ✅ Generate unique keys per transaction
- ✅ Include timestamp in key format
- ✅ Store keys to prevent duplicate submissions
- ✅ Use UUIDs or cryptographically secure random strings

**DON'T:**
- ❌ Reuse idempotency keys
- ❌ Use predictable key patterns
- ❌ Skip idempotency key on credit deductions
- ❌ Generate keys client-side (can be manipulated)

### 5. Error Handling

**DO:**
- ✅ Log authentication errors for debugging
- ✅ Show user-friendly error messages
- ✅ Handle network failures gracefully
- ✅ Implement retry logic for transient errors

**DON'T:**
- ❌ Expose internal error details to users
- ❌ Show raw API error messages
- ❌ Ignore authentication failures
- ❌ Retry indefinitely without backoff

---

## ⚠️ Error Handling

### Common Error Codes

| Error Code | HTTP Status | Meaning | Recommended Action |
|------------|-------------|---------|-------------------|
| `invalid_token` | 401 | JWT signature invalid or malformed | Re-authenticate user |
| `expired_token` | 401 | JWT past expiration time | Re-authenticate user |
| `missing_parameter` | 400 | Required field not provided | Fix request, provide missing field |
| `insufficient_credits` | 400 | User balance too low | Redirect to purchase page |
| `invalid_service` | 400 | Service slug not found | Check service registration |
| `rate_limit_exceeded` | 429 | Too many requests | Implement backoff, retry later |
| `duplicate_transaction` | 409 | Idempotency key already processed | Accept as success, don't retry |

### Error Handling Examples

#### Handle Authentication Errors

```php
function handle_auth_error($error) {
    switch ($error['code'] ?? 'unknown_error') {
        case 'invalid_token':
        case 'expired_token':
            // Token invalid - clear session and re-authenticate
            unset($_SESSION['smas_jwt_token']);
            redirect_to_account_service_login();
            break;

        case 'network_error':
            // Temporary issue - show error page
            show_error_page('Unable to connect to authentication service. Please try again.');
            break;

        default:
            // Unknown error - log and show generic message
            error_log('Auth error: ' . print_r($error, true));
            show_error_page('Authentication failed. Please try again.');
            break;
    }
}
```

#### Handle Credit Errors

```php
function handle_credit_error($error) {
    switch ($error['code'] ?? 'unknown_error') {
        case 'insufficient_credits':
            // No credits - redirect to purchase
            handle_insufficient_credits();
            break;

        case 'duplicate_transaction':
            // Already processed - treat as success
            return true;

        case 'invalid_service':
            // Service not found - contact administrator
            show_error_page('Service configuration error. Please contact support.');
            break;

        case 'rate_limit_exceeded':
            // Too many requests - show retry message
            show_error_page('Too many requests. Please wait a moment and try again.');
            break;

        default:
            // Unknown error - log and show generic message
            error_log('Credit error: ' . print_r($error, true));
            show_error_page('Unable to process request. Please try again.');
            break;
    }
}
```

---

## 🧪 Testing Your Integration

### Testing Checklist

#### Authentication Flow

- [ ] User redirected to Account Service login when not authenticated
- [ ] Callback endpoint receives JWT token correctly
- [ ] Token validated successfully via API
- [ ] Token stored securely in session/cookie
- [ ] Token removed from URL after callback
- [ ] User redirected to original requested page
- [ ] Invalid token triggers re-authentication
- [ ] Expired token triggers re-authentication

#### Logout Flow

- [ ] Local logout clears session/cookies
- [ ] User cannot access protected pages after logout
- [ ] (If using SSO logout) Account Service session cleared

#### Credit System

- [ ] Credit check works before service access
- [ ] Insufficient credits redirects to purchase page
- [ ] Credit deduction works after service delivery
- [ ] Idempotency prevents duplicate deductions
- [ ] Service-specific credits used before universal
- [ ] Universal credits used as fallback
- [ ] Balance displayed correctly to user

#### Error Handling

- [ ] Network errors handled gracefully
- [ ] Invalid token shows appropriate message
- [ ] Expired token re-authenticates user
- [ ] Insufficient credits redirects to shop
- [ ] Rate limiting shows retry message
- [ ] Duplicate transactions handled correctly

### Test User Accounts

Request test accounts from Account Service administrator:

1. **User with Credits:** Test successful flows
2. **User without Credits:** Test insufficient credit handling
3. **User with Expired Token:** Test re-authentication

### Postman Testing

Import the Account Service Postman collection for API testing:

1. Download collection from Account Service administrator
2. Set environment variables:
   - `account_service_url`
   - `jwt_token` (get from authentication flow)
   - `service_slug` (your service identifier)
3. Run test scenarios

---

## 💻 Code Examples

### Complete Integration Example (PHP)

```php
<?php
/**
 * SoulMirror Account Service Integration
 * Complete example for external service integration
 */

class SoulMirrorAccountIntegration {

    private $account_service_url;
    private $service_slug;

    public function __construct($account_service_url, $service_slug) {
        $this->account_service_url = rtrim($account_service_url, '/');
        $this->service_slug = $service_slug;

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if user is authenticated
     */
    public function is_authenticated() {
        return isset($_SESSION['smas_jwt_token']) && !empty($_SESSION['smas_jwt_token']);
    }

    /**
     * Get current user data from session
     */
    public function get_current_user() {
        return $_SESSION['smas_user_data'] ?? null;
    }

    /**
     * Redirect to Account Service login
     */
    public function login($callback_url, $original_page = null) {
        // Store original page to redirect after auth
        if ($original_page) {
            $_SESSION['smas_original_page'] = $original_page;
        }

        $login_url = $this->account_service_url . '/account/login?redirect_url=' . urlencode($callback_url);
        header('Location: ' . $login_url);
        exit;
    }

    /**
     * Handle authentication callback
     */
    public function handle_callback() {
        // Extract token from URL
        if (!isset($_GET['token'])) {
            return [
                'success' => false,
                'error' => 'no_token_provided'
            ];
        }

        $jwt_token = sanitize_text_field($_GET['token']);

        // Validate token
        $validation = $this->validate_token($jwt_token);

        if (!$validation['success']) {
            return $validation;
        }

        // Store token and user data
        $_SESSION['smas_jwt_token'] = $jwt_token;
        $_SESSION['smas_user_data'] = $validation['data'];

        // Store in httponly cookie
        setcookie('smas_jwt', $jwt_token, [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        return [
            'success' => true,
            'redirect' => $_SESSION['smas_original_page'] ?? '/'
        ];
    }

    /**
     * Validate JWT token with Account Service
     */
    public function validate_token($token) {
        $endpoint = $this->account_service_url . '/wp-json/soulmirror/v1/auth/validate';

        $response = $this->make_api_request($endpoint, 'POST', [], $token);

        if (!$response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'data' => $response['data']
        ];
    }

    /**
     * Check if user has credits
     */
    public function check_credits() {
        $token = $_SESSION['smas_jwt_token'] ?? null;
        if (!$token) {
            return [
                'success' => false,
                'error' => 'not_authenticated'
            ];
        }

        $endpoint = $this->account_service_url . '/wp-json/soulmirror/v1/credits/check';

        return $this->make_api_request($endpoint, 'POST', [
            'service_slug' => $this->service_slug
        ], $token);
    }

    /**
     * Deduct 1 credit
     */
    public function deduct_credit($idempotency_key) {
        $token = $_SESSION['smas_jwt_token'] ?? null;
        if (!$token) {
            return [
                'success' => false,
                'error' => 'not_authenticated'
            ];
        }

        $endpoint = $this->account_service_url . '/wp-json/soulmirror/v1/credits/deduct';

        return $this->make_api_request($endpoint, 'POST', [
            'service_slug' => $this->service_slug,
            'idempotency_key' => $idempotency_key
        ], $token);
    }

    /**
     * Logout user
     */
    public function logout($full_logout = false) {
        // Clear session
        unset($_SESSION['smas_jwt_token']);
        unset($_SESSION['smas_user_data']);

        // Clear cookie
        setcookie('smas_jwt', '', [
            'expires' => time() - 3600,
            'path' => '/'
        ]);

        if ($full_logout) {
            // Redirect to Account Service logout
            $logout_url = $this->account_service_url . '/account/logout?redirect_url=' . urlencode('https://your-service.com');
            header('Location: ' . $logout_url);
            exit;
        }
    }

    /**
     * Generate idempotency key
     */
    public function generate_idempotency_key() {
        $user = $this->get_current_user();
        $account_id = $user['account_id'] ?? 'unknown';
        $session_id = session_id();
        $timestamp = time();

        return sprintf('%s_%s_%s_%d', $this->service_slug, $account_id, $session_id, $timestamp);
    }

    /**
     * Make API request to Account Service
     */
    private function make_api_request($endpoint, $method, $body = [], $token = null) {
        $headers = ['Content-Type: application/json'];

        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => json_encode($body),
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            return [
                'success' => false,
                'error' => 'network_error'
            ];
        }

        $data = json_decode($response, true);

        // Check HTTP status code
        $status_code = 200;
        if (isset($http_response_header[0])) {
            preg_match('/\d{3}/', $http_response_header[0], $matches);
            $status_code = intval($matches[0]);
        }

        if ($status_code === 409) {
            // Duplicate transaction - treat as success
            return [
                'success' => true,
                'duplicate' => true,
                'data' => $data['data'] ?? []
            ];
        }

        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => $data['error'] ?? 'api_error'
            ];
        }

        return $data;
    }
}

// Usage example
$smas = new SoulMirrorAccountIntegration(
    'https://account.soulmirror.com',
    'palm-reading'
);

// Protect a page
if (!$smas->is_authenticated()) {
    $smas->login('https://palm-reading.com/auth/callback', $_SERVER['REQUEST_URI']);
}

// Check credits before showing service
$credits = $smas->check_credits();
if (!$credits['success'] || !$credits['data']['has_credits']) {
    header('Location: ' . $smas->account_service_url . '/shop?service=palm-reading');
    exit;
}

// Provide service
// ... your service logic ...

// Deduct credit after service
$idempotency_key = $smas->generate_idempotency_key();
$deduction = $smas->deduct_credit($idempotency_key);

if (!$deduction['success'] && !$deduction['duplicate']) {
    error_log('Credit deduction failed: ' . print_r($deduction, true));
}
```

### Authentication Middleware Example (PHP)

```php
<?php
/**
 * Authentication middleware
 * Protect routes requiring authentication
 */

function require_authentication() {
    global $smas;

    if (!$smas->is_authenticated()) {
        $callback_url = 'https://your-service.com/auth/callback';
        $original_page = $_SERVER['REQUEST_URI'];
        $smas->login($callback_url, $original_page);
    }
}

function require_credits() {
    global $smas;

    require_authentication();

    $credits = $smas->check_credits();

    if (!$credits['success'] || !$credits['data']['has_credits']) {
        header('Location: https://account.soulmirror.com/shop?service=' . urlencode($smas->service_slug));
        exit;
    }
}
```

### JavaScript Integration Example (Frontend)

```javascript
/**
 * SoulMirror Account Service - Frontend Integration
 * For displaying user info and handling logout
 */

class SoulMirrorFrontend {
    constructor() {
        this.userData = null;
        this.init();
    }

    async init() {
        await this.loadUserData();
        this.setupEventListeners();
    }

    async loadUserData() {
        try {
            const response = await fetch('/api/user/info', {
                credentials: 'include' // Include cookies
            });

            if (!response.ok) {
                throw new Error('Failed to load user data');
            }

            this.userData = await response.json();
            this.displayUserInfo();
        } catch (error) {
            console.error('Error loading user data:', error);
        }
    }

    displayUserInfo() {
        if (!this.userData) return;

        // Display user name
        const userNameEl = document.querySelector('.user-name');
        if (userNameEl) {
            userNameEl.textContent = this.userData.name;
        }

        // Display credit balance
        const creditBalanceEl = document.querySelector('.credit-balance');
        if (creditBalanceEl) {
            const serviceCredits = this.userData.credits.find(
                c => c.service_slug === 'palm-reading'
            );
            const universalCredits = this.userData.credits.find(
                c => c.service_slug === 'universal'
            );

            const total = (serviceCredits?.balance || 0) + (universalCredits?.balance || 0);
            creditBalanceEl.textContent = `${total} credits available`;
        }
    }

    setupEventListeners() {
        // Logout button
        const logoutBtn = document.querySelector('#logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }

        // Buy credits button
        const buyCreditsBtn = document.querySelector('#buy-credits-btn');
        if (buyCreditsBtn) {
            buyCreditsBtn.addEventListener('click', () => this.redirectToShop());
        }
    }

    logout() {
        window.location.href = '/logout';
    }

    redirectToShop() {
        const returnUrl = window.location.href;
        window.location.href = `https://account.soulmirror.com/shop?service=palm-reading&return_url=${encodeURIComponent(returnUrl)}`;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    new SoulMirrorFrontend();
});
```

---

## 📞 Support & Contact

### Integration Support

If you encounter issues during integration:

1. **Check this guide** - Review relevant sections
2. **Check API response** - Error codes indicate specific issues
3. **Test with Postman** - Verify API endpoints work independently
4. **Contact administrator** - Email: support@soulmirror.com

### Registration Request

To register your service:

**Email:** integrations@soulmirror.com

**Include:**
- Service name
- Service slug (desired identifier)
- Callback URL
- Service type (specific or universal credits)
- Brief service description

### Documentation Updates

This guide is version 1.0.0. Check for updates at:
`https://account.soulmirror.com/account/api-docs`

---

## 📝 Quick Reference

### URLs to Configure

```php
define('ACCOUNT_SERVICE_URL', 'https://account.soulmirror.com');
define('LOGIN_URL', ACCOUNT_SERVICE_URL . '/account/login');
define('LOGOUT_URL', ACCOUNT_SERVICE_URL . '/account/logout');
define('SHOP_URL', ACCOUNT_SERVICE_URL . '/shop');
define('AUTH_CALLBACK_URL', 'https://your-service.com/auth/callback');
```

### API Endpoints

```
POST /wp-json/soulmirror/v1/auth/validate
GET  /wp-json/soulmirror/v1/user/info
POST /wp-json/soulmirror/v1/credits/check
POST /wp-json/soulmirror/v1/credits/deduct
```

### Required Parameters

**Login Redirect:**
- `redirect_url` - Your callback URL

**Credits Check:**
- `service_slug` - Your service identifier

**Credits Deduct:**
- `service_slug` - Your service identifier
- `idempotency_key` - Unique transaction identifier

### Response Format

**Success:**
```json
{"success": true, "data": {...}}
```

**Error:**
```json
{"success": false, "error": {"code": "...", "message": "..."}}
```

---

**Last Updated:** December 6, 2025
**Version:** 1.0.0
**Maintained by:** SoulMirror Development Team

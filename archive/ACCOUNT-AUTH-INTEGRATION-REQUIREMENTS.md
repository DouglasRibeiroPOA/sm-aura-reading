# Account Service Authentication Integration - Requirements Document

**Generated:** 2025-12-20
**Plugin:** Mystic Palm Reading (SoulMirror)
**Status:** ✅ READY FOR IMPLEMENTATION
**Priority:** High
**Prerequisite for:** Teaser Rebalance & Paid Readings

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Core Objectives](#core-objectives)
3. [User Flow Scenarios](#user-flow-scenarios)
4. [Database Schema Changes](#database-schema-changes)
5. [WordPress Admin Settings](#wordpress-admin-settings)
6. [Authentication Flow Implementation](#authentication-flow-implementation)
7. [Credit System Integration](#credit-system-integration)
8. [UI/UX Components](#uiux-components)
9. [API Integration Details](#api-integration-details)
10. [Security Requirements](#security-requirements)
11. [Files to Modify/Create](#files-to-modifycreate)
12. [Success Criteria](#success-criteria)

---

## 📖 Overview

This feature integrates the SoulMirror Account Service authentication system into the Palm Reading plugin. The integration enables:

- **Single Sign-On (SSO)** across all SoulMirror services
- **Credit-based paid readings** for logged-in users
- **Persistent reading history** tied to user accounts
- **Seamless linking** of free readings to accounts when users log in later
- **100% optional authentication** - free flow remains completely open

### Key Principles

✅ **Free flow stays untouched** - New users can complete the entire quiz → OTP → free teaser reading without logging in
✅ **One free reading per email** - Existing email check logic remains (with enhancements)
✅ **Logged-in users get more** - Unlimited paid readings based on credit availability
✅ **Backward compatibility** - Existing free readings can be linked to accounts retroactively

---

## 🎯 Core Objectives

1. **Optional Login Button** - Display login option throughout the experience (except during OTP flow)
2. **JWT Callback Handler** - Handle redirects from Account Service with JWT tokens
3. **Email → Account Linking** - Automatically link free readings when users log in
4. **Logged-in User Dashboard** - Show "Generate New Reading" or "View My Readings" options
5. **Credit Integration** - Check credits before generating, deduct after success
6. **Session Management** - Persist JWT tokens securely in WordPress sessions/cookies

---

## 🔄 User Flow Scenarios

### Scenario 1: First-Time Visitor (No Login, No Free Reading)

**Flow:**
1. User lands on palm reading page
2. **Email capture page** displayed
3. User enters email → Click Continue
4. **Backend checks:** Email exists in database?
   - ❌ NOT found → Continue to OTP verification
5. OTP sent → User verifies → Continues with quiz
6. Palm photo upload → Quiz questions → AI generates **FREE teaser reading**
7. Reading saved with `email` (no `account_id` yet)

**Database State After:**
```
wp_sm_readings: { email: "user@example.com", account_id: NULL, ... }
wp_sm_leads: { email: "user@example.com", account_id: NULL, ... }
```

---

### Scenario 2A: Returning User with Free Reading (Has account_id, Not Logged In)

**Flow:**
1. User lands on palm reading page
2. **Email capture page** displayed
3. User enters email → Click Continue
4. **Backend checks:** Email exists in database?
   - ✅ FOUND with `account_id` NOT NULL → **User already linked to account**
5. **Redirect to Account Service Login**
   - URL: `{Account Service URL}/account/login?redirect_url={callback_url}`
   - Message: "Looks like you already have an account! Please log in to access your readings."

**Why?**
- If user has `account_id`, they created an account → Must log in to access readings
- Prevents creating duplicate free readings for the same email

---

### Scenario 2B: Returning User with Free Reading (No account_id, Not Logged In)

**Flow:**
1. User lands on palm reading page
2. **Email capture page** displayed
3. User enters email → Click Continue
4. **Backend checks:** Email exists in database?
   - ✅ FOUND with `account_id` IS NULL → **User completed free reading before account integration**
5. **Two options:**
   - **Option A (Recommended):** Redirect to Account Service login with message: "You already have a free reading! Log in to access it, or create an account to get more readings."
   - **Option B:** Show offerings page (current behavior)

**Recommendation:** Option A - Encourage account creation to link their free reading

---

### Scenario 3: Logged-In User Returning to Site

**Flow:**
1. User lands on palm reading page
2. **Backend detects:** Valid JWT token in session/cookie
3. **Skip email page entirely**
4. **Redirect to "Logged-In User Dashboard"** (new page)
5. Dashboard shows:
   - Welcome message: "Welcome back, {Name}!"
   - **Button 1:** "Generate New Reading" (check credits first)
   - **Button 2:** "View My Readings" (future feature - grid with pagination)

**Why Skip Email Page?**
- We already know who they are from JWT (`account_id` + `email`)
- No need to ask for email again

---

### Scenario 4: Logged-In User Generating New Reading

**Flow:**
1. User clicks **"Generate New Reading"** from dashboard
2. **Backend checks credits:**
   - API Call: `POST /soulmirror/v1/credits/check` with `service_slug: "palm-reading"`
   - Response: `{ has_credits: true/false, service_balance: X, universal_balance: Y }`
3. **If has_credits = false:**
   - Redirect to Account Service shop: `{Account Service URL}/shop?service=palm-reading&return_url={current_page}`
4. **If has_credits = true:**
   - Skip email/name/OTP steps (we have this from JWT)
   - **Go directly to palm photo upload page**
   - Continue with quiz questions → Generate AI reading
   - **After successful generation:**
     - API Call: `POST /soulmirror/v1/credits/deduct` with idempotency key
     - Deduct 1 credit from user's balance
5. Display reading to user

**Database State After:**
```
wp_sm_readings: { email: "user@example.com", account_id: "usr_abc123", ... }
```

---

### Scenario 5: User Completes Free Reading, Then Logs In Later

**Flow:**
1. User previously completed free reading **without logging in**
   - Database: `{ email: "user@example.com", account_id: NULL }`
2. User returns to site, clicks **"Login"** button
3. Redirected to Account Service → Completes login → **JWT callback**
4. **Callback handler receives JWT:**
   - Extract: `account_id = "usr_abc123"`, `email = "user@example.com"`
5. **Backend searches database:**
   - Search by `account_id` first → Not found
   - Search by `email` → ✅ **FOUND**
6. **Link the free reading to account:**
   - UPDATE `wp_sm_readings` SET `account_id = "usr_abc123"` WHERE `email = "user@example.com"`
   - UPDATE `wp_sm_leads` SET `account_id = "usr_abc123"` WHERE `email = "user@example.com"`
7. Redirect to dashboard with message: "Welcome! We found your previous reading and linked it to your account."

**Why This Is Important:**
- Users who did a free reading before accounts existed can still access it
- No data loss - seamless migration path

---

## 🗄️ Database Schema Changes

### Table: `wp_sm_readings`

**Add new column:**
```sql
ALTER TABLE wp_sm_readings
ADD COLUMN account_id VARCHAR(100) DEFAULT NULL AFTER lead_id,
ADD INDEX idx_account_id (account_id);
```

**Why?**
- Link readings to Account Service user accounts
- Allow lookup by `account_id` for logged-in users
- `NULL` for free readings done before account integration or by non-logged-in users

---

### Table: `wp_sm_leads`

**Add new column:**
```sql
ALTER TABLE wp_sm_leads
ADD COLUMN account_id VARCHAR(100) DEFAULT NULL AFTER email,
ADD INDEX idx_account_id (account_id);
```

**Why?**
- Consistent data model across tables
- Allow credit-based lead capture for logged-in users

---

### Migration Strategy

**Option A: Automatic migration on plugin activation (Recommended)**
- Check if columns exist
- Add if missing
- No data migration needed (existing rows have `account_id = NULL` by default)

**Option B: Manual migration via WP-CLI**
- Provide command: `wp sm-palmreading migrate-account-schema`

**Recommendation:** Option A - Simpler, no user action required

---

## ⚙️ WordPress Admin Settings

### New Admin Settings Section: "Account Service Integration"

**Location:** WordPress Admin → Palm Reading → Settings → Account Service

**Settings Fields:**

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| **Enable Account Integration** | Checkbox | ✅ Enabled | Turn account service integration on/off |
| **Account Service URL** | Text | `https://account.soulmirror.com` | Base URL of the Account Service |
| **Service Slug** | Text | `palm-reading` | Unique identifier for this service (provided by Account Service admin) |
| **Auth Callback URL** | Text (Read-only) | `{site_url}/palm-reading/auth/callback` | Where users return after authentication (auto-generated) |
| **Login Button Text** | Text | `Login / Sign Up` | Text displayed on login button |
| **Show Login Button** | Multi-checkbox | ☑ Dashboard<br>☑ Teaser Page<br>☐ Quiz Steps | Where to display login button |

**Validation:**
- Account Service URL must be valid HTTPS URL
- Service Slug must be alphanumeric with hyphens only
- Warn if Auth Callback URL is not HTTPS (security risk)

---

## 🔐 Authentication Flow Implementation

### 1. Login Button Implementation

**Where to Display:**
- ✅ **Teaser reading page** - Top right corner or header
- ✅ **Logged-in dashboard** - Logout button instead
- ❌ **OTP verification page** - Do NOT show login button (confusing UX)

**Visual Design:**
- Match existing mystic theme (purple gradient)
- Icon: `<i class="fas fa-user-circle"></i>`
- Desktop: Top-right corner, small button
- Mobile: Hamburger menu or sticky header

**HTML Example:**
```html
<a href="<?php echo esc_url( $login_url ); ?>" class="sm-login-btn">
    <i class="fas fa-user-circle"></i>
    <span>Login / Sign Up</span>
</a>
```

**Login URL Format:**
```
{Account Service URL}/account/login?redirect_url={urlencode(callback_url)}
```

Example:
```
https://account.soulmirror.com/account/login?redirect_url=https%3A%2F%2Fpalm-reading.com%2Fauth%2Fcallback
```

---

### 2. Auth Callback Handler

**Route:** `/palm-reading/auth/callback` (WordPress custom endpoint or query param)

**Implementation Options:**

**Option A: Custom Rewrite Rule (Recommended)**
```php
// Add rewrite rule
add_action('init', function() {
    add_rewrite_rule(
        '^palm-reading/auth/callback/?$',
        'index.php?sm_auth_callback=1',
        'top'
    );
});

// Register query var
add_filter('query_vars', function($vars) {
    $vars[] = 'sm_auth_callback';
    return $vars;
});

// Handle callback
add_action('template_redirect', function() {
    if (get_query_var('sm_auth_callback')) {
        SM_Auth_Handler::handle_callback();
        exit;
    }
});
```

**Option B: Query Parameter**
```
https://palm-reading.com/?sm_auth_callback=1&token=eyJ0eXAiOiJKV1Qi...
```

**Recommendation:** Option A - Cleaner URLs, more professional

---

### 3. Callback Logic

**File:** `includes/class-sm-auth-handler.php` (NEW)

**Method:** `handle_callback()`

**Steps:**

```php
public static function handle_callback() {
    // 1. Extract token from URL parameter
    $jwt_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : null;

    if (!$jwt_token) {
        // No token - redirect to login with error
        wp_redirect(add_query_arg('auth_error', 'no_token', home_url('/palm-reading')));
        exit;
    }

    // 2. Validate token with Account Service API
    $validation = self::validate_jwt_token($jwt_token);

    if (!$validation['success']) {
        // Invalid token - redirect to login with error
        wp_redirect(add_query_arg('auth_error', 'invalid_token', home_url('/palm-reading')));
        exit;
    }

    // 3. Extract user data from validation response
    $account_id = $validation['data']['account_id'];
    $email = $validation['data']['email'];
    $name = $validation['data']['name'];

    // 4. Store JWT token in secure session/cookie
    self::store_jwt_token($jwt_token, $validation['data']);

    // 5. Check if user has existing readings - link them to account
    self::link_existing_readings($account_id, $email);

    // 6. Redirect to logged-in dashboard
    wp_redirect(home_url('/palm-reading/dashboard'));
    exit;
}
```

---

### 4. JWT Token Storage

**Method 1: WordPress Sessions (Recommended)**

**Why?**
- Server-side storage (more secure)
- WordPress-friendly
- Works with caching plugins

**Implementation:**
```php
private static function store_jwt_token($token, $user_data) {
    // Start session if not already started
    if (!session_id()) {
        session_start();
    }

    // Store token and user data
    $_SESSION['sm_jwt_token'] = $token;
    $_SESSION['sm_user_data'] = array(
        'account_id' => $user_data['account_id'],
        'email' => $user_data['email'],
        'name' => $user_data['name'],
        'expires' => time() + 86400 // 24 hours
    );
}

public static function get_current_user() {
    if (!session_id()) {
        session_start();
    }

    // Check if token exists and not expired
    if (isset($_SESSION['sm_user_data']) && $_SESSION['sm_user_data']['expires'] > time()) {
        return $_SESSION['sm_user_data'];
    }

    return null;
}
```

**Method 2: Secure HttpOnly Cookies (Alternative)**

```php
private static function store_jwt_token($token, $user_data) {
    setcookie('sm_jwt_token', $token, array(
        'expires' => time() + 86400, // 24 hours
        'path' => '/',
        'domain' => parse_url(home_url(), PHP_URL_HOST),
        'secure' => is_ssl(), // HTTPS only
        'httponly' => true, // Not accessible via JavaScript
        'samesite' => 'Lax'
    ));
}
```

**Recommendation:** Method 1 (WordPress Sessions) - More control, easier to manage

---

### 5. Link Existing Readings to Account

**File:** `includes/class-sm-auth-handler.php`

**Method:** `link_existing_readings($account_id, $email)`

**Logic:**
```php
private static function link_existing_readings($account_id, $email) {
    global $wpdb;

    // Link readings table
    $readings_updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}sm_readings
             SET account_id = %s
             WHERE email = %s
             AND account_id IS NULL",
            $account_id,
            $email
        )
    );

    // Link leads table
    $leads_updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}sm_leads
             SET account_id = %s
             WHERE email = %s
             AND account_id IS NULL",
            $account_id,
            $email
        )
    );

    // Log success
    if ($readings_updated > 0 || $leads_updated > 0) {
        SM_Logger::log('info', 'Linked existing readings to account', array(
            'account_id' => $account_id,
            'email' => $email,
            'readings_linked' => $readings_updated,
            'leads_linked' => $leads_updated
        ));
    }
}
```

---

### 6. Check if User is Logged In

**File:** `includes/class-sm-auth-handler.php`

**Method:** `is_user_logged_in()`

```php
public static function is_user_logged_in() {
    $user = self::get_current_user();
    return $user !== null;
}

public static function require_login() {
    if (!self::is_user_logged_in()) {
        $callback_url = home_url('/palm-reading/auth/callback');
        $login_url = self::get_login_url($callback_url);
        wp_redirect($login_url);
        exit;
    }
}

public static function get_login_url($callback_url) {
    $account_service_url = get_option('sm_account_service_url', 'https://account.soulmirror.com');
    return add_query_arg('redirect_url', urlencode($callback_url), $account_service_url . '/account/login');
}
```

---

### 7. Logout Implementation

**Route:** `/palm-reading/logout`

**Logic:**
```php
public static function handle_logout() {
    // Clear session
    if (session_id()) {
        $_SESSION = array();
        session_destroy();
    }

    // Clear cookies
    setcookie('sm_jwt_token', '', time() - 3600, '/');

    // Option A: Local logout only (recommended)
    wp_redirect(home_url('/palm-reading'));
    exit;

    // Option B: Full SSO logout (logs out of all SoulMirror services)
    // $account_service_url = get_option('sm_account_service_url');
    // $logout_url = $account_service_url . '/account/logout?redirect_url=' . urlencode(home_url('/palm-reading'));
    // wp_redirect($logout_url);
    // exit;
}
```

**Recommendation:** Option A (local logout) - Simpler, doesn't affect other services

---

## 💳 Credit System Integration

### 1. Check Credits Before Generating Reading

**When:** User clicks "Generate New Reading" (logged-in users only)

**File:** `includes/class-sm-credit-handler.php` (NEW)

**Method:** `check_user_credits($jwt_token)`

```php
public static function check_user_credits($jwt_token) {
    $account_service_url = get_option('sm_account_service_url');
    $service_slug = get_option('sm_service_slug', 'palm-reading');

    $response = wp_remote_post($account_service_url . '/wp-json/soulmirror/v1/credits/check', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $jwt_token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'service_slug' => $service_slug
        ))
    ));

    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'error' => 'network_error',
            'has_credits' => false
        );
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
        return array(
            'success' => false,
            'error' => $body['error'] ?? 'check_failed',
            'has_credits' => false
        );
    }

    return array(
        'success' => true,
        'has_credits' => $body['data']['has_credits'],
        'service_balance' => $body['data']['service_balance'],
        'universal_balance' => $body['data']['universal_balance'],
        'total_available' => $body['data']['total_available']
    );
}
```

---

### 2. Deduct Credit After Successful Reading Generation

**When:** After AI reading is successfully generated and saved

**File:** `includes/class-sm-credit-handler.php`

**Method:** `deduct_credit($jwt_token, $reading_id)`

```php
public static function deduct_credit($jwt_token, $reading_id) {
    $account_service_url = get_option('sm_account_service_url');
    $service_slug = get_option('sm_service_slug', 'palm-reading');

    // Generate idempotency key to prevent duplicate deductions
    $user_data = SM_Auth_Handler::get_current_user();
    $idempotency_key = sprintf(
        '%s_%s_%s_%d',
        $service_slug,
        $user_data['account_id'],
        $reading_id,
        time()
    );

    $response = wp_remote_post($account_service_url . '/wp-json/soulmirror/v1/credits/deduct', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $jwt_token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'service_slug' => $service_slug,
            'idempotency_key' => $idempotency_key
        ))
    ));

    if (is_wp_error($response)) {
        SM_Logger::log('error', 'Credit deduction failed - network error', array(
            'reading_id' => $reading_id,
            'error' => $response->get_error_message()
        ));
        return array('success' => false, 'error' => 'network_error');
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status_code = wp_remote_retrieve_response_code($response);

    // 409 = Duplicate transaction (already deducted) - treat as success
    if ($status_code === 409) {
        SM_Logger::log('warning', 'Credit deduction duplicate transaction', array(
            'reading_id' => $reading_id,
            'idempotency_key' => $idempotency_key
        ));
        return array('success' => true, 'duplicate' => true);
    }

    if ($status_code !== 200) {
        SM_Logger::log('error', 'Credit deduction failed', array(
            'reading_id' => $reading_id,
            'status_code' => $status_code,
            'error' => $body['error'] ?? 'unknown'
        ));
        return array('success' => false, 'error' => $body['error'] ?? 'deduction_failed');
    }

    // Success - log transaction
    SM_Logger::log('info', 'Credit deducted successfully', array(
        'reading_id' => $reading_id,
        'transaction_id' => $body['data']['transaction_id'],
        'balance_after' => $body['data']['balance_after']
    ));

    return array('success' => true, 'data' => $body['data']);
}
```

---

### 3. Handle Insufficient Credits

**When:** `check_user_credits()` returns `has_credits: false`

**Flow:**
1. Show user-friendly message: "You don't have enough credits to generate a new reading."
2. Display current balance: "You have X palm reading credits and Y universal credits."
3. **Redirect to shop:** `{Account Service URL}/shop?service=palm-reading&return_url={current_page}`

**Implementation:**
```php
$credits = SM_Credit_Handler::check_user_credits($jwt_token);

if (!$credits['has_credits']) {
    $account_service_url = get_option('sm_account_service_url');
    $shop_url = add_query_arg(array(
        'service' => 'palm-reading',
        'return_url' => urlencode(home_url('/palm-reading/dashboard'))
    ), $account_service_url . '/shop');

    wp_redirect($shop_url);
    exit;
}
```

---

## 🎨 UI/UX Components

### 1. Login Button Component

**Visual Design:**
- **Desktop:** Small button in top-right corner
  - Icon: User circle icon
  - Text: "Login / Sign Up"
  - Colors: Match mystic theme (purple gradient)
  - Hover effect: Slight lift + shadow

- **Mobile:** Hamburger menu or sticky header
  - Icon only on small screens
  - Full text on larger mobile screens

**CSS Example:**
```css
.sm-login-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: linear-gradient(135deg, #9c7ae7, #6c63ff);
    color: white;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(156, 122, 231, 0.3);
}

.sm-login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(156, 122, 231, 0.4);
}

.sm-login-btn i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .sm-login-btn span {
        display: none; /* Icon only on mobile */
    }
}
```

---

### 2. Logged-In User Dashboard

**Location:** `/palm-reading/dashboard` (new page)

**Layout:**

```
┌─────────────────────────────────────────────────┐
│  Welcome back, [User Name]! ✨                  │
│                                                 │
│  ┌─────────────────────┐  ┌──────────────────┐ │
│  │  📸                 │  │  📚              │ │
│  │  Generate New       │  │  View My         │ │
│  │  Reading            │  │  Readings        │ │
│  │                     │  │                  │ │
│  │  [Get Started →]    │  │  [View All →]    │ │
│  └─────────────────────┘  └──────────────────┘ │
│                                                 │
│  Your Credits: 5 palm readings + 10 universal  │
│  [Buy More Credits]                    [Logout] │
└─────────────────────────────────────────────────┘
```

**HTML Example:**
```html
<div class="sm-dashboard">
    <div class="sm-dashboard-header">
        <h1>Welcome back, <?php echo esc_html($user_name); ?>! ✨</h1>
        <a href="<?php echo esc_url($logout_url); ?>" class="sm-logout-btn">Logout</a>
    </div>

    <div class="sm-dashboard-actions">
        <div class="sm-dashboard-card">
            <div class="card-icon">📸</div>
            <h2>Generate New Reading</h2>
            <p>Get a fresh palm reading based on a new photo and quiz.</p>
            <a href="<?php echo esc_url($new_reading_url); ?>" class="btn btn-primary">
                Get Started →
            </a>
        </div>

        <div class="sm-dashboard-card">
            <div class="card-icon">📚</div>
            <h2>View My Readings</h2>
            <p>Access all your previous palm readings anytime.</p>
            <a href="<?php echo esc_url($my_readings_url); ?>" class="btn btn-secondary">
                View All →
            </a>
        </div>
    </div>

    <div class="sm-dashboard-footer">
        <div class="credits-info">
            Your Credits: <?php echo esc_html($credits_display); ?>
        </div>
        <a href="<?php echo esc_url($shop_url); ?>" class="btn btn-outline">
            Buy More Credits
        </a>
    </div>
</div>
```

---

### 3. My Readings Page (Future Implementation)

**Location:** `/palm-reading/my-readings`

**Features:**
- Grid layout with pagination
- Each reading card shows:
  - Date generated
  - Preview/thumbnail
  - Actions: View | Download | Delete
- Filter/search options
- Responsive design

**Note:** This page is **NOT part of the current requirements** - will be implemented in a future phase. For now, clicking "View My Readings" can show a "Coming Soon" message or redirect to the most recent reading.

---

### 4. Email Check UI Enhancement

**Current:** Email input on first page

**New Behavior:**

**If email found with account_id:**
```
┌─────────────────────────────────────────────────┐
│  ℹ️ Account Found                               │
│                                                 │
│  Looks like you already have an account with   │
│  this email! Please log in to access your      │
│  readings.                                      │
│                                                 │
│  [Login to Continue →]                          │
│                                                 │
│  Not you? [Try a different email]              │
└─────────────────────────────────────────────────┘
```

**If email found without account_id:**
```
┌─────────────────────────────────────────────────┐
│  ℹ️ Free Reading Already Used                   │
│                                                 │
│  You've already completed your free reading    │
│  with this email. Log in or create an account  │
│  to access it and get more readings!           │
│                                                 │
│  [Login / Sign Up →]                            │
│                                                 │
│  Not you? [Try a different email]              │
└─────────────────────────────────────────────────┘
```

---

## 🔌 API Integration Details

### Account Service Endpoints Used

| Endpoint | Method | Purpose | When Used |
|----------|--------|---------|-----------|
| `/wp-json/soulmirror/v1/auth/validate` | POST | Validate JWT token | After login callback |
| `/wp-json/soulmirror/v1/credits/check` | POST | Check credit availability | Before generating new reading (logged-in users) |
| `/wp-json/soulmirror/v1/credits/deduct` | POST | Deduct 1 credit | After successful reading generation (logged-in users) |

---

### 1. Validate JWT Token

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

---

### 2. Check Credits

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

---

### 3. Deduct Credit

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
  "idempotency_key": "palm-reading_usr_abc123_read_xyz789_1703001234"
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
    "timestamp": "2025-12-20 14:30:00"
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
    "original_timestamp": "2025-12-20 14:30:00"
  }
}
```

---

### Idempotency Key Format

**Critical:** Always use unique idempotency keys to prevent duplicate credit deductions

**Format:**
```
{service_slug}_{account_id}_{reading_id}_{timestamp}
```

**Example:**
```
palm-reading_usr_abc123_read_xyz789_1703001234
```

**Implementation:**
```php
$idempotency_key = sprintf(
    '%s_%s_%s_%d',
    get_option('sm_service_slug', 'palm-reading'),
    $user_data['account_id'],
    $reading_id,
    time()
);
```

---

## 🔒 Security Requirements

### 1. JWT Token Storage

**DO:**
✅ Store tokens in server-side sessions (PHP `$_SESSION`)
✅ Store tokens in httponly cookies (not accessible via JavaScript)
✅ Use secure flag for cookies (HTTPS only)
✅ Set appropriate expiration (24 hours max)

**DON'T:**
❌ Store tokens in localStorage or sessionStorage (XSS vulnerable)
❌ Store tokens in regular cookies without httponly flag
❌ Keep tokens in URL after initial callback
❌ Expose tokens in client-side JavaScript

---

### 2. Token Validation

**DO:**
✅ Validate token with Account Service API on first use
✅ Check token expiration before making API calls
✅ Handle expired tokens gracefully (redirect to login)
✅ Clear invalid tokens from session/cookies

**DON'T:**
❌ Trust token without validation
❌ Use expired tokens
❌ Skip signature verification
❌ Decode token client-side for authorization decisions

---

### 3. Email Check Security

**DO:**
✅ Sanitize email input before database queries
✅ Use prepared statements (`$wpdb->prepare`)
✅ Rate limit email check requests (prevent enumeration attacks)
✅ Hash emails before logging (privacy)

**DON'T:**
❌ Expose whether email exists in error messages (privacy leak)
❌ Allow unlimited email check requests
❌ Log raw emails in plain text
❌ Return account_id in client-facing responses

---

### 4. HTTPS Enforcement

**Requirement:** All Account Service integrations **MUST** use HTTPS

**Implementation:**
```php
// Validate Account Service URL in settings
if (strpos($account_service_url, 'https://') !== 0) {
    add_settings_error(
        'sm_account_service_url',
        'invalid_url',
        'Account Service URL must use HTTPS for security.',
        'error'
    );
}
```

---

## 📂 Files to Modify/Create

### New Files to Create

| File | Purpose |
|------|---------|
| `includes/class-sm-auth-handler.php` | Handle JWT authentication, login/logout, session management |
| `includes/class-sm-credit-handler.php` | Credit check, deduction, error handling |
| `templates/dashboard.php` | Logged-in user dashboard template |
| `templates/my-readings.php` | My Readings page (future - placeholder for now) |
| `assets/css/auth.css` | Styling for login button, dashboard, auth pages |
| `assets/js/auth.js` | Client-side auth logic (optional - minimal JS needed) |

---

### Files to Modify

| File | Changes |
|------|---------|
| `includes/class-sm-database.php` | Add `account_id` columns to schema, migration logic |
| `includes/class-sm-rest-controller.php` | Add email check logic, account_id handling |
| `includes/class-sm-lead-capture.php` | Check for existing account_id, redirect to login if needed |
| `includes/class-sm-ai-handler.php` | Add account_id to reading generation, credit deduction |
| `templates/container.php` | Add login button, detect logged-in state |
| `palm-reading-template-teaser.html` | Add login button to teaser page |
| `assets/js/api-integration.js` | Skip email/OTP for logged-in users, credit check before generation |
| `includes/class-sm-settings.php` | Add Account Service settings page |

---

## ✅ Success Criteria

### Phase 1: Authentication Integration

- [ ] Admin settings page for Account Service configuration
- [ ] Login button displayed on dashboard and teaser page
- [ ] JWT callback handler receives and validates tokens
- [ ] Tokens stored securely in WordPress sessions
- [ ] Logged-in users skip email/OTP steps
- [ ] Dashboard page shows "Generate New Reading" and "View My Readings" options
- [ ] Logout functionality clears session and redirects correctly

### Phase 2: Email Check & Account Linking

- [ ] Email check on first page detects existing users
- [ ] Users with account_id are redirected to login
- [ ] Existing free readings are automatically linked when users log in
- [ ] `account_id` column added to `wp_sm_readings` and `wp_sm_leads` tables
- [ ] Database migration runs successfully on plugin activation

### Phase 3: Credit System Integration

- [ ] Credit check API call works before reading generation
- [ ] Users without credits are redirected to shop page
- [ ] Credit deduction API call works after successful generation
- [ ] Idempotency keys prevent duplicate deductions
- [ ] Error handling for credit API failures
- [ ] Credit balance displayed on dashboard

### Phase 4: UX & Design

- [ ] Login button matches existing mystic theme
- [ ] Dashboard page looks polished and professional
- [ ] Email check messages are clear and actionable
- [ ] Mobile-responsive design for all new pages
- [ ] Loading states for API calls
- [ ] User-friendly error messages

### Phase 5: Testing & QA

- [ ] Free user flow unchanged (no regressions)
- [ ] Logged-in user can generate paid readings
- [ ] Credit deduction works correctly
- [ ] Account linking works for existing free readings
- [ ] No duplicate credit charges
- [ ] Token expiration handled gracefully
- [ ] Cross-browser compatibility (Chrome, Firefox, Safari, Edge)
- [ ] Mobile testing (iOS Safari, Android Chrome)

---

## 📝 Implementation Notes

### Phase 1 Priority

**Implement in this order:**
1. Database schema changes (add `account_id` columns)
2. Admin settings page for Account Service configuration
3. JWT callback handler and session management
4. Login button on dashboard/teaser pages
5. Email check enhancement (redirect to login if account_id exists)
6. Logged-in user dashboard page
7. Credit check integration
8. Credit deduction integration

### Testing Strategy

**Local Development:**
1. Use Account Service staging environment (request credentials)
2. Test with test user accounts (with and without credits)
3. Verify token validation works
4. Test account linking with existing free readings

**Staging:**
1. Deploy to staging environment
2. End-to-end testing with real Account Service integration
3. Test credit check/deduction flows
4. Verify no regressions in free user flow

**Production:**
1. Soft launch to 10% of traffic
2. Monitor error logs for authentication failures
3. Track credit deduction success rate
4. Gather user feedback
5. Full rollout after 1 week

---

## 🚀 Future Enhancements (Out of Scope for Phase 1)

These features will be implemented in future phases:

1. **My Readings Page** - Grid view with pagination, search, filters
2. **Reading Deletion** - Allow users to delete old readings
3. **Reading Download** - PDF export of readings
4. **Email Notifications** - Notify users when credits are low
5. **Admin Dashboard** - View credit usage analytics
6. **Subscription Management** - Auto-renewing credit subscriptions (handled by Account Service)

---

**End of Requirements Document**

Last Updated: 2025-12-20

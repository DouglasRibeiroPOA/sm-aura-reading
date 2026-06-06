# Palm Reading vs Aura Reading — Mechanical & Behavioral Differences

> **Purpose:** Document every non-branding difference between the two plugins so Aura Reading can be brought to parity with Palm Reading.
>
> **Source of truth:** Palm Reading (refined, improved, bugs fixed).
> **Target:** Aura Reading (diverged, needs alignment).
>
> Palm Reading: 34,936 total lines across all files
> Aura Reading: 33,552 total lines across all files
>
> **35 differences documented** across 15+ files (8 new findings added in second pass)

---

## Synchronization Rules (Locked 2026-02-12)

This document is the **detailed behavioral baseline/reference**.

- **Tracking source of truth:** `PALM_PARITY_EXECUTION_PLAN.md`
- **Execution policy:** strict Palm parity for mechanics/behavior (no compatibility window)
- **Conflict rule:** if any recommendation here conflicts with locked decisions or the parity plan, follow `PALM_PARITY_EXECUTION_PLAN.md`

### Locked Decisions Applied

1. Adopt Palm token model (`report_magic_token`), remove Aura `SM_Reading_Token` behavior.
2. Match Palm report access gate exactly.
3. Port Palm polling behavior (`check_reading_status`) exactly.
4. Match Palm storage/session wrapper architecture exactly.
5. Match Palm OTP policy exactly.
6. Remove Aura-only behavioral divergences (strict parity).
7. No compatibility window.

### Current Status Snapshot (mirrors parity plan)

- Completed: `#1`, `#3`, `#4`, `#9`, `#10`, `#11`, `#21`, `#28`, `#29`
- In progress/pending: all remaining items

---

## Table of Contents

1. [REST Controller (Start New Reading Flow)](#1-rest-controller--start-new-reading-flow)
2. [REST Controller (DevMode Credit Bypass)](#2-rest-controller--devmode-credit-bypass)
3. [REST Controller (Lead Resolution via Flow Session)](#3-rest-controller--lead-resolution-via-flow-session)
4. [REST Controller (Existing Reading Detection & Error Handling)](#4-rest-controller--existing-reading-detection--error-handling)
5. [REST Controller (Report Token System)](#5-rest-controller--report-token-system)
6. [REST Controller (Report Container Wrapping)](#6-rest-controller--report-container-wrapping)
7. [REST Controller (DevMode Profile Defaults)](#7-rest-controller--devmode-profile-defaults)
8. [REST Controller (OTP Rate Limiting)](#8-rest-controller--otp-rate-limiting)
9. [REST Controller (OTP Lead Resolution by Email)](#9-rest-controller--otp-lead-resolution-by-email)
10. [REST Controller (Job Status Polling — check_reading_status)](#10-rest-controller--job-status-polling--check_reading_status)
11. [REST Controller (Flow Session Reset on Start New)](#11-rest-controller--flow-session-reset-on-start-new)
12. [Frontend JS — Session Storage Architecture](#12-frontend-js--session-storage-architecture)
13. [Frontend JS — Palm Image Fingerprint & Retry System](#13-frontend-js--palm-image-fingerprint--retry-system)
14. [Frontend JS — Start New Reading Client Flow](#14-frontend-js--start-new-reading-client-flow)
15. [Frontend JS — Auth Flow Resume (bootstrapResumeAuthFlow)](#15-frontend-js--auth-flow-resume-bootstrapresumeauthflow)
16. [Frontend JS — OTP Rate Limit Error Display](#16-frontend-js--otp-rate-limit-error-display)
17. [Frontend JS — Reading Result Container Lookup](#17-frontend-js--reading-result-container-lookup)
18. [Frontend JS — Dashboard Share Feature](#18-frontend-js--dashboard-share-feature)
19. [Teaser JS — Scoped Storage (smStorage)](#19-teaser-js--scoped-storage-smstorage)
20. [Teaser JS — Unlock Modal Copy Sections](#20-teaser-js--unlock-modal-copy-sections)
21. [Auth Handler — Return URL Normalization](#21-auth-handler--return-url-normalization)
22. [Database — Schema Integrity Check](#22-database--schema-integrity-check)
23. [Database — Table Access Pattern](#23-database--table-access-pattern)
24. [Main Plugin File — Report Access / Gate Logic](#24-main-plugin-file--report-access--gate-logic)
25. [Main Plugin File — SM_Reading_Token Class](#25-main-plugin-file--sm_reading_token-class)
26. [Settings — Dual Settings Group Registration](#26-settings--dual-settings-group-registration)
27. [Reading Service — Allowed Reading Types](#27-reading-service--allowed-reading-types)
28. [Job Handler — WP-Cron Fallback on Dispatch Failure](#28-job-handler--wp-cron-fallback-on-dispatch-failure)
29. [Job Handler — Duplicate Run Guard](#29-job-handler--duplicate-run-guard)
30. [Flow Session — Cookie Architecture (Single vs Three Cookies)](#30-flow-session--cookie-architecture-single-vs-three-cookies)
31. [Credit Handler — Stale Cache Fallback](#31-credit-handler--stale-cache-fallback)
32. [Credit Handler — DevMode Mock Injection Points](#32-credit-handler--devmode-mock-injection-points)
33. [OTP Handler — Lead Lookup Retry on Race Condition](#33-otp-handler--lead-lookup-retry-on-race-condition)
34. [OTP Handler — Internal Rate Limit Window](#34-otp-handler--internal-rate-limit-window)
35. [OTP Handler — Resend Cooldown Units](#35-otp-handler--resend-cooldown-units)
36. [Summary Matrix](#36-summary-matrix)

---

## 1. REST Controller — Start New Reading Flow

**Files:** `includes/class-sm-rest-controller.php`
**Palm:** 4,434 lines | **Aura:** 4,127 lines

### What Palm Does (Better)

Palm builds a clean redirect URL by stripping stale query parameters from the referer before appending `start_new=1`. It uses a dedicated `build_start_new_target()` private method that:

1. Reads `wp_get_referer()`
2. Parses the URL and strips: `sm_report`, `lead_id`, `lead`, `reading_id`, `token`, `reading_type`, `sm_flow`, `sm_flow_auth`, `start_new`
3. Appends only `start_new=1`
4. Returns a clean URL via `esc_url_raw()`

```php
// Palm — clean redirect URL builder
private function build_start_new_target( $referer, $fallback ) {
    $target = wp_validate_redirect( $referer, $fallback );
    $parts  = wp_parse_url( $target );
    // ... strips stale keys ...
    $query['start_new'] = '1';
    return esc_url_raw( $base . '?' . http_build_query( $query ) );
}
```

### What Aura Does (Simpler, Less Robust)

Aura does a naive redirect — it validates the referer and blindly appends `start_new=1` without stripping old query parameters:

```php
// Aura — naive redirect
$target = wp_validate_redirect( $referer, $fallback );
$target = add_query_arg( 'start_new', '1', $target );
```

This means the redirect URL can accumulate stale params like `sm_report`, `lead_id`, `token` from previous sessions, causing unexpected behavior.

### Action Required

Port `build_start_new_target()` method from Palm to Aura (adapt branding references).

---

## 2. REST Controller — DevMode Credit Bypass

### What Palm Does (Better)

Palm has DevMode credit check bypass in two places:

1. **`handle_start_new_reading`:** If `SM_Dev_Mode::should_mock_credits()` is true, returns success immediately with `devmode: true` flag — skips the Account Service call entirely.
2. **Paid generation flow:** Mocks the entire credit check response with fake balances (`service_balance: 10`) so the paid flow can be tested without real credits.

```php
// Palm — DevMode credit bypass
if ( class_exists( 'SM_Dev_Mode' ) && SM_Dev_Mode::should_mock_credits() ) {
    return $this->success_response( array(
        'proceed'  => true,
        'devmode'  => true,
        'next_step_url' => $target,
    ) );
}
```

### What Aura Does

No DevMode credit bypass exists. Testing the start-new and paid flows requires real Account Service credits or manual database manipulation.

### Action Required

Port DevMode credit bypass logic from Palm's `handle_start_new_reading` and paid generation flow.

---

## 3. REST Controller — Lead Resolution via Flow Session

### What Palm Does (Better)

Palm has a `resolve_lead_id()` method that falls back to the server-side flow session when the client doesn't send a `lead_id`. This handles edge cases where the browser loses sessionStorage:

```php
// Palm — lead resolution with flow session fallback
private function resolve_lead_id( $lead_id ) {
    $lead_id = $this->sanitize_string( $lead_id );
    if ( '' !== $lead_id ) {
        return $lead_id;
    }
    $flow_session = SM_Flow_Session::get_instance();
    $flow = $flow_session->get_or_create_flow();
    return isset( $flow['lead_id'] ) ? sanitize_text_field( (string) $flow['lead_id'] ) : '';
}
```

This is called in both OTP send and teaser generation endpoints.

### What Aura Does

Aura uses a simple `$this->sanitize_string( $request->get_param( 'lead_id' ) )` — no flow session fallback. If the client doesn't send a `lead_id`, the request fails.

### Action Required

Port `resolve_lead_id()` from Palm and use it in the OTP and generation endpoints.

---

## 4. REST Controller — Existing Reading Detection & Error Handling

### What Palm Does (Better)

When a lead already has a teaser reading and tries to generate another, Palm:

1. Returns error code `reading_exists` (semantically correct)
2. Provides the Account Service login URL as the redirect target (not just the page redirect)
3. Uses a shorter redirect delay: `2000ms`
4. Message: *"You already have a reading for this email. Please log in to access it."*

```php
// Palm
$login_url = SM_Auth_Handler::get_instance()->get_login_url();
$redirect_target = $login_url ? $login_url : $redirect;
return $this->error_response( 'reading_exists', '...', 400, array(
    'redirect_to'       => $redirect_target,
    'redirect_delay_ms' => 2000,
) );
```

### What Aura Does

1. Returns error code `credits_exhausted` (semantically wrong — the user isn't out of credits, they have an existing reading)
2. Uses the generic page redirect (not the login URL)
3. Uses a longer redirect delay: `3500ms`
4. Message: *"You've already received your free reading..."*

### Action Required

Port the `reading_exists` error code, login URL redirect, and 2000ms delay from Palm.

---

## 5. REST Controller — Report Token System

### What Palm Does (Better)

Palm uses **magic tokens** via `SM_Lead_Handler::ensure_report_magic_token()`. This generates a persistent token stored on the lead record that can be reused for report access:

```php
// Palm — magic token via lead handler
$token = $lead_handler->ensure_report_magic_token( $lead_id );
$report_token = ! is_wp_error( $token ) ? $token : '';
// Response uses key: 'report_token'
```

### What Aura Does

Aura uses `SM_Reading_Token::generate()` — a dedicated class (`class-sm-reading-token.php`, 150 lines) that generates tokens tied to a specific reading and type:

```php
// Aura — reading-specific token
$reading_token = SM_Reading_Token::generate( $lead_id, $existing_reading->id, 'aura_teaser' );
// Response uses key: 'reading_token'
```

### Key Difference

These are **architecturally different approaches**:
- **Palm:** Token is per-lead, reusable, stored in DB on the lead record
- **Aura:** Token is per-reading, generated dynamically, includes reading type context

The Aura approach is more granular but means the response key is `reading_token` instead of `report_token`. The frontend code must match whichever approach is used.

### Action Required

Decide which approach to standardize on. If Palm's approach is preferred (simpler, persistent), remove `SM_Reading_Token` class from Aura and port the lead-handler magic token approach. If Aura's approach is preferred, port `SM_Reading_Token` to Palm.

---

## 6. REST Controller — Report Container Wrapping

### What Palm Does

Palm has an `ensure_report_container()` method that wraps reading HTML in a `<div id="palm-reading-result">` container if it doesn't already have one. This is called in multiple places:

```php
// Palm — ensure result container wrapper
private function ensure_report_container( $reading_html, $reading_type ) {
    if ( false !== strpos( $reading_html, 'palm-reading-result' ) ) {
        return $reading_html;
    }
    $class_name = ( 'palm_full' === $reading_type )
        ? 'result-container full-report' : 'result-container';
    return '<div id="palm-reading-result" class="' . esc_attr($class_name) . '">'
        . $reading_html . '</div>';
}
```

### What Aura Does

Aura removed this method entirely. The reading HTML is returned as-is from the renderer. Instead, Aura's teaser JS has a `getReadingResultContainer()` helper that searches for either `aura-reading-result` or `palm-reading-result` container IDs.

### Impact

If the renderer ever returns HTML without the container wrapper, Aura's frontend may fail to find the result element.

### Action Required

Verify that Aura's template renderer always includes the container wrapper. If not, port this safety net from Palm (adapting the container ID to `aura-reading-result`).

---

## 7. REST Controller — DevMode Profile Defaults

### What Palm Does (Better)

Palm has `apply_devmode_profile_defaults()` — when DevMode is enabled and the Account Service profile is incomplete (missing name, email, or age), it fills in test defaults:

```php
// Palm — DevMode profile defaults
protected function apply_devmode_profile_defaults( $snapshot, $user_data, $fallback_email ) {
    if ( empty( $snapshot['name'] ) ) {
        $snapshot['name'] = 'Test User';
    }
    if ( empty( $snapshot['email'] ) ) {
        $snapshot['email'] = sanitize_email( $fallback_email );
    }
    // ... etc
}
```

This is called in 3 different places during the lead preparation flow.

### What Aura Does

No DevMode profile defaults exist. If the Account Service returns an incomplete profile during development, the flow fails with a "missing profile data" error.

### Action Required

Port `apply_devmode_profile_defaults()` from Palm and add calls in the lead preparation endpoints.

---

## 8. REST Controller — OTP Rate Limiting

### What Palm Does (Better)

Palm allows **10 OTP sends per minute** per email+IP combination, which accommodates legitimate resend clicks during verification:

```php
// Palm — generous rate limit
$rate_result = $this->check_rate_limit( $rate_limit_key, 10, MINUTE_IN_SECONDS, ... );
```

### What Aura Does

Aura has a much stricter limit of **1 send per 30 seconds**, with a user-facing countdown message:

```php
// Aura — strict rate limit
$rate_result = $this->check_rate_limit( $rate_limit_key, 1, 30, ... );
$message = sprintf( 'Please wait %d seconds before requesting a new code.', max( 1, $retry_after ) );
```

### Impact

Aura's stricter limit means users must wait 30 seconds between OTP resend attempts, which can feel frustrating. However, it also provides better feedback with the retry countdown.

### Action Required

Adopt Palm's rate limit (10/minute) but keep Aura's improved error message with countdown. Best of both worlds.

---

## 9. REST Controller — OTP Lead Resolution by Email

### What Palm Does (Better)

When sending an OTP, if the `lead_id` from the request doesn't match a lead record, Palm falls back to finding the lead by email:

```php
// Palm — fallback lead resolution by email during OTP send
if ( empty( $lead_record ) ) {
    $fallback_lead = $lead_handler->get_lead_by_email( $email );
    if ( ! empty( $fallback_lead ) && ! empty( $fallback_lead->id ) ) {
        $lead_id = sanitize_text_field( (string) $fallback_lead->id );
        SM_Logger::info( 'OTP_LEAD_RESOLVED', 'Lead resolved by email for OTP send', ... );
    }
}
```

### What Aura Does

No email-based fallback during OTP send. If the `lead_id` is wrong or missing, the OTP send fails even though the lead exists.

### Action Required

Port the OTP lead resolution fallback from Palm.

---

## 10. REST Controller — Job Status Polling (check_reading_status)

### What Palm Does (Better)

Palm's `check_reading_status` endpoint has additional logic to detect when a reading was completed by the background job while the client was polling. It:

1. Checks if a completed reading exists by `reading_id` from the job
2. If found, renders it, wraps in container, updates flow state, deletes the job, and returns `status: ready`

This handles the race condition where the background job finishes between polls.

### What Aura Does

Aura's polling endpoint has a simpler flow — it checks for an existing reading and returns it if found, but also adds job-status checking at the end of the endpoint:

```php
// Aura — adds job check at end of check_reading_status
$job_handler = SM_Reading_Job_Handler::get_instance();
$job = $job_handler->get_job( $lead_id, $reading_type );
if ( $job ) {
    $max_attempts = ( 'aura_full' === $reading_type ) ? 3 : 2;
    $charge_credit = ( 'aura_full' === $reading_type );
    return $this->build_job_status_response( $lead_id, $reading_type, $job, $max_attempts, $charge_credit );
}
```

### Action Required

Compare the two approaches closely. Palm's approach handles the job-reading completion race condition more explicitly. Consider whether Aura's approach at the end of the endpoint catches the same edge cases.

---

## 11. REST Controller — Flow Session Reset on Start New

### What Palm Does (Better)

After a successful credit check in `handle_start_new_reading`, Palm resets the server-side flow session:

```php
// Palm — reset flow session on start new
if ( class_exists( 'SM_Flow_Session' ) ) {
    SM_Flow_Session::get_instance()->reset_flow();
}
```

### What Aura Does

No flow session reset. Stale flow session data from a previous reading persists.

### Action Required

Port the flow session reset from Palm.

---

## 12. Frontend JS — Session Storage Architecture

**Files:** `assets/js/api-integration.js`
**Palm:** 2,791 lines | **Aura:** 2,698 lines

### What Palm Does (Better)

Palm uses `smSessionGet` / `smSessionSet` / `smSessionRemove` / `smLocalRemove` wrapper functions that delegate to `window.smSessionGet` if available, otherwise fall back to raw `sessionStorage`:

```javascript
// Palm — storage wrappers with override support
const smSessionGet = (key) =>
    (window.smSessionGet ? window.smSessionGet(key) : sessionStorage.getItem(key));
const smSessionSet = (key, value) =>
    (window.smSessionSet ? window.smSessionSet(key, value) : sessionStorage.setItem(key, value));
```

These wrappers allow the teaser JS (or other scripts) to override storage behavior globally.

### What Aura Does

Aura uses `smStorage` (a scoped storage system, see #19) directly within api-integration.js. This is a more sophisticated approach with context-aware key scoping, but it's **defined independently in both api-integration.js and teaser-reading.js**, which could lead to inconsistencies if one is updated without the other.

### Impact

Palm's approach is simpler and more maintainable (single storage abstraction). Aura's approach has better isolation between guest/auth/magic flows but has the duplication risk.

### Action Required

Consider unifying: use Palm's simple wrapper approach in api-integration.js, with Aura's scoped storage only in teaser-reading.js where context scoping actually matters.

---

## 13. Frontend JS — Palm Image Fingerprint & Retry System

### What Palm Does

Palm has a photo upload caching and retry system:

1. **`getPalmImageFingerprint()`:** Creates a fingerprint from the image's length + first/last 64 chars
2. **`readPalmUploadCache()` / `writePalmUploadCache()`:** Caches uploaded images in sessionStorage with metadata
3. **`lastPalmImageFingerprint`:** Tracks the last uploaded image to avoid re-upload
4. **`palmRetryActive` / `palmRetryNavHandler`:** Handles photo validation retry flow when the AI rejects the image

```javascript
// Palm — image fingerprint
function getPalmImageFingerprint(imageDataUrl) {
    const length = imageDataUrl.length;
    const head = imageDataUrl.slice(0, 64);
    const tail = imageDataUrl.slice(-64);
    return `${length}:${head}:${tail}`;
}
```

### What Aura Does

None of these exist. No image fingerprinting, no upload caching, no retry navigation handler.

### Impact

Without the retry system, when the AI rejects a photo in Aura, the user experience for re-uploading is degraded. Without upload caching, navigating back and forth in the flow loses the uploaded image.

### Action Required

Port the entire image fingerprint, cache, and retry system from Palm (adapting variable names from `palm*` to `aura*`).

---

## 14. Frontend JS — Start New Reading Client Flow

### What Palm Does (Better)

When the user clicks "Start New Reading" on the dashboard:

1. Clears client session and local flow state
2. Removes the stored palm image
3. Calls `reading/start-new` API
4. On success, navigates to `response.data.next_step_url` (server-provided clean URL)

```javascript
// Palm — start new reading
clearClientSession();
clearLocalFlowState();
smSessionRemove('sm_palm_image');
const response = await makeApiRequest('reading/start-new', 'GET');
window.location.href = response.data.next_step_url || (smData.homeUrl || '/palm-reading');
```

### What Aura Does

1. Calls `clearFlowStateForNewReading()` (different function name)
2. Sets `START_NEW_PENDING_KEY` flag in storage
3. Calls `reading/start-new` API
4. On success, builds a new URL client-side with `start_new=1`, `sm_flow=1`, `sm_flow_auth=1` params

```javascript
// Aura — start new reading
clearFlowStateForNewReading();
smStorage.set(START_NEW_PENDING_KEY, '1');
const response = await makeApiRequest('reading/start-new', 'GET');
const target = new URL(window.location.href);
target.searchParams.set('start_new', '1');
target.searchParams.set('sm_flow', '1');
target.searchParams.set('sm_flow_auth', '1');
window.location.href = target.toString();
```

### Key Difference

Palm relies on the server to provide a clean redirect URL. Aura builds it client-side and uses a `START_NEW_PENDING_KEY` flag mechanism to track the pending state across page loads.

Palm's server-side approach is cleaner because the URL is already sanitized by `build_start_new_target()`. Aura's client-side approach can accumulate stale query params.

### Action Required

Port Palm's server-driven redirect approach. Aura should use `next_step_url` from the API response instead of building URLs client-side.

---

## 15. Frontend JS — Auth Flow Resume (bootstrapResumeAuthFlow)

### What Aura Has (Unique)

Aura has a `bootstrapResumeAuthFlow()` function that doesn't exist in Palm. It handles resuming the reading flow after an auth callback:

```javascript
// Aura only
async function bootstrapResumeAuthFlow() {
    const params = new URLSearchParams(window.location.search);
    if (!params.has('sm_flow')) return false;
    const hasAuthFlag = params.get('sm_flow_auth') === '1';
    // ... resumes to stored step ...
}
```

### What Palm Does Instead

Palm handles auth flow resume through its existing `build_start_new_target()` URL stripping and standard flow initialization. The server cleans the URL before redirecting.

### Action Required

Evaluate whether Aura's `bootstrapResumeAuthFlow` is still needed once the server-side URL handling from Palm is ported. It may be solving a problem that Palm already solves server-side.

---

## 16. Frontend JS — OTP Rate Limit Error Display

### What Aura Does (Better)

Aura parses the rate limit error response and shows the countdown:

```javascript
// Aura — specific rate limit message
if (error.data && error.data.error_code === 'rate_limited') {
    const retryAfter = error.data.data && error.data.data.retry_after
        ? error.data.data.retry_after : null;
    if (retryAfter) {
        message = `Please wait ${retryAfter} seconds before requesting a new code.`;
    }
}
```

### What Palm Does

Palm shows a generic toast: `'Failed to send verification code. Please try again.'`

### Action Required

Port Aura's OTP rate-limit message display to Palm (once rate limits are aligned per #8).

---

## 17. Frontend JS — Reading Result Container Lookup

### What Aura Has

Aura has a `getReadingResultContainer()` helper in teaser-reading.js that searches for both aura and palm container IDs:

```javascript
// Aura — dual container ID search
function getReadingResultContainer() {
    return document.getElementById('aura-reading-result')
        || document.getElementById('palm-reading-result');
}
```

### What Palm Does

Palm directly references `document.getElementById('palm-reading-result')`.

### Impact

Aura's approach is a backwards-compatibility shim. Once all templates use `aura-reading-result`, the palm fallback should be removed.

### Action Required

Ensure all aura templates use `aura-reading-result` as the container ID, then remove the palm fallback from `getReadingResultContainer()`.

---

## 18. Frontend JS — Dashboard Share Feature

### What Aura Has (Unique)

Aura has `initDashboardShare()` — a share button handler using the Web Share API with clipboard fallback:

```javascript
// Aura only
function initDashboardShare() {
    const shareBtn = document.getElementById('share-app-btn');
    if (!shareBtn) return;
    shareBtn.addEventListener('click', async () => {
        const shareUrl = window.location.origin + window.location.pathname;
        if (navigator.share) { /* Web Share API */ }
        if (navigator.clipboard) { /* Clipboard fallback */ }
    });
}
```

The dashboard template also has `id="share-app-btn"` on the share button.

### What Palm Does

Palm's share button has no ID and no click handler — it's non-functional.

### Action Required

This is a feature addition in Aura. Consider porting to Palm if desired.

---

## 19. Teaser JS — Scoped Storage (smStorage)

**Files:** `assets/js/teaser-reading.js`
**Palm:** 659 lines | **Aura:** 770 lines

### What Aura Has (Different Architecture)

Aura defines an `smStorage` scoped storage system that prefixes keys with the current context (`guest`, `auth`, or `magic`):

```javascript
// Aura — scoped storage with context
var smStorage = window.smStorage || (() => {
    const getContext = () => {
        if (params.get('sm_magic') === '1') return 'magic';
        if (params.get('sm_flow_auth') === '1') return 'auth';
        if (smData.isLoggedIn) return 'auth';
        return 'guest';
    };
    const key = (base) => (scopedKeys.has(base) ? `${context}:${base}` : base);
    return { context, key, get, set, remove };
})();
```

It also handles **legacy key migration** — if a scoped key has no value but an unscoped legacy value exists, it migrates it.

### What Palm Does

Palm uses raw `sessionStorage.getItem/setItem/removeItem` calls throughout teaser-reading.js.

### Impact

Aura's scoped storage prevents data leakage between guest and authenticated sessions (e.g., a guest reading session polluting the authenticated session). This is architecturally better but adds complexity.

### Action Required

This is an improvement in Aura. Consider whether to port to Palm. If keeping Aura's approach, ensure the same `smStorage` definition is shared (not duplicated) between teaser-reading.js and api-integration.js.

---

## 20. Teaser JS — Unlock Modal Copy Sections

### What Aura Has (Different Content)

Aura's teaser unlock modal has **6 copy sections** for the locked sections:

1. Emotional State & Inner Climate
2. Energy Level & Flow
3. Love, Relationships & Emotional Connection
4. Life Direction, Success & Material Flow
5. Spiritual Memory & Deeper Patterns
6. Intentions, Healing & Growth

### What Palm Has

Palm's teaser unlock modal has **3 copy sections**:

1. Love Patterns
2. Career Direction
3. Life Alignment

### Action Required

This is a **content difference**, not a mechanical one. No alignment needed — each product has different reading categories.

---

## 21. Auth Handler — Return URL Normalization

**Files:** `includes/class-sm-auth-handler.php`
**Palm:** 1,013 lines | **Aura:** 960 lines

### What Palm Does (Better)

Palm has `normalize_return_url()` which strips flow-only query parameters (`sm_flow`, `sm_flow_auth`, `start_new`) from the return URL before passing it to the auth callback. This prevents the auth system from redirecting back to a flow-starting URL:

```php
// Palm — return URL normalization
private function normalize_return_url( $return_url ) {
    $parts = wp_parse_url( $return_url );
    parse_str( $parts['query'], $query );
    $strip_keys = array( 'sm_flow', 'sm_flow_auth', 'start_new' );
    foreach ( $strip_keys as $key ) {
        unset( $query[ $key ] );
    }
    // ... rebuild URL ...
}
```

### What Aura Does

No return URL normalization. The auth callback can redirect to a URL with `start_new=1` or `sm_flow=1` still in the query string, causing the flow to restart unexpectedly after login.

### Action Required

Port `normalize_return_url()` from Palm to Aura.

---

## 22. Database — Schema Integrity Check

**Files:** `includes/class-sm-database.php`
**Palm:** 906 lines | **Aura:** 976 lines

### What Aura Has (Unique Improvement)

Aura has `ensure_schema_integrity()` — a safety net that verifies critical columns and tables exist even if the stored DB version is current:

```php
// Aura — schema integrity check
private function ensure_schema_integrity() {
    // Checks: reading_type column in readings table
    // Checks: account_id column in leads table
    // Checks: flow_sessions table exists
    // If missing, re-runs the relevant migration
}
```

### What Palm Does

Palm relies purely on version-based migrations. If a migration was marked complete but the column didn't actually get created (e.g., MySQL error), the system breaks silently.

### Action Required

This is an improvement in Aura. Consider porting `ensure_schema_integrity()` to Palm.

---

## 23. Database — Table Access Pattern

### What Palm Does

Palm uses direct table references: `$wpdb->prefix . $this->table_name`

### What Aura Does (Better Abstraction)

Aura uses a centralized method: `SM_Database::get_instance()->get_table_name('readings')`

### Impact

Aura's approach is more maintainable — the table prefix is managed in one place. Palm's approach requires each service to know its own table name.

### Action Required

This is an improvement in Aura. Consider porting to Palm.

---

## 24. Main Plugin File — Report Access / Gate Logic

**Files:** `mystic-palm-reading.php` / `mystic-aura-reading.php`
**Palm:** 619 lines | **Aura:** 626 lines

### What Palm Does (More Sophisticated)

Palm's report access gate checks multiple conditions:

1. If lead has a valid `report_magic_token` → allow access
2. If lead has no account linked AND no paid reading exists → allow teaser access
3. Otherwise → redirect to login

```php
// Palm — multi-layered report access
if ( $lead_handler->validate_report_magic_token( $lead_id, $token ) ) {
    return; // Allow access
}
if ( $lead && '' === $lead_account_id ) {
    $paid_reading = $reading_service->get_latest_reading( $lead_id, 'palm_full' );
    if ( empty( $paid_reading ) ) {
        return; // Allow teaser access for unlinked leads
    }
}
```

### What Aura Does (Different Architecture)

Aura's report access gate uses the `SM_Reading_Token` system:

1. If `reading_type` is `aura_teaser` or has `sm_magic` param → allow access
2. If token validates via `SM_Reading_Token::validate()` → allow teaser access
3. Falls back to OTP magic token validation
4. Otherwise → redirect to login

```php
// Aura — token-based report access
if ( 'aura_teaser' === $reading_type || $has_magic ) {
    return;
}
if ( class_exists( 'SM_Reading_Token' ) ) {
    $payload = SM_Reading_Token::validate( $token, '', array( 'aura_teaser', 'aura_full' ) );
    if ( ! is_wp_error( $payload ) && 'aura_teaser' === $payload['reading_type'] ) {
        return;
    }
}
```

### Key Difference

Palm checks **lead ownership** (is this lead linked to an account? does a paid reading exist?). Aura checks **token validity** (is this a valid reading token?).

Palm's approach is more robust because it handles the case where a token might not exist but the lead genuinely has access. Aura's approach is cleaner but more restrictive.

### Action Required

This is an architectural decision. Recommend porting Palm's account-check logic as an additional fallback in Aura's gate, alongside the token validation.

---

## 25. Main Plugin File — SM_Reading_Token Class

### What Aura Has (Unique)

Aura has `class-sm-reading-token.php` (150 lines) — a dedicated class for generating and validating short-lived tokens that encode `lead_id`, `reading_id`, and `reading_type`:

- `SM_Reading_Token::generate( $lead_id, $reading_id, $reading_type )` → returns a signed token
- `SM_Reading_Token::validate( $token, $lead_id, $allowed_types )` → verifies and returns payload

### What Palm Does

Palm uses the lead handler's `ensure_report_magic_token()` / `validate_report_magic_token()` — a simpler token stored directly on the lead record.

### Action Required

Decide which approach to standardize on (see #5).

---

## 26. Settings — Dual Settings Group Registration

**Files:** `includes/class-sm-settings.php`
**Palm:** 1,362 lines | **Aura:** 1,452 lines

### What Aura Does (Different)

Aura registers its settings in **two groups**: `sm_aura_settings_group` AND `sm_settings_group`:

```php
// Aura — dual registration
register_setting( 'sm_aura_settings_group', self::OPTION_KEY, ... );
register_setting( 'sm_settings_group', self::OPTION_KEY, ... );
```

The settings form uses `sm_aura_settings_group`, but the second registration exists for backwards compatibility.

### What Palm Does

Palm registers only in `sm_settings_group`.

### Impact

The dual registration could cause confusion and is likely unnecessary once Aura is fully independent.

### Action Required

Remove the `sm_settings_group` registration from Aura once all references are updated to `sm_aura_settings_group`.

---

## 27. Reading Service — Allowed Reading Types

**Files:** `includes/class-sm-reading-service.php`
**Both:** 792 lines

### What Palm Allows

```php
$allowed_types = array( 'palm_teaser', 'palm_full', 'palm_legacy', 'aura_reading', 'love_insight' );
```

Palm supports 5 types, including `palm_legacy` for older readings and `aura_reading` for cross-service compatibility.

### What Aura Allows

```php
$allowed_types = array( 'aura_teaser', 'aura_full', 'love_insight' );
```

Aura supports 3 types. No legacy type, no cross-service type.

### Action Required

This is intentional — Aura is a new product with no legacy readings. No alignment needed unless cross-service compatibility is desired later.

---

## 28. Job Handler — WP-Cron Fallback on Dispatch Failure

**Files:** `includes/class-sm-reading-job-handler.php`
**Palm:** 714 lines | **Aura:** 683 lines

### What Palm Does (Better)

In `dispatch_job_request()`, Palm inspects the loopback HTTP response. If it's a `WP_Error` or returns status >= 400, Palm falls back to `schedule_job()` (WP-Cron) to ensure the reading job still runs:

```php
// Palm — fallback to WP-Cron if loopback dispatch fails
$response_code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
$dispatch_failed = is_wp_error( $response ) || $response_code >= 400;
if ( $dispatch_failed ) {
    $this->schedule_job( $lead_id, $reading_type, $account_id );
}
```

### What Aura Does

Aura fires `wp_remote_post()` and logs the result, but **never inspects the response code** and has **no WP-Cron fallback**. If the loopback request fails (SSL issues, local dev environment), the job is permanently stuck in `queued` state.

### Impact

This is a **critical reliability issue** in Aura. In local development environments or hosts with loopback problems, reading jobs will silently fail with no recovery mechanism.

### Action Required

Port the response inspection and WP-Cron fallback from Palm.

---

## 29. Job Handler — Duplicate Run Guard

### What Palm Does (Better)

Before processing a job, Palm checks if the job is already `running` and was updated within the last 300 seconds. If so, it exits immediately to prevent concurrent executions:

```php
// Palm — duplicate execution guard
if ( 'running' === $job['status'] && ! empty( $job['updated_at'] ) ) {
    $updated_time = strtotime( $job['updated_at'] );
    if ( $updated_time && ( current_time( 'timestamp' ) - $updated_time ) <= 300 ) {
        return; // Already running, skip duplicate
    }
}
```

### What Aura Does

Aura only has the timeout check (elapsed > 300 seconds marks it as stale). There is **no guard against concurrent runs** within the 300-second window. If two loopback requests or cron events fire simultaneously, both will attempt to generate a reading.

### Impact

Can cause duplicate reading generation, duplicate credit charges, and duplicate emails in Aura.

### Action Required

Port the duplicate-run guard from Palm.

---

## 30. Flow Session — Cookie Architecture (Single vs Three Cookies)

**Files:** `includes/class-sm-flow-session.php`

### What Palm Does

Palm uses a single cookie for all flow contexts:

```php
// Palm — single cookie
const COOKIE_NAME = 'sm_flow_id';
```

All users (guest, authenticated, magic link) share the same cookie.

### What Aura Does (Better)

Aura uses three separate cookies selected by context:

```php
// Aura — context-aware cookies
const COOKIE_NAME_GUEST = 'sm_flow_id_guest';
const COOKIE_NAME_AUTH  = 'sm_flow_id_auth';
const COOKIE_NAME_MAGIC = 'sm_flow_id_magic';

private function get_cookie_name() {
    if ( isset( $_GET['sm_magic'] ) && '1' === $_GET['sm_magic'] ) {
        return self::COOKIE_NAME_MAGIC;
    }
    if ( SM_Auth_Handler::get_instance()->is_user_logged_in() ) {
        return self::COOKIE_NAME_AUTH;
    }
    return self::COOKIE_NAME_GUEST;
}
```

### Impact

Aura's approach prevents flow state contamination across login contexts. An authenticated user won't accidentally load a previous guest flow session, and magic-link flows are tracked separately.

### Action Required

This is an improvement in Aura. Consider porting to Palm.

---

## 31. Credit Handler — Stale Cache Fallback

**Files:** `includes/class-sm-credit-handler.php`

### What Palm Does (Better)

When `get_credit_balance(force_refresh=true)` fails (network error, timeout), Palm falls back to the last cached credit snapshot:

```php
// Palm — stale cache fallback
if ( $force_refresh && empty( $result['success'] ) ) {
    $cached_result = $this->get_cached_credit_snapshot_for_current_user();
    if ( ! empty( $cached_result['success'] ) ) {
        $result = $cached_result;
    }
}
```

This uses a private method `get_cached_credit_snapshot_for_current_user()` that also exists only in Palm.

### What Aura Does

Aura calls `$this->check_user_credits( '', $force_refresh )` and returns the result directly. If the forced refresh fails, the user sees an error — no stale cache fallback.

### Impact

On Aura, transient Account Service outages cause the credit check to fail entirely, blocking the paid flow. Palm degrades gracefully by using the last known balance.

### Action Required

Port `get_cached_credit_snapshot_for_current_user()` and the stale cache fallback logic from Palm.

---

## 32. Credit Handler — DevMode Mock Injection Points

### What Palm Does (Better)

Palm has DevMode mock injection at **6+ points** across `check_user_credits()` and `deduct_credit()`:

1. When `enable_account_integration` is disabled
2. When `$jwt_token` is empty
3. When `account_service_url` is missing
4. On network error (`WP_Error` response)
5. On `401 + invalid_token` from local hosts
6. On 200 response with empty body

Each injects a mock response so developers can test the full paid flow locally.

### What Aura Does

**Zero** DevMode mock injection points in `SM_Credit_Handler`. All failure conditions return real errors. Testing the paid flow in Aura requires a live Account Service connection.

### Action Required

Port the DevMode mock injection points from Palm's `SM_Credit_Handler`.

---

## 33. OTP Handler — Lead Lookup Retry on Race Condition

**Files:** `includes/class-sm-otp-handler.php`

### What Palm Does (Better)

When creating an OTP, if the lead isn't found immediately (e.g., database replication lag after lead creation), Palm retries once after 200ms:

```php
// Palm — retry lead lookup after 200ms
if ( '' !== $lead_id && empty( $lead ) ) {
    usleep( 200000 ); // 200ms
    $lead = $this->get_lead( $lead_id );
}
if ( '' === $lead_id || empty( $lead ) ) {
    return new WP_Error( 'invalid_lead', ... );
}
```

### What Aura Does

No retry — fails immediately if the lead isn't found on first lookup.

### Impact

On Aura, there's a narrow race condition window where lead creation finishes but the OTP send (triggered milliseconds later) fails because the DB read hasn't caught up. This can happen on high-latency or replicated database setups.

### Action Required

Port the 200ms retry from Palm.

---

## 34. OTP Handler — Internal Rate Limit Window

### What Palm Does

Inside `SM_OTP_Handler`, the internal `check_send_rate_limit()` allows:

```php
// Palm — 3 OTPs per hour
SM_Rate_Limiter::check( $key, 3, HOUR_IN_SECONDS, ... );
```

### What Aura Does

```php
// Aura — 4 OTPs per 2 minutes
SM_Rate_Limiter::check( $key, 4, 2 * MINUTE_IN_SECONDS, ... );
```

### Impact

Note: This is a **separate rate limit** from the REST controller rate limit (which is 10/min in Palm, 1/30s in Aura). The OTP handler has its own internal check.

Palm's limit (3/hour) is more restrictive for sustained abuse but more generous for quick resends. Aura's limit (4/2min) allows more rapid resends but resets faster.

### Action Required

Align with Palm's rate limit for consistency with the overall approach of generous limits + countdown messaging.

---

## 35. OTP Handler — Resend Cooldown Units

### What Palm Does

Palm calculates OTP resend cooldown in **minutes** (default 10 minutes):

```php
// Palm
$minutes = $this->get_resend_cooldown_minutes(); // 10 min
$time = current_time('timestamp') + ($minutes * MINUTE_IN_SECONDS);
```

### What Aura Does (Different)

Aura calculates in **seconds** (default 30 seconds), with a dedicated setting method:

```php
// Aura
$seconds = $this->get_resend_cooldown_seconds_setting(); // 30s
$time = current_time('timestamp') + $seconds;
```

Aura also has `get_resend_cooldown_seconds_setting()` which reads from settings and applies a filter (`sm_aura_otp_resend_cooldown_seconds`), with a minimum of 30 seconds.

### Impact

Palm's 10-minute cooldown between OTP resends is quite long and could frustrate users. Aura's 30-second cooldown is more user-friendly. However, the setting method in Aura is more configurable.

### Action Required

Adopt Aura's 30-second cooldown as the default but keep Palm's settings/filter approach. The 10-minute default in Palm is excessive.

---

## 36. Summary Matrix

**Note:** The `Better`/`Priority` columns are historical analysis. For implementation decisions, use the locked rules above and `PALM_PARITY_EXECUTION_PLAN.md`.

| # | Area | Palm | Aura | Better | Priority |
|---|------|------|------|--------|----------|
| 1 | Start New URL Building | `build_start_new_target()` strips stale params | Naive `add_query_arg` | Palm | HIGH |
| 2 | DevMode Credit Bypass | Full bypass in start-new and paid flow | None | Palm | MEDIUM |
| 3 | Lead Resolution (Flow Session) | `resolve_lead_id()` with session fallback | Simple param sanitize only | Palm | HIGH |
| 4 | Existing Reading Error | `reading_exists` + login URL + 2s delay | `credits_exhausted` + generic URL + 3.5s | Palm | HIGH |
| 5 | Report Token System | Lead-level magic token (`report_token`) | Reading-level `SM_Reading_Token` (`reading_token`) | Architectural decision | MEDIUM |
| 6 | Report Container Wrapping | `ensure_report_container()` safety net | Removed; relies on renderer | Palm | LOW |
| 7 | DevMode Profile Defaults | `apply_devmode_profile_defaults()` | None | Palm | MEDIUM |
| 8 | OTP Rate Limiting | 10/minute (generous) | 1/30s (strict + countdown) | Hybrid | MEDIUM |
| 9 | OTP Lead Resolution | Email-based fallback | None | Palm | HIGH |
| 10 | Job Status Polling | Explicit race condition handling | Simpler but adds job check at end | Review | MEDIUM |
| 11 | Flow Session Reset | Resets on start-new | No reset | Palm | HIGH |
| 12 | Session Storage Wrappers | `smSessionGet/Set` with override support | `smStorage` scoped system | Different approach | MEDIUM |
| 13 | Image Fingerprint & Retry | Full system (fingerprint, cache, retry) | None | Palm | HIGH |
| 14 | Start New Client Flow | Server-driven `next_step_url` | Client-built URL + pending flag | Palm | HIGH |
| 15 | Auth Flow Resume | Handled by URL stripping server-side | `bootstrapResumeAuthFlow()` client-side | Evaluate | LOW |
| 16 | OTP Rate Limit Display | Generic message | Countdown message | Aura | LOW |
| 17 | Result Container Lookup | Direct ID reference | Dual-ID fallback helper | Clean up Aura | LOW |
| 18 | Dashboard Share | Non-functional button | Working Web Share API | Aura | LOW |
| 19 | Scoped Storage (Teaser) | Raw sessionStorage | Context-scoped `smStorage` | Aura | LOW |
| 20 | Unlock Modal Sections | 3 sections | 6 sections | Content difference | N/A |
| 21 | Auth Return URL Normalization | `normalize_return_url()` strips flow params | No normalization | Palm | HIGH |
| 22 | Schema Integrity Check | None | `ensure_schema_integrity()` | Aura | LOW |
| 23 | Table Access Pattern | Direct `$wpdb->prefix` | Centralized `get_table_name()` | Aura | LOW |
| 24 | Report Access Gate | Multi-layer (token + ownership + account check) | Token-based (SM_Reading_Token + OTP) | Palm | MEDIUM |
| 25 | SM_Reading_Token Class | N/A (uses lead magic token) | Dedicated class (150 lines) | Architectural decision | MEDIUM |
| 26 | Settings Registration | Single group | Dual group (backwards compat) | Clean up Aura | LOW |
| 27 | Allowed Reading Types | 5 types (incl. legacy, cross-service) | 3 types | Intentional | N/A |
| 28 | Job Dispatch Fallback | WP-Cron fallback if loopback fails | No fallback; jobs stuck forever | Palm | **CRITICAL** |
| 29 | Job Duplicate Run Guard | Skips if `running` within 300s | No guard; concurrent runs possible | Palm | HIGH |
| 30 | Flow Session Cookies | Single `sm_flow_id` cookie | Three context cookies (`_guest`, `_auth`, `_magic`) | Aura | LOW |
| 31 | Credit Stale Cache Fallback | Falls back to cached balance on failure | No fallback; returns error | Palm | MEDIUM |
| 32 | Credit DevMode Mocks | 6+ mock injection points | None in credit handler | Palm | MEDIUM |
| 33 | OTP Lead Lookup Retry | 200ms retry on race condition | No retry; immediate failure | Palm | MEDIUM |
| 34 | OTP Internal Rate Limit | 3/hour (in handler) | 4/2min (in handler) | Review | LOW |
| 35 | OTP Resend Cooldown | 10 minutes (excessive) | 30 seconds (configurable via filter) | Aura | LOW |

---

## Implementation Ordering

Use `PALM_PARITY_EXECUTION_PLAN.md` for ordered execution and progress tracking.

- Phase 1 completed: `#1`, `#3`, `#4`, `#9`, `#10`, `#11`, `#28`, `#29`
- Phase 2 in progress: token/report access/auth normalization set (`#21` done; `#5/#24/#25` pending completion)
- Remaining items continue in parity-plan phase order

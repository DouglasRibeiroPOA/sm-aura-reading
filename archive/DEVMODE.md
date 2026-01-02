# DevMode - API Mocking System

**Version:** 1.3.8+
**Purpose:** Avoid spending OpenAI credits and triggering real MailerLite actions during development.

---

## Overview

DevMode is a development-only feature that intercepts OpenAI and MailerLite API calls and routes them to mock REST endpoints instead of the real APIs. This allows you to:

- **Test the plugin** without spending OpenAI credits
- **Develop features** without triggering real email campaigns in MailerLite
- **Debug flows** with predictable, fast API responses
- **Work offline** without needing real API keys

---

## How It Works

### Normal Flow (DevMode OFF)
```
User Action → Plugin Logic → Real OpenAI API → Real Response
User Action → Plugin Logic → Real MailerLite API → Real Response
```

### DevMode Flow (DevMode ON)
```
User Action → Plugin Logic → Mock OpenAI Endpoint → Fake Response
User Action → Plugin Logic → Mock MailerLite Endpoint → Fake Response
```

When DevMode is enabled:
- All OpenAI API calls are redirected to `/wp-json/soulmirror-dev/v1/mock-openai`
- All MailerLite API calls are redirected to `/wp-json/soulmirror-dev/v1/mock-mailerlite`
- No real API keys are required
- Responses are instant (with simulated delay of 1-2 seconds)

---

## Enabling DevMode

### Method 1: WP-CLI (Recommended)

Navigate to your WordPress installation directory and run:

```bash
wp sm devmode enable
```

**Expected output:**
```
Success: DevMode enabled - API calls will use mock endpoints
Mock OpenAI URL: http://localhost/wp-json/soulmirror-dev/v1/mock-openai
Mock MailerLite URL: http://localhost/wp-json/soulmirror-dev/v1/mock-mailerlite
```

### Method 2: PHP Code (Manual)

Add this to your `wp-config.php` or run in a temporary PHP file:

```php
<?php
require_once( './wp-load.php' );
SM_Dev_Mode::enable();
echo "DevMode enabled\n";
```

### Method 3: WordPress Admin Console (Future Enhancement)

_A settings page will be added in a future version._

---

## Disabling DevMode

### Method 1: WP-CLI (Recommended)

```bash
wp sm devmode disable
```

**Expected output:**
```
Success: DevMode disabled - API calls will use real endpoints
```

### Method 2: PHP Code (Manual)

```php
<?php
require_once( './wp-load.php' );
SM_Dev_Mode::disable();
echo "DevMode disabled\n";
```

---

## Checking DevMode Status

### Method 1: WP-CLI

```bash
wp sm devmode status
```

**Expected output (when enabled):**
```
DevMode: ENABLED (using mock endpoints)
Mock OpenAI URL: http://localhost/wp-json/soulmirror-dev/v1/mock-openai
Mock MailerLite URL: http://localhost/wp-json/soulmirror-dev/v1/mock-mailerlite
```

**Expected output (when disabled):**
```
DevMode: DISABLED (using real API endpoints)
```

### Method 2: Check Logs

DevMode logs all API calls with a `[DEV_MODE]` or `[warning]` tag. Check your debug log:

```bash
tail -f wp-content/debug.log | grep -i "devmode"
```

When DevMode is active, you'll see:
```
[warning] DEV_MODE - DevMode enabled - using mock OpenAI endpoint
[warning] MAILERLITE_SYNC - DevMode enabled - using mock MailerLite endpoint
```

---

## Mock API Responses

### Mock OpenAI Response

The mock OpenAI endpoint returns a **complete, realistic teaser reading** with:
- All required JSON fields (meta, opening, life_foundations, love_patterns, etc.)
- Personalized content using the user's name (extracted from the request)
- Realistic trait scores (Intuition: 88%, Resilience: 92%, Independence: 85%)
- ~500-700 words of visible content
- Simulated processing delay (1-2 seconds)

**Sample response structure:**
```json
{
  "id": "chatcmpl-mock-uuid",
  "object": "chat.completion",
  "created": 1234567890,
  "model": "gpt-4o",
  "choices": [
    {
      "message": {
        "role": "assistant",
        "content": "{\"meta\": {...}, \"opening\": {...}, ...}"
      }
    }
  ],
  "usage": {
    "prompt_tokens": 1500,
    "completion_tokens": 1800,
    "total_tokens": 3300
  }
}
```

### Mock MailerLite Response

The mock MailerLite endpoint returns a **successful subscriber sync** with:
- Subscriber ID (hashed from email for consistency)
- Active status
- All submitted fields echoed back

**Sample response structure:**
```json
{
  "data": {
    "id": "mock-subscriber-abc123",
    "email": "user@example.com",
    "status": "active",
    "subscribed_at": "2025-12-19 10:30:00",
    "fields": {
      "name": "Alexandra",
      "company": "lead-uuid-12345"
    }
  }
}
```

---

## Use Cases

### 1. Local Development
Enable DevMode while building new features:
```bash
wp sm devmode enable
# Develop features, test flows
wp sm devmode disable
```

### 2. Testing Error Handling
Mock responses are always successful. To test error handling, temporarily modify the mock endpoint code in `class-sm-dev-mode.php`.

### 3. Frontend Testing
Test the entire user flow (camera → quiz → reading generation) without any API costs:
1. Enable DevMode
2. Complete the palm reading flow
3. Verify frontend displays mock reading correctly
4. Check unlock functionality works

### 4. Debugging Production Issues
If production has an issue, enable DevMode locally and reproduce the flow without hitting real APIs.

---

## Important Notes

### ⚠️ Security Warning
**NEVER enable DevMode in production.** This feature is for development environments only.

DevMode:
- Bypasses API key validation
- Returns fake data
- Logs sensitive information
- Has no rate limiting on mock endpoints

### 🔒 API Keys Still Required for Production
DevMode does **not** remove the need for API keys. When DevMode is disabled, the plugin will still require:
- OpenAI API key (set in WordPress admin)
- MailerLite API key (set in WordPress admin)

### 📝 Logging
All DevMode API calls are logged with level `warning` to make them visible in logs:
```
[warning] AI_READING - DevMode enabled - using mock OpenAI endpoint
[warning] MAILERLITE_SYNC - DevMode enabled - using mock MailerLite endpoint
```

### 🚀 Performance
Mock responses are **much faster** than real API calls:
- OpenAI mock: 1-2 seconds (vs 8-15 seconds real)
- MailerLite mock: 1 second (vs 2-5 seconds real)

---

## Troubleshooting

### Issue: DevMode enabled but still seeing real API calls

**Solution:**
1. Check status: `wp sm devmode status`
2. Clear object cache: `wp cache flush`
3. Restart PHP-FPM / web server
4. Verify logs show DevMode warnings

### Issue: Mock endpoint returns 404

**Solution:**
1. Flush rewrite rules: `wp rewrite flush`
2. Verify REST API is working: `curl http://localhost/wp-json/`
3. Check that SM_Dev_Mode::init() is called in `mystic-palm-reading.php`

### Issue: Can't find WP-CLI command

**Solution:**
1. Verify WP-CLI is installed: `wp --version`
2. Navigate to WordPress root directory
3. Run: `wp sm devmode status`
4. If command not found, check that `class-sm-dev-mode.php` is loaded

---

## Future Enhancements

Planned features for DevMode:

- [ ] WordPress admin settings page for one-click toggle
- [ ] Ability to customize mock responses via JSON files
- [ ] Mock error responses for testing error handling
- [ ] Mock rate limiting responses
- [ ] DevMode indicator in admin bar when enabled
- [ ] Automatic DevMode detection based on environment (WP_DEBUG)

---

## Technical Details

### Files Modified

1. **`includes/class-sm-dev-mode.php`** (new)
   - Main DevMode class
   - Mock endpoint registration
   - WP-CLI commands
   - Mock response generators

2. **`includes/class-sm-ai-handler.php`**
   - Modified `call_openai_api()` to check DevMode
   - Routes to mock endpoint when enabled

3. **`includes/class-sm-mailerlite-handler.php`**
   - Modified `make_request()` to check DevMode
   - Routes to mock endpoint when enabled

4. **`mystic-palm-reading.php`**
   - Added `SM_Dev_Mode::init()` to plugin initialization

### Database Storage

DevMode status is stored in WordPress options table:
- **Option name:** `sm_dev_mode_enabled`
- **Value:** `1` (enabled) or `0` (disabled)

### REST API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/soulmirror-dev/v1/mock-openai` | POST | Mock OpenAI chat completion |
| `/wp-json/soulmirror-dev/v1/mock-mailerlite` | POST | Mock MailerLite subscriber sync |

---

## Quick Reference

```bash
# Enable DevMode
wp sm devmode enable

# Check status
wp sm devmode status

# Disable DevMode
wp sm devmode disable

# Watch logs for DevMode activity
tail -f wp-content/debug.log | grep -i "devmode"
```

---

**For questions or issues, contact the development team.**

**Last updated:** 2025-12-19
**Plugin version:** 1.3.8

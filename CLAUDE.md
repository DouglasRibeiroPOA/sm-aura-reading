# CLAUDE.md — SM Aura Reading Plugin

AI assistant guide for working on this codebase. Read this before making any changes.

---

## What This Plugin Does

An AI-powered aura reading experience for the SoulMirror platform. Users upload a photo, answer a short quiz, and receive a personalized aura reading. There are two reading types:

- **Teaser** — Free reading with a few unlockable sections (2 free unlocks, third redirects to paywall)
- **Full / Paid** — Complete reading, gated behind credits from the SoulMirror Account Service

The plugin is **stable and in production**. Changes should be minimal, targeted, and well-tested.

---

## Stack

- **Backend:** PHP 7.4+, WordPress 5.8+
- **APIs:** OpenAI GPT-4o (Vision + text), MailerLite v3, SoulMirror Account Service (JWT + credits)
- **Frontend:** Vanilla JS (`assets/js/script.js`, `assets/js/api-integration.js`)
- **Testing:** Playwright (E2E) + Jest (unit)
- **Naming:** `SM_` prefix for PHP classes, `sm_` for DB tables

---

## Architecture

### User Flow
```
Email entry → OTP verification → Photo upload → Quiz (questions) → AI reading generation → Teaser result → [Unlock sections] → [Pay for full report]
```

### Reading Generation
1. Photo + quiz answers sent to OpenAI Vision API
2. Response is structured JSON stored in `wp_sm_readings.content_data`
3. Teaser: 2 API calls total (async via WP background jobs)
4. Full report: 3 API calls total
5. **Never change the number of API calls without understanding the full async job chain**

### Session State Keys (critical — do not rename)
- `sm_reading_loaded` — report is in session
- `sm_reading_lead_id` — current lead
- `sm_reading_token` — auth token
- `sm_flow_step_id` — current step in the flow
- `sm_existing_reading_id` — for returning users

### Authentication
- Optional SSO via SoulMirror Account Service (JWT)
- Free user flow requires NO login
- Logged-in users get dashboard + reading history

---

## Key Files

| File | Purpose |
|------|---------|
| `mystic-aura-reading.php` | Plugin bootstrap, WP auth bypass for report URLs |
| `includes/class-sm-rest-controller.php` | All REST API endpoints |
| `includes/class-sm-ai-handler.php` | OpenAI prompts, reading generation |
| `includes/class-sm-flow-session.php` | Session state management |
| `includes/class-sm-reading-job-handler.php` | Async background job processing |
| `includes/class-sm-auth-handler.php` | JWT validation, login/logout, session redirects |
| `includes/class-sm-credit-handler.php` | Credit check + deduction via Account Service |
| `includes/class-sm-database.php` | DB schema, migrations |
| `includes/class-sm-template-renderer.php` | Teaser template rendering |
| `includes/class-sm-full-template-renderer.php` | Full report rendering |
| `includes/class-sm-teaser-reading-schema.php` | JSON schema for teaser readings |
| `includes/class-sm-unlock-handler.php` | Section unlock logic |
| `assets/js/script.js` | Main frontend logic — **DO NOT modify without explicit request** |
| `assets/js/api-integration.js` | Frontend API calls — **DO NOT modify without explicit request** |

### Templates
- `aura-reading-template-teaser.html` — Teaser (traditional scrolling layout)
- `aura-reading-template-full.html` — Full report (traditional scrolling layout)
- `aura-reading-template-swipe-teaser.html` — Teaser (swipeable card layout)
- `aura-reading-template-swipe-full.html` — Full report (swipeable card layout)

Admin setting `sm_report_template` switches between `traditional` and `swipeable-cards`.

---

## Coding Conventions

Follow the existing patterns exactly. Do not introduce new abstractions.

**PHP:**
- Classes: `SM_ClassName` in `includes/class-sm-classname.php`
- Always use `$wpdb->prepare()` for queries
- Sanitize all inputs with `sanitize_text_field()`, `absint()`, etc.
- Escape all outputs with `esc_html()`, `esc_attr()`, `wp_kses_post()`
- Log with `SM_Logger::log('level', 'CONTEXT', 'message', $data)`
- Use `SM_Dev_Mode::is_enabled()` to gate test-only code paths

**JavaScript:**
- Vanilla JS only — no frameworks
- State lives in `sessionStorage` with `sm_` keys
- All API calls go through `apiCall()` helper in `api-integration.js`
- Prefix console logs with `[SM]` for easy filtering

**General:**
- No comments unless the WHY is non-obvious
- No over-engineering — three similar lines beats a premature abstraction
- No error handling for scenarios that can't happen
- Match the style of the file you're editing

---

## Security Rules

These are non-negotiable:

- All REST endpoints require nonces (`check_ajax_referer` or `verify_nonce`)
- Rate limiting on email check and OTP endpoints (`SM_Rate_Limiter`)
- Never expose raw OTP codes or API keys in responses
- Never use `$_GET`/`$_POST` directly — always sanitize first
- JWT tokens go in httponly cookies or WP sessions only — never in JS-accessible storage
- Use `$wpdb->prepare()` — no raw SQL string concatenation ever

---

## Before Making Any Change

**Point it out first.** If you see a way to do something, describe it and ask before doing it. This project has a specific style and architecture — don't assume.

Specifically, always flag:
- Anything touching `script.js` or `styles.css` (locked unless explicitly requested)
- Any change to session state key names
- Any change to the number of OpenAI API calls
- Any change to JSON schema keys (other systems depend on them)
- Any new dependency or library
- Any SQL schema change
- Anything that could affect the free user flow

---

## Testing

### DevMode
Enable at: WordPress Admin → Aura Reading → Settings → DevMode  
DevMode mocks OpenAI and Account Service API calls — use it to avoid real API costs.

### Run Tests
```bash
# All tests
npm test

# E2E with visible browser (recommended)
PLAYWRIGHT_USE_SYSTEM_CHROME=1 npm run test:e2e:headed

# Focused suites
npx playwright test tests/specs/suites/01-critical-happy-paths.spec.js
```

### Test Helper Endpoints (DevMode only)
- `GET /wp-json/soulmirror-test/v1/get-otp?email=X` — retrieve OTP without email
- `POST /wp-json/soulmirror-test/v1/seed-reading` — create a complete test reading instantly
- `POST /wp-json/soulmirror-test/v1/cleanup` — delete test data

### After Any Change
1. Run `npm test` — all tests must pass
2. Check `wp-content/debug.log` for PHP errors
3. Verify free user flow still works end-to-end
4. Verify logged-in user flow still works end-to-end

---

## External Services

| Service | Purpose | Config location |
|---------|---------|----------------|
| OpenAI GPT-4o | Reading generation | WP Admin → Aura Reading → Settings |
| MailerLite v3 | Email marketing | WP Admin → Aura Reading → Settings |
| SoulMirror Account Service | Auth, credits | WP Admin → Aura Reading → Settings |

Account Service base URL: configurable (default `https://account.soulmirror.com`)  
Service slug: `aura-reading`  
Auth callback: `{site_url}/aura-reading/auth/callback`

---

## Debugging

```bash
# Watch PHP logs
tail -f /path/to/wp-content/debug.log

# Filter plugin logs only
grep "\[SM\]" wp-content/debug.log | tail -50
```

Browser DevTools → Console → filter by `[SM` to see all frontend plugin logs.

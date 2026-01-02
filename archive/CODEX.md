# CODEX.md

GitHub Copilot & OpenAI Codex Guide for Mystic Palm Reading Plugin (SoulMirror)

## ✅ **System Status: Complete & Stable**

**Status:** ✅ **STABLE & PRODUCTION-READY** - Core Development Finished (Enhancements Only)
**Last Updated:** 2025-12-27

All major features successfully implemented:
- ✅ Account Service Authentication Integration - SSO login, JWT tokens, credit system
- ✅ Teaser Reading Rebalance - OpenAI prompt optimization (30-40% token reduction)
- 🛠️ Teaser Stabilization (in progress) - Harden teaser output, fix swipe gaps, add palm summary card
- ✅ Flow Session Stabilization - Server-side state management
- ✅ Complete automated testing infrastructure
- ✅ All critical and high-priority bugs resolved
- ✅ UI/UX refinements complete

**Current Phase:** Complete (Multi-Template System Implemented)

---

## 🚧 **ACTIVE PROJECT: Teaser Content Quality Enhancement**

**Status:** ⚡ IN PROGRESS - Content Optimization & API Efficiency
**Started:** 2025-12-30
**Plan:** `TEASER-CONTENT-OPTIMIZATION.md`

### Quick Overview

**Achievements:**
- ✅ Fixed duplicate API call bug (4 calls → 2 calls per teaser, 50% cost reduction)
- ✅ Identified 8 wasted fields (28% of schema) never displayed in templates

**Current Focus:**
- Replace wasted `locked_teaser` fields with 200 dynamic quotes (gender-specific)
- Strengthen content depth: Foundations +60%, Previews +200% (40w → 120-160w)
- Cleaner schema: 29 fields → 21 fields

**Benefits:**
- 50-70% richer teaser content
- Lower API costs (fewer wasted tokens)
- Better user experience (deeper insights)
- Dynamic quotes system (200 mystical quotes)

### Key Files to Modify

**New Files:**
- `includes/class-sm-quote-handler.php` - Quote selection logic (deterministic, gender-aware)
- `includes/teaser-quotes.json` - 200 inspirational quotes database

**Modified Files:**
- `includes/class-sm-ai-handler.php` - Update prompts with stronger word count targets
- `includes/class-sm-teaser-reading-schema.php` - Remove 8 wasted fields
- `includes/class-sm-template-renderer.php` - Pass quotes to template replacements
- `palm-reading-template-teaser.html` - Use quote placeholders instead of hardcoded text
- `palm-reading-template-swipe-teaser.html` - Use quote placeholders

### Implementation Phases

**Phase 1:** Quote System - Create handler + 200 quotes database
**Phase 2:** Remove Wasted Fields - Clean up schema (8 fields removed)
**Phase 3:** Strengthen Content - Increase word count targets

### Critical Rules for This Project

✅ **DO:**
- Read `TEASER-CONTENT-OPTIMIZATION.md` FIRST (complete plan + tracking)
- Test quotes display for both male/female identities
- Verify content depth improvement (foundations, previews)
- Run E2E tests after each phase
- Update `ASYNC-OPTIMIZATION-PLAN.md` as you complete tasks

❌ **DO NOT:**
- Modify expansion retry logic until new prompts are proven
- Skip testing with real OpenAI calls (DevMode won't catch prompt issues)
- Break existing upgrade flow (reuse teaser data)
- Remove logging (needed for monitoring)

---

## 🚧 **Previous Investigation (Reference)**

See archived investigation docs:
- `archive/2025-12-28-md-archive/INVESTIGATION.md`
- `remaining-issues.md` (now addressed in optimization project)

---

## 🚀 **Current Development Focus**

**Status:** Enhancements only. Multi-template support is live.

---

## ✅ Current Execution Checklist (Teaser → Paid + QA)

**Phase 1: Upgrade-in-Place**
- [ ] Implement upgrade-in-place (teaser → paid in same row)
- [ ] Ensure one extra OpenAI call only (paid completion)
- [ ] Deduct 1 credit with idempotency key
- [ ] Remove teaser copy + locks in paid render
- [ ] Verify no duplicate rows in dashboard

**Phase 2: Teaser Quality Alignment**
- [ ] Align teaser prompt depth with paid tone
- [ ] Enforce teaser section word counts
- [ ] Add expansion retry for short sections

**Phase 3: QA Instrumentation**
- [ ] Log per-section word counts
- [ ] Add QA summary output per batch run

**Phase 4: Playwright Batch Runs**
- [ ] Run 5 teaser reports
- [ ] Run 5 paid upgrades (use `credentials/paid-accounts.txt`)
- [ ] Review logs/screenshots for failures

**Phase 5: Iterate + Stabilize**
- [ ] Adjust prompts/logic based on QA summary
- [ ] Re-run batch until thresholds are met

**Update Rules**
- Mark completed steps with `[x]`.
- Add date stamps for phase completion if needed.
- Keep this checklist current while working.

---

## 🎯 Quick Start

**Before writing any code, READ THESE FILES IN ORDER:**

1. **`CONTEXT.md`** ← ⭐ **CHECK FIRST!** Complete system specifications, architecture, flows, API reference
2. **`ASYNC-OPTIMIZATION-PLAN.md`** ← Async optimization plan + progress
3. **`QA-STRATEGY.md`** ← Async optimization QA strategy + tests
4. **`archive/2025-12-28-md-archive/TESTING.md`** ← 🧪 Complete testing guide (consolidated from 3 previous docs)
5. **`CLAUDE.md`** ← AI assistant working guide (context + what to do next)
6. **`archive/MULTI-TEMPLATE-REQUIREMENTS.md`** ← Multi-template requirements (completed)
7. **`archive/MULTI-TEMPLATE-DEV-PLAN.md`** ← Multi-template dev plan (completed)
8. **`archive/REPORTS-LISTING-REQUIREMENTS.md`** ← Reports listing requirements (implemented)
9. **`archive/DEV-PLAN.md`** ← Reports listing development plan (completed)
10. **`archive/DEVELOPMENT-PLAN.md`** ← ❌ **DEPRECATED** Historical record only (not current)

**⚠️ CRITICAL:**
- Always run automated tests (`npm test`) after changes
- Free user flow AND logged-in user flow must remain 100% functional
- Do NOT modify locked frontend files (`assets/script.js`, `assets/styles.css`)

---

## ⚡ Codex-Specific Guidelines

### Code Completion Best Practices

When generating code for this plugin:

**DO:**
- ✅ Use `SM_` prefix for all class names (e.g., `SM_Auth_Handler`)
- ✅ Use `sm_` prefix for database tables (e.g., `wp_sm_readings`)
- ✅ Use `$wpdb->prepare()` for ALL database queries (prevent SQL injection)
- ✅ Escape output: `esc_html()`, `esc_url()`, `esc_attr()`, `wp_kses()`
- ✅ Sanitize input: `sanitize_text_field()`, `sanitize_email()`, `absint()`
- ✅ Add nonces to forms and AJAX requests
- ✅ Follow WordPress Coding Standards (WP-CS)
- ✅ Add PHPDoc blocks for classes and methods
- ✅ Log errors to `wp-content/debug.log` with context

**DON'T:**
- ❌ Modify `assets/script.js` or `assets/styles.css` (frontend is locked)
- ❌ **Break the free user flow OR logged-in user flow** (both MUST work)
- ❌ **Skip running tests after changes** (`npm test`)
- ❌ Use raw `$_GET`, `$_POST`, `$_REQUEST` (use `sanitize_*` functions)
- ❌ Skip nonce verification on REST endpoints
- ❌ Hardcode URLs (use `home_url()`, `admin_url()`, etc.)
- ❌ Expose API keys in client-side code
- ❌ Skip error handling (always use try-catch for API calls)
- ❌ Make assumptions about existing code - read and understand first

### WordPress-Specific Patterns

**Database Queries:**
```php
// GOOD
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sm_readings WHERE email = %s AND account_id = %s",
        $email,
        $account_id
    )
);

// BAD
$results = $wpdb->get_results("SELECT * FROM wp_sm_readings WHERE email = '$email'");
```

**REST API Endpoints:**
```php
// GOOD
register_rest_route('soulmirror/v1', '/auth/callback', array(
    'methods' => 'POST',
    'callback' => array($this, 'handle_callback'),
    'permission_callback' => array($this, 'verify_nonce')
));

// BAD
register_rest_route('soulmirror/v1', '/auth/callback', array(
    'methods' => 'POST',
    'callback' => array($this, 'handle_callback'),
    'permission_callback' => '__return_true' // Insecure!
));
```

**API Calls:**
```php
// GOOD
$response = wp_remote_post($url, array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $jwt_token,
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode($data),
    'timeout' => 30
));

if (is_wp_error($response)) {
    SM_Logger::log('error', 'API call failed', array(
        'url' => $url,
        'error' => $response->get_error_message()
    ));
    return false;
}

// BAD
$response = file_get_contents($url); // No error handling, no headers
```

---

## 📁 File Structure Reference

### Core Plugin Files

**Authentication System (Complete):**
```
includes/
  class-sm-auth-handler.php      ← JWT validation, sessions, login/logout
  class-sm-credit-handler.php    ← Credit check, deduction
```

**Teaser Reading System (Complete):**
```
includes/
  class-sm-ai-handler.php        ← OpenAI integration, optimized prompt
  class-sm-teaser-reading-schema-v2.php  ← New schema
  class-sm-template-renderer.php ← HTML template population
```

**Database:**
```
includes/
  class-sm-database.php          ← Schema, migrations
```

**Templates:**
```
templates/
  container.php                  ← Main app container
  dashboard.php                  ← Logged-in user dashboard
palm-reading-template-teaser.html ← Traditional teaser report
palm-reading-template-full.html  ← Traditional paid report
palm-reading-template-swipe-teaser.html ← Swipeable teaser report
palm-reading-template-swipe-full.html  ← Swipeable paid report
```

**Assets:**
```
assets/
  js/script.js                   ← Main UI flow (LOCKED - do not modify)
  js/api-integration.js          ← Backend API calls
  css/styles.css                 ← Main styles (LOCKED - do not modify)
  css/auth.css                   ← Auth UI styling
  css/swipe-template.css         ← Swipeable template styles (scoped)
  js/swipe-template.js           ← Swipeable template logic (scoped)
```

---

## 📋 **Template System - Quick Reference**

### Template Options
- Traditional Scrolling Layout (default)
- Swipeable Card Interface

### Admin Setting
- **Option:** `sm_report_template`
- **Values:** `traditional` or `swipeable-cards`

### Key Files
- Swipeable templates: `palm-reading-template-swipe-teaser.html`, `palm-reading-template-swipe-full.html`
- Swipeable assets: `assets/css/swipe-template.css`, `assets/js/swipe-template.js`
- Admin setting: `includes/class-sm-settings.php` (option `sm_report_template`)
- Template selection: existing report rendering logic (select template based on option)

---

## 🧪 Testing (Complete Infrastructure)

### Automated Testing with Playwright

**MANDATORY:** Always run automated tests after changes.

**TWO complete test suites:**

#### **1. Unit Tests** (`tests/palm-reading-flow.spec.js`)
Fast page mechanics tests covering refresh scenarios, session management, and navigation.

```bash
npm run test:unit          # Unit tests only
npm run test:unit:headed   # With visible browser
```

#### **2. E2E Tests** (`tests/e2e-full-flow.spec.js`)
Complete user flow automation - **ZERO manual steps**.

**Capabilities:**
- ✅ Auto-generates test emails and retrieves OTPs from database
- ✅ Completes FULL flow automatically
- ✅ Tests unlock + refresh + back button navigation
- ✅ Takes 20+ screenshots
- ✅ Captures ALL logs & network calls

```bash
npm run test:e2e:headed    # E2E with browser (RECOMMENDED)
npm test                   # ALL tests (unit + E2E)
```

### ✅ Recommended Test Suites (always provide commands)
- **Smoke (fast, current changes):** `E2E_BASE_URL=https://sm-palm-reading.local npm run test:e2e:headed`
- **Focused (current implementation):** `E2E_BASE_URL=https://sm-palm-reading.local npx playwright test tests/async-optimization.spec.js`
- **Full regression:** `E2E_BASE_URL=https://sm-palm-reading.local npm test`

### ✅ Test Expectations
- Add/extend tests for all new flows.
- After each run, review results and summarize failures.
- Suggest concrete code fixes based on the test output.
- Keep the suite comprehensive and up to date.
- Review results autonomously when available by inspecting `test-results/test-results.json` and `test-results/html-report`.

**For complete testing documentation, see `archive/2025-12-28-md-archive/TESTING.md`**

---

### 📋 **Logging & Debugging**

**Current Status:** System is stable - logging remains active for maintenance.

**Metrics Reporting (When Asked to Improve Reports/Readings):**
- OpenAI call count and per-call token usage (prompt/completion/total).
- Fallbacks and retries used (image retry, rescue prompts, expansions).
- Palm summary extraction result (hand type, lines, mounts, markings, overall energy).
- Palm image validation attempts, lockouts, and paid-credit deductions (if any).
- QA pass/fail counts by section for the run.
- Note if palm summary was image-based or blocked due to invalid palm image (and why).
- Call out any schema/word-count overshoots as well as short fields.

**Log Location & Tags:**
- `wp-content/debug.log` (shown in WP Admin → Palm Reading → Settings).
- `AI_READING` entries track OpenAI call counts, tokens, retries/rescues, and payload summaries.
- `SM_QA` entries track per-section word counts and schema validation warnings (relaxed mode).
- `AI_READING` and `SM_QA` metrics are always logged even when debug logging is disabled.

**Check Logs:**
```bash
# PHP backend logs
tail -f /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/debug.log

# Search specific errors
grep -i "SM" /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/debug.log | tail -50

# JavaScript console
# Open DevTools (F12) → Console → Filter by "[SM"

# Test logs (automatic)
npm run test:e2e:headed  # Captures ALL logs
npm run test:report      # View HTML report
```

**Debugging Workflow:**
1. Run `npm run test:e2e:headed` - catches issues with full logs
2. Check `test-results/*.png` - visual proof
3. Check console output - all `[SM]` messages
4. Check `wp-content/debug.log` if backend issue
5. Fix issue with full context
6. Re-run test - verify fix
7. ✅ Green = fixed + won't regress

---

### Testing Workflow

**Before Committing Code:**
- [ ] **Run automated tests** (`npm test`)
- [ ] Test in DevMode (avoid API costs)
- [ ] Verify no regressions (free + logged-in flows work)
- [ ] Check browser console (no JavaScript errors)
- [ ] Check `wp-content/debug.log` (no PHP errors)

**After Completing Any Task:**
- [ ] ✅ Test the specific functionality
- [ ] ✅ Run full test suite (`npm test`)
- [ ] ✅ Verify backward compatibility
- [ ] ✅ **Update archive/MULTI-TEMPLATE-DEV-PLAN.md** (if documenting historical changes)
- [ ] ✅ Update documentation if needed

**Dev-Plan Update Instructions:**
- Mark completed tasks with `[x]`
- Update progress bars proportionally
- Add bugs/issues to tracking table
- Update phase completion percentage

**Testing Shortcuts:**
```bash
# Run all automated tests
npm test

# Enable DevMode
WordPress Admin → Palm Reading → Settings → DevMode checkbox

# Watch debug.log
tail -f wp-content/debug.log
```

---

## 🔐 Security Checklist

When writing code, always verify:
- [ ] All database queries use `$wpdb->prepare()`
- [ ] All output escaped (`esc_html`, `esc_url`, etc.)
- [ ] All input sanitized (`sanitize_text_field`, etc.)
- [ ] Nonces verified on forms and AJAX
- [ ] JWT tokens stored securely (sessions/httponly cookies)
- [ ] HTTPS enforced for Account Service URLs
- [ ] No secrets in client-side code
- [ ] Rate limiting on public endpoints

---

## 📚 Reference Documents

**Core Documentation (Active):**

| Document | Purpose | Status |
|----------|---------|--------|
| `CONTEXT.md` | ⭐ **SINGLE SOURCE OF TRUTH** - All requirements, architecture, flows, API reference | Complete |
| `ASYNC-OPTIMIZATION-PLAN.md` | Async optimization plan + progress tracker | Active |
| `QA-STRATEGY.md` | Async optimization QA strategy and test coverage | Active |
| `archive/MULTI-TEMPLATE-REQUIREMENTS.md` | Multi-template enhancement requirements | ✅ Complete |
| `archive/MULTI-TEMPLATE-DEV-PLAN.md` | Multi-template development plan | ✅ Complete |
| `archive/REPORTS-LISTING-REQUIREMENTS.md` | 📋 Reports listing requirements | ✅ Complete |
| `archive/DEV-PLAN.md` | ✅ Reports listing development plan | ✅ Complete |
| `archive/2025-12-28-md-archive/TESTING.md` | 🧪 **TESTING GUIDE** - Automated testing (consolidated from 3 docs) | Complete |
| `CLAUDE.md` | AI assistant instructions and constraints | Complete |
| `README.md` | Plugin overview | Complete |

**Archived Documentation** (Reference Only):
- `archive/DEVELOPMENT-PLAN.md` → ❌ **DEPRECATED** Previous development plan (no longer relevant)
- `archive/archive/E2E-AUTOMATION-GUIDE.md` → Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/archive/E2E-QUICK-START.md` → Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/archive/README-TESTING.md` → Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/NEXT-STEPS.md` → No longer applicable
- `archive/ACCOUNT-AUTH-INTEGRATION-REQUIREMENTS.md` → Consolidated into `CONTEXT.md`
- `archive/TEASER-REBALANCE-REQUIREMENTS.md` → Consolidated into `CONTEXT.md`
- `archive/integration-guide.md` → Consolidated into `CONTEXT.md`

**Benefits:**
- ✅ Single source of truth
- ✅ Clear, consolidated documentation
- ✅ Easy to navigate
- ✅ System is stable and complete

---

## 🎯 Quick Commands (for Copilot Chat)

**Understand system:**
> "Explain the complete system architecture from CONTEXT.md"

**Reports listing feature:**
> "Show me the implementation plan for reports listing from archive/REPORTS-LISTING-REQUIREMENTS.md"

**Account linking:**
> "Help me implement the account linking feature (Phase 0)"

**Check testing:**
> "How do I run the automated tests?"

**Security review:**
> "Review this code for WordPress security best practices"

**Understand flows:**
> "Explain the free user flow vs logged-in user flow"

---

**Last Updated:** 2025-12-27
**Version:** 4.1.0 (Multi-Template System Complete)
**Maintained By:** Development Team

---

## ⚠️ REMEMBER - The Non-Negotiables:

1. **Check `CONTEXT.md` FIRST** - Understand the complete stable system
2. **Run tests after changes** - `npm test` to catch regressions
3. **DO NOT break user flows** - Free AND logged-in flows must remain 100% functional
4. **Respect locked frontend files** - Do NOT modify `assets/script.js` or `assets/styles.css`
5. **Test in DevMode first** - Avoid API costs during development
6. **System is stable** - Focus on maintaining quality and stability

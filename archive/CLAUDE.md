# CLAUDE.md

AI Assistant Guide for Mystic Palm Reading Plugin (SoulMirror)

## ✅ **Current Development Status**

**Status:** ✅ **STABLE & COMPLETE** - Core Development Finished (Enhancements Only)
**Last Updated:** 2025-12-30

---

## 🎉 **RECENTLY COMPLETED: Unauthenticated Report Link Redirect Flow**

**Status:** ✅ COMPLETE - Seamless login flow for shared paid reports
**Completed:** 2025-12-30

Users can now click async-generated paid report links from email and seamlessly authenticate to view their report, with the full URL preserved through the Account Service login flow.

**Key Features:**
- ✅ Automatic redirect to Account Service login when accessing paid reports while not logged in
- ✅ Session-based redirect preservation (immune to URL parameter corruption)
- ✅ WordPress authentication bypass for report URLs (JWT-based auth only)
- ✅ External domain whitelist for secure Account Service redirects

**Files Modified:**
- `mystic-palm-reading.php` - WordPress auth bypass, redirect logic
- `includes/class-sm-auth-handler.php` - Session redirect resolution, JWT callback

---

## 🚧 **ACTIVE PROJECT: Teaser Content Quality Enhancement**

**Status:** ⚡ IN PROGRESS - Content Optimization & API Efficiency
**Started:** 2025-12-30
**Plan:** `archive/TEASER-CONTENT-OPTIMIZATION.md`

### Project Goals

1. **✅ COMPLETED: Fix Duplicate API Calls**
   - **Was:** 4 calls per teaser (100% overhead due to duplicate job execution)
   - **Now:** 2 calls per teaser (fixed duplicate job dispatch bug)
   - **Impact:** 50% API cost reduction, faster generation

2. **Eliminate Wasted API Fields (28% reduction)**
   - Remove 5× `locked_teaser` fields (replaced with hardcoded text, never displayed)
   - Remove 3× `modal_*` fields in `career_success` (not used anywhere)
   - **Result:** 29 fields → 21 fields (cleaner, more efficient)

3. **Replace Locked Teasers with Dynamic Quotes**
   - 200 inspirational quotes (5 sections × 2 genders × 20 quotes)
   - Gender-specific, deterministic selection
   - Stored in `includes/teaser-quotes.json`
   - **Benefit:** No API waste, dynamic user experience

4. **Strengthen Content Depth (50-70% richer)**
   - Foundations: 160-245w → **290-390w** (+60% depth)
   - Previews: 40-60w each → **120-160w each** (~10 sentences per section)
   - **Result:** More valuable, substantive teaser readings

### Implementation Phases

**Phase 1:** Quote System (Dynamic inspirational quotes)
**Phase 2:** Remove Wasted Fields (Schema cleanup)
**Phase 3:** Strengthen Content Targets (Deeper insights)

### Current Focus

**MUST READ FIRST:**
- `archive/TEASER-CONTENT-OPTIMIZATION.md` - ⭐ **ACTIVE PLAN** - Complete implementation guide
- `CONTEXT.md` - System architecture and constraints

**Files to Modify:**
- `includes/class-sm-quote-handler.php` - NEW: Quote selection logic
- `includes/teaser-quotes.json` - NEW: 200 quote database
- `includes/class-sm-ai-handler.php` - Update prompts + word counts
- `includes/class-sm-teaser-reading-schema.php` - Remove wasted fields
- `includes/class-sm-template-renderer.php` - Pass quotes to templates

---

## 🚧 **Previous Optimization Work (Reference)**

### Async Reading Generation (Completed)
- Fixed duplicate job execution bug in `class-sm-reading-job-handler.php`
- Reduced API calls: Teaser 4→2, Paid 6→3
- See: `archive/2025-12-30-async-optimization-docs/` for full history
- Outstanding items: `archive/2025-12-30-remaining-issues.md`

### Report Quality Investigation (Completed)
- `archive/2025-12-28-md-archive/INVESTIGATION.md` - Context and findings
- `archive/2025-12-28-md-archive/TEASER-STABILIZATION-PLAN.md` - Output hardening
- `archive/2025-12-28-md-archive/UPGRADE-FULL-REPORT-PLAN.md` - Upgrade flow

---
## 🎉 **System Status: Complete & Stable**

### **Core Development Completed:**

All major feature implementations have been successfully completed and are now stable:

1. ✅ **Account Service Authentication Integration** - SSO login, JWT tokens, credit-based paid readings, and persistent user accounts
2. ✅ **Teaser Reading Rebalance** - Optimized OpenAI token usage (30-40% reduction) and improved free/paid content distinction
3. ✅ **Flow Session Stabilization** - Server-side state management for robust user experience
4. ✅ **Automated Testing Infrastructure** - Complete E2E and unit test coverage
5. ✅ **Bug Fixes & UI Refinements** - All critical issues resolved

### **System is Production-Ready:**
- Free user flow fully functional and stable
- Logged-in user flow complete with dashboard
- Comprehensive automated testing (see `archive/2025-12-28-md-archive/TESTING.md`)
- All documentation consolidated and current

---

## 📚 **Documentation Structure**

**Core References (Read in Order):**

1. **`CONTEXT.md`** - ⭐ **SINGLE SOURCE OF TRUTH** - Complete system specifications
   - All requirements and architecture details
   - User flows and API reference
   - Security requirements and testing strategy

2. **`ASYNC-OPTIMIZATION-PLAN.md`** - Async optimization plan + progress
3. **`QA-STRATEGY.md`** - Async optimization QA strategy + tests
4. **`archive/2025-12-28-md-archive/TESTING.md`** - 🧪 **COMPREHENSIVE TESTING GUIDE** - Automated test documentation
   - E2E and unit test suites (consolidated from 3 previous docs)
   - How to run tests, interpret results, and add new tests
   - Test helper API and debugging workflows

5. **This file (`CLAUDE.md`)** - AI assistant working guide (context + what to do next)
6. **`archive/MULTI-TEMPLATE-REQUIREMENTS.md`** - Multi-template enhancement requirements (completed)
7. **`archive/MULTI-TEMPLATE-DEV-PLAN.md`** - Multi-template development plan (completed)
   - Current system status and constraints
   - Development workflow and conventions

**Archived Documentation** (Reference Only):
- `archive/MULTI-TEMPLATE-REQUIREMENTS.md` - Multi-template enhancement requirements (completed)
- `archive/MULTI-TEMPLATE-DEV-PLAN.md` - Multi-template development plan (completed)
- `archive/DEV-PLAN.md` - Full paid reports development plan (archived - completed)
- `archive/REPORTS-LISTING-REQUIREMENTS.md` - Reports listing requirements (implemented)
- `archive/DEV-PLAN.md` - Reports listing development plan (completed)
- `archive/DEVELOPMENT-PLAN.md` - **DEPRECATED** - Previous development plan (no longer relevant)
- `archive/archive/E2E-AUTOMATION-GUIDE.md` - Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/archive/E2E-QUICK-START.md` - Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/archive/README-TESTING.md` - Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/NEXT-STEPS.md` - No longer applicable (development complete)
- `archive/ACCOUNT-AUTH-INTEGRATION-REQUIREMENTS.md` - Consolidated into `CONTEXT.md`
- `archive/TEASER-REBALANCE-REQUIREMENTS.md` - Consolidated into `CONTEXT.md`
- `archive/integration-guide.md` - Consolidated into `CONTEXT.md`

---

## 🚀 **Current Phase: Enhancements**

**Status:** Multi-Template System complete. Focus on stability and regressions only.

**Archive:** `archive/MULTI-TEMPLATE-REQUIREMENTS.md`, `archive/MULTI-TEMPLATE-DEV-PLAN.md`

**Previous Phase (COMPLETE):** Full paid reports development plan archived to `archive/DEV-PLAN.md`

---

## Current Context

### Plugin Overview
- WordPress plugin providing AI-powered palm reading teaser experiences
- **Stack:** WordPress 5.8+, PHP 7.4+
- **APIs:** OpenAI GPT-4o (Vision + text), MailerLite v3, **SoulMirror Account Service (new)**
- **Naming:** `SM_` for PHP, `sm_` for DB tables
- **Frontend flow:** 12-step quiz (email → OTP → palm photo → questions → AI reading)

### Core Architecture
- **Teaser Reading System:**
  - OpenAI returns structured JSON (no HTML)
  - Store full JSON blob in `wp_sm_readings.content_data`
  - Unlock model: 2 free unlocks, third attempt redirects to offerings page
  - Unlocked sections stored as JSON array; backend is source of truth

### Report + OTP Refresh Behavior (Do Not Regress)
- **Report refresh (paid or existing):** The report URL is tagged with `sm_report=1` to bypass the dashboard gate in `mystic-palm-reading.php` so refresh stays on the report.
- **Report state keys:** `sm_reading_loaded`, `sm_reading_lead_id`, `sm_reading_token`, `sm_existing_reading_id`.
- **Step restore key:** `sm_flow_step_id` stores the current step id on each render to restore mid-flow refreshes (OTP, quiz, etc.).
- **Guardrail:** If `sm_reading_loaded` is set while `sm_flow_step_id` is not `result`/`resultLoading`, clear the report flag so OTP refreshes resume properly.
- **Paywall redirect:** Clear all reading/session keys before redirect and set `sm_paywall_redirect=1` so back navigation returns to a clean initial page.
- **Premium lock overlay:** Header is layered above the overlay; premium lock card is padded below the header to avoid covering section titles.

- **Authentication System (New):**
  - SSO via SoulMirror Account Service
  - JWT token-based authentication
  - Optional login - free flow remains completely open
  - Account linking for existing free readings

- **Credit System (New):**
  - Check credits before paid reading generation
  - Deduct credits after successful generation
  - Redirect to shop if insufficient credits
  - Idempotency prevents duplicate charges

---

## Core Constraints

### Code Integrity
- **Frontend is locked:** Do NOT modify `assets/script.js` or `assets/styles.css` unless explicitly requested in requirements
- Sanitize/validate all inputs; escape all outputs
- Use `$wpdb->prepare` for all database queries
- Require nonces and rate limiting on REST endpoints
- Never expose secrets or raw OTPs; store API keys in `wp_options`

### Backward Compatibility
- **Free user flow must remain unchanged** - Authentication is optional
- Existing readings must display correctly (both old and new schema)
- OTP verification must continue to work for free users
- No regressions in existing functionality

### Security Requirements
- JWT tokens stored securely (httponly cookies or WordPress sessions)
- HTTPS enforced for Account Service integration
- No tokens exposed in client-side JavaScript
- Email check rate-limited (prevent enumeration attacks)
- All API calls use proper nonces and authorization

---

## Development Workflow

### Before Starting Any Work:
1. **Read `CONTEXT.md`** - Understand complete system architecture and specifications
2. **Review `archive/2025-12-28-md-archive/TESTING.md`** - Understand testing infrastructure
3. **Understand existing functionality** - You MUST NOT break the free user flow or logged-in user flow

### During Development (Maintenance & Enhancement):
- Use DevMode for testing to avoid API costs
- Run tests frequently (`npm test`) to catch regressions early
- Log all errors to `wp-content/debug.log` with context
- **Respect existing functionality** - Do not modify locked frontend files (`assets/script.js`, `assets/styles.css`)
- **Do not break existing flows** - Free user path AND logged-in user path must remain 100% functional
- Document any new features or changes

### After Completing Each Task:
- ✅ Test the specific functionality you just implemented
- ✅ Run full test suite (`npm test`) to verify no regressions
- ✅ Verify backward compatibility (existing readings, flows still work)
- ✅ **Update archive/MULTI-TEMPLATE-DEV-PLAN.md** (if documenting historical changes)
- ✅ Update documentation if behavior changes
- ✅ Check `wp-content/debug.log` for errors

**How to Update Dev-Plan:**
1. Mark completed tasks: `- [ ]` → `- [x]`
2. Update progress bar: `[....]` → `[####]` (proportional to completion)
3. Update phase percentage
4. Add any bugs found to Issues & Bugs section
5. When phase complete, mark as `✅ COMPLETE` and add completion date

---

## Testing Strategy

### DevMode
- Use DevMode for testing to avoid API costs
- See `DEVMODE.md` for setup and safety notes
- Mock Account Service API calls when DevMode enabled
- Mock OpenAI API calls for teaser generation

### Testing Checkpoints
- Each phase in `DEVELOPMENT-PLAN.md` has specific testing checkpoints
- **Critical:** Always run regression tests to ensure free user flow unchanged
- Test with real Account Service staging environment before production
- Cross-browser testing (Chrome, Firefox, Safari, Edge, Mobile)

### Regression Testing (Run After Every Phase)
- [ ] Free user flow (email → OTP → quiz → teaser) works
- [ ] Existing readings display correctly
- [ ] OTP verification works
- [ ] No JavaScript errors
- [ ] No PHP errors in `wp-content/debug.log`
- [ ] Email validation works
- [ ] MailerLite integration works

---

## Key Architecture Decisions

### Database Schema
- **Added columns:** `account_id` to `wp_sm_readings` and `wp_sm_leads` tables
- **Purpose:** Link readings to Account Service user accounts
- **Value:** NULL for free readings, Account Service user ID for logged-in users

### Authentication Flow
- **Entry point check:** Detect if user has valid JWT session
- **Email check:** Route users based on email/account status
  - Email NOT found → Continue to OTP (free user)
  - Email found + account_id → Redirect to login (returning user with account)
  - Email found + no account_id → Encourage account creation
- **JWT callback:** Handle redirect from Account Service with JWT token
- **Account linking:** Automatically link existing free readings when users log in

### Credit System Flow
1. **Before generation:** Check if user has credits (API call to Account Service)
2. **If insufficient:** Redirect to shop with service slug and return URL
3. **If sufficient:** Proceed with reading generation
4. **After generation:** Deduct 1 credit with idempotency key (prevent duplicates)

### Teaser Reading Schema
- **Old schema (v1):** Includes `locked_full` fields (750-1,050 words of paid content generated upfront)
- **New schema (v2):** Removes `locked_full` fields, reduces to 700-900 word teaser
- **Token reduction:** 30-40% fewer tokens per teaser reading
- **New premium sections:** 5 placeholder sections for future paid content generation

---

## File Structure & Conventions

### New Files Created (During Implementation)
```
includes/
  class-sm-auth-handler.php          # JWT validation, session management, login/logout
  class-sm-credit-handler.php        # Credit check, deduction, error handling
  class-sm-teaser-reading-schema-v2.php  # New optimized schema

templates/
  dashboard.php                      # Logged-in user dashboard
  my-readings.php                    # Future: My Readings page (placeholder)

assets/
  css/auth.css                       # Styling for login button, dashboard
  js/auth.js                         # Optional: Client-side auth logic
```

### Modified Files
```
includes/
  class-sm-database.php              # Add account_id columns, migration
  class-sm-rest-controller.php       # Email check logic, account_id handling
  class-sm-lead-capture.php          # Check account_id, redirect to login
  class-sm-ai-handler.php            # New teaser prompt, credit deduction
  class-sm-settings.php              # Account Service settings page

templates/
  container.php                      # Login button, detect logged-in state

assets/
  js/api-integration.js              # Skip email/OTP for logged-in users
```

---

## External Service Integration

### SoulMirror Account Service
- **Base URL:** Configurable in admin settings (default: `https://account.soulmirror.com`)
- **Service Slug:** `palm-reading`
- **Auth Callback:** `{site_url}/palm-reading/auth/callback`

**API Endpoints Used:**
- `POST /wp-json/soulmirror/v1/auth/validate` - Validate JWT token
- `POST /wp-json/soulmirror/v1/credits/check` - Check credit availability
- `POST /wp-json/soulmirror/v1/credits/deduct` - Deduct 1 credit

**Reference:** See `integration-guide.md` for complete API documentation

### OpenAI GPT-4o
- **Vision API:** Palm photo analysis
- **Chat Completion:** Teaser reading generation
- **New Prompt (Phase 5):** Optimized for 700-900 words, removes locked_full generation
- **Token Reduction Target:** 30-40% vs old prompt

### MailerLite v3
- **Purpose:** Email marketing, lead syncing
- **Integration:** Continues to work unchanged (no modifications needed)

---

## Archived Documentation (Historical Context)

These documents provide historical context but are **NOT active requirements**:
- `archive/TEASER-READING-REQUIREMENTS.md` - Original teaser reading specs
- `archive/TEASER-READING-DEV-PLAN.md` - Original teaser development plan
- `archive/requirements.md` - Original plugin requirements
- `archive/business-requirements.md` - Original business specs
- `archive/technical-requirements.md` - Original technical specs
- `archive/dev-plan.md` - Original development plan
- `archive/progress.md` - Historical progress tracking
- `archive/BUGS-LOG.md` - Historical bug log
- `archive/CHANGELOG-v1.3.8.md` - v1.3.8 changelog

**Note:** These are archived for reference only. Awaiting new requirements.

---

## Working Mode

### For AI Assistants (Claude, Codex, Gemini)

**Priority Order:**
1. **CONTEXT.md** - ⭐ **CHECK FIRST** - Complete system specifications and architecture
2. **archive/2025-12-28-md-archive/TESTING.md** - Complete testing guide (consolidated testing documentation)
3. **This file (CLAUDE.md)** - AI assistant working guide and constraints
4. `archive/MULTI-TEMPLATE-REQUIREMENTS.md` - Multi-template requirements (completed)
5. `archive/MULTI-TEMPLATE-DEV-PLAN.md` - Multi-template development plan (completed)
6. `archive/REPORTS-LISTING-REQUIREMENTS.md` - Reports listing requirements (implemented)
7. `archive/DEV-PLAN.md` - Reports listing development plan (completed)
8. `archive/DEVELOPMENT-PLAN.md` - (Reference only) Historical record of completed development

**Key Principles:**
- System is stable and complete - focus on maintaining stability
- All changes must pass automated tests (`npm test`)
- Use DevMode for testing to avoid API costs
- Run regression tests after every change
- Document any new features or changes
- Ask clarifying questions if requirements are ambiguous

**Critical Rules:**
- ❌ **DO NOT** modify frontend (`assets/script.js`, `assets/styles.css`) unless explicitly requested
- ❌ **DO NOT** break free user flow (must remain 100% functional)
- ❌ **DO NOT** break logged-in user flow (must remain 100% functional)
- ❌ **DO NOT** skip running tests (`npm test`)
- ✅ **DO** use DevMode for testing
- ✅ **DO** run Playwright UI automation for ALL UI changes
- ✅ **DO** validate backward compatibility (old readings still work)
- ✅ **DO** respect existing functionality - understand before modifying

### 🧪 **AUTOMATED TESTING PROTOCOL** (MANDATORY)

**Rule: Every agreed-upon page behavior MUST have an automated test.**
**Request to run Playwright UI automation** instead of doing manual UI testing each time.

We now have **TWO test suites**:

#### **1. Unit Tests** (`tests/palm-reading-flow.spec.js`)
Fast page mechanics tests (refresh, session, navigation).

**Run:**
```bash
npm run test:unit          # Unit tests only
npm run test:unit:headed   # With visible browser
```

#### **2. E2E Tests** (`tests/e2e-full-flow.spec.js`) ⭐ **NEW!**
Complete user flow automation - **ZERO manual steps required**.

**What it does:**
- ✅ Generates unique test emails automatically (`test-{timestamp}@example.com`)
- ✅ Retrieves OTP codes from database (no manual Mailpit checking)
- ✅ Completes FULL flow: Welcome → Lead → OTP → Photo → Quiz → Report
- ✅ Tests unlock behavior with seeded readings
- ✅ Tests paywall redirect + back button navigation
- ✅ Takes 20+ screenshots at every step
- ✅ Captures ALL console logs, network calls, session state
- ✅ Generates beautiful HTML report

**Run:**
```bash
npm run test:e2e           # E2E tests (headless)
npm run test:e2e:headed    # E2E with visible browser (RECOMMENDED)
npm run test:e2e:ui        # Interactive UI mode
npm test                   # ALL tests (unit + E2E)
```

**Recommended Test Suites (always provide commands):**
- **Smoke (fast, current changes):** `E2E_BASE_URL=https://sm-palm-reading.local npm run test:e2e:headed`
- **Focused (current implementation):** `E2E_BASE_URL=https://sm-palm-reading.local npx playwright test tests/async-optimization.spec.js`
- **Full regression:** `E2E_BASE_URL=https://sm-palm-reading.local npm test`

**Test Hygiene Expectations:**
- Add/extend tests whenever new flows are implemented.
- After any run, review the results and summarize failures.
- Suggest concrete code fixes based on test output.
- Keep the suite comprehensive and up to date.
- Review results autonomously when available by inspecting `test-results/test-results.json` and `test-results/html-report`.

**Test Helper API:**
The plugin now includes test helper endpoints (DevMode only):
- `GET /wp-json/soulmirror-test/v1/get-otp?email=X` - Auto-retrieve OTP
- `POST /wp-json/soulmirror-test/v1/seed-reading` - Instantly create complete reading
- `POST /wp-json/soulmirror-test/v1/cleanup` - Delete test data

**Playwright Base URL:**
- Local default: `https://sm-palm-reading.local/`
- Override via `E2E_BASE_URL` when running tests against a different host

**Location:** `includes/class-sm-test-helpers.php`
**Security:** Only active when DevMode is enabled

**How to Add E2E Tests:**
```javascript
test('Your test scenario', async ({ page }) => {
  const consoleLogs = [];
  const apiCalls = [];
  setupMonitoring(page, consoleLogs, apiCalls);

  // Generate unique test email
  const testEmail = E2EHelpers.generateTestEmail();

  // OR seed a reading instantly (bypass form for speed)
  const seeded = await E2EHelpers.seedReading(testEmail, 'Test User');

  // Navigate to report
  await page.goto(`/?sm_report=1&lead_id=${seeded.lead_id}`);

  // Take screenshot
  await E2EHelpers.takeScreenshot(page, 'description', '01');

  // Log current state
  await E2EHelpers.logState(page, 'After Navigation');

  // Test behavior...

  // Assertions
  expect(has500).toBe(false);
});
```

**Test Output:**
- 📸 **20+ screenshots** per test run (every major step)
- 📋 **Complete console logs** (all `[SM]` messages automatically captured)
- 🌐 **Network requests** (all API calls logged)
- 📊 **Session state** (logged at every checkpoint)
- 🎥 **HTML report** with timeline viewer

**See `archive/2025-12-28-md-archive/TESTING.md` for complete testing documentation** (consolidated from archive/E2E-AUTOMATION-GUIDE.md, archive/E2E-QUICK-START.md, and archive/README-TESTING.md)

---

## 📋 **LOGGING & DEBUGGING**

**Current Status:** System is stable and complete - logging remains active for maintenance and future development.

### **Why Logging is Important:**
- Helps diagnose issues quickly when they arise
- Automated tests capture logs automatically for debugging
- Provides audit trail for system behavior

### **Where Logs Are:**

SM_Logger writes to `wp-content/debug.log` (see WP Admin → Palm Reading → Settings). `AI_READING` entries track OpenAI call counts/tokens and rescue attempts; `SM_QA` entries capture per-section word counts and schema warnings. These metrics are always logged even when debug logging is off.

**1. PHP Backend Logs:**
```bash
# Watch WordPress debug.log
tail -f /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/debug.log

# Search for specific errors
grep -i "SM" /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/debug.log | tail -50
```

**2. JavaScript Console Logs:**
- Open browser DevTools (F12)
- Console tab
- Filter by `[SM` to see all plugin logs

**3. Automated Test Logs:**
```bash
# E2E test output includes ALL logs automatically
npm run test:e2e:headed

# Check console output - shows all [SM] messages
# Check screenshots - test-results/*.png
# Check HTML report - npm run test:report
```

### **Logging Best Practices (For Development):**

**PHP Logging:**
```php
SM_Logger::log('info', 'UNLOCK', 'Section unlock attempted', array(
    'reading_id' => $reading_id,
    'section_id' => $section_id,
    'unlocks_remaining' => $unlocks_remaining,
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
));
```

**JavaScript Logging:**
```javascript
console.log('[SM DEBUG] Current state:', {
    reading_loaded: sessionStorage.getItem('sm_reading_loaded'),
    lead_id: sessionStorage.getItem('sm_reading_lead_id'),
    flow_step: sessionStorage.getItem('sm_flow_step_id'),
    url: window.location.href
});
```

### **Debugging Workflow:**

**When fixing a bug:**

1. **Request to run E2E test first** - catches the bug automatically with full logs
   ```bash
   npm run test:e2e:headed
   ```

2. **Check test screenshots** - visual proof of issue
   ```bash
   ls -lt test-results/*.png | head -5
   open test-results/e2e-03-after-unlock-1-refresh.png
   ```

3. **Check test console output** - all logs captured
   ```
   📋 [SM DEBUG] Session state check: {...}
   📋 [SM API] GET flow/state
   🚨 API Error: unlock/section → 500
   ```

4. **Check PHP debug.log** if backend issue
   ```bash
   tail -f wp-content/debug.log
   ```

5. **Fix the bug** with full context from logs

6. **Re-run test** - verify fix
   ```bash
   npm run test:e2e:headed
   ```

7. **✅ Green test = bug fixed + won't regress**

### **Toggle Logs Later:**

Once bugs are fixed and tests are green, we can reduce logging:
- Remove verbose `console.log` statements
- Keep error logging (`SM_Logger::log('error', ...)`)
- Keep critical state changes
- Tests will still capture everything when needed

**For now: Log everything. Fix bugs fast. Clean up later.**

---

## Quick Reference

### Current Focus
**Template System (Complete)**

The plugin supports two report templates:
- **Traditional Scrolling Layout** (default)
- **Swipeable Card Interface**

**Admin Setting:** WordPress Admin → Palm Reading → Settings → Report Template  
**Option:** `sm_report_template` (`traditional` or `swipeable-cards`)

**Files:**
- Traditional: `palm-reading-template-teaser.html`, `palm-reading-template-full.html`
- Swipeable: `palm-reading-template-swipe-teaser.html`, `palm-reading-template-swipe-full.html`
- Swipeable assets: `assets/css/swipe-template.css`, `assets/js/swipe-template.js`

**Specs:** `archive/MULTI-TEMPLATE-REQUIREMENTS.md`

### Testing Shortcuts
```bash
# Enable DevMode
Go to WordPress Admin → Palm Reading → Settings → Enable DevMode

# Check debug.log
tail -f wp-content/debug.log

# Run all tests
npm test

# Run E2E tests with visible browser
npm run test:e2e:headed
```

---

## Support & Escalation

### During Development
- **Requirements Question:** Review `CONTEXT.md` first, then ask
- **Technical Blocker:** Check existing implementation, review logs, run tests
- **Account Service API Issues:** Contact integrations@soulmirror.com
- **OpenAI API Issues:** Check status page, adjust timeouts

### Post-Launch
- **User Auth Issues:** Check JWT validation logs, Account Service uptime
- **Credit Issues:** Check idempotency logs, verify Account Service API
- **Reading Failures:** Check OpenAI logs, schema validation errors

---

## 🐛 Recent Bug Fixes (v3.0.1 - 2025-12-26)

### Critical: Paid Report Refresh Bug
**Issue:** After refreshing a paid report page, the report would display as a teaser with blurred sections and missing lock buttons.

**Root Cause:** The `handle_reading_get_by_lead()` function in `class-sm-rest-controller.php` (line 2228) was missing extraction of the `reading_type` parameter from the request, causing an "Undefined variable" PHP warning and defaulting all refreshed reports to teaser mode.

**Fix:** Added line 2242 to extract the `reading_type` parameter:
```php
$reading_type = $this->sanitize_string( $request->get_param( 'reading_type' ) );
```

**Files Modified:**
- `includes/class-sm-rest-controller.php` (line 2242)

**Impact:** ✅ Paid reports now load correctly after page refresh for logged-in users.

---

### UI Fix: Back to Dashboard Button Hover Effect
**Issue:** When hovering over the "Back to Dashboard" button on paid reports, the text would disappear.

**Root Cause:** CSS class specificity conflict between `.action-btn` and `.btn-secondary` classes causing color override on hover state.

**Fix:** Added explicit hover state rules with `!important` to maintain text visibility:
```css
.action-btn.btn-secondary:hover {
  background: linear-gradient(135deg, var(--mystic-primary), var(--mystic-secondary));
  color: white !important;
}
```

**Files Modified:**
- `palm-reading-template-full.html` (lines 1003-1010)

**Impact:** ✅ "Back to Dashboard" button text remains visible on hover.

---

### UX Enhancement: Disable Back Button on Paid Reports
**Issue:** Users could navigate away from paid reports using browser back button, creating confusion and poor UX.

**Fix:** Implemented browser back button prevention for paid reports:
1. Push history state when paid report loads (`api-integration.js` line 606-609)
2. Intercept `popstate` events and block navigation (`script.js` line 2625-2630)

**Files Modified:**
- `assets/js/api-integration.js` (lines 605-609)
- `assets/js/script.js` (lines 2619-2643)

**Impact:** ✅ Users cannot accidentally navigate away from paid reports. Only "Back to Dashboard" button works.

**Behavior:**
- **Paid reports:** Back button disabled, must use "Back to Dashboard" button
- **Teaser reports:** Back button clears session and returns to welcome page

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-12-20 | Initial version - pre-integration baseline |
| 2.0.0 | 2025-12-20 | **Updated for active development:** Added development plan references, current priorities, Account Service integration |
| 2.1.0 | 2025-12-20 | **Added progress tracking:** Emphasized frequent updates and respecting existing functionality |
| 2.2.0 | 2025-12-21 | **Merged progress into DEVELOPMENT-PLAN.md:** Progress Tracker + Completed Work Log now live there |
| 2.2.1 | 2025-12-23 | **Documented report/OTP refresh flow** and paywall redirect guardrails |
| 3.0.0 | 2025-12-26 | **MAJOR REVISION:** Deprecated old development plan, archived to `archive/DEVELOPMENT-PLAN.md`. New focus: Dashboard – User Reports Listing. Updated all references and documentation structure. |
| 3.0.1 | 2025-12-26 | **BUG FIX MILESTONE:** Fixed critical paid report refresh bug (missing `reading_type` parameter in `handle_reading_get_by_lead`). Fixed "Back to Dashboard" button hover effect (text disappearing). Disabled browser back button navigation for paid reports. |
| 4.0.0 | 2025-12-27 | **NEW PHASE:** Full paid reports development complete and archived (`archive/DEV-PLAN.md`). New focus: Multi-Template System Enhancement. Created `archive/MULTI-TEMPLATE-REQUIREMENTS.md` for swipeable card-based template support alongside existing traditional template. |

---

**Last Updated:** 2025-12-27
**Next Review:** After multi-template requirements approval
**Maintained By:** Development Team

---

## 📌 Remember:

**The golden rule:** Check `CONTEXT.md` FIRST to understand the complete system.

**The golden test:** Free user flow AND logged-in user flow must ALWAYS work (no regressions).

**The golden path:** Test thoroughly (`npm test`), maintain stability, document changes.

**The golden principle:** System is stable and complete - focus on maintaining quality.

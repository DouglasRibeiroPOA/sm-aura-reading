# GEMINI.md

Google Gemini AI Guide for Mystic Palm Reading Plugin (SoulMirror)

## ✅ **System Status: Complete & Stable**

**Status:** ✅ **STABLE & PRODUCTION-READY** - Core Development Finished (Enhancements Only)
**Last Updated:** 2025-12-27

---

## 🚧 **Active Project: Teaser Content Quality Enhancement**

**Status:** ⚡ IN PROGRESS - Content Optimization & API Efficiency
**Started:** 2025-12-30
**Plan:** `TEASER-CONTENT-OPTIMIZATION.md`

### Current Focus

**Achievements:**
- ✅ Fixed duplicate API call bug (4 calls → 2 calls, 50% cost reduction)
- ✅ Identified 8 wasted schema fields (28% reduction)

**In Progress:**
- Replace `locked_teaser` fields with 200 dynamic quotes (gender-specific, deterministic)
- Remove 8 wasted fields from schema (never displayed in templates)
- Strengthen content depth: Foundations +60%, Previews +200% (40w → 120-160w)

**Benefits:**
- 50-70% richer teaser content
- Lower API costs (fewer wasted tokens)
- Cleaner schema (29 → 21 fields)
- Better user experience (deeper insights)

All major features successfully implemented:
- ✅ Account Service Authentication - SSO login, JWT tokens, credit-based paid readings
- ✅ Teaser Reading Rebalance - OpenAI prompt optimization (30-40% token reduction)
- ✅ Flow Session Stabilization - Server-side state management
- ✅ Complete automated testing infrastructure
- ✅ All critical and high-priority bugs resolved
- ✅ UI/UX refinements complete

**Current Phase:** Enhancements

---

## 🚀 **Current Development Focus**

**Status:** Multi-template support is live. Focus on stability and regressions.

---

## 🎯 Essential Reading Order

**Before generating any code or suggestions, read in this exact order:**

1. **`CONTEXT.md`** - ⭐ **CHECK FIRST!** Complete system specifications, architecture, flows, API reference
2. **`archive/2025-12-28-md-archive/TESTING.md`** - 🧪 Complete testing guide (consolidated from 3 previous docs)
3. **`CLAUDE.md`** - AI assistant instructions and constraints
4. **`archive/MULTI-TEMPLATE-REQUIREMENTS.md`** - Multi-template requirements (completed)
5. **`archive/MULTI-TEMPLATE-DEV-PLAN.md`** - Multi-template development plan (completed)
6. **`archive/REPORTS-LISTING-REQUIREMENTS.md`** - Reports listing requirements (implemented)
7. **`archive/DEV-PLAN.md`** - Reports listing development plan (completed)
8. **`archive/DEVELOPMENT-PLAN.md`** - ❌ **DEPRECATED** Historical record only (not current requirements)

**⚠️ CRITICAL:**
- Always run automated tests (`npm test`) after changes
- Free user flow AND logged-in user flow must remain 100% functional
- Do NOT modify locked frontend files (`assets/script.js`, `assets/styles.css`)

---

## 🔍 Gemini-Specific Guidance

### Multi-Modal Code Understanding

Leverage Gemini's strengths:

**Code Analysis:**
- Review entire file context before suggesting changes
- Understand WordPress plugin architecture patterns
- Identify security vulnerabilities (SQL injection, XSS, CSRF)
- Spot performance bottlenecks in database queries

**Documentation:**
- Generate PHPDoc blocks for classes and methods
- Explain complex authentication flows
- Document API integration patterns

**Testing:**
- Suggest comprehensive test scenarios
- Identify edge cases based on requirements
- Generate regression test checklists

---

## 🛡️ Security-First Development

**Critical Security Rules:**

1. **Database Queries:**
   - ALWAYS use `$wpdb->prepare()` with placeholders
   - NEVER concatenate user input into SQL strings

2. **Output Escaping:**
   - Use `esc_html()` for text content
   - Use `esc_url()` for URLs
   - Use `esc_attr()` for HTML attributes
   - Use `wp_kses()` for allowed HTML

3. **Input Validation:**
   - Use `sanitize_text_field()` for text inputs
   - Use `sanitize_email()` for email addresses
   - Use `absint()` for positive integers
   - Use `sanitize_url()` for URLs

4. **Authentication & Authorization:**
   - Verify nonces on ALL form submissions and AJAX requests
   - Check user capabilities before sensitive operations
   - Store JWT tokens in httponly cookies or secure sessions
   - NEVER expose tokens in client-side JavaScript

5. **API Security:**
   - Enforce HTTPS for Account Service integration
   - Rate limit public API endpoints
   - Use idempotency keys for credit deductions
   - Log all authentication failures

---

## 📂 Project Structure

### Core Plugin Files

**Authentication System (Complete):**
```
includes/
  class-sm-auth-handler.php        - JWT validation, login/logout, sessions
  class-sm-credit-handler.php      - Credit check, deduction, shop redirect
```

**Teaser Reading System (Complete):**
```
includes/
  class-sm-ai-handler.php          - OpenAI API integration, prompt building
  class-sm-teaser-reading-schema-v2.php - New optimized schema
  class-sm-template-renderer.php   - HTML template population
```

**Database:**
```
includes/
  class-sm-database.php            - Schema definitions, migrations
```

**Frontend:**
```
assets/
  js/script.js                     - Main UI flow (LOCKED - do not modify)
  js/api-integration.js            - Backend API calls
  js/swipe-template.js             - Swipeable template logic (scoped)
  css/styles.css                   - Main styles (LOCKED - do not modify)
  css/auth.css                     - Auth UI styles (NEW)
  css/swipe-template.css           - Swipeable template styles (scoped)
```

**Templates:**
```
templates/
  container.php                    - Main app container
  dashboard.php                    - Logged-in user dashboard
palm-reading-template-teaser.html  - Traditional teaser report
palm-reading-template-full.html    - Traditional paid report
palm-reading-template-swipe-teaser.html - Swipeable teaser report
palm-reading-template-swipe-full.html   - Swipeable paid report
```

---

## 🎯 Naming Conventions

**PHP Classes:**
- Prefix: `SM_`
- Example: `SM_Auth_Handler`, `SM_Credit_Handler`

**Database Tables:**
- Prefix: `wp_sm_`
- Example: `wp_sm_readings`, `wp_sm_leads`

**Functions:**
- Prefix: `sm_`
- Example: `sm_get_current_user()`, `sm_validate_jwt()`

**Constants:**
- Prefix: `SM_`
- All caps with underscores
- Example: `SM_VERSION`, `SM_PLUGIN_DIR`

---

## 🧪 Testing Strategy

### Automated Testing with Playwright

**MANDATORY:** Always run automated tests after changes.

**TWO complete test suites:**

#### **1. Unit Tests** (`tests/palm-reading-flow.spec.js`)
Fast page mechanics tests covering refresh scenarios, session management, and navigation.

**Current Coverage:**
- ✅ Fresh page load (no infinite loop, no 500 errors)
- ✅ Multiple page refreshes (5 consecutive)
- ✅ Rapid refresh stress test (race conditions)
- ✅ OTP step refresh (state restoration)
- ✅ Report page refreshes (URL persistence)
- ✅ Browser back button behavior
- ✅ Fast clicking (duplicate API prevention)
- ✅ Session persistence across navigation

**Run:**
```bash
npm run test:unit          # Unit tests only
npm run test:unit:headed   # With visible browser
```

#### **2. E2E Tests** (`tests/e2e-full-flow.spec.js`)
Complete user flow automation - **ZERO manual steps required**.

**What it automates:**
- ✅ Generates unique test emails automatically
- ✅ Retrieves OTP codes from database (no Mailpit!)
- ✅ Completes FULL flow: Welcome → Lead → OTP → Photo → Quiz → Report
- ✅ Tests unlock behavior (seeded readings)
- ✅ Tests paywall redirect + back button navigation
- ✅ Takes 20+ screenshots at every step
- ✅ Captures ALL console logs, network calls, session state

**Run:**
```bash
npm run test:e2e:headed    # E2E with browser (RECOMMENDED)
npm test                   # ALL tests (unit + E2E)
```

**For complete testing documentation, see `archive/2025-12-28-md-archive/TESTING.md`**

---

### 📋 **Logging & Debugging**

**Current Status:** System is stable - logging remains active for maintenance and future development.

**Check Logs:**
```bash
# PHP backend logs
tail -f /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/debug.log

# Search for errors
grep -i "SM" debug.log | tail -50

# JavaScript console
# Open DevTools (F12) → Console → Filter by "[SM"

# Test logs (captures everything automatically)
npm run test:e2e:headed
npm run test:report      # HTML report viewer
```

**Debugging Workflow:**
1. **Run E2E test** - `npm run test:e2e:headed` (catches bug with full logs)
2. **Check screenshots** - `test-results/*.png` (visual proof)
3. **Check console output** - All `[SM]` messages captured
4. **Check debug.log** - If backend issue
5. **Fix bug** with full context from logs
6. **Re-run test** - Verify fix
7. **✅ Green = fixed + won't regress**

**Logging Best Practices:**

```php
// PHP - log with context
SM_Logger::log('info', 'UNLOCK', 'Section unlocked', array(
    'reading_id' => $reading_id,
    'section_id' => $section_id,
    'unlocks_remaining' => $unlocks
));
```

```javascript
// JavaScript - log state changes
console.log('[SM DEBUG] State:', {
    reading_loaded: sessionStorage.getItem('sm_reading_loaded'),
    flow_step: sessionStorage.getItem('sm_flow_step_id'),
    url: window.location.href
});
```

---

### Manual Testing (When Needed)

**Before Code Changes:**

1. **Read Documentation:**
   - Check `CONTEXT.md` for complete system specifications
   - Understand testing infrastructure in `archive/2025-12-28-md-archive/TESTING.md`
   - Review historical context in `DEVELOPMENT-PLAN.md` if needed

2. **Enable DevMode:**
   - WordPress Admin → Palm Reading → Settings → DevMode
   - Avoids real API costs during testing
   - Uses mock responses for Account Service and OpenAI

3. **Test User Flows:**
   - Clear cookies and cache
   - Visit `/palm-reading`
   - Test both free AND logged-in user flows
   - Verify NO regressions

4. **Check Error Logs:**
   ```bash
   tail -f wp-content/debug.log
   ```
   - No PHP errors
   - No JavaScript errors (browser console)

5. **Run Automated Tests:**
   ```bash
   npm test
   ```
   - Verify all tests pass
   - Review test output for any warnings

---

## 📋 **Reports Listing Feature - Implementation Guidance**

### Phase 0: Account Linking (CRITICAL PREREQUISITE)

**MUST be completed BEFORE reports listing implementation.**

**Task:** Add automatic linking of free readings to user accounts when they log in.

**Implementation:**
1. **Location:** `includes/class-sm-auth-handler.php`
2. **Method:** `link_existing_readings_to_account($account_id, $email)`
3. **Triggers:** JWT callback handler, login handler
4. **SQL Logic:**
   ```php
   // Update readings table
   UPDATE wp_sm_readings r
   INNER JOIN wp_sm_leads l ON r.lead_id = l.id
   SET r.account_id = %s
   WHERE l.email = %s AND r.account_id IS NULL

   // Update leads table
   UPDATE wp_sm_leads
   SET account_id = %s
   WHERE email = %s AND account_id IS NULL
   ```

**Testing:**
- User with free reading creates account → old reading appears in reports listing
- Multiple free readings all linked correctly
- Email matching is case-insensitive
- Add logging for audit trail

### Phase 1-6: Reports Listing Implementation

**Complete implementation checklist available in `archive/REPORTS-LISTING-REQUIREMENTS.md`**

**Key Files to Create/Modify:**
1. `includes/class-sm-reports-handler.php` - New handler class
2. `templates/user-reports.php` - New template file
3. Update existing dashboard to link to reports page

**Key Methods to Implement:**
- `get_user_reports($account_id, $limit, $offset)` - Fetch reports with pagination
- `get_user_reports_count($account_id)` - Total count for pagination
- `generate_report_title($reading)` - Hybrid approach (JSON extraction → fallback)
- `calculate_reading_time($content_data)` - Word count ÷ 200 wpm
- `format_report_for_template($reading)` - Format for JavaScript consumption

**Implementation Decisions (All Approved):**
1. Report title: Hybrid (extract from JSON, fallback to date-based)
2. Reading time: Calculate from word count
3. Pagination: Functional selector (10/20/30 options)
4. Action buttons: View functional, others placeholder toasts
5. No caching (optimize later if needed)
6. JOIN with wp_sm_leads for user name/email

**Critical Constraints:**
- ✅ Must NOT impact existing functionality (teaser flow, auth, credits)
- ✅ Strictly presentation layer (read-only, no editing/regeneration)
- ✅ Backend-driven pagination (SQL LIMIT/OFFSET)
- ✅ Follow WordPress coding standards
- ✅ Add comprehensive logging

---

## 🔄 Development Workflow

### Before Starting Work

**Current Status: Stable System + New Feature Development**

1. **Read `CONTEXT.md`** - Understand complete system architecture
2. **Review `archive/REPORTS-LISTING-REQUIREMENTS.md`** - Completed reference (reports listing)
3. **Review `archive/DEV-PLAN.md`** - Completed development plan reference
4. **Review `archive/2025-12-28-md-archive/TESTING.md`** - Understand testing infrastructure
5. **Check `archive/DEVELOPMENT-PLAN.md`** (if needed) - Historical context only

**During Development:**
1. Make changes carefully - respect existing functionality
2. Test in DevMode first
3. Run `npm test` frequently
4. Check debug.log for errors
5. **DO NOT** modify locked frontend files
6. **DO NOT** break existing flows

**After Completing Task:**
1. ✅ Test specific functionality
2. ✅ Run full test suite (`npm test`)
3. ✅ Verify backward compatibility
4. ✅ **Update archive/MULTI-TEMPLATE-DEV-PLAN.md** - Mark tasks complete, update progress bars
5. ✅ Update documentation if behavior changes
6. ✅ Check debug.log for errors

**Dev-Plan Update Protocol:**
- Change `- [ ]` to `- [x]` for completed tasks
- Update progress bars: `[....]` → `[####]` based on completion percentage
- Calculate and update phase percentage
- Add any discovered bugs to Issues & Bugs table
- Mark phase as `✅ COMPLETE` when all tasks done

---

## 🚨 Critical Constraints

### DO NOT Modify (Frontend Lock):
- `assets/script.js` - Main UI flow (12-step quiz)
- `assets/styles.css` - Main stylesheet

**Why?** Frontend is the source of truth. Backend adapts to UI, not vice versa.

**Exception:** Explicitly requested in requirements document.

### DO NOT Break User Flows:
- ⚠️ **CRITICAL:** Free user flow (email → OTP → quiz → teaser) must ALWAYS work
- ⚠️ **CRITICAL:** Logged-in user flow (dashboard → generate reading) must ALWAYS work
- Authentication is 100% optional
- No regressions in existing functionality
- **Test both flows after EVERY change**
- If either flow breaks, STOP and fix before proceeding

### DO NOT Skip Testing:
- Run `npm test` after every change
- Regression tests catch issues immediately
- DevMode testing before real API testing
- Cross-browser testing for production changes

---

## 📚 Reference Documentation

**Core Documentation (Stable):**

| Document | Purpose | When to Use | Status |
|----------|---------|-------------|--------|
| `CONTEXT.md` | ⭐ **SINGLE SOURCE OF TRUTH** - All requirements, architecture, flows, API reference | **CHECK FIRST** for complete specs | Complete |
| `archive/MULTI-TEMPLATE-REQUIREMENTS.md` | Multi-template requirements | Reference | ✅ Complete |
| `archive/MULTI-TEMPLATE-DEV-PLAN.md` | Multi-template development plan | Reference | ✅ Complete |
| `archive/REPORTS-LISTING-REQUIREMENTS.md` | 📋 Reports listing requirements | Reference | ✅ Complete |
| `archive/DEV-PLAN.md` | ✅ Reports listing development plan | Reference | ✅ Complete |
| `archive/2025-12-28-md-archive/TESTING.md` | 🧪 **TESTING GUIDE** - Automated testing (consolidated from 3 docs) | Setting up/running tests | Complete |
| `CLAUDE.md` | AI assistant instructions, testing protocol, constraints | Need AI-specific guidance | Complete |
| `README.md` | Plugin overview | Quick introduction | Complete |

**Archived Documentation** (Reference Only):
- `archive/MULTI-TEMPLATE-REQUIREMENTS.md` → Multi-template requirements (completed)
- `archive/MULTI-TEMPLATE-DEV-PLAN.md` → Multi-template development plan (completed)
- `archive/DEVELOPMENT-PLAN.md` → ❌ **DEPRECATED** Previous development plan (no longer relevant)
- `archive/archive/E2E-AUTOMATION-GUIDE.md` → Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/archive/E2E-QUICK-START.md` → Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/archive/README-TESTING.md` → Consolidated into `archive/2025-12-28-md-archive/TESTING.md`
- `archive/NEXT-STEPS.md` → No longer applicable
- `archive/ACCOUNT-AUTH-INTEGRATION-REQUIREMENTS.md` → Consolidated into `CONTEXT.md`
- `archive/TEASER-REBALANCE-REQUIREMENTS.md` → Consolidated into `CONTEXT.md`
- `archive/integration-guide.md` → Consolidated into `CONTEXT.md`

**Benefits of New Structure:**
- ✅ Single source of truth (CONTEXT.md) - no conflicting information
- ✅ Clear, consolidated documentation
- ✅ Easier navigation for AI assistants
- ✅ System is stable and complete

---

## 🎯 Quick Reference Commands

**Check Current Status:**
> "What's the current system architecture from CONTEXT.md?"

**Reports Listing Feature:**
> "Explain the reports listing implementation from archive/REPORTS-LISTING-REQUIREMENTS.md"

**Account Linking:**
> "How do I implement the account linking feature (Phase 0)?"

**Check Testing:**
> "How do I run the automated tests?"

**Security Review:**
> "Review this code for WordPress security best practices"

**Explain Flow:**
> "Explain the free user flow vs logged-in user flow"

---

## ✅ Pre-Change Checklist

Before suggesting code changes, verify:

- [ ] Read `CONTEXT.md` for complete system specifications
- [ ] Understand testing infrastructure (`archive/2025-12-28-md-archive/TESTING.md`)
- [ ] **Understood existing code before modifying** - Read files first
- [ ] All database queries use `$wpdb->prepare()`
- [ ] All output is escaped (esc_html, esc_url, etc.)
- [ ] All input is sanitized (sanitize_text_field, etc.)
- [ ] Nonces verified on forms/AJAX
- [ ] Error handling for all API calls
- [ ] Logging added for debugging
- [ ] PHPDoc blocks added
- [ ] **Backward compatibility verified** (both flows unchanged)
- [ ] **Will run `npm test` to verify no regressions**

---

## 🚀 Gemini Advantages for This Project

Use Gemini's strengths:

1. **Long Context Understanding:** Digest entire requirement documents and understand system architecture
2. **Multi-File Analysis:** Understand how authentication, credits, and teaser features interact
3. **Security Analysis:** Identify WordPress-specific vulnerabilities before they ship
4. **Test Case Generation:** Create comprehensive testing scenarios from requirements
5. **Documentation:** Generate clear, structured docs for complex flows

---

**Last Updated:** 2025-12-27
**Version:** 4.1.0 (Multi-Template System Complete)
**Maintained By:** Development Team

---

## 📌 Golden Rules - The Non-Negotiables

1. **Check `CONTEXT.md` FIRST** - Complete system specifications and architecture
2. **Run `npm test` after changes** - Catch regressions immediately
3. **Free user flow AND logged-in user flow must NEVER break** - Both are sacred
4. **Understand before modifying** - Read existing code, don't make assumptions
5. **Respect locked frontend files** - Do NOT modify `assets/script.js` or `assets/styles.css`
6. **Security is non-negotiable** - Sanitize, escape, prepare, verify
7. **Test thoroughly** - DevMode → Automated tests → Manual verification (if needed)
8. **Document decisions** - PHPDoc blocks, inline comments, update docs
9. **System is stable** - Focus on maintaining quality and stability
10. **When in doubt, ask** - Better to clarify than to break existing functionality

---

## ⚠️ CRITICAL REMINDER

**The success of maintaining this system depends on:**
- ✅ Testing after every change (`npm test`)
- ✅ Respecting existing functionality (no breaking changes)
- ✅ Clear documentation of any modifications
- ✅ Focus on stability and quality over new features

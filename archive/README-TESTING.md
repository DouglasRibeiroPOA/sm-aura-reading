# 🧪 Automated Testing Guide

## Overview

This plugin uses **Playwright** for automated end-to-end testing of critical user flows and session management.
When UI changes are made, **request to run UI automation**; manual UI testing is optional and only used if automation cannot cover a case.

**We now have TWO test suites:**

1. **Unit Tests** - Fast page mechanics (refresh, session, navigation)
2. **E2E Tests** - Complete user flow automation (**ZERO manual steps**)

## Why Automated Testing?

The Palm Reading plugin has complex session/state management with:
- Multiple page refresh scenarios
- SessionStorage/Cookie coordination
- Flow state persistence
- Magic link handling
- Report URL state
- Unlock behavior
- Navigation flows

**Manual testing is slow, error-prone, and unsustainable.** Automated tests:
- ✅ Catch regressions immediately
- ✅ Provide visual proof (screenshots)
- ✅ Capture complete logs automatically
- ✅ Save hours of repetitive testing
- ✅ Enable sustainable iteration

---

## 🚀 Quick Start

### 1. Install Dependencies

```bash
cd /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/plugins/sm-palm-reading
npm install
npx playwright install chromium
```

### 2. Enable DevMode

**Required for E2E tests (uses test helper API):**
```bash
# Via WP-CLI
wp sm devmode enable

# Or via WordPress Admin
# Go to: Palm Reading → Settings → Enable DevMode
```

### 3. Run Tests

```bash
# Run ALL tests (unit + E2E) - headless
npm test

# Run unit tests only
npm run test:unit
npm run test:unit:headed   # With browser

# Run E2E tests only (RECOMMENDED FOR BUG FINDING)
npm run test:e2e
npm run test:e2e:headed    # With browser visible
npm run test:e2e:ui        # Interactive UI mode

# View last test report
npm run test:report
```

---

## 📋 Test Suites

### **1. Unit Tests** (`tests/palm-reading-flow.spec.js`)

Fast page mechanics tests.

**What it tests:**
1. ✅ Fresh Page Load - No infinite loop, no 500 errors
2. ✅ Multiple Refreshes - 5 consecutive refreshes without issues
3. ✅ Rapid Refresh Stress Test - Fast refreshes (race conditions)
4. ✅ OTP Step Refresh - State restoration with email preserved
5. ✅ Report Page Refreshes - Stay on report, correct URL params
6. ✅ Browser Back Button - State clearing behavior
7. ✅ Fast Clicking - Duplicate API call prevention
8. ✅ Session Persistence - Cross-navigation state preservation
9. ✅ No 500 Errors - Comprehensive check across scenarios

**Run time:** ~2-3 minutes

### **2. E2E Tests** (`tests/e2e-full-flow.spec.js`) ⭐ **NEW!**

Complete user flow automation - **ZERO manual steps required**.

**What it tests:**

**Test 1: Complete Flow (Welcome → Report)**
- Generates unique email (`test-{timestamp}@example.com`)
- Retrieves OTP from database automatically
- Fills all forms
- Uploads palm photo
- Completes quiz
- Generates reading (DevMode)
- Verifies report loaded
- **12 screenshots** documenting flow

**Test 2: Unlock + Refresh Behavior**
- Seeds reading instantly
- Clicks first unlock button
- Refreshes page
- **Verifies design stays intact**
- **Verifies unlocked section persists**
- Clicks second unlock
- Refreshes again
- **Verifies both sections unlocked**
- **5 screenshots**

**Test 3: Paywall + Back Button**
- Seeds reading with 2 unlocks
- Clicks third unlock (triggers paywall)
- Clicks browser BACK button
- **Verifies returns to REPORT (not first page)**
- **Verifies session persisted**
- **3 screenshots**

**Run time:** ~3-4 minutes

---

## 🛠️ Test Helper API (E2E Tests)

E2E tests use special backend endpoints (DevMode only) to automate the full flow.

**Location:** `includes/class-sm-test-helpers.php`

**Endpoints:**
```bash
# Get OTP code for an email
GET /wp-json/soulmirror-test/v1/get-otp?email=test@example.com

# Get lead data by email
GET /wp-json/soulmirror-test/v1/get-lead?email=test@example.com

# Seed a complete reading (instant)
POST /wp-json/soulmirror-test/v1/seed-reading
Body: { "email": "test@example.com", "name": "Test User" }

# Cleanup all test data
POST /wp-json/soulmirror-test/v1/cleanup
```

**Security:**
- ✅ Only active when DevMode is enabled
- ✅ Automatically disabled in production
- ✅ Only processes test emails (test-*@*)

**Used by E2E tests to:**
- Retrieve OTP codes automatically (no manual Mailpit checking)
- Create complete readings instantly (bypass forms for speed)
- Clean up test data after runs

---

## 📊 Test Output

### Console Output (Example)
```
✅ Fresh load: 3 API calls, 42 logs, NO errors
✅ 5 refreshes completed without errors
✅ Rapid refresh stress test passed
✅ OTP refresh: Restored to correct step with email preserved
```

### HTML Report
After tests run, open `test-results/html-report/index.html` to see:
- ✅ Pass/Fail for each test
- 📸 Screenshots on failure
- 🎥 Video recordings
- 📋 Full console logs
- 🌐 Network requests

### Trace Viewer (Time-Travel Debugging)
```bash
npx playwright show-trace test-results/.../trace.zip
```

This shows **EXACTLY** what happened:
- Every DOM change
- Every network request
- Every console log
- SessionStorage changes

---

## 🔧 Adding New Tests

### When to Add a Test

**Every time we agree on a page behavior, create a test for it.**

Examples:
- "Report refresh should stay on report" → Add test
- "OTP refresh should restore email" → Add test
- "Back button should clear session" → Add test

### Test Template

```javascript
test('Description of behavior', async ({ page }) => {
  const consoleLogs = [];
  const apiRequests = [];

  setupConsoleCapture(page, consoleLogs);
  setupNetworkCapture(page, apiRequests);

  // Setup
  await page.goto('/');
  await clearSession(page);

  // Perform action
  await page.reload();
  await page.waitForLoadState('networkidle');

  // Verify behavior
  const sessionState = await getSessionState(page);
  expect(sessionState.sm_reading_loaded).toBe('true');

  // Check for errors
  const has500Error = apiRequests.some(req => req.status === 500);
  expect(has500Error).toBe(false);

  console.log(`✅ Test passed`);
});
```

---

## 🐛 Debugging Failed Tests

### Step 1: Run in Headed Mode
```bash
npm run test:headed
```
Watch the browser to see what's happening.

### Step 2: Check Console Logs
Failed tests print all `[SM]` logs automatically.

### Step 3: View Screenshot
Check `test-results/` for screenshots of the failure.

### Step 4: Use Trace Viewer
```bash
npx playwright show-trace test-results/.../trace.zip
```
Replay the exact test execution.

---

## 📁 File Structure

```
sm-palm-reading/
├── tests/
│   └── palm-reading-flow.spec.js  # All E2E tests
├── test-results/                   # Generated test output
│   ├── html-report/                # HTML test report
│   └── *.zip                       # Trace files
├── playwright.config.js            # Playwright configuration
├── package.json                    # NPM dependencies
└── README-TESTING.md               # This file
```

---

## 🎯 Best Practices

### 1. Run Tests Before Committing
```bash
npm test
```
Ensure no regressions before pushing code.

### 2. Add Tests for Bug Fixes
When fixing a bug:
1. Write a failing test that reproduces the bug
2. Fix the bug
3. Verify test passes
4. Commit both fix and test

### 3. Keep Tests Fast
- Use `networkidle` instead of arbitrary `waitForTimeout` when possible
- Only add delays when testing timing-sensitive behavior

### 4. Document Test Intent
Each test should have a clear comment explaining **what behavior** it verifies.

---

## 📋 Logging & Debugging

### Where Logs Are

**1. Test Console Output:**
- E2E tests automatically capture ALL `[SM]` console logs
- Run `npm run test:e2e:headed` to see real-time logs

**2. PHP Backend Logs:**
```bash
# Watch WordPress debug.log
tail -f /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/debug.log

# Search for errors
grep -i "SM" debug.log | tail -50
```

**3. Browser DevTools (Manual Testing - Optional):**
- Press F12
- Console tab
- Filter by `[SM` to see plugin logs

**4. Test Screenshots:**
```bash
# Check screenshots after test failure
ls -lt test-results/*.png | head -10
open test-results/e2e-03-after-unlock-1-refresh.png
```

**5. HTML Test Report:**
```bash
npm run test:report
```
- Timeline view of test execution
- All screenshots
- Network requests
- Console logs
- Trace files (time-travel debugging)

### Debugging Workflow

**When a test fails:**

1. **Check test console output** - Shows which assertion failed
   ```
   ❌ expect(hasHeader).toBe(true)
      Received: false
   ```

2. **Check screenshot** - Visual proof of issue
   ```bash
   open test-results/e2e-03-after-unlock-1-refresh.png
   ```

3. **Check console logs** - All `[SM]` messages captured
   ```
   📋 [SM DEBUG] Session state check: {...}
   📋 [SM API] GET flow/state
   🚨 API Error: unlock/section → 500
   ```

4. **Check debug.log** - If backend PHP issue
   ```bash
   tail -f debug.log
   ```

5. **Use trace viewer** - Time-travel through test execution
   ```bash
   npx playwright show-trace test-results/.../trace.zip
   ```

6. **Fix bug** with full context from logs

7. **Re-run test** to verify fix
   ```bash
   npm run test:e2e:headed
   ```

8. **✅ Green test = bug fixed + won't regress**

### Logging Best Practices (Development)

**PHP Logging:**
```php
SM_Logger::log('info', 'UNLOCK', 'Section unlocked', array(
    'reading_id' => $reading_id,
    'section_id' => $section_id,
    'unlocks_remaining' => $unlocks
));
```

**JavaScript Logging:**
```javascript
console.log('[SM DEBUG] State:', {
    reading_loaded: sessionStorage.getItem('sm_reading_loaded'),
    flow_step: sessionStorage.getItem('sm_flow_step_id'),
    url: window.location.href
});
```

**We're in development - log everything now, clean up later.**

---

## 🔍 Common Issues

### Issue: Tests fail with "net::ERR_CERT_AUTHORITY_INVALID"
**Solution:** Tests are configured to ignore HTTPS errors for local development.
Check `playwright.config.js` has `ignoreHTTPSErrors: true`.

### Issue: Tests can't connect to local site
**Solution:** Update `baseURL` in `playwright.config.js` to match your local site URL.

### Issue: Test hangs on page load
**Solution:** Increase timeout in `playwright.config.js` or use `page.waitForLoadState('domcontentloaded')` instead of `networkidle`.

---

## 📚 Resources

- [Playwright Documentation](https://playwright.dev)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Debugging Guide](https://playwright.dev/docs/debug)

---

**Remember:** Every agreed-upon behavior should have a test. If it's not tested, it will break.

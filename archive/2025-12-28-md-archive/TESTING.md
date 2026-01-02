# Testing Guide - Mystic Palm Reading Plugin

**Status:** Complete & Stable
**Last Updated:** 2025-12-26
**Purpose:** Comprehensive automated testing documentation for the Mystic Palm Reading plugin

---

## Overview

This plugin uses **Playwright** for automated end-to-end testing of critical user flows and session management. The testing infrastructure is complete, stable, and ready for continuous use.

**Policy:** For UI changes, always run Playwright automation first. Manual UI testing is optional and only used when automation cannot cover a specific case.

---

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

## Test Suites

We have **TWO complete test suites**:

### 1. Unit Tests (`tests/palm-reading-flow.spec.js`)

Fast page mechanics tests covering:

**Coverage:**
1. ✅ Fresh page load (no infinite loop, no 500 errors)
2. ✅ Multiple page refreshes (5 consecutive)
3. ✅ Rapid refresh stress test (race conditions)
4. ✅ OTP step refresh (state restoration)
5. ✅ Report page refreshes (URL persistence)
6. ✅ Browser back button behavior
7. ✅ Fast clicking (duplicate API prevention)
8. ✅ Session persistence across navigation
9. ✅ No 500 errors across all scenarios

**Run time:** ~2-3 minutes

**Commands:**
```bash
npm run test:unit          # Unit tests only (headless)
npm run test:unit:headed   # With visible browser
```

### 2. E2E Tests (`tests/e2e-full-flow.spec.js`)

Complete user flow automation - **ZERO manual steps required**.

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
- Seeds reading instantly (bypass forms)
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

**Commands:**
```bash
npm run test:e2e           # E2E tests (headless)
npm run test:e2e:headed    # E2E with browser (RECOMMENDED)
npm run test:e2e:ui        # Interactive UI mode
npm test                   # ALL tests (unit + E2E)
```

---

## Quick Start

### Prerequisites

1. **Install Dependencies:**
   ```bash
   cd /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/plugins/sm-palm-reading
   npm install
   npx playwright install chromium
   ```

2. **Enable DevMode** (required for E2E tests):
   ```bash
   # Via WP-CLI
   wp sm devmode enable

   # Or via WordPress Admin
   # Go to: Palm Reading → Settings → Enable DevMode
   ```

3. **Verify Setup:**
   ```bash
   wp sm devmode status
   # Should show: "DevMode: ENABLED (using mock endpoints)"
   ```

### Running Tests

```bash
# Run ALL tests (recommended)
npm test

# Run specific suites
npm run test:unit          # Unit tests only
npm run test:e2e           # E2E tests only
npm run test:e2e:headed    # E2E with browser visible (great for watching/debugging)

# View last test report
npm run test:report
```

---

## Test Helper API

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

## Test Output

### Console Output (Example)

```
🧹 Cleaning up old test data...

🎯 Starting E2E test with email: test-1735139842567-123@example.com

📍 STEP 1: Loading welcome page...
📸 Screenshot: e2e-01-welcome.png
======================================================================
📊 STATE: Welcome Page Loaded
======================================================================
URL: https://sm-palm-reading.local/palm-reading
Session: {
  "sm_flow_step_id": "welcome",
  ...
}
======================================================================

📍 STEP 2: Navigating to lead capture...
📸 Screenshot: e2e-02-lead-capture.png

...

✅ Full flow test completed successfully!
```

### Screenshots Generated

**Test 1 (Full Flow):**
- `e2e-01-welcome.png`
- `e2e-02-lead-capture.png`
- `e2e-03-form-filled.png`
- `e2e-04-after-submit.png`
- `e2e-05-otp-entered.png`
- `e2e-06-otp-verified.png`
- `e2e-07-photo-uploaded.png`
- `e2e-08-quiz-start.png`
- `e2e-09-quiz-filled.png`
- `e2e-10-report-loaded.png`

**Test 2 (Unlock Behavior):**
- `e2e-01-seeded-report-initial.png`
- `e2e-02-after-first-unlock.png`
- `e2e-03-after-unlock-1-refresh.png`
- `e2e-04-after-second-unlock.png`
- `e2e-05-after-unlock-2-refresh.png`

**Test 3 (Paywall + Back):**
- `e2e-01-paywall-test-initial.png`
- `e2e-02-after-third-unlock.png`
- `e2e-03-after-back-from-paywall.png`

All screenshots saved to: `test-results/`

### HTML Report

After tests complete, view the beautiful Playwright report:

```bash
npm run test:report
# Opens: test-results/html-report/index.html
```

**Report includes:**
- ✅ Pass/fail status for each test
- ⏱️ Execution time
- 📸 Screenshots at each step
- 📋 Console logs
- 🌐 Network requests
- 🔍 Trace viewer (step-through debugging)

---

## Debugging Failed Tests

### 1. Check Screenshots

Look at the screenshot right before the failure:
```bash
ls -lt test-results/*.png | head -5
open test-results/e2e-05-otp-entered.png
```

Visual proof of what the page looked like when it failed.

### 2. Check Console Logs

Test output shows all `[SM...]` logs and errors:
```
📋 [SM API] GET flow/state
📋 [SM DEBUG] Session state check: {...}
🚨 API Error: unlock/section → 500
```

### 3. Check Session State

Each step logs session state:
```
======================================================================
📊 STATE: After First Unlock + Refresh
======================================================================
URL: https://sm-palm-reading.local/palm-reading?sm_report=1&lead_id=test-abc
Session: {
  "sm_reading_loaded": "true",
  "sm_unlocked_sections": "[\"love_patterns\"]",
  ...
}
======================================================================
```

### 4. Use Interactive UI Mode

```bash
npm run test:e2e:ui
```

- See test run in real-time
- Pause at any step
- Inspect page elements
- View network calls
- Step through manually

### 5. Run Specific Test

```bash
# Run only the unlock test
npx playwright test e2e-full-flow.spec.js -g "unlock"

# Run with browser visible
npx playwright test e2e-full-flow.spec.js -g "unlock" --headed

# Run with debugger (pauses at each step)
npx playwright test e2e-full-flow.spec.js -g "unlock" --debug
```

### 6. Check PHP Backend Logs

```bash
# Watch WordPress debug.log
tail -f /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/debug.log

# Search for errors
grep -i "SM" debug.log | tail -50

# Search for specific issue
grep -i "unlock" debug.log | tail -30
```

---

## What the Tests Verify

### Test 1: Full Flow
✅ Welcome page loads
✅ Lead capture form works
✅ Email/name validation
✅ OTP generation and retrieval
✅ OTP verification
✅ Photo upload
✅ Quiz completion
✅ Reading generation (DevMode)
✅ Report rendering
✅ Session state management
✅ No 500 errors
✅ No infinite loops

### Test 2: Unlock + Refresh
✅ Unlock button visible
✅ Unlock updates session
✅ Unlock persists database
✅ **Page design stays intact after refresh**
✅ **Unlocked sections persist after refresh**
✅ Header remains visible
✅ App container remains visible
✅ Report container remains visible
✅ No CSS/layout breakage

### Test 3: Paywall + Back Button
✅ Third unlock triggers paywall redirect
✅ Redirect goes to offerings page
✅ **Back button returns to REPORT**
✅ **Session persists through paywall redirect**
✅ NOT redirected to first page
✅ Reading data still accessible
✅ Unlocked sections still present

---

## Advantages Over Manual Testing

| Manual Testing | Automated Testing |
|----------------|-------------------|
| ❌ Repetitive, tiring | ✅ Run infinite times |
| ❌ Human error prone | ✅ Consistent every time |
| ❌ Slow (5-10 mins per test) | ✅ Fast (2-3 mins for all 3 tests) |
| ❌ No screenshots | ✅ Screenshot every step |
| ❌ Hard to share results | ✅ Beautiful HTML report |
| ❌ You have to check browser console | ✅ Auto-captured in output |
| ❌ You have to check network tab | ✅ Auto-captured API calls |
| ❌ You have to check sessionStorage | ✅ Auto-logged at each step |
| ❌ Easy to miss regressions | ✅ Catches every regression |
| ❌ Can't run during sleep | ✅ Run on CI/CD overnight |

---

## Test Data Management

### Automatic Cleanup

Tests use emails starting with `test-` which are:
- ✅ Auto-cleaned before each run
- ✅ Easy to identify in database
- ✅ Can be bulk-deleted anytime

### Manual Cleanup

```bash
# Via test endpoint
curl -X POST http://sm-palm-reading.local/wp-json/soulmirror-test/v1/cleanup

# Via database (if needed)
wp db query "DELETE FROM wp_sm_leads WHERE email LIKE 'test-%@%'"
wp db query "DELETE FROM wp_sm_readings WHERE lead_id LIKE 'test-%'"
```

---

## Adding New Tests

### When to Add a Test

**Every time we agree on a page behavior, create a test for it.**

Examples:
- "Report refresh should stay on report" → Add test
- "OTP refresh should restore email" → Add test
- "Back button should clear session" → Add test

### Test Template (E2E)

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

### Test Template (Unit)

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

## Best Practices

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

## Troubleshooting

### "Test endpoints not found (404)"

**Solution:**
```bash
# Ensure DevMode is enabled
wp sm devmode status

# If disabled, enable it
wp sm devmode enable

# Flush rewrite rules
wp rewrite flush
```

### "Cannot find test-palm.jpg"

**Solution:**
```bash
# Recreate test image
python3 << 'EOF'
from PIL import Image, ImageDraw
img = Image.new('RGB', (800, 600), 'beige')
draw = ImageDraw.Draw(img)
draw.text((50, 250), "Test Palm", fill='black')
img.save("assets/test-palm.jpg")
EOF
```

### "OTP not found"

**Possible causes:**
- Lead wasn't created (check `wp_sm_leads` table)
- Email not verified
- DevMode API mock not returning OTP

**Debug:**
```bash
# Check if lead exists
wp db query "SELECT * FROM wp_sm_leads WHERE email LIKE 'test-%' ORDER BY created_at DESC LIMIT 1"

# Check OTP value
wp db query "SELECT email, otp, email_confirmed FROM wp_sm_leads WHERE email LIKE 'test-%' ORDER BY created_at DESC LIMIT 1"
```

### Tests fail with "net::ERR_CERT_AUTHORITY_INVALID"

**Solution:** Tests are configured to ignore HTTPS errors for local development.
Check `playwright.config.js` has `ignoreHTTPSErrors: true`.

### Tests can't connect to local site

**Solution:** Update `baseURL` in `playwright.config.js` to match your local site URL.

### Test hangs on page load

**Solution:** Increase timeout in `playwright.config.js` or use `page.waitForLoadState('domcontentloaded')` instead of `networkidle`.

---

## File Structure

```
sm-palm-reading/
├── tests/
│   ├── palm-reading-flow.spec.js  # Unit tests
│   └── e2e-full-flow.spec.js      # E2E tests
├── test-results/                   # Generated test output
│   ├── html-report/                # HTML test report
│   └── *.zip                       # Trace files
├── assets/
│   └── test-palm.jpg               # Test image for uploads
├── playwright.config.js            # Playwright configuration
├── package.json                    # NPM dependencies
└── TESTING.md                      # This file
```

---

## Security

**Test endpoints are ONLY available when:**
1. DevMode is enabled (`wp sm devmode enable`)
2. Request is made from local environment

**Production safety:**
- ❌ Test endpoints don't register in production
- ❌ Can't be accessed even if someone knows the URLs
- ❌ No risk of data exposure

**Disable instantly:**
```bash
wp sm devmode disable
```

---

## Success Criteria

**When all tests pass, you know:**

1. ✅ Complete user flow works end-to-end
2. ✅ OTP generation and verification works
3. ✅ Reading generation works (DevMode)
4. ✅ Report rendering works
5. ✅ Unlock system works
6. ✅ Refresh behavior works (sessions persist)
7. ✅ Back button navigation works
8. ✅ No infinite loops
9. ✅ No 500 errors
10. ✅ No regressions

---

## Resources

- [Playwright Documentation](https://playwright.dev)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [Debugging Guide](https://playwright.dev/docs/debug)
- [Trace Viewer](https://playwright.dev/docs/trace-viewer)

---

**Last Updated:** 2025-12-26
**Status:** Complete & Stable
**Maintained By:** Development Team

**Remember:** Every agreed-upon behavior should have a test. If it's not tested, it will break.

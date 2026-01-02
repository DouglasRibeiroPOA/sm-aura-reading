# E2E Automation Guide - Complete Flow Testing

**Date:** 2025-12-25
**Purpose:** Fully automated end-to-end testing - no manual intervention required

**Policy:** For UI changes, request to run Playwright automation first. Manual UI testing is optional and only used when automation cannot cover a case.

---

## 🎯 What We Built

A **complete automated testing system** that:

✅ **Generates unique test emails** automatically
✅ **Retrieves OTP codes** from database (no manual Mailpit checking)
✅ **Completes entire user flow** - welcome → lead → OTP → photo → quiz → report
✅ **Tests unlock behavior** - unlock sections, refresh, verify persistence
✅ **Tests navigation** - back button, paywall redirects, session management
✅ **Captures everything** - screenshots, console logs, network calls, session state
✅ **No manual steps** - run one command, get complete results

---

## 📦 What Was Created

### 1. **Test Helper Backend** (`includes/class-sm-test-helpers.php`)

REST API endpoints (only active in DevMode) for test automation:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/soulmirror-test/v1/get-otp?email=X` | GET | Retrieve OTP code for an email |
| `/wp-json/soulmirror-test/v1/get-lead?email=X` | GET | Get lead data by email |
| `/wp-json/soulmirror-test/v1/seed-reading` | POST | Create complete reading instantly |
| `/wp-json/soulmirror-test/v1/cleanup` | POST | Delete all test data |

**Security:** Only works when `DevMode` is enabled. Automatically disabled in production.

### 2. **E2E Test Suite** (`tests/e2e-full-flow.spec.js`)

Three comprehensive test scenarios:

**TEST 1: Complete Flow (Welcome → Report)**
- Generates unique email (`test-{timestamp}-{random}@example.com`)
- Fills lead capture form
- Retrieves OTP automatically
- Enters OTP
- Uploads palm photo
- Completes quiz
- Waits for reading generation
- Verifies report loaded correctly
- **12 screenshots** documenting each step

**TEST 2: Unlock + Refresh Behavior**
- Seeds a reading instantly (bypass form for speed)
- Clicks first unlock button
- Refreshes page
- Verifies design integrity (header, containers, CSS)
- Verifies unlocked section persists
- Clicks second unlock button
- Refreshes again
- Verifies both sections still unlocked
- **5 screenshots** of unlock states

**TEST 3: Paywall + Back Button**
- Seeds reading with 2 sections already unlocked
- Clicks third unlock button (triggers paywall)
- Verifies redirect to offerings page
- Clicks browser BACK button
- **CRITICAL TEST:** Verifies user returns to REPORT (not first page)
- Verifies session persisted
- **3 screenshots** of navigation flow

### 3. **Test Helpers Class** (in `e2e-full-flow.spec.js`)

Reusable utilities:

```javascript
E2EHelpers.generateTestEmail()           // Generate unique email
E2EHelpers.getOTP(email)                 // Retrieve OTP from DB
E2EHelpers.getLeadByEmail(email)         // Get lead data
E2EHelpers.cleanupTestData()             // Delete old tests
E2EHelpers.seedReading(email, name)      // Create instant reading
E2EHelpers.getSessionState(page)         // Extract sessionStorage
E2EHelpers.takeScreenshot(page, name, step) // Descriptive screenshots
E2EHelpers.logState(page, label)         // Log URL + session state
```

### 4. **Test Assets**

- `assets/test-palm.jpg` - Mock palm photo for uploads
- Auto-generated with Python/PIL

---

## 🚀 Running the Tests

### Prerequisites

1. **DevMode must be enabled:**
   ```bash
   wp sm devmode enable
   # Or via WordPress Admin → Palm Reading → Settings → Enable DevMode
   ```

2. **Plugin must be active** on `https://sm-palm-reading.local`

### Commands

```bash
# Run E2E tests (headless - fastest)
npm run test:e2e

# Run E2E tests with visible browser (watch it happen)
npm run test:e2e:headed

# Run E2E tests in interactive UI mode (recommended for debugging)
npm run test:e2e:ui

# Run all tests (E2E + unit tests)
npm test

# Run only unit tests (fast page mechanics)
npm run test:unit

# Show last test report
npm run test:report
```

---

## 📊 What You'll See

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

📍 STEP 3: Filling lead capture form...
📸 Screenshot: e2e-03-form-filled.png

📍 STEP 4: Retrieving and entering OTP...
✅ OTP retrieved: 123456
📸 Screenshot: e2e-05-otp-entered.png

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

After tests complete, open:
```
test-results/html-report/index.html
```

**Beautiful Playwright report showing:**
- ✅ Pass/fail status for each test
- ⏱️ Execution time
- 📸 Screenshots at each step
- 📋 Console logs
- 🌐 Network requests
- 🔍 Trace viewer (step-through debugging)

---

## 🐛 Debugging Failed Tests

### 1. **Check Screenshots**

Look at the screenshot right before the failure:
```bash
ls -lt test-results/*.png | head -5
open test-results/e2e-05-otp-entered.png
```

Visual proof of what the page looked like when it failed.

### 2. **Check Console Logs**

Test output shows all `[SM...]` logs and errors:
```
📋 [SM API] GET flow/state
📋 [SM DEBUG] Session state check: {...}
🚨 API Error: unlock/section → 500
```

### 3. **Check Session State**

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

### 4. **Use Interactive UI Mode**

```bash
npm run test:e2e:ui
```

- See test run in real-time
- Pause at any step
- Inspect page elements
- View network calls
- Step through manually

### 5. **Run Specific Test**

```bash
# Run only the unlock test
npx playwright test e2e-full-flow.spec.js -g "unlock"

# Run with browser visible
npx playwright test e2e-full-flow.spec.js -g "unlock" --headed

# Run with debugger (pauses at each step)
npx playwright test e2e-full-flow.spec.js -g "unlock" --debug
```

---

## 🔍 What the Tests Verify

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
✅ **Page design stays intact after refresh** (this was broken!)
✅ **Unlocked sections persist after refresh** (this was broken!)
✅ Header remains visible
✅ App container remains visible
✅ Report container remains visible
✅ No CSS/layout breakage

### Test 3: Paywall + Back Button

✅ Third unlock triggers paywall redirect
✅ Redirect goes to offerings page
✅ **Back button returns to REPORT** (this was broken!)
✅ **Session persists through paywall redirect** (this was broken!)
✅ NOT redirected to first page
✅ Reading data still accessible
✅ Unlocked sections still present

---

## 🎯 Advantages Over Manual Testing

| Manual Testing | Automated E2E Testing |
|----------------|----------------------|
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

## 📝 Test Data Management

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

## 🔒 Security

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

## 🚀 Next Steps

### 1. **Run Your First E2E Test**

```bash
cd /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/plugins/sm-palm-reading
npm run test:e2e:headed
```

Watch it complete the entire flow automatically!

### 2. **Check the Results**

- Screenshots in `test-results/`
- HTML report: `test-results/html-report/index.html`
- Console output shows detailed logs

### 3. **If Tests Fail (Expected Right Now)**

This will **show you exactly what's broken**:
- ❌ Test 1 might pass (basic flow)
- ❌ Test 2 will likely fail (unlock + refresh design breaking)
- ❌ Test 3 will likely fail (back button redirect)

**Share the test output with me and we'll fix the bugs together!**

### 4. **Add More Tests**

The framework is ready. Add new tests to `e2e-full-flow.spec.js`:

```javascript
test('Your new test scenario', async ({ page }) => {
  const consoleLogs = [];
  const apiCalls = [];
  setupMonitoring(page, consoleLogs, apiCalls);

  // Your test code here...

  await E2EHelpers.takeScreenshot(page, 'step-name', '01');
  await E2EHelpers.logState(page, 'Description');
});
```

---

## 🎓 Learning Resources

**Playwright Documentation:**
- https://playwright.dev/docs/intro
- https://playwright.dev/docs/debug
- https://playwright.dev/docs/test-reporters

**Debugging Tips:**
- Use `await page.pause()` to pause mid-test
- Use `--debug` flag to step through
- Use `--headed` to see browser
- Use `.only()` to run single test: `test.only('My test', ...)`

---

## ✅ Success Criteria

**When E2E tests pass, you know:**

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

**No more manual testing loops!** 🎉

---

## 🆘 Troubleshooting

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

### "Reading not saved to database"

**This indicates a DevMode bug!** DevMode should save readings. Check:
```bash
# Check if reading exists
wp db query "SELECT * FROM wp_sm_readings WHERE lead_id LIKE 'test-%' ORDER BY created_at DESC LIMIT 1"

# Check logs
tail -f wp-content/debug.log | grep -i "reading\|devmode"
```

---

## 📞 Support

If you encounter issues:

1. **Check screenshots** - `test-results/*.png`
2. **Check HTML report** - `test-results/html-report/index.html`
3. **Check console output** - look for `🚨` errors
4. **Run in headed mode** - `npm run test:e2e:headed`
5. **Share test output** with development team

---

**Last Updated:** 2025-12-25
**Version:** 1.0.0
**Maintained By:** Development Team

**Ready to test? Run `npm run test:e2e:headed` and watch the magic happen!** ✨

# E2E Testing - Quick Start

**You're ready to go! Everything is automated.** ✨

---

## ⚡ Run Tests Right Now

```bash
cd /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/plugins/sm-palm-reading

# Run E2E tests (watch it happen in browser)
npm run test:e2e:headed
```

**That's it!** The test will:
1. ✅ Generate unique email automatically
2. ✅ Fill lead capture form
3. ✅ Retrieve OTP from database automatically
4. ✅ Complete quiz
5. ✅ Generate reading (DevMode)
6. ✅ Test unlock + refresh behavior
7. ✅ Test back button navigation
8. ✅ Take screenshots at every step
9. ✅ Capture all logs and errors
10. ✅ Generate beautiful HTML report

**No manual steps. Zero. None.**

---

## 📊 Check Results

### 1. **Console Output**
Watch the terminal - it shows each step with emojis and detailed logs.

### 2. **Screenshots**
```bash
open test-results/
```
You'll see screenshots like:
- `e2e-01-welcome.png`
- `e2e-02-lead-capture.png`
- `e2e-05-otp-entered.png`
- `e2e-10-report-loaded.png`

### 3. **HTML Report**
```bash
npm run test:report
```
Opens beautiful Playwright report with:
- Pass/fail status
- Execution timeline
- Screenshots
- Console logs
- Network requests

---

## 🐛 What You'll Discover

**The tests will likely FAIL** (revealing the bugs you've been experiencing):

### ❌ Expected Failures:

**Test 2: Unlock + Refresh**
```
❌ expect(afterFirstUnlock.hasHeader).toBe(true)
   Expected: true
   Received: false
```
→ **Design breaking after unlock + refresh** (your bug #1)

**Test 3: Paywall + Back Button**
```
❌ expect(isBackOnReport).toBe(true)
   Expected: true
   Received: false
```
→ **Back button goes to first page instead of report** (your bug #2)

**This is GOOD!** The tests are catching the exact bugs you described.

---

## 🔧 How This Helps Us

### Before (Manual Testing):
1. You test manually
2. Find bug
3. Describe it to me
4. I try to reproduce
5. I add logs
6. You test again
7. Get logs
8. Send to me
9. Repeat...

### Now (Automated Testing):
1. Run `npm run test:e2e:headed`
2. Test fails at exact point
3. **I can see:**
   - Screenshots showing visual issues
   - Console logs showing errors
   - Session state at moment of failure
   - Network requests that failed
   - Exact line in code that broke
4. I fix the bug
5. Run test again
6. ✅ Green = bug fixed, verified, won't regress

**Sustainable. Repeatable. Fast.**

**Policy:** For UI changes, request to run Playwright automation first. Manual UI testing is optional and only used when automation cannot cover a case.

---

## 📝 What We Built (Quick Overview)

### 1. **Test Helper API** (`class-sm-test-helpers.php`)
- Endpoints to retrieve OTP automatically
- Endpoints to seed readings instantly
- Only works in DevMode (secure)

### 2. **E2E Test Suite** (`tests/e2e-full-flow.spec.js`)
- 3 comprehensive tests
- Full flow automation
- Unlock behavior testing
- Navigation testing

### 3. **Test Utilities**
- Auto email generation
- Auto screenshot capture
- Auto state logging
- Auto cleanup

---

## 🎯 Next Steps

### Step 1: Run the tests
```bash
npm run test:e2e:headed
```

### Step 2: Watch them complete (or fail)

### Step 3: Share results with me

**Either:**
- Screenshot the terminal output
- Or share the HTML report
- Or share specific error messages

### Step 4: I fix the bugs using the automated feedback

### Step 5: Re-run tests to verify fixes

### Step 6: Add more test cases as needed

---

## 🚀 Other Commands

```bash
# Run without browser (faster)
npm run test:e2e

# Run in interactive UI (best for debugging)
npm run test:e2e:ui

# Run all tests (E2E + unit tests)
npm test

# Show last report
npm run test:report
```

---

## 💡 Tips

1. **Tests run in DevMode** - make sure it's enabled
   ```bash
   wp sm devmode status
   wp sm devmode enable  # if needed
   ```

2. **Tests clean up after themselves** - uses emails like `test-{timestamp}@example.com`

3. **Tests take 2-3 minutes** - much faster than manual testing

4. **Tests are deterministic** - same result every time

5. **Tests don't cost money** - DevMode uses mocks (no OpenAI API calls)

---

## 🎉 Why This is Better

✅ **You save hours** - no more manual testing loops
✅ **I get better data** - screenshots, logs, state, everything
✅ **We catch regressions** - if something breaks, we know immediately
✅ **We build confidence** - green tests = working code
✅ **We iterate faster** - fix, test, verify in minutes

---

## 📖 Full Documentation

See `E2E-AUTOMATION-GUIDE.md` for complete details.

---

**Ready? Let's find those bugs!** 🐛🔍

```bash
npm run test:e2e:headed
```

**Watch the automation magic!** ✨

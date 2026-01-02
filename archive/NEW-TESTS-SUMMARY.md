# New Test Cases Added - Unlock & Navigation Flow

**Date:** 2025-12-25
**Tests Added:** 3 new comprehensive test cases (Tests 10, 11, 12)
**Total Tests:** 12 tests

---

## 🎯 What These Tests Verify

These new tests specifically target the bugs you described in your workflow:

### **TEST 10: Unlock Section + Refresh (Design Integrity)**

**What it tests:**
- Generate/navigate to report
- Click first unlock button → Refresh page
- Verify design stays intact (header, containers, body classes)
- Verify unlocked section persists after refresh
- Click second unlock button → Refresh page again
- Verify design STILL intact
- Verify both unlocked sections persist

**Expected Behavior:**
- ✅ Page structure remains consistent after unlock + refresh
- ✅ Unlocked sections stay unlocked (stored in sessionStorage)
- ✅ No CSS/rendering breaks
- ✅ No infinite loops or 500 errors

**What You're Seeing (Bug):**
- ❌ Design changes after refresh
- ❌ Parts of code break
- ❌ Page becomes "funky"

**Screenshots Generated:**
- `test-results/before-unlock.png` - Initial state
- `test-results/after-unlock-1-refresh.png` - After 1st unlock + refresh
- `test-results/after-unlock-2-refresh.png` - After 2nd unlock + refresh

---

### **TEST 11: Third Unlock + Paywall Redirect + Back Button**

**What it tests:**
- Navigate to report with 2 sections already unlocked
- Click third unlock button → Should redirect to paywall/offerings
- Click browser BACK button
- **CRITICAL:** Verify user returns to REPORT (NOT first page)
- Verify session state persists (reading data, unlocked sections)

**Expected Behavior:**
- ✅ Third unlock redirects to paywall (correct)
- ✅ Back button returns to report page
- ✅ Session still active (reading data intact)
- ✅ User sees their report with 2 unlocked sections

**What You're Seeing (Bug):**
- ❌ Back button redirects to FIRST PAGE instead of report
- ❌ Session appears to be cleared on paywall redirect

**Current Implementation (from CLAUDE.md:71):**
```
Paywall redirect: Clear all reading/session keys before redirect
```

**This is the root cause!** The current code clears session on paywall redirect, but it shouldn't if the user is still in an active browser session.

**Screenshots Generated:**
- `test-results/after-back-from-paywall.png` - Page state after back button

---

### **TEST 12: Session Expiry Simulation**

**What it tests:**
- Create session with reading and unlocked sections
- Simulate browser close/session expiry (clearSession)
- Navigate to report URL with expired session
- Verify user is NOT shown the reading
- Verify fresh start is required

**Expected Behavior:**
- ✅ Expired session should NOT allow access to report
- ✅ User should start fresh flow or be prompted to login

**Why This Matters:**
- Differentiates between active session (back button should work) vs expired session (should restart)

---

## 🔍 Key Differences Between Tests 11 & 12

| Scenario | Session State | Back Button Behavior |
|----------|---------------|---------------------|
| **Test 11** | Active session (browser open) | Should return to report |
| **Test 12** | Expired/cleared session | Should NOT access report |

**Current Bug:** Test 11 is likely failing because the code treats active sessions like expired sessions when hitting the paywall.

---

## 🏃 Running the Tests

```bash
# Run all tests (headless)
npm test

# Run with visible browser (see what's happening)
npm run test:headed

# Run specific test
npm test -- --grep "Third unlock"

# Interactive UI mode (recommended for debugging)
npm run test:ui

# Debug mode (step through)
npm run test:debug
```

---

## 📊 What You Should See (When Tests Run)

### If Tests Pass ✅
```
✅ TEST 10: Design intact, sections persisted
✅ TEST 11: Back button returned to report correctly
✅ TEST 12: Session expiry handled correctly
```

### If Tests Fail ❌ (Expected Based on Your Bug Report)

**TEST 10 (Design Integrity):**
```
❌ expect(afterFirstUnlock.hasHeader).toBe(true)
   Expected: true
   Received: false

❌ expect(afterSecondUnlock.hasReportContainer).toBe(true)
   Expected: true
   Received: false
```
→ Indicates page structure breaking after unlock + refresh

**TEST 11 (Back Button):**
```
❌ expect(isBackOnReport).toBe(true)
   Expected: true
   Received: false

❌ expect(isOnFirstPage).toBe(false)
   Expected: false
   Received: true
```
→ Confirms back button goes to first page instead of report

**TEST 12 (Session Expiry):**
```
✅ Should pass (this is likely working correctly)
```

---

## 🐛 Root Causes to Investigate

### 1. **Design Breaking After Unlock + Refresh**

**Possible causes:**
- Unlocked sections not being restored from database/sessionStorage
- CSS classes not being reapplied after page load
- JavaScript re-initialization breaking DOM structure
- Race condition between reading load and unlock state restoration

**Where to look:**
- `assets/js/script.js` - Page initialization logic
- `assets/js/teaser-reading.js` - Report rendering logic
- `includes/class-sm-unlock-handler.php` - Unlock state persistence

### 2. **Back Button Redirects to First Page**

**Root cause (confirmed from CLAUDE.md:71):**
```php
// Current implementation (WRONG for active sessions):
// When redirecting to paywall, clear ALL session keys
sessionStorage.clear(); // ❌ This is too aggressive!
```

**Expected implementation:**
```php
// Should preserve session for active users:
// Only clear if session is actually expired/invalid
// Set a flag that paywall was triggered, but keep reading data
sessionStorage.setItem('sm_paywall_triggered', 'true');
// Do NOT clear: sm_reading_loaded, sm_reading_lead_id, sm_unlocked_sections
```

**Where to fix:**
- `assets/js/script.js` - Paywall redirect logic
- `includes/class-sm-unlock-handler.php` - Unlock limit check

---

## 💡 DevMode Clarification

**Your question:** Should reports be saved to database in DevMode?

**Answer:** YES! ✅

DevMode should:
- ✅ Save readings to `wp_sm_readings` table (with mock data)
- ✅ Store unlocked sections in database
- ✅ Maintain all session state identically to production
- ❌ NOT call OpenAI API (use fixtures/mock responses instead)

**The only difference:**
- Production: Call OpenAI API → get response → save to DB
- DevMode: Load fixture file → save to DB (exact same storage logic)

**If reports aren't being saved in DevMode, that's a bug in the DevMode implementation.**

---

## 📸 Test Artifacts

All tests generate detailed console output and screenshots:

**Console Logs:**
- Session state at each step
- API requests made
- Infinite loop detection checks
- URL changes
- Error detection

**Screenshots (Tests 10 & 11):**
- Visual evidence of page state at critical moments
- Helps debug CSS/rendering issues
- Located in `test-results/` directory

---

## 🎯 Next Steps (After Running Tests)

1. **Run the tests:** `npm test` (or `npm run test:headed` to watch)
2. **Review test output:** Look for specific failures
3. **Check screenshots:** Visual confirmation of issues
4. **Identify root causes:** Based on which assertions fail
5. **Fix bugs:** Starting with the most critical (Test 11 - back button)
6. **Re-run tests:** Verify fixes don't introduce regressions

---

## 📝 Important Notes

- **Do NOT change test expectations** - These tests verify correct behavior
- **Tests may fail in DevMode** if unlock buttons aren't rendered (expected)
- **Screenshots are invaluable** for debugging CSS/rendering issues
- **Session vs. URL state** - Both need to persist correctly

---

**Ready to run?** 🚀

```bash
npm test
```

Look for the new test output (Tests 10, 11, 12) and share the results! The failures will tell us exactly what needs to be fixed.

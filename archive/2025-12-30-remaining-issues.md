# remaining-issues.md

**Last Updated:** 2025-12-30 (Post Phase 5 Implementation)

## ✅ FIXED: Async Reading Generation (Teaser + Paid)
- **Was:** `Undefined variable $async` warnings in `includes/class-sm-rest-controller.php`
- **Status:** ✅ **VERIFIED FIXED** in Phase 4 (line 2004)
- **Fix:** `$async = $this->get_async_flag( $request );` properly extracted at the top of `handle_paid_reading_generate`

## ✅ FIXED: Loading Screen Email Note Not Showing
- **Was:** "We will email you…" message missing from loading screen
- **Status:** ✅ **FIXED** in Phase 5
- **Fix:** Added missing `updateLoadingNote()` function (api-integration.js:1417-1435)
  - Stops rotating messages when async processing starts
  - Updates primary loading subtext with static message
  - `updateLoadingEmailNoteWhenReady()` adds email notification below subtext
- **Verify:** Check that both messages appear on loading screen during async generation

## ✅ FIXED: Flaky Error Messages During Polling
- **Was:** Users might see error states even when job is still processing
- **Status:** ✅ **FIXED** in Phase 5
- **Fix:** Enhanced polling logic (api-integration.js:1380-1428)
  - Properly detects failed jobs (`!response.success`)
  - Only shows errors when job actually fails
  - Re-throws errors with full backend error data preserved
  - Increased polling interval to 5s
  - Max polling time increased to 5 minutes (was 3 minutes)

## 🧪 NEEDS TESTING: Completion Email Delivery
- **Symptom:** Emails may not arrive after async completion
- **Status:** 🧪 **READY FOR TESTING** (Phase 4 implemented email handling)
- **What was done:**
  - Email delivery logging enhanced (Phase 0)
  - Vision failure emails implemented (Phase 4)
  - Job timeout handling added (Phase 4)
- **What to test:** Generate 10 async readings and verify emails arrive
- **Where to look:** Check logs in `wp-content/uploads/sm-logs/debug.log` for:
  - "Reading job queued"
  - "Reading job dispatch requested"
  - "Reading job run triggered"
  - "Reading job completed"
  - "Completion email sent" OR "Completion email failed to send"
  - Check MailPit UI (Flywheel) or inbox for actual email delivery

## 🧪 NEEDS TESTING: Async Job Performance
- **Goal:** Verify OpenAI API call reduction improves generation time
- **Status:** 🧪 **READY FOR TESTING** (Phases 2-3 implemented call reduction)
- **What was done:**
  - Teaser: Reduced from 4-5 calls → 2 calls (Phase 2)
  - Paid (new): Reduced from 5-6 calls → 3 calls (Phase 3)
  - Paid (upgrade): 1 call only (Phase 3)
  - Disabled expansion retries (best-effort approach)
- **What to test:**
  - Teaser: Target 30-45 seconds (10 test generations)
  - Paid: Target 45-60 seconds (5 new + 5 upgrades)
- **Where to verify:**
  - Check logs for `OpenAI API call completed` entries (should show call count)
  - Check job duration in logs: `Reading job completed` with `duration_seconds`

---

## 📋 Next Steps (Phase 6: Testing & Documentation)

1. **Manual Testing** (Ready Now):
   - Generate 10 async teaser readings
   - Generate 5 async paid readings (new)
   - Generate 5 async paid upgrades
   - Verify email delivery via MailPit or inbox
   - Verify performance targets met

2. **E2E Test Development** (After Manual Testing):
   - Add `tests/async-optimization.spec.js`
   - Verify polling behavior
   - Verify email delivery (MailPit API integration)
   - Verify vision failure resubmission flow

3. **Documentation Updates**:
   - Update `CODEX.md` if API behavior changed
   - Update `CONTEXT.md` if architecture changed
   - Archive Phase 6 completion notes

# Async-First Reading Generation Optimization - Progress Tracker

**Project:** Performance & Reliability Enhancement
**Started:** 2025-12-30
**Status:** 🚧 IN PROGRESS
**Requirements:** `ASYNC-OPTIMIZATION-REQUIREMENTS.md`

---

## 📊 Overall Progress

```
Phase 1: OpenAI Prompt Consolidation       [........] 0% - NOT STARTED
Phase 2: Paid Report Optimization          [........] 0% - NOT STARTED
Phase 3: Async Job Reliability             [........] 0% - NOT STARTED
Phase 4: Frontend Enhancements             [........] 0% - NOT STARTED
Phase 5: Seen/Unseen Tracking              [........] 0% - NOT STARTED
Phase 6: Retry Logic & Error Handling      [........] 0% - NOT STARTED
Phase 7: Testing & Documentation           [........] 0% - NOT STARTED

TOTAL PROJECT PROGRESS: [........] 0%
```

---

## Phase 1: OpenAI Prompt Consolidation ⏳

**Goal:** Reduce teaser API calls from 4-5 to 2
**Timeline:** Week 1
**Status:** NOT STARTED
**Progress:** 0% [........]

### Tasks

- [ ] **1.1 Create Unified Vision Prompt (Call 1)**
  - [ ] Write system prompt for Vision API
  - [ ] Write user prompt template (palm_snapshot + intro + foundations)
  - [ ] Add word count targets (~100w intro, ~200w foundations, min 140w)
  - [ ] Test prompt with 5 sample palm images
  - [ ] Review output quality (manual review)
  - **File:** `includes/class-sm-ai-handler.php` (new method: `build_unified_vision_prompt()`)

- [ ] **1.2 Create Unified Completion Prompt (Call 2)**
  - [ ] Write system prompt for Completion API
  - [ ] Write user prompt template (all teaser sections)
  - [ ] Reference palm_snapshot from Call 1 in context
  - [ ] Add word count targets (best effort, no strict validation)
  - [ ] Test prompt with palm snapshots from Call 1
  - **File:** `includes/class-sm-ai-handler.php` (new method: `build_unified_teaser_prompt()`)

- [ ] **1.3 Refactor `generate_teaser_context()` Method**
  - [ ] Replace `generate_palm_summary()` with new unified Vision call
  - [ ] Remove `generate_teaser_part('core_a')` call
  - [ ] Remove `generate_teaser_part('core_b')` call
  - [ ] Remove `generate_teaser_part('secondary')` call
  - [ ] Add new unified Completion call
  - [ ] Extract palm_snapshot from Vision response for reuse
  - [ ] Merge responses into `$reading_data`
  - **File:** `includes/class-sm-ai-handler.php` (lines 143-276)

- [ ] **1.4 Remove Expansion Retry Logic**
  - [ ] Comment out `expand_short_teaser_sections()` call (line 211)
  - [ ] Add logging: "Expansion retries disabled - using best-effort prompts"
  - [ ] Keep method for now (may revert if needed)
  - **File:** `includes/class-sm-ai-handler.php` (line 211)

- [ ] **1.5 Update Schema Validation**
  - [ ] Relax word count validation (best effort only)
  - [ ] Keep minimum 140w for `foundations_of_path`
  - [ ] Log warnings for short sections (don't fail)
  - **File:** `includes/class-sm-teaser-reading-schema-v2.php`

- [ ] **1.6 Add API Call Count Logging**
  - [ ] Log each API call: type, tokens used, duration
  - [ ] Log total calls per reading (verify ≤ 2)
  - [ ] Add to existing logging: `[SM AI_READING]`
  - **File:** `includes/class-sm-ai-handler.php` (in `generate_teaser_context()`)

- [ ] **1.7 Testing - Sample Readings**
  - [ ] Generate 10 test teaser readings
  - [ ] Verify exactly 2 API calls for each
  - [ ] Log token usage per reading
  - [ ] Manual content quality review
  - [ ] Compare to baseline (old 4-5 call system)
  - [ ] Document any quality issues

### Acceptance Criteria

- ✅ Teaser generation uses exactly 2 API calls (100% compliance on test runs)
- ✅ No expansion retries triggered
- ✅ Content quality maintained (no significant degradation vs baseline)
- ✅ All unit tests pass
- ✅ Token usage reduced by 30-40% vs baseline

### Notes

- **Current baseline:** 4-5 API calls, ~3,000 tokens average
- **Target:** 2 API calls, ~2,000 tokens average
- **Risk:** Content quality may suffer - need manual review
- **Rollback plan:** Re-enable 3-part prompts + expansion retries

---

## Phase 2: Paid Report Optimization ⏳

**Goal:** Reduce paid API calls from 5-6 to 3
**Timeline:** Week 1
**Status:** NOT STARTED
**Progress:** 0% [........]

### Tasks

- [ ] **2.1 Create Premium Sections Prompt (Call 3)**
  - [ ] Write system prompt for premium content
  - [ ] Write user prompt template (5 premium sections)
  - [ ] Reference palm_snapshot + existing teaser context
  - [ ] Add word count targets (250-350w per section)
  - [ ] Test prompt with existing teaser context
  - **File:** `includes/class-sm-ai-handler.php` (new method: `build_premium_prompt()`)

- [ ] **2.2 Update `generate_paid_reading()` Method**
  - [ ] Uses `generate_teaser_context()` (already optimized to 2 calls)
  - [ ] Add new Call 3 for premium sections
  - [ ] Merge teaser + premium data
  - [ ] Verify total = 3 API calls
  - **File:** `includes/class-sm-ai-handler.php` (lines 285-341)

- [ ] **2.3 Verify `generate_paid_reading_from_teaser()` Unchanged**
  - [ ] Already optimized (reuses teaser, 1 call only)
  - [ ] Ensure Call 3 (premium sections) still works
  - [ ] Test upgrade flow end-to-end
  - **File:** `includes/class-sm-ai-handler.php` (lines 351-484)

- [ ] **2.4 Add Paid API Call Logging**
  - [ ] Log each API call: type, tokens, duration
  - [ ] Log total calls: new=3, upgrade=1
  - [ ] Add to existing logging: `[SM AI_READING]`
  - **File:** `includes/class-sm-ai-handler.php`

- [ ] **2.5 Testing - New Paid Reports**
  - [ ] Generate 5 new paid reports (no existing teaser)
  - [ ] Verify exactly 3 API calls for each
  - [ ] Log token usage per reading
  - [ ] Manual content quality review (premium sections)

- [ ] **2.6 Testing - Paid Upgrades**
  - [ ] Upgrade 5 existing teasers to paid
  - [ ] Verify exactly 1 API call for each
  - [ ] Verify teaser content preserved
  - [ ] Verify premium sections added correctly
  - [ ] Verify credit deduction works

### Acceptance Criteria

- ✅ New paid reports use exactly 3 API calls
- ✅ Upgraded paid reports use exactly 1 API call
- ✅ Teaser content preserved in upgrades
- ✅ Premium sections meet quality standards
- ✅ Credit deduction idempotency works correctly

### Notes

- **Current baseline:** 5-6 API calls for new paid, 1 for upgrades
- **Target:** 3 API calls for new paid, 1 for upgrades (unchanged)
- **Upgrade flow already optimized** - just verify it still works

---

## Phase 3: Async Job Reliability ⏳

**Goal:** Fix async job issues and email delivery
**Timeline:** Week 2
**Status:** NOT STARTED
**Progress:** 0% [........]

### Tasks

- [ ] **3.1 Fix `$async` Undefined Variable Bug**
  - [ ] Locate undefined `$async` in `class-sm-rest-controller.php` (lines 2097-2098)
  - [ ] Add `$async = $this->get_async_flag( $request );` at top of handler
  - [ ] Verify async flag extracted correctly
  - [ ] Test both async=true and async=false
  - **File:** `includes/class-sm-rest-controller.php`
  - **Reference:** `remaining-issues.md` line 3-6

- [ ] **3.2 Add Retry Logic to Non-Blocking Dispatch**
  - [ ] Update `dispatch_job_request()` to retry 3 times on failure
  - [ ] Add exponential backoff (1s, 2s, 4s)
  - [ ] Log each dispatch attempt
  - [ ] Log final success/failure
  - **File:** `includes/class-sm-reading-job-handler.php` (lines 181-209)

- [ ] **3.3 Enhance Email Delivery Error Handling**
  - [ ] Wrap `wp_mail()` in try-catch
  - [ ] Log email send success/failure with details
  - [ ] Add email validation before send
  - [ ] Test SMTP failure scenario
  - **File:** `includes/class-sm-reading-job-handler.php` (lines 382-444)

- [ ] **3.4 Add Job Timeout Detection**
  - [ ] Check job `updated_at` timestamp
  - [ ] If running > 5 minutes, mark as failed
  - [ ] Add cron job to cleanup stuck jobs
  - [ ] Log timeout events
  - **File:** `includes/class-sm-reading-job-handler.php` (new method: `cleanup_stuck_jobs()`)

- [ ] **3.5 Add Comprehensive Job Logging**
  - [ ] Log job creation: lead_id, type, job_id
  - [ ] Log job start: attempt number
  - [ ] Log API calls: count, tokens, duration
  - [ ] Log job completion: reading_id, total time
  - [ ] Log email send: success/failure
  - [ ] Tag: `[SM READING_JOB]`
  - **Files:** `includes/class-sm-reading-job-handler.php`, `includes/class-sm-ai-handler.php`

- [ ] **3.6 Testing - Email Delivery**
  - [ ] Generate 10 async readings
  - [ ] Verify email arrives for each
  - [ ] Check email delivery time (< 5 min)
  - [ ] Test failure scenario (invalid email)
  - [ ] Verify logs capture all events
  - [ ] Target: >98% delivery rate

### Acceptance Criteria

- ✅ No `$async` undefined errors in logs
- ✅ Emails arrive for >98% of completed jobs
- ✅ Jobs timeout gracefully after 5 minutes
- ✅ All job stages logged comprehensively
- ✅ Non-blocking dispatch retries on failure

### Notes

- **Current issue:** Emails not arriving despite job completion logs
- **Root cause:** Unknown - need to investigate wp_mail() failures
- **Test with:** Multiple email providers (Gmail, Outlook, custom domain)

---

## Phase 4: Frontend Enhancements ⏳

**Goal:** Add job status polling and auto-refresh
**Timeline:** Week 2
**Status:** NOT STARTED
**Progress:** 0% [........]

### Tasks

- [ ] **4.1 Create Job Status Endpoint**
  - [ ] Add REST route: `GET /wp-json/soulmirror/v1/reading/job-status`
  - [ ] Parameters: lead_id, reading_type
  - [ ] Response: status, reading_id, error_message, progress_percent
  - [ ] Add nonce verification
  - [ ] Add rate limiting (1 req/5s per user)
  - **File:** `includes/class-sm-rest-controller.php` (new method: `handle_job_status()`)

- [ ] **4.2 Implement Frontend Polling**
  - [ ] Start polling when loading screen appears
  - [ ] Interval: Every 5 seconds
  - [ ] Stop polling after 5 minutes OR when complete/failed
  - [ ] Handle network errors gracefully
  - [ ] Log polling requests to console
  - **File:** `assets/js/api-integration.js` (new function: `pollJobStatus()`)

- [ ] **4.3 Add Auto-Jump to Report**
  - [ ] When status = 'completed', stop polling
  - [ ] Get reading_id from response
  - [ ] Navigate to report URL with reading_id
  - [ ] Show success message before navigation
  - **File:** `assets/js/api-integration.js`

- [ ] **4.4 Update Loading Screen Message**
  - [ ] Primary message: "Your reading is being generated..."
  - [ ] Secondary message: "You'll receive an email when it's ready, or you can wait here for it to complete."
  - [ ] Keep mystical subtext rotation
  - **File:** `assets/js/script.js` or template file

- [ ] **4.5 Add Error Display on Failure**
  - [ ] When status = 'failed', stop polling
  - [ ] Display error_message from response
  - [ ] Show retry button (if under retry limit)
  - [ ] Show support link if over limit
  - **File:** `assets/js/api-integration.js`

- [ ] **4.6 Testing - Polling Flow**
  - [ ] Start generation, verify polling starts
  - [ ] Wait for completion, verify auto-jump
  - [ ] Force failure, verify error display
  - [ ] Test network interruption during polling
  - [ ] Verify polling stops after 5 min
  - [ ] Check browser console for errors

### Acceptance Criteria

- ✅ Frontend polls every 5 seconds
- ✅ Auto-jump triggers when job completes
- ✅ Error messages display correctly on failure
- ✅ Polling stops after completion/failure/timeout
- ✅ No performance degradation from polling
- ✅ Rate limiting prevents abuse

### Notes

- **Performance:** Polling endpoint must be lightweight (< 200ms)
- **Caching:** Cache job status for 3 seconds to reduce DB queries
- **Fallback:** If polling fails, user can still get email

---

## Phase 5: Seen/Unseen Tracking ⏳

**Goal:** Add seen/unseen indicators to dashboard
**Timeline:** Week 3
**Status:** NOT STARTED
**Progress:** 0% [........]

### Tasks

- [ ] **5.1 Database Migration - Add Columns**
  - [ ] Add `seen_at` column (DATETIME NULL)
  - [ ] Add `retry_count` column (INT DEFAULT 0)
  - [ ] Add index: `idx_lead_seen (lead_id, seen_at)`
  - [ ] Run migration on dev database
  - [ ] Verify columns created
  - **File:** `includes/class-sm-database.php` (new migration method)

- [ ] **5.2 Create Mark-Seen Endpoint**
  - [ ] Add REST route: `POST /wp-json/soulmirror/v1/reading/mark-seen`
  - [ ] Body: { reading_id }
  - [ ] Update `seen_at = NOW()`
  - [ ] Return success + seen_at timestamp
  - [ ] Add nonce verification
  - **File:** `includes/class-sm-rest-controller.php` (new method: `handle_mark_seen()`)

- [ ] **5.3 Add JS to Mark Report as Seen**
  - [ ] On report page load, check if reading_id exists
  - [ ] Call `POST /reading/mark-seen` with reading_id
  - [ ] Log success/failure to console
  - [ ] Only call once per page load
  - **File:** `assets/js/api-integration.js` or report template

- [ ] **5.4 Update Dashboard Query**
  - [ ] Fetch `seen_at` column in reports listing query
  - [ ] Order by: unseen first, then by created_at DESC
  - [ ] Pass seen_at to template
  - **File:** `templates/dashboard.php` or `includes/class-sm-reports-handler.php`

- [ ] **5.5 Add Visual Indicators**
  - [ ] Unseen reports: Light blue/yellow background
  - [ ] Seen reports: Normal background
  - [ ] Optional: "NEW" badge on unseen reports
  - [ ] Add CSS for highlighting
  - **File:** `templates/dashboard.php`, `assets/css/auth.css`

- [ ] **5.6 Testing - Seen/Unseen Flow**
  - [ ] Seed 3 unseen reports
  - [ ] Load dashboard, verify unseen highlighting
  - [ ] Click report, verify mark-seen API called
  - [ ] Reload dashboard, verify report marked as seen
  - [ ] Test with multiple unseen reports

### Acceptance Criteria

- ✅ Database migration runs successfully
- ✅ Unseen reports highlighted in dashboard
- ✅ Clicking report marks it as seen
- ✅ Indicator updates on next dashboard load
- ✅ No errors in mark-seen API calls

### Notes

- **Not mission-critical:** Simple visual aid, doesn't need to be 100% accurate
- **Edge case:** User opens report in multiple tabs - mark seen on first load only

---

## Phase 6: Retry Logic & Error Handling ⏳

**Goal:** Implement smart retry limits and error messaging
**Timeline:** Week 3
**Status:** NOT STARTED
**Progress:** 0% [........]

### Tasks

- [ ] **6.1 Add Retry Count Tracking**
  - [ ] Increment `retry_count` in database on each retry
  - [ ] Store retry_count with job record
  - [ ] Query retry_count before allowing retry
  - **File:** `includes/class-sm-reading-job-handler.php`

- [ ] **6.2 Create Retry Endpoint**
  - [ ] Add REST route: `POST /wp-json/soulmirror/v1/reading/retry`
  - [ ] Body: { lead_id, reading_type }
  - [ ] Check retry_count vs limits (free: 1, paid: 2)
  - [ ] If under limit: increment count, create new job
  - [ ] If over limit: return error
  - [ ] Add nonce verification
  - **File:** `includes/class-sm-rest-controller.php` (new method: `handle_retry()`)

- [ ] **6.3 Enforce Retry Limits**
  - [ ] Free reports (palm_teaser): Max 1 retry
  - [ ] Paid reports (palm_full): Max 2 retries
  - [ ] Check limit before creating job
  - [ ] Return error if exceeded
  - **File:** `includes/class-sm-rest-controller.php`

- [ ] **6.4 Update Failure Email Template**
  - [ ] Add subject: "Your Reading Generation Failed"
  - [ ] Add body with retry link
  - [ ] Include retry count (X of Y attempts)
  - [ ] Send only if retry count < limit
  - **File:** `includes/class-sm-reading-job-handler.php` (update `send_completion_email()`)

- [ ] **6.5 Add Retry Button to Frontend**
  - [ ] Show retry button when job status = 'failed'
  - [ ] Call `POST /reading/retry` endpoint
  - [ ] Handle success: show "Retrying..." message
  - [ ] Handle error (limit exceeded): show support link
  - **File:** `assets/js/api-integration.js`

- [ ] **6.6 Testing - Retry Flow**
  - [ ] Force job failure (OpenAI timeout)
  - [ ] Verify failure email with retry link
  - [ ] Click retry (under limit), verify new job created
  - [ ] Force failure again, verify retry count incremented
  - [ ] Exceed limit, verify retry blocked
  - [ ] Verify error message shown

### Acceptance Criteria

- ✅ Retry limits enforced correctly (free: 1, paid: 2)
- ✅ Failure emails include retry link
- ✅ Users can retry within limits
- ✅ Clear error message when limit exceeded
- ✅ Retry count persisted correctly

### Notes

- **Free users:** 1 retry = 2 total attempts
- **Paid users:** 2 retries = 3 total attempts
- **After limit:** Show support contact info

---

## Phase 7: Testing & Documentation ⏳

**Goal:** Comprehensive testing and documentation updates
**Timeline:** Week 4
**Status:** NOT STARTED
**Progress:** 0% [........]

### Tasks

- [ ] **7.1 Write E2E Tests**
  - [ ] Test: Teaser async generation (2 API calls)
  - [ ] Test: Paid generation from scratch (3 API calls)
  - [ ] Test: Paid upgrade from teaser (1 API call)
  - [ ] Test: Job status polling + auto-refresh
  - [ ] Test: Seen/unseen tracking
  - [ ] Test: Retry logic (under limit, over limit)
  - [ ] Test: User navigates away + email delivery
  - **File:** `tests/async-optimization.spec.js` (new file)

- [ ] **7.2 Run 50 Test Readings**
  - [ ] 25 teaser readings (real OpenAI calls)
  - [ ] 25 paid readings (15 new, 10 upgrades)
  - [ ] Log all metrics (API calls, tokens, time, email delivery)
  - [ ] Manual content quality review (sample 10)
  - [ ] Document any failures or issues

- [ ] **7.3 Verify Performance Metrics**
  - [ ] Teaser: ≤ 2 API calls (100% compliance)
  - [ ] Paid new: ≤ 3 API calls (100% compliance)
  - [ ] Paid upgrade: 1 API call (100% compliance)
  - [ ] Teaser time: ≤ 45s (80% of runs)
  - [ ] Paid time: ≤ 60s (80% of runs)
  - [ ] Email delivery: > 98%
  - [ ] Job completion: > 99%

- [ ] **7.4 Update CLAUDE.md**
  - [ ] Mark project as COMPLETE
  - [ ] Update "Current Phase" section
  - [ ] Add lessons learned
  - [ ] Update constraints if needed
  - **File:** `CLAUDE.md`

- [ ] **7.5 Update CODEX.md**
  - [ ] Mark project as COMPLETE
  - [ ] Update quick reference
  - [ ] Add new API endpoints documentation
  - [ ] Update testing section
  - **File:** `CODEX.md`

- [ ] **7.6 Update TESTING.md**
  - [ ] Add async optimization test cases
  - [ ] Document new E2E tests
  - [ ] Update test helper API (if added)
  - [ ] Add performance benchmarks
  - **File:** `archive/2025-12-28-md-archive/TESTING.md`

- [ ] **7.7 Final Documentation Review**
  - [ ] Review all requirements met
  - [ ] Update PROGRESS.md to 100%
  - [ ] Archive old investigation docs
  - [ ] Create summary report

### Acceptance Criteria

- ✅ All E2E tests pass (100%)
- ✅ Performance targets met (verified with 50 test runs)
- ✅ Documentation complete and accurate
- ✅ No regressions in existing flows (free + logged-in)
- ✅ Content quality maintained or improved

### Notes

- **Test thoroughly:** This optimization changes core generation logic
- **Monitor production:** Watch metrics daily for first week after deploy
- **Rollback ready:** Keep old prompts for 2 weeks in case of issues

---

## 📈 Performance Metrics Tracking

### Baseline (Before Optimization)

| Metric | Baseline Value |
|--------|----------------|
| Teaser API Calls | 4-5 calls |
| Paid API Calls (New) | 5-6 calls |
| Paid API Calls (Upgrade) | 1 call ✅ |
| Teaser Gen Time | 30-60s |
| Paid Gen Time | 60-90s |
| Teaser Token Usage | ~3,000 tokens |
| Paid Token Usage | ~5,000 tokens |
| Email Delivery Rate | ~0% (failing) |
| Job Completion Rate | ~80% |

### Target (After Optimization)

| Metric | Target Value | Progress |
|--------|--------------|----------|
| Teaser API Calls | 2 calls max | - |
| Paid API Calls (New) | 3 calls max | - |
| Paid API Calls (Upgrade) | 1 call ✅ | ✅ Already optimized |
| Teaser Gen Time | 30-45s | - |
| Paid Gen Time | 45-60s | - |
| Teaser Token Usage | ~2,000 tokens | - |
| Paid Token Usage | ~3,500 tokens | - |
| Email Delivery Rate | >98% | - |
| Job Completion Rate | >99% | - |

### Test Results (Update After Phase 7)

| Metric | Actual Value | Target Met? |
|--------|--------------|-------------|
| Teaser API Calls | - | - |
| Paid API Calls (New) | - | - |
| Paid API Calls (Upgrade) | - | - |
| Teaser Gen Time (avg) | - | - |
| Paid Gen Time (avg) | - | - |
| Teaser Token Usage (avg) | - | - |
| Paid Token Usage (avg) | - | - |
| Email Delivery Rate | - | - |
| Job Completion Rate | - | - |

---

## 🐛 Issues & Blockers

### Active Issues

*None yet - add as discovered*

### Resolved Issues

*Add resolved issues here with resolution date*

---

## 📝 Notes & Learnings

### Key Decisions

*Document important architectural decisions made during implementation*

### Lessons Learned

*Add lessons learned after completing each phase*

### Risks & Mitigations

*Document risks encountered and how they were mitigated*

---

## 📅 Timeline

| Phase | Start Date | End Date | Status |
|-------|------------|----------|--------|
| Phase 1: Prompt Consolidation | - | - | NOT STARTED |
| Phase 2: Paid Optimization | - | - | NOT STARTED |
| Phase 3: Async Reliability | - | - | NOT STARTED |
| Phase 4: Frontend Enhancements | - | - | NOT STARTED |
| Phase 5: Seen/Unseen Tracking | - | - | NOT STARTED |
| Phase 6: Retry Logic | - | - | NOT STARTED |
| Phase 7: Testing & Docs | - | - | NOT STARTED |

**Estimated Completion:** 4 weeks from start
**Actual Completion:** -

---

## ✅ Completion Checklist

**Before marking project COMPLETE:**

- [ ] All 7 phases completed (100%)
- [ ] All acceptance criteria met
- [ ] All E2E tests pass
- [ ] 50 test readings completed successfully
- [ ] Performance metrics verified
- [ ] No regressions in existing flows
- [ ] Documentation updated (CLAUDE.md, CODEX.md, TESTING.md)
- [ ] PROGRESS.md updated to 100%
- [ ] Summary report created

**Final Sign-Off:**

- [ ] Technical Review: -
- [ ] QA Review: -
- [ ] Project Owner Approval: -

---

**Last Updated:** 2025-12-30
**Next Update:** After completing Phase 1 tasks

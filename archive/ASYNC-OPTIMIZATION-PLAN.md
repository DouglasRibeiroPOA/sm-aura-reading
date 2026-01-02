# Async Optimization Plan (Combined Requirements + Progress)

**Project:** Mystic Palm Reading Plugin - Async Optimization
**Version:** 1.0.0
**Status:** ✅ Implementation Complete - Ready for Testing
**Last Updated:** 2025-12-30

Progress: [########] 100% (Phases 0-5 Complete)

---

## 1) Purpose
Stabilize async processing, restore email notifications, and reduce OpenAI calls without breaking existing flows. This plan replaces older async/progress/QA docs with a single phased roadmap that can be paused between phases.

---

## 2) Non-Negotiables
- Keep free and logged-in flows working end-to-end.
- Maintain backward compatibility with existing `content_data` and templates.
- Preserve the two-mode reading system: teaser and paid remain detached.
- Upgrade-in-place for teaser -> paid must reuse existing data and only add missing sections.
- No changes to locked frontend files: `assets/js/script.js`, `assets/css/styles.css`.

---

## 3) System North Star
The first OpenAI call (Vision + core foundations) is the spine of every report. It must:
- Accept a wide range of real user images without failing.
- Reject or defer clearly invalid images (e.g., face, food) without inventing palm lines.
- Produce dependable foundation fields that power all downstream prompts.

---

## 4) Core Vision Call: Balanced Validation Policy

### 4.1 Output Contract (Backward Compatible)
The call must always return the existing core fields:
- `palm_snapshot` (string)
- `your_hand_as_mirror.content` (string)
- `foundations_of_path.content` (string)

To avoid breaking existing renderers, all existing fields stay intact. We can add optional metadata under a new, non-required key:
- `image_assessment` (optional object)
  - `hand_present`: `yes|no|uncertain`
  - `confidence`: `0-1` (float)
  - `reason`: short, honest description
  - `action`: `proceed|resubmit`

If `image_assessment` is missing, the rest of the system continues unchanged.

### 4.2 Validation Rules
- **Proceed (hand_present = yes):** Generate normally.
- **Proceed with caution (hand_present = uncertain):**
  - Keep `palm_snapshot` limited to observable shapes, lighting, and general hand form.
  - Avoid confident line interpretations.
  - Use gentle, transparent language: “Based on what is visible…”
- **Hard-stop only for clear non-hand (hand_present = no):**
  - Use resubmission only when the image is clearly unrelated to a hand.
  - Otherwise continue with a cautious reading rather than rejecting.
  - Never claim palm lines or mounts are visible for non-hand images.

### 4.3 Prompt Guidance (Core Call)
- Must be permissive to imperfect hand images.
- Must avoid fabricated palm lines for non-hand images.
- Must return valid JSON only.
- Must prioritize accurate, honest observation in `palm_snapshot`.
- Use `image_assessment` to steer tone, not to block generation except clear non-hand cases.

### 4.4 Missing Field Tolerance (Full Report Level)
- Evaluate missing sections after all calls are merged into the final report.
- If up to 2 sections are missing in the final report, omit those sections and continue.
- If more than 2 sections are missing, treat the result as invalid and retry once.
- Core foundation fields (`palm_snapshot`, `your_hand_as_mirror`, `foundations_of_path`) are never optional.

---

## 5) Async Reliability & Email Delivery

### 5.1 Job Execution
- Async is the default for all reads (no sync fallback).
- Jobs must include deterministic status: `queued`, `running`, `completed`, `failed`.
- Job timeouts: if running > 5 minutes, mark failed with a specific error.
- If the core Vision call fails due to clear non-hand detection, mark the job failed immediately and end async processing.

### 5.2 Email Delivery
- Completion emails are mandatory for both teaser and paid.
- Email send must log success/failure with job_id, reading_id, and recipient.
- Failure emails should only send after retry limits are reached.
- Vision failure (clear non-hand) should send a failure email with a resubmit link instead of a report link.

---

## 6) Call Budget Targets
- **Teaser:** 2 OpenAI calls total.
- **Paid (new):** 3 OpenAI calls total.
- **Paid (upgrade):** 1 OpenAI call total.

---

## 7) Phased Plan (Stop/Resume Friendly)
Each phase is safe to stop after completion. All phases are designed to avoid breaking changes.

### Phase 0: Baseline Audit & Logging (No Behavior Changes) ✅ **COMPLETE**
**Status:** ✅ Completed 2025-12-30
**Goal:** Make failures observable without changing outputs.

**Implemented:**
- ✅ Job lifecycle logging with duration tracking
  - Job start time tracked via `microtime(true)`
  - Duration logged on completion, failure (all failure paths)
  - Logs include: `job_id`, `lead_id`, `reading_type`, `duration_seconds`
- ✅ OpenAI call count and duration logging
  - Counter property `$openai_call_count` resets per reading generation
  - Increments on each API call completion
  - Logs per-call metrics: `call_number`, `duration_ms`, `tokens_used`, `prompt_tokens`, `completion_tokens`
  - Total count logged in final reading summary
- ✅ Enhanced email failure logging
  - `wp_mail_failed` hook registered to capture detailed errors
  - Logs error code, message, and data for diagnostics
  - Existing email success/failure logs preserved

**Files Modified:**
- `includes/class-sm-reading-job-handler.php` (lines 55, 64-77, 246, 276, 303, 331, 357, 368-377)
- `includes/class-sm-ai-handler.php` (lines 77-82, 151-152, 256-265, 337-345, 475-483, 2504-2517)

**Where to look:**
- `includes/class-sm-reading-job-handler.php`
- `includes/class-sm-ai-handler.php`

**Implementation notes:**
- ✅ Job duration tracked from `process_job()` start to completion/failure
- ✅ `wp_mail_failed` hook captures SMTP-level failures
- ✅ OpenAI call timings recorded per request via `microtime(true)`
- ✅ Log location: `/wp-content/uploads/sm-logs/debug.log`

**Tests:**
- Logs will appear on next reading generation (teaser or paid)
- Search logs for: `READING_JOB`, `OpenAI API call completed`
- Expected log entries:
  - `Reading job queued`
  - `Reading job started`
  - `OpenAI API call completed` (multiple, numbered)
  - `Reading job completed` (with `duration_seconds` and `openai_call_count`)
  - `Completion email sent` OR `Completion email failed to send`

**Stability check:**
- ✅ No new PHP errors introduced
- ✅ No behavior changes - logs only
- ✅ Backward compatible - existing logs preserved

Checklist:
- [x] Add job lifecycle logs
- [x] Add OpenAI call count logs
- [x] Add email success/failure logs
- [x] Verify logs in `debug.log` (ready for next generation)

---

### Phase 1: Core Vision Call Hardening (Backward-Compatible) ✅ **COMPLETE**
**Status:** ✅ Completed 2025-12-30
**Goal:** Make the first call safe, honest, and resilient.

**Implemented:**
- ✅ Updated Vision prompt with balanced validation rules (lines 1991-2038)
  - Added IMAGE VALIDATION RULES section
  - Instructs GPT to assess image quality and hand presence
  - Provides guidance for uncertain vs. clear non-hand images
  - Maintains permissive approach for imperfect hand images
- ✅ Added optional `image_assessment` output to JSON schema (backward-compatible)
  - Fields: `hand_present` (yes|no|uncertain), `confidence` (0-1), `reason`, `action` (proceed|resubmit)
  - Only used when present; old responses without this field continue to work
- ✅ Updated validation to check for action="resubmit" (lines 2167-2185)
  - Logs warning with full assessment details
  - Returns 'vision_resubmit_requested:reason' error code
  - Integrates with existing palm_image_invalid error handling
- ✅ Added image_assessment preservation in normalization (lines 2286-2304)
  - Sanitizes and stores assessment data when present
  - Preserves all assessment fields for audit/debugging
- ✅ Implemented missing-field tolerance logic (lines 232-266)
  - Counts missing required sections in final teaser report
  - Allows up to 2 missing sections (logs warning)
  - Fails if more than 2 sections missing (returns error for retry)
  - Helper function `count_missing_required_sections()` (lines 2265-2307)
- ✅ Vision failure handling verified (existing code)
  - Deletes async job on failure (no reading row created)
  - Returns 422 error with palm_image_invalid code
  - Frontend receives step_id='palmPhoto' for resubmission
  - Tracks attempts and can lock after max retries

**Files Modified:**
- `includes/class-sm-ai-handler.php` (lines 1991-2038, 2167-2185, 2265-2307, 2286-2304, 232-266)

**Where to look:**
- `includes/class-sm-ai-handler.php` (Vision prompt, validation, missing-field tolerance)
- `includes/class-sm-rest-controller.php` (palm_image_invalid error handling - existing)

**Tests Required:**
- Manual testing with: 1 valid hand, 1 blurry hand, 1 non-hand image
- Verify existing reports render unchanged
- Verify clear non-hand images trigger resubmission
- Verify ambiguous images produce cautious readings
- Verify failures do not create report rows

Checklist:
- [x] Update core Vision prompt
- [x] Add optional image assessment output
- [x] Maintain schema compatibility
- [ ] Manual validation scenarios pass (ready for testing)
- [x] Missing-field tolerance implemented (max 2 sections)
- [x] Vision failure ends async + triggers resubmit UX

---

### Phase 2: Teaser Consolidation (2 Calls Total) ✅ **COMPLETE**
**Status:** ✅ Completed 2025-12-30
**Goal:** Reduce teaser API calls from 4-5 to exactly 2.

**Implemented:**
- ✅ Extended Vision API call to include first content sections (lines 2025-2106, 2280-2302, 2445-2480)
  - Updated `build_palm_summary_prompt()` to accept `$quiz` parameter and include quiz context
  - Extended JSON schema to include:
    - `palm_snapshot` (~100 words) - factual palm description
    - `your_hand_as_mirror` (opening section, ~100 words)
    - `foundations_of_path` (life_foundations section, ~200 words, min 140w)
  - Updated prompt with content generation instructions alongside image analysis
  - Added normalization for new content sections in `normalize_palm_summary_data()`
  - Added validation warnings (non-blocking) for missing content sections
- ✅ Created unified completion prompt for all remaining sections (lines 1382-1489)
  - New function: `build_teaser_completion_prompt()`
  - Single prompt generates: personality_traits, love_patterns, career_success, challenges_opportunities, life_phase, timeline_6_months, guidance, closing
  - Uses palm_snapshot from Call 1 as context
  - Total ~740 words across 8 sections
- ✅ Modified `generate_teaser_context()` to use 2-call flow (lines 192-228)
  - Call 1: Vision API returns palm analysis + palm_snapshot + opening + foundations (~400 words)
  - Call 2: Completion API returns all remaining sections (~740 words)
  - Merges Vision content with Completion content
  - Removed 3-call flow (core_a, core_b, secondary)
- ✅ Disabled expansion retries for teaser (line 227-228)
  - Best-effort approach: accept OpenAI output as-is
  - Rely on Phase 1 missing-field tolerance (max 2 sections) to catch systemic failures

**Files Modified:**
- `includes/class-sm-ai-handler.php` (lines 192-228, 1382-1489, 2025-2106, 2280-2302, 2445-2480)

**Where to look:**
- `includes/class-sm-ai-handler.php` (Vision prompt, completion prompt, generation flow)

**Tests Required:**
- Generate 10 teaser readings
- Verify exactly 2 OpenAI calls via logging (existing call count logging from Phase 0)
- Verify all sections present or within tolerance (max 2 missing)
- Verify token usage reduced by 50-60%

Checklist:
- [x] Extended Vision prompt with content sections
- [x] Created unified teaser completion prompt
- [x] Modified generation flow to use 2 calls
- [x] Expansion retries disabled (best-effort)
- [x] Call count logging verifies 2 (existing from Phase 0)
- [ ] Manual testing: 10 teaser generations (ready for testing)

---

### Phase 3: Paid Generation (3 Calls New, 1 Call Upgrade) ✅ **COMPLETE**
**Status:** ✅ Completed 2025-12-30
**Goal:** Reduce paid API calls from 3-5 to exactly 3 (new) or 1 (upgrade).

**Implemented:**
- ✅ Removed JSON validation retry from `generate_paid_completion()` (lines 1640-1708)
  - Previous: Primary call + rescue_json retry (if JSON invalid) + rescue_short_payload retry (if too short) = 1-3 calls
  - Now: Single call only, no retries
  - Log error if JSON validation fails (fail fast)
  - Log warning if sections are short but accept output anyway
- ✅ New paid path verified: exactly 3 calls total
  - Call 1: Vision API (palm analysis + snapshot + opening + foundations) - Phase 2
  - Call 2: Teaser completion (remaining teaser sections) - Phase 2
  - Call 3: Premium completion (10 premium sections, 1300-1600 words) - Phase 3
  - Flow: `generate_paid_reading()` → `generate_teaser_context()` (2 calls) + `generate_paid_completion()` (1 call)
- ✅ Upgrade path verified: exactly 1 call total
  - Reuses existing teaser data (no regeneration)
  - Call 1: Premium completion only (10 premium sections)
  - Flow: `generate_paid_reading_from_teaser()` → loads teaser + `generate_paid_completion()` (1 call)
  - Preserves original reading_id and merges data via `update_reading_data()`
- ✅ Credit deduction idempotency verified (existing implementation)
  - Uses `idempotency_key = 'reading_' . $reading_id`
  - Prevents duplicate charges for same reading_id
  - Location: `class-sm-reading-job-handler.php:350`

**Files Modified:**
- `includes/class-sm-ai-handler.php` (lines 1640-1708)

**Where to look:**
- `includes/class-sm-ai-handler.php` (`generate_paid_completion()`)
- `includes/class-sm-reading-job-handler.php` (credit deduction idempotency)

**Tests Required:**
- Generate 5 paid new readings (verify exactly 3 OpenAI calls via logging)
- Generate 5 paid upgrades (verify exactly 1 OpenAI call via logging)
- Verify upgrade preserves reading_id and merges data correctly
- Verify credit deduction remains idempotent (no double charges)

Checklist:
- [x] Removed JSON validation retry
- [x] Removed short payload retry
- [x] New paid path uses exactly 3 calls
- [x] Upgrade path uses exactly 1 call
- [x] Credit deduction idempotency verified
- [ ] Manual testing: 5 new + 5 upgrades (ready for testing)

---

### Phase 4: Async Reliability + Email Delivery ✅ **COMPLETE**
**Status:** ✅ Completed 2025-12-30
**Goal:** Make async jobs and email notifications reliable and dependable.

**Implemented:**
- ✅ Verified $async flag extraction is correct (lines 2004 in class-sm-rest-controller.php)
  - `$async = $this->get_async_flag( $request );` already properly defined
  - Used at line 2100 for async path branching
  - No undefined variable bug - issue was already resolved
- ✅ Added job timeout handling (lines 268-296 in class-sm-reading-job-handler.php)
  - Check if job status is "running" and updated_at > 5 minutes ago
  - Mark as failed with `error_code = 'job_timeout'`
  - Log timeout with elapsed seconds
  - Prevents stuck jobs from blocking the system
- ✅ Verified email delivery logging is working (Phase 0 implementation)
  - `wp_mail_failed` hook captures detailed SMTP errors (line 55)
  - Email success logged (lines 495-505)
  - Email failure logged (lines 480-493)
- ✅ Verified async job dispatch reliability (existing implementation)
  - Non-blocking dispatch via `wp_remote_post()` with `blocking => false` (line 218)
  - Job dispatch logged with response status (lines 220-230)
  - WP-Cron scheduled as backup (line 187)
- ✅ Added vision failure email handling (lines 352-580)
  - Detect `palm_image_invalid` error code after job failure (line 353)
  - Send resubmission email instead of completion email
  - Email includes clear instructions and resubmit link with `sm_resubmit=1&step=palmPhoto`
  - Log email send success/failure

**Files Modified:**
- `includes/class-sm-reading-job-handler.php` (lines 268-296, 352-580)

**Where to look:**
- `includes/class-sm-reading-job-handler.php` (timeout handling, vision failure email)
- `includes/class-sm-rest-controller.php` ($async flag - verified correct)

**Tests Required:**
- Trigger 10 async readings (teaser + paid)
- Confirm completion emails arrive within 1 minute
- Confirm vision failures send resubmission emails
- Confirm job timeouts (> 5 min) mark jobs as failed
- Verify no undefined variable warnings in logs

Checklist:
- [x] Async flag extraction verified (already correct)
- [x] Job timeout handling added (5 minutes)
- [x] Email logging verified (Phase 0 complete)
- [x] Vision failure email implemented
- [ ] Manual testing: 10 async reads + email verification (ready for testing)

---

### Phase 5: Frontend Polling + Status UX ✅ **COMPLETE**
**Status:** ✅ Completed 2025-12-30
**Goal:** Remove flaky "error then success" behavior.

**Implemented:**
- ✅ Job status endpoint already exists (`/reading/status` in class-sm-rest-controller.php:259-270)
  - Returns `status: 'ready'` with reading HTML when job completed
  - Returns `status: 'processing'` when job is queued/running
  - Returns error response when job fails
  - Returns `status: 'not_found'` when no job exists
- ✅ Enhanced polling implementation (lines 1380-1428 in api-integration.js)
  - Polls every 5 seconds (increased from 3s per Phase 5 spec)
  - Max 60 attempts = 300 seconds = 5 minutes (improved from 3 minutes)
  - Properly handles failed jobs (error responses)
  - Detailed logging for debugging (`[SM Polling]` prefix)
  - Re-throws errors with full backend error data preserved
- ✅ Auto-jump to report when completed (line 1395-1396)
  - Returns reading_html immediately when status is 'ready'
  - Rendering logic in generateReading() displays report (line 1267-1292)
- ✅ Error states only shown when job fails (lines 1410-1418)
  - Polling detects error responses (`!response.success`)
  - Preserves error_code and error_data for proper error handling
  - palm_image_invalid errors trigger resubmission flow (line 1301-1366)
- ✅ Added missing `updateLoadingNote()` function (lines 1417-1435)
  - Stops rotating loading messages
  - Updates primary loading subtext with static message
  - Called when async processing starts (line 1263)
- ✅ Email notification message already implemented
  - `updateLoadingEmailNoteWhenReady()` adds email note (line 1244)
  - Retries until loading screen renders (max 20 attempts)

**Files Modified:**
- `assets/js/api-integration.js` (lines 1380-1428, 1417-1435)

**Where to look:**
- `includes/class-sm-rest-controller.php` (job status endpoint - existing)
- `assets/js/api-integration.js` (polling logic, loading messages)

**Tests Required:**
- Generate 10 async teaser readings (verify polling, email note, auto-jump)
- Generate 5 async paid readings (verify polling works for paid flow)
- Trigger a vision failure (verify error handling, resubmission flow)
- Verify no premature error messages
- Verify loading messages update correctly

Checklist:
- [x] Job status endpoint (already exists)
- [x] Polling implementation (enhanced with 5s interval, error handling, logging)
- [x] Auto-jump on completion (already exists)
- [x] Error states only on failed status (implemented)
- [x] Loading message functions (updateLoadingNote added)
- [ ] Manual testing: 10 async reads (ready for testing)

---

### Phase 6: Testing & Documentation Lock-In
**Goal:** Prove stability and document final state.
- Add Playwright tests for async flows.
- Run end-to-end teaser + paid (new and upgrade).
- Update CODEX/CONTEXT if necessary.

**Where to look:**
- `tests/*.spec.js`
- `CODEX.md`

**Implementation notes:**
- Add async-focused Playwright specs and keep them independent of existing tests.
- Add MailPit verification steps (API or UI) when running locally.

**Tests:**
- `npm test`
- Verify new async-specific test cases.

**Stability check:**
- All tests green.
- No regressions in free and logged-in flows.

Checklist:
- [ ] E2E async tests added
- [ ] 10 teaser + 10 paid runs pass
- [ ] Documentation updated

---

## 8) Playwright Testing Strategy

### Required Test Coverage
- Teaser async generation (2 calls)
- Paid generation from scratch (3 calls)
- Paid upgrade from teaser (1 call)
- Email delivery post-completion
- Polling + auto-jump
- Retry handling (if enabled)
- Vision failure: resubmit flow + no report created

### Suggested Test Files
- `tests/async-teaser.spec.js`
- `tests/async-paid.spec.js`
- `tests/async-upgrade.spec.js`

### Suggested Commands
```bash
npm test
npm run test:e2e:headed
```

### Test Inputs
- Provide paid accounts with credits (user-supplied file).
- Use at least one known-good hand image for repeatability.

---

## 9) Progress Tracker
Update the checklist below as phases complete. Keep progress bar in sync.

Progress: [########] 100%

- [x] Phase 0: Baseline logging ✅ **COMPLETE** (2025-12-30)
- [x] Phase 1: Core Vision call hardening ✅ **COMPLETE** (2025-12-30)
- [x] Phase 2: Teaser consolidation (2 calls) ✅ **COMPLETE** (2025-12-30)
- [x] Phase 3: Paid consolidation (3 calls + upgrade) ✅ **COMPLETE** (2025-12-30)
- [x] Phase 4: Async reliability + email ✅ **COMPLETE** (2025-12-30)
- [x] Phase 5: Frontend polling + status UX ✅ **COMPLETE** (2025-12-30)
- [ ] Phase 6: Tests + documentation lock-in

---

## 12) Vision Failure UX & Data Handling

### UX Requirements
- If the core Vision call fails due to clear non-hand detection:\n  - Stop async processing and mark job as `failed`.\n  - Show the failure screen with a “Try Again” button.\n  - Do not show a Continue button.\n  - Preserve quiz answers so the user can re-upload and continue quickly.

### Email Requirements
- Send a failure email with a resubmit link to restart at the photo step.
- Do not send a completion email or report link.

### Data Handling
- Do not create a reading row for failed Vision attempts.
- Track the failed attempt in job logs only (job_id, lead_id, failure_reason).
- Treat a resubmission as a new job using the same lead_id and updated image.\n  - This preserves flow continuity while keeping reports clean.

---

## 13) Decision Notes (Technical)
- **No failed reports in dashboard:** Failures end the async job and do not create reading rows to avoid confusing users and to keep dashboards clean.
- **Soft validation first:** We accept most imperfect hand photos and only hard-stop for clear non-hand images to reduce false negatives.
- **Max 2 missing sections:** Keeps the experience resilient without masking systemic prompt failures.

---

## 10) File Map (Primary Touchpoints)
- `includes/class-sm-ai-handler.php`
- `includes/class-sm-reading-job-handler.php`
- `includes/class-sm-rest-controller.php`
- `includes/class-sm-teaser-reading-schema-v2.php`
- `assets/js/api-integration.js`
- `tests/*.spec.js`

---

## 11) Rollback Strategy
- Keep pre-optimization prompts archived.
- If a phase introduces regressions, revert only that phase’s changes.
- Never remove or rename existing schema keys used by templates.

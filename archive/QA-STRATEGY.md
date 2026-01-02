# QA Strategy - Async Optimization

**Project:** Mystic Palm Reading Plugin (SoulMirror)
**Status:** Draft for implementation
**Last Updated:** 2025-12-30

This document defines automated and manual QA coverage for async optimization. It is written for a new developer who needs to pick up any phase and implement tests confidently.

---

## 1) Scope
This QA strategy covers:
- Async job stability and polling
- Email delivery (MailPit in Flywheel)
- Core Vision call reliability (valid, blurry, non-hand)
- Teaser and paid report flows
- Paid upgrade flow
- Failure handling and resubmission UX

---

## 2) Test Environments

### Local (Flywheel)
- **Base URL:** `https://sm-palm-reading.local/`
- **MailPit UI:** (Flywheel tool)
- **MailPit API (optional):** `http://localhost:8025` (default; override via env var)
- **DevMode:** Required for test helper endpoints
- **Live OpenAI runs:** Set DevMode to **Mock MailerLite only** (OpenAI live), or disable DevMode entirely if you do not need test helper endpoints.

### Staging / Production (optional)
- Use real paid accounts with credits.
- Email delivery validated via provider inbox (Gmail/Outlook).

---

## 3) Required Environment Variables
- `E2E_BASE_URL` - Base URL for Playwright tests (default uses local)
- `MAILPIT_BASE_URL` - MailPit API base (e.g., `http://localhost:8025`)
- `E2E_PAID_EMAIL` / `E2E_PAID_PASSWORD` (optional, if using real paid accounts)
- `E2E_LIVE_OPENAI=1` - Enable live OpenAI runs (expect slower tests)
- `E2E_MAX_REPORT_SECONDS` - Optional max duration (seconds) for report generation
- `E2E_MAX_EMAIL_SECONDS` - Optional max duration (seconds) for completion email arrival

---

## 4) Automated Test Suite (Playwright)

### Files
- `tests/async-optimization.spec.js` (new)
- Existing suites remain: `tests/e2e-full-flow.spec.js`, `tests/palm-reading-flow.spec.js`

### Coverage Matrix

**A) Async Teaser Generation**
- **Goal:** Teaser completes async, polling resolves to report, email arrives.
- **Flow:** Welcome -> Lead capture -> OTP -> Photo -> Quiz -> Loading -> Report.
- **Assertions:**
  - Job transitions to `completed`.
  - Report renders (DOM container present).
  - Completion email exists in MailPit (if configured).

**Implementation details:**
- Use `soulmirror-test/v1/get-otp` to retrieve OTP.
- Upload `assets/test-palm.png` via `#photo-upload-input`.
- Wait for report container `#palm-reading-result`.
- Use MailPit API `/api/v1/messages` to confirm subject and recipient.

**B) Paid New Generation**
- **Goal:** Logged-in user can generate paid report async.
- **Flow:** Mock login -> Dashboard -> New Reading -> Photo -> Quiz -> Loading -> Report.
- **Assertions:**
  - Dashboard visible with `#generate-new-reading-btn`.
  - Report renders without teaser copy.

**Implementation details:**
- Use `soulmirror-test/v1/mock-login` to set JWT in DevMode.
- Click `#generate-new-reading-btn`.
- Reuse photo + quiz steps from teaser test.

**C) Paid Upgrade from Teaser**
- **Goal:** Existing teaser upgrades to paid.
- **Flow:** Seed teaser -> Open report -> Click Unlock Full Report -> Loading -> Full report.
- **Assertions:**
  - Unlock button disappears after upgrade.
  - Full report content visible.

**Implementation details:**
- Use `soulmirror-test/v1/seed-reading` with `account_id`.
- Open report URL with `sm_report=1&lead_id=...`.
- Click button with text `Unlock Full Report`.

**D) Vision Failure Resubmission**
- **Goal:** Clear non-hand triggers failure, no report, resubmit flow.
- **Flow:** Upload non-hand image -> Submit -> Failure state -> Try Again.
- **Assertions:**
  - No report row created.
  - Failure UI shows “Try Again.”
  - Completion email not sent; resubmit email sent.

**Implementation details:**
- Use a non-hand image fixture (add one in `assets/` or `tests/fixtures/`).
- Assert presence of failure state and absence of report container.

---

## 5) MailPit Verification

### Automated (API)
- If `MAILPIT_BASE_URL` is set, Playwright tests query:
  - `GET /api/v1/messages`
  - Filter by `To` and subject line.

### Manual (UI)
- Open Flywheel MailPit tool.
- Confirm OTP and completion/failure emails arrive.

---

## 6) Manual Smoke Checklist
- Teaser completes in under 45 seconds (5 runs)
- Paid completes in under 60 seconds (5 runs)
- Async polling resolves to report without false error
- Clear non-hand image shows failure + resubmit

---

## 7) Test Tiers (Run Commands)

**Smoke (fast, current changes):**
```bash
E2E_BASE_URL=https://sm-palm-reading.local npm run test:e2e:headed
```

**Focused (async optimization):**
```bash
E2E_BASE_URL=https://sm-palm-reading.local npx playwright test tests/async-optimization.spec.js
```

**Full regression:**
```bash
E2E_BASE_URL=https://sm-palm-reading.local npm test
```

---

## 8) Logging Expectations (Browser + Backend)

**Browser:**
- Playwright captures console output for `[SM]` logs and network failures.
- Always review `test-results/html-report` and `test-results/test-results.json`.

**Backend:**
- Tail `wp-content/debug.log` and `/wp-content/uploads/sm-logs/debug.log`.
- Enable OpenAI trace logs when debugging prompts:
  - Add `define('SM_OPENAI_TRACE', true);` to `wp-config.php`.
- Confirm job lifecycle logs: `READING_JOB`, `OpenAI API call completed`, `Completion email sent/failed`.

---

## 9) Async Optimization Plan Coverage

**Phases 1–3 (core prompts + call reduction):**
- Verified by `tests/async-optimization.spec.js` and OpenAI call count logs.

**Phase 4–5 (async + polling + email):**
- Covered by async suite (teaser, paid new, paid upgrade, vision failure).
- Use MailPit for completion email verification when configured.

**Phase 6 (tests + documentation lock-in):**
- Run full regression suite and review the HTML report before manual testing.
- Completion email links to report

---

## 7) Test Execution Commands
```bash
npm test
npm run test:e2e:headed
```

---

## 8) Decision Notes (Technical)
- **Use DevMode for automation:** It provides OTP and mock login endpoints.
- **MailPit is the source of truth for emails on local:** Tests verify delivery there.
- **Upgrade test uses seed data:** Keeps test deterministic and fast.

---

## 9) Implementation Notes for New Developers

### Where to Edit Test Code
- `tests/async-optimization.spec.js` is the primary file for new async scenarios.
- Use helper endpoints in `includes/class-sm-test-helpers.php` when DevMode is enabled.

### Where to Verify Backend Behavior
- `includes/class-sm-reading-job-handler.php` for job execution + email.
- `includes/class-sm-rest-controller.php` for job status endpoints.
- `includes/class-sm-ai-handler.php` for Vision call, prompt structure, and call counts.

### How to Confirm Stability per Phase
- Phase 1: Run async teaser test + non-hand test.
- Phase 2: Run teaser test and confirm 2 OpenAI calls in logs.
- Phase 3: Run paid new + paid upgrade tests.
- Phase 4: Run email checks via MailPit API/UI.
- Phase 5: Run async tests and verify polling-based completion.

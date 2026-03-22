# Aura Reading Stabilization Plan

Date: 2026-03-22
Status: Active

## Purpose

This document defines the smart regression pack for Aura Reading. Run these tests before every release or after any change to auth, OTP, refresh, image, or magic-link flows.

## Smart Regression Pack

| ID | File | Description | Priority |
|----|------|-------------|----------|
| HP-001 | 01-critical-happy-paths.spec.js | Teaser happy path end-to-end | P0 |
| HP-002 | 01-critical-happy-paths.spec.js | Paid happy path (if enabled locally) | P1 |
| AUTH-004 | 03-otp-flow.spec.js | OTP verify happy path | P0 |
| AUTH-012 | 03-otp-flow.spec.js | Email switch after login redirect uses new lead for MailerLite sync | P0 |
| REFRESH-005 | 02-refresh-stability.spec.js | Refresh recovery after OTP | P0 |
| MAGIC-002 | 05-magic-links.spec.js | Paid magic link access | P0 |
| IMG-007 | 07-image-validation.spec.js | Invalid image lockout guard | P0 |

## Run Command

```bash
PLAYWRIGHT_USE_SYSTEM_CHROME=1 \
  MAILPIT_URL=http://localhost:10005 \
  E2E_OTP_SOURCE=mailpit \
  E2E_DEBUG=1 \
  E2E_REPORT_WAIT_MS=180000 \
  E2E_BASE_URL=https://sm-aura-reading.local/ \
  npx playwright test \
    tests/specs/suites/01-critical-happy-paths.spec.js \
    tests/specs/suites/02-refresh-stability.spec.js \
    tests/specs/suites/03-otp-flow.spec.js \
    tests/specs/suites/05-magic-links.spec.js \
    tests/specs/suites/07-image-validation.spec.js \
    -g "HP-001|AUTH-004|AUTH-012|REFRESH-005|MAGIC-002|IMG-007" \
    --workers=1 \
    --reporter=list
```

## Run AUTH-012 alone

```bash
PLAYWRIGHT_USE_SYSTEM_CHROME=1 \
  MAILPIT_URL=http://localhost:10005 \
  E2E_OTP_SOURCE=mailpit \
  E2E_DEBUG=1 \
  E2E_REPORT_WAIT_MS=180000 \
  E2E_BASE_URL=https://sm-aura-reading.local/ \
  npx playwright test tests/specs/suites/03-otp-flow.spec.js \
    -g "AUTH-004|AUTH-012" \
    --workers=1 \
    --reporter=list
```

## What Each Test Guards

### HP-001 — Teaser happy path
Guards the core free-reading flow from welcome email through teaser result.
Catches regressions in lead creation, OTP, photo upload, quiz, and reading generation.

### AUTH-004 — OTP verify happy path
Guards that a valid OTP code completes verification and advances the flow.
Catches regressions in OTP generation, email delivery, and code verification.

### AUTH-012 — Email switch after login redirect
Guards that when a user enters an account-linked email (redirected to login), then returns and enters a different email, the new identity is used cleanly for lead creation and MailerLite sync.
This is the primary guard for stale identity carryover after login redirect.

### REFRESH-005 — Refresh recovery after OTP
Guards that a page refresh after OTP verification restores the user to the correct step without losing their session.
Catches state persistence and restore regressions.

### MAGIC-002 — Paid magic link access
Guards that a paid magic link correctly restores the paid reading without requiring re-authentication.
Catches regressions in token validation and reading render.

### IMG-007 — Invalid image lockout guard
Guards that submitting an invalid or undetectable aura photo triggers the correct lockout/retry UI and does not silently proceed.

## Serial Execution Requirement

Run the smart pack with `--workers=1`. These tests are stateful and share backend database and session infrastructure. Parallel execution causes flaky results.

## Parity Baseline

This stabilization pack was defined against the Palm Reading baseline as of 2026-03-22.

The UX hardening ported in this release covers:
- Welcome email identity reset (stale state after login redirect)
- Dynamic question single-flight fetch prevention
- REST return URL hygiene in auth handler
- Duplicate-lead account detection in reading check endpoint

See `AURA_UX_FIX_PLAN_2026-03-22.md` for the full gap analysis and porting rationale.

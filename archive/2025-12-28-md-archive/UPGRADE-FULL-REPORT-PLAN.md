# Upgrade to Full Report Plan

Goal: when a user buys credits after a teaser, allow premium cards to trigger full report generation using existing saved data (no image re-upload).

## Phase 1: Upgrade Trigger
- Add a new backend endpoint (or extend the existing unlock endpoint) to detect premium card clicks.
- Validate: teaser reading exists, user has credits or purchase, reading belongs to lead/account.

## Phase 2: Paid Completion Using Existing Data
- Reuse saved teaser JSON + palm summary (already stored) to generate Phase 2 paid completion.
- Persist paid reading and mark `has_full_access = true`.

## Phase 3: UI + Redirect
- On premium card click, call upgrade endpoint.
- If success, render full report (or refresh to paid template) and unlock all premium sections.

## Edge Cases
- If paid completion fails, return a retryable error and log in `debug.log`.
- If user has credits but no account session, request login/SSO before upgrade.

## Testing Checklist
- Teaser → buy credits → return → click premium card → paid report renders.
- Refresh after upgrade keeps paid report and unlocked sections.
- Premium unlock blocked when no credits.

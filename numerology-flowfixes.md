# Numerology Flow Parity Plan (Aura Flow Alignment)

## Goal
Align the numerology flow behavior with the stabilized Aura flow:
- Refresh-safe navigation at every step
- Clean “Begin Journey” restart for paid users (no bounce, no stale data)
- Stable teaser magic-link/report refresh access
- Strict isolation between guest and paid contexts
- Preserve numerology-specific requirement: paid flow always starts at the **form page** (lead capture)

## Sources of Truth
- Aura flow updates in `sm-aura-reading` (front-end state + URL flags + flow cookie scoping).
- Numerology codebase: `assets/js/script.js`, `assets/js/api-integration.js`, `includes/class-sm-flow-session.php`, `mystic-numerology-reading.php`.

---

## 1) Frontend State Isolation (Context-Scoped Storage)
**Issue:** Numerology currently uses raw `sessionStorage`/`localStorage` keys shared across guest/paid/magic contexts, causing cross-flow contamination.

**Plan:**
- Add a `smStorage` helper (same pattern as Aura) to scope keys by context:
  - `auth` when logged-in or `sm_flow_auth=1`
  - `magic` when `sm_magic=1`
  - `guest` otherwise
- Replace direct `sessionStorage`/`localStorage` usage in:
  - `assets/js/script.js`
  - `assets/js/api-integration.js`
- Scope key sets:
  - `sm_flow_step_id`, `sm_reading_loaded`, `sm_reading_lead_id`, `sm_reading_token`, `sm_reading_type`, `sm_existing_reading_id`, `sm_email`, `sm_loop_guard`, `sm_paywall_*`, `sm_lead_cache`, `sm_start_new_pending`.

**Files:**
- `assets/js/script.js` (state persistence + flow restore)
- `assets/js/api-integration.js` (API cache, report refresh, magic link)

---

## 2) URL Flow Flag for Refresh-Safe Navigation
**Issue:** Logged-in refreshes often return to dashboard instead of resuming the flow.

**Plan:**
- Introduce `sm_flow=1` URL flag when a flow is in progress (same as Aura).
- Add `sm_flow_auth=1` when the user is logged in.
- On flow entry/step change: ensure URL includes `sm_flow=1`.
- On report render or dashboard: remove `sm_flow` (avoid locking users into flow).

**Files:**
- `assets/js/api-integration.js`:
  - Add helpers `markFlowUrl()` and `markReportUrl()` as in Aura.
  - Set `sm_flow` when `goToNextStep` or `renderStep` is used.
- `assets/js/script.js`:
  - Respect `sm_flow` on restore/boot.

---

## 3) Begin Journey / Start-New Flow (Paid)
**Issue:** First click sometimes bounces back to dashboard; stale data persists.

**Plan:**
- Mirror Aura’s start-new pending guard:
  - Store `sm_start_new_pending=1` before redirecting from dashboard.
  - If page reloads mid-redirect, retry with `start_new=1&sm_flow=1&sm_flow_auth=1`.
- Update start-new bootstrap to always land on **leadCapture** (numerology form), not camera.
- Clear prior flow cache on start_new:
  - `sm_reading_*`, `sm_email`, `sm_flow_step_id`, `sm_dynamic_questions`, etc.

**Files:**
- `assets/js/api-integration.js`:
  - `bootstrapStartNewFlow()` should set step to `leadCapture`.
  - Add pending guard and `sm_flow` handling (mirror Aura).
- `templates/dashboard.php` + API start-new handler:
  - Ensure redirect URL includes `start_new=1&sm_flow=1&sm_flow_auth=1`.

---

## 4) Local State Persistence (Refresh on Form/Quiz)
**Issue:** localStorage restore is disabled, dynamic questions are not persisted, refresh can invalidate state.

**Plan:**
- Re-enable localStorage persistence with context scoping:
  - Save `appState.userData` (name, email, DOB, time, location, GDPR)
  - Save `appState.quizResponses`
  - Save dynamic questions + demographics (see below)
- Restore on load when `sm_flow=1` and no report is loaded.
- Clear on completion or start-new reset.

**Files:**
- `assets/js/script.js`:
  - Enable `restoreStateFromLocalStorage()` guarded by `sm_flow`.
  - Replace `localStorage` usage with `smStorage`.
- `assets/js/api-integration.js`:
  - Persist `dynamicQuestions` and `demographics` in storage.
  - Restore them during bootstrap.

---

## 5) Magic Link + Report Refresh Parity
**Issue:** Guest magic links can conflict with logged-in context or stale storage.

**Plan:**
- When `sm_magic=1`, force storage context to `magic`.
- Ensure report refresh clears stale reading state on failure.
- If logged-in user opens someone else’s teaser link, redirect to dashboard (or show a neutral error) instead of rendering an empty shell.

**Files:**
- `assets/js/api-integration.js` (magic link verify + report refresh)
- `assets/js/script.js` (existing reading lookup + render handling)
- `mystic-numerology-reading.php` (login redirect bypass rules)

---

## 6) Backend Flow Cookie Isolation
**Issue:** One flow cookie is shared across guest/paid/magic flows.

**Plan:**
- Adopt context-specific cookie names in `SM_Flow_Session`:
  - `sm_flow_id_guest`, `sm_flow_id_auth`, `sm_flow_id_magic`
- Determine context via:
  - Logged-in state
  - `sm_magic` flag in URL
  - `sm_flow_auth=1` flag (frontend)

**Files:**
- `includes/class-sm-flow-session.php`
- `includes/class-sm-rest-controller.php` (flow endpoints should respect context)

---

## 7) Shortcode Container Rendering
**Issue:** Logged-in refresh can return to dashboard even when in flow.

**Plan:**
- Mirror Aura: if `sm_flow=1` present, always render `templates/container.php`, even if logged in.
- Keep `sm_reports=1` behavior unchanged.

**Files:**
- `mystic-numerology-reading.php` (`sm_render_shortcode`)

---

## 8) Validation & Test Checklist
**Guest (Teaser):**
- Refresh on lead form, OTP, quiz, result loading, report.
- Magic link opens report for 48h without login.

**Paid (Logged in):**
- Dashboard → Begin Journey always opens leadCapture on first click.
- Refresh on form and quiz resumes correctly.
- Second reading starts clean (no prior answers).

**Mixed:**
- Logged-in user opens someone else’s teaser link → safe redirect.

---

## Recommended Sequence (Minimal Risk)
1. Add `smStorage` + context-scoped keys (JS only).
2. Add `sm_flow` URL flag handling (JS + shortcode render).
3. Apply start-new pending guard + leadCapture targeting.
4. Re-enable localStorage restore + dynamic question persistence.
5. Update flow session cookies (PHP).
6. Run refresh + magic link validation tests.


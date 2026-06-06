# Aura Reading UX Fix Plan

Date: 2026-03-22
Source baseline: latest Palm Reading implementation as of 2026-03-22
Target: `/Users/douglasribeiro/Local Sites/sm-aura-reading/app/public/wp-content/plugins/sm-aura-reading`

## Purpose

Bring Aura Reading back to the same user experience standard as Palm Reading without reinventing anything.

This is not a redesign plan. It is a parity and stabilization plan focused on:
- login and account-routing behavior
- refresh and recovery behavior
- welcome-to-lead-capture state integrity
- OTP and existing-reading handling
- dynamic question loading behavior
- regression automation to keep Aura from drifting again

## Executive Summary

Aura already contains a large amount of Palm parity work from February 2026. However, the current Aura parity docs are now stale relative to Palm.

Important: Palm received additional UX hardening on 2026-03-22 that Aura has not absorbed. The current Aura docs say parity is complete, but that is no longer true against the latest Palm source.

The highest-value work is to port the latest Palm UX hardening from these files:
- `assets/js/script.js`
- `assets/js/api-integration.js`
- `includes/class-sm-auth-handler.php`
- `includes/class-sm-rest-controller.php`
- `tests/specs/suites/03-otp-flow.spec.js`

## What I Reviewed

I compared Aura against the current Palm implementation, with emphasis on the user-facing and recovery-heavy paths that changed recently:
- welcome email capture and redirect-to-login behavior
- teaser state reset when a user switches identity mid-flow
- dynamic question fetch timing and duplicate-request prevention
- auth login URL generation from REST contexts
- existing-reading and account detection when duplicate leads exist for the same email
- OTP and MailerLite regression coverage

I also reviewed Aura’s existing parity artifacts:
- `PALM_PARITY_EXECUTION_PLAN.md`
- `PARITY_HANDOFF_REPORT.md`
- `compare_palm_reading_versus_aura_reading.md`

Those documents are useful historical context, but they should not be treated as current truth for UX parity with Palm after 2026-03-22.

## Current State Assessment

### What Aura already appears to have

Aura already has most of the February parity work:
- callback path and account-service wiring are Aura-specific
- magic-link and refresh work appears largely ported
- Playwright parity suite structure exists
- scoped flow/state and many refresh fixes are already present
- the repo already includes parity documentation and changelog entries for earlier Palm alignment

### What Aura is still behind on

These are the meaningful current gaps versus Palm.

### Gap 1: Welcome email changes do not fully reset teaser identity state

Palm now explicitly resets teaser-entry state when the welcome email changes after a redirect-to-login or any stale in-progress flow reuse.

Why this matters:
- a user can start with one email, get redirected toward login, come back, enter a different email, and Aura may still carry stale lead/session/app state into the new attempt
- this can cause wrong lead association, wrong MailerLite sync target, or stale quiz/image state being reused for the wrong person

Palm-side hardening added:
- `resetTeaserEntryState()` in `assets/js/api-integration.js`
- `resetFlowForWelcomeEmailChange()` in `assets/js/script.js`
- explicit comparison between previous `sm_email` and newly entered welcome email
- clearing of client session, local flow state, dynamic question state, and API state

Aura should port this exactly, adapted only for Aura naming.

Files:
- `assets/js/script.js`
- `assets/js/api-integration.js`

Priority: P0

### Gap 2: Dynamic question fetching in Aura is less hardened than Palm

Palm now uses a single-flight promise pattern for dynamic question fetches and refreshes the active quiz step once personalized questions arrive.

Why this matters:
- prevents duplicate requests during step transitions
- avoids race conditions where quiz UI starts with static content and never refreshes to the personalized set
- improves perceived responsiveness during the photo-to-quiz transition

Palm-side hardening added:
- `dynamicQuestionsPromise`
- `ensureDynamicQuestionsReady(ageRange, gender)`
- `refreshActiveQuizStep()`
- preloading personalized questions before advancing from the photo step

Aura’s current `api-integration.js` is behind this version.

Files:
- `assets/js/api-integration.js`

Priority: P1

### Gap 3: Auth login URL generation is missing REST-context return URL protection

Palm now prevents REST API URLs from being used as login return URLs when `get_login_url()` is invoked from a REST handler context.

Why this matters:
- without this, a login redirect can capture `/wp-json/...` as the return target
- after auth, users can land in the wrong place or hit confusing dead-end redirects
- this is exactly the kind of subtle auth UX failure that is hard to diagnose and easy to reintroduce

Palm-side hardening added:
- REST prefix guard in `includes/class-sm-auth-handler.php`
- fallback to `home_url('/')` when the candidate return URL is a REST endpoint

Aura’s auth handler is missing this protection.

Files:
- `includes/class-sm-auth-handler.php`

Priority: P0

### Gap 4: Existing-reading and account detection is weaker when duplicate leads exist

Palm now checks for account linkage and reading history across all leads for an email, not only the most recent lead.

Why this matters:
- duplicate leads are normal over time in these flows
- if only the latest lead is checked, the system can misclassify a returning user as new
- that leads to wrong CTA behavior, wrong login prompts, duplicate free-flow attempts, and stale lead selection

Palm-side hardening added:
- `email_has_linked_account($email)` in `includes/class-sm-rest-controller.php`
- broader reading existence check using `SM_AI_Handler::reading_exists_for_email($email)`
- login URL generation that explicitly passes `home_url('/')`
- better redirect semantics for `reading_exists`

Aura still lags this March hardening.

Files:
- `includes/class-sm-rest-controller.php`

Priority: P0

### Gap 5: Aura automation coverage is missing the newest Palm regression scenarios

Palm gained new OTP/account-switch regressions on 2026-03-22 that directly protect the UX issues above.

Why this matters:
- these are not theoretical edge cases
- they cover exactly the stale-state and wrong-email carryover problems that damage conversion and trust
- without them, Aura will drift again even if code is ported once

Palm-side test additions include:
- stronger coverage for re-entry after redirect/login behavior
- `AUTH-012` for switching email after login redirect and verifying the new lead is used for MailerLite sync

Aura’s `tests/specs/suites/03-otp-flow.spec.js` does not yet include the full latest Palm coverage.

Files:
- `tests/specs/suites/03-otp-flow.spec.js`

Priority: P0

## Recommended Implementation Strategy

## Rule 1: Do not re-run the old February parity project

Do not start from the older parity tracker as if the repo were still behind everywhere.

Instead:
- treat Palm on 2026-03-22 as the source of truth
- port only the delta that Aura still lacks
- preserve Aura branding, selectors, reading types, and paths
- do not reintroduce Palm names like `palm-reading`, `palm_teaser`, or Palm-specific copy

## Rule 2: Port behavior, not just snippets

For each target file:
- compare against latest Palm
- port the mechanics exactly
- adapt only branding and Aura-specific IDs/selectors where unavoidable

## Rule 3: Fix code and tests together

Every UX hardening port must land with its Playwright coverage in the same effort.

## Work Plan

## Phase 1: Critical UX parity from latest Palm

### Task 1. Welcome email identity reset parity

Port from Palm into Aura:
- centralized teaser reset method in `assets/js/api-integration.js`
- welcome-step reset hook in `assets/js/script.js`
- clearing of stale API state, session state, local saved state, dynamic questions, image state, and quiz state
- immediate in-memory update of `appState.userData.email`

Acceptance criteria:
- user enters email A
- app redirects toward login because account/history exists
- user comes back and enters email B
- lead/create uses email B, not email A
- MailerLite sync uses lead for email B
- no stale image, quiz, or verification state leaks into the new journey

### Task 2. Dynamic question load hardening parity

Port from Palm into Aura:
- single-flight `dynamicQuestionsPromise`
- `ensureDynamicQuestionsReady()`
- `refreshActiveQuizStep()` after question hydration
- prefetch/wait behavior before advancing from the image step

Acceptance criteria:
- personalized questions are fetched once per needed demographic set
- no duplicate fetch storms during fast navigation
- active quiz screen reflects personalized questions once loaded
- photo-to-quiz transition remains responsive

### Task 3. Login return URL hygiene parity

Port from Palm into Aura:
- REST endpoint return URL guard in `includes/class-sm-auth-handler.php`

Acceptance criteria:
- when login URL is generated from REST-driven flows, return target never becomes `/wp-json/...`
- after login, user returns to a valid page-level destination

### Task 4. Existing-reading/account detection parity

Port from Palm into Aura:
- `email_has_linked_account($email)`
- all-leads reading detection behavior
- explicit safe `get_login_url(home_url('/'))` usage
- latest `reading_exists` redirect semantics

Acceptance criteria:
- if any lead for an email is account-linked, Aura routes user toward login
- if any prior reading exists for an email, Aura encourages login instead of treating the user as brand new
- duplicate leads no longer produce misleading free-flow continuation

## Phase 2: Documentation truth repair

Aura’s current parity docs overstate the current situation.

Update or supersede:
- `PALM_PARITY_EXECUTION_PLAN.md`
- `PARITY_HANDOFF_REPORT.md`

Required change:
- make it explicit that February parity was achieved only against the Palm baseline at that time
- add a new section or linked follow-up plan for the 2026-03-22 Palm UX delta

Acceptance criteria:
- engineers no longer assume Aura is fully current with latest Palm UX
- active source-of-truth doc clearly distinguishes historical parity from current parity

## Phase 3: Automation and regression safety net

## Tests to port or add

### 1. Port the latest Palm OTP regression additions

Target file:
- `tests/specs/suites/03-otp-flow.spec.js`

Minimum required:
- port the expanded login-redirect return scenario
- port `AUTH-012` equivalent for Aura
- adapt only Aura branding, selectors, reading types, and base URL assumptions

### 2. Create a compact Aura stabilization pack

Add an Aura-specific active regression document, equivalent in spirit to Palm’s `stabilization_plan.md`.

Suggested file:
- `AURA_STABILIZATION_PLAN.md`

Recommended smart pack:
- `HP-001` teaser happy path
- `HP-002` paid happy path if enabled locally
- `AUTH-004` OTP verify happy path
- `AUTH-012` email switch after login redirect uses new lead
- `REFRESH-005` refresh recovery after OTP
- `MAGIC-002` paid magic link access
- `IMG-007` invalid image lockout guard

### 3. Require serial execution for stateful parity tests

For the critical stateful subset, standardize on `--workers=1` unless there is evidence parallel runs are stable.

## Suggested validation commands

From Aura repo root:

```bash
PLAYWRIGHT_USE_SYSTEM_CHROME=1 MAILPIT_URL=http://localhost:10005 E2E_OTP_SOURCE=mailpit E2E_DEBUG=1 E2E_REPORT_WAIT_MS=180000 E2E_BASE_URL=https://sm-aura-reading.local/ npx playwright test tests/specs/suites/03-otp-flow.spec.js -g "AUTH-004|AUTH-012" --workers=1 --reporter=list
```

```bash
PLAYWRIGHT_USE_SYSTEM_CHROME=1 MAILPIT_URL=http://localhost:10005 E2E_OTP_SOURCE=mailpit E2E_DEBUG=1 E2E_REPORT_WAIT_MS=180000 E2E_BASE_URL=https://sm-aura-reading.local/ npx playwright test tests/specs/suites/01-critical-happy-paths.spec.js tests/specs/suites/02-refresh-stability.spec.js tests/specs/suites/03-otp-flow.spec.js tests/specs/suites/05-magic-links.spec.js tests/specs/suites/07-image-validation.spec.js -g "HP-001|AUTH-004|AUTH-012|REFRESH-005|MAGIC-002|IMG-007" --workers=1 --reporter=list
```

## Definition of Done

This effort is done only when all of the following are true:
- Aura ports the latest Palm UX hardening from 2026-03-22 in the five target files
- Aura preserves Aura-specific branding and routes while matching Palm mechanics
- changing email after a redirect/login attempt creates and syncs the correct new lead
- login redirects never capture REST API URLs as return destinations
- duplicate-lead scenarios resolve to correct login/returning-user behavior
- the new Aura regression pack passes locally
- Aura documentation no longer incorrectly claims full current parity with latest Palm

## Recommended Execution Order

1. Port `assets/js/api-integration.js`
2. Port `assets/js/script.js`
3. Port `includes/class-sm-auth-handler.php`
4. Port `includes/class-sm-rest-controller.php`
5. Port `tests/specs/suites/03-otp-flow.spec.js`
6. Add `AURA_STABILIZATION_PLAN.md`
7. Run the smart regression pack
8. Update parity/status docs to reflect the new current state

## Final Recommendation

Do not treat Aura as a fresh parity project. Treat it as a mostly-aligned repo that missed the latest Palm UX hardening wave.

That means the right move is a focused delta port, not a broad rewrite.

The highest-risk problems are stale identity carryover, wrong lead selection after login redirect, and brittle login return routing. Fix those first, lock them down with Playwright, then refresh the documentation so the repo’s stated status matches reality.

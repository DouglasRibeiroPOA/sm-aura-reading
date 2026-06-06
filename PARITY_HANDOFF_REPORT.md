# Palm Parity Handoff Report

Date: 2026-02-12

## Scope Completed
- Ported Palm structured Playwright parity suites into Aura under `tests/specs/suites/`.
- Ported shared test helpers into Aura under `tests/helpers/`.
- Adapted defaults/selectors for Aura parity execution:
  - Base URL defaults switched to `https://sm-aura-reading.local/`
  - Reading types switched to `aura_teaser` / `aura_full`
  - Report container selector switched to `#aura-reading-result`
- Updated Playwright config default base URL to Aura.
- Added parity scripts in `package.json`:
  - `test:parity`
  - `test:parity:critical`

## Validation Run Summary (Aura Local)
Executed with:
- `PLAYWRIGHT_USE_SYSTEM_CHROME=1`
- `PLAYWRIGHT_HOST_PLATFORM_OVERRIDE=mac-arm64`
- `E2E_BASE_URL=https://sm-aura-reading.local/`
- `E2E_DEV_MODE=dev_all`

### Command 1
`npx playwright test tests/specs/suites/01-critical-happy-paths.spec.js -g 'HP-001|HP-004|HP-007' --reporter=list`

Results:
- `HP-001` passed
- `HP-004` passed
- `HP-007` skipped (MailPit-dependent in this environment)

### Command 2
`npx playwright test tests/specs/suites/02-refresh-stability.spec.js tests/specs/suites/03-otp-flow.spec.js -g 'REFRESH-013|AUTH-004' --reporter=list`

Results:
- `REFRESH-013` passed
- `AUTH-004` skipped in this run selection/environment

### Command 3 (stable serial subset)
`npx playwright test tests/specs/suites/01-critical-happy-paths.spec.js tests/specs/suites/02-refresh-stability.spec.js tests/specs/suites/03-otp-flow.spec.js -g 'HP-001|HP-004|REFRESH-013|AUTH-005' --workers=1 --reporter=list`

Results:
- `HP-001` passed
- `HP-004` passed
- `REFRESH-013` passed
- `AUTH-005` passed

Notes:
- Running critical parity tests serially (`--workers=1`) is more reliable in local Aura due to shared flow/session and async generation contention under multi-worker mode.

## Comparison Note
Behavior was validated using Palm baseline suite IDs and flow contracts (same scenario IDs and sequence). Aura matched expected baseline behavior for the executed scenarios above.

## Remaining Validation Gaps
- MailPit-dependent paths (magic links/email assertions) were not fully executed in this environment.
- Full suite execution (`tests/specs/suites`) still recommended when MailPit + full local services are available.
- Some paid/full-report scenarios can depend on real credit/backend state and may require dedicated fixtures or account setup.

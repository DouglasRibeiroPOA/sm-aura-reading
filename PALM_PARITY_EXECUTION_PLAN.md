# Aura vs Palm Parity Execution Plan

**Version:** 1.0.0  
**Last Updated:** 2026-02-12  
**Scope:** Make Aura Reading behavior match Palm Reading (strict parity, no compatibility window)

---

## Overall Progress

**Total Progress:** 13/34 tasks complete (38%)

```text
[▓▓▓▓▓▓▓░░░░░░░░░░░░░] 38%
```

### Phase Progress Snapshot

- Phase 1: Core Backend Reliability Parity - 8/8 (100%)
- Phase 2: Auth, Access, and Token Parity - 5/6 (83%)
- Phase 3: Frontend Flow and Storage Parity - 0/8 (0%)
- Phase 4: OTP and Credit Behavior Parity - 0/5 (0%)
- Phase 5: Remove Aura-Only Behavioral Divergence - 0/4 (0%)
- Phase 6: Test Parity and Release Readiness - 0/3 (0%)

---

## Locked Decisions (Already Approved)

1. Token architecture: adopt Palm `report_magic_token`; remove Aura `SM_Reading_Token` flow.
2. Report access gate: match Palm exactly.
3. `check_reading_status`: port Palm behavior exactly.
4. Frontend storage: match Palm session wrapper architecture exactly.
5. OTP policy: match Palm exactly.
6. Aura-only divergences: remove for strict parity.
7. Compatibility window: none (hard cut).

---

## Execution Order (Dependency-First)

## Phase 1: Core Backend Reliability Parity

**Progress:** 8/8 tasks (100%)  
```text
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
```

- [x] P1-T1 Port `build_start_new_target()` and sanitize stale query params (#1)
- [x] P1-T2 Add flow session reset in `handle_start_new_reading` (#11)
- [x] P1-T3 Port `resolve_lead_id()` and use in OTP + generation endpoints (#3)
- [x] P1-T4 Fix existing-reading handling to Palm semantics (`reading_exists`, login redirect, 2000ms) (#4)
- [x] P1-T5 Port OTP lead resolution fallback by email (#9)
- [x] P1-T6 Port job dispatch failure inspection + WP-Cron fallback (#28)
- [x] P1-T7 Port duplicate run guard in job execution path (#29)
- [x] P1-T8 Port Palm race-condition handling in `check_reading_status` (#10)

## Phase 2: Auth, Access, and Token Parity

**Progress:** 5/6 tasks (83%)
```text
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░]
```

- [x] P2-T1 Replace Aura reading-token responses with Palm report-token behavior (#5)
- [x] P2-T2 Remove `SM_Reading_Token` class usage across REST, jobs, and bootstrap (#25)
- [x] P2-T3 Port Palm report access gate logic into `mystic-aura-reading.php` (#24)
- [x] P2-T4 Port `normalize_return_url()` in auth handler (#21)
- [x] P2-T5 Remove client flow dependence on old token fields and align API payload keys (#5/#25)
- [ ] P2-T6 Remove no-longer-needed token compatibility code paths (hard cut) (#5/#24/#25)

## Phase 3: Frontend Flow and Storage Parity

**Progress:** 0/8 tasks (0%)  
```text
[░░░░░░░░░░░░░░░░░░░░]
```

- [ ] P3-T1 Port Palm start-new client behavior to use server `next_step_url` (#14)
- [ ] P3-T2 Remove Aura `START_NEW_PENDING_KEY` path and related flow workaround logic (#14/#15)
- [ ] P3-T3 Port Palm image fingerprint + upload cache + retry system (#13)
- [ ] P3-T4 Replace `smStorage` architecture with Palm storage wrappers in `api-integration.js` (#12)
- [ ] P3-T5 Replace `smStorage` architecture with Palm storage wrappers in `teaser-reading.js` (#12/#19)
- [ ] P3-T6 Validate `aura-reading-result` container behavior; add Palm safety wrapper if needed (#6/#17)
- [ ] P3-T7 Remove `bootstrapResumeAuthFlow` if no longer required after Palm URL/session parity (#15)
- [ ] P3-T8 Align frontend error mapping and redirect handling to Palm contracts (#1/#4/#14)

## Phase 4: OTP and Credit Behavior Parity

**Progress:** 0/5 tasks (0%)  
```text
[░░░░░░░░░░░░░░░░░░░░]
```

- [ ] P4-T1 Port DevMode credit bypass in start-new + paid flow (#2)
- [ ] P4-T2 Port DevMode profile defaults in lead prep paths (#7)
- [ ] P4-T3 Align REST OTP rate limits to Palm and keep Palm behavior end-to-end (#8)
- [ ] P4-T4 Port OTP handler lead lookup retry and Palm internal rate limiting/cooldown behavior (#33/#34/#35)
- [ ] P4-T5 Port credit stale-cache fallback and DevMode mock injection points (#31/#32)

## Phase 5: Remove Aura-Only Behavioral Divergence

**Progress:** 0/4 tasks (0%)  
```text
[░░░░░░░░░░░░░░░░░░░░]
```

- [ ] P5-T1 Remove 3-cookie flow session architecture and match Palm single cookie behavior (#30)
- [ ] P5-T2 Remove Aura dashboard share behavior for strict Palm parity (#18)
- [ ] P5-T3 Remove Aura schema integrity auto-repair hook for strict parity behavior (#22)
- [ ] P5-T4 Remove dual settings registration fallback and align with Palm registration model (#26)

## Phase 6: Test Parity and Release Readiness

**Progress:** 0/3 tasks (0%)  
```text
[░░░░░░░░░░░░░░░░░░░░]
```

- [ ] P6-T1 Create/port parity-focused Playwright suites for all CRITICAL/HIGH flows
- [ ] P6-T2 Run end-to-end validation against local Aura and compare behavior with Palm baseline
- [ ] P6-T3 Final regression checklist + changelog + handoff report

---

## Task Update Protocol (Mandatory)

After completing each task:

1. Mark task checkbox from `[ ]` to `[x]`.
2. Update that phase progress count and percentage.
3. Update that phase progress bar (20 chars, `▓` complete and `░` remaining).
4. Update **Overall Progress** task count, percentage, and global progress bar.
5. Add a short entry to `CHANGELOG.md` under `[Unreleased]`.

Formula:

- Phase % = `round((completed_phase_tasks / total_phase_tasks) * 100)`
- Overall % = `round((completed_total_tasks / 34) * 100)`
- Filled blocks = `floor(percent / 5)`

---

## Notes

- This plan supersedes parity-tracking decisions previously scattered in other docs.
- `AURA_READING_REQUIREMENTS.md` remains the product implementation history/reference.
- `compare_palm_reading_versus_aura_reading.md` is the detailed behavior-delta baseline/reference.
- For parity work status, this file is the source of truth.

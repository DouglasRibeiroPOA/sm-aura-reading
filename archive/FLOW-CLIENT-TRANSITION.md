# Client Transition Plan (Flow State First)

Goal: move client to server-driven flow state without breaking existing UI.

## Phase A: Read-Only Integration (Low Risk)
1) On page load, call `GET /flow/state` before normal initialization.
2) If server returns `status=reading_ready`, fetch and render reading by `lead_id/reading_id`.
3) Else, jump to `step_id` from the server if available.
4) Keep all existing `sessionStorage` logic as fallback (no removals yet).

## Phase B: Write-Through Updates
1) After each successful API call, call `POST /flow/state` with new step/status.
2) Use server state as the source for step transitions when possible.
3) Keep `sessionStorage` writes for now (back-compat).

## Phase C: Reduce Browser State
1) Remove step restore logic from `sessionStorage` (keep only UI input caching).
2) Remove `sm_reading_loaded` as a routing flag (use server state instead).
3) Keep URL flags (`sm_report`, `sm_magic`) only for backward compatibility.

## Phase D: Cleanup
1) Remove `localStorage` flow persistence entirely.
2) Keep only form data and transient UI values client-side.

## Notes
- JWT auth stays as-is; flow state is independent.
- Magic links call `POST /flow/magic/verify` then render by server response.
- Multi-tab concurrency: each tab continues with its own flow_id; latest activity “wins” for that flow.


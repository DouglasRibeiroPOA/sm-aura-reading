# Stateful Backend Flow Plan (DB-Backed, Client-Light)

Goal: move UI flow state out of the browser into a database-backed "flow session" so refreshes, redirects, and multi-instance WordPress are stable. Browser becomes a thin client that asks the backend where to resume.

## 1) Source of Truth
- New table: `wp_sm_flow_sessions` (or reuse an existing table if preferred).
- Each flow session stores current step, verification status, and read/reading linkage.
- Session is identified by a `flow_id` (opaque, signed) and optionally linked to `account_id` when JWT is present.

Suggested columns:
- `id` (PK, bigint)
- `flow_id` (char(36) UUID or 32 hex; indexed, unique)
- `lead_id` (bigint, nullable)
- `reading_id` (bigint, nullable)
- `account_id` (varchar, nullable)
- `email` (varchar, nullable)
- `step_id` (varchar, e.g., leadCapture, emailVerification, palmPhoto, quiz, result)
- `status` (varchar, e.g., in_progress, otp_pending, otp_verified, reading_ready, expired)
- `magic_token_hash` (varchar, nullable)
- `magic_expires_at` (datetime, nullable)
- `created_at`, `updated_at`

## 2) Identity & Cookies
- Store `flow_id` in an HttpOnly, SameSite=Lax cookie, e.g. `sm_flow_id`.
- If JWT is present (via existing auth cookie/session), the backend links the flow to `account_id`.
- If JWT is absent, flow is anonymous but still resumable (cookie only).
- Keep JWT and flow_id independent so logout only clears JWT, not the flow, unless you want full reset.

## 3) API Endpoints (REST)
All endpoints read `flow_id` from cookie. If missing, create a new flow.

- `GET /flow/state`
  - Returns: `{ flow_id, step_id, status, lead_id, reading_id, email, flags }`
  - Use on every page load before UI init.

- `POST /flow/state`
  - Payload: `{ step_id, status, lead_id?, reading_id?, email? }`
  - Called after each successful step transition or backend action.

- `POST /flow/reset`
  - Clears flow session, removes cookie, resets to welcome.

- `POST /flow/magic/verify`
  - Accepts `lead_id` + `token` from magic link.
  - Validates OTP token, links to flow, sets `step_id` to result/loading and `status=reading_ready`.

Existing endpoints stay but should update flow state:
- `lead/create` -> sets `lead_id`, `step_id=leadCapture`, `status=in_progress`
- `otp/send` -> `status=otp_pending`
- `otp/verify` -> `status=otp_verified`, `step_id=palmPhoto`
- `reading/generate` -> `status=reading_ready`, `reading_id` + `step_id=result`

## 4) Client Flow (minimal state)
- On load:
  1) Call `GET /flow/state`.
  2) If `status=reading_ready`, load reading via existing endpoint (by lead/reading id).
  3) Else render `step_id` from server.
- After any successful action, call `POST /flow/state` to update.
- Browser only stores UI inputs and temporary fields; no stateful routing in `sessionStorage`.

## 5) Magic Links
- Magic link URL contains `lead_id` + `token`.
- On arrival:
  - Client calls `POST /flow/magic/verify` immediately.
  - Backend verifies token, sets flow `step_id=result` and `status=reading_ready`.
  - Client then loads the reading (or backend returns reading HTML directly).

## 6) Logout Behavior
- Logout clears JWT session/cookie only.
- Flow session remains unless `flow/reset` called.
- If you want logout to always restart, call `POST /flow/reset` on logout.

## 7) Redirects / Cross-Domain
- If user leaves the domain and returns later in same browser, flow resumes via cookie.
- If cookie is cleared, `GET /flow/state` creates a fresh flow.
- For cross-domain purchase redirects, you can pass `flow_id` in return URL and rehydrate cookie on arrival.

## 8) Migration Strategy (Low-Risk)
- Step 1: Add flow table + new endpoints only (no UI changes yet).
- Step 2: Update backend handlers to write flow state.
- Step 3: Update client to read flow state first, but keep sessionStorage as fallback.
- Step 4: Remove sessionStorage reliance once stable.

## 9) Guardrails
- Never rely on `sessionStorage` to determine the current step.
- Backend should be authoritative: if `step_id` says "otp_pending", UI must go there.
- Ensure magic link verification updates flow state before rendering UI.

## 10) Decisions (Based on Your Guidance)
- Concurrency: allow multiple flows; each flow is independent and expires naturally. No strict locking.
- Expiration: 24 hours from last activity (extend `expires_at` on every update).
- Token: use an opaque `flow_id` cookie and validate via DB lookup. No HMAC required if `flow_id` is random and only trusted after DB lookup.

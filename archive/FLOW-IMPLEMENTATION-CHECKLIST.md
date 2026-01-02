# Flow Session Implementation Checklist (Backend)

Scope: database migration + REST endpoints only. No frontend changes in this pass.

## 1) Database Migration
- Add new table `wp_sm_flow_sessions`.
- Columns:
  - `id` BIGINT UNSIGNED auto_increment PK
  - `flow_id` CHAR(36) NOT NULL UNIQUE (UUID or 32-36 random string)
  - `account_id` VARCHAR(64) NULL
  - `lead_id` BIGINT UNSIGNED NULL
  - `reading_id` BIGINT UNSIGNED NULL
  - `email` VARCHAR(190) NULL
  - `step_id` VARCHAR(64) NOT NULL DEFAULT 'welcome'
  - `status` VARCHAR(32) NOT NULL DEFAULT 'in_progress'
  - `magic_token_hash` VARCHAR(128) NULL
  - `magic_expires_at` DATETIME NULL
  - `expires_at` DATETIME NOT NULL (default now + 24h)
  - `created_at` DATETIME NOT NULL
  - `updated_at` DATETIME NOT NULL
- Indexes:
  - `UNIQUE(flow_id)`
  - `KEY(account_id)`
  - `KEY(lead_id)`
  - `KEY(reading_id)`
  - `KEY(expires_at)`
- TTL: expired rows are ignored and can be GC’d by a daily WP-Cron.

## 2) Flow Session Helpers (PHP)
New class: `SM_Flow_Session` (or similar) in `includes/`.

Responsibilities:
- `get_or_create_flow()`
  - Reads `sm_flow_id` cookie; if valid and not expired, loads row.
  - Otherwise creates a new row with new `flow_id` and sets cookie.
- `touch_flow($updates)`
  - Updates step/status/ids; always sets `updated_at` and `expires_at` = now + 24h.
- `link_account($account_id)`
  - If JWT is present, update `account_id` on the flow.
- `reset_flow()`
  - Delete row and clear cookie.

Cookie:
- Name: `sm_flow_id`
- HttpOnly, SameSite=Lax, Secure if SSL
- Value: random UUID; server validates in DB.

## 3) REST Endpoints (New)
Add to `class-sm-rest-controller.php`.

- `GET /flow/state`
  - Returns current flow state.
  - If missing/expired, creates new flow and returns it.
  - Response fields: `flow_id`, `step_id`, `status`, `lead_id`, `reading_id`, `email`, `expires_at`.

- `POST /flow/state`
  - Accepts updates: `step_id`, `status`, `lead_id`, `reading_id`, `email`.
  - Server validates allowed step/status values.
  - Returns updated flow state.

- `POST /flow/reset`
  - Clears flow (DB + cookie), returns `flow_id` for new session.

- `POST /flow/magic/verify`
  - Payload: `lead_id`, `token`.
  - Uses existing OTP verify logic.
  - On success: update flow to `step_id=resultLoading` (or `result`) + `status=reading_ready` + `lead_id`.
  - Returns `flow_state` and optionally `reading_html` if available.

## 4) Hook Integration (Server Side)
When existing endpoints run, update flow state:
- lead/create -> `lead_id`, `email`, `step_id=leadCapture`, `status=in_progress`
- otp/send -> `status=otp_pending`
- otp/verify -> `status=otp_verified`, `step_id=palmPhoto`
- image/upload -> `step_id=palmPhoto` (if not already)
- quiz/save -> `step_id=quiz`
- reading/generate -> `reading_id`, `step_id=resultLoading`, `status=reading_ready`

## 5) Expiration Policy
- Default: 24 hours from last activity.
- On each update, extend `expires_at`.
- If expired, treat as new flow.

## 6) Concurrency
- Allow multiple flows per account.
- Latest request “wins” by updating its flow.
- No cross-flow locking; if a second flow starts, it does not break the first, but the first will eventually expire.

## 7) Security Notes
- Cookie value is opaque; trust only DB lookup.
- Do not expose JWTs or flow ids to client JS.
- Require nonce for all flow endpoints.


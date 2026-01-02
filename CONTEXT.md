# CONTEXT - Mystic Aura Reading Plugin (SoulMirror)

**Version:** 1.0.0
**Last Updated:** 2026-01-02
**Status:** In progress (see AURA_READING_REQUIREMENTS.md)
**Purpose:** Single source of truth for architecture, integrations, and flow details

---

## System Status

- Phases 1-5 complete (foundation, backend, assets, templates, prompts)
- Phase 6 partial (integration details pending)
- Phase 7 partial (testing updates pending)
- Phase 8 in progress (documentation updates)

For detailed status and task tracking, see:
- `AURA_READING_REQUIREMENTS.md`
- `CHANGELOG.md`

---

## Table of Contents

1. Plugin Overview
2. Current Architecture
3. Account Service Integration
4. Teaser Reading System
5. Report Templates
6. Database Schema
7. User Flow Scenarios
8. API Reference (High Level)
9. Security Requirements
10. Testing and Quality

---

## Plugin Overview

### Stack
- **WordPress:** 6.0+
- **PHP:** 8.0+
- **APIs:** OpenAI GPT-4o (Vision + Chat), MailerLite v3, SoulMirror Account Service
- **Frontend:** Vanilla JS
- **Naming:** `SM_` for PHP classes, `sm_` for DB tables

### Core Functionality
- 12-step flow: email -> OTP -> aura photo -> questions -> AI reading
- Shoulders-up photo analysis (energy, presence, posture)
- Free teaser reading per email (OTP-verified)
- Paid full reading via Account Service credits
- Unlock model: 2 free unlocks per teaser; third unlock triggers paywall

---

## Current Architecture

### Frontend Flow
1. Lead capture (name, email, consent)
2. Email verification (OTP)
3. Aura photo (shoulders up, good light)
4. Demographics (age range, gender)
5. Quiz (6-category questionnaire)
6. Result loading
7. Teaser reading with locked sections

### Backend Components
- `class-sm-rest-controller.php` - REST API
- `class-sm-database.php` - Schema management
- `class-sm-ai-handler.php` - OpenAI integration
- `class-sm-otp-handler.php` - Email verification
- `class-sm-lead-capture.php` - Lead management
- `class-sm-flow-session.php` - Flow session state
- `class-sm-auth-handler.php` - JWT validation and session
- `class-sm-credit-handler.php` - Credit check/deduction
- `class-sm-unlock-handler.php` - Unlock logic
- `class-sm-mailerlite-handler.php` - Subscriber sync

### Session State Management
- **Server-side source of truth:** `wp_sm_aura_flow_sessions` table
- **Cookie:** `sm_flow_id`
- **Client-side UI state:** `sessionStorage` (used only for UX)

---

## Account Service Integration

### Overview
Provides SSO, credits, and reading history. Free flow remains open.

### Service Slug (Credits)
- `aura_reading`

### Auth Flow (Simplified)
- Email exists + `account_id` present -> redirect to Account Service login
- Email not linked -> OTP -> free teaser

### Credit Check
```json
POST /wp-json/soulmirror/v1/credits/check
{
  "service": "aura_reading"
}
```

### Credit Deduction
```json
POST /wp-json/soulmirror/v1/credits/deduct
{
  "service": "aura_reading",
  "amount": 1,
  "idempotency_key": "reading_{reading_id}",
  "metadata": {
    "reading_id": "abc-123",
    "lead_id": "xyz-789"
  }
}
```

### Shop Redirect
`{account_url}/shop?service=aura_reading&return_url={callback}`

### Auth Callback
`/aura-reading/auth/callback`

---

## Teaser Reading System

- Teaser type: `aura_teaser`
- Full type: `aura_full`
- Two free unlocks per teaser reading
- HTML templates use aura categories and brand language

---

## Report Templates

- `aura-reading-template-teaser.html`
- `aura-reading-template-full.html`
- `aura-reading-template-swipe-teaser.html`
- `aura-reading-template-swipe-full.html`

HTML reports are generated server-side and available for download.

---

## Database Schema

Key tables:
- `wp_sm_aura_leads`
- `wp_sm_aura_readings`
- `wp_sm_aura_reading_sections`
- `wp_sm_aura_unlocks`
- `wp_sm_aura_flow_sessions`

All options use `sm_aura_*` keys.

---

## User Flow Scenarios

### Free User (No Account)
- Lead capture -> OTP -> quiz -> teaser reading
- Unlock up to 2 sections; further unlocks prompt paywall

### Logged-in User (Has Credits)
- Account validation -> credit check -> full reading generation

### Paid Report Link (Not Logged In)
- Redirect to Account Service login
- Return to report URL after auth

---

## API Reference (High Level)

All REST endpoints are under:
- `/wp-json/soulmirror/v1/`

Key endpoints include:
- `POST /lead` (create lead)
- `POST /otp/send`
- `POST /otp/verify`
- `POST /reading/generate`
- `POST /reading/unlock`
- `GET /flow/state`
- `POST /flow/state`

---

## Security Requirements

- Uploaded photos stored in non-public `uploads/` directory
- Validate file type (JPG/PNG) and size (max 5MB)
- OTP required for free reading access
- JWT stored as HttpOnly cookie when authenticated

---

## Testing and Quality

- Dev mode flags in `wp-config.php`:
  ```php
  define('SM_DEV_MODE', 'dev_all');
  ```
- Playwright E2E tests in `tests/`
- Use aura-specific fixtures and selectors

---

## Notes

- Do not modify the Palm Reading plugin. Use it as reference only.
- The definitive task list and priorities live in `AURA_READING_REQUIREMENTS.md`.

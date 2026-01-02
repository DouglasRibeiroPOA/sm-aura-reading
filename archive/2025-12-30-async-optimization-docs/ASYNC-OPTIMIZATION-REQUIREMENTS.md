# Async-First Reading Generation Optimization

**Project:** Mystic Palm Reading Plugin - Performance & Reliability Enhancement
**Version:** 1.0.0
**Status:** Requirements Approved
**Created:** 2025-12-30
**Priority:** HIGH

---

## Executive Summary

This project optimizes the palm reading generation system to dramatically reduce OpenAI API costs, improve generation speed, and enhance user experience through reliable async-first processing with email delivery.

### Key Objectives

1. **Reduce API Calls:** From 4-5 calls to **2 calls maximum** for teasers, 5-6 calls to **3 calls maximum** for paid reports
2. **Improve Performance:** Target 30-45s for teasers, 45-60s for paid reports
3. **Enhance Reliability:** Async-first generation with email notifications that actually work
4. **Better UX:** Real-time status updates, seen/unseen indicators, smart retry logic

### Business Impact

- **70-80% reduction** in OpenAI token costs for teaser generation
- **50-60% reduction** in generation time
- **100% async** processing eliminates user timeout frustration
- **Email delivery** enables users to leave during generation

---

## Current State Analysis

### OpenAI API Call Flow (As-Is)

**Teaser Generation:**
1. `generate_palm_summary()` → **1 Vision API call** (palm image analysis)
2. `generate_teaser_part('core_a')` → **1 Completion API call**
3. `generate_teaser_part('core_b')` → **1 Completion API call**
4. `generate_teaser_part('secondary')` → **1 Completion API call**
5. `expand_short_teaser_sections()` → **0-1 Completion API call** (if sections are short)

**Total:** 1 vision + 3-4 completion = **4-5 API calls**

**Paid Generation (New):**
- Same teaser flow (4-5 calls) + `generate_paid_completion()` (1 call)
- **Total:** 1 vision + 4-5 completion = **5-6 API calls**

**Paid Upgrade (From Existing Teaser):** ✅
- Reuses existing teaser data
- Only calls `generate_paid_completion()` (1 call)
- **Total:** **1 API call** (already optimized!)

### Async Job System (As-Is)

**Implementation:**
- `SM_Reading_Job_Handler` manages async jobs
- Uses `wp_schedule_single_event()` + `spawn_cron()` for WordPress cron
- Dispatches non-blocking HTTP request to `/reading/job-run` endpoint
- Sends completion email via `wp_mail()` (same mechanism as OTP emails)

**Problems Identified:**
1. **Undefined `$async` variable** in `class-sm-rest-controller.php` (lines 2097-2098)
2. **Completion emails not arriving** despite successful job completion
3. **No frontend polling** to auto-refresh when job completes
4. **No seen/unseen tracking** for reports in dashboard
5. **No retry limit enforcement** (free: 1 retry, paid: 2 retries)

---

## Problems & Pain Points

### 1. High API Costs
- **4-5 API calls per teaser** is excessive
- Each call incurs latency + token costs
- Expansion retries add unpredictable costs

### 2. Slow Generation Times
- Multiple sequential API calls add up (15-20s+ per teaser)
- Users experience long wait times on spinner
- No visibility into progress

### 3. Unreliable Async System
- Emails not arriving (job completes but email fails)
- `$async` variable undefined causes fallback to sync mode
- No real-time status updates for waiting users

### 4. Poor User Experience
- Users don't know if they can leave during generation
- No seen/unseen indicators in dashboard
- No way to track report status
- Failed generations have no retry guidance

### 5. Inefficient Content Generation
- 3 separate prompts for teaser sections is redundant
- Expansion logic is reactive (check → retry) vs proactive (enforce upfront)
- Palm summary generated separately, not integrated

---

## Goals & Success Criteria

### Primary Goals

| Goal | Current | Target | Success Metric |
|------|---------|--------|----------------|
| **Teaser API Calls** | 4-5 calls | 2 calls max | ≤ 2 calls (100% compliance) |
| **Paid API Calls** | 5-6 calls | 3 calls max | ≤ 3 calls (100% compliance) |
| **Teaser Generation Time** | 30-60s | 30-45s | ≤ 45s (80% of runs) |
| **Paid Generation Time** | 60-90s | 45-60s | ≤ 60s (80% of runs) |
| **Email Delivery** | ~0% (failing) | >98% | Email arrives within 5 min |
| **Async Reliability** | ~80% | >99% | Jobs complete successfully |

### Secondary Goals

- ✅ Seen/unseen tracking for dashboard reports
- ✅ Real-time status polling for waiting users
- ✅ Smart retry logic (free: 1 retry, paid: 2 retries)
- ✅ Better error handling and user messaging
- ✅ Simplified codebase (remove expansion logic)

---

## Requirements

### Functional Requirements

#### FR1: Optimized Teaser Generation (2 API Calls)

**Call 1 - Vision + Core Content (Palm Analysis):**
- **API:** OpenAI GPT-4o Vision
- **Input:** Palm image + lead data + quiz responses
- **Output:** JSON containing:
  - `palm_snapshot`: Plain-text palm analysis (~100 words, best effort)
  - `your_hand_as_mirror`: Introduction section (~100 words, best effort)
  - `foundations_of_path`: Foundations section (~200 words, minimum 140 words)
- **Total Output:** ~400-500 words
- **Prompt:** Single unified prompt requesting all fields
- **Validation:** Minimum word count for `foundations_of_path` only (140w)

**Call 2 - Completion (Remaining Teaser Sections):**
- **API:** OpenAI GPT-4o Completion
- **Input:** Palm snapshot (from Call 1) + lead data + quiz responses
- **Output:** JSON containing all remaining teaser sections:
  - `career_financial_destiny`
  - `love_relationships_path`
  - `health_wellbeing_insights`
  - `spiritual_growth_evolution`
  - `challenges_lessons_overcome`
  - `timeline_6_months`
- **Prompt:** References palm snapshot, enforces best-effort word counts
- **No expansion retries:** Accept output as-is

**Total:** 2 API calls, no expansion retries

#### FR2: Optimized Paid Generation (3 API Calls)

**Scenario A - New Paid Report (No Existing Teaser):**
- **Call 1:** Vision + core content (same as FR1 Call 1)
- **Call 2:** Teaser sections (same as FR1 Call 2)
- **Call 3:** Premium-only sections:
  - `deep_relationship_analysis`
  - `extended_timeline_12_months`
  - `life_purpose_soul_mission`
  - `shadow_work_transformation`
  - `practical_guidance_action_plan`
- **Total:** 3 API calls

**Scenario B - Upgrade Existing Teaser:**
- **Reuse:** Palm snapshot, intro, foundations, teaser sections from existing reading
- **Call 1:** Premium-only sections (same as Scenario A Call 3)
- **Total:** 1 API call (already optimized!)

#### FR3: Async-First Processing

**All reading generation MUST be async:**
- **No sync mode** - always async from the start
- **Loading UI:** Show spinner immediately with message: "Your reading is being generated. You'll receive an email when it's ready, or you can wait here for it to complete."
- **Auto-refresh:** Poll job status every 5 seconds, auto-jump to report when complete
- **Email notification:** Send completion email with direct report link
- **Navigation freedom:** User can leave/close page, return via email or dashboard

**Implementation:**
- Use reliable async execution (WordPress cron OR immediate background process)
- Fix `$async` variable undefined issue
- Ensure completion emails are sent successfully

#### FR4: Email Delivery System

**Completion Email (When Job Finishes):**
- **Trigger:** Job status changes to `completed`
- **Mechanism:** Use `wp_mail()` (same as OTP emails - proven reliable)
- **Subject:**
  - Teaser: "Your Palm Reading Is Ready"
  - Paid: "Your Full Palm Reading Is Ready"
- **Body:**
  ```
  Hey [Name],

  Your [palm reading/full palm reading] report is ready.

  View it in your dashboard:
  [Report URL]

  If the link does not open, go to your dashboard and open your latest reading.

  — SoulMirror
  ```
- **Report URL:**
  - Logged-in users: Direct link to report (`?sm_report=1&lead_id=X&reading_type=Y`)
  - Non-logged users: Same link, will redirect to login page (existing behavior)
- **Success Logging:** Log email send success/failure to debug.log

**Failure Email (When Job Fails After Retry Limit):**
- **Subject:** "Your Reading Generation Failed"
- **Body:**
  ```
  Hey [Name],

  We encountered an issue generating your palm reading.

  Please try again:
  [Retry URL]

  If the problem persists, please contact support.

  — SoulMirror
  ```
- **Retry URL:** Direct link to restart generation flow

#### FR5: Job Status Polling (Real-Time Updates)

**Frontend Polling:**
- **When:** User is on loading screen waiting for report
- **Interval:** Every 5 seconds
- **Endpoint:** `GET /wp-json/soulmirror/v1/reading/job-status?lead_id=X&reading_type=Y`
- **Response:**
  ```json
  {
    "status": "queued|running|completed|failed",
    "reading_id": "uuid-if-completed",
    "error_message": "string-if-failed",
    "progress_percent": 0-100
  }
  ```
- **Auto-jump:** When status = `completed`, automatically navigate to report
- **Error display:** When status = `failed`, show error message + retry button

**Backend Endpoint:**
- Check job status from `SM_Reading_Job_Handler`
- Return current status + reading_id if complete
- Lightweight (no heavy queries)

#### FR6: Seen/Unseen Tracking (Dashboard)

**Seen Status:**
- **Database:** Add `seen_at` column to `wp_sm_readings` table (DATETIME, NULL by default)
- **Mark as Seen:** When report page loads in browser, JS calls API:
  - `POST /wp-json/soulmirror/v1/reading/mark-seen`
  - Body: `{ "reading_id": "uuid" }`
  - Updates `seen_at = NOW()`
- **Visual Indicator (Dashboard):**
  - **Unseen reports:** Different background color (e.g., light blue/yellow highlight)
  - **Seen reports:** Normal background
  - **Badge:** Optional "NEW" badge on unseen reports
- **Not Mission-Critical:** Simple visual aid, doesn't need to be 100% watertight

#### FR7: Retry Logic & Limits

**Retry Limits:**
- **Free reports (palm_teaser):** 1 retry allowed
- **Paid reports (palm_full):** 2 retries allowed

**Tracking:**
- **Database:** Add `retry_count` column to `wp_sm_readings` table (INT, default 0)
- **Increment:** On each retry attempt, increment `retry_count`
- **Enforce:** Before creating new job, check if retry limit exceeded
- **Error Message:** "You have reached the retry limit for this reading. Please contact support."

**Retry Flow:**
1. Job fails → `status = 'failed'`
2. Send failure email with retry link
3. User clicks retry → check retry count
4. If under limit: increment count, create new job
5. If over limit: show error message, offer support contact

#### FR8: Error Handling & User Messaging

**Error Types:**

| Error | User Message | Action |
|-------|--------------|--------|
| OpenAI Timeout | "Generation is taking longer than expected. We'll email you when ready." | Continue async, send email when done |
| OpenAI Rate Limit | "We're experiencing high demand. Your reading will be ready shortly." | Retry after delay |
| Invalid Palm Image | "We couldn't analyze your palm photo. Please upload a clearer image." | Prompt re-upload |
| Credit Deduction Failed | "We couldn't process your credit. Please try again." | Show retry button |
| Retry Limit Exceeded | "You've reached the retry limit. Please contact support." | Show support link |

**Logging:**
- All errors logged to `wp-content/debug.log` with context
- Tag: `[SM READING_JOB]`
- Include: lead_id, reading_type, error_code, attempt number

---

### Non-Functional Requirements

#### NFR1: Performance

- **Teaser generation:** ≤ 45 seconds (80% of runs)
- **Paid generation:** ≤ 60 seconds (80% of runs)
- **Job status polling:** ≤ 200ms response time
- **Email delivery:** Within 5 minutes of completion

#### NFR2: Reliability

- **Async job completion:** >99% success rate
- **Email delivery:** >98% success rate
- **No sync fallback:** 100% async processing

#### NFR3: Scalability

- **Concurrent jobs:** Support at least 10 simultaneous generation jobs
- **Database performance:** Efficient queries with proper indexing
- **Cron reliability:** Jobs process within 30 seconds of scheduling

#### NFR4: Security

- **Job tokens:** Validate job_id + job_token on all job-run requests
- **Nonces:** All REST endpoints require nonces
- **Rate limiting:** Job status polling limited to 1 req/5s per user
- **Email privacy:** No sensitive data in email body

#### NFR5: Maintainability

- **Code simplification:** Remove expansion retry logic
- **Logging:** Comprehensive logging at all stages
- **Documentation:** Update all docs (CLAUDE.md, CODEX.md, TESTING.md)
- **Testing:** Automated E2E tests for async flow

---

## Technical Specifications

### Database Schema Changes

**1. Add `seen_at` column to `wp_sm_readings`:**
```sql
ALTER TABLE wp_sm_readings
ADD COLUMN seen_at DATETIME NULL DEFAULT NULL AFTER created_at;
```

**2. Add `retry_count` column to `wp_sm_readings`:**
```sql
ALTER TABLE wp_sm_readings
ADD COLUMN retry_count INT NOT NULL DEFAULT 0 AFTER seen_at;
```

**3. Add index for unseen reports query:**
```sql
ALTER TABLE wp_sm_readings
ADD INDEX idx_lead_seen (lead_id, seen_at);
```

### API Endpoints

#### 1. Job Status Polling
```
GET /wp-json/soulmirror/v1/reading/job-status
```
**Parameters:**
- `lead_id` (required): UUID
- `reading_type` (required): `palm_teaser` or `palm_full`

**Response:**
```json
{
  "success": true,
  "status": "queued|running|completed|failed",
  "reading_id": "uuid-or-null",
  "error_message": "string-or-null",
  "progress_percent": 50
}
```

#### 2. Mark Reading as Seen
```
POST /wp-json/soulmirror/v1/reading/mark-seen
```
**Body:**
```json
{
  "reading_id": "uuid"
}
```
**Response:**
```json
{
  "success": true,
  "seen_at": "2025-12-30 10:30:45"
}
```

#### 3. Retry Reading Generation
```
POST /wp-json/soulmirror/v1/reading/retry
```
**Body:**
```json
{
  "lead_id": "uuid",
  "reading_type": "palm_teaser|palm_full"
}
```
**Response:**
```json
{
  "success": true,
  "job_id": "uuid",
  "message": "Reading generation restarted"
}
```
**Error (retry limit exceeded):**
```json
{
  "success": false,
  "error_code": "retry_limit_exceeded",
  "message": "You have reached the retry limit for this reading."
}
```

### OpenAI Prompt Structure

#### Call 1: Vision + Core Content

**System Prompt:**
```
You are an expert palm reader and spiritual guide. Analyze the palm image provided and generate a detailed, personalized palm reading based on the user's hand features, quiz responses, and birth date.

CRITICAL INSTRUCTIONS:
- Return ONLY valid JSON (no markdown, no explanations)
- Use the exact field names specified
- Write in a warm, mystical, yet professional tone
- Reference specific palm features you observe
- Be specific and personalized based on the user's quiz responses
```

**User Prompt:**
```
Analyze this palm image and create the following sections:

1. "palm_snapshot": A brief textual description of the key palm features you observe (lines, mounts, markings, hand shape, finger length). About 100 words. This is for internal reference, not shown to the user.

2. "your_hand_as_mirror": An introduction explaining what this person's hand reveals about their essential nature and life path. About 100 words.

3. "foundations_of_path": A deeper exploration of the core patterns and themes visible in their palm. Minimum 140 words, aim for 180-200 words.

User Details:
- Name: [name]
- Birth Date: [birth_date]
- Quiz Responses:
  - Life Focus: [quiz_1]
  - Current Challenge: [quiz_2]
  - [additional quiz fields...]

Return JSON in this exact format:
{
  "palm_snapshot": "string (100 words)",
  "your_hand_as_mirror": {
    "content": "string (100 words)"
  },
  "foundations_of_path": {
    "content": "string (180-200 words, minimum 140)"
  }
}
```

#### Call 2: Teaser Sections

**System Prompt:**
```
You are an expert palm reader creating a personalized reading. You have already analyzed the palm and created a palm snapshot. Now generate the remaining teaser sections based on that analysis.

CRITICAL INSTRUCTIONS:
- Return ONLY valid JSON
- Reference the palm snapshot for consistency
- Each section should be detailed and personalized
- Best effort on word counts (no strict validation)
```

**User Prompt:**
```
Based on the palm snapshot below, create the remaining teaser sections for this reading:

Palm Snapshot:
[palm_snapshot from Call 1]

User Details:
- Name: [name]
- Birth Date: [birth_date]
- Quiz Responses: [...]

Generate these sections:

1. "career_financial_destiny": Career and financial path (150-180 words)
2. "love_relationships_path": Love and relationship insights (150-180 words)
3. "health_wellbeing_insights": Health and wellbeing guidance (120-150 words)
4. "spiritual_growth_evolution": Spiritual growth path (120-150 words)
5. "challenges_lessons_overcome": Challenges and lessons (120-150 words)
6. "timeline_6_months": 6-month timeline prediction (150-180 words)

Return JSON in this exact format:
{
  "career_financial_destiny": {
    "content": "string"
  },
  "love_relationships_path": {
    "content": "string"
  },
  "health_wellbeing_insights": {
    "content": "string"
  },
  "spiritual_growth_evolution": {
    "content": "string"
  },
  "challenges_lessons_overcome": {
    "content": "string"
  },
  "timeline_6_months": {
    "content": "string"
  }
}
```

#### Call 3: Premium Sections (Paid Only)

**System Prompt:**
```
You are an expert palm reader creating premium content for a paid reading. Use the palm snapshot and existing reading context to generate deep, transformational insights.

CRITICAL INSTRUCTIONS:
- Return ONLY valid JSON
- Premium sections should be significantly more detailed and actionable
- Reference the palm snapshot and user's journey
- Provide practical, specific guidance
```

**User Prompt:**
```
Based on the palm snapshot and existing reading below, create the premium sections:

Palm Snapshot:
[palm_snapshot]

Existing Reading Summary:
- Foundations: [brief summary]
- Key Themes: [themes from teaser]

User Details:
- Name: [name]
- Birth Date: [birth_date]
- Quiz Responses: [...]

Generate these premium sections:

1. "deep_relationship_analysis": Deep relationship patterns and compatibility (250-300 words)
2. "extended_timeline_12_months": Detailed 12-month timeline with specific guidance (300-350 words)
3. "life_purpose_soul_mission": Life purpose and soul mission exploration (250-300 words)
4. "shadow_work_transformation": Shadow work and transformation path (250-300 words)
5. "practical_guidance_action_plan": Actionable steps and practical guidance (250-300 words)

Return JSON in this exact format:
{
  "deep_relationship_analysis": {
    "content": "string"
  },
  "extended_timeline_12_months": {
    "content": "string"
  },
  "life_purpose_soul_mission": {
    "content": "string"
  },
  "shadow_work_transformation": {
    "content": "string"
  },
  "practical_guidance_action_plan": {
    "content": "string"
  }
}
```

### Async Job Execution Strategy

**Chosen Approach:** WordPress Cron + Non-Blocking HTTP Request (Hybrid)

**Why:**
- Proven to work in current system (mostly)
- Simple to implement and maintain
- No external dependencies
- Reliable on most hosting environments

**Implementation:**
1. **Create job record** in `wp_options` (existing)
2. **Schedule wp-cron event** for +5 seconds (existing)
3. **Dispatch non-blocking HTTP request** to trigger immediate execution (existing)
4. **Fix `$async` variable issue** in REST controller
5. **Enhance email delivery** with better error handling

**Improvements:**
- Add retry logic to non-blocking dispatch (3 attempts)
- Log all dispatch attempts and responses
- Monitor email send success/failure
- Add job timeout detection (mark as failed if running > 5 minutes)

---

## User Experience Flows

### Flow 1: Teaser Generation (Happy Path)

1. User completes quiz (email → OTP → photo → questions)
2. Clicks "Generate Reading" button
3. **Loading screen appears:**
   - Spinner animation
   - Message: "Your reading is being generated. You'll receive an email when it's ready, or you can wait here for it to complete."
   - Subtext cycles through mystical messages
4. **Frontend starts polling** job status every 5 seconds
5. **Backend generates reading** async (2 API calls)
6. **Job completes** (30-45 seconds)
7. **Email sent** to user with report link
8. **Frontend detects completion** via polling, auto-jumps to report
9. User sees completed reading

**If user navigates away:**
- Email arrives with direct link
- User clicks link → sees report
- Report marked as "unseen" in dashboard
- When report page loads, JS marks it as "seen"

### Flow 2: Paid Report Generation (Upgrade)

1. User has existing teaser, clicks "Unlock Full Reading"
2. Credit check passes (sufficient credits)
3. **Loading screen appears** (same as teaser flow)
4. **Backend reuses teaser data** (palm snapshot, intro, foundations, teaser sections)
5. **Generates premium sections only** (1 API call)
6. **Job completes** (15-30 seconds)
7. **Email sent** + **frontend auto-jumps** to full report
8. **Credit deducted** (idempotency key prevents duplicates)

### Flow 3: Generation Failure + Retry

1. Job starts, encounters OpenAI error
2. **Job status** → `failed`
3. **Email sent** with error message + retry link
4. **Frontend shows error:**
   - "We encountered an issue generating your reading."
   - Retry button (if under retry limit)
5. User clicks retry button
6. **Backend checks retry count** (free: 1, paid: 2)
7. If under limit: **Increment count**, restart job
8. If over limit: **Show error**, offer support contact

### Flow 4: Dashboard - Unseen Reports

1. User logs into dashboard
2. **Report listing shows** all readings
3. **Unseen reports** have light blue/yellow background
4. **"NEW" badge** appears on unseen reports
5. User clicks report → navigates to reading
6. **JS fires on page load:** `POST /reading/mark-seen`
7. Backend updates `seen_at = NOW()`
8. Next time dashboard loads, report shows as seen (normal background)

---

## Testing & Quality Assurance

### Automated Testing Requirements

**E2E Tests (Playwright):**

1. **Test: Teaser Async Generation**
   - Complete quiz flow
   - Verify loading screen message
   - Wait for job completion
   - Verify auto-jump to report
   - Check API call count (max 2)
   - Verify email sent

2. **Test: Paid Generation from Teaser**
   - Seed existing teaser
   - Trigger paid upgrade
   - Verify 1 API call only
   - Verify credit deduction
   - Verify email sent

3. **Test: Job Status Polling**
   - Start generation
   - Poll status endpoint
   - Verify status transitions: queued → running → completed
   - Verify auto-refresh triggers

4. **Test: Seen/Unseen Tracking**
   - Seed unseen report
   - Load dashboard, verify unseen indicator
   - Open report
   - Verify mark-seen API called
   - Reload dashboard, verify seen indicator

5. **Test: Retry Logic**
   - Force job failure
   - Verify failure email
   - Click retry (under limit)
   - Verify retry count incremented
   - Force failure again (exceed limit)
   - Verify retry blocked

6. **Test: User Navigates Away**
   - Start generation
   - Close browser tab
   - Wait for completion
   - Verify email arrives
   - Click email link
   - Verify report loads

**Unit Tests:**

1. API call count validation
2. Prompt template generation
3. Job status transitions
4. Retry limit enforcement
5. Email delivery success/failure handling

### Manual Testing Checklist

- [ ] Teaser generation completes in < 45s (5 test runs)
- [ ] Paid generation completes in < 60s (5 test runs)
- [ ] Completion emails arrive within 5 minutes
- [ ] Frontend auto-refreshes when job completes
- [ ] Seen/unseen indicators work correctly
- [ ] Retry limits enforced (free: 1, paid: 2)
- [ ] Error messages clear and actionable
- [ ] No PHP errors in debug.log
- [ ] No JavaScript errors in console

### Performance Metrics to Track

**Before Optimization:**
- Teaser: 4-5 API calls, 30-60s average
- Paid: 5-6 API calls, 60-90s average
- Email delivery: ~0% (failing)

**After Optimization (Target):**
- Teaser: 2 API calls, 30-45s average
- Paid: 3 API calls (new) / 1 API call (upgrade), 45-60s average
- Email delivery: >98%

**Monitor:**
- API call count per reading (log each call)
- Token usage per reading (log total tokens)
- Generation time per reading (log start/end timestamps)
- Email delivery success rate (log all sends)
- Job failure rate (track failed jobs)

---

## Implementation Phases

### Phase 1: OpenAI Prompt Consolidation (Week 1)

**Goal:** Reduce API calls from 4-5 to 2 for teasers

**Tasks:**
1. ✅ Create unified Vision prompt (Call 1: palm_snapshot + intro + foundations)
2. ✅ Create unified Completion prompt (Call 2: all teaser sections)
3. ✅ Update `SM_AI_Handler::generate_teaser_context()` to use new flow
4. ✅ Remove expansion retry logic (`expand_short_teaser_sections()`)
5. ✅ Update schema validation (best-effort word counts)
6. ✅ Add API call count logging
7. ✅ Test with 10 sample readings, verify 2 calls max

**Acceptance Criteria:**
- Teaser generation uses exactly 2 API calls (100% compliance)
- No expansion retries triggered
- Content quality maintained (manual review)
- All tests pass

### Phase 2: Paid Report Optimization (Week 1)

**Goal:** Reduce API calls from 5-6 to 3 for new paid reports

**Tasks:**
1. ✅ Create premium sections prompt (Call 3)
2. ✅ Update `SM_AI_Handler::generate_paid_reading()` to use new flow
3. ✅ Ensure upgrade flow reuses teaser data (already optimized)
4. ✅ Test new paid flow (3 calls)
5. ✅ Test upgrade flow (1 call)
6. ✅ Verify credit deduction works correctly

**Acceptance Criteria:**
- New paid reports use exactly 3 API calls
- Upgraded paid reports use exactly 1 API call
- Content quality maintained
- All tests pass

### Phase 3: Async Job Reliability (Week 2)

**Goal:** Fix async job issues and email delivery

**Tasks:**
1. ✅ Fix `$async` undefined variable in `class-sm-rest-controller.php`
2. ✅ Add retry logic to non-blocking dispatch
3. ✅ Enhance email delivery error handling
4. ✅ Add job timeout detection (5 min max)
5. ✅ Add comprehensive logging for all job stages
6. ✅ Test email delivery (10 runs, verify >98% success)

**Acceptance Criteria:**
- No `$async` undefined errors
- Emails arrive for >98% of completed jobs
- Jobs timeout gracefully after 5 minutes
- All job stages logged to debug.log

### Phase 4: Frontend Enhancements (Week 2)

**Goal:** Add job status polling and auto-refresh

**Tasks:**
1. ✅ Create `GET /reading/job-status` endpoint
2. ✅ Implement frontend polling (every 5s)
3. ✅ Add auto-jump to report when complete
4. ✅ Update loading screen message
5. ✅ Add error display on failure
6. ✅ Test polling flow with E2E tests

**Acceptance Criteria:**
- Frontend polls every 5 seconds
- Auto-jump triggers when job completes
- Error messages display correctly
- No performance degradation from polling

### Phase 5: Seen/Unseen Tracking (Week 3)

**Goal:** Add seen/unseen indicators to dashboard

**Tasks:**
1. ✅ Add `seen_at` and `retry_count` columns to database
2. ✅ Create `POST /reading/mark-seen` endpoint
3. ✅ Add JS to mark report as seen on page load
4. ✅ Update dashboard query to fetch `seen_at`
5. ✅ Add visual indicators (background color + badge)
6. ✅ Test seen/unseen flow

**Acceptance Criteria:**
- Unseen reports highlighted in dashboard
- Clicking report marks it as seen
- Indicator updates on next dashboard load
- Database migration runs successfully

### Phase 6: Retry Logic & Error Handling (Week 3)

**Goal:** Implement smart retry limits and error messaging

**Tasks:**
1. ✅ Add retry count tracking
2. ✅ Create `POST /reading/retry` endpoint
3. ✅ Enforce retry limits (free: 1, paid: 2)
4. ✅ Update failure email with retry link
5. ✅ Add retry button to frontend error state
6. ✅ Test retry flow (under limit and over limit)

**Acceptance Criteria:**
- Retry limits enforced correctly
- Failure emails include retry link
- Users can retry within limits
- Clear error when limit exceeded

### Phase 7: Testing & Documentation (Week 4)

**Goal:** Comprehensive testing and documentation updates

**Tasks:**
1. ✅ Write E2E tests for all flows
2. ✅ Run 50 test readings (25 teaser, 25 paid)
3. ✅ Verify performance metrics meet targets
4. ✅ Update CLAUDE.md with new async-first instructions
5. ✅ Update CODEX.md with new async-first instructions
6. ✅ Update TESTING.md with new test cases
7. ✅ Create PROGRESS.md to track implementation

**Acceptance Criteria:**
- All E2E tests pass
- Performance targets met (80% of runs)
- Documentation complete and accurate
- No regressions in existing flows

---

## Risk Mitigation

### Risk 1: Prompt Consolidation Reduces Content Quality

**Mitigation:**
- Run 20-30 test readings before deploying
- Manual content review by subject matter expert
- A/B test new prompts vs old prompts (10 each)
- Keep old prompts as fallback for 2 weeks

**Rollback Plan:**
- Revert to old 3-part prompt system
- Keep expansion retries as safety net
- Monitor content quality metrics

### Risk 2: Async Jobs Don't Complete Reliably

**Mitigation:**
- Add job timeout detection (5 min)
- Retry failed jobs automatically (up to limit)
- Monitor job completion rate daily
- Alert if completion rate drops below 95%

**Rollback Plan:**
- Add sync fallback for critical failures
- Increase timeout to 10 minutes
- Switch to alternative async mechanism (if needed)

### Risk 3: Email Delivery Still Fails

**Mitigation:**
- Use proven `wp_mail()` mechanism (same as OTP)
- Add comprehensive email send logging
- Test with multiple email providers
- Monitor delivery rate hourly for first week

**Rollback Plan:**
- Fall back to in-app notifications only
- Add SMS notification option (future)
- Use third-party email service (Mailgun, SendGrid)

### Risk 4: Frontend Polling Impacts Performance

**Mitigation:**
- Limit polling to 1 request per 5 seconds
- Use lightweight endpoint (no heavy queries)
- Cache job status for 3 seconds
- Stop polling after 5 minutes

**Rollback Plan:**
- Increase polling interval to 10 seconds
- Disable auto-refresh (manual refresh only)
- Use WebSocket for real-time updates (future)

---

## Maintenance & Monitoring

### Logging Requirements

**All log entries must include:**
- Timestamp
- Tag: `[SM READING_JOB]` or `[SM AI_READING]`
- Lead ID
- Reading type
- Context (what's happening)

**What to log:**

1. **Job Creation:**
   ```
   [SM READING_JOB] Job created: lead_id=X, type=Y, job_id=Z
   ```

2. **API Calls:**
   ```
   [SM AI_READING] API Call 1: Vision (palm analysis) - 1,234 tokens
   [SM AI_READING] API Call 2: Completion (teaser sections) - 2,345 tokens
   ```

3. **Job Completion:**
   ```
   [SM READING_JOB] Job completed: lead_id=X, type=Y, duration=45s, reading_id=Z
   ```

4. **Email Send:**
   ```
   [SM READING_JOB] Completion email sent: lead_id=X, email=user@example.com
   [SM READING_JOB] Email send failed: lead_id=X, error=SMTP timeout
   ```

5. **Retry Attempts:**
   ```
   [SM READING_JOB] Retry initiated: lead_id=X, retry_count=1, limit=2
   [SM READING_JOB] Retry blocked: lead_id=X, retry_count=2, limit=2
   ```

### Performance Monitoring

**Metrics to track weekly:**
- Average teaser generation time
- Average paid generation time
- API call count per reading (verify 2/3 max)
- Token usage per reading
- Job completion rate
- Email delivery rate
- Retry rate
- Failure rate by error type

**Alert thresholds:**
- Generation time > 60s (teasers) or > 90s (paid)
- Job completion rate < 95%
- Email delivery rate < 98%
- Failure rate > 5%

### Support & Escalation

**User-facing issues:**
- Generation timeout → Check debug.log for job status
- Email not received → Check email send logs, verify wp_mail works
- Retry blocked → Verify retry count in database
- Report not seen → Clear browser cache, verify mark-seen API

**Backend issues:**
- OpenAI rate limits → Add exponential backoff retry
- Job stuck in "running" → Add timeout detection (5 min)
- Email SMTP failure → Switch to alternative email provider

---

## Success Metrics

### Quantitative Metrics

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Teaser API Calls | 4-5 | 2 | Log each call, verify count |
| Paid API Calls (New) | 5-6 | 3 | Log each call, verify count |
| Paid API Calls (Upgrade) | 1 | 1 | Already optimized |
| Teaser Generation Time | 30-60s | 30-45s | Log start/end timestamps |
| Paid Generation Time | 60-90s | 45-60s | Log start/end timestamps |
| Email Delivery Rate | ~0% | >98% | Track send success/failure |
| Job Completion Rate | ~80% | >99% | Track completed vs failed |
| Token Usage (Teaser) | ~3,000 | ~2,000 | Log total tokens per reading |

### Qualitative Metrics

- **Content Quality:** Manual review of 30 readings (10 teaser, 10 paid new, 10 paid upgrade)
- **User Satisfaction:** Monitor support tickets related to generation issues
- **Developer Experience:** Simplified codebase, easier maintenance

---

## Appendices

### Appendix A: Current vs. Target Comparison

**Current Flow (Teaser):**
```
1. generate_palm_summary() → Vision API
2. generate_teaser_part('core_a') → Completion API
3. generate_teaser_part('core_b') → Completion API
4. generate_teaser_part('secondary') → Completion API
5. expand_short_teaser_sections() → Completion API (maybe)

Total: 4-5 API calls
Time: 30-60 seconds
```

**Target Flow (Teaser):**
```
1. Vision API → palm_snapshot + intro + foundations
2. Completion API → all teaser sections (using snapshot)

Total: 2 API calls
Time: 30-45 seconds
```

**Savings:** 50-60% fewer API calls, 0-25% faster

---

### Appendix B: Email Templates

**Completion Email (Teaser):**
```
Subject: Your Palm Reading Is Ready

Body:
Hey [Name],

Your palm reading report is ready.

View it in your dashboard:
[Report URL]

If the link does not open, go to your dashboard and open your latest reading.

— SoulMirror
```

**Completion Email (Paid):**
```
Subject: Your Full Palm Reading Is Ready

Body:
Hey [Name],

Your full palm reading report is ready.

View it in your dashboard:
[Report URL]

If the link does not open, go to your dashboard and open your latest reading.

— SoulMirror
```

**Failure Email:**
```
Subject: Your Reading Generation Failed

Body:
Hey [Name],

We encountered an issue generating your palm reading.

Please try again:
[Retry URL]

If the problem persists, please contact support at support@soulmirror.com.

— SoulMirror
```

---

### Appendix C: Database Schema Reference

**wp_sm_readings (changes):**
```sql
CREATE TABLE wp_sm_readings (
  id VARCHAR(36) PRIMARY KEY,
  lead_id VARCHAR(36) NOT NULL,
  content_data LONGTEXT NOT NULL,
  reading_type VARCHAR(50) NOT NULL,
  created_at DATETIME NOT NULL,
  seen_at DATETIME NULL DEFAULT NULL,          -- NEW
  retry_count INT NOT NULL DEFAULT 0,          -- NEW
  account_id VARCHAR(36) NULL,
  ...
  INDEX idx_lead_seen (lead_id, seen_at)       -- NEW
);
```

---

**Document Version:** 1.0.0
**Status:** ✅ Requirements Approved
**Next Step:** Create PROGRESS.md and begin Phase 1 implementation

---

## Approval & Sign-Off

**Reviewed By:** Development Team
**Approved By:** Project Owner
**Date:** 2025-12-30
**Status:** Approved for Implementation

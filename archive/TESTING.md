# Mystic Palm Reading AI - Testing Guide

**Plugin Version:** 1.0.0 (MVP1)
**Testing Phase:** Phase 6 - Quality Assurance
**Created:** 2025-12-12

---

## Testing Environment Setup

### Prerequisites
- WordPress 5.8+ installed
- PHP 7.4+ configured
- Plugin activated in WordPress Admin
- SSL/HTTPS enabled (recommended for camera access)
- Test email account for OTP testing
- OpenAI API key with GPT-4o access
- MailerLite API account (v3)

### Initial Setup Steps

1. **Activate Plugin**
   - Navigate to WordPress Admin → Plugins
   - Activate "Mystic Palm Reading AI"
   - Verify no PHP errors appear

2. **Configure API Keys**
   - Navigate to WordPress Admin → SoulMirror Settings
   - Enter OpenAI API Key
   - Enter MailerLite API Key
   - Enter MailerLite Group ID
   - Save settings

3. **Verify Database Tables**
   - Check phpMyAdmin or use database tool
   - Verify tables exist:
     - `{prefix}_sm_leads`
     - `{prefix}_sm_otps`
     - `{prefix}_sm_quiz`
     - `{prefix}_sm_readings`
     - `{prefix}_sm_logs`

4. **Create Test Page**
   - Create new WordPress Page
   - Add shortcode: `[soulmirror_palm_reading]`
   - Publish page
   - Note the page URL for testing

---

## Automation Plan (Mobile UX Smoke) — 2025-12-14

- **Goal:** Avoid manual regressions for the 12-step mobile flow (lead → OTP → photo → quiz → result).
- **Tooling:** Playwright (headless), configurable base URL + test email.
- **Setup:** `npm install @playwright/test` (repo-local); set env `SM_BASE_URL=https://yoursite/page` and `SM_TEST_EMAIL=<inbox>`.
- **Script outline (pseudo-js):**
```javascript
// tests/e2e/mobile-flow.spec.js
const { test, expect } = require('@playwright/test');
test('mobile happy path', async ({ page }) => {
  await page.goto(process.env.SM_BASE_URL);
  await page.setViewportSize({ width: 390, height: 844 }); // iPhone 12-ish
  await page.getByRole('textbox', { name: /name/i }).fill('Test User');
  await page.getByRole('textbox', { name: /email/i }).fill(process.env.SM_TEST_EMAIL);
  await page.getByRole('combobox', { name: /identify/i }).selectOption('woman');
  await page.getByRole('spinbutton', { name: /old/i }).fill('28');
  await page.getByRole('checkbox').check();
  await page.getByRole('button', { name: /continue/i }).click();
  await page.waitForSelector('.loading-spinner');
  // OTP: assume magic link or mock. If real, read inbox via API and fill 4 digits here.
  // Photo: mock by setting window.appState.userData.palmImage = placeholder and clicking next.
  await page.evaluate(() => { window.appState.userData.palmImage = window.appState.userData.palmImage || 'data:image/jpeg;base64,...'; });
  await page.getByRole('button', { name: /continue/i }).click();
  await page.waitForSelector('.options-container');
  await page.getByText(/Energetic/).click();
  await page.getByText(/Career/).click();
  await page.getByText(/Fire/).click();
  await page.getByText(/Success/).click();
  await page.getByRole('button', { name: /continue/i }).click();
  await page.waitForSelector('.loading-spinner');
  await page.waitForSelector('.result-content', { timeout: 15000 });
  await expect(page.locator('.result-content')).toBeVisible();
});
```
- **Run:** `npx playwright test tests/e2e/mobile-flow.spec.js --headed` (or headless CI).
- **Notes:** For real OTP and photo capture, swap the inline mocks with an inbox fetch + `getUserMedia` permission grant.

---

## Unit 6.1: Component Unit Testing

### 6.1.1: Database Handler (SM_Database)

**Test Case: Table Creation**
- [ ] Activate plugin
- [ ] Check database for all 5 tables
- [ ] Verify correct schema (columns, types, indexes)
- [ ] Verify UUID primary keys
- [ ] Verify foreign key relationships
- [ ] Check database version in wp_options

**Expected Results:**
- All tables created successfully
- Correct column types and constraints
- Indexes on email, lead_id, created_at fields
- Database version matches plugin version

**Test Case: Migration System**
- [ ] Note current database version
- [ ] Deactivate and reactivate plugin
- [ ] Verify tables not duplicated
- [ ] Verify data persists across reactivation

**Expected Results:**
- No duplicate tables
- No data loss
- Migration system handles existing tables gracefully

---

### 6.1.2: Settings Handler (SM_Settings)

**Test Case: Admin Page Display**
- [ ] Navigate to WordPress Admin → SoulMirror Settings
- [ ] Verify page loads without errors
- [ ] Check all setting fields present:
  - OpenAI API Key
  - MailerLite API Key
  - MailerLite Group ID
  - OTP Expiration Time
  - Max OTP Attempts
  - Resend Cooldown
  - Debug Logging Toggle

**Expected Results:**
- Admin page displays correctly
- All fields visible
- No PHP warnings or errors

**Test Case: Settings Save/Retrieve**
- [ ] Enter test API key in OpenAI field
- [ ] Enter test API key in MailerLite field
- [ ] Change OTP settings
- [ ] Click Save
- [ ] Refresh page
- [ ] Verify all settings persisted

**Expected Results:**
- Settings save successfully
- Settings load correctly on page refresh
- Success message displayed

**Test Case: API Key Encryption**
- [ ] Save API key in settings
- [ ] Check wp_options table directly
- [ ] Verify API keys are not stored in plain text
- [ ] Verify encrypted values exist

**Expected Results:**
- API keys encrypted in database
- Plain text not visible in wp_options

---

### 6.1.3: Logger (SM_Logger)

**Test Case: Log File Creation**
- [ ] Trigger any plugin action (e.g., activate plugin)
- [ ] Check plugin root directory for `debug.log`
- [ ] Verify file is created

**Expected Results:**
- debug.log file exists
- File has write permissions

**Test Case: Log Entry Format**
- [ ] Trigger various plugin actions
- [ ] Open debug.log
- [ ] Verify entries have correct format:
  - Timestamp
  - Event type
  - Status (SUCCESS/ERROR/WARNING)
  - Message
  - Context data

**Expected Results:**
- Log entries well-formatted
- Timestamps accurate
- Event types descriptive

**Test Case: Sensitive Data Masking**
- [ ] Trigger OTP send
- [ ] Check debug.log
- [ ] Verify full OTP code NOT logged
- [ ] Verify email partially masked
- [ ] Verify API keys not exposed

**Expected Results:**
- Sensitive data masked (e.g., em***@example.com)
- OTP hashed or truncated
- No API keys in logs

**Test Case: Log Rotation**
- [ ] Check current log file size
- [ ] (If < 10MB, note: automatic rotation at 10MB)

**Expected Results:**
- Log rotation happens at 10MB
- Old logs archived with date suffix

---

### 6.1.4: Lead Handler (SM_Lead_Handler)

**Test Case: Lead Creation - Valid Data**
- [ ] Use REST API or direct method call
- [ ] Create lead with:
  - Valid name
  - Valid email
  - Valid identity
  - GDPR consent = true
- [ ] Check database for new lead record

**Expected Results:**
- Lead created successfully
- UUID generated
- Timestamps recorded
- GDPR consent logged

**Test Case: Lead Creation - Duplicate Email**
- [ ] Create lead with email test@example.com
- [ ] Attempt to create another lead with same email
- [ ] Verify error returned

**Expected Results:**
- Duplicate email rejected
- Error message: "Email already registered"
- Original lead unchanged

**Test Case: Lead Creation - Invalid Email**
- [ ] Attempt lead creation with invalid email format
- [ ] Verify validation error

**Expected Results:**
- Invalid email rejected
- User-friendly error message

**Test Case: Lead Creation - Missing GDPR Consent**
- [ ] Attempt lead creation with gdpr = false
- [ ] Verify error returned

**Expected Results:**
- Lead creation blocked
- Error: "GDPR consent required"

**Test Case: Lead Retrieval**
- [ ] Create test lead
- [ ] Retrieve by lead_id
- [ ] Retrieve by email
- [ ] Verify data matches

**Expected Results:**
- Lead retrievable by ID
- Lead retrievable by email
- All data accurate

---

### 6.1.5: OTP Handler (SM_OTP_Handler)

**Test Case: OTP Generation**
- [ ] Create test lead
- [ ] Generate OTP
- [ ] Check database sm_otps table
- [ ] Verify OTP record created

**Expected Results:**
- 6-digit numeric OTP generated
- OTP hashed before storage
- Expiration time set (10 minutes default)
- Attempts counter = 0

**Test Case: OTP Email Delivery**
- [ ] Generate OTP for test email
- [ ] Check email inbox
- [ ] Verify OTP email received
- [ ] Check email content

**Expected Results:**
- Email delivered successfully
- Subject: "Your SoulMirror Verification Code"
- OTP code visible in email
- Expiration time mentioned
- Branding matches requirements

**Test Case: OTP Verification - Valid Code**
- [ ] Generate OTP
- [ ] Copy code from email
- [ ] Verify OTP via API
- [ ] Check lead email_confirmed status

**Expected Results:**
- Valid OTP accepted
- email_confirmed = true in database
- Success message returned
- sm_otp_verified hook fires

**Test Case: OTP Verification - Invalid Code**
- [ ] Generate OTP
- [ ] Submit wrong 6-digit code
- [ ] Verify error returned
- [ ] Check attempt counter incremented

**Expected Results:**
- Invalid OTP rejected
- Attempt counter incremented
- Remaining attempts shown in error

**Test Case: OTP Lockout**
- [ ] Generate OTP
- [ ] Submit 3 invalid codes
- [ ] Verify lockout occurs
- [ ] Attempt 4th verification

**Expected Results:**
- After 3 failed attempts, OTP locked
- 4th attempt blocked
- Error: "Too many attempts"

**Test Case: OTP Expiration**
- [ ] Generate OTP
- [ ] Wait 10+ minutes (or modify expiration in DB)
- [ ] Attempt verification
- [ ] Verify expired error

**Expected Results:**
- Expired OTP rejected
- Error: "Code expired"
- User prompted to request new code

**Test Case: Resend Cooldown**
- [ ] Send OTP
- [ ] Immediately request resend
- [ ] Verify cooldown enforced

**Expected Results:**
- Resend blocked if within cooldown period
- Error shows time remaining
- Cooldown default: 10 minutes

**Test Case: Rate Limiting**
- [ ] Send OTP 3 times for same email
- [ ] Attempt 4th send within 1 hour
- [ ] Verify rate limit error

**Expected Results:**
- 4th attempt blocked
- Error: "Too many requests"
- Limit: 3 sends per email per hour

---

### 6.1.6: MailerLite Handler (SM_MailerLite_Handler)

**Test Case: Connection Test**
- [ ] Navigate to SoulMirror Settings
- [ ] Enter valid MailerLite API key
- [ ] (If test button exists) Click Test Connection
- [ ] Verify connection success

**Expected Results:**
- API connection successful
- No authentication errors

**Test Case: Subscriber Upsert - New Subscriber**
- [ ] Create test lead
- [ ] Verify OTP
- [ ] Trigger MailerLite sync
- [ ] Check MailerLite dashboard
- [ ] Verify subscriber created

**Expected Results:**
- New subscriber appears in MailerLite
- Email matches lead email
- Name matches lead name
- Assigned to correct Group ID

**Test Case: Subscriber Upsert - Existing Subscriber**
- [ ] Use email already in MailerLite
- [ ] Create lead and verify
- [ ] Trigger sync
- [ ] Check MailerLite
- [ ] Verify subscriber updated (not duplicated)

**Expected Results:**
- Existing subscriber updated
- No duplicate created
- Group assignment updated

**Test Case: Resubscribe Previously Unsubscribed**
- [ ] Unsubscribe email in MailerLite
- [ ] Create lead with that email
- [ ] Verify and sync
- [ ] Check MailerLite status

**Expected Results:**
- Subscriber resubscribed
- Status = active
- Added to group

**Test Case: Error Handling - Invalid API Key**
- [ ] Set invalid API key in settings
- [ ] Trigger MailerLite sync
- [ ] Verify error logged
- [ ] Verify user flow NOT blocked

**Expected Results:**
- Error logged to debug.log
- User progression continues
- No user-facing error shown

**Test Case: Error Handling - Network Failure**
- [ ] (Simulate by disconnecting internet or using invalid endpoint)
- [ ] Trigger sync
- [ ] Verify graceful degradation

**Expected Results:**
- Failure logged
- User flow continues
- No fatal errors

---

### 6.1.7: Image Handler (SM_Image_Handler)

**Test Case: Valid Image Upload - JPEG**
- [ ] Upload JPEG palm image (< 5MB)
- [ ] Verify upload success
- [ ] Check storage directory

**Expected Results:**
- Image uploaded successfully
- Stored in wp-content/uploads/sm-palm-private/
- .htaccess prevents direct access
- Image reference returned

**Test Case: Valid Image Upload - PNG**
- [ ] Upload PNG palm image
- [ ] Verify success

**Expected Results:**
- PNG accepted
- Same storage as JPEG

**Test Case: Invalid File Type**
- [ ] Attempt upload of GIF, BMP, or other format
- [ ] Verify rejection

**Expected Results:**
- Upload rejected
- Error: "Invalid file type"
- Only JPEG/PNG allowed

**Test Case: Oversized File**
- [ ] Attempt upload > 5MB
- [ ] Verify rejection

**Expected Results:**
- Upload rejected
- Error: "File too large (max 5MB)"

**Test Case: EXIF Data Stripping**
- [ ] Upload image with EXIF metadata
- [ ] Download uploaded image
- [ ] Check for EXIF data

**Expected Results:**
- EXIF data removed
- Privacy protected

**Test Case: Base64 Upload (Camera Capture)**
- [ ] Capture image via camera
- [ ] Send base64 encoded data
- [ ] Verify successful upload

**Expected Results:**
- Base64 decoded correctly
- Image saved properly
- Same validation as file upload

**Test Case: Rate Limiting**
- [ ] Upload 5 images rapidly
- [ ] Attempt 6th upload
- [ ] Verify rate limit

**Expected Results:**
- 6th upload blocked
- Limit: 5 uploads per lead per hour

**Test Case: Image Deletion After Reading**
- [ ] Upload image
- [ ] Generate reading
- [ ] Check storage directory
- [ ] Verify image deleted

**Expected Results:**
- Image removed after processing
- Only reading retained
- Storage cleaned up

---

### 6.1.8: Quiz Handler (SM_Quiz_Handler)

**Test Case: Valid Quiz Save**
- [ ] Submit quiz with all 5 questions answered
- [ ] Check database sm_quiz table
- [ ] Verify JSON structure

**Expected Results:**
- Quiz saved successfully
- All answers stored in JSON
- Timestamp recorded

**Test Case: Missing Required Questions**
- [ ] Submit quiz with only 3 questions
- [ ] Verify validation error

**Expected Results:**
- Submission rejected
- Error: "All questions required"

**Test Case: Multi-Select Validation**
- [ ] Submit quiz with multi-select question
- [ ] Verify array structure accepted

**Expected Results:**
- Array values accepted
- Stored correctly in JSON

**Test Case: Free Text Character Limit**
- [ ] Submit quiz with 501+ character free text
- [ ] Verify truncation or rejection

**Expected Results:**
- Text limited to 500 chars
- Error or automatic truncation

**Test Case: Quiz Retrieval**
- [ ] Save quiz for test lead
- [ ] Retrieve quiz by lead_id
- [ ] Verify data matches

**Expected Results:**
- Quiz retrievable
- All answers intact
- JSON structure preserved

---

### 6.1.9: AI Handler (SM_AI_Handler)

**Test Case: Reading Generation - Happy Path**
- [ ] Create complete lead (verified email, image, quiz)
- [ ] Trigger reading generation
- [ ] Wait for response (8-12 seconds)
- [ ] Verify reading returned

**Expected Results:**
- Reading generated successfully
- HTML structure correct
- 300-500 words
- All required sections present:
  - Life Line
  - Heart Line
  - Fate Line
  - Palm shape/mounts
  - Personality insights
  - Quiz-based guidance
  - Teaser for deeper reading

**Test Case: HTML Sanitization**
- [ ] Generate reading
- [ ] Inspect returned HTML
- [ ] Verify only allowed tags present

**Expected Results:**
- Only allowed tags: h2, h3, p, strong, em, ul, ol, li, br
- No `<script>` tags
- No event handlers
- No `<iframe>` or `<style>` tags

**Test Case: Duplicate Reading Prevention**
- [ ] Generate reading for email
- [ ] Attempt second reading for same email
- [ ] Verify rejection

**Expected Results:**
- Second attempt blocked
- Error: "Reading already exists"
- One reading per email enforced

**Test Case: Missing Prerequisites - No Email Verification**
- [ ] Create lead without OTP verification
- [ ] Attempt reading generation
- [ ] Verify blocked

**Expected Results:**
- Generation blocked
- Error: "Email not verified"

**Test Case: Missing Prerequisites - No Quiz**
- [ ] Create verified lead without quiz
- [ ] Attempt reading generation
- [ ] Verify blocked

**Expected Results:**
- Generation blocked
- Error: "Quiz required"

**Test Case: Missing Prerequisites - No Image**
- [ ] Create verified lead without palm image
- [ ] Attempt reading generation
- [ ] Verify blocked

**Expected Results:**
- Generation blocked
- Error: "Palm image required"

**Test Case: OpenAI API Error Handling**
- [ ] Set invalid API key temporarily
- [ ] Attempt reading generation
- [ ] Verify graceful error

**Expected Results:**
- Error caught
- User-friendly message
- Error logged to debug.log
- No fatal PHP errors

**Test Case: Token Usage Tracking**
- [ ] Generate reading
- [ ] Check debug.log
- [ ] Verify token count logged

**Expected Results:**
- Token usage recorded
- Cost estimate logged (if implemented)

---

## Unit 6.2: REST API Integration Testing

### 6.2.1: Endpoint Accessibility

**Test All Endpoints:**
- [ ] POST /soulmirror/v1/lead/create
- [ ] POST /soulmirror/v1/otp/send
- [ ] POST /soulmirror/v1/otp/verify
- [ ] POST /soulmirror/v1/mailerlite/sync
- [ ] POST /soulmirror/v1/image/upload
- [ ] POST /soulmirror/v1/quiz/save
- [ ] POST /soulmirror/v1/reading/generate
- [ ] GET /soulmirror/v1/reading/check
- [ ] POST /soulmirror/v1/nonce/refresh

**Method:** Use browser dev tools, Postman, or curl

**Expected Results:**
- All endpoints respond (not 404)
- Proper error if nonce missing
- CORS headers present

---

### 6.2.2: Nonce Verification

**Test Case: Valid Nonce**
- [ ] Get nonce from smData JavaScript object
- [ ] Make API request with valid nonce
- [ ] Verify success

**Expected Results:**
- Request accepted
- No nonce error

**Test Case: Missing Nonce**
- [ ] Make API request without nonce header
- [ ] Verify rejection

**Expected Results:**
- Request rejected
- Error: "Invalid or missing nonce"
- Status code: 403

**Test Case: Invalid Nonce**
- [ ] Make request with fake nonce
- [ ] Verify rejection

**Expected Results:**
- Request rejected
- Failed attempt logged

**Test Case: Expired Nonce**
- [ ] Wait for nonce expiration (24 hours default)
- [ ] Attempt request with old nonce
- [ ] Verify rejection

**Expected Results:**
- Expired nonce rejected
- User prompted to refresh page

**Test Case: Nonce Refresh**
- [ ] Call /nonce/refresh endpoint
- [ ] Verify new nonce returned
- [ ] Use new nonce in subsequent requests

**Expected Results:**
- New nonce generated
- New nonce works in requests

---

### 6.2.3: Rate Limiting

**Test Case: OTP Send Rate Limit**
- [ ] Send OTP 3 times for same email
- [ ] Attempt 4th send within 1 hour
- [ ] Verify blocked

**Expected Results:**
- 4th attempt blocked
- Error: "Rate limit exceeded"
- Cooldown time shown

**Test Case: Image Upload Rate Limit**
- [ ] Upload 5 images for same lead
- [ ] Attempt 6th upload within 1 hour
- [ ] Verify blocked

**Expected Results:**
- 6th upload blocked
- Rate limit error

**Test Case: Failed Nonce Rate Limit**
- [ ] Make 5 requests with invalid nonces
- [ ] Verify IP cooldown triggered

**Expected Results:**
- After 5 failures, 10-minute IP cooldown
- All requests from IP blocked temporarily

---

### 6.2.4: Input Validation

**Test Case: SQL Injection Attempt**
- [ ] Submit lead with SQL in name: `'; DROP TABLE sm_leads; --`
- [ ] Verify sanitization

**Expected Results:**
- SQL characters escaped
- No database damage
- Attempt logged

**Test Case: XSS Attempt**
- [ ] Submit name with: `<script>alert('xss')</script>`
- [ ] Verify sanitization

**Expected Results:**
- Script tags stripped
- Safe text stored

**Test Case: Invalid Data Types**
- [ ] Submit string for integer field
- [ ] Submit array for string field
- [ ] Verify validation

**Expected Results:**
- Type mismatches rejected
- Clear error messages

---

## Unit 6.3: End-to-End User Flow Testing

### 6.3.1: Complete 12-Step Journey

**Prerequisites:**
- Page with shortcode published
- Valid API keys configured
- Test email accessible

**Flow:**

**Step 1: Welcome Screen**
- [ ] Load page
- [ ] Verify welcome screen displays
- [ ] Check mystical branding/design
- [ ] Click "Begin" or equivalent

**Step 2: Lead Capture**
- [ ] Enter name
- [ ] Enter email
- [ ] Select identity
- [ ] Check GDPR consent checkbox
- [ ] Submit form
- [ ] Verify validation on missing fields
- [ ] Verify validation on invalid email

**Step 3: Email Loading Animation**
- [ ] Observe loading animation
- [ ] Verify smooth transition

**Step 4: Email Verification (OTP Entry)**
- [ ] Check email inbox
- [ ] Verify OTP email received
- [ ] Copy 6-digit code
- [ ] Enter OTP in form
- [ ] Submit
- [ ] Verify success transition

**Test Invalid OTP:**
- [ ] Enter wrong code
- [ ] Verify error message
- [ ] Verify attempt counter shown
- [ ] Test resend OTP button

**Step 5: Palm Photo Capture**
- [ ] Test camera capture (mobile)
  - [ ] Click "Use Camera"
  - [ ] Grant camera permission
  - [ ] Capture palm photo
  - [ ] Verify preview shown
  - [ ] Confirm/retake options work

- [ ] Test file upload (desktop)
  - [ ] Click "Upload Photo"
  - [ ] Select file
  - [ ] Verify preview shown
  - [ ] Confirm upload

**Step 6-10: Quiz Questions**
- [ ] Answer question 1 (energy level)
- [ ] Answer question 2 (life focus)
- [ ] Answer question 3 (element resonance)
- [ ] Answer question 4 (multi-select intentions)
- [ ] Answer question 5 (free text future goals)
- [ ] Verify navigation between questions
- [ ] Verify "back" functionality (if exists)
- [ ] Verify validation on required questions

**Step 11: Result Loading Animation**
- [ ] Observe AI generation loading
- [ ] Verify mystical animation/messaging
- [ ] Wait 8-12 seconds

**Step 12: Final AI Reading Result**
- [ ] Verify reading displays
- [ ] Check HTML formatting correct
- [ ] Verify all sections present:
  - Life Line interpretation
  - Heart Line meaning
  - Fate Line analysis
  - Palm shape/mounts
  - Personality insights
  - Quiz-based guidance
  - Teaser for deeper reading
- [ ] Verify 300-500 word count
- [ ] Check mystical tone
- [ ] Verify no technical jargon
- [ ] Verify reading is personalized (uses name)

**Expected Results:**
- Smooth flow through all 12 steps
- No errors or breaks
- All transitions animated
- Data persists correctly
- Reading is relevant and personalized

---

### 6.3.2: One Reading Per Email Enforcement

**Test Case:**
- [ ] Complete full flow with test@example.com
- [ ] Receive reading
- [ ] Attempt to start new reading with same email
- [ ] Verify blocked or shown existing reading

**Expected Results:**
- Second attempt blocked
- Message: "You already have a reading"
- Option to view existing reading (if implemented)

---

### 6.3.3: GDPR Compliance

**Test Case:**
- [ ] Verify GDPR consent checkbox required
- [ ] Verify Privacy Policy link present
- [ ] Verify Terms link present
- [ ] Check database for consent timestamp
- [ ] Verify palm image deleted after reading

**Expected Results:**
- Cannot proceed without GDPR consent
- Consent timestamp logged
- Links functional
- Image cleanup confirmed

---

## Unit 6.4: Edge Case & Error Testing

### 6.4.1: Browser/Session Edge Cases

**Test Case: Browser Close During OTP Wait**
- [ ] Start flow, enter email
- [ ] Close browser tab
- [ ] Reopen page
- [ ] Attempt to continue

**Expected Results:**
- Session state handled gracefully
- User can restart or continue
- No data corruption

**Test Case: Network Timeout**
- [ ] Disconnect internet mid-flow
- [ ] Attempt form submission
- [ ] Reconnect
- [ ] Retry

**Expected Results:**
- Timeout error displayed
- User can retry
- No data loss

**Test Case: JavaScript Disabled**
- [ ] Disable JavaScript in browser
- [ ] Load page with shortcode

**Expected Results:**
- Fallback message or degraded experience
- Clear instruction to enable JS

---

### 6.4.2: API Failure Scenarios

**Test Case: OpenAI API Down**
- [ ] (Simulate by setting invalid endpoint or key)
- [ ] Attempt reading generation

**Expected Results:**
- Graceful error message
- Retry option
- Error logged
- No fatal PHP error

**Test Case: MailerLite API Down**
- [ ] (Simulate failure)
- [ ] Complete OTP verification

**Expected Results:**
- User flow continues
- MailerLite failure logged
- No user-facing error

---

### 6.4.3: Data Edge Cases

**Test Case: Corrupted Image**
- [ ] Upload corrupted/incomplete image file
- [ ] Verify handling

**Expected Results:**
- Image rejected
- Error: "Invalid image file"

**Test Case: Extremely Long Name**
- [ ] Enter 200+ character name
- [ ] Verify truncation or validation

**Expected Results:**
- Name limited or validated
- No database overflow

**Test Case: Special Characters in Input**
- [ ] Enter name with emojis, accents, special chars
- [ ] Verify handling

**Expected Results:**
- Special chars handled gracefully
- No encoding issues

---

## Unit 6.5: Security Testing

### 6.5.1: Injection Attacks

**Test Case: SQL Injection**
- [ ] Test all text inputs with SQL payloads
- [ ] Verify prepared statements prevent injection

**Payloads to test:**
- `' OR '1'='1`
- `'; DROP TABLE sm_leads; --`
- `admin'--`

**Expected Results:**
- All injection attempts fail
- Data sanitized
- No database damage

**Test Case: XSS (Cross-Site Scripting)**
- [ ] Test all text inputs with script tags
- [ ] Verify escaping on output

**Payloads to test:**
- `<script>alert('XSS')</script>`
- `<img src=x onerror=alert(1)>`
- `javascript:alert(document.cookie)`

**Expected Results:**
- Scripts stripped or escaped
- No execution in browser
- Output sanitized

**Test Case: AI HTML Injection**
- [ ] (If possible, craft prompt to make OpenAI return forbidden HTML)
- [ ] Verify wp_kses sanitization

**Expected Results:**
- Only allowed tags rendered
- Forbidden tags stripped

---

### 6.5.2: Authentication & Authorization

**Test Case: Nonce Bypass Attempt**
- [ ] Make API request without nonce
- [ ] Make request with tampered nonce
- [ ] Verify rejection

**Expected Results:**
- All attempts blocked
- 403 Forbidden status
- Attempts logged

**Test Case: CSRF Attack**
- [ ] Create malicious form on external site
- [ ] Attempt to submit to plugin endpoints
- [ ] Verify nonce protection works

**Expected Results:**
- Cross-site requests blocked
- Nonce validation prevents CSRF

---

### 6.5.3: Data Exposure

**Test Case: API Key Exposure**
- [ ] View page source
- [ ] Check JavaScript variables
- [ ] Verify API keys NOT exposed

**Expected Results:**
- No API keys in frontend code
- Keys only in backend

**Test Case: Direct File Access**
- [ ] Attempt direct URL access to uploaded images
- [ ] Verify .htaccess blocks access

**Expected Results:**
- Direct access denied
- 403 or 404 error

**Test Case: Database Credential Exposure**
- [ ] Check error messages
- [ ] Verify no DB credentials shown

**Expected Results:**
- No credentials in errors
- Generic error messages only

---

### 6.5.4: Rate Limiting & Abuse Prevention

**Test Case: OTP Brute Force**
- [ ] Attempt 100+ OTP verifications rapidly
- [ ] Verify lockout

**Expected Results:**
- Locked after 3 attempts
- IP rate limit triggered
- Attack logged

**Test Case: Email Enumeration**
- [ ] Test lead creation with various emails
- [ ] Verify error messages don't reveal existence

**Expected Results:**
- Consistent error messages
- Cannot determine if email exists

---

## Unit 6.6: Performance Testing

### 6.6.1: Response Time Measurements

Use browser dev tools (Network tab) or API testing tool.

**Targets:**
- [ ] Lead creation: < 500ms
- [ ] OTP send: < 2 seconds
- [ ] OTP verify: < 500ms
- [ ] Image upload: < 1 second
- [ ] Quiz save: < 500ms
- [ ] Reading generate: 8-12 seconds (acceptable)

**Test Method:**
- Measure each endpoint 5 times
- Calculate average response time
- Verify meets targets

**Expected Results:**
- All endpoints within target times
- AI generation 8-12s (acceptable due to OpenAI)

---

### 6.6.2: Concurrent Users

**Test Case: Multiple Simultaneous Users**
- [ ] Open 10 browser tabs/windows
- [ ] Start flow simultaneously in each
- [ ] Verify all complete successfully

**Expected Results:**
- No conflicts
- All sessions independent
- Database handles concurrency

---

### 6.6.3: Database Performance

**Test Case: Query Optimization**
- [ ] Enable WordPress query logging
- [ ] Complete full user flow
- [ ] Review query count and time

**Expected Results:**
- Reasonable query count
- Indexes used effectively
- No N+1 query issues

---

### 6.6.4: Memory Usage

**Test Case: Memory Monitoring**
- [ ] Check PHP memory limit
- [ ] Complete full flow including image upload
- [ ] Monitor memory usage

**Expected Results:**
- No memory limit errors
- Image processing efficient
- Memory usage reasonable

---

## Testing Checklist Summary

### Pre-Testing Setup
- [ ] Plugin activated
- [ ] API keys configured
- [ ] Test page created with shortcode
- [ ] Test email account ready
- [ ] SSL/HTTPS enabled

### Unit 6.1: Component Testing (9 components)
- [ ] Database Handler
- [ ] Settings Handler
- [ ] Logger
- [ ] Lead Handler
- [ ] OTP Handler
- [ ] MailerLite Handler
- [ ] Image Handler
- [ ] Quiz Handler
- [ ] AI Handler

### Unit 6.2: REST API Testing
- [ ] Endpoint accessibility
- [ ] Nonce verification
- [ ] Rate limiting
- [ ] Input validation

### Unit 6.3: End-to-End Testing
- [ ] Complete 12-step flow
- [ ] One reading per email enforcement
- [ ] GDPR compliance

### Unit 6.4: Edge Case Testing
- [ ] Browser/session edge cases
- [ ] API failure scenarios
- [ ] Data edge cases

### Unit 6.5: Security Testing
- [ ] SQL injection prevention
- [ ] XSS prevention
- [ ] Authentication/authorization
- [ ] Data exposure prevention
- [ ] Abuse prevention

### Unit 6.6: Performance Testing
- [ ] Response time measurements
- [ ] Concurrent users
- [ ] Database performance
- [ ] Memory usage

---

## Issue Tracking Template

Use this template to document any issues found during testing:

```
**Issue ID:** TEST-XXX
**Severity:** Critical / High / Medium / Low
**Unit:** 6.X.X
**Component:** [Component Name]
**Description:** [What went wrong]
**Steps to Reproduce:**
1. Step 1
2. Step 2
3. Step 3
**Expected Result:** [What should happen]
**Actual Result:** [What actually happened]
**Screenshots/Logs:** [Attach if relevant]
**Status:** Open / In Progress / Fixed / Won't Fix
```

---

## Post-Testing Actions

After completing all tests:

1. **Document Results**
   - Record pass/fail for each test case
   - Document any issues found
   - Prioritize fixes by severity

2. **Update Progress**
   - Mark completed units in progress.md
   - Update overall progress percentage
   - Add any new notes or constraints

3. **Address Failures**
   - Fix critical and high-severity issues
   - Retest after fixes
   - Document workarounds for known issues

4. **Prepare for Phase 7**
   - Ensure all critical tests pass
   - Document any limitations
   - Ready for documentation phase

---

**Good luck with testing! Remember:**
- Test thoroughly - quality matters
- Document everything
- Security first
- User experience is paramount
- When in doubt, refer to requirements.md

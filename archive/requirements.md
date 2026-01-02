# WordPress Plugin Requirements

**Generated:** 2025-12-11 21:30:00
**Source Documents:** business-requirements.md (v2.0) + technical-requirements.md (v2.0)
**UI/UX Source of Truth:** assets/styles.css + assets/script.js

---

## Plugin Header

- **Plugin Name:** Mystic Palm Reading AI
- **Description:** An immersive AI-powered palm reading experience that captures leads, verifies emails, and delivers personalized mystical insights using OpenAI Vision and GPT-4o.
- **Version:** 1.0.0
- **Author:** SoulMirror
- **Author URI:** (To be provided)
- **Text Domain:** mystic-palm-reading
- **Requires at least:** WordPress 5.8
- **Requires PHP:** 7.4
- **License:** Proprietary

---

## Naming Convention

**Prefix:** `SM_`

All functions, variables, constants, classes, and custom database tables MUST use this prefix to avoid conflicts with other plugins.

**Examples:**
- Functions: `SM_send_otp()`, `SM_generate_reading()`
- Classes: `SM_REST_Controller`, `SM_Lead_Handler`
- Constants: `SM_VERSION`, `SM_PLUGIN_DIR`
- Database tables: `sm_leads`, `sm_otps`, `sm_quiz`, `sm_readings`, `sm_logs`

---

## Core Functionality

### Main Purpose

Deliver a fully functional, immersive palm-reading lead capture experience for SoulMirror. The system captures user details securely, verifies emails with real OTP pipeline, integrates with MailerLite for marketing automation, captures and validates palm images, collects quiz responses, and generates medium-length (300-500 words) AI-driven palm readings using OpenAI Vision + GPT-4o.

The experience enforces one free reading per email, persists all required data, follows strict GDPR and security rules, and is architected to be extensible for future SoulMirror modules (Aura, Purpose, Love, etc.).

### Target Users

**End Users:**
- Individuals seeking mystical insights and self-discovery
- People interested in palm reading and spiritual guidance
- Users curious about AI-powered personalized experiences

**Site Administrators:**
- WordPress site owners managing the SoulMirror experience
- Marketing teams building email lists through MailerLite
- Admins monitoring OTP delivery, API usage, and user flows

### Key Features

1. **12-Step Interactive Experience**
   - Welcome Screen
   - Lead Capture (name, email, identity, GDPR consent)
   - Email Loading Animation
   - Email Verification (OTP entry)
   - Palm Photo Capture/Upload
   - Quiz Questions (5 questions: energy, focus, element, intentions, future goals)
   - Result Loading Animation
   - Final AI Reading Display

2. **Secure Email Verification (OTP)**
   - 6-digit numeric codes with 10-minute expiration
   - Maximum 3 attempts with lockout protection
   - Resend cooldown of 10 minutes
   - IP rate limiting for abuse prevention

3. **MailerLite Integration**
   - Automatic subscriber upsert after email verification
   - Group assignment for segmentation
   - Graceful error handling that doesn't block user flow

4. **AI-Powered Palm Reading**
   - OpenAI GPT-4o Vision + Text analysis
   - Structured 300-500 word readings in HTML format
   - Personalized based on palm image + quiz responses
   - Mystical, warm, welcoming tone

5. **One Free Reading Enforcement**
   - Server-side validation per email address
   - Prevents multiple free readings from same email

6. **GDPR Compliance**
   - Explicit consent checkbox
   - Consent timestamp logging
   - Privacy policy and terms links

7. **Debug Logging System**
   - All critical events logged to `debug.log` in plugin root
   - OTP generation/verification, MailerLite sync, OpenAI calls
   - Error tracking with timestamps and context
   - Suspicious activity monitoring

**Priority Feature:** Complete end-to-end OTP email verification flow with MailerLite integration is the foundation for the entire experience.

### User Workflow

1. User lands on welcome screen
2. Enters name, email, identity, and grants GDPR consent
3. System creates pending lead record
4. User sees email loading animation
5. Backend generates and emails 6-digit OTP
6. User enters OTP from their email
7. System validates OTP, marks email as confirmed
8. Backend syncs user to MailerLite (non-blocking)
9. User captures or uploads palm photo
10. User answers 5 quiz questions about their energy, focus, spiritual intentions
11. User sees loading animation while AI analyzes palm + quiz data
12. Backend calls OpenAI Vision + GPT-4o with structured prompt
13. System generates and sanitizes HTML reading
14. User sees personalized palm reading with mystical insights
15. Reading is stored for future retrieval
16. User cannot get another free reading with same email

---

## Technical Requirements

### System Architecture

**Frontend Layer (Existing - DO NOT MODIFY):**
- Pure JavaScript-driven UI/UX in `assets/script.js`
- CSS styling in `assets/styles.css`
- Renders 12 steps dynamically
- Sends data to backend via REST API
- Receives sanitized HTML for AI reading display

**Backend Layer (New in MVP1):**
WordPress plugin with modular architecture:
- REST API router (`/wp-json/soulmirror/v1/`)
- Lead Manager
- OTP Manager
- MailerLite Integration Manager
- Palm Image Manager
- Quiz Manager
- AI Reading Manager (OpenAI)
- Sanitization & Validation Layer
- Logging Manager (debug.log in root)
- Settings Manager (Admin UI)

**External Services:**
- MailerLite v3 API (subscriber management)
- OpenAI GPT-4o Vision + Text API (reading generation)

### Plugin File Structure

```
/mystic-palm-reading/
    |-- assets/
    |     |-- script.js (source of truth for UX flow)
    |     |-- styles.css (source of truth for UI design)
    |
    |-- includes/
    |     |-- class-sm-rest-controller.php
    |     |-- class-sm-lead-handler.php
    |     |-- class-sm-otp-handler.php
    |     |-- class-sm-mailerlite-handler.php
    |     |-- class-sm-image-handler.php
    |     |-- class-sm-quiz-handler.php
    |     |-- class-sm-ai-handler.php
    |     |-- class-sm-logger.php
    |     |-- class-sm-settings.php
    |
    |-- templates/
    |     |-- container.php
    |
    |-- mystic-palm-reading.php (main plugin file)
    |-- debug.log (created automatically for debugging)
```

### Admin Interface

**Required:** Yes

**Admin Settings Page Location:** WordPress Admin > SoulMirror Settings

**Settings Required:**
1. **API Configuration**
   - OpenAI API Key (encrypted storage in wp_options)
   - MailerLite API Key (encrypted storage in wp_options)
   - MailerLite Group ID (for subscriber assignment)

2. **OTP Configuration**
   - OTP Expiration Time (default: 10 minutes)
   - Max OTP Attempts (default: 3)
   - Resend Cooldown (default: 10 minutes)

3. **Debug Settings**
   - Enable/Disable Debug Logging
   - View Debug Log (last 100 entries)
   - Clear Debug Log button

4. **System Status**
   - Database tables status
   - API connection tests
   - Recent activity summary

All settings saved using WordPress Settings API for security.

### Database

**Custom Tables Required:** Yes

#### Table 1: `sm_leads`
Stores all user lead information.

| Field | Type | Description |
|-------|------|-------------|
| id | UUID (PRIMARY) | Unique lead identifier |
| name | VARCHAR(255) | User's name |
| email | VARCHAR(255) UNIQUE | User's email (unique index) |
| identity | VARCHAR(50) | Gender/identity selection |
| gdpr | TINYINT(1) | GDPR consent flag (1 = yes) |
| gdpr_timestamp | DATETIME | When consent was given |
| email_confirmed | TINYINT(1) | Email verification status |
| created_at | DATETIME | Record creation timestamp |
| updated_at | DATETIME | Last update timestamp |

#### Table 2: `sm_otps`
Stores OTP verification codes and attempt tracking.

| Field | Type | Description |
|-------|------|-------------|
| id | UUID (PRIMARY) | Unique OTP record ID |
| lead_id | UUID (FK) | Foreign key to sm_leads |
| otp_hash | VARCHAR(255) | Hashed OTP code |
| expires_at | DATETIME | OTP expiration time |
| attempts | INT(11) | Failed attempt counter |
| resend_available | DATETIME | When resend is allowed |
| created_at | DATETIME | OTP generation time |

#### Table 3: `sm_quiz`
Stores quiz responses linked to leads.

| Field | Type | Description |
|-------|------|-------------|
| id | UUID (PRIMARY) | Unique quiz record ID |
| lead_id | UUID (FK) | Foreign key to sm_leads |
| answers_json | LONGTEXT | JSON object of all quiz answers |
| completed_at | DATETIME | Quiz completion timestamp |

#### Table 4: `sm_readings`
Stores generated AI palm readings.

| Field | Type | Description |
|-------|------|-------------|
| id | UUID (PRIMARY) | Unique reading ID |
| lead_id | UUID (FK) | Foreign key to sm_leads |
| reading_html | LONGTEXT | Sanitized HTML of the reading |
| generated_at | DATETIME | Reading generation timestamp |

#### Table 5: `sm_logs`
Stores debug and event logs (auto-purge after 30 days).

| Field | Type | Description |
|-------|------|-------------|
| id | UUID (PRIMARY) | Unique log entry ID |
| event_type | VARCHAR(100) | Event category (otp_sent, ai_generated, etc.) |
| status | VARCHAR(50) | success, error, warning |
| message | TEXT | Human-readable log message |
| meta | JSON | Additional context data |
| created_at | DATETIME | Log entry timestamp |

**Database Maintenance:**
- Logs older than 30 days are automatically purged
- Orphaned OTP records cleaned up daily

### Frontend Display

**Has Frontend Output:** Yes

**Display Method:** Shortcode

**Shortcode:** `[soulmirror_palm_reading]`

The shortcode renders a container that loads the existing JavaScript experience. The UI/UX is completely controlled by `assets/script.js` and `assets/styles.css` - the backend simply provides the container and REST API endpoints.

### REST API Endpoints

**Base URL:** `/wp-json/soulmirror/v1/`

**All endpoints require:**
- WordPress nonce verification
- Rate limiting
- Input sanitization
- Output escaping

| Endpoint | Method | Purpose | Request Data | Response Data |
|----------|--------|---------|--------------|---------------|
| `/lead/create` | POST | Create pending lead | name, email, identity, gdpr | lead_id, email_status |
| `/otp/send` | POST | Generate and email OTP | lead_id, email | success, message |
| `/otp/verify` | POST | Validate OTP code | lead_id, otp | success, message |
| `/mailerlite/sync` | POST | Upsert to MailerLite | lead_id | success, message |
| `/image/upload` | POST | Upload palm image | lead_id, image (base64/file) | success, image_url |
| `/quiz/save` | POST | Save quiz responses | lead_id, answers (JSON) | success, message |
| `/reading/generate` | POST | Generate AI reading | lead_id | success, reading_html |
| `/reading/check` | GET | Check if reading exists | email | exists, can_generate |

### WordPress Hooks/Filters

**Actions Used:**
- `rest_api_init` - Register REST endpoints
- `admin_menu` - Add admin settings page
- `admin_init` - Register settings
- `wp_enqueue_scripts` - Load frontend assets
- `plugins_loaded` - Initialize plugin classes
- `wp_mail_failed` - Log email sending failures

**Filters Used:**
- `wp_mail_from` - Customize OTP email sender
- `wp_mail_from_name` - Customize OTP email sender name
- `upload_mimes` - Validate palm image types
- `wp_kses_allowed_html` - Define allowed HTML for AI readings

**Custom Hooks Created:**
- `sm_before_lead_create` - Fire before lead creation
- `sm_after_lead_create` - Fire after lead creation
- `sm_otp_verified` - Fire after successful OTP verification
- `sm_reading_generated` - Fire after AI reading generation
- `sm_mailerlite_synced` - Fire after MailerLite sync

### External Integrations

#### MailerLite v3 API

**Purpose:** Subscriber management and email marketing automation

**API Endpoints Used:**
- `POST /api/subscribers` - Create or update subscriber
- `POST /api/subscribers/{id}/groups/{groupId}` - Assign to group

**Authentication:** Bearer token (stored encrypted in wp_options)

**Integration Flow:**
1. Triggered automatically after OTP verification success
2. Upsert subscriber with name, email
3. Assign to configured Group ID
4. If previously unsubscribed, resubscribe
5. Store lead_id in "company" field for tracking

**Error Handling:**
- Log all failures to debug.log
- Do NOT block user progression if MailerLite fails
- Queue failed syncs for retry (future enhancement)

**API Credential Storage:** WordPress Settings API (encrypted in wp_options as `sm_mailerlite_api_key` and `sm_mailerlite_group_id`)

#### OpenAI GPT-4o Vision + Text API

**Purpose:** Generate personalized palm readings

**Model:** `gpt-4o` with vision capabilities

**API Endpoint:** `https://api.openai.com/v1/chat/completions`

**Authentication:** Bearer token (stored encrypted in wp_options)

**Integration Flow:**
1. Triggered when user completes quiz (step 11)
2. Upload palm image to OpenAI (or provide URL)
3. Send structured prompt with:
   - Palm image analysis request
   - User demographics (name, identity)
   - Quiz responses (energy, focus, element, intentions, goals)
   - Output requirements (HTML structure, word count, sections)
4. Receive HTML response
5. Sanitize HTML using wp_kses with limited tag whitelist
6. Store in sm_readings table
7. Return to frontend for display

**Prompt Structure:**
```
You are a mystical palm reader with deep intuitive insight. Analyze the palm image and provide a personalized reading for {name}.

Based on the palm image, create a warm, mystical palm reading that includes:

1. **Life Line Interpretation** - What their life line reveals
2. **Heart Line Meaning** - Insights about emotions and relationships
3. **Fate Line Analysis** - Path and purpose indicators
4. **Palm Shape & Mounts** - Energetic qualities and character
5. **Personality Insights** - Emotional and spiritual nature
6. **Personalized Guidance** - Based on their quiz responses:
   - Current energy: {quiz.energy}
   - Life focus: {quiz.focus}
   - Element resonance: {quiz.element}
   - Spiritual intentions: {quiz.intentions}
   - Future goals: {quiz.goals}
7. **Invitation for Deeper Reading** - Soft teaser (not salesy)

Requirements:
- 300-500 words
- Warm, mystical, welcoming tone
- Output as clean HTML using only: <h2>, <p>, <strong>, <em>, <ul>, <li>
- No medical or legal predictions
- No deterministic promises
- Reference specific palm features you observe
- Connect insights to their quiz responses
```

**Error Handling:**
- Log all API failures to debug.log
- Return user-friendly error message
- Offer retry option
- Track failed attempts for monitoring

**API Credential Storage:** WordPress Settings API (encrypted in wp_options as `sm_openai_api_key`)

### User Permissions

**Frontend Access:** Public (no authentication required)

**Admin Access:**
- Settings page: `manage_options` capability (Administrators only)
- View logs: `manage_options` capability
- Debug controls: `manage_options` capability

**Data Access:**
- Users can only access their own readings (via email verification)
- No public listing of leads or readings
- Admin can view aggregate data and logs only

### Input Validation

**Server-Side Validation Rules:**

1. **Lead Creation:**
   - Name: Required, 2-100 characters, sanitize_text_field
   - Email: Required, valid email format, sanitize_email
   - Identity: Required, must be from allowed list
   - GDPR: Required, must be true/1

2. **OTP:**
   - Code: Required, exactly 6 digits, numeric only
   - Lead ID: Required, must exist in database

3. **Palm Image:**
   - File type: JPEG or PNG only
   - File size: Maximum 5MB
   - Image validation: Check for corruption
   - EXIF stripping: Remove metadata for privacy

4. **Quiz Answers:**
   - Must include all required questions
   - Multi-select: Array validation
   - Free text: Maximum 500 characters
   - JSON structure validation

5. **Email Validation:**
   - Format: RFC 5322 compliant
   - DNS MX record check (optional enhancement)
   - Disposable email detection (optional enhancement)

**Error Messages:**
- "Please enter a valid email address"
- "Please complete all required fields"
- "Invalid verification code. Please try again"
- "Image file too large. Maximum 5MB"
- "Invalid file type. Please upload JPEG or PNG"

---

## Security Requirements

### Data Sanitization & Validation

**Input Sanitization Functions:**
- `sanitize_text_field()` - All text inputs
- `sanitize_email()` - Email addresses
- `absint()` - Integer values
- `sanitize_file_name()` - Uploaded file names
- `wp_kses()` - HTML content (AI readings)

**Output Escaping Functions:**
- `esc_html()` - Plain text output
- `esc_attr()` - HTML attributes
- `esc_url()` - URLs
- `wp_kses_post()` - Limited HTML output

### CSRF Protection

- All REST endpoints verify WordPress nonces
- Nonces embedded in frontend JavaScript
- Nonce validation on every API call
- Failed nonce attempts logged to debug.log

### Rate Limiting

**OTP Endpoints:**
- `/otp/send`: Maximum 3 requests per email per hour
- `/otp/verify`: Maximum 3 attempts per OTP
- IP-based cooldown: 10 minutes after 5 failed attempts

**Image Upload:**
- Maximum 5 uploads per lead per hour

**Reading Generation:**
- Maximum 1 request per email (enforced by business logic)

### API Key Security

- OpenAI and MailerLite keys stored encrypted in wp_options
- Never exposed to frontend JavaScript
- Never included in REST responses
- Access restricted to `manage_options` capability

### Abuse Prevention

- Email enumeration prevention (consistent error messages)
- OTP brute-force lockout after 3 attempts
- IP-based rate limiting on all endpoints
- Suspicious activity logging
- CAPTCHA consideration for future enhancement

### File Upload Security

- Validate MIME type server-side
- Strip EXIF metadata from images
- Store in non-public temporary directory
- Delete after processing
- Prevent direct URL access

### HTML Sanitization for AI Content

**Allowed HTML Tags:**
```php
'h2', 'h3', 'p', 'strong', 'em', 'ul', 'ol', 'li', 'br'
```

**Forbidden:**
- `<script>` tags
- `<iframe>` tags
- Event handlers (onclick, onerror, etc.)
- `<style>` tags
- External resource loading

---

## Performance Considerations

### Frontend Optimization

- Existing JavaScript and CSS are optimized (source of truth)
- Loading animations compensate for backend latency
- Progressive step rendering

### Backend Optimization

**Expected Response Times:**
- Lead creation: < 500ms
- OTP send: < 2 seconds
- OTP verify: < 500ms
- Image upload: < 1 second
- Quiz save: < 500ms
- AI reading generation: 8-12 seconds (acceptable due to loading animation)

**Database Optimization:**
- Indexes on email fields (sm_leads.email)
- Foreign key indexes
- Regular cleanup of expired OTPs
- Log purging after 30 days

**Caching Strategy (Future):**
- Not required for MVP1
- Could cache MailerLite responses
- Could cache AI readings for re-delivery

---

## Compatibility

**WordPress Version:** 5.8 or higher
**PHP Version:** 7.4 or higher
**MySQL Version:** 5.7 or higher
**Required PHP Extensions:** cURL, JSON, mbstring, GD (for image processing)

**Browser Compatibility:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers with camera API support

**HTTPS:** Strongly recommended for camera access and security

---

## Dependencies

**WordPress Plugins:** None required (standalone)

**PHP Libraries:** None (uses WordPress core functions and wp_remote_* for API calls)

**External Services (Required):**
- OpenAI API account with GPT-4o access
- MailerLite account with v3 API access

**WordPress Features Used:**
- REST API
- Settings API
- wp_mail (or SMTP plugin for better deliverability)
- WP Filesystem API
- Shortcode API

---

## Internationalization

**Translation Ready:** Yes

**Text Domain:** `mystic-palm-reading`

**Translatable Strings:**
- All user-facing messages
- Admin interface labels
- Error messages
- Email templates (OTP)

**Translation Files Location:** `/languages/`

**POT File:** Generated from source code using wp-cli or Poedit

**RTL Support:** CSS prepared for RTL languages (future enhancement)

---

## Debug Logging Requirements

### Log File Location

**File:** `debug.log` in plugin root directory
**Path:** `/wp-content/plugins/mystic-palm-reading/debug.log`

### Logged Events

1. **OTP Operations**
   - OTP generation (code hash, expiration, lead_id)
   - OTP email sent (success/failure, recipient)
   - OTP verification attempts (success/failure, remaining attempts)
   - OTP expiration cleanup

2. **MailerLite Integration**
   - Sync triggered (lead_id, email)
   - API request sent (endpoint, method)
   - API response received (status code, message)
   - Subscriber created/updated
   - Group assignment result
   - Failures and retry attempts

3. **OpenAI Integration**
   - Reading generation triggered (lead_id)
   - API request sent (model, prompt length)
   - API response received (tokens used, cost estimate)
   - Reading saved to database
   - Failures and error messages

4. **Image Processing**
   - Upload received (file size, type)
   - Validation result
   - Storage location
   - Deletion after processing

5. **Security Events**
   - Failed nonce verification
   - Rate limit exceeded
   - Suspicious activity detected
   - Invalid OTP attempts
   - Email enumeration attempts

6. **Database Operations**
   - Table creation/updates
   - Migration results
   - Query errors

### Log Entry Format

```
[2025-12-11 21:30:45] [OTP_SENT] SUCCESS: OTP sent to user@example.com (Lead ID: abc-123) | Expires: 2025-12-11 21:40:45
[2025-12-11 21:31:12] [OTP_VERIFY] FAILURE: Invalid OTP for lead abc-123 (Attempt 1/3)
[2025-12-11 21:35:22] [MAILERLITE] SUCCESS: Subscriber synced - user@example.com added to group 12345
[2025-12-11 21:36:01] [OPENAI] SUCCESS: Reading generated for lead abc-123 (485 words, 1250 tokens)
[2025-12-11 21:37:15] [SECURITY] WARNING: Rate limit exceeded for IP 192.168.1.1 on /otp/send
```

### Log Management

- **Maximum file size:** 10MB (rotate when exceeded)
- **Rotation:** Create debug-YYYY-MM-DD.log and start fresh
- **Retention:** Keep last 7 rotated logs
- **Admin access:** View last 100 lines in admin dashboard
- **Manual clear:** Admin button to truncate log file
- **Sensitive data:** Never log full OTP codes, API keys, or passwords (use hashes/truncated values)

### Debug Levels

**CRITICAL:** System failures that break functionality
**ERROR:** Operation failures that need attention
**WARNING:** Issues that don't break flow but need monitoring
**INFO:** Normal operations and successful events
**DEBUG:** Detailed diagnostic information

### Toggle Debug Logging

Admin can enable/disable verbose logging:
- **Production:** Only log CRITICAL, ERROR, WARNING
- **Debug Mode:** Log all levels including INFO and DEBUG

---

## Extensibility for Future Modules

### MVP1 Architecture Constraints

**Current State (MVP1):**
- 12-step flow hardcoded in `assets/script.js`
- Palm reading specific prompts and logic
- Single shortcode experience

**Future Requirements (MVP2+):**
- JSON-defined flow engine stored in WordPress admin
- Multiple modules: Aura Reading, Purpose Discovery, Love Insights
- Shared backend services across modules
- Dynamic step configuration per module

### Backend Extensibility Requirements

**Service Layer Separation:**

All backend handlers (OTP, MailerLite, Image, AI) must be:
- Module-agnostic (don't hardcode "palm reading" logic)
- Reusable across different reading types
- Configurable via parameters
- Extensible through WordPress hooks

**Example Extensibility Points:**

1. **AI Handler:**
   - Accept dynamic prompts via settings
   - Support different OpenAI models
   - Allow prompt templates per module

2. **Quiz Handler:**
   - Dynamic question sets from JSON
   - Flexible answer validation
   - Module-specific quiz structures

3. **Reading Generator:**
   - Template-based prompt system
   - Module-specific output formatters
   - Swappable AI providers

**Data Model Extensibility:**

- `sm_leads` table remains shared across modules
- Add `module_type` field in future (palm, aura, purpose, love)
- Readings table could have `reading_type` field
- Quiz table already uses JSON for flexible structures

---

## Additional Notes

### Critical UX/UI Constraints

**NON-NEGOTIABLE RULES:**

1. The visual experience, spacing, interaction flow, and step progression are defined entirely in `assets/styles.css` and `assets/script.js`
2. If any requirement conflicts with UI/UX, **the UI prevails**
3. Backend architecture must adapt to the UI - not the other way around
4. The 12-step sequence is authoritative and MUST NOT be altered
5. Transitions, animations, button behavior, progress bar updates, and validation messages must remain consistent with the existing UI implementation

**Reference Files:**
- Keep original `script.js` and `styles.css` as backups during development
- Any backend error must map to UI-compatible messaging
- Loading states and animations are already implemented - backend must respect these

### One Free Reading Enforcement

**Server-Side Business Logic:**

Before generating a reading:
1. Check if `sm_readings` table contains entry for the email
2. If reading exists for this email → Return error message
3. Error response should trigger UI to display upgrade/pricing message
4. Email-based checking prevents workarounds

**Future Enhancement:**
- Allow purchases for additional readings
- Track reading credits per user
- Implement payment gateway integration

### GDPR and Privacy

**Data Retention:**
- Leads retained indefinitely for reading retrieval
- Consent timestamp permanently stored
- Palm images deleted after reading generation (not retained)
- Logs purged after 30 days

**User Rights:**
- Right to access: User can request their data
- Right to deletion: Admin can delete lead + all associated records
- Right to portability: Data export functionality (future enhancement)

**Privacy Policy Must State:**
- What data is collected (name, email, identity, palm image, quiz responses)
- How it's used (reading generation, email marketing via MailerLite)
- How long it's stored (indefinitely for leads, 30 days for logs, images deleted immediately)
- Third parties involved (OpenAI, MailerLite)
- User rights under GDPR

### Email Deliverability

**OTP Email Requirements:**

**Subject Line:** "Your SoulMirror Verification Code"

**Email Body Template:**
```
Hello {name},

Welcome to SoulMirror!

Your verification code is: {OTP_CODE}

This code will expire in 10 minutes.

If you didn't request this code, please ignore this email.

With cosmic light,
The SoulMirror Team
```

**Sender Configuration:**
- From Name: SoulMirror
- From Email: noreply@{site-domain} (configurable)
- Reply-To: support@{site-domain} (configurable)

**Deliverability Best Practices:**
- Use SMTP plugin for better delivery rates (WP Mail SMTP recommended)
- SPF/DKIM/DMARC records configured for domain
- Avoid spam trigger words
- Plain text + HTML versions

### Error Handling Philosophy

**User-Facing Errors:**
- Must be warm, friendly, non-technical
- Should offer next steps or retry options
- Never expose technical details or stack traces

**Examples:**
- ❌ "Database query failed on line 247"
- ✅ "We couldn't process your request. Please try again."

**Logged Errors:**
- Can be technical and detailed
- Include stack traces, request data (sanitized)
- Timestamp and context for debugging

### Testing Requirements

**Manual Testing Checklist:**

1. Complete full 12-step flow successfully
2. Test OTP expiration (wait 10 minutes)
3. Test OTP invalid attempts (3 failures)
4. Test image upload with oversized file
5. Test image upload with wrong file type
6. Test duplicate email (second reading attempt)
7. Test MailerLite sync (verify subscriber appears)
8. Test AI reading generation (verify HTML structure)
9. Test admin settings save/load
10. Test debug log viewing and clearing

**Edge Cases:**

- User closes browser during OTP wait
- User submits form with JavaScript disabled
- OpenAI API returns error
- MailerLite API returns error
- Image upload corrupted file
- Quiz submitted with missing answers
- Network timeout during AI generation

### Assumptions

1. Admin will provide OpenAI and MailerLite API keys before testing
2. All backend services will be built in PHP using WordPress standards
3. Hosting environment supports HTTPS (recommended for camera access)
4. SMTP configuration is admin's responsibility (plugin or server-level)
5. Caching and CDN optimizations are out of scope for MVP1
6. AI readings are ephemeral and not intended for long-term archival beyond user retrieval
7. UI is stable, complete, and authoritative - no frontend development required for MVP1
8. WordPress site is already configured with permalink structure supporting REST API

---

## Acceptance Criteria

### MVP1 is complete when:

1. ✅ User can complete all 12 steps without UI errors or visual breaks
2. ✅ OTP generation, email delivery, and validation work end-to-end in real-world testing
3. ✅ MailerLite upsert succeeds OR gracefully logs failure without blocking user
4. ✅ Palm photo capture (camera) and upload (file) both function correctly
5. ✅ All 5 quiz responses are saved and passed to AI prompt
6. ✅ AI reading is generated with accurate structure, HTML formatting, and 300-500 word count
7. ✅ One free reading per email is enforced server-side (duplicate attempts blocked)
8. ✅ GDPR consent is captured and logged with timestamp
9. ✅ All errors are gracefully handled with user-friendly messages
10. ✅ Architecture is modular and extensible for future JSON-based flow engine
11. ✅ Debug logging captures all critical events to `debug.log` in plugin root
12. ✅ Admin settings page allows configuration of API keys and OTP settings
13. ✅ Security requirements implemented (nonces, sanitization, rate limiting)
14. ✅ Database tables created automatically on plugin activation
15. ✅ Shortcode `[soulmirror_palm_reading]` renders experience correctly

---

## Success Metrics

**Technical Success:**
- 99%+ OTP delivery rate
- 95%+ AI reading generation success rate
- < 1% error rate on API endpoints
- Zero security vulnerabilities

**User Experience:**
- < 2 second page load
- < 15 second total experience completion (excluding AI wait)
- Zero UI breaking errors
- Smooth animations and transitions

**Business Success:**
- Email capture and verification working 100%
- MailerLite integration syncing subscribers
- One free reading enforced preventing abuse
- Debug logs providing actionable troubleshooting data

---

**End of Requirements Document**
**Mystic Palm Reading AI - WordPress Plugin Requirements (MVP1)**
**Version 1.0.0**

# **technical-requirements.md**  
**SoulMirror – Palm Reading Experience**  
**Technical Requirements Document (TRD) – MVP1 (v2.0)**  
**Source of Truth: business-requirements.md + UI/UX (styles.css + script.js)**

---

# **1. Technical Objectives**

The technical implementation must:

- Support the **12-step UI flow exactly as implemented in script.js**  
- Provide secure backend logic (OTP, OpenAI, MailerLite, persistence)  
- Integrate seamlessly with WordPress (shortcodes, REST endpoints, admin settings)  
- Enforce business logic rules (one free reading per email, GDPR validation, etc.)  
- Be fully modular to support future JSON-driven UI flows  
- Ensure security, performance, and extensibility  

---

# **2. System Architecture Overview**

### 2.1 Layers

1. **Frontend Layer (Existing)**  
   - Pure JS-driven UI/UX  
   - Renders steps dynamically  
   - Sends data to backend via REST API  
   - Receives sanitized HTML for the AI reading  

2. **Backend Layer (New in MVP1)**  
   - WordPress plugin with these modules:
     - **REST API router** (`/wp-json/soulmirror/v1/`)  
     - **Lead Manager**  
     - **OTP Manager**  
     - **MailerLite Integration Manager**  
     - **Palm Image Manager**  
     - **Quiz Manager**  
     - **AI Reading Manager (OpenAI)**  
     - **Sanitization & Validation Layer**  
     - **Logging Manager**  
     - **Settings Manager (Admin UI)**  

3. **External Services**
   - **MailerLite v3 API**  
   - **OpenAI GPT-4o Vision + Text API**  

---

# **3. WordPress Plugin Structure**

```
/soulmirror-palm-reading/
    |-- assets/
    |     |-- script.js
    |     |-- styles.css
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
    |-- soulmirror-palm-reading.php
```

---

# **4. REST API Specification**

All backend communication must occur through WordPress REST API endpoints under:

```
/wp-json/soulmirror/v1/
```

## 4.1 Endpoint List

| Endpoint | Method | Purpose |
|---------|--------|---------|
| `/lead/create` | POST | Creates pending lead, validates GDPR |
| `/otp/send` | POST | Generates + emails OTP |
| `/otp/verify` | POST | Validates OTP; marks email verified |
| `/mailerlite/sync` | POST | Upserts user into MailerLite |
| `/image/upload` | POST | Validates, stores palm image |
| `/quiz/save` | POST | Validates and stores quiz responses |
| `/reading/generate` | POST | Calls OpenAI → returns HTML reading |
| `/logging/add` | POST | Internal logging utility |

All endpoints require **nonces** and **rate limiting**.

---

# **5. Data Validation Layer**

Every endpoint must validate:

- **Nonce**  
- **Input type** (string, array, base64, image MIME, etc.)  
- **Required fields**  
- **Sanitize everything**  
- **Escape on output**  
- **Reject oversized images**  
- **Reject invalid emails**  
- **Reject malformed JSON**  

If validation fails → return standardized error:

```
{
  "success": false,
  "error_code": "invalid_input",
  "message": "Please check your information and try again."
}
```

---

# **6. Lead Management Requirements**

### 6.1 Lead Creation (`/lead/create`)
- Validate name, email, identity, GDPR consent  
- Check for existing lead  
- Create new record or retrieve existing  
- Cannot mark confirmed at this stage  
- Return: `lead_id`, `email_status`, `exists_before`  

### 6.2 One-Free-Reading Enforcement
Before generating a reading:

- Check if `reading_exists` for email  
- If yes → block AI reading + return banner message  

---

# **7. OTP System Requirements**

### 7.1 Sending OTP (`/otp/send`)
- Generate 6-digit numeric code  
- Store hashed OTP + expiration (10 min)  
- Respect resend cooldown  
- Email using wp_mail or SMTP plugin  

### 7.2 Verifying OTP (`/otp/verify`)
- Compare hashed values  
- Check expiration, attempts, lockouts  
- On success:  
  - Mark lead as `email_confirmed = true`  
  - Trigger MailerLite sync  

### 7.3 Security
- Brute-force protection (max 3 attempts)  
- IP rate limiting  
- Lockout after repeated abuse  

---

# **8. MailerLite Integration Requirements**

### API Requirements
- Use MailerLite **v3 API** with Bearer authentication  
- Endpoints used:
  - `/subscribers` (create/update)  
  - `/subscribers/{id}/groups/{groupId}` (assign group)

### Logic Requirements
- After OTP success → Upsert user  
- Ensure user belongs to correct group  
- If previously unsubscribed → resubscribe  
- Log all success/fail cases  

### Failure Rules
- Do NOT block user flow  
- Save failure details for admin review  

---

# **9. Palm Image Handling Requirements**

### 9.1 Upload Endpoint (`/image/upload`)
- Accept base64 OR multipart/form-data  
- Validate file:
  - JPEG/PNG  
  - ≤ 5 MB  
  - Not empty or corrupt  

### 9.2 Storage
- Save to WP uploads temp folder  
- Delete after OpenAI call unless retention is enabled  

### 9.3 Security
- Use WordPress file system APIs  
- Strip EXIF metadata  
- Sanitize filename  

---

# **10. Quiz Handling Requirements**

### `/quiz/save` endpoint
- Validate quiz structure  
- Ensure all required answers provided  
- Save JSON object  
- Link quiz to `lead_id`  
- Prevent tampering with order or options  

---

# **11. AI Reading Generation Requirements**

### 11.1 Endpoint: `/reading/generate`

Inputs:
- lead_id  
- palm_image_url  
- quiz responses  
- name + identity  

### 11.2 OpenAI Request

Use **GPT-4o Vision + Text**:

- 1st part: Vision analysis of palm  
- 2nd part: Prompt with quiz + demographics  

### 11.3 Prompt Requirements
Prompt must instruct model to:

- Produce **300–500 words**  
- Structured HTML  
- Sections:
  - Life Line  
  - Heart Line  
  - Fate Line  
  - Palm shape & mounts  
  - Personality/emotional insights  
  - Guidance based on quiz  
  - Teaser for deeper reading  
- No medical/legal predictions  
- No deterministic promises  

### 11.4 Output Requirements

Backend must:

- Sanitize HTML  
- Store reading  
- Return reading to UI  

### 11.5 Error Handling
If OpenAI fails:

```
{
  "success": false,
  "error_code": "ai_failure",
  "message": "We couldn't complete your reading. Please try again."
}
```

---

# **12. Database Schema Requirements**

### 12.1 Table: `sm_leads`
| Field | Type |
|-------|------|
| id | UUID |
| name | varchar |
| email | varchar (unique) |
| identity | varchar |
| gdpr | tinyint |
| gdpr_timestamp | datetime |
| email_confirmed | tinyint |
| created_at | datetime |
| updated_at | datetime |

### 12.2 Table: `sm_otps`
| Field | Type |
|-------|------|
| id | UUID |
| lead_id | FK |
| otp_hash | varchar |
| expires_at | datetime |
| attempts | int |
| resend_available | datetime |

### 12.3 Table: `sm_quiz`
| Field | Type |
|-------|------|
| id | UUID |
| lead_id | FK |
| answers_json | longtext |
| completed_at | datetime |

### 12.4 Table: `sm_readings`
| Field | Type |
|-------|------|
| id | UUID |
| lead_id | FK |
| reading_html | longtext |
| generated_at | datetime |

### 12.5 Table: `sm_logs`
| Field | Type |
|-------|------|
| id | UUID |
| event_type | varchar |
| status | varchar |
| message | text |
| meta | json |
| created_at | datetime |

Logs purge after 30 days.

---

# **13. Security Requirements**

### 13.1 Authentication & Nonces
All REST calls require:

- Nonce  
- Lead ID tokens stored securely  
- No unauthenticated write actions  

### 13.2 Sanitization
- Use `sanitize_text_field`, `sanitize_email`, `wp_kses`  
- AI HTML reduced to limited tag whitelist  

### 13.3 Hardening
- Prevent email enumeration  
- Rate limit OTP + image uploads  
- Disable direct access to uploaded images  

### 13.4 API Key Security
- OpenAI + MailerLite keys stored in `wp_options` as encrypted values  
- Never exposed to frontend  

---

# **14. Extensibility Requirements**

Future versions will use a **JSON-defined flow engine**.  
MVP1 backend must be written with separation of concerns so that:

- Steps can be swapped without breaking backend logic  
- Reading generation can use different prompts/modules  
- OTP, MailerLite, and AI handlers remain reusable across modules  
- New modules (Aura, Purpose, Love, etc.) can reuse the entire backend service layer  

---

# **15. Error Handling Requirements**

### General Error Structure:
```
{
  "success": false,
  "error_code": "<machine_code>",
  "message": "<user-friendly message>"
}
```

### Must handle:
- OTP expired  
- OTP invalid  
- MailerLite failure  
- OpenAI failure  
- Image too large  
- Invalid file type  
- Missing inputs  
- Rate limit exceeded  

Errors must never break UI flow.

---

# **16. Logging Requirements**

Log the following:

- OTP generation + verification  
- MailerLite sync attempts  
- OpenAI requests + failures  
- Image upload failures  
- Suspicious activities  

Logs must include meta information but exclude sensitive data like full images.

---

# **17. Performance Requirements**

- OpenAI call must complete within ~8–12 seconds  
- UI loading animations compensate for backend latency  
- Database operations must be lightweight  
- REST API must respond within reasonable time frames  

Caching may be introduced later but is not required for MVP1.

---

# **18. Admin Settings Requirements**

A WordPress admin menu must allow:

- OpenAI API key input  
- MailerLite API key + Group ID  
- OTP expiration settings  
- Log viewer (basic)  
- Toggle debug logging  

Settings saved with WP Settings API.

---

# **19. Acceptance Criteria**

### Technical Completion is achieved when:
- All endpoints function exactly as required  
- OTP flows end-to-end work in real time  
- MailerLite upsert works or logs errors  
- OpenAI reading is generated and displayed  
- Data is persisted correctly  
- UI never breaks due to backend issues  
- Security requirements are implemented  
- Architecture supports future JSON-driven flows  

---

# **End of Document**
**SoulMirror Palm Reading – Technical Requirements (MVP1)**
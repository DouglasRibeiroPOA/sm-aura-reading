# **business-requirements.md**
**SoulMirror – Palm Reading Experience**  
**Business Requirements Document (BRD) – MVP1 (v2.0)**  
**Source of Truth: UI/UX (styles.css + script.js)**  

---

# **1. Executive Summary**

The goal of MVP1 is to deliver a fully functional, immersive palm-reading lead capture experience for SoulMirror. The system must:

- Capture user details securely  
- Verify email with a real OTP pipeline  
- Add or update the user in MailerLite  
- Capture and validate a palm image  
- Collect quiz responses  
- Generate a **medium-length (300–500 words)** AI-driven palm reading using **OpenAI Vision + GPT-4o**  
- Display the reading in the existing UI with no visual changes  
- Enforce **one free reading per email**  
- Persist all required data in the backend  
- Follow strict GDPR and security rules  
- Be extensible for future SoulMirror modules  

The **UI/UX is final and MUST NOT be altered** except to support backend functionality or security.

---

# **2. UX/UI Source of Truth (Non-Negotiable)**

The visual experience, spacing, interaction flow, and step progression are defined entirely in:

- `styles.css`  
- `script.js`

If any BRD requirement conflicts with UI or UX, **the UI prevails**.  
Backend architecture must adapt to the UI — not the other way around.

---

# **3. End-to-End User Flow (Exact 12-Step Sequence)**

MVP1 must follow **exactly** the step sequence implemented in `script.js`:

1. **Welcome Screen**  
2. **Lead Capture** (name, email, identity, GDPR)  
3. **Email Loading Animation**  
4. **Email Verification (OTP entry)**  
5. **Palm Photo Capture / Upload**  
6. **Quiz 1**  
7. **Quiz 2**  
8. **Quiz 3**  
9. **Quiz 4 (multi-select)**  
10. **Quiz 5 (free text)**  
11. **Result Loading Animation**  
12. **Final AI Reading Result**

Transitions, animations, button behavior, progress bar updates, and validation messages must remain consistent with the UI implementation.

---

# **4. Lead Capture Requirements**

### 4.1 Data Collected
- Name  
- Email  
- Gender/Identity  
- GDPR consent checkbox (mandatory)

### 4.2 Business Rules
- All fields required except later free-text question  
- Server-side validation must match UI expectations  
- GDPR consent must be saved with timestamp  
- Email must be validated server-side  
- A “Pending Lead” record is created at this step  

### 4.3 Compliance
- Consent language must match UI text  
- Privacy Policy + Terms links must be present  
- Consent timestamp must be stored  

---

# **5. OTP Verification Requirements**

### 5.1 OTP Generation
- 6-digit numeric code  
- Server-side generation and storage  
- Expiration: **10 minutes**  
- Max attempts: **3**  
- Resend cooldown: **10 minutes**  
- Rate limiting for abuse prevention  
- Tied to the email + lead record  

### 5.2 OTP Delivery
- Email sent via backend (WordPress mail or configured provider)  
- Must match mystical brand tone  
- MailerLite is *not* used for OTP sending  

### 5.3 OTP Validation
- Fully server-side  
- Successful validation → mark email as confirmed  
- Failure increments attempt counter  
- Lock after 3 invalid attempts  

### 5.4 Abuse Prevention
- IP rate limiting  
- Lockout windows  
- Log suspicious behaviors  

---

# **6. MailerLite Requirements**

Triggered immediately after OTP success.

### 6.1 Required Behavior
- Upsert subscriber into MailerLite  
- Add to configured Group ID  
- Store internal Lead ID in “company” field if needed  
- Ensure user ends in correct group (cleanup old groups if required)

### 6.2 Error Handling
- Log failures  
- Do **not** block user progression  
- Avoid duplicate contacts  
- Must use MailerLite v3 API  

---

# **7. Palm Photo Capture Requirements**

### 7.1 User Methods
- Live camera capture  
- Fallback image upload

### 7.2 Backend Requirements
- Accept uploaded or base64 image  
- Validate file size (≤5MB), type (JPEG/PNG), corruption  
- Store temporarily for OpenAI analysis  
- Delete after reading generation unless retention becomes a feature  

### 7.3 User Experience Rules
- JS manages camera lifecycle  
- Backend errors must not break UI  
- Upload option must always be available  

---

# **8. Quiz Requirements**

5 questions must appear exactly as in UI:

1. Energy level  
2. Current life focus  
3. Element resonance  
4. Multi-select spiritual intentions  
5. Free-text future intention  

### Backend Requirements
- Persist all answers  
- Use answers as context for OpenAI prompt  
- Validate required questions server-side  
- Prevent tampering with ordering or content  

---

# **9. AI Reading Requirements (OpenAI Vision + GPT-4o)**

### 9.1 Workflow
- Backend sends:
  - Palm image  
  - Lead data (name, identity)  
  - Quiz responses  
- Use **GPT-4o** with Vision and Text capabilities  
- Return structured **HTML** safe for rendering  

### 9.2 Reading Length
- **300–500 words**

### 9.3 Required Sections
1. **Life Line interpretation**  
2. **Heart Line meaning**  
3. **Fate Line meaning**  
4. **Personality + emotional insights**  
5. **Palm shape / mounts / energetic analysis**  
6. **Guidance tied to quiz responses**  
7. **Soft teaser toward deeper/full reading**  

### 9.4 Prompt Requirements
- Speak in mystical, warm tone  
- Use simple clean HTML (p, h2, ul/li, strong/em)  
- No medical, legal, deterministic predictions  
- No technical jargon  
- Must reference palm details  

### 9.5 Output Handling
- Store reading HTML server-side  
- Sanitize HTML before display  
- Return to UI for rendering  

---

# **10. Data Model Requirements**

### 10.1 Lead Table
- UUID  
- Name  
- Email  
- Identity  
- GDPR consent (boolean)  
- Consent timestamp  
- Email confirmed (boolean)  
- Created / Updated timestamps  

### 10.2 OTP Table
- Lead ID  
- OTP code  
- Expiration timestamp  
- Attempt count  
- Resend available timestamp  

### 10.3 Quiz Table
- Lead ID  
- JSON answers  
- Completed timestamp  

### 10.4 Reading Table
- Lead ID  
- Reading HTML  
- Generated timestamp  

### 10.5 Logs Table
- Event type  
- Status  
- Message  
- Timestamp  
- Meta JSON  

Logs expire after 30 days.

---

# **11. Security Requirements**

- Nonces for all REST/AJAX calls  
- Input sanitization  
- Output escaping  
- HTML sanitization for AI results  
- CSRF protection  
- Secure API key storage (WP Settings API)  
- Enforce HTTPS for camera when available  
- Prevent email enumeration  
- Rate limit high-risk actions  

---

# **12. Extensibility Requirements (Future JSON-Driven Flow)**

### MVP1 Behavior
- Steps defined directly in JavaScript (fixed).

### MVP2+ Requirements
- Steps defined in JSON stored in WordPress admin  
- Backend and UI must be architected to allow:
  - New modules (Aura, Purpose, Love, etc.)  
  - Custom step sequences  
  - Shared backend services: OTP, MailerLite, OpenAI, storage  

### Constraints
- MVP1 must not block future modularization  
- Clear separation of concerns is mandatory  

---

# **13. Non-Functional Requirements**

### 13.1 Performance
- Mask latency with loading animations  
- AI response time must be acceptable  
- Backend should not freeze UI  

### 13.2 Reliability
- OTP must work consistently  
- MailerLite failures must not block user  
- AI must fail gracefully with retry options  

### 13.3 Accessibility
- Keyboard support  
- Focus transitions  
- Clear text hierarchy  

### 13.4 Brand Experience
- Mystic, elegant tone consistent with CSS and UI design  
- Language must be warm, welcoming, and curiosity-driven  

---

# **14. Acceptance Criteria**

1. User can complete all 12 steps without UI errors  
2. OTP generation, email delivery, and validation work end-to-end  
3. MailerLite upsert succeeds or logs failure  
4. Palm photo capture and upload both function  
5. All quiz responses are saved and used  
6. AI reading is generated with accurate structure + HTML  
7. One free reading per email is enforced server-side  
8. GDPR consent is logged  
9. Errors are gracefully handled  
10. Architecture is extensible for JSON-based modules  

---

# **15. Assumptions**

- Admin already provides OpenAI and MailerLite API keys  
- All backend services built in PHP with REST endpoints  
- Hosting, caching, and SMTP optimizations out of scope  
- AI readings are ephemeral and not intended for long-term archival  
- UI is stable and authoritative  

---

# **End of Document**  
**BRD v2.0 – SoulMirror Palm Reading Experience**
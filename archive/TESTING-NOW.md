# Testing Instructions - API Integration Now Live!

**Status:** ✅ API Integration Complete - Ready for Testing
**Created:** 2025-12-12
**Issue Fixed:** UI was disconnected from backend (fake demo data)
**Solution:** Created `api-integration.js` to bridge frontend → backend

---

## What Was Fixed

### The Problem
The original `script.js` was a **complete standalone demo** with:
- ❌ No API calls to backend
- ❌ Hardcoded fake palm readings
- ❌ No OTP email sending
- ❌ No MailerLite integration
- ❌ No OpenAI API calls

### The Solution
Created `assets/js/api-integration.js` which:
- ✅ Intercepts the UI flow
- ✅ Makes real REST API calls to WordPress backend
- ✅ Sends OTP emails
- ✅ Syncs to MailerLite
- ✅ Uploads palm images
- ✅ Saves quiz responses
- ✅ Generates real AI readings with OpenAI

---

## Quick Start Testing

### Step 1: Verify Plugin is Active
```bash
cd /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public
wp plugin list --status=active | grep palm-reading
```

Expected output:
```
palm-reading  active  1.0.0  Mystic Palm Reading AI
```

### Step 2: Verify Database Tables
```bash
wp db query "SHOW TABLES LIKE 'wp_sm_%'"
```

Expected output:
```
wp_sm_leads
wp_sm_logs
wp_sm_otps
wp_sm_quiz
wp_sm_readings
```

### Step 3: Verify REST API Endpoints
```bash
wp rest api list --format=table | grep soulmirror
```

Expected: You should see routes like `/soulmirror/v1/lead/create`, `/soulmirror/v1/otp/send`, etc.

### Step 4: Create Test Page with Shortcode
```bash
wp post create --post_type=page --post_title='Palm Reading Test' --post_content='[soulmirror_palm_reading]' --post_status=publish
```

This will create a test page and return its URL.

### Step 5: Open the Test Page in Browser

1. Visit the URL from Step 4 (or navigate to the page in WordPress admin)
2. **Open Browser Console** (F12 → Console tab)
3. You should see:
   ```
   [SM API] API Integration loaded successfully
   [SM API] Backend API URL: http://sm-palm-reading.local/wp-json/soulmirror/v1/
   [SM API] API methods exposed on window.smApiMethods for debugging
   ```

### Step 6: Run Through the Flow

**Important:** Watch the browser console for API logs!

1. **Click "Begin Your Journey"**
2. **Fill out Lead Capture form:**
   - Name: Test User
   - Email: test@example.com
   - Identity: Select one
   - ✅ Check GDPR consent
3. **Click "Continue"**
   - Console should show: `[SM API] POST lead/create`
   - Console should show: `[SM API] ✓ lead/create success`
4. **Wait for Email Loading animation**
5. **Console should show:**
   ```
   [SM API] POST otp/send
   [SM API] ✓ otp/send success
   ```
6. **Check Mailpit for OTP email:**
   - Open Flywheel's Mailpit tool
   - You should see an email to `test@example.com`
   - Copy the 6-digit verification code
7. **Enter OTP code in UI**
8. **Click "Verify"**
   - Console should show: `[SM API] POST otp/verify`
   - Console should show: `[SM API] ✓ otp/verify success`
   - Console should show: `[SM API] POST mailerlite/sync`
9. **Continue through palm photo and quiz**
10. **Wait for AI reading to generate**
    - Console should show: `[SM API] POST reading/generate`
    - Console should show: `[SM API] ✓ reading/generate success`
11. **See your personalized AI reading!**

---

## Debugging Commands

### Check Debug Log
```bash
tail -20 /Users/douglasribeiro/Local\ Sites/sm-palm-reading/app/public/wp-content/plugins/palm-reading/debug.log
```

### Check Recent Leads
```bash
wp db query "SELECT id, name, email, email_confirmed, created_at FROM wp_sm_leads ORDER BY created_at DESC LIMIT 5"
```

### Check OTP Records
```bash
wp db query "SELECT id, lead_id, expires_at, attempts, created_at FROM wp_sm_otps ORDER BY created_at DESC LIMIT 5"
```

### Check Readings Generated
```bash
wp db query "SELECT id, lead_id, LEFT(reading_html, 100) as preview, generated_at FROM wp_sm_readings ORDER BY generated_at DESC LIMIT 3"
```

### Check API Settings
```bash
wp option get sm_openai_api_key
wp option get sm_mailerlite_api_key
wp option get sm_mailerlite_group_id
```

### Test Individual Endpoints (Manual cURL)

**Create Lead:**
```bash
curl -X POST 'http://sm-palm-reading.local/wp-json/soulmirror/v1/lead/create' \
  -H 'Content-Type: application/json' \
  -H 'X-WP-Nonce: YOUR_NONCE_HERE' \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "identity": "woman",
    "gdpr": true
  }'
```

(Note: Replace `YOUR_NONCE_HERE` with actual nonce from browser console: `smData.nonce`)

---

## Expected Console Output (Example)

When everything works correctly, your browser console should show:

```
[SM API] API Integration loaded successfully
[SM API] Backend API URL: http://sm-palm-reading.local/wp-json/soulmirror/v1/
[SM API] Nonce: a1b2c3d4e5...
[SM API] API methods exposed on window.smApiMethods for debugging
[SM API] Intercepted goToNextStep - Current step: leadCapture
[SM API] Creating lead... {name: "Test User", email: "test@example.com", identity: "woman"}
[SM API] POST lead/create {name: "Test User", email: "test@example.com"...}
[SM API] ✓ lead/create success {success: true, data: {lead_id: "uuid-here"}}
[SM API] ✓ Lead created {leadId: "uuid-here"}
[SM API] Intercepted goToNextStep - Current step: emailLoading
[SM API] Sending OTP... {email: "test@example.com"}
[SM API] POST otp/send {lead_id: "uuid-here", email: "test@example.com"}
[SM API] ✓ otp/send success {success: true}
[SM API] ✓ OTP sent successfully
[SM API] Verifying OTP... {code: "123456"}
[SM API] POST otp/verify {lead_id: "uuid-here", otp: "123456"}
[SM API] ✓ otp/verify success {success: true}
[SM API] ✓ OTP verified successfully
[SM API] Syncing to MailerLite...
[SM API] POST mailerlite/sync {lead_id: "uuid-here"}
[SM API] ✓ MailerLite sync successful
```

---

## Troubleshooting

### Console Shows: "smData is not defined"
**Cause:** WordPress not passing nonce/API URL to JavaScript
**Fix:** Clear browser cache, hard refresh (Ctrl+Shift+R)

### Console Shows: "appState is not defined"
**Cause:** `api-integration.js` loaded before `script.js`
**Fix:** Verify `api-integration.js` has dependency on `sm-script` in enqueue

### API Calls Return 401 Unauthorized
**Cause:** Invalid or expired nonce
**Fix:** Refresh page to get new nonce

### OTP Email Not Received in Mailpit
**Cause:** wp_mail() not configured or Flywheel SMTP issue
**Fix 1:** Check Flywheel Mailpit is running
**Fix 2:** Check debug.log for email errors
**Fix 3:** Verify email was sent: `tail debug.log | grep OTP_SENT`

### MailerLite Sync Fails
**Cause:** Invalid API key or Group ID
**Fix:** Go to WP Admin → SoulMirror Settings → Verify credentials
**Note:** This is non-blocking - user flow should continue anyway

### Reading Generation Takes Forever
**Cause:** OpenAI API timeout or invalid key
**Fix:** Check debug.log for OpenAI errors
**Check:** Verify OpenAI API key has GPT-4o access

### Console Shows: "[SM API ERROR] ✗ lead/create failed"
**Cause:** Validation error or database issue
**Check:** Look at the error object in console for details
**Debug:** Check debug.log: `tail -50 debug.log | grep ERROR`

---

## Manual Debugging Functions

### Check API State
Open browser console and type:
```javascript
smApiState
```

This shows:
```javascript
{
  leadId: "uuid-here",
  otpSent: true,
  otpVerified: true,
  imageUploaded: true,
  quizSaved: true,
  readingGenerated: false,
  processingRequest: false
}
```

### Manually Call API Functions
```javascript
// Test lead creation
await smApiMethods.createLead("Test", "test@example.com", "woman", true)

// Test OTP send
await smApiMethods.sendOtp("test@example.com")

// Test OTP verify
await smApiMethods.verifyOtp("123456")
```

---

## Success Criteria

✅ **Phase 1: Basic Setup**
- [ ] Plugin is active
- [ ] Database tables exist
- [ ] REST endpoints are registered
- [ ] Test page loads without errors

✅ **Phase 2: Frontend Integration**
- [ ] JavaScript loads without console errors
- [ ] `[SM API] API Integration loaded successfully` appears in console
- [ ] `smData` and `smApiState` are defined

✅ **Phase 3: Lead Capture & OTP**
- [ ] Lead creation works (console shows success)
- [ ] OTP email arrives in Mailpit
- [ ] OTP verification works
- [ ] MailerLite sync succeeds (or logs non-blocking error)

✅ **Phase 4: Image & Quiz**
- [ ] Palm image uploads successfully
- [ ] Quiz responses are saved

✅ **Phase 5: AI Reading**
- [ ] Reading generation completes (8-12 seconds)
- [ ] Real AI-generated HTML appears (not fake demo reading)
- [ ] Reading is saved to database

---

## Next Steps After Testing

Once you confirm the flow works end-to-end:

1. **Check MailerLite Dashboard**
   - Verify subscriber was added
   - Verify correct group assignment

2. **Test Edge Cases**
   - Try same email twice (should block second reading)
   - Try invalid OTP code 3 times (should lock out)
   - Try uploading oversized image (should reject)

3. **Performance Testing**
   - Measure API response times
   - Check debug.log size
   - Monitor database queries

4. **Security Testing**
   - Verify nonce validation
   - Test rate limiting
   - Check input sanitization

---

## Contact

If testing reveals issues, check:
1. Browser console for JavaScript errors
2. `debug.log` for PHP errors
3. Network tab (F12 → Network) for failed API requests
4. Database tables for incomplete records

---

**Last Updated:** 2025-12-12
**Integration Status:** ✅ COMPLETE - Ready for End-to-End Testing

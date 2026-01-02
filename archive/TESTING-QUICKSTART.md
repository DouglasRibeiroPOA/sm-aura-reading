# Quick Start Testing Guide

**Current Status:** Plugin files ready, database tables need to be created

**Immediate Next Steps:**

## Step 1: Activate the Plugin

1. **Access WordPress Admin:**
   - Open your browser
   - Navigate to: `http://sm-palm-reading.local/wp-admin` (or your Local site URL)
   - Log in with your admin credentials

2. **Activate Plugin:**
   - Go to: **Plugins** → **Installed Plugins**
   - Find: "Mystic Palm Reading AI"
   - Click: **Activate**

3. **Verify Activation:**
   - Check for PHP errors (should show success message)
   - Plugin should appear in active plugins list

## Step 2: Verify Database Tables

After activation, the plugin should create 5 database tables:

**Check via phpMyAdmin or database tool:**
- `wp_sm_leads`
- `wp_sm_otps`
- `wp_sm_quiz`
- `wp_sm_readings`
- `wp_sm_logs`

**Alternative: Check debug.log:**
```
cat debug.log
```

Look for successful database creation log entries.

## Step 3: Configure API Keys

1. **Navigate to Settings:**
   - WordPress Admin → **SoulMirror Settings** (should appear in admin menu)

2. **Enter Required Keys:**
   - **OpenAI API Key:** `sk-...` (get from https://platform.openai.com/api-keys)
   - **MailerLite API Key:** Get from your MailerLite account
   - **MailerLite Group ID:** Get from MailerLite dashboard

3. **Configure OTP Settings:**
   - OTP Expiration: 10 minutes (default)
   - Max Attempts: 3 (default)
   - Resend Cooldown: 10 minutes (default)

4. **Enable Debug Logging:**
   - Toggle: **ON**
   - This helps with testing and troubleshooting

5. **Save Settings**

## Step 4: Create Test Page

1. **Create New Page:**
   - WordPress Admin → **Pages** → **Add New**
   - Title: "Palm Reading Test"

2. **Add Shortcode:**
   ```
   [soulmirror_palm_reading]
   ```

3. **Publish Page**

4. **Note the URL** (e.g., `http://sm-palm-reading.local/palm-reading-test/`)

## Step 5: Verify Frontend Load

1. **Visit Test Page:**
   - Open test page URL in browser
   - **Important:** Use HTTPS if available (for camera access)

2. **Check Browser Console:**
   - Press F12 (Developer Tools)
   - Go to Console tab
   - Verify no JavaScript errors

3. **Verify Assets Loaded:**
   - Network tab should show:
     - `styles.css` loaded
     - `script.js` loaded
     - `smData` object available (check Console: type `smData`)

4. **Verify UI Displays:**
   - Welcome screen should be visible
   - UI should match the existing design
   - No broken elements

## Step 6: Run First Test - Lead Creation

1. **Fill Out Lead Form:**
   - Name: "Test User"
   - Email: Your accessible test email
   - Identity: Select any option
   - GDPR Consent: Check the box

2. **Submit Form**

3. **Check Results:**
   - Should see loading animation
   - Should transition to OTP entry screen
   - Check email for OTP code

4. **Verify Database:**
   - Check `wp_sm_leads` table
   - Should see new lead record
   - Verify all fields populated correctly

## Step 7: Verify OTP Flow

1. **Check Email:**
   - Should receive email with 6-digit code
   - Subject: "Your SoulMirror Verification Code"

2. **Enter OTP:**
   - Input the 6-digit code
   - Submit

3. **Expected Results:**
   - OTP verified successfully
   - Lead `email_confirmed` = 1 in database
   - Progress to next step (palm upload)

4. **Check Debug Log:**
   ```
   tail -f debug.log
   ```
   - Should see OTP generation log
   - Should see OTP verification success log
   - Should see MailerLite sync attempt

## Common Issues & Solutions

### Issue: Plugin won't activate
**Solution:**
- Check PHP version: `php -v` (must be 7.4+)
- Check WordPress version (must be 5.8+)
- Review activation errors in browser
- Check `debug.log` for errors

### Issue: Database tables not created
**Solution:**
- Deactivate and reactivate plugin
- Check database user permissions
- Review `SM_Database::activate()` logs
- Manually check if tables exist in phpMyAdmin

### Issue: Admin settings page not visible
**Solution:**
- Clear browser cache
- Check user has `manage_options` capability
- Verify `SM_Settings::init()` is called
- Check for JavaScript errors in console

### Issue: Assets (CSS/JS) not loading
**Solution:**
- Verify shortcode is on page
- Check file paths in browser Network tab
- Clear WordPress cache (if caching plugin active)
- Verify `SM_VERSION` constant defined

### Issue: OTP email not delivered
**Solution:**
- Check spam folder
- Verify `wp_mail()` working (test with WP Mail Tester plugin)
- Install WP Mail SMTP plugin for better deliverability
- Check debug.log for email sending errors

### Issue: Frontend shows blank/broken UI
**Solution:**
- Check browser console for errors
- Verify `smData` object exists (type in console)
- Verify nonce present: `smData.nonce`
- Verify API URL: `smData.apiUrl`
- Check Network tab for failed requests

## Next Testing Steps

After completing Steps 1-7 successfully:

1. **Proceed to TESTING.md** for comprehensive test cases
2. **Start with Unit 6.1:** Component Unit Testing
3. **Document results** as you test
4. **Report issues** using the issue template in TESTING.md

## Testing Tools Recommended

- **Browser DevTools** (F12) - Essential for frontend debugging
- **Postman** - For API endpoint testing
- **phpMyAdmin** - For database inspection
- **WP Mail Tester** - For email deliverability testing
- **Browser Extensions:**
  - JSON Formatter
  - REST Client (e.g., RESTer)

## Support

If you encounter issues not covered here:
1. Check `debug.log` for detailed error messages
2. Review TESTING.md for specific test cases
3. Consult requirements.md for expected behavior
4. Check CLAUDE.md for development principles

---

**Ready to begin?** Start with Step 1 above!

**Current Environment:**
- Plugin Location: `/wp-content/plugins/palm-reading/`
- Debug Log: `/wp-content/plugins/palm-reading/debug.log`
- Local URL: `http://sm-palm-reading.local` (adjust if different)

**Testing Status:**
- [ ] Plugin activated
- [ ] Database tables created
- [ ] API keys configured
- [ ] Test page created
- [ ] Frontend loads successfully
- [ ] Lead creation tested
- [ ] OTP flow tested

Check off items as you complete them!

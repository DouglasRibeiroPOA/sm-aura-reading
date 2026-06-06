# Aura Reading Service - Requirements & Implementation Plan

**Version:** 1.0.0
**Created:** 2026-01-01
**Base Template:** Palm Reading Plugin v1.4.5

---

## 📊 Overall Progress

**Total Progress:** 6/8 phases complete (75% complete + significant progress)

```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░] 75%
```

### Phase Status Overview
- Phase 1: Plugin Foundation - 8/8 tasks (100%) ✅ **COMPLETE**
- Phase 2: Database & Backend - 9/9 tasks (100%) ✅ **COMPLETE**
- Phase 3: Frontend Assets - 19/19 tasks (100%) ✅ **COMPLETE**
- Phase 4: Templates & Content - 11/11 tasks (100%) ✅ **COMPLETE**
- Phase 5: AI Prompts & Question Bank - 8/8 tasks (100%) ✅ **COMPLETE**
- Phase 6: Integration & Configuration - 11/14 tasks (79%) ⚠️ Offerings URL + MailerLite group pending
- Phase 7: Testing & Quality Assurance - 46/52 tasks (88%)
- Phase 8: Documentation - 4/4 tasks (100%) ✅ **COMPLETE**

**🎉 Major Milestone:** All critical blockers have been fixed! The plugin now has a solid foundation and backend ready for testing.

**📝 Important:** This progress tracker should be updated **every time a task is completed**. Update frequently to maintain accurate status visibility.

---

## 🎯 Project Overview

Create a complete Aura Reading service by cloning and rebranding the existing Palm Reading WordPress plugin. The two services will share the same technical architecture, user flow, and integrations, but will be 100% independent and maintain distinct brand identities.

### Core Principle
**Technical Foundation:** Identical architecture, same flow, same systems
**User Experience:** Completely distinct branding, visuals, language, and AI logic

---

## 🏗️ Architecture Decisions

### Independence Level: 100% Standalone
- **Deployment:** Separate WordPress instances (primary use case)
- **Fallback:** Can coexist on same WordPress instance without conflicts
- **Database:** Completely separate tables (`wp_sm_aura_*` vs `wp_sm_palm_*`)
- **Settings:** Independent admin pages and configuration
- **Assets:** Separate CSS/JS files with distinct styling
- **Namespace:** Unique plugin slug, text domain, and class prefixes

**Why:** Maximum flexibility for independent evolution, updates, and deployment strategies.

---

## 🎨 Brand Identity: Aura Reading

### Visual Theme
**Palm Reading:** Dark, mystical, grounded, physical
**Aura Reading:** Light, ethereal, energetic, emotional/spiritual

### Color Palette

#### Core Colors
```css
/* Background (main) */
--aura-bg-primary: #F4F7FB;        /* Soft mist blue */
--aura-bg-secondary: #F6F3FA;      /* Very pale lavender */

/* Primary accent (buttons, highlights) */
--aura-accent-primary: #A88BEB;    /* Soft aura purple */
--aura-accent-alt: #9F8CF1;        /* Spiritual lilac */

/* Secondary accent (glow, dividers, icons) */
--aura-accent-secondary: #6FD6C5;  /* Ethereal teal */
--aura-accent-sky: #7BB7E6;        /* Calm sky blue */

/* Text */
--aura-text-primary: #2F2F3A;      /* Deep blue-gray */
--aura-text-secondary: #6B6F85;    /* Muted gray */
```

#### Visual Effects
- **Background:** Soft gradients, subtle light noise, gentle radial glow
- **Animation:** Breathing effect (opacity 3-5%, slow pulse)
- **Glow:** Center glow behind user silhouette/photo
- **Shapes:** Floating blur shapes (very subtle)
- **NO stars** (that's Palm Reading's aesthetic)

### Imagery & Icons
- **Input Photo:** Upper body/shoulders up (energy, presence, posture, alignment)
- **NOT:** Palm/hand imagery
- **YES:** Silhouettes, energy fields, aura colors, light rays, chakra symbols

### Tone & Language
- **Focus Areas:** Energy, intuition, aura, emotional state, spiritual alignment
- **Avoid:** Palm lines, hand analysis, physical predictions
- **Style:** Simple, accessible, warm, empowering (not mystical/dark)

---

## 📸 Image Input Experience

### User Prompt
```
"A calm photo of yourself from the shoulders up, in good light.
Your face does not need to be perfectly visible."
```

### Framing
We're analyzing:
- ✅ Energy
- ✅ Presence
- ✅ Posture
- ✅ Alignment

NOT analyzing:
- ❌ Beauty
- ❌ Looks
- ❌ Physical appearance

### Technical Specs
- **File Types:** JPEG, PNG
- **Max Size:** 5MB
- **Storage:** Non-public directory (same security as Palm Reading)
- **AI Analysis:** OpenAI GPT-4o Vision (analyze energy, posture, presence from upper body photo)

---

## 📋 Questionnaire Structure

The aura reading questionnaire explores **6 core areas** that map to different aspects of the user's energetic state:

### 1. Emotional State & Inner Climate
**What this explores:**
How the person is feeling right now on an emotional level.

**This looks at:**
- Dominant emotions
- Emotional calm vs. overwhelm
- Inner stability or sensitivity

**Aura Insight:**
Sets the tone of the aura — calm, intense, heavy, light.

---

### 2. Energy Level & Flow
**What this explores:**
How energy is moving through their life.

**This touches on:**
- Vitality vs. fatigue
- Motivation
- Where energy feels blocked or flowing freely

**Aura Insight:**
Helps interpret aura strength and movement.

---

### 3. Love, Relationships & Emotional Connection
**What this explores:**
How the person experiences love and connection — with others and with themselves.

**This may include:**
- Romantic relationships
- Emotional closeness
- Trust, openness, or distance

**Important Note:**
This does NOT predict outcomes — it reflects emotional patterns that show up in the aura.

**Why it matters:**
This area is very important and absolutely belongs in aura analysis.

---

### 4. Life Direction, Success & Material Flow
**What this explores:**
How aligned the person feels with their path in life — including work, money, and personal growth.

**This looks at:**
- Sense of direction
- Confidence around success
- Relationship with abundance and effort

**Aura Insight:**
Connects aura energy with grounded, real-world expression.

---

### 5. Spiritual Memory & Deeper Patterns
**What this explores:**
Recurring themes, lessons, or patterns that feel older or deeply familiar.

**This can be described as:**
- Long-standing tendencies
- Repeating emotional themes
- Inner wisdom that feels "carried forward"

**Framing:**
Symbolic and reflective, NOT literal or deterministic. This is the "past lives" concept gently integrated.

---

### 6. Intentions, Healing & Growth
**What this explores:**
What the person is ready to shift, heal, or cultivate next.

**This focuses on:**
- Personal intentions
- Inner growth
- Emotional or energetic renewal

**Aura Insight:**
Gives the reading a forward-looking, empowering close.

---

## 🤖 AI Integration (OpenAI)

### Model Configuration
- **Model:** GPT-4o (same as Palm Reading)
- **Features:** Vision + Text
- **Max Tokens:** 3200
- **Temperature:** 0.7

### Input Data
1. **Image:** Upper body photo (shoulders up)
2. **Quiz Responses:** Answers to all 6 questionnaire categories
3. **User Metadata:** Name, age/age range, identity/gender

### Prompt Template Updates
**Replace Palm Reading prompts with Aura Reading prompts that:**
- Reference energy, aura colors, emotional patterns, spiritual alignment
- Analyze posture, presence, and energy from the photo
- Map quiz responses to aura insights (not palm line analysis)
- Use aura-specific language and symbolism
- Maintain the same JSON structure for reading sections

### Reading Structure
**Teaser Reading (Free):**
- 6 sections (one per questionnaire category)
- Some sections locked, user can unlock 2 for free
- Paywall for full reading

**Full Reading (Paid):**
- All 6 sections unlocked with deeper insights
- Additional synthesis/summary section
- Downloadable HTML report

---

## 💾 Database Schema

### New Tables (Completely Independent)
```sql
wp_sm_aura_leads
wp_sm_aura_otps
wp_sm_aura_quiz
wp_sm_aura_readings
wp_sm_aura_logs
wp_sm_aura_flow_sessions
```

### Reading Types
- `aura_teaser` (replaces `palm_teaser`)
- `aura_full` (replaces `palm_full`)

### Migration Notes
- No data migration needed (separate tables)
- Schema version: Start at 1.0.0 for Aura Reading
- Same structure as Palm Reading tables, just renamed

---

## 🔧 Technical Implementation Checklist

> **⚠️ IMPORTANT - Progress Tracking:**
>
> **Update this document after EVERY task completion.** This means:
> - Mark tasks as complete (`[ ]` → `[x]`) immediately when done
> - Update the phase progress bar and percentage
> - Update the overall progress tracker at the top
> - Update frequently throughout development, not just at phase completion
>
> This ensures accurate status visibility for all stakeholders and helps track project momentum.

### Phase 1: Plugin Foundation

**Progress:** 8/8 tasks complete (100%) ✅
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
```

✅ **ALL CRITICAL ISSUES FIXED - 2026-01-02:**
1. ✅ **Text domain loading FIXED** - Now correctly loads `mystic-aura-reading`
2. ✅ **Constants completely fixed** - All `SM_PLUGIN_*` replaced with `SM_AURA_*` throughout codebase
3. ℹ️ **"palm" references** - Some remain in documentation/archives (intentional), core code updated

#### File & Directory Structure
- [x] Copy entire `sm-palm-reading/` directory to `sm-aura-reading/` ✅
- [x] Rename main plugin file: `mystic-palm-reading.php` → `mystic-aura-reading.php` ✅
- [x] Update plugin header metadata: ✅
  - Plugin Name: "SoulMirror Aura Reading" ✅
  - Description: Update to reference aura reading ✅
  - Text Domain: `mystic-aura-reading` ✅
  - Version: Start at 1.0.0 ✅

#### Constants & Configuration
- [x] Update plugin constants: ✅
  ```php
  define( 'SM_AURA_VERSION', '1.0.0' );
  define( 'SM_AURA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
  define( 'SM_AURA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
  define( 'SM_AURA_PLUGIN_FILE', __FILE__ );
  define( 'SM_AURA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
  ```

- [x] **FIXED:** Replaced all `SM_PLUGIN_*` constant usage with `SM_AURA_*` equivalents throughout codebase ✅
  - mystic-aura-reading.php ✅
  - includes/class-sm-template-renderer.php ✅
  - includes/class-sm-logger.php ✅
  - includes/class-sm-settings.php ✅
  - templates/user-reports.php ✅
  - tests/diagnose.php ✅

#### Text Domain
- [x] Update text domain in plugin header to `mystic-aura-reading` ✅
- [x] **FIXED:** Updated text domain loading (now loads `mystic-aura-reading`) ✅
- [x] **FIXED:** Also fixed `SM_AURA_PLUGIN_BASENAME` usage in text domain loading ✅

#### Shortcode
- [x] Change shortcode: `[soulmirror_palm_reading]` → `[soulmirror_aura_reading]` ✅
  - Verified in mystic-aura-reading.php line 298 ✅
  - Elementor detection updated line 115 ✅

---

### Phase 2: Database & Backend

**Progress:** 9/9 tasks complete (100%) ✅
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
```

✅ **ALL CRITICAL ISSUES FIXED - 2026-01-02:**
1. ✅ **Reading types FIXED** - All `palm_teaser`/`palm_full` replaced with `aura_teaser`/`aura_full` across entire codebase
2. ✅ **Settings page header FIXED** - Now shows "Aura Reading Settings"
3. ✅ **Callback URLs FIXED** - Auth callbacks now use `/aura-reading/auth/callback`

#### Class Files (includes/)
- [x] Keep class prefix as `SM_` (SoulMirror - shared namespace is fine) ✅

#### Database Schema
- [x] Update `SM_Database` class: ✅
  - [x] Change table names: `sm_palm_*` → `sm_aura_*` ✅
    - `wp_sm_aura_leads` ✅
    - `wp_sm_aura_otps` ✅
    - `wp_sm_aura_quiz` ✅
    - `wp_sm_aura_readings` ✅
    - `wp_sm_aura_logs` ✅
    - `wp_sm_aura_flow_sessions` ✅
  - [x] Update schema version to 1.0.0 ✅ (class-sm-database.php line 20)
  - [x] Update default reading_type to `aura_teaser` in migrations ✅
  - [x] Keep same table structure ✅

#### Reading Types
- [x] Update `SM_Reading_Service`: ✅
  - [x] Add reading types: `aura_teaser`, `aura_full` ✅ (line 93 in class-sm-reading-service.php)
  - [x] Update validation logic ✅

- [x] **FIXED:** Replaced ALL `palm_teaser`/`palm_full` references with `aura_teaser`/`aura_full` in: ✅
  - includes/class-sm-teaser-reading-schema.php (constant) ✅
  - includes/class-sm-teaser-reading-schema-v2.php (constant) ✅
  - includes/class-sm-template-renderer.php ✅
  - includes/class-sm-reading-job-handler.php ✅
  - includes/class-sm-dev-mode.php ✅
  - includes/class-sm-test-helpers.php ✅
  - includes/class-sm-database.php ✅
  - includes/class-sm-reading-service.php ✅
  - includes/class-sm-reports-handler.php ✅
  - includes/class-sm-ai-handler.php ✅
  - includes/class-sm-rest-controller.php (26+ occurrences) ✅
  - assets/js/script.js ✅
  - assets/js/teaser-reading.js ✅
  - assets/js/api-integration.js ✅

#### Settings & Options
- [x] Update WordPress options keys: ✅
  - [x] `sm_palm_settings` → `sm_aura_settings` ✅ (line 20 in class-sm-settings.php)
  - [x] `sm_palm_db_version` → `sm_aura_db_version` ✅

#### Admin Menu
- [x] Change admin menu title: "Palm Reading" → "Aura Reading" ✅ (line 65 in class-sm-settings.php)
- [x] **FIXED:** Settings page header now says "Aura Reading Settings" ✅ (line 542)
- [x] **FIXED:** Callback URLs updated to `/aura-reading/auth/callback` ✅ (line 718 in class-sm-settings.php)

---

### Phase 3: Frontend Assets

**Progress:** 19/19 tasks complete (100%)
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
```

✅ **Completed:** CSS, JavaScript, and images updated for Aura branding

#### CSS Files (assets/css/)
- [x] **styles.css:**
  - [x] Update color palette (use new Aura colors) ✓
    - `--color-bg-main: #F4F7FB` ✓
    - `--color-primary: #A88BEB` ✓
    - `--color-secondary: #6FD6C5` ✓
    - `--color-accent-light: #7BB7E6` ✓
  - [x] Increase aura color contrast for stronger UI presence ✓
  - [x] Improve form input contrast for visibility ✓
  - [x] Improve OTP input contrast for visibility ✓
  - [x] Improve quiz option contrast for visibility ✓
  - [x] Add rating button styling for scale questions ✓
  - [x] Remove star backgrounds ✓
  - [x] Add soft gradients and glow effects ✓
  - [x] Implement breathing animation ✓
  - [x] Update button styles with new accent colors ✓
  - [x] Improve dashboard and reports contrast + share button support ✓

- [x] **auth.css:** Update to match new color scheme ✓
  - All aura color variables implemented ✓
  - [x] Fix login button hover contrast so text remains visible ✓

- [x] **reports-listing.css:** Update card styling ✓
  - Aura palette defined and used ✓

- [x] **swipe-template.css:** Update colors and animations ✓
  - Aura gradients and palette applied ✓

#### JavaScript Files (assets/js/)
- [x] **script.js:**
  - [x] Update step labels and instructions ✓
  - [x] Change image upload prompt text (palm → upper body/shoulders) ✓
  - [x] Update UI text references (palm → aura) ✓
  - [x] Update any reading type references to use `aura_teaser`/`aura_full` ✓
  - [x] Update welcome icon to aura compass ✓

- [x] **api-integration.js:**
  - [x] Update API endpoint references (if namespace changes) ✓
  - [x] Update reading type parameters (`palm_teaser` → `aura_teaser`, etc.) ✓
  - [x] Verify REST endpoint paths ✓

- [x] **teaser-reading.js:**
  - [x] Update section labels to match 6 aura categories ✓
  - [x] Update unlock messaging ✓
  - [x] Verify reading type parameters ✓

#### Images (assets/img/)
- [x] Create `assets/img/` directory if it doesn't exist ✓
- [x] Replace test images with aura-appropriate test images ✓
- [x] Add new icon assets if needed (energy symbols, chakra symbols, etc.) ✓
- [x] Remove any palm-specific imagery ✓

---

### Phase 4: Templates & Content

**Progress:** 12/12 tasks complete (100%)
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
```

#### PHP Templates (templates/)
- [x] **container.php:**
  - [x] Update welcome text and instructions ✓
  - [x] Update progress labels ✓
  - [x] Change image upload instructions ✓

- [x] **dashboard.php:**
  - [x] Update page title: "Aura Reading Dashboard" ✓
  - [x] Update UI labels (credits, readings, etc.) ✓
  - [x] Keep same structure ✓

- [x] **user-reports.php:**
  - [x] Update title: "Your Aura Readings" ✓
  - [x] Keep same grid layout ✓

#### HTML Reading Templates
- [ ] **Rename and update:**
  - `palm-reading-template-teaser.html` → `aura-reading-template-teaser.html` ✓
  - `palm-reading-template-swipe-teaser.html` → `aura-reading-template-swipe-teaser.html` ✓
  - `palm-reading-template-full.html` → `aura-reading-template-full.html` ✓
  - `palm-reading-template-swipe-full.html` → `aura-reading-template-swipe-full.html` ✓

- [x] **Update content in templates:**
  - [x] Change color scheme (CSS variables) ✓
  - [x] Update section titles to match questionnaire categories ✓
  - [x] Remove palm-specific imagery/icons ✓
  - [x] Add aura-specific visual elements ✓
  - [x] Update locked section messaging ✓
  - [x] Remove teaser header timestamp to match paid reading layout ✓

---

### Phase 5: AI Prompts & Question Bank

**Progress:** 8/8 tasks complete (100%)
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
```

#### Prompt Templates
- [x] Create new prompt templates in `SM_Prompt_Template_Handler`:
  - [x] **Teaser prompt:** Analyze upper body photo + quiz responses for aura insights ✓
  - [x] **Full reading prompt:** Deeper aura analysis with all 6 categories ✓
  - [x] Use aura-specific language: energy, aura colors, emotional patterns, spiritual alignment ✓
  - [x] Reference questionnaire categories in prompts ✓
  - [x] Request same JSON structure for compatibility ✓

#### Question Bank
- [x] Create questions for each category:
  1. [x] Emotional State & Inner Climate (3-5 questions) ✓
  2. [x] Energy Level & Flow (3-5 questions) ✓
  3. [x] Love, Relationships & Emotional Connection (3-5 questions) ✓
  4. [x] Life Direction, Success & Material Flow (3-5 questions) ✓
  5. [x] Spiritual Memory & Deeper Patterns (3-5 questions) ✓
  6. [x] Intentions, Healing & Growth (3-5 questions) ✓

- [x] Question types:
  - [x] Multiple choice ✓
  - [x] Text input (max 500 chars) ✓
  - [x] Scales/ratings where appropriate ✓

---

### Phase 6: Integration & Configuration

**Progress:** 11/14 tasks complete (79%)
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░]
```

#### Account Service Integration
- [x] Keep same JWT authentication flow ✓
- [x] Update service identifier (if needed): `palm_reading` → `aura_reading` ✓
- [x] Update credit checking to use `aura_reading` service credits ✓
- [ ] Update offerings page URLs (may be different for Aura vs Palm)
- [x] Fix admin settings persistence for Account Service URL (allow http/https, correct settings group)
- [x] Allow legacy settings group to prevent options page save errors
- [x] Add settings key status indicators and plaintext fallback for missing salts
- [x] Normalize API keys on save and log masked key metadata when debug logging is enabled

#### MailerLite Integration
- [x] Keep same MailerLite handler ✓
- [ ] Use different MailerLite Group ID for Aura Reading subscribers
- [ ] Update in admin settings

#### OpenAI Integration
- [x] Keep same API handler ✓
- [x] Can share same OpenAI API key ✓
- [x] Use new prompt templates (created in Phase 5) ✓

---

### Phase 7: Testing & Quality Assurance

**Progress:** 46/52 tasks complete (88%)
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░] 88%
```

#### E2E Tests (tests/)
- [x] Update Playwright test files:
  - [x] Rename test suite ✓
  - [x] Update selectors if UI changed significantly ✓
  - [x] Update test data (aura-specific questions/responses) ✓
  - [x] Update assertions for aura content ✓

- [x] Test files to update:
  - `e2e-full-flow.spec.js` → Update for aura flow ✓
  - Update any other test files ✓

#### Activation and Stability
- [x] Fix activation fatal (SM_AURA_VERSION and aura FK constraint names)
- [x] Fix settings registration fatal (sm_aura_settings option key)
- [x] Fix missing reading_type column by updating DB schema version and base tables
- [x] Fix AI handler to use aura tables and reading types
- [x] Ensure schema integrity checks run for missing columns
- [x] Update palm error copy to aura phrasing
- [x] Update image retry email to aura phrasing
- [x] Relax vision validation and add OpenAI vision logging
- [x] Update aura vision prompts and summary labels to accept upper body photos
- [x] Prevent vision resubmit lockouts and enforce aura signal defaults
- [x] Reduce dynamic quiz to 4 questions and remove extra free-text prompt
- [x] Ensure only the final question is free text in dynamic and fallback flows
- [x] Fix quiz navigation buttons after the camera step in aura flow
- [x] Restore teaser report refresh + email access with a teaser token
- [x] Isolate guest vs paid flow state across session storage and flow cookies
- [x] Fix smStorage redeclaration blocking app initialization
- [x] Preserve in-progress flow refresh via sm_flow URL flag and scoped localStorage restore
- [x] Allow teaser report refresh and magic token access without paid login redirect
- [x] Prevent step enforcement from snapping users back after refresh on quiz steps
- [x] Persist dynamic quiz questions and demographics across refreshes
- [x] Persist uploaded aura photo state across refreshes to prevent missing-photo errors
- [x] Restore logged-in flow on refresh when sm_flow is present but step state is missing
- [x] Preserve auth flow context across refresh with sm_flow_auth flag
- [x] Persist paid-flow lead_id for quiz save after refresh
- [x] Clear paid flow state on Begin Journey to ensure a clean slate
- [x] Prevent paid flow bounce to dashboard on first Begin Journey click
- [x] Update teaser quick-insight modal close button label
- [x] Reset OTP state at lead capture to ensure OTP send/verify works per session
- [x] Fix duplicate URL params declaration blocking teaser flow scripts
- [x] Connect OTP resend to backend and update OTP hint copy
- [x] Reduce OTP resend cooldown to 30 seconds and improve rate-limit messaging
- [x] Redirect logged-in users away from unauthorized teaser links to the dashboard
- [x] Force paid Begin Journey to reuse current URL with start_new flags to avoid bounce
- [x] Preserve start-new pending flag so redirect guard triggers on first click
- [x] Ensure newly entered welcome email overrides stale restored app state before lead creation; added regression coverage for stale-email/session carryover in `01-critical-happy-paths.spec.js`

#### Manual Testing Checklist
- [x] Lead creation and OTP verification
- [ ] Quiz submission (all 6 categories)
- [x] Image upload (upper body photo)
- [x] Teaser reading generation
- [ ] Section unlock flow (2 free unlocks)
- [ ] Paywall redirect
- [ ] Full reading purchase (with credits)
- [ ] Reading download (HTML report)
- [x] Dashboard display
- [ ] Reports listing
- [ ] MailerLite sync
- [ ] Account Service integration
- [ ] Mobile responsiveness
- [ ] Cross-browser compatibility

#### Development Mode Testing
- [x] Test with `dev_all` mode (mock APIs)
- [ ] Test with `dev_openai_only` (real MailerLite)
- [ ] Test with `dev_mailerlite_only` (real OpenAI)
- [ ] Test production mode (all real APIs)

---

### Phase 8: Documentation

**Progress:** 4/4 tasks complete (100%)
```
[▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓]
```

#### Update Documentation Files
- [x] **README.md:**
  - [x] Update project name and description
  - [x] Update setup instructions
  - [x] Update screenshot/demo info

- [x] **CLAUDE.md:** ✓ Created with comprehensive development guide
  - [x] Includes mandatory workflow instructions ✓
  - [x] Progress tracking protocol ✓
  - [x] Architecture and best practices ✓
  - [x] Brand identity guide ✓
  - [x] Common development tasks ✓
  - [x] Testing protocol ✓
  - [x] Code review checklist ✓

- [x] **CODEX.md, CONTEXT.md:**
  - [x] Update references to Palm → Aura
  - [x] Update architecture notes
  - [x] Document aura-specific features

- [x] **package.json:**
  - [x] Update package name: `sm-aura-reading-tests`
  - [x] Update description
  - [x] Keep same version as plugin (1.0.0)

---

## 🔄 String Replacement Reference

### Global Find & Replace
Use these patterns for bulk updates across all files:

| Find | Replace | Scope |
|------|---------|-------|
| `sm_palm_` | `sm_aura_` | Database tables, options |
| `palm_reading` | `aura_reading` | Slugs, reading types |
| `Palm Reading` | `Aura Reading` | UI labels, titles |
| `palm reading` | `aura reading` | Copy text |
| `mystic-palm-reading` | `mystic-aura-reading` | Text domain |
| `soulmirror_palm_reading` | `soulmirror_aura_reading` | Shortcode |
| `SM_PALM_` | `SM_AURA_` | Constants (if using separate constants) |

### Context-Specific Updates
These require manual review (don't bulk replace):
- Prompt templates (rewrite for aura context)
- Question bank content (completely different questions)
- CSS color values (new palette)
- Template content (section titles, descriptions)
- Image upload instructions (shoulders up, not palm)

---

## 📦 Deployment Strategy

### Development Workflow
1. **Clone Palm Reading plugin** into `sm-aura-reading/` directory
2. **Bulk rename/replace** using reference table above
3. **Update visuals** (CSS colors, effects, animations)
4. **Rewrite content** (questions, prompts, templates)
5. **Test locally** with DevMode
6. **Test with real APIs** in staging
7. **Deploy to production** WordPress instance

### Production Deployment
- **Primary:** Separate WordPress instance (e.g., `aura.soulmirror.com`)
- **Fallback:** Can coexist with Palm Reading on same instance
- **URL structure:** `/aura-reading/` or dedicated page
- **Shortcode:** `[soulmirror_aura_reading]`

### Version Management
- **Initial Release:** 1.0.0
- **Follow Palm Reading updates** for bug fixes and features
- **Independent evolution** for aura-specific features

---

## 🎯 Success Criteria

### Technical Requirements
- ✅ Plugin activates without errors
- ✅ All database tables created successfully
- ✅ No conflicts with Palm Reading plugin (if coexisting)
- ✅ All REST API endpoints functional
- ✅ OpenAI integration working (aura prompts)
- ✅ MailerLite sync working
- ✅ Account Service authentication working
- ✅ Credit checking working
- ✅ Reading generation and storage working
- ✅ File uploads secure and functional

### User Experience Requirements
- ✅ Visual identity clearly distinct from Palm Reading
- ✅ Color palette implemented consistently
- ✅ Breathing animation and glow effects working
- ✅ Image upload instructions clear and appropriate
- ✅ Questionnaire covers all 6 aura categories
- ✅ Teaser reading displays correctly
- ✅ Unlock flow works (2 free unlocks)
- ✅ Paywall redirects properly
- ✅ Full reading purchase flow works
- ✅ HTML report downloads correctly
- ✅ Mobile-responsive on all screens
- ✅ No palm-related language or imagery visible

### Content Quality Requirements
- ✅ AI prompts generate aura-focused insights
- ✅ Reading content feels relevant to aura/energy analysis
- ✅ Language is simple, accessible, empowering
- ✅ Questionnaire questions are clear and meaningful
- ✅ Section titles match the 6 categories
- ✅ Tone is lighter and more ethereal than Palm Reading

---

## 📝 Open Questions & Future Enhancements

### Initial Launch Questions
- [ ] Should we use the same Account Service credit pool or separate `aura_reading` service credits?
- [ ] Should Aura readings appear in the same user dashboard as Palm readings (if on same instance)?
- [ ] What should the base URL / page slug be? (`/aura-reading/` suggested)

### Future Feature Ideas
- **Aura color visualization:** Generate visual aura representation based on reading
- **Energy chart:** Graph showing energy levels across the 6 categories
- **Chakra integration:** Map aura insights to chakra system
- **Multi-language support:** Translate prompts and UI
- **Video upload:** Allow video instead of static photo for richer analysis
- **Comparison reports:** Show how aura changes over time with multiple readings

---

## 🚀 Implementation Timeline

This is a **sequential implementation** - each phase builds on the previous:

1. **Phase 1-2:** Plugin Foundation & Database (1 day)
2. **Phase 3:** Frontend Assets (1-2 days)
3. **Phase 4:** Templates & Content (1 day)
4. **Phase 5:** AI Prompts & Questions (1-2 days)
5. **Phase 6:** Integration & Configuration (1 day)
6. **Phase 7:** Testing & QA (2-3 days)
7. **Phase 8:** Documentation (0.5 day)

**Total Estimated Effort:** 7-10 days for complete implementation and testing

---

## 🔗 Related Resources

### Source Plugin
- **Path:** `/Users/douglasribeiro/Local Sites/sm-aura-reading/app/public/wp-content/plugins/sm-palm-reading`
- **Version:** 1.4.5
- **Database Schema:** 1.4.6

### Target Plugin
- **Path:** `/Users/douglasribeiro/Local Sites/sm-aura-reading/app/public/wp-content/plugins/sm-aura-reading`
- **Version:** 1.0.0 (initial)
- **Database Schema:** 1.0.0 (initial)

### Documentation References
- Palm Reading CLAUDE.md
- Palm Reading CODEX.md
- Palm Reading CONTEXT.md
- OpenAI GPT-4o Vision API docs
- MailerLite API v3 docs
- WordPress Plugin Handbook

---

## ✅ Next Steps

**Ready to begin implementation?**

1. Review this requirements document
2. Confirm all decisions and preferences
3. Begin Phase 1: Clone and rename plugin foundation
4. Work through phases sequentially
5. Test thoroughly at each phase
6. Deploy to staging environment
7. Final QA and production deployment

**Questions or adjustments needed?** Let's discuss before starting implementation.

---

## 🔴 CURRENT STATE ASSESSMENT (Updated 2026-01-02)

### What's Actually Been Completed:

✅ **Database & Infrastructure (Strong Foundation)**
- All 6 tables using `sm_aura_*` naming correctly
- Database schema version updated to 1.0.0
- WordPress options using `sm_aura_*` keys
- Class files structure maintained

✅ **Visual Identity (CSS Complete)**
- Aura color palette fully implemented across all CSS files
- Soft gradients and glow effects added
- Breathing animation implemented
- Button styles updated with new accent colors
- All 3 CSS files updated: styles.css, auth.css, reports-listing.css

✅ **Basic Configuration**
- Main plugin file renamed to `mystic-aura-reading.php`
- Plugin header metadata updated correctly
- Shortcode changed to `[soulmirror_aura_reading]`
- Admin menu title shows "Aura Reading"

### ✅ Critical Issues - ALL FIXED (2026-01-02):

✅ **FIXED: Reading Types Consistency**
- **Solution:** Successfully replaced ALL `palm_teaser`/`palm_full` with `aura_teaser`/`aura_full`
- **Files Updated:**
  - All PHP includes files (14 files) ✅
  - All JavaScript files (3 files) ✅
  - Template files ✅
  - Test files ✅
- **Status:** COMPLETE - Plugin now uses correct reading types throughout

✅ **FIXED: Text Domain Loading**
- **Solution:** Updated line 62 in `mystic-aura-reading.php` to load `mystic-aura-reading`
- **Also Fixed:** Updated `SM_PLUGIN_BASENAME` to `SM_AURA_PLUGIN_BASENAME` in text domain loading
- **Status:** COMPLETE - Translations will now work correctly

✅ **FIXED: Constants Consistency**
- **Solution:** Replaced ALL `SM_PLUGIN_*` constants with `SM_AURA_*` throughout codebase
- **Files Updated:**
  - mystic-aura-reading.php ✅
  - includes/class-sm-template-renderer.php ✅
  - includes/class-sm-logger.php ✅
  - includes/class-sm-settings.php ✅
  - templates/user-reports.php ✅
  - tests/diagnose.php ✅
- **Status:** COMPLETE - Codebase now uses consistent aura constants

✅ **FIXED: Callback URLs**
- **Solution:** Updated auth callback URL to `/aura-reading/auth/callback` (line 718)
- **Status:** COMPLETE - Authentication flow will work correctly

ℹ️ **INFO: "Palm" References in Documentation**
- **Status:** Some "palm" references remain in documentation/archive files (intentional)
- **Core Code:** All critical "palm" references in active code have been replaced
- **Impact:** No functional impact - documentation can be updated separately if needed

### Work Completed But Not Yet Started:

📋 **Phase 4:** Templates & Content (0/11 tasks)
- HTML reading templates not renamed
- Section titles not updated
- Template content still references palm reading

📋 **Phase 5:** AI Prompts & Question Bank (0/8 tasks)
- No aura-specific prompts created yet
- Question bank not developed
- This is critical for product differentiation

📋 **Phase 6:** Integration & Configuration (0/7 tasks)
- Account Service integration needs verification
- MailerLite group ID needs updating
- Service identifier may need updating

📋 **Phase 7:** Testing (0/18 tasks)
- No testing conducted yet
- E2E tests not updated
- Manual testing not performed

### ✅ Completed - Critical Blockers Fixed (2026-01-02):

**✅ STEP 1: Critical Blockers - ALL FIXED**
1. ✅ Fixed text domain loading
2. ✅ Global replaced `palm_teaser` → `aura_teaser` and `palm_full` → `aura_full` across entire codebase
3. ✅ Fixed callback URLs in class-sm-settings.php
4. ✅ Fixed settings page header

**✅ STEP 2: Constants Cleanup - COMPLETE**
1. ✅ Global search/replaced `SM_PLUGIN_*` → `SM_AURA_*` across all files
2. ✅ Verified no references to old constants remain in active code

**✅ STEP 3: JavaScript Updates - COMPLETE**
1. ✅ Updated reading types in all JS files (script.js, teaser-reading.js, api-integration.js)
2. ⏭️ UI text updates (palm → aura) - to be done in Phase 3/4
3. ⏭️ Image upload instructions - to be done in Phase 3/4

### Recommended Next Steps:

**STEP 4: Test Current State (Priority: HIGH)**
1. Activate plugin and verify no PHP errors
2. Check WordPress admin → Aura Reading settings page loads
3. Verify database tables created successfully
4. Test shortcode renders without errors
5. Check browser console for JavaScript errors

**STEP 5: Complete Phase 3 - Frontend Assets**
1. Update `swipe-template.css` with aura colors
2. Update JavaScript UI text (palm → aura)
3. Update image upload instructions in JavaScript
4. Create/populate `assets/img/` directory
5. Remove any palm-specific imagery

**STEP 6: Continue Sequential Implementation**
1. Move to Phase 4 (Templates & Content) - rename and update HTML templates
2. Phase 5 (AI Prompts & Question Bank) - critical for functionality
3. Phase 6 (Integration & Configuration)
4. Phase 7 (Testing & QA)
5. Phase 8 (Documentation)

### Testing Strategy Before Proceeding:

**DO NOT proceed to new phases until:**
1. Critical blockers are fixed
2. Basic smoke test passes:
   - Plugin activates without errors
   - Database tables created successfully
   - Settings page loads correctly
   - Shortcode renders without errors

**Then test with DevMode:**
1. Use `dev_all` mode to mock all APIs
2. Test complete flow: lead → quiz → image → reading generation
3. Verify reading types work correctly
4. Check for console errors

---

## 🎯 Next Session Action Items

**For the next developer (or Claude) working on this project:**

1. **FIRST:** Read CLAUDE.md for complete workflow instructions
2. **SECOND:** Read this section for current state understanding
3. **THIRD:** Fix the 4 critical blockers listed above
4. **FOURTH:** Run smoke tests to verify plugin works
5. **FIFTH:** Ask user which phase to tackle next

**Remember:**
- Update progress after EVERY task
- Test as you go
- Ask questions when unclear
- Follow patterns in CLAUDE.md

---

**Document Version:** 1.1.0
**Last Updated:** 2026-01-02
**Status:** In Progress - 25% Complete - Critical Blockers Identified

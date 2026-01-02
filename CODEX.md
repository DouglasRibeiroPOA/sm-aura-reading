# Codex Development Guide - Aura Reading Plugin

**Version:** 1.0.0
**Last Updated:** 2026-01-02
**Plugin:** SoulMirror Aura Reading WordPress Plugin

---

## 🎯 Project Overview

This is the **Aura Reading WordPress plugin**, a complete rebrand of the Palm Reading plugin. It's the **exact same technical architecture** with different branding, visuals, and AI prompts.

**Key Principle:**
- Same code structure, same flow, same systems
- Different branding, different colors, different content
- 100% independent from Palm Reading (separate tables, settings, deployments)

---

## 📋 MANDATORY: Before Starting ANY Task

**CRITICAL:** Before you begin working on ANY task, you MUST:

1. **Read the requirements file:**
   ```
   /Users/douglasribeiro/Local Sites/sm-aura-reading/app/public/wp-content/plugins/sm-aura-reading/AURA_READING_REQUIREMENTS.md
   ```

2. **Check the current progress:**
   - Review which phase you're working in
   - Identify what's already complete
   - Understand what's pending

3. **Identify your specific task:**
   - Find the exact task in the checklist
   - Read all sub-items under that task
   - Understand dependencies

4. **Ask questions BEFORE implementing:**
   - If anything is unclear, ask the user
   - If multiple approaches exist, present options
   - If you find inconsistencies, report them

5. **Update progress IMMEDIATELY after completion:**
   - Mark tasks as complete `[x]` in AURA_READING_REQUIREMENTS.md
   - Update the phase progress bar and percentage
   - Update the overall progress tracker
   - Update progress often (every task)
   - Update `CHANGELOG.md` for meaningful changes

6. **Access logs directly when needed:**
   - Do not ask the user to fetch logs
   - Use the plugin debug log and WordPress logs to troubleshoot

---

## ✅ Progress Tracking Protocol

### CRITICAL RULE: Update After EVERY Task (Update Progress Often)

**You MUST update AURA_READING_REQUIREMENTS.md after completing EACH task**, not at the end of a phase. Update progress often to keep status accurate.

#### How to Update Progress:

1. **Mark the completed task:**
   ```markdown
   - [x] Task description
   ```

2. **Update the phase progress:**
   ```markdown
   **Progress:** X/Y tasks complete (ZZ%)
   ```
   - Calculate percentage: `(completed / total) * 100`
   - Round to nearest whole number

3. **Update the phase progress bar:**
   - Each bar is 20 characters wide
   - Calculate filled blocks: `Math.floor(percentage / 5)`
   - Use `▓` for complete, `░` for incomplete

   Example for 62%:
   ```markdown
   [▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░]
   ```

4. **Update overall progress at the top:**
   - Count completed phases (100% = complete phase)
   - Update "Total Progress: X/8 phases complete (Y%)"
   - Update the overall progress bar

---

## 🏗️ Architecture & Best Practices

### File Structure
```
sm-aura-reading/
├── mystic-aura-reading.php         # Main plugin file
├── includes/                        # PHP classes
│   ├── class-sm-database.php       # Database schema
│   ├── class-sm-rest-controller.php # REST API
│   ├── class-sm-ai-handler.php     # OpenAI integration
│   ├── class-sm-reading-service.php # Reading logic
│   └── ...
├── assets/
│   ├── css/                        # Aura-themed styles
│   ├── js/                         # Frontend scripts
│   └── img/                        # Images
├── templates/                       # PHP templates
├── tests/                          # Playwright E2E tests
└── *.html                          # Reading templates
```

### Naming Conventions

**Database:**
- Tables: `wp_sm_aura_*` (e.g., `wp_sm_aura_leads`, `wp_sm_aura_readings`)
- Options: `sm_aura_*` (e.g., `sm_aura_settings`, `sm_aura_db_version`)

**Constants:**
- `SM_AURA_VERSION`, `SM_AURA_PLUGIN_DIR`, `SM_AURA_PLUGIN_URL`, etc.
- **DO NOT use** `SM_PLUGIN_*` or `SM_VERSION` (legacy/palm reading)

**Reading Types:**
- `aura_teaser` (free preview with 2 unlockable sections)
- `aura_full` (complete paid reading)
- **DO NOT use** `palm_teaser` or `palm_full`

**Text Domain:**
- `mystic-aura-reading` (for translations)
- **DO NOT use** `mystic-palm-reading`

**Shortcode:**
- `[soulmirror_aura_reading]`

**Service Slug (Credits):**
- `aura_reading`

**Auth Callback Path:**
- `/aura-reading/auth/callback`

---

## 🎨 Brand Identity: Aura vs Palm

### Visual Differences

| Aspect | Palm Reading | Aura Reading |
|--------|--------------|--------------|
| **Theme** | Dark, mystical, grounded | Light, ethereal, energetic |
| **Primary Color** | Dark purples/blues | Soft lavender/purple (`#A88BEB`) |
| **Background** | Dark with stars | Light mist blue (`#F4F7FB`) |
| **Accent** | Gold/amber tones | Teal (`#6FD6C5`) + Sky blue (`#7BB7E6`) |
| **Effects** | Starfield, sparkles | Soft glow, breathing animation |
| **Imagery** | Palm/hand photos | Upper body/shoulders photo |

### Content Differences

| Aspect | Palm Reading | Aura Reading |
|--------|--------------|--------------|
| **Focus** | Palm lines, physical markers | Energy, aura, emotional state |
| **Input** | Hand photo | Upper body photo (shoulders up) |
| **Analysis** | Lines, mounts, shapes | Posture, presence, energy field |
| **Language** | Mystical, ancient, grounded | Simple, accessible, empowering |
| **Tone** | Dark, mysterious | Light, warm, spiritual |

---

## 🔧 Common Development Tasks

### Task: Update CSS/Styling

1. Read the requirements (Phase 3: Frontend Assets)
2. Use the aura color palette defined in requirements
3. Update CSS variables in relevant files:
   - `assets/css/styles.css`
   - `assets/css/auth.css`
   - `assets/css/reports-listing.css`
   - `assets/css/swipe-template.css`
4. Remove palm-specific effects (stars, dark backgrounds)
5. Add aura-specific effects (glow, breathing animation, gradients)
6. Test in browser
7. Mark task as complete in requirements
8. Update progress often

### Task: Update JavaScript

1. Read the requirements (Phase 3: Frontend Assets)
2. Identify which JS file needs updating:
   - `assets/js/script.js` - Main flow, UI text
   - `assets/js/api-integration.js` - API endpoints, reading types
   - `assets/js/teaser-reading.js` - Section labels, unlock messaging
3. Replace palm references with aura equivalents
4. Update reading types to `aura_teaser` and `aura_full`
5. Update UI text and instructions
6. Test functionality in browser
7. Mark task as complete in requirements
8. Update progress often

### Task: Update Templates

1. Read the requirements (Phase 4: Templates & Content)
2. Rename HTML files:
   - `palm-reading-template-*.html` -> `aura-reading-template-*.html`
3. Update template content:
   - Change color scheme (CSS variables)
   - Update section titles
   - Remove palm imagery
   - Add aura visual elements
   - Update locked section messaging
4. Update PHP templates if needed (templates/*.php)
5. Test rendering
6. Mark task as complete in requirements
7. Update progress often

### Task: Update Backend Logic

1. Read the requirements (Phase 2: Database & Backend)
2. Identify the specific backend file to update
3. Common changes:
   - Replace `palm_teaser`/`palm_full` with `aura_teaser`/`aura_full`
   - Update option keys to use `sm_aura_*` prefix
   - Update service identifiers
   - Update validation logic
4. Test with DevMode
5. Mark task as complete in requirements
6. Update progress often

### Task: Create/Update AI Prompts

1. Read the requirements (Phase 5: AI Prompts & Question Bank)
2. Understand the 6 aura categories:
   - Emotional State & Inner Climate
   - Energy Level & Flow
   - Love, Relationships & Emotional Connection
   - Life Direction, Success & Material Flow
   - Spiritual Memory & Deeper Patterns
   - Intentions, Healing & Growth
3. Write prompts that:
   - Reference energy, aura colors, emotional patterns
   - Analyze posture, presence from upper body photo
   - Use aura-specific language (not palm line analysis)
   - Maintain same JSON structure for compatibility
4. Test with OpenAI
5. Mark task as complete in requirements
6. Update progress often

---

## 🧪 Testing Protocol

### Before Testing Anything:

1. **Verify DevMode configuration** in Settings
2. **Check which APIs are mocked** (OpenAI, MailerLite, Account Service)
3. **Have test data ready** (test images, quiz responses)

### DevMode Options:

- `dev_all` - Mock all APIs (fastest, no external calls)
- `dev_openai_only` - Mock only OpenAI (test with real MailerLite)
- `dev_mailerlite_only` - Mock only MailerLite (test with real OpenAI)
- Production mode - All real APIs

### Testing Checklist:

For each feature you implement:

1. **Unit test:** Does the code logic work?
2. **Integration test:** Does it work with other components?
3. **UI test:** Does it look correct in the browser?
4. **DevMode test:** Does it work with mocked APIs?
5. **Real API test:** Does it work with real integrations? (if applicable)
6. **Mobile test:** Does it work on mobile devices?
7. **Cross-browser test:** Chrome, Firefox, Safari

### E2E Tests (Playwright):

Located in `tests/` directory:
- Update test files when you change functionality
- Run tests with: `npm test` (if configured)
- Update test data to use aura-specific content

---

## 🧾 Logs and Troubleshooting

When a plugin activation or runtime error happens, check logs yourself before asking the user:

- WordPress debug log: `/Users/douglasribeiro/Local Sites/sm-aura-reading/app/public/wp-content/debug.log`
- Plugin-local log (if present): `debug.log` in the plugin root
- Filter for fatals/warnings before and after the activation time

If you see a fatal error, call out the exact file and line number and propose the smallest safe fix.

---

## ⚠️ Common Pitfalls & How to Avoid Them

### 1. Using Wrong Constants

**Wrong:**
```php
define( 'SM_VERSION', '1.0.0' );
$plugin_dir = SM_PLUGIN_DIR;
```

**Correct:**
```php
define( 'SM_AURA_VERSION', '1.0.0' );
$plugin_dir = SM_AURA_PLUGIN_DIR;
```

### 2. Using Wrong Reading Types

**Wrong:**
```php
if ( $reading_type === 'palm_teaser' ) { ... }
```

**Correct:**
```php
if ( $reading_type === 'aura_teaser' ) { ... }
```

### 3. Using Wrong Text Domain

**Wrong:**
```php
__( 'Some text', 'mystic-palm-reading' );
```

**Correct:**
```php
__( 'Some text', 'mystic-aura-reading' );
```

### 4. Hardcoded URLs

**Wrong:**
```php
$callback_url = '/palm-reading/auth/callback';
```

**Correct:**
```php
$callback_url = '/aura-reading/auth/callback';
```

### 5. Forgetting to Update Progress

**Wrong:**
```
Complete 5 tasks, then update progress once
```

**Correct:**
```
Complete 1 task -> Update progress
Complete 1 task -> Update progress
```

---

## 🔍 Code Review Checklist

Before marking ANY task as complete, verify:

- [ ] No "palm" references in changed code (unless intentional for comparison)
- [ ] No "mystic-palm-reading" text domain
- [ ] No `palm_teaser` or `palm_full` reading types
- [ ] Constants use `SM_AURA_*` prefix
- [ ] Database queries use `sm_aura_*` tables
- [ ] Options use `sm_aura_*` keys
- [ ] Colors match aura palette (not palm colors)
- [ ] Language is aura-focused (energy, not palm lines)
- [ ] URLs reference `/aura-reading/` (not `/palm-reading/`)
- [ ] Comments and documentation updated
- [ ] Tested in browser (if UI change)
- [ ] DevMode test passed (if backend change)
- [ ] Progress updated in AURA_READING_REQUIREMENTS.md

---

## 📚 Reference Files

### Must-Read Files:

1. **AURA_READING_REQUIREMENTS.md** - Complete specification and task list
2. **CONTEXT.md** - Codebase context and patterns
3. **CODEX.md** - Technical reference

### Important Source Files:

**Backend:**
- `mystic-aura-reading.php` - Main plugin file
- `includes/class-sm-database.php` - Database schema
- `includes/class-sm-rest-controller.php` - REST API endpoints
- `includes/class-sm-ai-handler.php` - OpenAI integration
- `includes/class-sm-reading-service.php` - Reading generation logic
- `includes/class-sm-settings.php` - Admin settings

**Frontend:**
- `assets/css/styles.css` - Main stylesheet
- `assets/js/script.js` - Main JavaScript
- `templates/container.php` - Main flow container

**Templates:**
- `aura-reading-template-teaser.html` - Free reading template
- `aura-reading-template-full.html` - Paid reading template

---

## 🚀 Workflow: Starting a New Task

### Step-by-Step Process:

1. **Read requirements file**
   - Find your task in the checklist
   - Read all context around it

2. **Ask clarifying questions**
   - If anything is unclear, ask the user
   - If multiple approaches exist, present options

3. **Review existing code**
   - Read the files you'll be modifying
   - Understand the current implementation
   - Check for patterns to follow

4. **Plan your implementation**
   - Outline what needs to change
   - Identify all affected files
   - Consider edge cases

5. **Implement the change**
   - Make minimal, focused changes
   - Follow existing code patterns
   - Add comments if logic is complex

6. **Test your change**
   - Use DevMode for quick testing
   - Test in browser if UI change
   - Verify no regressions

7. **Update progress immediately**
   - Mark task as complete `[x]`
   - Update phase progress
   - Update overall progress
   - Save AURA_READING_REQUIREMENTS.md

8. **Commit your work**
   - Clear, descriptive commit message
   - Reference the task completed

9. **Move to next task**
   - Repeat the process

---

## 🎯 Current Project Status

For the current status and priorities, rely on:

- `AURA_READING_REQUIREMENTS.md` (authoritative checklist)
- `CHANGELOG.md` (recent changes)

---

## 💡 Tips for Success

### Do:
- Read requirements before every task
- Ask questions when unclear
- Update progress immediately
- Test as you go
- Follow existing patterns
- Make focused, minimal changes
- Commit frequently with clear messages

### Don't:
- Skip reading requirements
- Batch progress updates
- Make breaking changes without asking
- Copy-paste palm reading code without updating
- Assume anything works without testing
- Mix palm and aura terminology
- Leave TODO comments without implementing

---

## 🆘 When You Get Stuck

1. **Read the error message carefully**
   - Often contains the solution

2. **Check the requirements file**
   - The answer might be documented

3. **Review similar code in palm reading plugin**
   - Located at: `/Users/douglasribeiro/Local Sites/sm-aura-reading/app/public/wp-content/plugins/sm-palm-reading`

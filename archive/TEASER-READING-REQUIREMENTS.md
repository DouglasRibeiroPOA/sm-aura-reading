# Palm Reading Teaser Report - Requirements Document

**Generated:** 2025-12-16
**Version:** 1.0
**Status:** Ready for Implementation

---

## Executive Summary

Transform the palm reading teaser report from a simple HTML output to a sophisticated, data-driven experience where OpenAI returns structured JSON data that populates a predefined template. This creates a consistent, high-quality user experience while enabling advanced features like partial unlocks, multiple readings per user, and personalized content.

---

## Core Objectives

1. **Structured Data Output**: Replace direct HTML generation with JSON key-value pairs
2. **Template-Based Rendering**: Populate a beautiful, predefined HTML template with AI-generated content
3. **Freemium Model**: Allow users to unlock 1 locked section free, then paywall for full access
4. **Personalization**: Deep integration with user inputs (name, gender, quiz answers, palm photo)
5. **Multi-Reading Support**: Users can generate multiple readings over time
6. **Extensibility**: Design for future modules (Aura, Love, Purpose readings)

---

## Content Structure & Word Count Distribution

### Total Visible Content: 580-680 words

#### 1. Opening Reflection (80-100 words)
- **Purpose**: Set mystical tone, explain what user will see
- **Structure**: 2 paragraphs (~40-50 words each)
- **Content**: Introduction to palm reading as a mirror, explain teaser vs full reading

**OpenAI Fields:**
```json
{
  "opening_reflection_p1": "40-50 word paragraph",
  "opening_reflection_p2": "40-50 word paragraph"
}
```

---

#### 2. Life Foundations (140-160 words) ⭐ **DEEPEST VISIBLE SECTION**
- **Purpose**: Main hook - most detailed free content
- **Structure**: 2-3 paragraphs + core theme insight
- **Content**: Primary palm lines analysis, resilience patterns, emotional nature, growth patterns
- **Palm Reference**: Should reference actual palm features when relevant

**OpenAI Fields:**
```json
{
  "life_foundations_p1": "50-60 word paragraph",
  "life_foundations_p2": "50-60 word paragraph",
  "life_foundations_p3": "40-50 word paragraph (optional)",
  "life_foundations_core_theme": "20-30 word insight"
}
```

---

#### 3. 🔒 Love Patterns (70-80 words preview) - **LOCKED SECTION**
- **Purpose**: Enticing preview of heart line analysis
- **Structure**: 2 short paragraphs
- **Content**: Emotional depth, discernment in relationships, connection patterns
- **Unlock Teaser**: "Locked Insight: Your love pattern + what to stop repeating."

**OpenAI Fields:**
```json
{
  "love_preview_p1": "35-40 word paragraph",
  "love_preview_p2": "35-40 word paragraph",
  "love_locked_teaser": "15-20 word teaser",
  "love_locked_full": "150-200 word deep analysis (revealed on unlock/purchase)"
}
```

---

#### 4. Career & Success (60-70 words + 3 modals)
- **Purpose**: Introduce career/money relationship, present clickable mini-insights
- **Structure**: 1 main paragraph + 3 modal popup insights
- **Content**: Success through alignment, work authenticity

**OpenAI Fields:**
```json
{
  "career_main_paragraph": "60-70 words",
  "modal_love_patterns": "40-50 word mini-insight for modal popup",
  "modal_career_direction": "40-50 word mini-insight for modal popup",
  "modal_life_alignment": "40-50 word mini-insight for modal popup"
}
```

**Modal Popup Example:**
- **Trigger**: User clicks "💖 Love Patterns" symbol
- **Modal Title**: "Love Patterns (Quick Reflection)"
- **Modal Body**: `modal_love_patterns` content

---

#### 5. Personality & Intuition (50-60 words + trait data)
- **Purpose**: Visual trait bars with AI-selected traits
- **Structure**: 1-2 sentence intro + 3 trait visualization bars
- **Trait Selection**: OpenAI picks **3 most aligned traits** from master list

**Master Trait List (OpenAI selects 3):**
1. Intuition
2. Creativity
3. Resilience
4. Emotional Depth
5. Independence
6. Adaptability
7. Empathy
8. Leadership
9. Analytical Thinking
10. Passion
11. Patience
12. Courage
13. Wisdom
14. Authenticity
15. Determination

**OpenAI Fields:**
```json
{
  "personality_intro": "50-60 word paragraph",
  "trait_1_name": "Intuition",
  "trait_1_score": 85,
  "trait_2_name": "Resilience",
  "trait_2_score": 92,
  "trait_3_name": "Creativity",
  "trait_3_score": 78
}
```

**Visual Output:**
```
Intuition     [████████████████░░] 85%
Resilience    [██████████████████░] 92%
Creativity    [███████████████░░░] 78%
```

---

#### 6. 🔒 Challenges & Opportunities (60-70 words preview) - **LOCKED SECTION** *(NEW)*
- **Purpose**: Preview of obstacles and growth areas
- **Structure**: 2 short paragraphs
- **Content**: Current challenges user faces, hidden opportunities

**OpenAI Fields:**
```json
{
  "challenges_preview_p1": "30-35 word paragraph",
  "challenges_preview_p2": "30-35 word paragraph",
  "challenges_locked_teaser": "15-20 word teaser",
  "challenges_locked_full": "150-200 word deep analysis (revealed on unlock/purchase)"
}
```

---

#### 7. 🔒 Current Life Phase (60-70 words preview) - **LOCKED SECTION**
- **Purpose**: Transition period analysis
- **Structure**: 2 short paragraphs
- **Content**: Phase description, what's ending/beginning

**OpenAI Fields:**
```json
{
  "life_phase_preview_p1": "30-35 word paragraph",
  "life_phase_preview_p2": "30-35 word paragraph",
  "life_phase_locked_teaser": "15-20 word teaser",
  "life_phase_locked_full": "150-200 word deep analysis (revealed on unlock/purchase)"
}
```

---

#### 8. 🔒 Timeline/Next 6 Months (60-70 words preview) - **LOCKED SECTION** *(NEW)*
- **Purpose**: Forward-looking timeline and upcoming themes
- **Structure**: 2 short paragraphs
- **Content**: What to expect, timing guidance, key milestones

**OpenAI Fields:**
```json
{
  "timeline_preview_p1": "30-35 word paragraph",
  "timeline_preview_p2": "30-35 word paragraph",
  "timeline_locked_teaser": "15-20 word teaser",
  "timeline_locked_full": "150-200 word deep analysis (revealed on unlock/purchase)"
}
```

---

#### 9. 🔒 Guidance (50-60 words preview) - **LOCKED SECTION**
- **Purpose**: Actionable advice and focus points
- **Structure**: 1 paragraph surface advice
- **Content**: What to pay attention to now

**OpenAI Fields:**
```json
{
  "guidance_preview_p1": "50-60 word paragraph",
  "guidance_locked_teaser": "15-20 word teaser",
  "guidance_locked_full": "150-200 word deep guidance + 3 focus points (revealed on unlock/purchase)"
}
```

---

#### 10. Closing Reflection (70-80 words)
- **Purpose**: Wrap up + CTA to unlock
- **Structure**: 2 paragraphs
- **Content**: Summary of teaser, invitation to reveal full reading

**OpenAI Fields:**
```json
{
  "closing_p1": "35-40 word paragraph",
  "closing_p2": "35-40 word paragraph"
}
```

---

## Locked Sections Summary

**Total Locked Sections: 3-5** (configurable via WordPress admin)

**Default Locked Sections:**
1. 🔒 Love Patterns
2. 🔒 Challenges & Opportunities
3. 🔒 Current Life Phase
4. 🔒 Timeline/Next 6 Months
5. 🔒 Guidance

**Unlock Rules:**
- User can unlock **2 sections free** (their choice)
- The 3 modal popups count as **1 unlock** (unlock all 3 at once)
- Attempting to unlock a **3rd section** → Direct redirect to offerings page
- **Future:** Logged-in users (authenticated/purchased) will see all sections unlocked automatically (no blur)

---

## User Input Integration

### Data Sources for Personalization

1. **Lead Capture Data**
   - `name`: Used throughout content, woven naturally into narrative
   - `email`: Backend only, not in content
   - `gender`: Influences tone, relationship insights, language choice

2. **Palm Photo Data**
   - `palm_image_url`: Analyzed by OpenAI Vision (GPT-4o)
   - `hand_type`: Left or right hand
   - **Usage**: Content should reference actual palm features when relevant
     - Example: "Your heart line shows..." (based on actual visual analysis)

3. **Quiz Answers** (5 questions)
   - Directly inform section content
   - Example: If user answers about career struggles → Career section addresses it
   - Example: If user answers about relationship patterns → Love section reflects those themes
   - **Quiz questions are dynamic** (generated based on lead data)

### Personalization Requirements

✅ **Gender Integration:**
- Different relationship language for different genders
- Tone adjustments (not stereotypical, but thoughtful)
- Inclusive, respectful language

✅ **Quiz-Driven Content:**
- OpenAI MUST analyze quiz answers and reflect them in content
- Sections should feel specifically about the user's actual situation
- Avoid generic horoscope-style content

✅ **Palm Image References:**
- When relevant, mention specific palm features
- Example: "The curve of your heart line suggests..."
- Example: "Your life line's depth indicates..."

✅ **Name Integration:**
- Use user's name naturally 2-3 times throughout reading
- Not just in metadata/header, but woven into narrative
- Example: "Alexandra, your palm reveals..." or "This phase, Alexandra, asks you to..."

---

## OpenAI Prompt Architecture

### Current vs. New Approach

**OLD (Current System):**
```
User → Quiz → OpenAI Prompt → Returns HTML → Display HTML
```

**NEW (Structured Data System):**
```
User → Quiz → OpenAI Prompt → Returns JSON → Populate Template → Display
```

---

### Comprehensive JSON Response Schema

OpenAI must return a complete JSON object with all fields:

```json
{
  "meta": {
    "user_name": "Alexandra",
    "generated_at": "2024-12-17T10:30:00Z",
    "reading_type": "palm_teaser"
  },

  "opening": {
    "reflection_p1": "Your palm holds patterns shaped by experience, intention, and instinct...",
    "reflection_p2": "This teaser captures what rises first — your strongest themes..."
  },

  "life_foundations": {
    "paragraph_1": "The primary lines in your palm suggest resilience shaped through experience...",
    "paragraph_2": "Your emotional nature runs deep, but you don't give access instantly...",
    "paragraph_3": "When you commit to a path or a person, it's intentional.",
    "core_theme": "You evolve through lived experience, not shortcuts."
  },

  "love_patterns": {
    "preview_p1": "Your heart line points to emotional depth paired with discernment...",
    "preview_p2": "There's a pattern here that explains why certain connections feel 'fated'...",
    "locked_teaser": "Your love pattern + what to stop repeating.",
    "locked_full": "[150-200 word deep analysis - only shown after unlock/purchase]"
  },

  "career_success": {
    "main_paragraph": "Your palm suggests success grows best through alignment, not pressure...",
    "modal_love_patterns": "You open slowly but deeply. You're drawn to sincerity over excitement...",
    "modal_career_direction": "There's a strong independence signal here. You thrive when you have ownership...",
    "modal_life_alignment": "Balance becomes essential for you after periods of overgiving..."
  },

  "personality_traits": {
    "intro": "Your hand shape and finger alignment suggest strong intuition paired with practical thinking...",
    "trait_1_name": "Intuition",
    "trait_1_score": 85,
    "trait_2_name": "Resilience",
    "trait_2_score": 92,
    "trait_3_name": "Creativity",
    "trait_3_score": 78
  },

  "challenges_opportunities": {
    "preview_p1": "Your palm reveals tensions between comfort and growth...",
    "preview_p2": "The obstacles you face now are actually doorways...",
    "locked_teaser": "What to release + where to double down.",
    "locked_full": "[150-200 word deep analysis]"
  },

  "life_phase": {
    "preview_p1": "Your palm suggests a transition period — where clarity is forming...",
    "preview_p2": "This phase often feels quiet on the outside and loud on the inside.",
    "locked_teaser": "Your current life chapter + the next step.",
    "locked_full": "[150-200 word deep analysis]"
  },

  "timeline_6_months": {
    "preview_p1": "The next six months hold a pattern of emergence...",
    "preview_p2": "Look for subtle shifts in February and a bigger opening in April...",
    "locked_teaser": "Month-by-month guidance + key decision points.",
    "locked_full": "[150-200 word timeline with specific monthly themes]"
  },

  "guidance": {
    "preview_p1": "Notice where you're forcing certainty. Your hand suggests the next shift comes through patience...",
    "locked_teaser": "Three focus points + what deserves your devotion.",
    "locked_full": "[150-200 word actionable guidance with 3 specific focus points]"
  },

  "closing": {
    "paragraph_1": "This teaser captures what rises first — your strongest themes...",
    "paragraph_2": "If you're ready, reveal the next layer and see the full meaning..."
  }
}
```

---

### OpenAI Prompt Template Structure

**System Prompt:**
```
You are a master palm reader and intuitive guide for SoulMirror. You analyze palm images using traditional palmistry combined with psychological insight. Your readings are:

- Warm, mystical, and emotionally intelligent
- Specific and personalized (never generic horoscope content)
- Grounded in actual palm features visible in the image
- Reflective of the user's quiz answers and stated concerns
- Written in 2nd person ("You...", "Your...")
- Gender-sensitive and inclusive
- 500-700 words total for visible content
- Structured as JSON key-value pairs (never HTML)

CRITICAL RULES:
1. Reference actual palm features from the image when relevant
2. Integrate quiz answers into section content
3. Use the user's name naturally 2-3 times
4. Select 3 personality traits from the master list that best align with the user
5. Write locked section previews as enticing but incomplete
6. Generate full locked content even though it won't be shown immediately
7. Keep word counts within specified ranges per section
8. Maintain mystical yet grounded tone (not fortune-telling, but reflective insight)
```

**User Prompt Template:**
```
Generate a personalized palm reading teaser for:

USER DATA:
- Name: {name}
- Gender: {gender}
- Hand Type: {left/right}
- Palm Image: [base64 or URL]

QUIZ ANSWERS:
1. {question_1}: {answer_1}
2. {question_2}: {answer_2}
3. {question_3}: {answer_3}
4. {question_4}: {answer_4}
5. {question_5}: {answer_5}

TRAIT MASTER LIST (select 3 most aligned):
Intuition, Creativity, Resilience, Emotional Depth, Independence, Adaptability,
Empathy, Leadership, Analytical Thinking, Passion, Patience, Courage, Wisdom,
Authenticity, Determination

INSTRUCTIONS:
- Analyze the palm image and reference specific visible features
- Integrate quiz answers into relevant sections
- Use {name}'s name naturally 2-3 times throughout
- Select 3 traits that best match this user's profile
- Generate ALL content (visible + locked) according to JSON schema
- Maintain word counts as specified
- Keep tone warm, mystical, insightful

Return ONLY valid JSON matching the schema. No markdown, no explanations.
```

---

## Database Architecture

### New Table: `wp_sm_readings`

```sql
CREATE TABLE wp_sm_readings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL,
  reading_type VARCHAR(50) NOT NULL DEFAULT 'palm_teaser',
  content_data LONGTEXT NOT NULL COMMENT 'JSON blob with all reading content',
  unlocked_section TEXT NULL COMMENT 'JSON array of unlocked section names. e.g., [\"love\", \"challenges\"]',
  unlock_count INT DEFAULT 0 COMMENT 'Number of sections unlocked (0, 1, or 2)',
  has_purchased BOOLEAN DEFAULT FALSE COMMENT 'Whether user purchased full access',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_lead_id (lead_id),
  INDEX idx_reading_type (reading_type),
  INDEX idx_created_at (created_at),

  FOREIGN KEY (lead_id) REFERENCES wp_sm_leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Field Descriptions

- **`id`**: Unique reading identifier
- **`lead_id`**: Foreign key to `wp_sm_leads` table
- **`reading_type`**: Supports future modules (`palm_teaser`, `aura_reading`, `love_insight`, etc.)
- **`content_data`**: Complete JSON response from OpenAI (LONGTEXT blob)
- **`unlocked_section`**: JSON array of unlocked section names
  - Possible values: `["love"]`, `["love", "challenges"]`, `["modals", "life_phase"]`
  - Valid section names: `love`, `challenges`, `life_phase`, `timeline`, `guidance`, `modals`
  - `NULL` or `[]` = no unlocks used yet
- **`unlock_count`**:
  - `0` = No unlocks yet
  - `1` = Used 1st free unlock
  - `2` = Used 2nd free unlock (limit reached)
  - `3+` = Reserved for future use
- **`has_purchased`**: Boolean flag for full access purchase (future use - logged-in users)
- **`created_at`**: When reading was generated
- **`updated_at`**: Last modification time

### Multi-Reading Support

- Users can generate **multiple readings** over time
- Each reading is a separate row with unique `id`
- Query for user's reading history: `SELECT * FROM wp_sm_readings WHERE lead_id = ? ORDER BY created_at DESC`
- Latest reading: `SELECT * FROM wp_sm_readings WHERE lead_id = ? ORDER BY created_at DESC LIMIT 1`

---

## Unlock & Paywall Flow

### State Machine

```
STATE 0: Initial View
├─ All visible sections: ✅ shown
├─ All locked sections (3-5): 🔒 blurred with overlay
├─ unlock_count = 0
└─ unlocked_sections = []

STATE 1: First Unlock Used
├─ User clicks "Unlock Love Patterns"
├─ Backend validates: unlock_count < 2
├─ On success:
│   ├─ unlock_count = 1
│   ├─ unlocked_sections = ["love"]
│   ├─ Love section: ✅ revealed
│   └─ Other sections: 🔒 still locked

STATE 2: Second Unlock Used
├─ User clicks "Unlock Life Phase"
├─ Backend validates: unlock_count < 2
├─ On success:
│   ├─ unlock_count = 2
│   ├─ unlocked_sections = ["love", "life_phase"]
│   ├─ Life Phase section: ✅ revealed
│   └─ Other sections: 🔒 still locked

STATE 3: Limit Reached - Redirect
├─ User clicks "Unlock Guidance" (3rd attempt)
├─ Backend validates: unlock_count >= 2 → REJECT
├─ Frontend receives rejection response
├─ Direct redirect to offerings page (configurable URL)
└─ No modal, just immediate redirect

FUTURE STATE: Authenticated/Purchased User
├─ User is logged in OR has purchased
├─ Backend checks authentication/purchase status
├─ All sections: ✅ revealed (no blur overlays)
└─ Unlock buttons hidden
```

---

### Magic Link Flow (UPDATED 2025-12-19)

**Critical Requirement:** Backend database is the single source of truth for unlock state.

#### Scenario 1: Magic Link with Existing Reading
```
User clicks magic link (lead_id + token in URL)
    ↓
Frontend: checkForExistingReading()
    ↓
Backend: /reading/get-by-lead endpoint
    ↓
Check: Does reading exist for lead_id?
    ↓
YES → Return reading HTML (rendered with unlock state from DB)
    ↓
Frontend: Display report immediately
    ↓
Show ONLY sections unlocked in database (unlocked_section field)
    ↓
No modal, no delay, no redirect
```

**Key Points:**
- Remove "credits expired" modal completely
- Load report immediately when existing reading detected
- Template renderer reads `unlocked_section` from database (JSON array)
- If section name in array → show full content
- If section name NOT in array → show preview only with blur overlay
- sessionStorage used ONLY for page refresh detection, not unlock state

#### Scenario 2: New User (No Existing Reading)
```
User clicks magic link OR arrives at page
    ↓
Frontend: checkForExistingReading()
    ↓
Backend: /reading/get-by-lead endpoint
    ↓
Check: Does reading exist for lead_id?
    ↓
NO → Return { exists: false }
    ↓
Frontend: Proceed with normal flow
    ↓
Camera capture → Questionnaire → Generate reading
```

**Key Points:**
- Normal flow unchanged
- User proceeds through camera, quiz, generation
- No blocking, no modal

#### Scenario 3: Page Refresh on Report
```
User is viewing their report
    ↓
User refreshes browser (F5 or cmd+R)
    ↓
Frontend: Detects sessionStorage flag 'sm_reading_loaded'
    ↓
Load existing reading from /reading/get-by-lead
    ↓
Display report with unlock state from database
```

**Key Points:**
- sessionStorage flag prevents unnecessary redirect
- Still reads unlock state from database (not localStorage)
- User stays on report page

#### Scenario 4: Page Refresh with Expired Token
```
User returns to report URL with an expired/invalid token
    ↓
Frontend: Detects sessionStorage and/or URL params and calls /reading/get-by-lead
    ↓
Backend: API call fails with a 403 or other error status
    ↓
Frontend: Catches the error from the failed API call
    ↓
Clear invalid session data (sm_reading_loaded, sm_reading_lead_id, etc. from sessionStorage)
    ↓
Redirect user to the base page URL (window.location.pathname) to restart the flow
```

**Key Points:**
- Provides a graceful fallback instead of showing a broken page.
- Ensures no invalid state persists in the browser.

#### Database as Source of Truth

**Critical Rule:** Frontend NEVER decides unlock state. Always read from database.

**Database Field:**
```php
$reading->unlocked_section // JSON array: ["love", "challenges", "modals"]
```

**Template Renderer Logic:**
```php
$unlocked_sections = json_decode($reading->unlocked_section, true) ?: [];

// For each locked section (love, challenges, life_phase, timeline, guidance)
if (in_array('love', $unlocked_sections)) {
    // Show full content for Love section
    echo esc_html($data['love_patterns']['locked_full']);
} else {
    // Show preview only for Love section
    echo esc_html($data['love_patterns']['preview_p1']);
    echo esc_html($data['love_patterns']['preview_p2']);
    // Apply blur overlay
}
```

**When User Unlocks Section:**
1. User clicks "Unlock Love Patterns" button
2. Frontend sends AJAX to `/reading/unlock` endpoint
3. Backend validates, updates database:
   - `unlock_count` incremented (0 → 1)
   - `unlocked_section` updated: `["love"]`
4. Backend returns success
5. Frontend removes blur overlay for that section only
6. Next page load/refresh reads state from database

---

### Backend Logic

**Function: `attemptUnlock(reading_id, section_name)`**

```php
function attemptUnlock($reading_id, $section_name) {
    // Get current reading state
    $reading = get_reading_by_id($reading_id);

    // Parse currently unlocked sections
    $unlocked = json_decode($reading->unlocked_section, true) ?: [];

    // Check if section is already unlocked
    if (in_array($section_name, $unlocked)) {
        return ['status' => 'already_unlocked', 'section' => $section_name];
    }

    // Check unlock limit (2 free unlocks)
    if ($reading->unlock_count >= 2) {
        // Limit reached - redirect to offerings
        return [
            'status' => 'limit_reached',
            'message' => 'Unlock limit reached',
            'redirect_url' => get_option('sm_offerings_url', '/offerings')
        ];
    }

    // Unlock allowed - increment count and add section
    $unlocked[] = $section_name;
    $new_count = $reading->unlock_count + 1;

    update_reading([
        'id' => $reading_id,
        'unlock_count' => $new_count,
        'unlocked_section' => json_encode($unlocked)
    ]);

    return [
        'status' => 'unlocked',
        'section' => $section_name,
        'unlocks_remaining' => 2 - $new_count
    ];
}
```

**Special Case: Modal Popups**
The 3 modal popups (Love Patterns, Career Direction, Life Alignment) are grouped together:
- If user unlocks "modals", all 3 are unlocked simultaneously
- This counts as **1 unlock** (not 3)
- Store as `"modals"` in `unlocked_sections` array

---

## WordPress Admin Configuration

### Settings Page: Lock Configuration

**Location:** Settings > SoulMirror > Reading Configuration

**Admin UI:**
```
┌─────────────────────────────────────────────────────┐
│ Palm Reading Teaser - Lock Configuration            │
├─────────────────────────────────────────────────────┤
│                                                      │
│ Select which sections should be locked:             │
│                                                      │
│ ☐ Life Foundations                                  │
│ ☑ Love Patterns                                     │
│ ☑ Challenges & Opportunities                        │
│ ☐ Career & Success                                  │
│ ☐ Personality & Intuition                           │
│ ☑ Current Life Phase                                │
│ ☑ Timeline/Next 6 Months                            │
│ ☑ Guidance                                          │
│                                                      │
│ Currently locked sections: 5                        │
│ Recommended: 3-5 locked sections                    │
│                                                      │
│ [Save Configuration]                                │
└─────────────────────────────────────────────────────┘
```

**Default Configuration:**
- Love Patterns: 🔒 LOCKED
- Challenges & Opportunities: 🔒 LOCKED
- Current Life Phase: 🔒 LOCKED
- Timeline/Next 6 Months: 🔒 LOCKED
- Guidance: 🔒 LOCKED

**Saved as:** `wp_options` → `sm_locked_sections` (serialized array)

```php
$locked_sections = get_option('sm_locked_sections', [
    'love',
    'challenges',
    'life_phase',
    'timeline',
    'guidance'
]);
```

---

## Generation & Error Handling

### Timing: When OpenAI is Called

**Trigger Point:** Immediately after quiz completion (Step 11 → Step 12 transition)

**Flow:**
```
Step 11: Final Quiz Question
    ↓
User clicks "Submit"
    ↓
Frontend: Show loading screen ("Analyzing your palm...")
    ↓
Backend: Call OpenAI API
    ↓
[Wait 5-15 seconds for generation]
    ↓
OpenAI returns JSON
    ↓
Backend: Save to wp_sm_readings table
    ↓
Backend: Return success + reading_id
    ↓
Frontend: Redirect to teaser report (Step 12)
    ↓
Display populated template
```

### Error Handling Strategy

**Retry Logic:**
1. **First attempt fails** → Automatic retry (once)
2. **Second attempt fails** → Show error UI with manual regeneration button

**Error UI:**
```
┌────────────────────────────────────────────┐
│  ⚠️  We couldn't generate your reading     │
│                                            │
│  Our mystic systems are momentarily        │
│  clouded. Please try regenerating your     │
│  reading.                                  │
│                                            │
│  [Regenerate My Reading]                   │
└────────────────────────────────────────────┘
```

**Fallback Option (Future Enhancement):**
- If OpenAI fails twice, optionally show generic template with placeholders
- Log failure to admin dashboard for monitoring
- Send alert email to admin if failures exceed threshold

**Backend Implementation:**
```php
function generate_reading($lead_id) {
    $max_retries = 1;
    $attempt = 0;

    while ($attempt <= $max_retries) {
        try {
            $response = call_openai_api($lead_id);

            if ($response['success']) {
                save_reading_to_db($lead_id, $response['data']);
                return ['status' => 'success', 'reading_id' => $reading_id];
            }

        } catch (Exception $e) {
            $attempt++;
            SM_Logger::log('error', 'OPENAI_GENERATION_FAILED', $e->getMessage(), [
                'lead_id' => $lead_id,
                'attempt' => $attempt
            ]);

            if ($attempt > $max_retries) {
                return [
                    'status' => 'error',
                    'message' => 'Generation failed after retries',
                    'allow_regenerate' => true
                ];
            }

            sleep(2); // Wait 2 seconds before retry
        }
    }
}
```

---

## Template Rendering Logic

### Frontend: Template Population

**Template File:** `palm-reading-template-teaser.html`

**Rendering Process:**

1. **Load reading data** from database by `reading_id`
2. **Parse JSON** from `content_data` field
3. **Check unlock status** (which sections are unlocked)
4. **Populate template** with JSON values
5. **Apply lock overlays** to still-locked sections

**Example (PHP):**
```php
// Get reading data
$reading = get_reading_by_id($reading_id);
$data = json_decode($reading->content_data, true);

// Extract locked sections config
$locked_sections = get_option('sm_locked_sections');

// Check unlock status
$user_unlocked = $reading->unlocked_section; // 'love' or NULL
$has_full_access = $reading->has_purchased; // TRUE/FALSE

// Render template
include 'palm-reading-template-teaser.html';
```

**Template Variables (Available in HTML):**
```php
<?php
// Meta
echo esc_html($data['meta']['user_name']); // "Alexandra"

// Opening
echo esc_html($data['opening']['reflection_p1']);
echo esc_html($data['opening']['reflection_p2']);

// Life Foundations (always visible)
echo esc_html($data['life_foundations']['paragraph_1']);
echo esc_html($data['life_foundations']['core_theme']);

// Love (check if unlocked)
if ($has_full_access || $user_unlocked === 'love') {
    // Show full content
    echo esc_html($data['love_patterns']['locked_full']);
} else {
    // Show preview only
    echo esc_html($data['love_patterns']['preview_p1']);
    // Apply blur overlay
}

// Traits
echo esc_html($data['personality_traits']['trait_1_name']); // "Intuition"
echo intval($data['personality_traits']['trait_1_score']); // 85
?>
```

---

## Technical Implementation Checklist

### Phase 1: Database & Schema
- [ ] Create `wp_sm_readings` table migration
- [ ] Add foreign key relationship to `wp_sm_leads`
- [ ] Create indexes for performance
- [ ] Test table creation on fresh install
- [ ] Test table creation on existing install (migration)

### Phase 2: OpenAI Integration
- [ ] Update OpenAI prompt to return structured JSON (not HTML)
- [ ] Implement JSON schema validation
- [ ] Test with GPT-4o Vision model
- [ ] Add retry logic (1 automatic retry)
- [ ] Implement error handling
- [ ] Test with various palm images
- [ ] Test with different quiz answer combinations
- [ ] Validate word counts per section
- [ ] Validate trait selection (3 from 15)

### Phase 3: Template Updates
- [ ] Update `palm-reading-template-teaser.html` with new sections
- [ ] Add "Challenges & Opportunities" section
- [ ] Add "Timeline/Next 6 Months" section
- [ ] Update trait bars to accept dynamic trait names
- [ ] Add blur overlays for locked sections
- [ ] Add unlock buttons for each locked section
- [ ] Remove Reading ID from UI (keep in backend only)
- [ ] Test responsive design (mobile/tablet/desktop)

### Phase 4: Unlock Logic
- [ ] Create `attemptUnlock()` backend function
- [ ] Implement state machine (0 → 1 → paywall → 5)
- [ ] Create unlock confirmation modal
- [ ] Create paywall modal (Option A copy)
- [ ] Implement redirect to offerings page
- [ ] Update database on unlock
- [ ] Test free unlock flow
- [ ] Test paywall trigger
- [ ] Test post-purchase full unlock

### Phase 5: WordPress Admin
- [ ] Create Settings page: Reading Configuration
- [ ] Add checkboxes for locked section selection
- [ ] Save configuration to `wp_options`
- [ ] Load configuration in template rendering
- [ ] Add admin notices/documentation
- [ ] Test configuration changes

### Phase 6: Multi-Reading Support
- [ ] Create "My Readings" page/section
- [ ] Display list of user's past readings
- [ ] Allow viewing any past reading
- [ ] Add "Generate New Reading" button
- [ ] Test multiple readings per user
- [ ] Test reading history display

### Phase 7: Testing & QA
- [ ] Test full flow end-to-end
- [ ] Test with various user inputs (gender, quiz answers)
- [ ] Test palm image analysis accuracy
- [ ] Test unlock states (0, 1, paywall, full)
- [ ] Test error handling (OpenAI failures)
- [ ] Test database performance with 1000+ readings
- [ ] Security audit (SQL injection, XSS, nonce verification)
- [ ] Mobile testing (iOS Safari, Android Chrome)
- [ ] Cross-browser testing

---

## Security Considerations

### Input Validation
- Sanitize all user inputs before sending to OpenAI
- Validate `reading_id` exists and belongs to current user
- Validate `section_name` is in allowed list
- Rate limit regeneration attempts (max 3 per hour per user)

### Output Escaping
- Escape all JSON values when rendering template
- Use `wp_kses()` for any HTML content from OpenAI
- Never trust OpenAI output as safe HTML

### Database Security
- Use prepared statements for all queries
- Foreign key constraints enforce data integrity
- Cascade delete readings when lead is deleted
- Index sensitive queries for performance

### API Key Protection
- Store OpenAI API key encrypted in `wp_options`
- Never expose API key to frontend
- Log API usage for cost monitoring
- Implement rate limiting to prevent abuse

---

## Performance Optimization

### Caching Strategy
- **Reading data**: No caching needed (already in database)
- **Locked sections config**: Cache in transient (1 hour)
- **Template rendering**: No caching (personalized per user)

### Database Optimization
- Index `lead_id` for fast user queries
- Index `created_at` for sorting reading history
- LONGTEXT for JSON blob (handles large content)
- Regular cleanup of old readings (optional, configurable)

### OpenAI Optimization
- Single API call generates all content (not multiple calls)
- Estimated tokens per reading: 1,500-2,000 tokens
- GPT-4o cost: ~$0.03-0.05 per reading (as of 2024)
- Monitor token usage via logging

---

## Future Enhancements

### Phase 2 Features (Post-MVP)
1. **Downloadable PDF Report** (full version for paid users)
2. **Email Delivery** of reading to user's inbox
3. **Social Sharing** with custom preview cards
4. **Reading Comparison** (compare multiple readings over time)
5. **Advanced Analytics** (admin dashboard showing popular unlock choices)

### Extensibility for Other Modules
This architecture supports future reading types:
- **Aura Reading**: Same database structure, different `reading_type`
- **Love Insights**: Same unlock logic, different sections
- **Purpose Discovery**: Same template approach, different content

**Key:** `reading_type` field enables module separation while reusing infrastructure.

---

## Acceptance Criteria

### Must Have (MVP)
- ✅ OpenAI returns structured JSON (not HTML)
- ✅ Template populated with JSON values
- ✅ 5 locked sections (Love, Challenges, Life Phase, Timeline, Guidance)
- ✅ User can unlock 1 section free
- ✅ Paywall triggers on 2nd unlock attempt
- ✅ Full unlock after purchase
- ✅ Content personalized with name, gender, quiz answers, palm features
- ✅ 3 dynamic trait bars (selected from 15-trait master list)
- ✅ 500-700 word visible content
- ✅ Multi-reading support (users can generate multiple readings)
- ✅ Reading ID hidden from UI (backend only)
- ✅ WordPress admin can configure locked sections

### Should Have
- ✅ Automatic retry on OpenAI failure
- ✅ Manual regeneration option
- ✅ Error logging and monitoring
- ✅ Mobile-responsive design
- ✅ 3 modal popup insights

### Nice to Have (Future)
- ⏳ PDF download
- ⏳ Email delivery
- ⏳ Social sharing
- ⏳ Reading history comparison
- ⏳ Admin analytics dashboard

---

## Success Metrics

### User Engagement
- **Unlock rate**: % of users who unlock 1 free section
- **Paywall conversion**: % who click "View Full Reading" after paywall
- **Section popularity**: Which sections get unlocked most (Love? Guidance? Timeline?)
- **Reading regeneration**: How many users generate multiple readings

### Content Quality
- **Reading length**: Verify 500-700 words visible content
- **Personalization score**: Manual review of 10 sample readings for relevance
- **Error rate**: <2% OpenAI generation failures

### Performance
- **Generation time**: <15 seconds average
- **Database query time**: <100ms per reading load
- **Page load time**: <2 seconds for teaser page

---

## Integration Points

### Existing Plugin Components

**Modified Files:**
- `includes/class-sm-rest-controller.php` - Add new endpoint for reading generation
- `includes/class-sm-openai-service.php` - Update prompt structure for JSON output
- `assets/js/script.js` - Handle unlock button clicks, paywall modal
- `palm-reading-template-teaser.html` - New template structure

**New Files:**
- `includes/class-sm-reading-service.php` - Reading CRUD operations
- `includes/class-sm-unlock-handler.php` - Unlock logic and state management
- `admin/class-sm-reading-settings.php` - WordPress admin settings page
- `migrations/create-readings-table.php` - Database migration

**REST Endpoints:**
- `POST /wp-json/soulmirror/v1/reading/generate` - Generate new reading
- `POST /wp-json/soulmirror/v1/reading/unlock` - Unlock a section
- `GET /wp-json/soulmirror/v1/reading/{id}` - Get reading by ID
- `GET /wp-json/soulmirror/v1/readings` - Get user's reading history

---

## NEW – Questionnaire Flow & State Management (Documentation Only)

**Purpose:** Improve mobile UX and prevent accidental loss of progress; aligns with magic-link-style persistence without authentication.

### Two-Phase Flow
- **Phase 1 – Entry Page:** Animated hand + email input only; email required to proceed.
- **Phase 2 – Questionnaire:** Remaining questions (name, gender, age, consent) shown after email step.
- Email is entered only on Phase 1; reuse unless user navigates back and changes it.

### Browser State Persistence
- Store in browser (localStorage/sessionStorage):
  - Current step index
  - Email
  - Questionnaire answers
- Must survive refresh, back/forward navigation, and browser close/reopen on the same device.
- Behavior mirrors magic link UX continuity but does **not** add auth.

### Safeguards
- Persistence is UX-only; must not:
  - Allow multiple free reports
  - Bypass Lead ID / OTP / credit limits
- Lead ID and OTP remain single-use.

### Open Questions (to resolve before implementation)
- Should stored state expire (24h vs 7d)?
- Should state reset after a report is generated?
- Should switching devices reset the flow?
- Should browser storage be cleared manually after completion?

---

## Documentation & Handoff

### Developer Documentation
- Update `@technical-requirements.md` with new reading architecture
- Update `@CLAUDE.md` with teaser reading context
- Update `@CODEX.md` with implementation patterns
- Move `UI-UX-REARCHITECTURE-REQUIREMENTS.md` to archive

### User Documentation
- Admin guide: How to configure locked sections
- Support guide: Common user questions about unlocking
- Troubleshooting: What to do if reading generation fails

---

## Timeline Estimate

**Total Development Time: 3-4 weeks**

### Week 1: Foundation
- Database schema and migration
- OpenAI prompt restructuring
- JSON response validation
- Basic template population

### Week 2: Core Features
- Unlock logic implementation
- Paywall modal
- Admin settings page
- Error handling and retry logic

### Week 3: Polish & Testing
- Template refinement
- Mobile responsiveness
- Multi-reading support
- Security audit

### Week 4: QA & Launch
- End-to-end testing
- Performance optimization
- Documentation
- Production deployment

---

## Questions & Clarifications

*This section will be updated as implementation questions arise.*

---

**Document Status:** ✅ Complete - Ready for Implementation
**Next Steps:** Review with stakeholder → Begin Phase 1 (Database & Schema)
**Point of Contact:** Development Team Lead

---

**END OF REQUIREMENTS DOCUMENT**

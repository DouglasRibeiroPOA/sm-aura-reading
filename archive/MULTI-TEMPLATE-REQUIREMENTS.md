# MULTI-TEMPLATE-REQUIREMENTS.md

Multi-Template System Enhancement Requirements

**Version:** 1.0.0
**Date:** 2025-12-27
**Status:** Draft - Awaiting Approval

---

## 🎯 Executive Summary

**Goal:** Add swipeable card-based template as an alternative display option for palm reading reports.

**Approach:** Surgical, minimal-impact implementation
- ✅ Create 2 new standalone template files (HTML with inline CSS/JS)
- ✅ Add 1 admin setting (radio button to choose template)
- ✅ Modify template loading logic (3-5 lines of code)
- ❌ NO changes to existing templates
- ❌ NO changes to OpenAI API calls, REST endpoints, or database
- ❌ NO complex refactoring or new classes

**Impact:** Zero impact on existing functionality. New templates are a pure presentation-layer option.

**Time Estimate:** 4-6 hours total

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Business Goals](#business-goals)
3. [Template Types](#template-types)
4. [Admin Settings Interface](#admin-settings-interface)
5. [Technical Architecture](#technical-architecture)
6. [Content Mapping](#content-mapping)
7. [Locking Mechanism](#locking-mechanism)
8. [Template Selection Logic](#template-selection-logic)
9. [User Experience Requirements](#user-experience-requirements)
10. [Security & Performance](#security--performance)
11. [Testing Requirements](#testing-requirements)
12. [Backward Compatibility](#backward-compatibility)
13. [Implementation Phases](#implementation-phases)
14. [Success Criteria](#success-criteria)

---

## Overview

### Problem Statement

The current palm reading plugin supports only one template design for displaying reading reports (both teaser and paid). We want to offer an alternative swipeable card-based template as an option.

### Proposed Solution

Create **two NEW standalone template files** (swipeable teaser and swipeable paid) that:
- Are completely self-contained (CSS, JS, HTML in template files)
- Use the EXACT SAME data that existing templates use
- Do NOT require changes to existing business logic, APIs, or database
- Can be selected via a simple admin setting (radio button)

**Key Principle:** This is a **presentation-layer-only change**. The new templates are just a different way to display the same data.

### Scope

**IN SCOPE:**
- Create 2 new template files: `palm-reading-template-swipe-teaser.html` and `palm-reading-template-swipe-full.html`
- Add admin setting (radio button) to choose between templates
- Simple template selection logic (load different file based on setting)
- Both templates use IDENTICAL data structure as existing templates

**OUT OF SCOPE:**
- Changes to existing templates (they remain untouched)
- Changes to OpenAI API calls (reuse existing)
- Changes to REST endpoints (reuse existing)
- Changes to database schema (no new tables/columns)
- Changes to unlock mechanism logic (reuse existing)
- Complex template manager classes (keep it simple)
- Heavy refactoring (surgical changes only)

**CRITICAL CONSTRAINT:** Existing functionality MUST work exactly as it does now. The new templates are an OPTION, not a replacement.

---

## Business Goals

1. **Improved User Experience** - Offer modern, engaging presentation options
2. **Increased Engagement** - Card-based swipe interface may increase reading completion rates
3. **Flexibility** - Easy switching between templates based on analytics/feedback
4. **Mobile Optimization** - Swipeable cards particularly well-suited for mobile devices
5. **Future Scalability** - Foundation for additional template options

### Key Performance Indicators (KPIs)

- **Reading Completion Rate** - Track % of users who view all sections
- **Time on Page** - Average time spent on reading report
- **Unlock Rate** - % of users who unlock premium sections (teaser reports)
- **Mobile vs Desktop Usage** - Track template performance by device type

---

## Template Types

### 1. Traditional Template (Existing)

**Display Name:** "Traditional Scrolling Layout"
**Internal Slug:** `traditional`
**File:** `palm-reading-template-teaser.html` (teaser), `palm-reading-template-full.html` (paid)

**Characteristics:**
- Single-page vertical scroll
- All sections visible at once
- Section headers with navigation
- Premium lock overlays for teaser reports
- Unlock buttons within locked sections
- Back to Dashboard button (paid reports)

**Current Status:** Fully implemented and production-ready

---

### 2. Swipeable Card Template (New)

**Display Name:** "Swipeable Card Interface"
**Internal Slug:** `swipeable-cards`
**File:** `palm-reading-template-swipe-teaser.html` (teaser), `palm-reading-template-swipe-full.html` (paid)

**Characteristics:**
- Card-based interface (one section per card)
- Swipe left/right navigation (mobile) and arrow buttons (desktop)
- Progress indicator at top
- Trait bars with animations
- Premium lock overlays for locked cards (teaser)
- Smooth transitions between cards
- Swipe instruction overlay on first visit

**Reference File:** `swipeTemplate.html` (standalone prototype)

**Sections/Cards:**

1. **Card 1: Introduction**
   - Icon: Hand (`fa-hand-paper`)
   - Title: "Your SoulMirror Reading"
   - Subtitle: "A journey through your palm's story"
   - Content: Welcome message + navigation hint

2. **Card 2: Life Path** (Maps to "Your Life Path & Core Identity")
   - Icon: Road (`fa-road`)
   - Title: "Your Life Path"
   - Subtitle: "Resilience through experience"
   - Content: Life line analysis + resilience insights
   - Quote: Included
   - **Teaser:** Unlocked by default

3. **Card 3: Personality & Intuition** (Maps to "How You Think, Feel & Decide")
   - Icon: Brain (`fa-brain`)
   - Title: "Personality & Intuition"
   - Subtitle: "How you think and decide"
   - Content: Head line analysis + personality traits
   - Trait Bars: Intuition (85%), Creativity (78%), Resilience (92%), Practicality (76%)
   - **Teaser:** Unlocked by default

4. **Card 4: Love & Relationships** (Maps to "Your Relationship & Love Style")
   - Icon: Heart (`fa-heart`)
   - Title: "Love & Relationships"
   - Subtitle: "Patterns of connection"
   - Content: Heart line analysis + relationship patterns
   - **Teaser:** Unlocked by default

5. **Card 5: Career & Success** (Maps to "Success & Money")
   - Icon: Briefcase (`fa-briefcase`)
   - Title: "Career & Success"
   - Subtitle: "Your path to fulfillment"
   - Content: Fate line analysis + career guidance
   - Quote: Included
   - **Teaser:** LOCKED (Premium - unlockable in teaser mode)

6. **Card 6: Challenges & Growth** (Maps to "Challenges & Hidden Opportunities")
   - Icon: Mountain (`fa-mountain`)
   - Title: "Challenges & Growth"
   - Subtitle: "Where you're being stretched"
   - Content: Challenge analysis + growth opportunities
   - **Teaser:** LOCKED (Premium - unlockable in teaser mode)

7. **Card 7: Premium Sections - Deep Relationship Analysis** (Maps to locked section)
   - Icon: Heart with Pulse (`fa-heartbeat`)
   - Title: "Deep Relationship Analysis"
   - Subtitle: "Unlock deeper insights"
   - Content: Premium locked content placeholder
   - **Teaser:** LOCKED (Premium - unlockable in teaser mode)
   - **Note:** Add 4 more cards for remaining premium sections (12-Month Timeline, Life Purpose, Shadow Work, Practical Guidance)

8. **Card 8: Premium Sections - 12-Month Extended Timeline**
   - Icon: Calendar (`fa-calendar-alt`)
   - Title: "12-Month Extended Timeline"
   - **Teaser:** LOCKED (Premium)

9. **Card 9: Premium Sections - Life Purpose & Soul Mission**
   - Icon: Compass (`fa-compass`)
   - Title: "Life Purpose & Soul Mission"
   - **Teaser:** LOCKED (Premium)

10. **Card 10: Premium Sections - Shadow Work & Transformation**
    - Icon: Moon (`fa-moon`)
    - Title: "Shadow Work & Transformation"
    - **Teaser:** LOCKED (Premium)

11. **Card 11: Premium Sections - Practical Guidance & Action Plan**
    - Icon: List Check (`fa-list-check`)
    - Title: "Practical Guidance & Action Plan"
    - **Teaser:** LOCKED (Premium)

12. **Card 12: Conclusion**
    - Icon: Eye (`fa-eye`)
    - Title: "Reading Complete"
    - Subtitle: "Your journey in full"
    - Content: Closing message
    - Action Buttons: Restart Reading, Share Insights (teaser) OR Back to Dashboard (paid)

**Design Requirements:**
- Maintain exact design from `swipeTemplate.html`
- Scrollable content within cards with fade-at-bottom effect
- Progress bar at top (updates as user swipes)
- Responsive design (optimized for mobile and desktop)
- Smooth animations and transitions
- Haptic feedback on mobile (if supported)

---

## Admin Settings Interface

### Location

**WordPress Admin → Palm Reading → Settings → Report Templates (new tab/section)**

Alternatively, add to existing settings page:

**WordPress Admin → Palm Reading → Settings**
- New section: "Report Template Settings"

### UI Design

```
┌─────────────────────────────────────────────────────────────┐
│ Report Template Settings                                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Choose the template design for palm reading reports:       │
│                                                             │
│ ○ Traditional Scrolling Layout                              │
│   Single-page vertical scroll with all sections visible    │
│   Best for: Desktop users, detailed reading                │
│                                                             │
│ ● Swipeable Card Interface                                  │
│   Card-based swipe navigation (one section per card)       │
│   Best for: Mobile users, engaging experience              │
│                                                             │
│ [ Save Settings ]                                           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Settings Storage

**Option Name:** `sm_report_template`
**Values:**
- `traditional` (default)
- `swipeable-cards`

**Storage:** WordPress `wp_options` table via `update_option()` and `get_option()`

### Default Value

**Default:** `traditional` (to maintain backward compatibility)

### Validation

- Only accept `traditional` or `swipeable-cards`
- Sanitize input using `sanitize_text_field()`
- Validate against whitelist before saving

---

## Technical Architecture

### File Structure (Minimal Changes)

```
sm-palm-reading/
├── includes/
│   └── class-sm-settings.php                  # MODIFIED - Add ONE setting + radio buttons
│
├── templates/
│   ├── palm-reading-template-teaser.html      # UNTOUCHED - Existing traditional teaser
│   ├── palm-reading-template-full.html        # UNTOUCHED - Existing traditional paid
│   ├── palm-reading-template-swipe-teaser.html  # NEW - Swipeable teaser (self-contained)
│   └── palm-reading-template-swipe-full.html    # NEW - Swipeable paid (self-contained)
│
└── (Existing rendering logic - ONE line change to load different template file)
```

**No new classes. No complex refactoring. Just:**
1. Two new template files (standalone HTML with inline CSS/JS)
2. One new admin setting (`sm_report_template`)
3. One tiny change to template loading logic

---

### Surgical Change #1: Admin Setting

**File:** `includes/class-sm-settings.php`

**Change:** Add ONE radio button setting to existing settings page

```php
// Add this to existing settings page
public function render_template_settings() {
    $current = get_option('sm_report_template', 'traditional');
    ?>
    <h3>Report Template</h3>
    <label>
        <input type="radio" name="sm_report_template" value="traditional"
               <?php checked($current, 'traditional'); ?>>
        Traditional Scrolling Layout
    </label>
    <br>
    <label>
        <input type="radio" name="sm_report_template" value="swipeable"
               <?php checked($current, 'swipeable'); ?>>
        Swipeable Card Interface
    </label>
    <?php
}

// Register the option
register_setting('sm_settings', 'sm_report_template', array(
    'type' => 'string',
    'default' => 'traditional',
    'sanitize_callback' => function($value) {
        return in_array($value, ['traditional', 'swipeable']) ? $value : 'traditional';
    }
));
```

---

### Surgical Change #2: Template Loading

**Find where templates are currently loaded** (likely in `class-sm-rest-controller.php` or similar)

**Current code probably looks like:**
```php
// OLD CODE (find this pattern)
if ($reading_type === 'palm_full') {
    $template_file = 'palm-reading-template-full.html';
} else {
    $template_file = 'palm-reading-template-teaser.html';
}
```

**Change to:**
```php
// NEW CODE (simple template selection)
$template_style = get_option('sm_report_template', 'traditional');

if ($reading_type === 'palm_full') {
    $template_file = ($template_style === 'swipeable')
        ? 'palm-reading-template-swipe-full.html'
        : 'palm-reading-template-full.html';
} else {
    $template_file = ($template_style === 'swipeable')
        ? 'palm-reading-template-swipe-teaser.html'
        : 'palm-reading-template-teaser.html';
}
```

**That's it!** No new classes, no complex logic, just load a different file based on setting.

---

## Content Mapping

### Section Mapping: Traditional → Swipeable Cards

| Traditional Section | Swipeable Card | Status (Teaser) |
|---------------------|----------------|-----------------|
| Introduction | Card 1: Introduction | Unlocked |
| Your Life Path & Core Identity | Card 2: Life Path | Unlocked |
| How You Think, Feel & Decide | Card 3: Personality & Intuition | Unlocked |
| Your Relationship & Love Style | Card 4: Love & Relationships | Unlocked |
| Success & Money | Card 5: Career & Success | LOCKED (unlockable) |
| Challenges & Hidden Opportunities | Card 6: Challenges & Growth | LOCKED (unlockable) |
| Deep Relationship Analysis | Card 7: Deep Relationship Analysis | LOCKED (unlockable) |
| 12-Month Extended Timeline | Card 8: 12-Month Timeline | LOCKED (unlockable) |
| Life Purpose & Soul Mission | Card 9: Life Purpose | LOCKED (unlockable) |
| Shadow Work & Transformation | Card 10: Shadow Work | LOCKED (unlockable) |
| Practical Guidance & Action Plan | Card 11: Practical Guidance | LOCKED (unlockable) |
| Conclusion | Card 12: Conclusion | Unlocked |

### Data Structure (Unchanged)

The existing reading data structure remains the same:

```json
{
  "reading_id": 123,
  "reading_type": "palm_teaser|palm_full",
  "content_data": {
    "introduction": { "content": "..." },
    "life_path": { "content": "...", "locked": false },
    "personality": { "content": "...", "locked": false },
    "relationships": { "content": "...", "locked": false },
    "career": { "content": "...", "locked": true },
    "challenges": { "content": "...", "locked": true },
    "deep_relationship": { "content": "...", "locked": true },
    "timeline": { "content": "...", "locked": true },
    "life_purpose": { "content": "...", "locked": true },
    "shadow_work": { "content": "...", "locked": true },
    "action_plan": { "content": "...", "locked": true },
    "conclusion": { "content": "..." }
  },
  "unlocked_sections": ["life_path", "personality", "relationships"],
  "unlocks_remaining": 2
}
```

**Template Population Logic:**
- Each template reads the same data structure
- Traditional template displays sections vertically
- Swipeable template displays sections as cards
- Both respect `locked` and `unlocked_sections` properties

---

## Locking Mechanism

### Teaser Reports (Both Templates)

**Default Unlocked Sections:**
1. Introduction
2. Life Path
3. Personality & Intuition
4. Love & Relationships
5. Conclusion (always unlocked)

**Default Locked Sections (Unlockable):**
1. Career & Success
2. Challenges & Growth
3. Deep Relationship Analysis
4. 12-Month Extended Timeline
5. Life Purpose & Soul Mission
6. Shadow Work & Transformation
7. Practical Guidance & Action Plan

**Unlock Behavior:**
- User gets 2 free unlocks
- Unlock buttons appear on locked sections/cards
- After 2 unlocks used, third attempt redirects to offerings page
- Unlocked sections stored in database (`unlocked_sections` JSON array)

### Traditional Template Locking

**Existing behavior (no changes):**
- Premium lock overlay with blur effect
- "Unlock This Section" button
- Yellow premium insight boxes
- Unlock counter display

### Swipeable Card Template Locking

**New behavior:**
- Locked cards show blur overlay on card content
- Premium lock icon overlay centered on card
- "Unlock This Section" button at bottom of card
- Lock overlay respects scrollable content area
- Same unlock counter logic as traditional template

**Lock Overlay CSS (Swipeable):**

```css
/* Lock overlay for cards */
.card-locked-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(139, 92, 246, 0.03));
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: 28px;
    padding: 30px;
}

.lock-icon {
    font-size: 48px;
    color: #7c3aed;
    margin-bottom: 20px;
}

.unlock-btn {
    padding: 14px 28px;
    background: linear-gradient(135deg, #7c3aed, #8b5cf6);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.2);
    transition: all 0.3s ease;
}

.unlock-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(124, 58, 237, 0.3);
}

.unlocks-remaining {
    margin-top: 15px;
    font-size: 14px;
    color: #666;
}
```

### Paid Reports (Both Templates)

**All sections unlocked, no lock overlays displayed**

---

## Template Selection Logic

### Where Template is Selected

**Location:** During report rendering in `class-sm-rest-controller.php` or template rendering logic

**Flow:**

1. User requests reading report (teaser or paid)
2. Backend determines reading type (`palm_teaser` or `palm_full`)
3. `SM_Template_Manager::get_active_template()` retrieves admin-selected template
4. `SM_Template_Manager::get_template_path($reading_type)` returns appropriate template file
5. Template is loaded and populated with reading data
6. Rendered HTML is returned to frontend

### Pseudo-code

```php
// In class-sm-rest-controller.php or rendering logic

public function render_reading_report($reading_id, $reading_type) {
    // Get reading data from database
    $reading_data = $this->get_reading_data($reading_id);

    // Get active template (from admin settings)
    $template = SM_Template_Manager::get_active_template();

    // Render using appropriate template
    $html = SM_Template_Manager::render_template($reading_type, $reading_data);

    // Return rendered HTML
    return $html;
}
```

### Template Flag (Self-Contained)

Each template file can include a data attribute or JavaScript flag for client-side logic:

**Traditional Template:**
```html
<div id="sm-reading-container" data-template="traditional">
    <!-- Content -->
</div>
```

**Swipeable Template:**
```html
<div id="sm-reading-container" data-template="swipeable-cards">
    <!-- Cards -->
</div>

<script>
const TEMPLATE_TYPE = 'swipeable-cards';
// Template-specific JavaScript logic
</script>
```

---

## User Experience Requirements

### Swipeable Card Template UX

#### Navigation

**Mobile (Touch):**
- Swipe left → Next card
- Swipe right → Previous card
- Tap left 30% of screen → Previous card
- Tap right 70% of screen → Next card

**Desktop (Mouse/Keyboard):**
- Click "Next" button → Next card
- Click "Previous" button → Previous card
- Arrow Right key → Next card
- Arrow Left key → Previous card
- Spacebar → Next card
- PageDown → Next card
- PageUp → Previous card

**Accessibility:**
- Keyboard navigation fully supported
- Screen reader announces card number and title
- Focus management between cards
- Skip navigation option

#### Visual Feedback

- **Progress Bar:** Shows current position (e.g., 3/12 cards)
- **Card Transitions:** Smooth slide animations (0.5s duration)
- **Haptic Feedback:** Subtle vibration on mobile when changing cards
- **End of Cards:** Double vibration when trying to go beyond first/last card

#### First-Time Experience

**Swipe Instruction Overlay:**
- Shows on first visit (dismissed with "Begin Reading" button)
- Explains swipe/navigation mechanics
- Can be dismissed by clicking background or button
- Does not show again (uses sessionStorage flag)

#### Scrollable Content

**Within Cards:**
- Card content scrollable if exceeds card height
- Fade-at-bottom effect when content is scrollable
- Scroll indicator (subtle gradient fade)
- Smooth scroll behavior

#### Animations

**Card Entrance:**
- Fade-in + slide-up animation (0.7s)
- Delayed slightly for smooth effect

**Trait Bars (Card 3):**
- Animate width on card appearance
- Stagger animations (100ms delay between bars)
- Elastic easing for playful effect

**Quote Blocks:**
- Subtle fade-in with delay

---

## Security & Performance

### Security Considerations

1. **Template Selection Validation**
   - Whitelist validation for `sm_report_template` option
   - Sanitize all user inputs
   - Prevent template injection attacks

2. **File Path Validation**
   - Validate template file paths before loading
   - Prevent directory traversal attacks
   - Use `plugin_dir_path()` for safe path construction

3. **Content Escaping**
   - Escape all dynamic content in templates
   - Use `esc_html()`, `esc_attr()`, `wp_kses_post()` as appropriate
   - Sanitize reading data before rendering

4. **Nonce Verification**
   - Require nonces for admin settings save
   - Verify nonces on unlock requests

### Performance Considerations

1. **Template Caching**
   - Cache template file contents (transient or object cache)
   - Invalidate cache when template changes
   - Reduce file I/O operations

2. **Asset Loading**
   - Load swipeable template CSS/JS only when that template is active
   - Minify CSS and JavaScript files
   - Use WordPress `wp_enqueue_script()` and `wp_enqueue_style()` properly

3. **Lazy Loading**
   - Consider lazy-loading card content (future optimization)
   - Preload next/previous cards for smooth navigation

4. **Mobile Optimization**
   - Test on various mobile devices
   - Optimize image sizes
   - Reduce JavaScript execution time

---

## Testing Requirements

### Automated Testing

**New E2E Tests (Playwright):**

1. **Template Selection (Admin)**
   - Test changing template in admin settings
   - Verify template saves correctly
   - Test validation (invalid values rejected)

2. **Swipeable Template - Teaser Report**
   - Test card navigation (swipe, buttons, keyboard)
   - Test locked sections display correctly
   - Test unlock mechanism on swipeable cards
   - Test progress bar updates
   - Test first-time instruction overlay

3. **Swipeable Template - Paid Report**
   - Test all cards display without locks
   - Test "Back to Dashboard" button
   - Test card navigation
   - Test content population

4. **Traditional Template - Regression**
   - Verify traditional template still works
   - Test all existing functionality
   - Test teaser and paid reports

5. **Template Switching**
   - Generate reading with Traditional template
   - Switch to Swipeable template in admin
   - Verify same reading displays correctly in new template
   - Switch back and verify again

**Test Files:**
```
tests/
├── e2e-swipeable-template.spec.js       # NEW - Swipeable template tests
├── e2e-template-switching.spec.js       # NEW - Template switching tests
├── e2e-full-flow.spec.js                # MODIFIED - Add template checks
└── palm-reading-flow.spec.js            # MODIFIED - Add template checks
```

### Manual Testing Checklist

**Admin Settings:**
- [ ] Template selection radio buttons display correctly
- [ ] Default template is Traditional
- [ ] Can switch to Swipeable and save
- [ ] Can switch back to Traditional and save
- [ ] Invalid values are rejected

**Swipeable Template - Desktop:**
- [ ] Cards display correctly (all 12 cards)
- [ ] Next/Previous buttons work
- [ ] Keyboard navigation works (arrows, spacebar, pageup/down)
- [ ] Progress bar updates correctly
- [ ] Content is scrollable within cards
- [ ] Fade-at-bottom effect appears when scrolling
- [ ] Trait bars animate correctly
- [ ] Locked cards show lock overlay
- [ ] Unlock buttons work
- [ ] Unlocks decrement correctly
- [ ] Third unlock redirects to offerings page

**Swipeable Template - Mobile:**
- [ ] Cards display correctly on mobile viewport
- [ ] Swipe left/right works
- [ ] Tap navigation works
- [ ] Progress bar visible and updating
- [ ] Content scrollable within cards
- [ ] Haptic feedback works (if supported)
- [ ] Instruction overlay displays on first visit
- [ ] Instruction overlay dismisses correctly

**Traditional Template - Regression:**
- [ ] All sections display correctly
- [ ] Lock overlays work
- [ ] Unlock mechanism works
- [ ] No visual regressions

**Both Templates:**
- [ ] Same content displays correctly
- [ ] Locked sections consistent
- [ ] Unlock counts sync correctly
- [ ] Back to Dashboard works (paid)
- [ ] Back button behavior correct (teaser vs paid)

### Cross-Browser Testing

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Mobile Chrome (Android)

---

## Backward Compatibility

### Existing Readings

**Requirement:** All existing readings (generated before multi-template enhancement) must display correctly in both templates.

**Approach:**
- Use same data structure (no schema changes)
- Both templates read same database columns
- Template rendering is purely presentational

**Testing:**
- Generate readings with Traditional template
- Switch to Swipeable template
- Verify readings display correctly
- Switch back and verify again

### API Compatibility

**No API changes required:**
- REST endpoints unchanged
- Request/response format unchanged
- Reading generation logic unchanged

### Database Compatibility

**No schema changes:**
- No new tables
- No new columns in existing tables
- Only new option: `sm_report_template` in `wp_options`

### Free User Flow

**Must remain unchanged:**
- Free users see same experience (with admin-selected template)
- Email → OTP → Quiz → Report flow unchanged
- Unlock mechanism works identically

### Logged-In User Flow

**Must remain unchanged:**
- Dashboard displays readings correctly
- Clicking reading opens in admin-selected template
- Back to Dashboard button works

---

## Implementation Phases (Simplified)

### Phase 1: Create New Template Files 📄

**Tasks:**
1. Copy `swipeTemplate.html` as starting point
2. Adapt to match existing template data placeholders
3. Create `palm-reading-template-swipe-teaser.html` (with lock overlays for premium sections)
4. Create `palm-reading-template-swipe-full.html` (no locks, all unlocked)
5. Ensure CSS and JS are inline/self-contained in template files

**Deliverables:**
- `palm-reading-template-swipe-teaser.html` (self-contained)
- `palm-reading-template-swipe-full.html` (self-contained)

**Testing:**
- Templates render correctly as standalone HTML files
- All cards display
- Navigation works (swipe, buttons, keyboard)
- CSS styles apply correctly
- JavaScript functionality works

**Time Estimate:** 2-3 hours

---

### Phase 2: Add Admin Setting 🎛️

**Tasks:**
1. Add radio button to `class-sm-settings.php`
2. Register `sm_report_template` option
3. Add validation (whitelist: 'traditional' or 'swipeable')

**Deliverables:**
- Admin UI with radio buttons
- Setting saved to `wp_options`

**Testing:**
- Can select Traditional or Swipeable
- Setting saves correctly
- Invalid values rejected (defaults to 'traditional')

**Time Estimate:** 30 minutes

---

### Phase 3: Update Template Loading Logic 🔧

**Tasks:**
1. Find where templates are currently loaded
2. Add 3-5 lines of code to check `sm_report_template` setting
3. Load appropriate template file based on setting

**Deliverables:**
- Template loading respects admin setting
- No other changes to business logic

**Testing:**
- Traditional template still works (default)
- Swipeable template loads when selected
- Can switch between templates and both work
- Existing readings display correctly in both templates

**Time Estimate:** 15-30 minutes

---

### Phase 4: Testing & Validation ✅

**Tasks:**
1. Test traditional template (regression - ensure nothing broke)
2. Test swipeable template (new functionality)
3. Test switching between templates
4. Test on mobile and desktop
5. Test teaser and paid reports in both templates
6. Test unlock mechanism in both templates

**Deliverables:**
- Confirmed no regressions
- Both templates work correctly
- Unlock mechanism works in both

**Testing Checklist:**
- [ ] Traditional teaser works
- [ ] Traditional paid works
- [ ] Swipeable teaser works
- [ ] Swipeable paid works
- [ ] Can switch templates in admin
- [ ] Unlock buttons work in both templates
- [ ] Mobile swipe works
- [ ] Desktop navigation works
- [ ] No JavaScript errors
- [ ] No PHP errors

**Time Estimate:** 1-2 hours

---

### Phase 5: Documentation Update 📚

**Tasks:**
1. Update `CLAUDE.md` to mention multi-template support
2. Add brief section to `CONTEXT.md` about template selection
3. Note in docs that templates are self-contained

**Deliverables:**
- Updated documentation

**Time Estimate:** 15 minutes

---

## Total Time Estimate: 4-6 hours (surgical implementation)

---

## Success Criteria

### Critical Success Factors (Must Have) ✅

**NO REGRESSIONS:**
- [ ] Traditional template works EXACTLY as before (zero changes)
- [ ] All existing features work (OpenAI calls, unlocks, database, REST endpoints)
- [ ] Free user flow unchanged
- [ ] Logged-in user flow unchanged
- [ ] No PHP errors in debug.log
- [ ] No JavaScript console errors

**NEW FUNCTIONALITY:**
- [ ] Admin can select between Traditional and Swipeable templates
- [ ] Setting persists in database
- [ ] Swipeable template loads when selected
- [ ] Both templates display same reading data correctly
- [ ] Unlock mechanism works in both templates
- [ ] Mobile swipe navigation works
- [ ] Desktop button/keyboard navigation works

### Nice to Have (Optional) ✨

- [ ] Smooth animations and transitions
- [ ] Progress bar updates accurately
- [ ] Haptic feedback on mobile
- [ ] Cross-browser tested (Chrome, Firefox, Safari, Edge)
- [ ] Performance comparable to traditional template

### Quality Gates 🚦

**Before Shipping:**
- [ ] Tested on at least 2 browsers (Chrome + one other)
- [ ] Tested on mobile (iOS or Android)
- [ ] Tested on desktop
- [ ] No errors in browser console
- [ ] No PHP warnings or errors
- [ ] Can switch between templates multiple times without issues

**Documentation:**
- [ ] Brief update to `CLAUDE.md` noting multi-template support
- [ ] Brief update to `CONTEXT.md` with template selection info

---

## ❓ Frequently Asked Questions

### Q: Will this break existing functionality?
**A:** No. The existing templates remain completely untouched. This adds new template files as an option, with zero changes to existing business logic.

### Q: Do we need to change the OpenAI API calls?
**A:** No. Both templates use the exact same reading data from the exact same API calls. This is purely a presentation change.

### Q: Do we need to modify the database?
**A:** No. The only database change is adding ONE option to `wp_options` table (`sm_report_template`). All reading data remains unchanged.

### Q: Do we need to change REST endpoints?
**A:** No. All REST endpoints remain unchanged. The only change is which HTML template file gets loaded.

### Q: Will switching templates affect existing readings?
**A:** No. Existing readings will display correctly in both templates because both templates read the same data structure.

### Q: Do we need a new class for template management?
**A:** No. We keep it simple - just add a few lines to check the admin setting and load the appropriate template file.

### Q: How much code will change?
**A:** Very minimal:
- **New files:** 2 template files (standalone HTML)
- **Modified files:** 1 file (`class-sm-settings.php` - add radio buttons)
- **Code changes:** ~10-15 lines total (admin UI + template loading logic)

### Q: What if we want to switch back to traditional template?
**A:** Just select "Traditional" in admin settings. Both templates always work - it's just a toggle.

### Q: Will this affect performance?
**A:** Minimal impact. Templates are self-contained HTML files, so no additional HTTP requests for CSS/JS. Performance should be comparable to existing templates.

### Q: How much testing is required?
**A:** Focus on regression testing (ensure existing templates still work) and basic functionality testing (ensure new templates work). Estimated 1-2 hours of manual testing.

---

## Out of Scope (Future Enhancements)

These items are explicitly **NOT** included in this phase but may be considered for future releases:

1. **User-Level Template Selection**
   - Allow individual users to choose their preferred template
   - Requires user preference storage and UI

2. **Template Preview in Admin**
   - Show visual preview of each template in admin settings
   - Requires screenshot generation or live preview

3. **Additional Templates**
   - More than 2 template options (e.g., "Minimalist", "Bold", "Classic")
   - Requires additional design and development

4. **Custom Template Builder**
   - Allow admins to create custom templates via drag-and-drop
   - Complex feature requiring significant development

5. **A/B Testing**
   - Automatically test different templates with different user segments
   - Requires analytics integration and user segmentation

6. **Template-Specific Content**
   - Different content for different templates
   - Would break content consistency goal

---

## Questions & Decisions

### Open Questions

1. **Template Naming:**
   - Current: "Traditional Scrolling Layout" and "Swipeable Card Interface"
   - Alternative: "Classic View" and "Card View"
   - Alternative: "Scrolling Template" and "Swipeable Template"
   - **Decision:** Use current naming (descriptive and clear)

2. **Default Template:**
   - Should default be Traditional (existing) or Swipeable (new)?
   - **Decision:** Traditional (backward compatibility, gradual rollout)

3. **Trait Bar Count:**
   - Swipeable template prototype has 4 bars, requirements mention 3
   - Which to use?
   - **Decision:** Keep 4 bars to match prototype design (Intuition, Creativity, Resilience, Practicality)

4. **Premium Section Cards:**
   - Should each premium section be its own card or grouped?
   - **Decision:** Each premium section = separate card (7 cards total for premium)

5. **Back Button Behavior:**
   - Should swipeable template disable browser back button like traditional paid template?
   - **Decision:** Yes, consistent behavior across templates

### Decisions Log

| Date | Decision | Rationale |
|------|----------|-----------|
| 2025-12-27 | Use "Traditional" and "Swipeable Card" naming | Descriptive and clear for admins |
| 2025-12-27 | Default to Traditional template | Backward compatibility |
| 2025-12-27 | Keep 4 trait bars in swipeable template | Matches prototype design |
| 2025-12-27 | Each premium section = separate card | Better granularity and UX |
| 2025-12-27 | Disable back button for both templates (paid) | Consistent UX across templates |

---

## Appendix

### Reference Files

- **Swipeable Template Prototype:** `swipeTemplate.html`
- **Traditional Teaser Template:** `palm-reading-template-teaser.html`
- **Traditional Paid Template:** `palm-reading-template-full.html`

### Related Documentation

- `CONTEXT.md` - System architecture
- `CLAUDE.md` - AI assistant guide
- `TESTING.md` - Testing guide
- `DEV-PLAN.md` - Full paid reports plan (archived)

### Dependencies

- WordPress 5.8+
- PHP 7.4+
- Modern browser with CSS Grid and Flexbox support
- Touch API support (mobile)
- Vibration API support (optional, mobile)

---

**Document Status:** Draft - Awaiting Approval
**Next Steps:** Review requirements → Approve → Create development plan → Begin implementation

**Prepared By:** Development Team (AI Assistant + Human Developer)
**Date:** 2025-12-27
**Version:** 1.0.0

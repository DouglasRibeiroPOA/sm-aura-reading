# Teaser Reading Rebalance - Requirements Document

**Generated:** 2025-12-20
**Plugin:** Mystic Palm Reading (SoulMirror)
**Status:** ⚠️ BLOCKED - Awaiting Prerequisites
**Priority:** High

---

## ⚠️ PREREQUISITE: Login/Authentication System

### Status: TODO - Requirements Gathering Needed

**CRITICAL BLOCKER:** The teaser rebalance and paid reading feature **cannot be implemented** without a login/authentication system.

**Why This Blocks Implementation:**
- Users need to return and access their paid readings across sessions/devices
- Payment purchases must be tied to user accounts
- `has_purchased = true` is meaningless without persistent user authentication
- No way to verify user owns a reading when they return

**Next Steps:**

1. **[ ] TODO: Gather Login/Auth Requirements** ⬅️ **START HERE**
   - User will provide integration guide for existing "account service"
   - Account service already exists and needs to be integrated with palm reading plugin
   - Requirements document will be created after integration guide is provided

2. **[ ] TODO: Implement Login/Auth Integration**
   - Integrate with existing account service
   - Add "Access My Reading" flow
   - Add session management
   - Add "My Readings" page

3. **[ ] TODO: Implement Teaser Rebalance** (this document)
   - Can only proceed after Login/Auth is complete

**HOLD:** All work on this document until Login/Auth requirements are gathered and implemented.

**Notes:**
- Plugin has existing OTP system for email verification
- May be extended for returning user login
- Integration approach depends on existing account service architecture
- Waiting for integration guide from user

---

## Problem Statement

### Current Issues

1. **OpenAI Request Overload**
   - Single API call attempts to generate **both** teaser content (~700 words) AND full locked content (~750-1,050 words)
   - Total request: **1,450-1,750 words** across 10+ sections
   - OpenAI consistently fails to meet minimum word count requirements despite validation
   - Increasing tokens, timeout limits does not solve the issue reliably

2. **Wasted Resources**
   - Generating 750-1,050 words of paid content that most users never see
   - API credits spent on content that sits unused in database
   - Token limits being reached on free teaser generation

3. **Free Experience Too Complete**
   - Current locked sections show substantial previews (76-114 words per section)
   - Distinction between free and paid content is not strong enough
   - Users get enough insight from previews that they may not see value in unlocking

### Solution Overview

**Two-Phase OpenAI Architecture:**

1. **Teaser API Call** (Phase 1 - Implement Now)
   - Lighter, more focused request
   - 700-900 words total
   - Remove generation of `locked_full` fields
   - Add new 100% locked sections with placeholder/gibberish text
   - Improve quality by reducing scope

2. **Paid Content API Call** (Phase 2 - Future Implementation)
   - Triggered only when user pays or uses unlock credits
   - Generates deep analysis for locked sections
   - 1,200-1,500 words of premium content
   - Populates previously empty locked sections

---

## Requirements

### Phase 1: Teaser Rebalance (Current Priority)

#### 1. Reduce & Refocus OpenAI Teaser Prompt

**Objective:** Generate 700-900 words of focused, high-quality teaser content that OpenAI can reliably produce.

**Sections to KEEP and ENRICH:**

| Section | Current | New Target | Change |
|---------|---------|------------|--------|
| **Opening** | 70-120 words (2 paragraphs) | 80-120 words (2 paragraphs) | Slightly richer, more personalized welcome |
| **Life Foundations** | 150-185 words (2-3 paragraphs + theme) | 180-220 words (3 paragraphs + theme) | Deeper analysis of primary lines, more specific palm observations |
| **Career & Success** | 55-80 words (1 paragraph) | 80-120 words (1-2 paragraphs) | Expanded career alignment insights |
| **Personality Traits** | 45-70 words (intro + 3 traits) | 70-100 words (richer intro + 3 traits) | More detailed trait analysis |
| **Closing** | 60-100 words (2 paragraphs) | 80-120 words (2 paragraphs) | Stronger call-to-action, invitation to deeper work |

**Subtotal for FULL sections:** ~490-680 words

**Sections to MAKE PREVIEW-ONLY (remove locked_full generation):**

| Section | Current Preview | New Preview | locked_full |
|---------|----------------|-------------|-------------|
| **Love Patterns** | 64-90 words (2 paragraphs) | 40-60 words (1 paragraph) | **REMOVE** (placeholder text) |
| **Challenges & Opportunities** | 56-80 words (2 paragraphs) | 40-60 words (1 paragraph) | **REMOVE** (placeholder text) |
| **Life Phase** | 56-80 words (2 paragraphs) | 40-60 words (1 paragraph) | **REMOVE** (placeholder text) |
| **Timeline (6 months)** | 56-80 words (2 paragraphs) | 40-60 words (1 paragraph) | **REMOVE** (placeholder text) |
| **Guidance** | 57-94 words (preview_p1 only) | 40-60 words (1 paragraph) | **REMOVE** (placeholder text) |

**Subtotal for PREVIEW sections:** ~200-300 words

**TOTAL TEASER CONTENT:** ~690-980 words (target: 700-900 words)

#### 2. Add New 100% Locked Premium Sections

**Objective:** Create clearly visible locked sections that signal deeper paid content without generating content at teaser time.

**New Sections to Add (Placeholder Text Only):**

1. **Deep Relationship Analysis** (NEW - 100% locked)
   - **Teaser display:** Section title + lock icon + placeholder text (gibberish or "Lorem ipsum" style)
   - **Future content:** Attachment styles, partner compatibility, relationship timing, love patterns (200-250 words)
   - **Visual signal:** "🔒 Unlock Deep Relationship Insights"

2. **12-Month Extended Timeline** (NEW - 100% locked)
   - **Teaser display:** Section title + lock icon + placeholder text
   - **Future content:** Month-by-month themes, timing for major decisions, upcoming opportunities (250-300 words)
   - **Visual signal:** "🔒 Unlock Your Year Ahead"

3. **Life Purpose & Soul Mission** (NEW - 100% locked)
   - **Teaser display:** Section title + lock icon + placeholder text
   - **Future content:** Soul purpose, karmic patterns, life mission alignment (200-250 words)
   - **Visual signal:** "🔒 Unlock Your Life Purpose"

4. **Shadow Work & Transformation** (NEW - 100% locked)
   - **Teaser display:** Section title + lock icon + placeholder text
   - **Future content:** Shadow patterns, blocks to release, transformation path (200-250 words)
   - **Visual signal:** "🔒 Unlock Shadow & Growth Insights"

5. **Practical Guidance & Action Plan** (NEW - 100% locked)
   - **Teaser display:** Section title + lock icon + placeholder text
   - **Future content:** 3 specific focus points, daily/weekly rituals, practices, accountability framework (200-250 words)
   - **Visual signal:** "🔒 Unlock Your Personalized Action Plan"

**Placeholder Text Strategy:**
- Use mystical-sounding but clearly nonsensical text (e.g., "The celestial patterns weave through temporal nodes of resonance...")
- OR use explicit placeholder: "This section will be unlocked when you access the full reading"
- Ensure it's obvious this is NOT the real content

#### 3. Update JSON Schema

**File:** `includes/class-sm-teaser-reading-schema.php`

**Changes Required:**

**REMOVE these fields from schema validation:**
- `love_patterns.locked_full`
- `challenges_opportunities.locked_full`
- `life_phase.locked_full`
- `timeline_6_months.locked_full`
- `guidance.locked_full`

**SIMPLIFY preview fields:**
- `love_patterns.preview` (single paragraph, 40-60 words) - REMOVE preview_p1/preview_p2 structure
- `challenges_opportunities.preview` (single paragraph, 40-60 words)
- `life_phase.preview` (single paragraph, 40-60 words)
- `timeline_6_months.preview` (single paragraph, 40-60 words)
- `guidance.preview` (single paragraph, 40-60 words)

**ADJUST word counts for enriched sections:**
- `opening.reflection_p1`: 40-60 words (up from 35-60)
- `opening.reflection_p2`: 40-60 words (up from 35-60)
- `life_foundations.paragraph_1`: 60-75 words (up from 50-65)
- `life_foundations.paragraph_2`: 60-75 words (up from 50-65)
- `life_foundations.paragraph_3`: 40-60 words (REQUIRED now, not optional)
- `life_foundations.core_theme`: 20-35 words (up from 18-35)
- `career_success.main_paragraph`: 80-120 words (up from 55-80)
- `personality_traits.intro`: 70-100 words (up from 45-70)
- `closing.paragraph_1`: 40-60 words (up from 30-50)
- `closing.paragraph_2`: 40-60 words (up from 30-50)

**ADD placeholder sections to schema:**
```php
'deep_relationship_analysis' => array(
    'required' => false,
    'fields'   => array(
        'placeholder_text' => array(
            'type'      => 'string',
            'required'  => false,
            'default'   => 'This section contains deep relationship insights available in the full reading.',
        ),
    ),
),
// ... similar for other new locked sections
```

#### 4. Update OpenAI Prompt

**File:** `includes/class-sm-ai-handler.php`
**Method:** `build_teaser_prompt()`

**Changes Required:**

1. **REMOVE from prompt:**
   - All `locked_full` field instructions
   - All references to generating 150-210 word locked content
   - `preview_p2` fields (simplify to single preview paragraph)
   - Word count minimums that OpenAI consistently fails to meet

2. **UPDATE word count targets:**
   - Adjust to new schema (see section 3 above)
   - Remove strict "MINIMUM X words" language
   - Use "target X-Y words" instead for flexibility
   - Focus on "write complete, rich paragraphs" rather than exact counts

3. **SIMPLIFY section structure:**
   - Love Patterns: `preview` (40-60 words), `locked_teaser` (12-20 words) - REMOVE `locked_full`
   - Challenges: `preview` (40-60 words), `locked_teaser` (12-20 words) - REMOVE `locked_full`
   - Life Phase: `preview` (40-60 words), `locked_teaser` (12-20 words) - REMOVE `locked_full`
   - Timeline: `preview` (40-60 words), `locked_teaser` (12-20 words) - REMOVE `locked_full`
   - Guidance: `preview` (40-60 words), `locked_teaser` (12-20 words) - REMOVE `locked_full`

4. **ADD guidance for enriched sections:**
   - Opening: "Write a warm, personalized welcome that acknowledges {name}'s current life phase and sets mystical tone"
   - Life Foundations: "Analyze visible palm features (life line, head line, heart line) with specific observations. Include resilience markers, emotional patterns, and growth themes."
   - Career: "Provide career alignment insights based on palm features and quiz answers. Be specific about strengths and timing."
   - Personality: "Write rich analysis connecting the 3 selected traits to palm features and quiz responses."
   - Closing: "Create a compelling invitation to unlock deeper insights. Make the value of paid content clear."

#### 5. Update Template Renderer

**File:** `includes/class-sm-template-renderer.php`

**Changes Required:**

1. **Simplify locked section rendering:**
   - Change from `preview_p1 + preview_p2` to single `preview` paragraph
   - Update `replace_locked_section_preview()` method
   - Adjust regex patterns to match new single-paragraph structure

2. **Add rendering for new 100% locked sections:**
   - Create new section rendering methods for premium locked sections
   - Display placeholder text + lock overlay
   - Add visual styling hooks for clearly locked appearance

3. **Handle missing `locked_full` gracefully:**
   - If `locked_full` field is missing/empty, use placeholder text
   - Log warning but don't fail rendering

#### 6. Update HTML Template

**File:** `palm-reading-template-teaser.html`

**Changes Required:**

1. **Simplify locked section HTML:**
   - Remove second paragraph from locked section previews
   - Adjust CSS classes if needed

2. **Add new 100% locked sections:**
   ```html
   <section class="reading-section section-deep-love locked premium-locked">
     <div class="icon-circle"><i class="fas fa-heart"></i></div>
     <h2>Deep Relationship Analysis</h2>
     <div class="section-content">
       <p class="placeholder-text">
         <!-- Placeholder/gibberish text -->
       </p>
     </div>
     <div class="lock-overlay">
       <div class="lock-icon"><i class="fas fa-lock"></i></div>
       <p class="lock-text">Unlock Deep Relationship Insights</p>
       <button class="btn-unlock" data-section="deep_love">Unlock Now</button>
     </div>
   </section>
   ```

3. **Add visual distinction for premium-locked sections:**
   - Different opacity/blur for 100% locked vs. partially locked
   - Stronger lock icon/badge
   - Different button styling ("Unlock Premium Insights" vs. "Unlock Section")

#### 7. Update Unlock Handler

**File:** `includes/class-sm-unlock-handler.php`

**Changes Required:**

1. **Add new sections to allowed_sections:**
   ```php
   'deep_love',
   'extended_timeline',
   'life_purpose',
   'shadow_work',
   'guidance_plan',
   ```

2. **Add logic for premium section unlocking:**
   - When user attempts to unlock premium section, check payment status
   - If not paid, redirect to offerings page immediately
   - If paid, trigger Phase 2 API call to generate content (future implementation)

3. **Track unlock state separately for premium vs. free sections:**
   - Free unlock counter (max 2) - for partially locked sections
   - Premium unlock - requires payment or credits

#### 8. Database Schema Changes

**File:** `includes/class-sm-database.php`

**Changes Required:**

**Option A: No database changes (recommended for Phase 1)**
- Store placeholder text in `content_data` JSON as empty strings or literal placeholders
- When Phase 2 is implemented, update `content_data` with new API content

**Option B: Add new column (if needed for clarity)**
```sql
ALTER TABLE wp_sm_readings
ADD COLUMN premium_content_data LONGTEXT DEFAULT NULL AFTER content_data;
```
- Store teaser content in `content_data`
- Store paid content in `premium_content_data` (populated in Phase 2)

**Recommendation:** Option A (no schema change, just update JSON structure)

#### 9. DevMode Updates

**File:** `includes/class-sm-dev-mode.php`

**Changes Required:**

1. **Update mock teaser response:**
   - Remove `locked_full` fields from mock data
   - Simplify preview fields to single paragraphs
   - Add placeholder text for new premium sections
   - Adjust word counts to match new targets

2. **Add mock paid content endpoint (for Phase 2 testing):**
   ```php
   register_rest_route(
       'soulmirror-dev/v1',
       '/mock-paid-content',
       array(
           'methods'  => 'POST',
           'callback' => array( $this, 'mock_paid_content_response' ),
       )
   );
   ```

#### 10. Validation & Logging

**Files:**
- `includes/class-sm-teaser-reading-schema.php`
- `includes/class-sm-ai-handler.php`

**Changes Required:**

1. **Relax validation (already in place):**
   - Keep "relaxed mode" validation that accepts responses with warnings
   - Log word count issues but don't reject response
   - Focus on structural validation (required fields exist, types are correct)

2. **Enhanced logging:**
   - Log actual vs. target word counts per section
   - Log total teaser word count
   - Track success rate of new lighter prompt
   - Monitor which sections consistently fall short

3. **Success metrics:**
   - Target: 95%+ of teaser readings meet 700-900 word total
   - Target: 90%+ of individual sections meet minimum word counts
   - Target: Average token usage reduced by 30-40%

---

## Phase 2: Paid Content API Call (Future Implementation)

**NOT part of current requirements, but documented for planning purposes.**

### Trigger Conditions

Paid content API call should be triggered when:

1. **User purchases full reading** (payment confirmed via webhook)
2. **User uses unlock credits** (admin-granted or promotional)
3. **User is logged in as paid subscriber** (subscription status verified)

### Implementation Approach

1. **New method:** `SM_AI_Handler::generate_paid_content( $reading_id, $lead_id )`
   - Load existing teaser reading
   - Extract quiz answers and palm image reference
   - Build paid content prompt (separate from teaser prompt)
   - Call OpenAI API for premium content only
   - Merge premium content into existing reading's `content_data`

2. **Paid Content Prompt:**
   - Focus on the 5 new premium sections
   - Reference teaser insights for continuity
   - Generate 1,200-1,500 words of deep analysis
   - Use richer personality traits, timeline breakdowns, shadow work themes

3. **Seamless User Experience:**
   - For paid users, auto-unlock all sections on page load
   - No visible lock icons or "unlock" buttons
   - Display appears as one complete, unified reading
   - Backend knows user is paid, fetches premium content, merges it

4. **Caching & Performance:**
   - Once premium content is generated, store in database
   - Don't regenerate on each view
   - Allow manual regeneration (admin feature for troubleshooting)

---

## UI/UX Specifications

### Visual Distinction: Partial vs. Premium Locked

**Partially Locked Sections** (Love, Challenges, Life Phase, Timeline, Guidance):
- Show short preview (40-60 words)
- Lock overlay with moderate opacity (50%)
- Button: "Unlock Section" (free unlock available)
- Can be unlocked with free unlock credits (max 2)

**Premium Locked Sections** (Deep Love, Extended Timeline, Life Purpose, Shadow Work, Guidance Plan):
- Show placeholder/gibberish text or clear "locked" message
- Lock overlay with strong opacity (70-80%)
- Gold/premium color accent (e.g., gold lock icon)
- Button: "Unlock Premium Insights" (requires payment)
- Cannot be unlocked with free credits

### Updated Unlock Flow

**Current Flow (2 free unlocks):**
1. User clicks "Unlock" on partially locked section
2. Check unlock count < 2
3. If yes: unlock section, increment counter
4. If no: redirect to offerings page

**New Flow (2 free unlocks + premium sections):**
1. User clicks "Unlock" button
2. Check section type:
   - **Partially locked:** Same as current flow (2 free unlocks)
   - **Premium locked:** Check payment status
     - If not paid: redirect to offerings page immediately
     - If paid: trigger Phase 2 API call, populate content, unlock all premium sections

### Call-to-Action Updates

**Closing Section CTA:**
```
"Your palm holds deeper insights waiting to be revealed. Unlock your complete
reading to discover:

• Deep relationship patterns and compatibility insights
• Extended 12-month timeline with monthly themes
• Your soul purpose and life mission alignment
• Shadow work and transformation guidance
• Personalized action plan with daily practices

[Unlock Your Complete Reading →]"
```

**Lock Overlay CTA (Premium Sections):**
```
"🔒 Premium Insight

This section contains in-depth analysis available in the full reading.

[Unlock Full Reading →]"
```

---

## Success Criteria

### Phase 1 (Teaser Rebalance)

**Technical Success:**
- [ ] OpenAI consistently generates 700-900 word teaser responses
- [ ] 95%+ of readings meet target word counts per section
- [ ] Token usage reduced by 30-40% per teaser reading
- [ ] No `locked_full` content generated at teaser time
- [ ] New premium sections display correctly with placeholder text

**UX Success:**
- [ ] Teaser reading still feels substantial and valuable
- [ ] Users clearly understand the difference between free and paid content
- [ ] Lock overlays are visually distinct (partial vs. premium)
- [ ] Unlock flow works correctly for both free and premium sections

**Business Success:**
- [ ] Unlock rate increases (users more motivated to unlock/purchase)
- [ ] Conversion rate to paid readings increases
- [ ] User feedback indicates teaser is "valuable but leaves them wanting more"

### Phase 2 (Paid Content Generation)

**To be defined in separate requirements document.**

---

## Migration Strategy

### Handling Existing Readings

**Option A: Leave existing readings unchanged (recommended)**
- Existing readings with `locked_full` content continue to work
- Only new readings use new schema
- No data migration required
- Simplest implementation

**Option B: Migrate existing readings**
- Extract `locked_full` content from existing readings
- Store separately or flag as "legacy" format
- Update template renderer to handle both old and new formats
- More complex, higher risk

**Recommendation:** Option A

### Rollout Plan

1. **Development & Testing (Week 1-2)**
   - Implement schema changes
   - Update OpenAI prompt
   - Test with DevMode extensively
   - Verify word counts and quality

2. **Staging Testing (Week 3)**
   - Deploy to staging environment
   - Generate 20-30 test readings
   - Analyze word counts, quality, user experience
   - Iterate on prompt and schema if needed

3. **Soft Launch (Week 4)**
   - Deploy to production
   - Monitor first 50-100 readings closely
   - Track success metrics
   - Gather user feedback

4. **Full Rollout (Week 5+)**
   - Confirm success criteria met
   - Monitor ongoing performance
   - Plan Phase 2 implementation

---

## Technical Implementation Notes

### Prompt Engineering Best Practices

**To improve OpenAI reliability with new lighter prompt:**

1. **Be specific about structure:**
   ```
   "Write a {X}-{Y} word paragraph about [topic].
   Structure: 3-4 complete sentences."
   ```

2. **Use examples in system prompt:**
   ```
   "Example good paragraph (50 words):
   [sample paragraph here]"
   ```

3. **Reduce cognitive load:**
   - Don't ask for 10+ sections at once
   - Group related fields
   - Use clear section boundaries

4. **Test iteratively:**
   - Start with absolute minimum schema
   - Add fields one at a time
   - Test each change with 10+ generations
   - Measure success rate before adding more

### JSON Response Format Optimization

**Current approach (keep):**
- Use `response_format: { type: "json_object" }` to force JSON
- Strip markdown code fences
- Attempt JSON recovery if parse fails
- Validate against schema in relaxed mode

**Potential improvements:**
- Add JSON structure example in system prompt
- Use field descriptions to guide OpenAI
- Consider few-shot prompting with example JSON

---

## Open Questions & Decisions Needed

### Decisions Required Before Implementation

1. **Career Modals:**
   - [ ] Keep in free teaser as engagement feature?
   - [ ] Move to premium locked sections?
   - [ ] Show 1 modal free, lock other 2?

   **Recommendation:** Keep in free teaser (they're short, add engagement)

2. **Placeholder Text Strategy:**
   - [ ] Use mystical-sounding gibberish?
   - [ ] Use explicit "This section is locked" message?
   - [ ] Use Lorem ipsum style text?

   **Recommendation:** Explicit message - clearer UX, avoids confusion

3. **Premium Section Naming:**
   - [ ] "Premium Insights"
   - [ ] "Deep Analysis"
   - [ ] "Extended Reading"

   **Recommendation:** "Deep [Topic] Analysis" (e.g., "Deep Relationship Analysis")

4. **Unlock Counter Behavior:**
   - [ ] Keep 2 free unlocks for partially locked sections only?
   - [ ] Make premium sections completely separate (not counted)?

   **Recommendation:** Keep separate - free unlocks for partial sections, payment required for premium

5. **Template File Changes:**
   - [ ] Modify existing `palm-reading-template-teaser.html`?
   - [ ] Create new template version?

   **Recommendation:** Modify existing, use version number in data attribute

---

## Files to Modify

### High Priority (Core Functionality)

1. `includes/class-sm-teaser-reading-schema.php` - Update schema validation
2. `includes/class-sm-ai-handler.php` - Update `build_teaser_prompt()` method
3. `includes/class-sm-template-renderer.php` - Update rendering logic
4. `palm-reading-template-teaser.html` - Add new sections, update structure
5. `includes/class-sm-unlock-handler.php` - Add premium section logic

### Medium Priority (DevMode & Testing)

6. `includes/class-sm-dev-mode.php` - Update mock responses
7. `includes/class-sm-logger.php` - Add enhanced logging (if needed)

### Low Priority (Future Phase 2)

8. `includes/class-sm-rest-controller.php` - Add paid content endpoint (Phase 2)
9. `includes/class-sm-database.php` - Schema changes if needed (Phase 2)

---

## Appendix A: Word Count Comparison

### Current vs. New

| Component | Current Words | New Words | Change |
|-----------|---------------|-----------|--------|
| **Teaser Content** |
| Opening | 70-120 | 80-120 | +10 words (richer) |
| Life Foundations | 150-185 | 180-220 | +30-35 words (deeper) |
| Career | 55-80 | 80-120 | +25-40 words (expanded) |
| Personality | 45-70 | 70-100 | +25-30 words (richer) |
| Closing | 60-100 | 80-120 | +20 words (stronger CTA) |
| Love Preview | 64-90 | 40-60 | -24-30 words (lighter) |
| Challenges Preview | 56-80 | 40-60 | -16-20 words (lighter) |
| Life Phase Preview | 56-80 | 40-60 | -16-20 words (lighter) |
| Timeline Preview | 56-80 | 40-60 | -16-20 words (lighter) |
| Guidance Preview | 57-94 | 40-60 | -17-34 words (lighter) |
| **Subtotal** | **669-979** | **690-980** | **±0-10 words (maintained)** |
| | | | |
| **Locked Content (generated now)** |
| Love locked_full | 150-210 | 0 | **-150-210 (REMOVED)** |
| Challenges locked_full | 150-210 | 0 | **-150-210 (REMOVED)** |
| Life Phase locked_full | 150-210 | 0 | **-150-210 (REMOVED)** |
| Timeline locked_full | 150-210 | 0 | **-150-210 (REMOVED)** |
| Guidance locked_full | 150-210 | 0 | **-150-210 (REMOVED)** |
| **Subtotal** | **750-1,050** | **0** | **-750-1,050 (REMOVED)** |
| | | | |
| **TOTAL PER API CALL** | **1,419-2,029** | **690-980** | **-729-1,049 words** |
| **Token Reduction** | ~2,100-3,000 tokens | ~1,000-1,400 tokens | **~50-65% reduction** |

### Phase 2 Paid Content (Future)

| Component | Words | API Call |
|-----------|-------|----------|
| Deep Relationship Analysis | 200-250 | Phase 2 only |
| Extended Timeline (12 months) | 250-300 | Phase 2 only |
| Life Purpose & Soul Mission | 200-250 | Phase 2 only |
| Shadow Work & Transformation | 200-250 | Phase 2 only |
| Practical Guidance & Action Plan | 200-250 | Phase 2 only |
| **TOTAL PREMIUM CONTENT** | **1,050-1,300** | **Separate call** |

---

## Appendix B: Example New Teaser JSON Structure

```json
{
  "meta": {
    "user_name": "Alexandra",
    "generated_at": "2025-12-20T10:30:00Z",
    "reading_type": "palm_teaser"
  },
  "opening": {
    "reflection_p1": "40-60 word paragraph...",
    "reflection_p2": "40-60 word paragraph..."
  },
  "life_foundations": {
    "paragraph_1": "60-75 word paragraph...",
    "paragraph_2": "60-75 word paragraph...",
    "paragraph_3": "40-60 word paragraph...",
    "core_theme": "20-35 word core insight..."
  },
  "career_success": {
    "main_paragraph": "80-120 word paragraph...",
    "modal_love_patterns": "35-55 word modal text...",
    "modal_career_direction": "35-55 word modal text...",
    "modal_life_alignment": "35-55 word modal text..."
  },
  "personality_traits": {
    "intro": "70-100 word intro paragraph...",
    "trait_1_name": "Intuition",
    "trait_1_score": 88,
    "trait_2_name": "Resilience",
    "trait_2_score": 92,
    "trait_3_name": "Creativity",
    "trait_3_score": 85
  },
  "love_patterns": {
    "preview": "40-60 word preview paragraph...",
    "locked_teaser": "12-20 word teaser..."
  },
  "challenges_opportunities": {
    "preview": "40-60 word preview paragraph...",
    "locked_teaser": "12-20 word teaser..."
  },
  "life_phase": {
    "preview": "40-60 word preview paragraph...",
    "locked_teaser": "12-20 word teaser..."
  },
  "timeline_6_months": {
    "preview": "40-60 word preview paragraph...",
    "locked_teaser": "12-20 word teaser..."
  },
  "guidance": {
    "preview": "40-60 word preview paragraph...",
    "locked_teaser": "12-20 word teaser..."
  },
  "closing": {
    "paragraph_1": "40-60 word paragraph...",
    "paragraph_2": "40-60 word paragraph with CTA..."
  },
  "premium_sections": {
    "deep_relationship_analysis": {
      "locked": true,
      "placeholder_text": "This section contains deep relationship insights available in the full reading."
    },
    "extended_timeline": {
      "locked": true,
      "placeholder_text": "This section contains your 12-month extended timeline available in the full reading."
    },
    "life_purpose": {
      "locked": true,
      "placeholder_text": "This section contains your life purpose and soul mission analysis available in the full reading."
    },
    "shadow_work": {
      "locked": true,
      "placeholder_text": "This section contains shadow work and transformation guidance available in the full reading."
    },
    "guidance_plan": {
      "locked": true,
      "placeholder_text": "This section contains your personalized action plan available in the full reading."
    }
  }
}
```

---

## Next Steps

1. **Review & Approve Requirements**
   - Confirm approach aligns with vision
   - Make decisions on open questions
   - Approve word count targets

2. **Create Implementation Plan**
   - Break down into tickets/tasks
   - Estimate effort for each component
   - Set sprint goals

3. **Begin Development**
   - Start with schema updates
   - Update OpenAI prompt
   - Test extensively in DevMode

4. **Test & Iterate**
   - Generate 50+ test readings
   - Analyze quality and word counts
   - Refine prompt based on results

5. **Deploy & Monitor**
   - Soft launch to production
   - Track success metrics
   - Gather user feedback

---

**End of Requirements Document**

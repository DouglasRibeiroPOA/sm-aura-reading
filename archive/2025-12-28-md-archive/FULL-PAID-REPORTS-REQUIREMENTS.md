# Full Paid Reports - Requirements Specification

**Feature:** Two-Phase Palm Reading System
**Status:** Requirements Definition
**Version:** 1.0.0
**Created:** 2025-12-26
**Plugin:** Mystic Palm Reading (SoulMirror)

---

## Executive Summary

This document specifies what needs to be **added** to implement fully unlocked paid palm readings.

**What Already Works (Don't Touch):**
- ✅ Authentication/JWT system (`SM_Auth_Handler`)
- ✅ Credit system (`SM_Credit_Handler`) - check and deduction
- ✅ Database schema (supports `palm_full` reading type already)
- ✅ Teaser generation (`generate_teaser_reading()`) - 700-900 words
- ✅ Frontend rendering with locks/unlocks
- ✅ E2E testing infrastructure (Playwright)

**What Needs to Be Built:**
1. **Clone teaser generation** → Create `generate_paid_reading()` method
2. **Add Phase 2 prompt** → Generate 700-900 additional words using Phase 1 context
3. **Combine outputs** → Merge Phase 1 + Phase 2 into single `palm_full` JSON (1400-1800 total words)
4. **New REST endpoint** → `POST /reading/generate-paid` (uses existing credit checks)
5. **Render without locks** → Extend existing rendering to hide locks for `palm_full` type
6. **E2E tests** → Add Playwright tests for paid flow

**Target Word Count:**
- Phase 1: 700-900 words (existing teaser prompt, reused)
- Phase 2: 700-900 words (NEW prompt, builds on Phase 1)
- **Combined: 1400-1800 words total**

**Key Principle:** Clone the working teaser flow, add Phase 2 generation, combine results. Keep it simple.

---

## 1. What Needs to Be Built

### 1.1 New Method: `generate_paid_reading($lead_id)`

**Location:** `/includes/class-sm-ai-handler.php`

**Purpose:** Generate fully unlocked paid reading with two OpenAI calls

**Implementation:**
```php
/**
 * Generate a fully unlocked paid palm reading (two-phase generation).
 *
 * @param string $lead_id UUID of the lead
 * @return array|WP_Error
 */
public function generate_paid_reading($lead_id) {
    // 1. Call Phase 1: Generate teaser context (700-900 words) - REUSE existing method
    $phase_1_data = $this->generate_teaser_context($lead_id); // Extract core from generate_teaser_reading()

    if (is_wp_error($phase_1_data)) {
        return $phase_1_data; // Return error immediately
    }

    // 2. Call Phase 2: Generate completion content (700-900 words) - NEW PROMPT
    $phase_2_data = $this->generate_paid_completion($lead_id, $phase_1_data);

    if (is_wp_error($phase_2_data)) {
        // Fallback: Save Phase 1 as teaser (don't charge credit)
        return new WP_Error(
            'phase_2_failed',
            'Full generation incomplete. Preview saved instead.',
            array('fallback_reading_id' => $this->save_teaser_reading($lead_id, $phase_1_data))
        );
    }

    // 3. Merge Phase 1 + Phase 2 into final JSON structure
    $combined_json = $this->merge_paid_reading_data($phase_1_data, $phase_2_data);

    // 4. Save as palm_full reading
    $reading_id = $this->save_paid_reading($lead_id, $combined_json);

    return array(
        'reading_id' => $reading_id,
        'reading_data' => $combined_json,
        'word_count' => $combined_json['meta']['total_word_count']
    );
}
```

**Key Points:**
- Reuse Phase 1 logic from existing `generate_teaser_reading()`
- Add Phase 2 with NEW prompt (builds on Phase 1)
- Merge both phases into single JSON
- Save as `reading_type = 'palm_full'`, `has_purchased = 1`

---

### 1.2 New REST Endpoint: `POST /reading/generate-paid`

**Location:** `/includes/class-sm-rest-controller.php`

**Purpose:** Handle paid reading generation requests

**Implementation:**
```php
public function handle_paid_reading_generate(WP_REST_Request $request) {
    $lead_id = sanitize_text_field($request->get_param('lead_id'));

    // 1. Auth check (already implemented - reuse)
    $user_data = SM_Auth_Handler::get_instance()->get_current_user();
    if (empty($user_data)) {
        return $this->error_response('authentication_required', '...', 401);
    }

    // 2. Credit check (already implemented - reuse)
    $credits = SM_Credit_Handler::get_instance()->check_user_credits();
    if (!$credits['has_credits']) {
        return $this->error_response('insufficient_credits', '...', 402, array(
            'redirect_to' => $this->build_shop_url()
        ));
    }

    // 3. Generate paid reading (NEW method)
    $result = SM_AI_Handler::get_instance()->generate_paid_reading($lead_id);
    if (is_wp_error($result)) {
        return $this->error_response($result->get_error_code(), $result->get_error_message(), 500);
    }

    // 4. Deduct credit (already implemented - reuse)
    $deduction = SM_Credit_Handler::get_instance()->deduct_credit($result['reading_id']);
    if (is_wp_error($deduction)) {
        // Rollback: delete reading
        SM_Reading_Service::get_instance()->delete_reading($result['reading_id']);
        return $this->error_response('credit_deduction_failed', '...', 500);
    }

    // 5. Success
    return $this->success_response(array(
        'reading_id' => $result['reading_id'],
        'reading_data' => $result['reading_data'],
        'credits_remaining' => $deduction['total_available']
    ));
}
```

**Register Route:**
```php
register_rest_route('soulmirror/v1', '/reading/generate-paid', array(
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => array($this, 'handle_paid_reading_generate'),
    'permission_callback' => array($this, 'verify_nonce')
));
```

---

### 1.3 Extend Frontend Rendering

**Location:** `assets/js/teaser-reading.js` (or create `paid-reading.js`)

**Purpose:** Render paid readings without locks

**Implementation:**
```javascript
function renderReading(readingData, readingType) {
    const isPaid = (readingType === 'palm_full');

    // Render all sections
    renderSection('opening', readingData.opening);
    renderSection('life_foundations', readingData.life_foundations);
    renderSection('love_patterns', readingData.love_patterns, isPaid);
    renderSection('career_success', readingData.career_success);
    renderSection('personality_traits', readingData.personality_traits);
    // ... etc

    // Hide unlock UI for paid readings
    if (isPaid) {
        document.querySelectorAll('.unlock-button').forEach(btn => btn.style.display = 'none');
        document.querySelectorAll('.premium-lock-overlay').forEach(overlay => overlay.style.display = 'none');
        document.querySelector('.reading-header').classList.add('paid-reading');
    }
}

function renderSection(sectionId, sectionData, isPaid = false) {
    const container = document.getElementById(sectionId);

    if (isPaid && sectionData.full_analysis) {
        // Paid: Show full content (no locks)
        container.innerHTML = `<p>${sectionData.full_analysis}</p>`;
    } else if (sectionData.preview) {
        // Teaser: Show preview + lock
        container.innerHTML = `
            <p>${sectionData.preview}</p>
            <div class="locked-content">
                <p>${sectionData.locked_teaser}</p>
                <button class="unlock-button">Unlock</button>
            </div>
        `;
    }
}
```

**CSS:**
```css
body.reading-type-palm_full .unlock-button,
body.reading-type-palm_full .premium-lock-overlay {
    display: none !important;
}
```

---

## 2. Phase 2 Prompt Design (The Core New Work)

### 2.1 Review Phase 1 Prompt (Current Teaser)

**Current Location:** `/includes/class-sm-ai-handler.php::build_teaser_prompt()` (Lines 743-861)

**What It Does:**
- Analyzes palm image (OpenAI Vision API)
- Generates 700-900 word teaser
- Uses quiz answers and user data
- Returns JSON structure

**✅ What Works Well:**
- Word count control (700-900 words)
- JSON structure enforcement
- Palm trait analysis

**⚠️ What Needs Improvement:**
- **Personalization:** Strengthen connection to user's quiz answers
- **Specificity:** Make insights more directly tied to user's input
- **Relevance:** Ensure every sentence relates to something the user shared

**Action Required:**
- Review existing prompt for personalization opportunities
- Ensure Phase 1 context is rich enough for Phase 2 to build on
- Make sure quiz answers are prominently featured in insights

---

### 2.2 New Phase 2 Prompt (700-900 Words Additional Content)

**Purpose:** Generate completion content that builds on Phase 1 insights

**Key Requirements:**
1. **Use Phase 1 Context:** Don't duplicate, extend and deepen the insights
2. **Strong Personalization:** Deep connection to user's quiz answers
3. **Actionable Insights:** Practical, specific guidance tied to user's input
4. **Word Count:** 700-900 words (same as Phase 1)
5. **Total Combined:** 1400-1800 words

**Critical Personalization Principles:**

1. **Direct Connection to Quiz Answers:**
   - Every insight must reference something the user said
   - Example: "Your response about feeling most alive when creating shows..."
   - Example: "Given your tendency to overthink decisions, as you mentioned..."

2. **Build on Phase 1 Context:**
   - Phase 1 identifies patterns
   - Phase 2 shows HOW those patterns play out specifically
   - Example: Phase 1 says "creative energy", Phase 2 says "This creative energy specifically manifests in your career choice to..."

3. **Actionable & Specific:**
   - Not generic advice
   - Tied to user's actual situation from quiz
   - Example: "For your relationship dynamic with [partner type from quiz], consider..."

**Prompt Structure:**

```
You are an expert palm reader providing deep, personalized insights.

## Context from Initial Reading (Phase 1):
{phase_1_json}

## User Information:
Name: {name}
Quiz Answers:
- Question 1: {answer_1}
- Question 2: {answer_2}
- Question 3: {answer_3}
... (all quiz answers)

## Instructions:
Based on the initial palm reading analysis above and the user's specific quiz responses, generate 700-900 words of deep, actionable insights across the following areas.

CRITICAL: Every insight MUST be directly connected to something the user shared in their quiz answers or their palm analysis. Be specific, personal, and actionable.

Generate the following sections:

1. **Deep Relationship Analysis (150-200 words)**
   - Reference their relationship patterns from quiz
   - Connect to palm traits from Phase 1
   - Provide specific, actionable guidance based on their answers

2. **Extended Career & Life Direction (150-200 words)**
   - Build on career insights from Phase 1
   - Reference their work-related quiz answers
   - Give practical next steps tied to their situation

3. **Life Purpose & Soul Mission (200-250 words)**
   - Connect to their deepest desires from quiz
   - Extend palm analysis from Phase 1
   - Provide meaningful, personal direction

4. **Shadow Work & Personal Growth (150-200 words)**
   - Address challenges mentioned in quiz
   - Build on personality traits from Phase 1
   - Offer specific transformation guidance

5. **Practical Next Steps (100-150 words)**
   - 3-5 concrete action items
   - Each tied to their specific situation
   - Realistic, achievable, personalized

WORD COUNT TARGET: 700-900 words total

PERSONALIZATION RULE: If an insight could apply to anyone, rewrite it to be specific to THIS user based on their quiz answers.

Return as JSON:
{
  "deep_relationship_analysis": { "full_content": "..." },
  "extended_career_direction": { "full_content": "..." },
  "life_purpose_soul_mission": { "full_content": "..." },
  "shadow_work_transformation": { "full_content": "..." },
  "practical_next_steps": { "full_content": "..." }
}
```

**Example of Strong vs Weak Personalization:**

❌ **Weak (Generic):**
"You have strong creative energy that can lead to fulfillment in artistic pursuits."

✅ **Strong (Personalized):**
"Your palm shows strong creative energy, which aligns perfectly with your quiz response about feeling most alive when designing spaces. This suggests interior design or architecture could be particularly fulfilling career paths for you."

---

## 3. Implementation Steps

**Step 1: Extract Phase 1 Logic**
- Refactor `generate_teaser_reading()` to separate core generation from storage
- Create `generate_teaser_context($lead_id)` that returns JSON without saving

**Step 2: Create Phase 2 Prompt Method**
- Implement `build_paid_completion_prompt($phase_1_data, $user_data, $quiz_data)`
- Implement `get_paid_completion_system_prompt()`
- Focus on strong personalization (quiz answers → insights)

**Step 3: Implement `generate_paid_completion()`**
- Call OpenAI API with Phase 2 prompt
- NO image (already analyzed in Phase 1)
- Validate Phase 2 JSON structure
- Return completion data

**Step 4: Implement `merge_paid_reading_data()`**
- Combine Phase 1 + Phase 2 JSON
- Create final `palm_full` structure
- Sections that had `preview + locked_teaser` now have `full_analysis`
- Add new premium sections from Phase 2

**Step 5: Implement `save_paid_reading()`**
- Save combined JSON to database
- Set `reading_type = 'palm_full'`
- Set `has_purchased = 1`
- Set `account_id` (required for paid readings)

**Step 6: Add REST Endpoint**
- Implement `handle_paid_reading_generate()` in REST controller
- Register route: `POST /reading/generate-paid`
- Reuse existing auth, credit check, deduction logic

**Step 7: Frontend Updates**
- Extend `renderReading()` to handle `palm_full` type
- Hide unlock UI for paid readings
- Add "Generate Full Reading (1 credit)" button to dashboard
- Show credit balance

**Step 8: E2E Tests**
- Test complete paid flow (auth → credit check → generation → deduction)
- Test insufficient credits flow (redirect to shop)
- Regression test: Ensure teaser flow still works

---

## 4. Testing (E2E Only - Playwright)

**New Test:** `tests/paid-reading-flow.spec.js`

```javascript
test('Paid reading - full generation flow', async ({ page }) => {
  // 1. Login with credits
  await loginWithAccount(page, { credits: 5 });

  // 2. Navigate to palm reading
  await page.goto('/palm-reading');

  // 3. Complete quiz
  await completeQuiz(page);

  // 4. Click "Generate Full Reading (1 credit)"
  await page.click('#generate-paid-button');

  // 5. Wait for Phase 1 + Phase 2 generation (max 60 seconds)
  await page.waitForSelector('.reading-badge.paid', { timeout: 60000 });

  // 6. Verify no locks visible
  const unlockButtons = await page.locator('.unlock-button').count();
  expect(unlockButtons).toBe(0);

  // 7. Verify all premium sections visible
  await expect(page.locator('#deep-relationship-analysis')).toBeVisible();
  await expect(page.locator('#extended-career-direction')).toBeVisible();
  await expect(page.locator('#life-purpose-soul-mission')).toBeVisible();
  await expect(page.locator('#shadow-work-transformation')).toBeVisible();
  await expect(page.locator('#practical-next-steps')).toBeVisible();

  // 8. Verify credit deducted
  const creditBalance = await page.locator('#credit-balance').textContent();
  expect(creditBalance).toContain('4 credits');
});

test('Paid reading - insufficient credits redirect', async ({ page }) => {
  // Login with NO credits
  await loginWithAccount(page, { credits: 0 });

  await page.goto('/palm-reading');
  await completeQuiz(page);
  await page.click('#generate-paid-button');

  // Expect redirect to shop
  await page.waitForURL('**/shop?service=palm-reading**');
  expect(page.url()).toContain('shop');
});

test('Regression - teaser flow still works', async ({ page }) => {
  // Free user can still generate teaser
  const testEmail = generateTestEmail();
  await completeFreeFlow(page, testEmail);

  // Verify teaser with locks
  const unlockButtons = await page.locator('.unlock-button').count();
  expect(unlockButtons).toBeGreaterThan(0);
});
```

---

## 5. JSON Schema (Paid Full Reading)

**Difference from Teaser:**

**Teaser Schema (Phase 1):**
```json
{
  "love_patterns": {
    "preview": "40-60 words visible",
    "locked_teaser": "12-20 words locked"
  }
}
```

**Paid Schema (Phase 1 + Phase 2):**
```json
{
  "love_patterns": {
    "full_analysis": "200-250 words complete insight"
  }
}
```

**New Sections (Phase 2 Only):**
```json
{
  "deep_relationship_analysis": {
    "full_content": "150-200 words"
  },
  "extended_career_direction": {
    "full_content": "150-200 words"
  },
  "life_purpose_soul_mission": {
    "full_content": "200-250 words"
  },
  "shadow_work_transformation": {
    "full_content": "150-200 words"
  },
  "practical_next_steps": {
    "full_content": "100-150 words"
  }
}
```

---

## 6. Success Criteria

**Backend:**
- [ ] `generate_paid_reading()` method works (two-phase generation)
- [ ] Phase 1 reuses existing teaser logic
- [ ] Phase 2 generates 700-900 additional words
- [ ] Combined output is 1400-1800 words
- [ ] Insights are strongly personalized (tied to quiz answers)
- [ ] Credit check happens before generation
- [ ] Credit deducted after successful generation
- [ ] Rollback works if deduction fails

**Frontend:**
- [ ] Paid readings render fully unlocked (no locks)
- [ ] "Generate Full Reading (1 credit)" button works
- [ ] Credit balance displays correctly
- [ ] Insufficient credits redirects to shop

**Testing:**
- [ ] E2E test for paid flow passes
- [ ] E2E test for insufficient credits passes
- [ ] Regression test for teaser flow passes
- [ ] Generation completes in < 60 seconds

**Personalization:**
- [ ] Phase 2 insights reference user's quiz answers
- [ ] Every section connects to something user shared
- [ ] Advice is specific, not generic

---

## 7. Time Estimate

**Total Work:** ~20-30 hours

| Task | Hours |
|------|-------|
| Extract Phase 1 logic | 2-3 |
| Design Phase 2 prompt | 4-6 |
| Implement Phase 2 generation | 3-4 |
| Merge logic | 2-3 |
| REST endpoint | 2-3 |
| Frontend rendering | 2-3 |
| E2E tests | 3-4 |
| Testing & refinement | 4-6 |

**Calendar Time:** 3-5 days (assuming focused work)

---

**End of Requirements Document**

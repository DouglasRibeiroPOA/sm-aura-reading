# [P2] Dynamic Curiosity-First Question Flow - Requirements

**Priority:** P2 (High)
**Type:** Enhancement (originally logged as bug)
**Status:** Requirements Complete - Awaiting Approval
**Created:** 2025-12-13

---

## ⚠️ IMPORTANT: Progress Tracking Instructions

**This is an ad-hoc requirement/enhancement tracked separately from main development plan.**

📊 **Progress File:** `P2-dynamic-questions-PROGRESS.md`

**CRITICAL:** After completing ANY task (backend, frontend, testing, etc.):
1. ✅ Mark the task as complete in the progress file
2. ✅ Update the phase percentage
3. ✅ Add notes about what was done
4. ✅ Update "Last Updated" timestamp
5. ✅ Move to next task or request user confirmation

**DO NOT skip progress updates.** The progress file is the single source of truth for tracking this enhancement.

---

## Executive Summary

Replace the current static quiz questions with a dynamic, JSON-driven question bank that:
- Presents curiosity-first questions based on user demographics (age, gender)
- Starts with a hook around future anxiety (finances/career/love)
- Adapts subsequent questions based on user's initial concern
- Stores both questions AND answers (not just answers)
- Feeds full Q&A context to AI prompt generation
- Allows future expansion without code changes (JSON-only updates)

---

## Current State Analysis

### Existing Implementation
- **Quiz Questions:** 5 static questions hardcoded in frontend
- **Question Flow:** Fixed sequence, same for all users
- **Data Stored:** Only user answers (not questions themselves)
- **AI Prompt:** Generic prompt that doesn't reference specific questions asked
- **Personalization:** Minimal (same questions for 18yo female vs 65yo male)

### Current Questions (from UI)
1. Energy level
2. Current life focus
3. Element resonance
4. Multi-select spiritual intentions
5. Free-text future intention

---

## Business Requirements (Interview Notes)

### INTERVIEW SECTION - TO BE COMPLETED

**Q1: What is the primary business goal of this dynamic question system?**
- ✅ **ANSWER:** All of the above
  - Increase conversion rates through personalization
  - Improve reading quality with better user context
  - Segment users for future marketing
  - Create more engaging, curiosity-driven experience


**Q2: Who are the target user segments we want to personalize for?**
- ✅ **ANSWER:**
  - **Age ranges:** 18-25, 26-35, 36-50, 51-65, 65+
  - **Gender/identity:** Male, Female, Non-binary, Prefer not to say (neutral questions)
  - **Demographics linked to age:**
    - 18-25: Career path uncertainty, finding love, identity discovery
    - 26-35: Career advancement, relationships/settling down, starting family
    - 36-50: Mid-life transitions, purpose, parenting, career peak
    - 51-65: Life transitions, legacy, health, retirement planning
    - 65+: Wisdom phase, legacy, health, relationships with adult children
  - **Approach:** Be creative and curious about real life-stage concerns


**Q3: What are the "future anxiety" categories for the first curiosity hook?**
- ✅ **ANSWER:** Five main categories:
  - **Career/Money** - Job security, financial stability, success
  - **Love/Relationships** - Finding partner, keeping love alive, heartbreak
  - **Health/Wellness** - Physical health, aging, vitality
  - **Purpose/Meaning** - Life direction, spiritual growth, legacy
  - **Family** - Children, parents, family dynamics
  - Note: All categories available to all age groups (questions within categories adapt by age)


**Q4: How should the first question work?**
- ✅ **ANSWER:** MIX OF ALL APPROACHES FOR VARIETY
  - **Strategy:** Rotate between direct, indirect/subtle, and story-based questions
  - **Selection logic:** Based on age, gender, and randomization for variety
  - **Goal:** Create engaging, varied experience so users want to generate more readings
  - **Question bank size:** Up to ~100 different questions total
  - **Key insight:** Not always the same thing - variety is critical for repeat engagement
  - Examples:
    - Direct: "What aspect of your future concerns you most?"
    - Indirect: "When you think about the next 12 months, what keeps you up at night?"
    - Story-based: "If you could ask the universe one question about your future, what would it be?"


**Q5: How many questions should each user answer total?**
- ✅ **ANSWER:** 5 questions per session
  - Same flow length as current implementation
  - Good balance between personalization and engagement
  - Questions will vary based on demographics and concern area
  - Future: Could make this variable, but start with 5


**Q6: How should subsequent questions be selected after the first hook?**
- ✅ **ANSWER:** MIX of strategies for variety
  - **Q1:** Curiosity hook - captures main concern category (Career/Love/Health/Purpose/Family)
  - **Q2-3:** Concern-focused questions (pulled from pool matching their concern + demographics)
  - **Q4:** Could be adaptive based on previous answers OR random from related pool
  - **Q5:** General spiritual/personality question (energy, element, universal themes)
  - **Selection logic:** Combine random pools + some adaptive paths + weighted selection
  - **Key:** Avoid predictable patterns - keep it fresh and engaging


**Q7: What types of questions do we want in the bank?**
- ✅ **ANSWER:** Four question types:
  - **Multiple choice** - Pick one answer from several options
  - **Multi-select checkboxes** - Pick multiple answers (like current Q4)
  - **Free text** - Open-ended response (like current Q5)
  - **Rating scales** - 1-5 stars or 1-10 numerical scale
  - Note: This provides good variety while keeping implementation manageable


**Q8: How should the AI reading reference these dynamic questions?**
- ✅ **ANSWER:** ROTATE between different approaches using multiple prompt templates
  - **Implementation:** 3-5 distinct prompt template variations
  - **Template A:** Explicit quote approach - "You told us that career concerns you most..."
  - **Template B:** Natural weaving - No direct quotes, weave insights naturally
  - **Template C:** Summary list at start, then natural flow
  - **Template D-E:** Additional variations for variety
  - **Selection:** Random selection per reading for maximum variety
  - **Benefits:** True variety for repeat users, better engagement, A/B testing potential
  - **Storage:** Store which template was used with each reading (for analytics)


**Q9: What data should we store in the database?**
- ✅ **ANSWER:** Option B - Store BOTH questions AND answers
  - **Format:** `{"q1": {"question": "What concerns you most?", "answer": "Career"}, "q2": {...}, ...}`
  - **Benefits:**
    - Full context preserved for AI prompt generation
    - Can analyze which questions perform best
    - Historical integrity (question text preserved even if bank changes)
    - Better customer support (can see exactly what user was asked)
  - **Database:** Extend current `answers_json` field in `sm_quiz` table
  - **Additional fields to store:**
    - Question text
    - Answer value(s)
    - Question type (multiple_choice, multi_select, free_text, rating)
    - Question category (career, love, health, purpose, family)
    - Demographic context (age_range, gender used for selection)


**Q10: What does "allow future expansion" mean specifically?**
- ✅ **ANSWER:** JSON file in codebase (now) + Admin UI (future Phase 2)
  - **MVP (Phase 1):**
    - Store all questions in separate JSON file in plugin directory
    - Structure: Logically organized by **Category → Age Range → Gender**
    - Example path: `Love → 18-25 → Male`, `Health → 45-55 → Female`
    - Easy to read, understand, and change manually
    - No code changes needed to add/edit questions
  - **Future (Phase 2):**
    - Admin UI to load and edit the JSON file
    - CRUD operations for questions via WordPress admin
    - JSON remains as data store (UI just edits the file)
  - **Question ordering preference:**
    - Free text questions should appear at the END (typically Q5)
    - Q1-Q4: Multiple choice, multi-select, ratings
    - Q5: Free text for deeper insights


**Q11: How many total questions should be in the initial bank?**
- Minimum: 30-40 (as mentioned in bug description)
- Breakdown:
  - How many per age range?
  - How many per gender?
  - How many per concern category?
- User response:


**Q12: Do we need to track question performance/analytics?**
- [ ] Yes, track which questions lead to better engagement
- [ ] Yes, track which questions lead to conversions
- [ ] No, just functionality for now
- User response:


**Q13: Should questions adapt in real-time based on previous answers within the same session?**
Example: User says "career" → next question could be "What aspect of your career path feels uncertain?"
- [ ] Yes, questions should adapt based on previous answers
- [ ] No, questions are selected upfront based on demographic only
- User response:


**Q14: Any questions we should NEVER ask certain demographics?**
Example: Don't ask about retirement to 18-25 year olds
- User response:


**Q15: What's the UX for the question flow?**
- [ ] Keep current step-by-step progression (one question per screen)?
- [ ] Show multiple questions on one screen?
- [ ] Progress bar still shows "Quiz 1, Quiz 2, Quiz 3..." or changes to single "Questions" step?
- User response:


---

## Technical Strategy

### Overview
Build a dynamic, JSON-driven question system that personalizes based on demographics, rotates for variety, and feeds rich context to AI prompt generation.

---

### JSON Question Bank Structure

**File Location:** `/includes/data/question-bank.json`

**Hierarchical Organization:**
```json
{
  "concern_categories": {
    "career": {
      "age_18_25": {
        "male": [...questions],
        "female": [...questions],
        "non_binary": [...questions],
        "prefer_not_to_say": [...questions]
      },
      "age_26_35": {...},
      "age_36_50": {...},
      "age_51_65": {...},
      "age_65_plus": {...}
    },
    "love": {...},
    "health": {...},
    "purpose": {...},
    "family": {...}
  },
  "first_hooks": {
    "age_18_25": {
      "male": [...hook questions],
      "female": [...hook questions],
      ...
    },
    ...
  },
  "universal_questions": {
    "free_text": [...always placed at Q5]
  }
}
```

**Individual Question Object:**
```json
{
  "id": "career_18_25_male_001",
  "question": "What aspect of your career path feels most uncertain right now?",
  "type": "multiple_choice",
  "options": [
    "Choosing the right field",
    "Getting experience",
    "Financial stability",
    "Finding my passion"
  ],
  "category": "career",
  "age_range": "18_25",
  "gender": "male",
  "position_preference": [2, 3],
  "weight": 1.0
}
```

**Question Types:**
- `multiple_choice` - Single selection
- `multi_select` - Multiple selections
- `free_text` - Open-ended
- `rating` - 1-5 or 1-10 scale

---

### Question Selection Algorithm

**Step 1: Capture Demographics**
- User provides: Age, Gender during lead capture (step 2)
- Map to categories: `age_18_25`, `male` etc.

**Step 2: Select Q1 (First Hook)**
- Pull from `first_hooks[age_range][gender]`
- Random selection from available hooks
- Determines concern category (Career/Love/Health/Purpose/Family)

**Step 3: Select Q2-Q4 (Concern-Focused)**
- Pull from `concern_categories[selected_concern][age_range][gender]`
- Filter by `position_preference` (Q2-Q4 eligible questions)
- Weighted random selection for variety
- Mix: Some adaptive (based on Q1 answer), some random from pool

**Step 4: Select Q5 (Free Text)**
- Pull from `universal_questions.free_text` or concern-specific free text
- Always position 5
- Contextual to their concern

**Variety Mechanisms:**
- Randomization within filtered pools
- Weighted selection (popular questions slightly favored)
- Session tracking to avoid same questions on repeat (future)

---

### Database Changes Required

**Update `sm_quiz` table:**
- `answers_json` field remains (expand structure)

**New JSON Structure in `answers_json`:**
```json
{
  "demographics": {
    "age_range": "18_25",
    "gender": "male"
  },
  "questions": [
    {
      "position": 1,
      "question_id": "hook_career_001",
      "question_text": "What keeps you up at night about your future?",
      "question_type": "multiple_choice",
      "category": "career",
      "answer": "Career direction",
      "options": ["Career", "Love", "Health", "Purpose", "Family"]
    },
    {
      "position": 2,
      "question_id": "career_18_25_male_005",
      "question_text": "Which career aspect concerns you most?",
      "question_type": "multiple_choice",
      "category": "career",
      "answer": "Financial stability",
      "options": [...]
    },
    ...
  ],
  "selected_at": "2025-12-13 10:30:00"
}
```

**Update `sm_readings` table:**
- Add `prompt_template_used` VARCHAR(50) - Track which template generated the reading
- Example values: `template_a_explicit`, `template_b_natural`, `template_c_summary`

---

### AI Prompt Template System

**File Location:** `/includes/data/prompt-templates.json`

**Template Structure:**
```json
{
  "templates": [
    {
      "id": "template_a_explicit",
      "name": "Explicit Quote Approach",
      "weight": 1.0,
      "prompt": "You are a mystical palm reader...\n\nThe seeker told you:\n{{QUESTION_ANSWER_EXPLICIT_LIST}}\n\nBased on their palm and these concerns, create a reading that directly references what they shared..."
    },
    {
      "id": "template_b_natural",
      "name": "Natural Weaving",
      "weight": 1.0,
      "prompt": "You are a mystical palm reader...\n\nContext about the seeker:\n{{QUESTION_ANSWER_CONTEXT}}\n\nWeave these insights naturally into the reading without explicitly quoting their answers..."
    },
    {
      "id": "template_c_summary",
      "name": "Summary List + Flow",
      "weight": 1.0,
      "prompt": "You are a mystical palm reader...\n\nSeeker's Concerns:\n{{QUESTION_ANSWER_BULLET_LIST}}\n\nBegin the reading with a brief acknowledgment of these concerns, then flow naturally..."
    }
  ]
}
```

**Placeholder Variables:**
- `{{QUESTION_ANSWER_EXPLICIT_LIST}}` - Full Q&A with quotes
- `{{QUESTION_ANSWER_CONTEXT}}` - Condensed context for AI
- `{{QUESTION_ANSWER_BULLET_LIST}}` - Bullet point summary
- `{{USER_NAME}}`, `{{AGE_RANGE}}`, `{{GENDER}}` - Demographics
- `{{PALM_IMAGE_CONTEXT}}` - Vision API output

**Template Selection:**
- Random weighted selection per reading
- Store template ID in `sm_readings.prompt_template_used`
- Future: Can analyze which templates convert best

---

### Frontend Changes Required

**Files to Modify:**
- `assets/js/script.js` - Quiz rendering logic
- `assets/js/api-integration.js` - API calls for question fetching

**Changes:**

1. **Lead Capture (Step 2):**
   - Already captures age (via age range selection) + gender
   - No changes needed

2. **Quiz Steps (Steps 6-10):**
   - **Current:** Hardcoded 5 questions
   - **New:** Dynamic question rendering
   - Fetch questions from backend via new endpoint: `GET /quiz/questions`
   - Pass: `lead_id`, `age_range`, `gender`
   - Receive: Array of 5 questions (JSON)
   - Render based on `question_type` field:
     - `multiple_choice` → Radio buttons
     - `multi_select` → Checkboxes
     - `free_text` → Textarea
     - `rating` → Star rating or slider

3. **Quiz Submission:**
   - Build new JSON structure (question + answer pairs)
   - POST to `/quiz/save` with full question/answer data

4. **UI/UX Constraints:**
   - Keep 5-step flow (Quiz 1-5)
   - Maintain existing transitions/animations
   - Icons: Can map question categories to icons

---

### Backend API Changes Required

**New Endpoint: GET `/quiz/questions`**
- **Purpose:** Generate personalized 5-question set
- **Request:** `lead_id`, `age_range`, `gender`
- **Process:**
  1. Load question bank JSON
  2. Run selection algorithm
  3. Select Q1 hook (random from demographic pool)
  4. Select Q2-Q4 based on Q1 concern + demographics
  5. Select Q5 free text
  6. Return array of 5 question objects
- **Response:**
```json
{
  "success": true,
  "questions": [
    {
      "position": 1,
      "question_id": "hook_001",
      "question": "What concerns you most?",
      "type": "multiple_choice",
      "options": ["Career", "Love", "Health", "Purpose", "Family"]
    },
    ...
  ]
}
```

**Update Endpoint: POST `/quiz/save`**
- **Current:** Saves simple answers
- **New:** Save full question+answer JSON structure
- Validate question structure
- Store in `sm_quiz.answers_json`

**Update Endpoint: POST `/reading/generate`**
- **Current:** Uses single static prompt
- **New:**
  1. Load `sm_quiz.answers_json` (full Q&A)
  2. Load `prompt-templates.json`
  3. Randomly select template (weighted)
  4. Replace placeholders with user Q&A data
  5. Call OpenAI with dynamic prompt
  6. Store `prompt_template_used` in `sm_readings` table

**New Handler Class: `SM_Question_Bank_Handler`**
- Load question bank JSON
- Implement selection algorithm
- Cache parsed JSON for performance
- Validate question structure

**New Handler Class: `SM_Prompt_Template_Handler`**
- Load prompt templates JSON
- Select template (weighted random)
- Replace placeholders with user data
- Generate final prompt for OpenAI

---

### Implementation Phases

**Phase 1: Question Bank Foundation (Week 1)**
- Create `question-bank.json` with initial 100 questions
  - 5 concern categories × 5 age ranges × 4 genders = structure
  - ~4-6 questions per demographic segment
  - Include first hooks (20-30 variations)
  - Include universal free text questions
- Create `SM_Question_Bank_Handler` class
- Implement selection algorithm
- Add unit tests for selection logic

**Phase 2: Backend API (Week 1-2)**
- Create `GET /quiz/questions` endpoint
- Update `POST /quiz/save` to handle new structure
- Update database schema (`answers_json` documentation)
- Test question selection across demographics

**Phase 3: Prompt Templates (Week 2)**
- Create `prompt-templates.json` with 3-5 templates
- Create `SM_Prompt_Template_Handler` class
- Update `POST /reading/generate` endpoint
- Add `prompt_template_used` to `sm_readings` table
- Test AI output with different templates

**Phase 4: Frontend Integration (Week 2-3)**
- Update `api-integration.js` to fetch questions
- Update `script.js` to render dynamic questions
- Support all 4 question types
- Maintain existing UI/UX flow
- Test across demographics

**Phase 5: Testing & Refinement (Week 3)**
- End-to-end testing with various demographics
- Question quality review
- Prompt template effectiveness review
- Performance optimization (JSON caching)
- Analytics setup (track template performance)

**Phase 6: Future Admin UI (Phase 2 - Future)**
- WordPress admin page to edit questions
- CRUD operations on JSON file
- Question preview
- A/B testing setup

---

## Success Criteria

### Functional Success
- ✅ Question bank JSON loads successfully with 100+ questions
- ✅ Question selection algorithm personalizes based on age + gender
- ✅ Each user receives 5 unique questions per session
- ✅ Free text questions always appear at position 5
- ✅ First hook captures concern category correctly
- ✅ Questions vary for repeat users (variety mechanism works)
- ✅ All 4 question types render correctly in UI (multiple_choice, multi_select, free_text, rating)
- ✅ Full question+answer data saved to database
- ✅ AI prompt templates rotate randomly (3-5 templates)
- ✅ AI reading references user's specific questions/answers
- ✅ No code changes needed to add/edit questions (JSON only)

### User Experience Success
- ✅ Quiz flow remains 5 steps (no change in UX)
- ✅ Questions feel personalized and relevant to user's age/gender
- ✅ First question sparks curiosity about future concerns
- ✅ Subsequent questions feel connected to their concern
- ✅ AI reading feels deeply personalized (not generic)
- ✅ Repeat users get different questions and reading styles

### Technical Success
- ✅ Question bank JSON is well-organized and easy to edit
- ✅ Selection algorithm executes in <200ms
- ✅ JSON caching improves performance
- ✅ Database stores full context for analytics
- ✅ Prompt template system is extensible (easy to add new templates)
- ✅ No breaking changes to existing API contracts
- ✅ Backward compatible (old quiz data still readable)

### Business Success
- ✅ Users are more engaged (lower drop-off during quiz)
- ✅ AI readings are richer and more personalized
- ✅ Marketing team can segment users by concern category
- ✅ Analytics show which questions perform best
- ✅ Foundation for future admin UI is in place

---

## Out of Scope (For This Phase)

### Not Included in MVP
- ❌ Admin UI to manage questions (Phase 2 - Future)
- ❌ A/B testing framework for questions
- ❌ Session tracking to avoid duplicate questions for repeat users
- ❌ Machine learning for question optimization
- ❌ Multi-language support for questions
- ❌ Image-based question options
- ❌ Video or audio question types
- ❌ Adaptive question length (always 5 for MVP)
- ❌ User-generated questions
- ❌ Social sharing of question results

### Deferred to Future Phases
- Admin UI for question management (Phase 2)
- Advanced analytics dashboard (Phase 2)
- Question performance reporting (Phase 2)
- Duplicate question prevention for repeat users (Phase 2)
- Split testing different question paths (Phase 3)

---

## Next Steps

1. ✅ Create requirements document
2. ✅ Complete interview with stakeholder
3. ✅ Define technical strategy
4. ⏳ **Review and approve requirements** ← YOU ARE HERE
5. ⏳ Begin implementation (Phase 1: Question Bank Foundation)

### Immediate Actions After Approval

**Action 1: Create Question Bank JSON Structure**
- Create `/includes/data/question-bank.json`
- Organize by: Category → Age Range → Gender
- Start with 20-30 first hook questions (varied approaches)
- Add 80-100 follow-up questions across all demographics
- Test JSON validity

**Action 2: Create Prompt Templates JSON**
- Create `/includes/data/prompt-templates.json`
- Write 3-5 template variations (explicit, natural, summary, etc.)
- Define placeholder variables
- Test template placeholder replacement logic

**Action 3: Create Backend Handlers**
- `SM_Question_Bank_Handler` class
- `SM_Prompt_Template_Handler` class
- Implement selection algorithm
- Add JSON caching for performance

**Action 4: Create New REST Endpoint**
- `GET /quiz/questions` endpoint
- Wire up to question bank handler
- Test with various demographics

**Action 5: Update Existing Endpoints**
- Update `POST /quiz/save` for new JSON structure
- Update `POST /reading/generate` for prompt templates
- Add database migration for `prompt_template_used` field

**Action 6: Frontend Integration**
- Update `api-integration.js` to fetch dynamic questions
- Update `script.js` to render question types dynamically
- Test all 4 question types (multiple choice, multi-select, free text, rating)

**Action 7: End-to-End Testing**
- Test with different age ranges and genders
- Verify variety in question selection
- Verify variety in AI reading styles
- Confirm full Q&A context in database

---

**Last Updated:** 2025-12-13
**Document Owner:** Development Team
**Stakeholder:** [Name]

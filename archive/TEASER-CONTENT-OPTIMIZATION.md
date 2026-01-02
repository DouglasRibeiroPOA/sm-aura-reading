# Teaser Content Optimization Plan

**Status:** 🚀 **IN PROGRESS**
**Started:** 2025-12-30
**Goal:** Enhance teaser reading quality, reduce API waste, and improve content depth

---

## 🎯 **OBJECTIVES**

### 1. **Remove Wasted API Fields** (8 fields, ~28% reduction)
Currently generating content that's NEVER displayed in templates:
- 5× `locked_teaser` fields (replaced with hardcoded text)
- 3× `modal_*` fields in `career_success` (not used anywhere)

**Impact:**
- ❌ Wasting ~80-160 words per reading on unused content
- ❌ Slower generation time
- ❌ Higher API costs

### 2. **Replace Locked Teasers with Dynamic Quotes**
Instead of wasting API tokens, use gender-specific inspirational quotes:
- 200 total quotes (5 sections × 2 genders × 20 quotes each)
- Deterministic selection (same reading = same quote)
- Stored in `includes/teaser-quotes.json`

**Sections:**
- `love_patterns` - Heart wisdom quotes
- `challenges_opportunities` - Transformation quotes
- `life_phase` - Transition quotes
- `timeline_6_months` - Timing quotes
- `guidance` - Direction quotes

### 3. **Strengthen Remaining Content Fields**
Increase word counts for better quality and depth:

**Vision API (Call 1) - Foundations:**
- `paragraph_1`: 60-75w → **100-130w** (+50% depth)
- `paragraph_2`: 60-75w → **100-130w** (+50% depth)
- `paragraph_3`: 40-60w → **80-100w** (+75% depth)
- `core_theme`: 20-35w → **50-70w** (+100% depth)

**Completion API (Call 2) - Previews:**
- `love_patterns.preview`: 40-60w → **120-160w** (~10 sentences)
- `challenges_opportunities.preview`: 40-60w → **120-160w** (~10 sentences)
- `life_phase.preview`: 40-60w → **120-160w** (~10 sentences)
- `timeline_6_months.preview`: 40-60w → **120-160w** (~10 sentences)
- `guidance.preview`: 40-60w → **120-160w** (~10 sentences)

---

## 📋 **IMPLEMENTATION PHASES**

### **Phase 1: Quote System** ✅ PLANNED

**1.1 Create Quote Handler**
- File: `includes/class-sm-quote-handler.php`
- Loads quotes from JSON
- Deterministic selection based on reading_id + section
- Gender-aware (male/female)

**1.2 Create Quote Database**
- File: `includes/teaser-quotes.json`
- 200 inspirational quotes
- Organized by section and gender
- Mystical, empowering tone

**1.3 Update Template Renderer**
- Modify `class-sm-template-renderer.php`
- Pass quotes to template replacements
- Add placeholders: `{{LOVE_QUOTE}}`, `{{CHALLENGES_QUOTE}}`, etc.

**1.4 Update HTML Templates**
- `palm-reading-template-teaser.html`
- `palm-reading-template-swipe-teaser.html`
- Replace hardcoded "Locked Insight:" text with quote placeholders

---

### **Phase 2: Remove Wasted Fields** 📝 PENDING

**2.1 Update Schema**
- File: `includes/class-sm-teaser-reading-schema.php` (or V2 version)
- Remove `locked_teaser` from:
  - `love_patterns`
  - `challenges_opportunities`
  - `life_phase`
  - `timeline_6_months`
  - `guidance`
- Remove from `career_success`:
  - `modal_love_patterns`
  - `modal_career_direction`
  - `modal_life_alignment`

**2.2 Update Prompts**
- File: `includes/class-sm-ai-handler.php`
- Method: `build_teaser_completion_prompt()`
- Remove wasted fields from JSON schema
- Remove wasted fields from instructions

**2.3 Update Validation**
- Remove validation rules for deleted fields
- Update word count logging

---

### **Phase 3: Strengthen Content Targets** 📝 PENDING

**3.1 Update Vision API Prompt**
- File: `includes/class-sm-ai-handler.php`
- Method: `build_palm_summary_prompt()`
- Increase `foundations_of_path` targets:
  - `paragraph_1`: 60-75w → 100-130w
  - `paragraph_2`: 60-75w → 100-130w
  - `paragraph_3`: 40-60w → 80-100w
  - `core_theme`: 20-35w → 50-70w
- Add stronger instructions: "Write AT LEAST X words per section"
- Emphasize depth and specificity

**3.2 Update Completion API Prompt**
- File: `includes/class-sm-ai-handler.php`
- Method: `build_teaser_completion_prompt()`
- Increase preview targets to 120-160 words each:
  - `love_patterns.preview`
  - `challenges_opportunities.preview`
  - `life_phase.preview`
  - `timeline_6_months.preview`
  - `guidance.preview`
- Add instruction: "Write approximately 10 sentences per preview section"

**3.3 Update Schema Validation**
- File: `includes/class-sm-teaser-reading-schema.php`
- Update `min_words` and `max_words` for all strengthened fields
- Keep validation relaxed (best-effort, won't block on short content)

---

## 📊 **EXPECTED RESULTS**

### Before Optimization:
- **Total fields:** 29 content fields
- **Wasted fields:** 8 (28%)
- **Average teaser length:** ~500-700 words
- **API calls:** 4 (due to duplicate job bug)
- **Generation time:** 35-45 seconds (inflated by duplicates)

### After Optimization:
- **Total fields:** 21 content fields (-28%)
- **Wasted fields:** 0 (0%)
- **Average teaser length:** ~900-1,200 words (+50-70%)
- **API calls:** 2 (duplicate job bug fixed)
- **Generation time:** 30-40 seconds (faster, despite more content)
- **Quote variety:** 200 dynamic quotes

### Benefits:
✅ **50-70% richer content** (foundations + previews)
✅ **28% fewer API fields** (remove waste)
✅ **50% fewer API calls** (duplicate job bug fixed)
✅ **200 dynamic quotes** (gender-specific, deterministic)
✅ **Lower API costs** (fewer calls + optimized tokens)
✅ **Faster generation** (2 calls instead of 4)
✅ **Better user experience** (deeper, more specific insights)

---

## 🧪 **TESTING STRATEGY**

### Manual Testing:
1. Generate 5 teaser readings (2 male, 3 female)
2. Verify quotes appear correctly and are gender-appropriate
3. Verify foundations section is 300-400 words (was 160-245 words)
4. Verify preview sections are 120-160 words each (was 40-60 words)
5. Check debug.log for:
   - Only 2 OpenAI API calls per teaser
   - Word count compliance logs

### Automated Testing:
- Update `tests/async-optimization.spec.js`
- Add assertions for quote presence
- Add assertions for content length
- Verify no duplicate job execution

---

## 📁 **FILES TO MODIFY**

### New Files:
- `includes/teaser-quotes.json` (200 quotes database)
- `includes/class-sm-quote-handler.php` (quote selection logic)

### Modified Files:
- `includes/class-sm-ai-handler.php` (prompts + word counts)
- `includes/class-sm-teaser-reading-schema.php` (remove wasted fields)
- `includes/class-sm-template-renderer.php` (pass quotes to templates)
- `palm-reading-template-teaser.html` (use quote placeholders)
- `palm-reading-template-swipe-teaser.html` (use quote placeholders)

### Archived Files:
- `remaining-issues.md` → `archive/2025-12-30-remaining-issues.md`

---

## 🔗 **RELATED DOCUMENTATION**

- `CONTEXT.md` - System architecture (updated with quote system)
- `CLAUDE.md` - AI assistant guide (references this plan)
- `CODEX.md` - API reference (references quote handler)
- `GEMINI.md` - Alternative AI assistant guide (references this plan)

---

## 📝 **PROGRESS TRACKING**

### Phase 1: Quote System
- [ ] Create `class-sm-quote-handler.php`
- [ ] Create `teaser-quotes.json` with 200 quotes
- [ ] Update template renderer
- [ ] Update HTML templates
- [ ] Test quote display

### Phase 2: Remove Wasted Fields
- [ ] Update schema (remove 8 fields)
- [ ] Update prompts (remove from JSON schema)
- [ ] Update validation
- [ ] Test generation with reduced fields

### Phase 3: Strengthen Content
- [ ] Update Vision API prompt (foundations)
- [ ] Update Completion API prompt (previews)
- [ ] Update schema validation
- [ ] Test content depth improvement
- [ ] Verify word count compliance

---

**Last Updated:** 2025-12-30
**Next Review:** After Phase 1 completion

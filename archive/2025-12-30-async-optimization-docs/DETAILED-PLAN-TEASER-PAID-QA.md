# Detailed Plan: Teaser-to-Paid Upgrade, Teaser Quality Alignment, and Automated QA

This document is intentionally long and line-numbered for tracking and review.
Line numbers help us reference specific steps during implementation.

## 1. Purpose and Goals
- Define a repeatable, self-healing development loop using Playwright and QA metrics.
- Upgrade teaser reports to paid reports in place (single DB row).
- Align teaser quality with paid quality (minus locked sections).
- Produce reliable, consistent word counts for every section in teaser and paid outputs.
- Eliminate teaser wording in paid reports and remove lock UI when paid.

## 2. Non-Goals
- No redesign of core UI beyond required copy changes for teaser vs paid distinctions.
- No changes to locked frontend files unless explicitly requested (assets/js/script.js, assets/css/styles.css).
- No network calls or third-party integrations beyond existing OpenAI and Account Service flows.

## 3. Key Decisions
- Upgrade in place: overwrite teaser content_data with paid content_data.
- Convert reading_type from palm_teaser to palm_full in the same row.
- Ensure has_purchased is true and unlocked_section/unlock_count are reset or cleared.
- Use a single additional OpenAI call to generate paid completion data.
- Use existing teaser data as phase 1 context without re-generating it.

## 4. Inputs Required from User
- Provide a credentials file containing paid accounts with credits.
- Recommend 2 accounts with 10 credits each for repeatable paid batch runs.
- Provide a stable hand image (optional) for consistent batch testing.
- Confirm desired path for credentials file (example below).

### 4.1 Credentials File (User Provided)
- Example path: credentials/paid-accounts.txt
- Format: one account per line, comma-separated.
- Example: email@example.com,password,account_label
- This plan will not include real credentials directly in this file.

## 5. Definitions
- Teaser: the initial reading with preview sections and locked sections.
- Paid: the full report after credit deduction, fully unlocked.
- Upgrade in place: mutate the teaser row into a paid row (same reading id).
- QA metrics: word counts, section completeness, and validation results.
- Batch run: multiple automated report generations in a single Playwright session.

## 6. Current State Summary (Observed)
- Teaser generation uses a three-part prompt for core A (opening/life_foundations/traits), core B (love/career), and secondary (challenges/phase/timeline/guidance/closing).
- Paid generation currently creates a new reading row (palm_full).
- Teaser sections can be short and inconsistent in length.
- Paid report content is generally stronger than teaser content.
- The current UI can display teaser wording even after paid upgrade.

## 7. Phase 0: Preparation
- Read CODEX.md and CONTEXT.md to confirm constraints and flow.
- Confirm which files are locked from modification.
- Review existing prompt logic in class-sm-ai-handler.php.
- Review rendering logic in class-sm-template-renderer.php and class-sm-full-template-renderer.php.
- Review reading retrieval logic in class-sm-rest-controller.php.
- Confirm test helpers in includes/class-sm-test-helpers.php.
- Verify existing Playwright tests in tests/*.spec.js.

## 8. Phase 1: Upgrade-in-Place Implementation
- Identify upgrade trigger in reading/get-by-lead path when user is logged in and asks for palm_full.
- Ensure the path uses existing teaser content_data as phase 1 input.
- Generate paid completion via one additional OpenAI call.
- Merge phase 1 and phase 2 data into a paid payload.
- Overwrite the existing teaser row rather than inserting a new row.
- Update reading_type to palm_full and has_purchased to true.
- Clear unlocked_section and reset unlock_count to 0 for paid.
- Ensure account_id is attached to the updated row.
- Deduct credit with idempotency key based on reading id.
- Render full template and return paid HTML.
- Make sure response includes reading_type palm_full.
- Update flow state to reading_ready with the same reading id.

## 9. Phase 2: Teaser Quality Alignment Strategy
- Treat teaser as a subset of the paid structure.
- Keep the same tone and depth as paid for shared sections.
- Ensure teaser sections meet minimum word counts consistently.
- Omit only the paid-only sections (the locked yellow ones).
- Ensure teaser metadata is complete and consistent (user name, timestamp, reading type).
- Ensure teaser copy does not say "teaser" in the paid context.
- Add prompt guidance to avoid shallow or overly brief teaser outputs.

## 10. Phase 3: QA Instrumentation
- Add word-count evaluation for each section in teaser and paid payloads.
- Record counts in a structured log format for easy parsing.
- Track attempts taken to expand short sections.
- Track response word count totals for teaser and paid separately.
- Report a summary of short sections for each batch run.
- Store QA results in debug.log or in a structured QA table (if needed).

## 11. Phase 4: Playwright Batch Automation
- Create a batch test script that generates 5 teaser reports and 5 paid reports.
- Use random emails for teaser generation (free flow).
- Use provided accounts for paid flows (credit-based).
- Capture screenshots for report pages for each run.
- Capture console logs and network logs for OpenAI and REST calls.
- After each run, parse QA metrics from logs and summarize results.

## 12. Phase 5: Iteration Loop (Self-Healing Process)
- Run batch tests (start with 2 runs).
- Review QA summary and find weak sections.
- Adjust prompts or expansion logic for weak sections.
- Re-run 2 more runs, then optimize again.
- Continue optimizing every run after the first 4 total runs.
- Cap total runs at 15 unless explicitly extended.
- Repeat until failure rate falls below defined threshold without breaking functionality.

## 13. Acceptance Criteria
- Teaser reports meet all minimum section word counts in 90 percent of runs.
- Paid reports meet all minimum section word counts in 95 percent of runs.
- No duplicate paid rows created when upgrading from teaser.
- Paid report UI never references "teaser" after upgrade.
- Lock buttons are removed in paid report HTML.
- Credit deduction occurs exactly once per paid upgrade.
- No breaking changes to free user flow or logged-in user flow.

## 14. Rollback and Safety
- Keep a backup of database before batch tests if running on staging with real users.
- Log every upgrade action with reading_id and lead_id.
- If paid generation fails, do not mutate the teaser row.
- If credit deduction fails, revert any paid mutation or mark for retry.
- Validate nonces for all REST endpoints used in automation.

## 15. Section Thresholds (Teaser)
- opening.reflection_p1: 40 to 60 words.
- opening.reflection_p2: 40 to 60 words.
- life_foundations.paragraph_1: 60 to 75 words.
- life_foundations.paragraph_2: 60 to 75 words.
- life_foundations.paragraph_3: 40 to 60 words.
- life_foundations.core_theme: 20 to 35 words.
- love_patterns.preview: 40 to 60 words.
- love_patterns.locked_teaser: 12 to 20 words.
- career_success.main_paragraph: 80 to 120 words.
- career_success.modal_love_patterns: 35 to 55 words.
- career_success.modal_career_direction: 35 to 55 words.
- career_success.modal_life_alignment: 35 to 55 words.
- personality_traits.intro: 70 to 100 words.
- challenges_opportunities.preview: 40 to 60 words.
- challenges_opportunities.locked_teaser: 12 to 20 words.
- life_phase.preview: 40 to 60 words.
- life_phase.locked_teaser: 12 to 20 words.
- timeline_6_months.preview: 40 to 60 words.
- timeline_6_months.locked_teaser: 12 to 20 words.
- guidance.preview: 40 to 60 words.
- guidance.locked_teaser: 12 to 20 words.
- closing.paragraph_1: 40 to 60 words.
- closing.paragraph_2: 40 to 60 words.

## 16. Section Thresholds (Paid Completion)
- love_patterns.locked_full: 160 to 220 words.
- challenges_opportunities.locked_full: 160 to 220 words.
- life_phase.locked_full: 160 to 220 words.
- timeline_6_months.locked_full: 160 to 220 words.
- guidance.locked_full: 140 to 180 words.
- deep_relationship_analysis.full_content: 160 to 220 words.
- extended_timeline_12_months.full_content: 160 to 220 words.
- life_purpose_soul_mission.full_content: 220 to 280 words.
- shadow_work_transformation.full_content: 160 to 220 words.
- practical_guidance_action_plan.full_content: 140 to 180 words.

## 17. Teaser Generation Adjustments (Detailed Steps)
- Ensure teaser prompt uses the same narrative style as paid prompt.
- Ensure teaser prompt references quiz answers explicitly.
- Ensure teaser prompt references palm summary signals explicitly.
- Require minimum word counts per section in the prompt instructions.
- Add a short-section expansion retry for teaser if any section fails.
- Store palm_summary_text in teaser meta for reuse in paid upgrade.
- Ensure teaser preview content does not contain paid-only instructions.

## 18. Paid Completion Adjustments (Detailed Steps)
- Ensure paid completion prompt uses teaser data as phase 1 context.
- Ensure paid completion prompt builds on teaser and does not repeat verbatim.
- Ensure paid completion prompt references at least 5 quiz answers.
- Normalize palm summary text so it is always non-empty.
- If phase 1 data is missing, fallback to teaser generation once and reuse it.
- Replace teaser-specific opening copy when upgrading to paid.

## 19. Data Mutation Steps for Upgrade-in-Place
- Fetch teaser reading by lead_id with reading_type palm_teaser.
- Parse content_data into phase 1 data.
- Generate paid completion from phase 1 data (one call).
- Merge phase 1 + phase 2 into paid payload.
- Update content_data to the merged payload.
- Update reading_type to palm_full.
- Set has_purchased to true.
- Clear unlock_count and unlocked_section fields.
- Set account_id to current authenticated account_id.
- Save updated_at timestamp.
- Deduct credit with idempotency key based on reading id.
- Render paid HTML for response.

## 20. UI Copy Adjustments
- Remove any teaser-only copy when reading_type is palm_full.
- Update paid rendering template to show paid language.
- Confirm lock buttons are removed in paid HTML output.
- Confirm unlock badges are removed in paid HTML output.

## 21. Playwright Batch Testing Overview
- Run 5 teaser generations.
- Run 5 paid upgrades using provided accounts.
- Capture logs and screenshots.
- Summarize QA metrics and failures.
- Iterate on prompts and data handling until stable.

## 22. Playwright Setup Steps (Detailed)
- Confirm Playwright is installed and tests can run locally.
- Ensure DevMode is disabled when running paid real OpenAI tests.
- Ensure accounts have sufficient credits before the run.
- Ensure test helper endpoints are enabled in DevMode for teaser-only tests if needed.
- Define a batch run config for test counts and account usage.

## 23. QA Metrics Logging Format
- Log prefix: [SM QA] for all QA metric lines.
- Include reading_id and lead_id on every QA line.
- Include reading_type (palm_teaser or palm_full).
- Include section name and word count.
- Include attempt count for expansion retries.
- Include pass or fail status per section.

## 24. Example QA Summary Output
- Total teaser runs: 5
- Total paid runs: 5
- Teaser failures: love_patterns.preview (2/5)
- Paid failures: none
- Retried sections: life_phase.preview (1 time)

## 25. Appendix A: Teaser Run Steps (Run 1 to Run 5)
### Teaser Run 1
- TR1.01: Start a new browser context for isolation.
- TR1.02: Navigate to the palm reading URL.
- TR1.03: Complete lead capture with a unique email.
- TR1.04: Trigger OTP flow and retrieve OTP via helper.
- TR1.05: Verify OTP and proceed to quiz.
- TR1.06: Complete demographics and quiz answers.
- TR1.07: Upload palm image or use existing fixture.
- TR1.08: Submit quiz and wait for teaser generation.
- TR1.09: Wait for result page to render.
- TR1.10: Capture screenshot of the teaser report.
- TR1.11: Extract reading_id from the DOM.
- TR1.12: Record reading_type from data attributes.
- TR1.13: Parse QA metrics from logs for this reading.
- TR1.14: Validate each section word count against thresholds.
- TR1.15: Note any short sections in summary output.
- TR1.16: Verify lock buttons are present for locked sections.
- TR1.17: Click one unlock and verify UI state updates.
- TR1.18: Confirm unlock_count increments in data attribute.
- TR1.19: Refresh the report page and ensure state persists.
- TR1.20: Save console logs to test-results folder.
- TR1.21: Save network log for OpenAI calls if available.
- TR1.22: End the browser context.
- TR1.23: Append run results to QA summary.
- TR1.24: Tag this run as teaser in the summary.
- TR1.25: Ensure no errors in debug.log for this run.
### Teaser Run 2
- TR2.01: Start a new browser context for isolation.
- TR2.02: Navigate to the palm reading URL.
- TR2.03: Complete lead capture with a unique email.
- TR2.04: Trigger OTP flow and retrieve OTP via helper.
- TR2.05: Verify OTP and proceed to quiz.
- TR2.06: Complete demographics and quiz answers.
- TR2.07: Upload palm image or use existing fixture.
- TR2.08: Submit quiz and wait for teaser generation.
- TR2.09: Wait for result page to render.
- TR2.10: Capture screenshot of the teaser report.
- TR2.11: Extract reading_id from the DOM.
- TR2.12: Record reading_type from data attributes.
- TR2.13: Parse QA metrics from logs for this reading.
- TR2.14: Validate each section word count against thresholds.
- TR2.15: Note any short sections in summary output.
- TR2.16: Verify lock buttons are present for locked sections.
- TR2.17: Click one unlock and verify UI state updates.
- TR2.18: Confirm unlock_count increments in data attribute.
- TR2.19: Refresh the report page and ensure state persists.
- TR2.20: Save console logs to test-results folder.
- TR2.21: Save network log for OpenAI calls if available.
- TR2.22: End the browser context.
- TR2.23: Append run results to QA summary.
- TR2.24: Tag this run as teaser in the summary.
- TR2.25: Ensure no errors in debug.log for this run.
### Teaser Run 3
- TR3.01: Start a new browser context for isolation.
- TR3.02: Navigate to the palm reading URL.
- TR3.03: Complete lead capture with a unique email.
- TR3.04: Trigger OTP flow and retrieve OTP via helper.
- TR3.05: Verify OTP and proceed to quiz.
- TR3.06: Complete demographics and quiz answers.
- TR3.07: Upload palm image or use existing fixture.
- TR3.08: Submit quiz and wait for teaser generation.
- TR3.09: Wait for result page to render.
- TR3.10: Capture screenshot of the teaser report.
- TR3.11: Extract reading_id from the DOM.
- TR3.12: Record reading_type from data attributes.
- TR3.13: Parse QA metrics from logs for this reading.
- TR3.14: Validate each section word count against thresholds.
- TR3.15: Note any short sections in summary output.
- TR3.16: Verify lock buttons are present for locked sections.
- TR3.17: Click one unlock and verify UI state updates.
- TR3.18: Confirm unlock_count increments in data attribute.
- TR3.19: Refresh the report page and ensure state persists.
- TR3.20: Save console logs to test-results folder.
- TR3.21: Save network log for OpenAI calls if available.
- TR3.22: End the browser context.
- TR3.23: Append run results to QA summary.
- TR3.24: Tag this run as teaser in the summary.
- TR3.25: Ensure no errors in debug.log for this run.
### Teaser Run 4
- TR4.01: Start a new browser context for isolation.
- TR4.02: Navigate to the palm reading URL.
- TR4.03: Complete lead capture with a unique email.
- TR4.04: Trigger OTP flow and retrieve OTP via helper.
- TR4.05: Verify OTP and proceed to quiz.
- TR4.06: Complete demographics and quiz answers.
- TR4.07: Upload palm image or use existing fixture.
- TR4.08: Submit quiz and wait for teaser generation.
- TR4.09: Wait for result page to render.
- TR4.10: Capture screenshot of the teaser report.
- TR4.11: Extract reading_id from the DOM.
- TR4.12: Record reading_type from data attributes.
- TR4.13: Parse QA metrics from logs for this reading.
- TR4.14: Validate each section word count against thresholds.
- TR4.15: Note any short sections in summary output.
- TR4.16: Verify lock buttons are present for locked sections.
- TR4.17: Click one unlock and verify UI state updates.
- TR4.18: Confirm unlock_count increments in data attribute.
- TR4.19: Refresh the report page and ensure state persists.
- TR4.20: Save console logs to test-results folder.
- TR4.21: Save network log for OpenAI calls if available.
- TR4.22: End the browser context.
- TR4.23: Append run results to QA summary.
- TR4.24: Tag this run as teaser in the summary.
- TR4.25: Ensure no errors in debug.log for this run.
### Teaser Run 5
- TR5.01: Start a new browser context for isolation.
- TR5.02: Navigate to the palm reading URL.
- TR5.03: Complete lead capture with a unique email.
- TR5.04: Trigger OTP flow and retrieve OTP via helper.
- TR5.05: Verify OTP and proceed to quiz.
- TR5.06: Complete demographics and quiz answers.
- TR5.07: Upload palm image or use existing fixture.
- TR5.08: Submit quiz and wait for teaser generation.
- TR5.09: Wait for result page to render.
- TR5.10: Capture screenshot of the teaser report.
- TR5.11: Extract reading_id from the DOM.
- TR5.12: Record reading_type from data attributes.
- TR5.13: Parse QA metrics from logs for this reading.
- TR5.14: Validate each section word count against thresholds.
- TR5.15: Note any short sections in summary output.
- TR5.16: Verify lock buttons are present for locked sections.
- TR5.17: Click one unlock and verify UI state updates.
- TR5.18: Confirm unlock_count increments in data attribute.
- TR5.19: Refresh the report page and ensure state persists.
- TR5.20: Save console logs to test-results folder.
- TR5.21: Save network log for OpenAI calls if available.
- TR5.22: End the browser context.
- TR5.23: Append run results to QA summary.
- TR5.24: Tag this run as teaser in the summary.
- TR5.25: Ensure no errors in debug.log for this run.

## 26. Appendix B: Paid Run Steps (Run 1 to Run 5)
### Paid Run 1
- PR1.01: Start a new browser context for isolation.
- PR1.02: Login using paid account from credentials file.
- PR1.03: Navigate to dashboard and click view readings.
- PR1.04: Select a teaser reading (from previous or seeded run).
- PR1.05: Open report with sm_report parameter.
- PR1.06: Ensure the system requests palm_full reading type.
- PR1.07: Trigger upgrade-in-place to paid reading.
- PR1.08: Wait for paid HTML to render.
- PR1.09: Confirm reading_type is palm_full in data attributes.
- PR1.10: Confirm lock buttons are absent.
- PR1.11: Confirm teaser copy is absent in paid view.
- PR1.12: Capture screenshot of paid report.
- PR1.13: Extract reading_id and verify it matches original teaser id.
- PR1.14: Confirm only one row exists for the reading (if validated).
- PR1.15: Parse QA metrics for paid sections.
- PR1.16: Validate paid section word counts against thresholds.
- PR1.17: Verify credit deduction occurred once.
- PR1.18: Refresh the paid report page and ensure persistence.
- PR1.19: Check debug.log for upgrade-related errors.
- PR1.20: Save console logs to test-results folder.
- PR1.21: Save network logs for OpenAI paid call.
- PR1.22: End the browser context.
- PR1.23: Append run results to QA summary.
- PR1.24: Tag this run as paid in the summary.
- PR1.25: Validate flow state is reading_ready in backend.
- PR1.26: Verify account_id is attached to the updated reading.
- PR1.27: Verify unlock_count is reset to 0 for paid.
- PR1.28: Verify unlocked_section is cleared for paid.
- PR1.29: Confirm updated_at is set correctly.
- PR1.30: Confirm no duplicate paid rows created.
### Paid Run 2
- PR2.01: Start a new browser context for isolation.
- PR2.02: Login using paid account from credentials file.
- PR2.03: Navigate to dashboard and click view readings.
- PR2.04: Select a teaser reading (from previous or seeded run).
- PR2.05: Open report with sm_report parameter.
- PR2.06: Ensure the system requests palm_full reading type.
- PR2.07: Trigger upgrade-in-place to paid reading.
- PR2.08: Wait for paid HTML to render.
- PR2.09: Confirm reading_type is palm_full in data attributes.
- PR2.10: Confirm lock buttons are absent.
- PR2.11: Confirm teaser copy is absent in paid view.
- PR2.12: Capture screenshot of paid report.
- PR2.13: Extract reading_id and verify it matches original teaser id.
- PR2.14: Confirm only one row exists for the reading (if validated).
- PR2.15: Parse QA metrics for paid sections.
- PR2.16: Validate paid section word counts against thresholds.
- PR2.17: Verify credit deduction occurred once.
- PR2.18: Refresh the paid report page and ensure persistence.
- PR2.19: Check debug.log for upgrade-related errors.
- PR2.20: Save console logs to test-results folder.
- PR2.21: Save network logs for OpenAI paid call.
- PR2.22: End the browser context.
- PR2.23: Append run results to QA summary.
- PR2.24: Tag this run as paid in the summary.
- PR2.25: Validate flow state is reading_ready in backend.
- PR2.26: Verify account_id is attached to the updated reading.
- PR2.27: Verify unlock_count is reset to 0 for paid.
- PR2.28: Verify unlocked_section is cleared for paid.
- PR2.29: Confirm updated_at is set correctly.
- PR2.30: Confirm no duplicate paid rows created.
### Paid Run 3
- PR3.01: Start a new browser context for isolation.
- PR3.02: Login using paid account from credentials file.
- PR3.03: Navigate to dashboard and click view readings.
- PR3.04: Select a teaser reading (from previous or seeded run).
- PR3.05: Open report with sm_report parameter.
- PR3.06: Ensure the system requests palm_full reading type.
- PR3.07: Trigger upgrade-in-place to paid reading.
- PR3.08: Wait for paid HTML to render.
- PR3.09: Confirm reading_type is palm_full in data attributes.
- PR3.10: Confirm lock buttons are absent.
- PR3.11: Confirm teaser copy is absent in paid view.
- PR3.12: Capture screenshot of paid report.
- PR3.13: Extract reading_id and verify it matches original teaser id.
- PR3.14: Confirm only one row exists for the reading (if validated).
- PR3.15: Parse QA metrics for paid sections.
- PR3.16: Validate paid section word counts against thresholds.
- PR3.17: Verify credit deduction occurred once.
- PR3.18: Refresh the paid report page and ensure persistence.
- PR3.19: Check debug.log for upgrade-related errors.
- PR3.20: Save console logs to test-results folder.
- PR3.21: Save network logs for OpenAI paid call.
- PR3.22: End the browser context.
- PR3.23: Append run results to QA summary.
- PR3.24: Tag this run as paid in the summary.
- PR3.25: Validate flow state is reading_ready in backend.
- PR3.26: Verify account_id is attached to the updated reading.
- PR3.27: Verify unlock_count is reset to 0 for paid.
- PR3.28: Verify unlocked_section is cleared for paid.
- PR3.29: Confirm updated_at is set correctly.
- PR3.30: Confirm no duplicate paid rows created.
### Paid Run 4
- PR4.01: Start a new browser context for isolation.
- PR4.02: Login using paid account from credentials file.
- PR4.03: Navigate to dashboard and click view readings.
- PR4.04: Select a teaser reading (from previous or seeded run).
- PR4.05: Open report with sm_report parameter.
- PR4.06: Ensure the system requests palm_full reading type.
- PR4.07: Trigger upgrade-in-place to paid reading.
- PR4.08: Wait for paid HTML to render.
- PR4.09: Confirm reading_type is palm_full in data attributes.
- PR4.10: Confirm lock buttons are absent.
- PR4.11: Confirm teaser copy is absent in paid view.
- PR4.12: Capture screenshot of paid report.
- PR4.13: Extract reading_id and verify it matches original teaser id.
- PR4.14: Confirm only one row exists for the reading (if validated).
- PR4.15: Parse QA metrics for paid sections.
- PR4.16: Validate paid section word counts against thresholds.
- PR4.17: Verify credit deduction occurred once.
- PR4.18: Refresh the paid report page and ensure persistence.
- PR4.19: Check debug.log for upgrade-related errors.
- PR4.20: Save console logs to test-results folder.
- PR4.21: Save network logs for OpenAI paid call.
- PR4.22: End the browser context.
- PR4.23: Append run results to QA summary.
- PR4.24: Tag this run as paid in the summary.
- PR4.25: Validate flow state is reading_ready in backend.
- PR4.26: Verify account_id is attached to the updated reading.
- PR4.27: Verify unlock_count is reset to 0 for paid.
- PR4.28: Verify unlocked_section is cleared for paid.
- PR4.29: Confirm updated_at is set correctly.
- PR4.30: Confirm no duplicate paid rows created.
### Paid Run 5
- PR5.01: Start a new browser context for isolation.
- PR5.02: Login using paid account from credentials file.
- PR5.03: Navigate to dashboard and click view readings.
- PR5.04: Select a teaser reading (from previous or seeded run).
- PR5.05: Open report with sm_report parameter.
- PR5.06: Ensure the system requests palm_full reading type.
- PR5.07: Trigger upgrade-in-place to paid reading.
- PR5.08: Wait for paid HTML to render.
- PR5.09: Confirm reading_type is palm_full in data attributes.
- PR5.10: Confirm lock buttons are absent.
- PR5.11: Confirm teaser copy is absent in paid view.
- PR5.12: Capture screenshot of paid report.
- PR5.13: Extract reading_id and verify it matches original teaser id.
- PR5.14: Confirm only one row exists for the reading (if validated).
- PR5.15: Parse QA metrics for paid sections.
- PR5.16: Validate paid section word counts against thresholds.
- PR5.17: Verify credit deduction occurred once.
- PR5.18: Refresh the paid report page and ensure persistence.
- PR5.19: Check debug.log for upgrade-related errors.
- PR5.20: Save console logs to test-results folder.
- PR5.21: Save network logs for OpenAI paid call.
- PR5.22: End the browser context.
- PR5.23: Append run results to QA summary.
- PR5.24: Tag this run as paid in the summary.
- PR5.25: Validate flow state is reading_ready in backend.
- PR5.26: Verify account_id is attached to the updated reading.
- PR5.27: Verify unlock_count is reset to 0 for paid.
- PR5.28: Verify unlocked_section is cleared for paid.
- PR5.29: Confirm updated_at is set correctly.
- PR5.30: Confirm no duplicate paid rows created.

## 27. Appendix C: QA Summary Template
- Batch Run ID: YYYYMMDD-HHMM
- Teaser Runs: 5
- Paid Runs: 5
- Teaser Failures by Section: list counts
- Paid Failures by Section: list counts
- Total Retried Sections: count
- Total OpenAI Calls: count
- Total Credits Deducted: count
- Notes: freeform observations

## 28. Appendix D: File Touch List
- includes/class-sm-ai-handler.php (prompts, generation, QA metrics)
- includes/class-sm-rest-controller.php (upgrade-in-place flow)
- includes/class-sm-reading-service.php (update reading data)
- includes/class-sm-full-template-renderer.php (paid UI cleanup)
- includes/class-sm-template-renderer.php (teaser template copy)
- assets/js/api-integration.js (report loading, redirects)
- tests/e2e-full-flow.spec.js (batch tests)
- tests/palm-reading-flow.spec.js (smoke checks)

## 29. Appendix E: Risk Checklist
- Risk: duplicate reading rows when upgrading. Mitigation: in-place update only.
- Risk: credit deduction fails after upgrade. Mitigation: rollback or mark for retry.
- Risk: prompts produce short sections. Mitigation: expansion retries and QA gates.
- Risk: UI still shows teaser text. Mitigation: conditional template copy.
- Risk: state mismatch after refresh. Mitigation: rely on server source of truth.

## 30. Appendix F: Detailed QA Checklist (Expanded)
- QC01: Verify opening reflection word counts meet thresholds.
- QC02: Verify life_foundations paragraphs meet thresholds.
- QC03: Verify core_theme is present and within range.
- QC04: Verify love_patterns preview is present and within range.
- QC05: Verify love_patterns locked_teaser is present.
- QC06: Verify career_success main_paragraph within range.
- QC07: Verify career_success modal_love_patterns within range.
- QC08: Verify career_success modal_career_direction within range.
- QC09: Verify career_success modal_life_alignment within range.
- QC10: Verify personality_traits intro within range.
- QC11: Verify personality_traits trait names are valid.
- QC12: Verify personality_traits trait scores are within 0-100.
- QC13: Verify challenges_opportunities preview within range.
- QC14: Verify challenges_opportunities locked_teaser present.
- QC15: Verify life_phase preview within range.
- QC16: Verify life_phase locked_teaser present.
- QC17: Verify timeline_6_months preview within range.
- QC18: Verify timeline_6_months locked_teaser present.
- QC19: Verify guidance preview within range.
- QC20: Verify guidance locked_teaser present.
- QC21: Verify closing paragraph_1 within range.
- QC22: Verify closing paragraph_2 within range.
- QC23: Verify paid deep_relationship_analysis full_content within range.
- QC24: Verify paid extended_timeline_12_months full_content within range.
- QC25: Verify paid life_purpose_soul_mission full_content within range.
- QC26: Verify paid shadow_work_transformation full_content within range.
- QC27: Verify paid practical_guidance_action_plan full_content within range.
- QC28: Verify no teaser wording in paid HTML.
- QC29: Verify no lock buttons in paid HTML.
- QC30: Verify unlock_count reset to 0 after paid upgrade.
- QC31: Verify unlocked_section cleared after paid upgrade.
- QC32: Verify account_id attached to paid reading row.
- QC33: Verify updated_at updated after paid upgrade.
- QC34: Verify idempotency key used for credit deduction.
- QC35: Verify only one credit deducted per upgrade.
- QC36: Verify reading_type is palm_full after upgrade.
- QC37: Verify reading_type is palm_teaser before upgrade.
- QC38: Verify reading_id remains same after upgrade.
- QC39: Verify flow state reading_ready after upgrade.
- QC40: Verify sm_report param removed after rendering to avoid loops.
- QC41: Verify report refresh loads correct reading type.
- QC42: Verify report refresh uses server state as source of truth.
- QC43: Verify magic token path still works for free users.
- QC44: Verify logged-out users never receive paid content.
- QC45: Verify credit exhaustion redirects to shop.
- QC46: Verify credit exhaustion does not mutate teaser row.
- QC47: Verify QA metrics log includes section name and word count.
- QC48: Verify QA metrics log includes reading_id and lead_id.
- QC49: Verify QA metrics log includes reading_type.
- QC50: Verify QA metrics log includes pass or fail status.
- QC51: Verify QA metrics log includes retry count.
- QC52: Verify QA summary aggregates failures correctly.
- QC53: Verify batch run script saves screenshots for each run.
- QC54: Verify batch run script saves console logs for each run.
- QC55: Verify batch run script saves network logs for each run.
- QC56: Verify batch run script tags run results with run id.
- QC57: Verify teaser batch runs use random emails.
- QC58: Verify paid batch runs use paid accounts from file.
- QC59: Verify DevMode is disabled for paid runs unless explicitly requested.
- QC60: Verify debug.log is clean after batch run.
- QC61: Verify no PHP warnings in debug.log.
- QC62: Verify no JS errors in console output.
- QC63: Verify OpenAI responses are parsed without JSON errors.
- QC64: Verify JSON schema validation warnings are logged if present.
- QC65: Verify short sections trigger expansion attempts.
- QC66: Verify expansion attempts do not alter section keys.
- QC67: Verify expansion attempts keep trait scores stable.
- QC68: Verify expansion attempts keep user_name stable.
- QC69: Verify palm_summary_text is stored in teaser meta.
- QC70: Verify palm_summary_text is reused in paid completion.
- QC71: Verify palm summary is normalized if empty.
- QC72: Verify paid completion uses phase 1 context directly.
- QC73: Verify paid completion avoids repeated sentences from teaser.
- QC74: Verify paid completion references at least 5 quiz answers.
- QC75: Verify paid completion uses warm, grounded tone.
- QC76: Verify teaser prompt avoids weak or generic statements.
- QC77: Verify teaser prompt does not mention "paid" or "upgrade" too early.
- QC78: Verify the report title and date are correct in the dashboard list.
- QC79: Verify the dashboard list does not show duplicate rows after upgrade.
- QC80: Verify all REST endpoints are nonce-protected.
- QC81: Verify rate limiting is in place for relevant endpoints.
- QC82: Verify database updates use prepared statements.
- QC83: Verify output escaping is applied in templates.
- QC84: Verify sanitized inputs for lead_id and account_id.
- QC85: Verify read-only reports listing remains functional.
- QCX1.01: Recheck teaser run 1 word counts after any prompt change.
- QCX1.02: Recheck teaser run 1 UI for locked sections.
- QCX1.03: Recheck teaser run 1 unlock behavior.
- QCX1.04: Recheck teaser run 1 refresh persistence.
- QCX1.05: Recheck teaser run 1 debug.log for errors.
- QCX1.06: Recheck paid run 1 word counts after any prompt change.
- QCX1.07: Recheck paid run 1 UI for lock removal.
- QCX1.08: Recheck paid run 1 credit deduction count.
- QCX1.09: Recheck paid run 1 refresh persistence.
- QCX1.10: Recheck paid run 1 debug.log for errors.
- QCX2.01: Recheck teaser run 2 word counts after any prompt change.
- QCX2.02: Recheck teaser run 2 UI for locked sections.
- QCX2.03: Recheck teaser run 2 unlock behavior.
- QCX2.04: Recheck teaser run 2 refresh persistence.
- QCX2.05: Recheck teaser run 2 debug.log for errors.
- QCX2.06: Recheck paid run 2 word counts after any prompt change.
- QCX2.07: Recheck paid run 2 UI for lock removal.
- QCX2.08: Recheck paid run 2 credit deduction count.
- QCX2.09: Recheck paid run 2 refresh persistence.
- QCX2.10: Recheck paid run 2 debug.log for errors.
- QCX3.01: Recheck teaser run 3 word counts after any prompt change.
- QCX3.02: Recheck teaser run 3 UI for locked sections.
- QCX3.03: Recheck teaser run 3 unlock behavior.
- QCX3.04: Recheck teaser run 3 refresh persistence.
- QCX3.05: Recheck teaser run 3 debug.log for errors.
- QCX3.06: Recheck paid run 3 word counts after any prompt change.
- QCX3.07: Recheck paid run 3 UI for lock removal.
- QCX3.08: Recheck paid run 3 credit deduction count.
- QCX3.09: Recheck paid run 3 refresh persistence.
- QCX3.10: Recheck paid run 3 debug.log for errors.
- QCX4.01: Recheck teaser run 4 word counts after any prompt change.
- QCX4.02: Recheck teaser run 4 UI for locked sections.
- QCX4.03: Recheck teaser run 4 unlock behavior.
- QCX4.04: Recheck teaser run 4 refresh persistence.
- QCX4.05: Recheck teaser run 4 debug.log for errors.
- QCX4.06: Recheck paid run 4 word counts after any prompt change.
- QCX4.07: Recheck paid run 4 UI for lock removal.
- QCX4.08: Recheck paid run 4 credit deduction count.
- QCX4.09: Recheck paid run 4 refresh persistence.
- QCX4.10: Recheck paid run 4 debug.log for errors.
- QCX5.01: Recheck teaser run 5 word counts after any prompt change.
- QCX5.02: Recheck teaser run 5 UI for locked sections.
- QCX5.03: Recheck teaser run 5 unlock behavior.
- QCX5.04: Recheck teaser run 5 refresh persistence.
- QCX5.05: Recheck teaser run 5 debug.log for errors.
- QCX5.06: Recheck paid run 5 word counts after any prompt change.
- QCX5.07: Recheck paid run 5 UI for lock removal.
- QCX5.08: Recheck paid run 5 credit deduction count.
- QCX5.09: Recheck paid run 5 refresh persistence.
- QCX5.10: Recheck paid run 5 debug.log for errors.

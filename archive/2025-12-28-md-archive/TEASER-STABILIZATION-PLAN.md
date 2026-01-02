# Teaser Stabilization Plan

Goal: stabilize teaser output quality without regressing paid flow, then fix swipe template rendering gaps, then add a palm-summary card.

## Phase 1: Teaser Output Stability (backend only)
- Enforce teaser minimums before saving (reject/redo underfilled payloads).
- Improve fallback logging and surface palm summary details in trace logs.
- Confirm rescue prompt output meets minimum section word counts.

Testing
- Generate a teaser reading with `SM_OPENAI_TRACE` enabled.
- Check `app/public/wp-content/uploads/sm-logs/debug.log` for:
  - `OPENAI_TRACE` → `Palm summary details`
  - `AI_READING` → `Teaser payload summary` (counts)
  - No `Teaser payload too short` warnings on the final attempt.

## Phase 2: Swipe Teaser Rendering Gaps
- Add missing marker blocks in the swipe teaser template.
- Verify all teaser sections render in the swipe layout.

Testing
- Open swipe teaser view and confirm each section is visible (including timeline, purpose, shadow, guidance placeholders).

## Phase 3: Palm Snapshot Card
- Persist palm summary data in reading payloads.
- Add a “Palm Snapshot” card using hand type, line observations, mounts, markings, and overall energy.

Testing
- Confirm palm snapshot content matches the `Palm summary details` log for the same lead.

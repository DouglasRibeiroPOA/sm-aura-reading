# Palm Summary Strategy (3-Call Flow)

This document outlines the new strategy to reduce token usage, improve palm relevance, and minimize image refusals.

## Goals

- Send the palm image only once.
- Keep prompts smaller and more controllable.
- Ensure teaser sections are personalized and not vague.
- Make fallbacks visible in logs, not hidden.

## Flow Overview

### Call 1: Palm Summary (image)
**Purpose:** Extract compact, concrete palm observations.

**Input:** palm image + short quiz summary  
**Output:** small JSON summary

Example fields:
- `hand_type`
- `line_observations` (life/head/heart/fate)
- `mounts` (top 3)
- `markings` (2 items)
- `overall_energy`

### Call 2: Teaser Reading (text-only)
**Purpose:** Generate the teaser content with locked/unlocked sections.

**Input:** palm summary + short quiz summary  
**Output:** teaser JSON (same structure as before)

Rules:
- Explicitly reference at least 3 quiz answers.
- Avoid vague generalities.
- Follow word targets for each section.

### Call 3: Paid Completion (text-only)
**Purpose:** Expand locked sections and add premium sections.

**Input:** palm summary + quiz summary + phase 1 JSON  
**Output:** paid completion JSON

Rules:
- Explicitly reference at least 5 quiz answers.
- Word targets adjusted to ~1300-1600 words total.

## Logging Checkpoints

Look in `wp-content/uploads/sm-logs/debug.log` for:

- `AI_READING` → `Palm summary generated` (attempts + word count)
- `AI_READING` → `Teaser payload too short` / `Paid payload too short`
- `OPENAI_TRACE` → request/response metadata and word counts
- `attempts` arrays showing fallback usage

## Notes

- OpenAI does not preserve image context across calls; the palm summary is the reusable context.
- If the image is unclear, the palm summary prompt instructs a best-guess without mentioning limitations.

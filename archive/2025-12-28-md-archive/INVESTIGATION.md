# Report Generation Investigation

We are actively investigating issues with the new report generation flow. This document captures the current context, observed behavior, and findings so far.

## Context

- Users reported that the new swipe report sometimes renders with very short content per section.
- The teaser report feels slow, and it is unclear whether fallback strategies are being used.
- We want to confirm whether OpenAI calls are healthy, whether retries/fallbacks are kicking in, and why content length is inconsistent.

## Observed Behavior

- The report can render and swipe, but sections may contain only a couple of sentences.
- Teaser generation can take a long time.
- The system sometimes retries to get acceptable content.

## Findings (from logs)

- OpenAI requests that include the palm image are sometimes refused.
- When the image request is refused, the system retries without the image.
- If the teaser payload is still too short, a rescue retry is triggered.
- Paid report payloads typically have longer sections, but teaser content can remain short after retries.

## Logging Added

We added logging throughout the flow to help pinpoint failures and fallbacks:

- AI handler logs the attempt chain (primary with image, retry without image, rescue retry).
- Paid completion now logs when short payload retries occur and records attempt chain.
- Payload summaries include word counts per section to detect short content.
- REST controller logs request entry points, HTML length, and fallback usage.
- Template renderer logs which template was used and data summary.

Logs live at:

- `app/public/wp-content/uploads/sm-logs/debug.log`

## Open Questions

- Are image-based requests being consistently refused (and why)?
- Is the teaser schema too permissive, allowing short content to pass?
- Do we need stronger minimum-length checks or prompt adjustments to force fuller content?

## Next Steps

- Monitor logs during new report generation runs.
- If short content persists, tighten validation and/or prompt constraints.
- Confirm if OpenAI refusal rate drops when removing the image input.

## Current Test Focus

- Generate a teaser + paid reading, then inspect `wp-content/uploads/sm-logs/debug.log`.
- Look for `OPENAI_TRACE` entries to capture request/response metadata and word counts.
- Check for `AI_READING` warnings like `Teaser payload too short` or `Paid payload too short`.
- Confirm the attempt chain (primary, retry without image, rescue) to see whether fallbacks were used.

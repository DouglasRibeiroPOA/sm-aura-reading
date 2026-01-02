# BUGS-LOG

Use this file only when working on bugs (if explicitly requested). Keep entries concise and move items from **Active Bugs** to **Resolved Bugs** once fixed.

## Active Bugs

> Always fix in listed order (top = highest priority). After each bug is fixed, **stop and request user confirmation that the bug works** before starting the next item.

- **[NEW] Lead/session data persists across new sessions** — Local/session cache keeps prior name/email/age identity causing stale data on reopen/magic-link; user wants no personal data persisted beyond session (age derived server-side).
- **[NEW] Toast styling scope** — White “offerings” toast appearing in all steps and lingering too long; should only show the white/CTA variant for credits-exhausted case, otherwise use default dark toast.
- **[NEW] Back navigation after email verification** — Going back to change email is blocked/sticky because prior lead state persists; should allow returning to lead capture with cleared API/user data.

- **[P3] Reading prompt & layout personalization (backups reference)** — Reintroduce the transparent/end-of-report styling cues from `assets/js/script.js.backup` and `assets/css/styles.css.backup` as end-of-report highlights, and shift the prompt to lead with the user’s inputs. Requirements: open report with a short paragraph explicitly referencing captured user inputs; include “Hand Shape & Energy” as bullet points; add closing section with callouts tied to user data using the backup design vibe; ensure prompt feeds full Q&A context to the AI; keep existing no-UL/LI frontend constraints.

## Resolved Bugs

### Current Session (2025-12-13)
- **Dynamic curiosity-first question flow (age/gender aware)** — Implemented JSON-driven question bank with demographics, randomization, AI template integration, frontend rendering for all question types, and full Q&A persistence. Bug closed after end-to-end confirmation.
- **OTP input auto-fill & correction UX** — OTP inputs now support paste/OS auto-fill, digit overwrite, single-backspace with left move, arrow navigation, trimming to 4 digits, and focus management; magic-link success jumps straight to palm photo to avoid step flicker; OTP email template redesigned (HTML) with emphasized code and button link.

## Resolved Bugs

### Current Session (2025-12-12) - OTP Backend Fix & AI Prompt Enhancement
- **OTP verification failing (400 Bad Request)** — After changing OTP from 6 to 4 digits, backend validation still checked for 6-digit codes. Fixed: Updated `validate_otp_verify_request()` in `class-sm-rest-controller.php` from `strlen($otp) !== 6` to `strlen($otp) !== 4`, and `sanitize_code()` in `class-sm-otp-handler.php` from `substr($code, 0, 6)` to `substr($code, 0, 4)`. OTP verification now works correctly.
- **AI reading too short & not personalized** — Readings were 600-800 words with generic content. Updated prompts to: (1) Increase word count to 750-1000 words for more depth, (2) Remove ALL `<ul>` and `<li>` tags to use only `<h4>` sections for consistent styling, (3) Add CRITICAL instruction to weave in user's Energy, Focus, Element, Intentions, and Goals throughout reading for deep personalization. Each section (Hand Shape, Fingers, Heart Line, Head Line, Life Line, Mounts & Markings, Hidden Channels, Path Forward) now properly styled as h4 headers with accent color.

### Previous Session (2025-12-12) - UX Enhancement & Bug Fixes
- **Font brightness too low** — After darkening background, text appeared too dark. Increased `--color-text-secondary` from #d1d5db to #f3f4f6 and `--color-text-muted` from #9ca3af to #d1d5db for better readability.
- **Reading generation loading messages too short** — Only 3 messages rotated during 8-12 second AI generation. Expanded to 8 messages (2 seconds each) to keep user engaged: added "Decoding the wisdom of your fate line", "Interpreting the mounts and valleys", "Channeling insights", "Weaving past/present/future", "Finalizing reading".
- **OTP changed from 6 digits to 4 digits** — Changed OTP generation from 6-digit to 4-digit codes for better UX (less screen cutoff). Updated: backend OTP generation (SM_OTP_Handler), frontend validation (script.js codeLength AND validateVerificationCode(), api-integration.js validation), and user-facing text ("4-digit verification code").
- **OTP button disabled after entering 4 digits** — After changing to 4-digit OTP, the continue button remained disabled because validateVerificationCode() still checked for 6 digits. Fixed: changed `code.length !== 6` to `code.length !== 4` in script.js line 657.
- **Email send timing issue** — OTP email was sent AFTER 5-second loading animation. Changed to send email IMMEDIATELY after lead creation, then show shorter 2-second loading animation before OTP entry screen. User gets email faster and perceives better responsiveness.
- **Back button breaks forward navigation** — Clicking Continue → Back → Continue again from welcome page didn't work. Added goToPreviousStep override in api-integration.js to reset apiState and userData when returning to welcome page (step 0), allowing fresh restart.

### Previous Session (2025-12-12) - UI/UX Polish to 100% Perfection
- **Background too light, text hard to read** — Main background colors were too light (#0a0a14). Darkened to #050509 (main), #020204 (dark), #0f0f1a (light) for better contrast and readability.
- **Result section headings too close together** — Sections (Overview, Insights, etc.) had minimal spacing. Added `margin-top: spacing-lg` (2.5rem) to h4 headings and changed color to `--color-accent` for better prominence and breathing room.
- **Buttons too large and visually heavy** — Navigation and CTA buttons had excessive padding/size. Reduced padding to `spacing-sm/md`, font-size to 0.95rem, and min-width to 110px/180px for subtler, more elegant appearance.
- **Desktop spacing too loose** — Progress bar, title, and buttons had excessive gaps. Tightened: progress bar `margin-bottom: spacing-xs`, title `margin-top: 0`, navigation `margin-top: spacing-md`. Everything now properly close together.
- **Desktop layout too narrow, wasted screen space** — Content was 800px max-width with empty sides. Expanded to 1000px (+25%) for app-content, progress, navigation; 900px for results. Desktop now uses screen real estate effectively.
- **Nested bullet points in AI results** — OpenAI sometimes generated nested `<ul>` inside `<li>` tags. Added CSS rule `display: none` for nested lists and updated AI prompts (main, system, fallback) with instruction: "Use ONLY single-level lists; DO NOT nest `<ul>` tags inside `<li>` elements."
- **Desktop continue button too far down, required scrolling** — Content was vertically centered (`justify-content: center`) causing button to be off-screen. Changed to `justify-content: flex-start` on desktop, set `min-height: auto` for app-container. Content now starts at top with button immediately visible—no scrolling needed.
- **Background animations too strong** — Aura opacity was overpowering at 0.5. Reduced to 0.35 and pulse animation to 0.2-0.35 range for subtle, elegant effect.

### Previous Sessions
- **Icons not showing (quiz + background)** — Font Awesome library was not being loaded. Added Font Awesome 6.4.0 CDN to asset enqueue in `mystic-palm-reading.php`. All quiz question icons and background animation icons now display correctly.
- **Result text too narrow on mobile** — Result container had excessive margins/padding on mobile, making text hard to read. Updated `.result-container` to use 100% width with minimal padding (--spacing-xs) on mobile. Text now uses full available screen width.
- **Result text too light/hard to read** — Text was using `--color-text-secondary` (#d1d5db - light gray) with no font-weight. Changed to `--color-text` (pure white) with `font-weight: 500` for better readability. Headings now use `--color-accent-light` for better visual hierarchy.
- **Background animations not visible** — Aura circles had opacity 0.3 (too subtle) and pulse animation ranged 0.15-0.25. Increased base aura opacity to 0.5 and pulse animation to range 0.3-0.5 for much better visibility.
- **Result layout polish** — Adjusted result text alignment, spacing, mobile sizing, and removed hardcoded insights so AI HTML renders cleanly.
- **Background animations visibility** — Increased aura opacity/positioning to surface animations.
- **OpenAI API key invalid** — OpenAI returned 401 (“Incorrect API key provided”), blocking `/reading/generate`. Updated to use the new key in settings and the flow now completes.
- **Short/refusal AI responses** — OpenAI occasionally replied with refusals like “I can’t assist with that.” Added system/user prompt guards and server-side validation to reject short/refusal outputs before saving.
- **MailerLite sync failing** — Sync failed with the new key. Added `Accept` header and detailed request/response logging; confirmed sync now succeeds with the updated key.
- **Reading generation 500** — REST call to `/reading/generate` crashed with a WordPress critical error because `SM_AI_Handler::call_openai_api()` invoked nonexistent `SM_Settings::get_option`; switched to `SM_Settings::init()->get_openai_api_key()` to retrieve the OpenAI key without fatal errors.
- **Cookie header warnings** — Addressed cookie header errors that occurred during earlier auth flows (documented fix now cleaned up).
- **Nonce header mismatch** — Resolved nonce header handling issues that blocked requests; legacy doc removed after fix.

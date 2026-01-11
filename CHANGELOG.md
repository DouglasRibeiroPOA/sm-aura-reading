# Changelog

All notable changes to the SoulMirror Aura Reading plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial plugin foundation cloned from Palm Reading v1.4.5
- Aura Reading brand identity with ethereal color palette
- 6-category questionnaire structure covering:
  - Emotional State & Inner Climate
  - Energy Level & Flow
  - Love, Relationships & Emotional Connection
  - Life Direction, Success & Material Flow
  - Spiritual Memory & Deeper Patterns
  - Intentions, Healing & Growth
- Upper body photo upload for aura energy analysis
- AI-powered aura reading generation using OpenAI GPT-4o Vision
- Independent database schema with `wp_sm_aura_*` tables
- Teaser reading with unlock mechanism (2 free unlocks)
- Full reading purchase flow with Account Service integration
- HTML report download functionality
- MailerLite integration for subscriber management
- Custom CSS with soft gradients, glow effects, and breathing animations
- Responsive design for mobile and desktop
- Development mode support for API testing
- Aura test images and placeholder icon assets for frontend previews
- Aura-specific prompt templates and question bank for the six category flow

### Changed
- Rebranded all UI elements from Palm Reading to Aura Reading
- Updated color scheme to light, ethereal theme (soft purples, teals, blues)
- Modified image input requirements (shoulders up vs. palm close-up)
- Adapted AI prompts for aura/energy analysis instead of palm line reading
- Replaced mystical dark aesthetic with lighter, spiritual aesthetic
- Refined swipe template styling to use aura gradients and palette
- Updated onboarding copy and loading messaging for aura-focused flow
- Updated API integration copy, redirects, and aura report container handling
- Refreshed teaser modal labels and unlock copy to match aura categories
- Replaced palm test assets with aura-themed placeholder imagery
- Updated container template copy and identifiers for aura flow
- Updated dashboard template labels and credit messaging for aura readings
- Updated reports listing template copy and links for aura branding
- Renamed reading templates to aura variants and refreshed content to aura categories
- Replaced palm-oriented question bank with aura category questions and ratings
- Updated account integration defaults and auth callback URLs for aura service
- Updated Playwright tests and fixtures for aura flows and selectors
- Updated README with aura setup guidance and screenshot notes
- Rewrote CODEX.md and CONTEXT.md for aura-specific workflow and architecture
- Updated package.json metadata for aura Playwright tests
- Added .gitignore with WordPress plugin and Playwright artifact exclusions
- Added troubleshooting log locations to CODEX.md
- Strengthened aura color palette and gradient variables
- Updated welcome icon to aura compass
- Updated aura prompts and summary labels to accept upper body photos and reduce palm wording
- Reduced dynamic quiz to 4 questions and removed the extra free-text prompt
- Ensured only the final question is free-text in fallback and dynamic quiz flows
- Lightened dashboard and reports listing card styling for better contrast on the aura theme

### Fixed
- Resolved activation fatal by switching to SM_AURA_VERSION and aura-specific FK constraint names
- Fixed settings registration fatal by registering the sm_aura_settings option key
- Fixed admin settings persistence for account service URL updates
- Fixed login button hover contrast so text stays visible
- Improved form and OTP input contrast on the light aura background
- Allowed legacy settings group to avoid options page save errors
- Improved quiz option and rating control visibility
- Fixed missing reading_type column by updating DB schema version and base table definitions
- Fixed AI handler to use aura tables/reading types for lead and reading queries
- Added schema integrity checks to apply missing migrations on existing installs
- Updated palm error messaging to aura-friendly copy
- Updated vision failure email copy to aura photo language
- Added OpenAI vision logging and relaxed image validation strictness
- Switched aura uploads to `sm-aura-private` with fallback for existing palm uploads
- Added key status indicators in settings and plaintext fallback when encryption is unavailable
- Normalized API key inputs, added masked key diagnostics, and ensured plaintext fallback without OpenSSL
- Prevented vision resubmit lockouts and forced aura signal defaults to avoid empty outputs
- Improved back button visibility on light backgrounds
- Fixed dashboard share button to use native share/clipboard fallbacks
- Fixed report titles to default to Aura Reading and drop time suffix
- Improved reports table header and hover contrast for readability
- Fixed Aura flow navigation so Back/Continue respond on the first quiz question
- Restored teaser report refresh + email access using a teaser access token
- Isolated guest and paid flow state by scoping session storage keys and flow session cookies
- Fixed smStorage redeclaration that blocked script loading and app initialization
- Restored in-progress flow refresh via sm_flow URL flag and scoped localStorage state restore
- Allowed teaser report refresh and magic token access without paid login redirect
- Prevented step enforcement from snapping users back after refresh during quiz navigation
- Persisted dynamic quiz questions and demographics across refresh to keep question text consistent
- Persisted uploaded aura photo state across refresh to avoid missing-photo errors at report generation
- Restored logged-in flow on refresh when sm_flow is present but step state is missing
- Added sm_flow_auth URL flag to preserve auth context across refresh in paid flow
- Ensured paid flow persists lead_id for quiz save after refresh
- Reset paid flow state on "Begin Journey" to avoid stale selections and dashboard bounce
- Updated teaser modal secondary button text to "Close"
- Added start-new pending redirect guard to keep paid flow from bouncing to dashboard
- Reset OTP state when starting a new lead capture to ensure codes send and verification is enforced
- Fixed duplicate URL params declaration that blocked teaser flow scripts
- Wired OTP resend to the backend and updated verification hint copy
- Reduced OTP resend cooldown to 30 seconds and improved rate-limit messaging
- Redirected logged-in users away from unauthorized teaser links to the dashboard
- Forced paid "Begin Journey" to reuse the current page URL with start_new flags to prevent dashboard bounce
- Fixed start-new pending flag reset so paid flow redirect guard triggers on first click

### Technical
- Plugin namespace: `mystic-aura-reading`
- Shortcode: `[soulmirror_aura_reading]`
- Database tables: `wp_sm_aura_*`
- Text domain: `mystic-aura-reading`
- Reading types: `aura_teaser`, `aura_full`
- Base version: 1.0.0
- Compatible with WordPress 6.0+
- PHP 8.0+ required

---

## Development Guidelines

### Updating This Changelog

**This file should be updated frequently as development progresses.** Specifically:

1. **After completing any task** in the AURA_READING_REQUIREMENTS.md checklist, update this changelog
2. **Add entries under the `[Unreleased]` section** using the appropriate category:
   - **Added** for new features
   - **Changed** for changes in existing functionality
   - **Deprecated** for soon-to-be removed features
   - **Removed** for now removed features
   - **Fixed** for any bug fixes
   - **Security** for vulnerability fixes
3. **Be specific and descriptive** - each entry should clearly communicate what changed
4. **Update often**, not just at major milestones

### Release Process

When releasing a new version:

1. Move all `[Unreleased]` items to a new version section: `## [X.Y.Z] - YYYY-MM-DD`
2. Create a new empty `[Unreleased]` section
3. Update the version number in:
   - `mystic-aura-reading.php` (plugin header)
   - `AURA_READING_REQUIREMENTS.md` (version field)
   - Any other version references

### Version Numbering

Follow [Semantic Versioning](https://semver.org/):
- **MAJOR** (X.0.0): Breaking changes, incompatible API changes
- **MINOR** (0.X.0): New features, backwards-compatible
- **PATCH** (0.0.X): Bug fixes, backwards-compatible

---

## [1.0.0] - TBD

Initial release (in development)

---

**Note:** This project is based on the SoulMirror Palm Reading plugin v1.4.5 and maintains the same technical architecture while providing a completely independent aura reading experience.

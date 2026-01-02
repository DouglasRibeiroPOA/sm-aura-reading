# Repository Guidelines

## Project Structure & Module Organization

This repository is the WordPress plugin **SoulMirror Aura Reading**. The main plugin file is expected at `mystic-aura-reading.php`, with core PHP classes under `includes/`, frontend assets under `assets/`, templates in `templates/`, and end-to-end tests in `tests/` (Playwright). User uploads are stored in a non-public `uploads/` directory. See `CLAUDE.md` for the canonical architecture map and naming.

Example layout:

```
sm-aura-reading/
├── mystic-aura-reading.php
├── includes/
├── assets/
├── templates/
└── tests/
```

## Build, Test, and Development Commands

There are no build scripts in this repository. Run the plugin inside a local WordPress site and use dev mode flags in `wp-config.php` when needed:

```php
define('SM_DEV_MODE', 'dev_all');
```

If Playwright tests are present, run them from the plugin root:
`npx playwright test tests/`

## Coding Style & Naming Conventions

- Language: PHP 8.0+, vanilla JS, and CSS.
- Indentation: 4 spaces for PHP, 2 spaces for JS/CSS where applicable.
- Class prefix: keep the `SM_` namespace.
- Database tables: `wp_sm_aura_*`.
- Reading types: `aura_teaser`, `aura_full`.
- Text domain: `mystic-aura-reading`.

## Testing Guidelines

Playwright is the intended E2E framework. Name specs with `.spec.js` (example: `tests/e2e-full-flow.spec.js`). Validate the full user flow: OTP, quiz, upload, teaser unlocks, paywall, and full reading.

## Commit & Pull Request Guidelines

No explicit commit convention is documented. Use short, imperative messages (e.g., `Add aura prompt templates`). PRs should include:
- A concise summary of changes
- Linked issue or requirement (if applicable)
- Screenshots for UI changes
- Notes on manual or automated testing performed

## Agent-Specific Instructions

Follow the workflow in `AURA_READING_REQUIREMENTS.md` and update its progress tracker immediately after each task. Update `CHANGELOG.md` for meaningful changes. Avoid modifying the Palm Reading plugin; use it only as a reference.

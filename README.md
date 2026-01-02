# SoulMirror Aura Reading

WordPress plugin for the SoulMirror Aura Reading experience. This plugin captures leads, verifies email, collects a short quiz, analyzes a shoulders-up photo, and generates aura readings (teaser + full) with unlocks and paid upgrades.

## Quick Start

- **Shortcode:** `[soulmirror_aura_reading]`
- **Main plugin file:** `mystic-aura-reading.php`
- **Templates:** `aura-reading-template-*.html`
- **Dev mode (optional):**
  ```php
  define('SM_DEV_MODE', 'dev_all');
  ```

## Setup

1. Copy this plugin into `wp-content/plugins/sm-aura-reading/`.
2. Activate **SoulMirror Aura Reading** in the WordPress admin.
3. Add the shortcode to a page: `[soulmirror_aura_reading]`.
4. Configure API keys in the plugin settings (OpenAI, MailerLite, Account Service).
5. Enable dev mode in `wp-config.php` when needed:
   ```php
   define('SM_DEV_MODE', 'dev_all');
   ```

## Project Layout

```
sm-aura-reading/
├── mystic-aura-reading.php
├── includes/
├── assets/
├── templates/
└── tests/
```

## Key Notes

- **Reading types:** `aura_teaser`, `aura_full`
- **Service slug:** `aura_reading`
- **Text domain:** `mystic-aura-reading`
- **Image input:** shoulders-up photo, good light, max 5MB

## Screenshots

Screenshots are not checked into the repo yet. Capture them from the live or local WordPress page once the UI is finalized.

## Tests

If Playwright tests are present:
```
npx playwright test tests/
```

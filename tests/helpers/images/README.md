# Test Images

This directory contains palm and non-palm images used for E2E testing of the Mystic Palm Reading plugin.

## Required Images

### Valid Palm Images (5 required)

Place **5 valid palm images** in `tests/helpers/images/valid/` with the following names:

- `valid-palm-1.png`
- `valid-palm-2.png`
- `valid-palm-3.png`
- `valid-palm-4.png`
- `valid-palm-5.png`

**Requirements:**
- Format: JPEG or PNG
- Size: < 10MB each
- Content: Clear, well-lit photos of open palms (left or right hand)
- Quality: High resolution preferred for best AI analysis

**Purpose:** These images are used to test successful palm reading generation flows.

### Invalid Images (3 required)

Place **3 invalid (non-palm) images** in `tests/helpers/images/invalid/` with the following names:

- `invalid-1.png` - Non-palm image (type TBD)
- `invalid-2.png` - Non-palm image (type TBD)
- `invalid-3.png` - Non-palm image (type TBD)

**Requirements:**
- Format: JPEG or PNG
- Size: < 10MB each
- Content: Should NOT be palm images (used to test OpenAI Vision failure scenarios)

**Purpose:** These images are used to test:
- OpenAI Vision API rejection handling
- Error message display
- Retry flow after invalid image upload

## Image Sources

**USER TO FILL IN:**

Document where the images came from:

### Valid Palm Images
- Source: _______________ (e.g., user-provided, stock photography, AI-generated)
- License: _______________ (if applicable)
- Notes: _______________

### Invalid Images
- Source: _______________
- License: _______________ (if applicable)
- Notes: _______________

## Usage in Tests

These images are referenced in the following test suites:

- `tests/01-critical-happy-paths.spec.js` - Uses valid palm images
- `tests/07-image-validation.spec.js` - Uses both valid and invalid images
- All other test suites that require palm photo upload

## Directory Structure

```
tests/helpers/images/
├── README.md
├── valid/
│   ├── valid-palm-1.png
│   ├── valid-palm-2.png
│   ├── valid-palm-3.png
│   ├── valid-palm-4.png
│   └── valid-palm-5.png
└── invalid/
    ├── invalid-1.png
    ├── invalid-2.png
    └── invalid-3.png
```

## Adding Images

**Option 1: Manual Addition**
1. Collect 8 images (5 valid palms + 3 invalid)
2. Rename them according to the naming convention above
3. Place them in this directory
4. Update the "Image Sources" section in this README

**Option 2: Use Stock Photos**
- Valid palms: Search stock sites for "open palm", "hand palm reading"
- Invalid images: Any non-palm photos work (faces, landscapes, objects)

**Option 3: Generate with AI**
- Use AI image generation tools to create palm images
- Note: Ensure images are realistic for accurate testing

## Testing Image Quality

To verify images work correctly:

```bash
# Run image validation test suite
npx playwright test tests/07-image-validation.spec.js --headed
```

## Notes

- Images are NOT committed to git (should be in .gitignore)
- Each test run may use different images from this pool
- Images should represent diverse palm types for comprehensive testing
- Invalid images should be clearly non-palm to trigger OpenAI Vision rejection

---

**Status:** ⚠️ WAITING FOR USER INPUT - 8 images needed to complete Phase 0 setup

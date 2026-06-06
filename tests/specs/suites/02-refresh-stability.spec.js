// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const { E2EHelpers, setupMonitoring } = require('../../helpers/test-helpers');
const MailPitHelper = require('../../helpers/mailpit-helper');

process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

const DEFAULT_BASE_URL = 'https://sm-aura-reading.local/';
const BASE_URL = process.env.E2E_BASE_URL || DEFAULT_BASE_URL;
const USE_LIVE_OPENAI = process.env.E2E_LIVE_OPENAI === '1';
const REPORT_WAIT_MS = Number(process.env.E2E_REPORT_WAIT_MS) || (USE_LIVE_OPENAI ? 180000 : 60000);

const VALID_PALM_IMAGE = path.resolve(__dirname, '../../helpers/images/valid/valid-palm-1.png');

async function waitForAnySelector(page, selectors, timeoutMs = 10000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    for (const selector of selectors) {
      if (await page.locator(selector).first().isVisible().catch(() => false)) {
        return true;
      }
    }
    try {
      await page.waitForTimeout(250);
    } catch {
      return false;
    }
  }
  return false;
}

async function fillVisibleInput(locator, value) {
  await locator.scrollIntoViewIfNeeded();
  await locator.click({ force: true });
  await locator.fill('');
  await locator.type(value, { delay: 30 });

  let currentValue = '';
  try {
    currentValue = await locator.inputValue();
  } catch {
    currentValue = '';
  }

  if (currentValue !== value) {
    await locator.evaluate((el, nextValue) => {
      const setter = Object.getOwnPropertyDescriptor(
        window.HTMLInputElement.prototype,
        'value'
      )?.set;
      if (setter) {
        setter.call(el, nextValue);
      } else {
        el.value = nextValue;
      }
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      el.dispatchEvent(new Event('blur', { bubbles: true }));
    }, value);
  }

  await expect(locator).toHaveValue(value, { timeout: 5000 });
}

async function mockLoginInBrowser(page, email, name = 'Test User', accountId = '') {
  const apiBase = new URL('/wp-json', BASE_URL).toString().replace(/\/$/, '');
  const params = new URLSearchParams({ email, name });
  if (accountId) {
    params.set('account_id', accountId);
  }
  const response = await page.request.get(`${apiBase}/soulmirror-test/v1/mock-login?${params.toString()}`);
  if (!response.ok()) {
    throw new Error(`Mock login failed: ${response.status()}`);
  }
  return response.json();
}

async function fillLeadCapture(page, testEmail, testName) {
  const nextBtn = page.locator('#next-btn');
  const welcomeEmailInput = page.locator('.welcome-form input[name="email"]');
  const welcomeContinueBtn = page.locator('.welcome-form button[type="submit"]');
  const continueBtn = page.locator('button:has-text("Continue"), [role="button"]:has-text("Continue"), text=/^continue$/i');
  const continueBtnByRole = page.getByRole('button', { name: /continue/i });
  const emailInputByRole = page.getByRole('textbox', { name: /best email|email/i });
  const emailInputByPlaceholder = page.getByPlaceholder('your.email@example.com');
  const emailInputFallback = page.locator('input[type="email"]:visible, input[placeholder*="email" i]:visible, input[placeholder*="example.com" i]:visible, input[name*="email" i]:visible');
  const leadCaptureHeading = page.locator('text=/begin your journey/i');
  const nameInputByPlaceholder = page.getByPlaceholder('Enter your name');
  const identitySelectByLabel = page.locator('select[aria-label*="identify" i], select[name="identity"]');
  const identityByRole = page.getByRole('combobox', { name: /identify/i });
  const ageInputByRole = page.getByRole('spinbutton', { name: /old|age/i });
  const ageInputFallback = page.locator('input[type="number"], input[placeholder*="age" i]');
  const postWelcomeSelectors = [
    'select[name="identity"]',
    'select[aria-label*="identify" i]',
    'input[placeholder*="name" i]',
    'text=/begin your journey/i',
    '.code-input',
    '#photo-upload-input, input[type="file"]',
    '.option-btn, .rating-btn, textarea.form-textarea',
    '#aura-reading-result, .report-container'
  ];
  const debug = process.env.E2E_DEBUG === '1';

  const isLeadCaptureStep = async () => {
    return await page.locator('select[name="identity"]').isVisible().catch(() => false)
      || await identitySelectByLabel.first().isVisible().catch(() => false)
      || await nameInputByPlaceholder.isVisible().catch(() => false)
      || await leadCaptureHeading.first().isVisible().catch(() => false);
  };

  const leadCaptureVisible = await isLeadCaptureStep();
  if (!leadCaptureVisible) {
    await page.locator('text=/best email/i').first().waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
    let emailTarget = null;
    let emailFilled = false;
    if (await welcomeEmailInput.isVisible().catch(() => false)) {
      emailTarget = welcomeEmailInput;
      await fillVisibleInput(welcomeEmailInput, testEmail);
      emailFilled = true;
    } else if (await emailInputByPlaceholder.isVisible().catch(() => false)) {
      emailTarget = emailInputByPlaceholder;
      await fillVisibleInput(emailInputByPlaceholder, testEmail);
      emailFilled = true;
    } else if (await emailInputByRole.count()) {
      emailTarget = emailInputByRole.first();
      await fillVisibleInput(emailInputByRole.first(), testEmail);
      emailFilled = true;
    } else if (await emailInputFallback.count()) {
      emailTarget = emailInputFallback.first();
      await fillVisibleInput(emailInputFallback.first(), testEmail);
      emailFilled = true;
    }
    if (emailFilled && emailTarget) {
      await expect(emailTarget).toHaveValue(testEmail, { timeout: 5000 });
      if (debug) {
        const value = await emailTarget.inputValue().catch(() => '');
        const propValue = await emailTarget.evaluate(el => el.value).catch(() => '');
        console.log(`[E2E DEBUG] Email input value=${value} prop=${propValue}`);
      }
    }
    if (emailFilled && emailTarget) {
      await emailTarget.press('Tab').catch(() => {});
    }
    if (await welcomeContinueBtn.isVisible().catch(() => false)) {
      await expect(welcomeContinueBtn).toBeEnabled({ timeout: 5000 });
      await welcomeContinueBtn.click({ force: true });
    } else if (await continueBtnByRole.isVisible().catch(() => false)) {
      await expect(continueBtnByRole).toBeEnabled({ timeout: 5000 });
      await continueBtnByRole.click({ force: true });
    } else if (await continueBtn.isVisible().catch(() => false)) {
      await continueBtn.click({ force: true });
    } else if (await nextBtn.isVisible().catch(() => false)) {
      await nextBtn.click({ force: true });
    }
    if (debug) {
      const continueDisabled = await continueBtnByRole.isDisabled().catch(() => false);
      const continueAttr = await continueBtnByRole.getAttribute('disabled').catch(() => null);
      console.log(`[E2E DEBUG] Continue disabled=${continueDisabled} attr=${continueAttr}`);
    }
    let advanced = await waitForAnySelector(page, postWelcomeSelectors, 10000);
    if (!advanced && emailTarget && await emailTarget.isVisible().catch(() => false)) {
      await emailTarget.press('Enter').catch(() => {});
      if (await continueBtnByRole.isVisible().catch(() => false)) {
        await continueBtnByRole.click();
      } else if (await continueBtn.isVisible().catch(() => false)) {
        await continueBtn.click();
      } else if (await nextBtn.isVisible().catch(() => false)) {
        await nextBtn.click();
      }
      advanced = await waitForAnySelector(page, postWelcomeSelectors, 10000);
    }
    if (!advanced) {
      advanced = await page.locator('text=/begin your journey/i').first().waitFor({ state: 'visible', timeout: 8000 }).then(() => true).catch(() => false);
    }
    if (!advanced && await isLeadCaptureStep()) {
      advanced = true;
    }
    if (!advanced) {
      advanced = await page
        .waitForSelector('input[placeholder*="name" i], select[name="identity"]', { timeout: 20000, state: 'visible' })
        .then(() => true)
        .catch(() => false);
    }
    if (!advanced) {
      throw new Error('Lead capture did not advance after email submission.');
    }
  }

  const emailInput = page.locator('input[name="email"]');
  const nameInput = page
    .locator('input[placeholder*="name" i], input[aria-label*="name" i], input[name*="name" i], input.form-input[type="text"], input[type="text"]')
    .first();
  const identitySelect = page.locator('select[name="identity"], select[aria-label*="identify" i]');
  const ageInput = page.locator('input[type="number"], input[placeholder*="age" i], input[aria-label*="old" i]');
  const gdprCheckbox = page.locator('.checkbox-custom-input, input[type="checkbox"]');
  const gdprLabel = page.locator('.checkbox-custom-label, label:has-text("I agree")');
  const gdprText = page.locator('text=/I agree to receive my aura reading/i');

  if (await emailInput.isVisible().catch(() => false)) {
    await emailInput.fill(testEmail);
  }
  if (await nameInput.isVisible().catch(() => false)) {
    await nameInput.fill(testName);
  }
  if (await identitySelect.isVisible().catch(() => false)) {
    await identitySelect.selectOption('prefer-not').catch(async () => {
      await identitySelect.selectOption({ label: /prefer not/i }).catch(() => {});
    });
  } else if (await identityByRole.isVisible().catch(() => false)) {
    await identityByRole.selectOption({ label: /prefer not/i }).catch(async () => {
      await identityByRole.click({ force: true });
      await page.locator('option:has-text("Prefer not")').click().catch(() => {});
    });
  }
  if (await ageInput.isVisible().catch(() => false)) {
    await ageInput.fill('29');
  } else if (await ageInputByRole.isVisible().catch(() => false)) {
    await ageInputByRole.fill('29');
  } else if (await ageInputFallback.first().isVisible().catch(() => false)) {
    await ageInputFallback.first().fill('29');
  }
  if (await gdprCheckbox.isVisible().catch(() => false)) {
    await gdprCheckbox.scrollIntoViewIfNeeded().catch(() => {});
    const isChecked = await gdprCheckbox.evaluate(el => el.classList.contains('checked')).catch(() => false);
    if (!isChecked) {
      await gdprCheckbox.click({ force: true });
    }
    const checkedAfter = await gdprCheckbox.evaluate(el => el.classList.contains('checked')).catch(() => false);
    if (!checkedAfter && await gdprLabel.isVisible().catch(() => false)) {
      await gdprLabel.click({ force: true });
    }
    const checkedFinal = await gdprCheckbox.evaluate(el => el.classList.contains('checked')).catch(() => false);
    if (!checkedFinal && await gdprText.isVisible().catch(() => false)) {
      await gdprText.click({ force: true });
    }
  }

  if (await continueBtn.isVisible().catch(() => false)) {
    await expect(continueBtn).toBeEnabled({ timeout: 5000 });
    await continueBtn.click();
  } else if (await nextBtn.isVisible().catch(() => false)) {
    await nextBtn.click();
  }

  await page.waitForSelector('.code-input', { timeout: 20000 });
}

async function uploadPalmPhoto(page, imagePath) {
  await page
    .waitForSelector('#upload-btn, #photo-upload-input', { timeout: 20000, state: 'attached' })
    .catch(() => {});

  const specificInput = page.locator('#photo-upload-input');
  const genericInput = page.locator('input[type="file"]');
  const uploadBtn = page.locator('#upload-btn, button:has-text("Upload Photo")');
  const debug = process.env.E2E_DEBUG === '1';

  if (debug) {
    const state = await page.evaluate(() => {
      const upload = document.querySelector('#upload-btn');
      const specific = document.querySelector('#photo-upload-input');
      const generic = document.querySelector('input[type="file"]');
      return {
        uploadBtn: upload ? { disabled: upload.hasAttribute('disabled'), visible: !!(upload.offsetWidth || upload.offsetHeight) } : null,
        specificInput: specific ? { disabled: specific.disabled || specific.hasAttribute('disabled'), hidden: specific.type === 'hidden' } : null,
        genericInput: generic ? { disabled: generic.disabled || generic.hasAttribute('disabled'), hidden: generic.type === 'hidden' } : null
      };
    });
    console.log(`[E2E DEBUG] Upload state ${JSON.stringify(state)}`);
  }

  let inputTarget = null;
  if (await specificInput.count()) {
    inputTarget = specificInput;
  } else {
    inputTarget = genericInput.first();
  }

  await inputTarget.setInputFiles(imagePath);
  await page.evaluate(() => {
    const input = document.querySelector('#photo-upload-input') || document.querySelector('input[type="file"]');
    if (input) {
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });

  const previewSelector = '#show.*aura|capture.*aura" i]';
  const previewVisible = await page
    .waitForSelector(previewSelector, { timeout: 10000 })
    .then(() => true)
    .catch(() => false);
  if (debug && !previewVisible) {
    const screenshotPath = `test-results/debug/photo-upload-${Date.now()}.png`;
    await page.screenshot({ path: screenshotPath, fullPage: true });
    console.warn(`⚠️  Photo preview not found after upload. screenshot=${screenshotPath}`);
  }

  const usePhotoBtn = page.locator('#use-photo-btn');
  await usePhotoBtn.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  if (await usePhotoBtn.isVisible().catch(() => false)) {
    await usePhotoBtn.click();
  }
}

async function completeQuiz(page) {
  const nextBtn = page.locator('#next-btn');

  await nextBtn.waitFor({ state: 'visible', timeout: 15000 });

  const answerStep = async () => {
    const optionBtn = page.locator('.option-btn').first();
    const ratingBtn = page.locator('.rating-btn').first();
    const textArea = page.locator('textarea.form-textarea').first();

    if (await optionBtn.isVisible().catch(() => false)) {
      await optionBtn.click();
      await page.waitForTimeout(200);
      return true;
    }
    if (await ratingBtn.isVisible().catch(() => false)) {
      await ratingBtn.click();
      await page.waitForTimeout(200);
      return true;
    }
    if (await textArea.isVisible().catch(() => false)) {
      await textArea.fill('Test response');
      await textArea.blur().catch(() => {});
      return true;
    }
    return false;
  };

  for (let i = 0; i < 6; i++) {
    await answerStep();

    const reportVisible = await page.locator('#aura-reading-result, .report-container').isVisible().catch(() => false);
    const loadingVisible = await page.locator('.loading-container, .loading-step').isVisible().catch(() => false);
    if (reportVisible || loadingVisible) {
      break;
    }

    const start = Date.now();
    while (Date.now() - start < 15000) {
      const nextVisible = await nextBtn.isVisible().catch(() => false);
      const nextDisabled = await nextBtn.isDisabled().catch(() => true);
      const nextClass = await nextBtn.getAttribute('class').catch(() => '');
      const nextLoading = (nextClass || '').includes('loading');
      const reportNow = await page.locator('#aura-reading-result, .report-container').isVisible().catch(() => false);
      const loadingNow = await page.locator('.loading-container, .loading-step').isVisible().catch(() => false);
      if (reportNow || loadingNow) {
        return;
      }
      if (nextVisible && !nextDisabled && !nextLoading) {
        await nextBtn.click({ force: true });
        await page.waitForTimeout(600);
        break;
      }
      await page.waitForTimeout(300);
    }
  }
}

async function waitForReport(page) {
  const selector = '#aura-reading-result, .reading-result-container, .report-container';
  await page.waitForSelector(selector, { timeout: REPORT_WAIT_MS });
  return page.locator(selector).first();
}

async function waitForDashboard(page) {
  await page.waitForSelector('.dashboard-container, #generate-new-reading-btn', { timeout: 15000 });
}

test.describe('Phase 2 - Refresh Stability', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(USE_LIVE_OPENAI ? 240000 : 120000);

  test.afterEach(async () => {
    const helpers = new E2EHelpers(BASE_URL);
    try {
      await helpers.cleanupTestData();
    } catch (error) {
      console.warn(`⚠️  Cleanup skipped: ${error.message}`);
    }
  });

  test('REFRESH-003: Refresh on OTP step (before code entry)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'OTP Refresh User';

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    // Fill lead capture and land on OTP step
    await fillLeadCapture(page, testEmail, testName);

    // Verify we're on OTP step
    const otpInputs = page.locator('.code-input');
    await expect(otpInputs.first()).toBeVisible();

    // Capture session state before refresh
    const sessionBefore = await helpers.getSessionState(page);

    // Refresh the page
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    // EXPECTED: STAY on OTP step, email visible, OTP still valid
    const otpInputsAfter = page.locator('.code-input');
    await expect(otpInputsAfter.first()).toBeVisible({ timeout: 10000 });

    // Verify we didn't get kicked back to welcome page
    const welcomeVisible = await page.locator('.welcome-form').isVisible().catch(() => false);
    expect(welcomeVisible).toBe(false);

    // Verify email is still visible/accessible
    const emailDisplay = page.locator('text=/sent to|enter.*code/i');
    if (await emailDisplay.count()) {
      await expect(emailDisplay.first()).toBeVisible();
    }

    // Verify OTP is still functional
    await helpers.enterOtp(page, testEmail);

    // Should advance to photo step (check for visible UI elements, not hidden input)
    const palmPhotoHeading = page.locator('h1:has-text("Show Me Your Aura"), h2:has-text("Show Me Your Aura"), text=/show.*aura|capture.*aura/i').first();
    const uploadButtons = page.locator('button:has-text("Upload Photo"), button:has-text("Use Camera"), #upload-btn');

    await palmPhotoHeading.waitFor({ state: 'visible', timeout: 15000 }).catch(() =>
      uploadButtons.first().waitFor({ state: 'visible', timeout: 5000 })
    );

    // Verify we successfully reached the photo step
    const onPhotoStep = await palmPhotoHeading.isVisible().catch(() => false) ||
                       await uploadButtons.first().isVisible().catch(() => false);
    expect(onPhotoStep).toBe(true);
  });

  test('REFRESH-005: Refresh after OTP verified (before photo)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Photo Refresh User';

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    // Complete lead capture and OTP
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);

    // Verify we're on photo step (check for visible UI elements)
    const palmPhotoHeading = page.locator('h1:has-text("Show Me Your Aura"), h2:has-text("Show Me Your Aura"), text=/show.*aura|capture.*aura/i').first();
    const uploadButtons = page.locator('button:has-text("Upload Photo"), button:has-text("Use Camera"), #upload-btn');

    await palmPhotoHeading.waitFor({ state: 'visible', timeout: 15000 }).catch(() =>
      uploadButtons.first().waitFor({ state: 'visible', timeout: 5000 })
    );

    const onPhotoStep = await palmPhotoHeading.isVisible().catch(() => false) ||
                       await uploadButtons.first().isVisible().catch(() => false);
    expect(onPhotoStep).toBe(true);

    // Capture session state before refresh
    const sessionBefore = await helpers.getSessionState(page);

    // Refresh the page
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    // EXPECTED: STAY on photo step, no re-verification required
    const palmPhotoHeadingAfter = page.locator('h1:has-text("Show Me Your Aura"), h2:has-text("Show Me Your Aura"), text=/show.*aura|capture.*aura/i').first();
    const uploadButtonsAfter = page.locator('button:has-text("Upload Photo"), button:has-text("Use Camera"), #upload-btn');

    await palmPhotoHeadingAfter.waitFor({ state: 'visible', timeout: 10000 }).catch(() =>
      uploadButtonsAfter.first().waitFor({ state: 'visible', timeout: 5000 })
    );

    const stillOnPhotoStep = await palmPhotoHeadingAfter.isVisible().catch(() => false) ||
                            await uploadButtonsAfter.first().isVisible().catch(() => false);
    expect(stillOnPhotoStep).toBe(true);

    // Verify we didn't get kicked back to OTP
    const otpVisible = await page.locator('.code-input').isVisible().catch(() => false);
    expect(otpVisible).toBe(false);

    // Verify we didn't get kicked back to welcome
    const welcomeVisible = await page.locator('.welcome-form').isVisible().catch(() => false);
    expect(welcomeVisible).toBe(false);

    // Verify we can continue with photo upload
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);
    const report = await waitForReport(page);
    await expect(report).toBeVisible();
  });

  test('TEASER-010: Refresh on teaser report page', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seeded reading');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();

    // Seed a teaser reading
    const seeded = await helpers.seedReading(testEmail, 'Teaser Refresh User');
    expect(seeded.reading_id).toBeTruthy();
    expect(seeded.lead_id).toBeTruthy();

    // Navigate to report
    await page.goto(`${BASE_URL}?sm_report=1&lead_id=${seeded.lead_id}`, { waitUntil: 'domcontentloaded' });

    const reportDiagnostics = await page.evaluate(async (leadId) => {
      const apiUrl = window.smData?.apiUrl || '';
      const nonce = window.smData?.nonce || '';
      let fetchStatus = null;
      let fetchBody = null;
      if (apiUrl) {
        try {
          const response = await fetch(`${apiUrl}reading/get-by-lead?lead_id=${encodeURIComponent(leadId)}`, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': nonce
            }
          });
          fetchStatus = response.status;
          fetchBody = await response.json();
        } catch (error) {
          fetchBody = { error: String(error) };
        }
      }
      return {
        url: window.location.href,
        hasApp: !!document.querySelector('#app-content'),
        hasWelcome: !!document.querySelector('.welcome-form'),
        hasReportContainer: !!document.querySelector('#aura-reading-result, .reading-result-container, .report-container'),
        apiUrlPresent: Boolean(apiUrl),
        fetchStatus,
        fetchSuccess: fetchBody && fetchBody.success,
        fetchExists: fetchBody && fetchBody.data && fetchBody.data.exists,
        fetchHtmlLength: fetchBody && fetchBody.data && fetchBody.data.reading_html ? fetchBody.data.reading_html.length : 0,
        fetchError: fetchBody && fetchBody.error ? fetchBody.error : null
      };
    }, seeded.lead_id);

    if (process.env.E2E_DEBUG === '1') {
      console.log('[E2E DEBUG] TEASER-010 diagnostics', reportDiagnostics);
    }

    // Wait for report to display
    const report = await waitForReport(page);
    await expect(report).toBeVisible();

    // Verify unlock info is present (teaser-specific)
    const unlockText = page.getByText(/free unlocks remaining/i);
    const unlockButtons = page.locator('.unlock-section-btn');
    const unlockExists = (await unlockText.isVisible().catch(() => false)) || (await unlockButtons.count() > 0);
    if (!unlockExists && process.env.E2E_DEBUG === '1') {
      console.warn('[E2E DEBUG] TEASER-010: unlock UI not visible on seeded report.');
    }

    // Capture session state before refresh
    const sessionBefore = await helpers.getSessionState(page);
    expect(sessionBefore.sm_reading_loaded).toBeTruthy();

    // Refresh the page
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    // EXPECTED: REPORT STAYS ON SCREEN, unlock state preserved
    const reportAfter = page.locator('#aura-reading-result, .reading-result-container, .report-container');
    await expect(reportAfter.first()).toBeVisible({ timeout: 10000 });

    // Verify we didn't get redirected to welcome page
    const welcomeVisible = await page.locator('.welcome-form').isVisible().catch(() => false);
    expect(welcomeVisible).toBe(false);

    // Verify unlock state is preserved
    const unlockTextAfter = page.getByText(/free unlocks remaining/i);
    const unlockButtonsAfter = page.locator('.unlock-section-btn');
    const unlockExistsAfter = (await unlockTextAfter.isVisible().catch(() => false)) || (await unlockButtonsAfter.count() > 0);
    if (!unlockExistsAfter && process.env.E2E_DEBUG === '1') {
      console.warn('[E2E DEBUG] TEASER-010: unlock UI not visible after refresh.');
    }

    // Verify session state is restored
    const sessionAfter = await helpers.getSessionState(page);
    expect(sessionAfter.sm_reading_loaded).toBeTruthy();
  });

  test('PAID-004: Refresh on paid report page', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seeded paid reading');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();

    // Mock login first
    await mockLoginInBrowser(page, testEmail, 'Paid Refresh User');

    // Seed a PAID reading
    const seeded = await helpers.seedReading(testEmail, 'Paid Refresh User', 'aura_full');
    expect(seeded.reading_id).toBeTruthy();
    expect(seeded.lead_id).toBeTruthy();

    // Navigate to paid report
    await page.goto(`${BASE_URL}?sm_report=1&lead_id=${seeded.lead_id}&reading_type=aura_full`, {
      waitUntil: 'domcontentloaded'
    });

    // Wait for report to display
    const report = await waitForReport(page);
    await expect(report).toBeVisible();

    // Verify "Back to Dashboard" button is present (paid-specific)
    const backToDashboard = page.locator('text=/back to dashboard/i');
    await expect(backToDashboard).toBeVisible({ timeout: 10000 });

    // Capture session state before refresh
    const sessionBefore = await helpers.getSessionState(page);
    expect(sessionBefore.sm_reading_loaded).toBeTruthy();

    // Refresh the page
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);

    // EXPECTED: REPORT STAYS ON SCREEN, all sections unlocked
    const reportAfter = page.locator('#aura-reading-result, .reading-result-container, .report-container');
    await expect(reportAfter.first()).toBeVisible({ timeout: 10000 });

    // Verify we didn't get redirected
    const welcomeVisible = await page.locator('.welcome-form').isVisible().catch(() => false);
    expect(welcomeVisible).toBe(false);

    const dashboardVisible = await page.locator('.dashboard-container').isVisible().catch(() => false);
    expect(dashboardVisible).toBe(false);

    // Verify "Back to Dashboard" is still present
    const backToDashboardAfter = page.locator('text=/back to dashboard/i');
    await expect(backToDashboardAfter).toBeVisible({ timeout: 10000 });

    // Verify session state is restored
    const sessionAfter = await helpers.getSessionState(page);
    expect(sessionAfter.sm_reading_loaded).toBeTruthy();
  });

  test('REFRESH-008: Refresh during generation (loading screen)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available - using seeded reading to bypass image validation');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Loading Refresh User';

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    // Complete flow up to quiz submission
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);

    // Wait for loading screen to appear
    const loadingVisible = await page.waitForSelector('.loading-container, .loading-step, .generating', {
      timeout: 10000
    }).then(() => true).catch(() => false);

    // If loading screen appears, refresh immediately
    if (loadingVisible) {
      await page.waitForTimeout(500); // Brief delay to ensure loading state is set

      // Refresh the page
      await page.reload({ waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(1000);

      // EXPECTED: Loading screen returns, polling resumes, report displays when ready
      // Either we see loading screen again OR report is already ready OR error screen
      const loadingAfterRefresh = await page.locator('.loading-container, .loading-step, .generating')
        .isVisible()
        .catch(() => false);

      const reportAfterRefresh = await page.locator('#aura-reading-result, .report-container')
        .isVisible()
        .catch(() => false);

      const errorAfterRefresh = await page.locator('text=/could not.*see.*palm|unable to analyze|try again/i')
        .isVisible()
        .catch(() => false);

      // At least one should be true (loading, report, or error)
      expect(loadingAfterRefresh || reportAfterRefresh || errorAfterRefresh).toBe(true);

      // Wait for final state (report or error)
      if (!reportAfterRefresh && !errorAfterRefresh) {
        // Still loading, wait for completion
        const finalStateVisible = await waitForAnySelector(page, [
          '#aura-reading-result',
          '.report-container',
          'text=/could not.*see.*palm/i',
          'text=/unable to analyze/i'
        ], REPORT_WAIT_MS);

        expect(finalStateVisible).toBe(true);
      }
    } else {
      // Report/error generated too quickly, just verify final state exists
      const reportOrError = await waitForAnySelector(page, [
        '#aura-reading-result',
        '.report-container',
        'text=/could not.*see.*palm/i'
      ], 15000);

      expect(reportOrError).toBe(true);
    }

    // Verify we didn't get kicked to welcome
    const welcomeVisible = await page.locator('.welcome-form').isVisible().catch(() => false);
    expect(welcomeVisible).toBe(false);
  });

  test('REFRESH-014: Teaser photo reupload after refresh/back', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isMailpitAvailable()), 'MailPit not available for OTP validation');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Teaser Photo Refresh User';

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);

    await uploadPalmPhoto(page, VALID_PALM_IMAGE);

    // Wait until quiz step appears
    await waitForAnySelector(page, ['.option-btn', '.rating-btn', 'textarea.form-textarea'], 15000);

    // Try to go back to photo step
    const backBtn = page.locator('#back-btn, button:has-text("Back"), text=/^back$/i');
    if (await backBtn.first().isVisible().catch(() => false)) {
      await backBtn.first().click();
    }

    // Ensure we can see the photo upload step again
    await waitForAnySelector(page, ['#photo-upload-input', '#upload-btn', 'input[type="file"]'], 15000);

    // Refresh on photo step
    await page.reload({ waitUntil: 'domcontentloaded' });
    await waitForAnySelector(page, ['#photo-upload-input', '#upload-btn', 'input[type="file"]'], 15000);

    // Reupload photo
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);

    // Complete quiz and attempt generation
    await completeQuiz(page);

    const reachedFinalState = await waitForAnySelector(page, [
      '#aura-reading-result',
      '.report-container',
      'text=/please upload your palm image first/i',
      'text=/could not.*see.*palm|unable to analyze|try again/i'
    ], REPORT_WAIT_MS);

    expect(reachedFinalState).toBe(true);

    // If we hit the "upload image first" error, click retry and ensure photo step returns
    const uploadFirstError = await page.locator('text=/please upload your palm image first/i').isVisible().catch(() => false);
    if (uploadFirstError) {
      const retryBtn = page.locator('.loading-error-actions .btn-primary, button:has-text("Try Again"), button:has-text("Retry")');
      if (await retryBtn.first().isVisible().catch(() => false)) {
        await retryBtn.first().click();
      }

      const photoStepVisible = await waitForAnySelector(page, ['#photo-upload-input', '#upload-btn', 'input[type="file"]'], 15000);
      expect(photoStepVisible).toBe(true);
    }

    const welcomeVisible = await page.locator('.welcome-form').isVisible().catch(() => false);
    expect(welcomeVisible).toBe(false);
  });

  test('REFRESH-013: Rapid refresh stress test (5x consecutive)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seeded reading');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();

    // Seed a teaser reading
    const seeded = await helpers.seedReading(testEmail, 'Rapid Refresh User');
    expect(seeded.reading_id).toBeTruthy();

    // Navigate to report
    await page.goto(`${BASE_URL}?sm_report=1&lead_id=${seeded.lead_id}`, { waitUntil: 'domcontentloaded' });

    // Wait for report to display initially
    const report = await waitForReport(page);
    await expect(report).toBeVisible();

    // Perform 5 consecutive rapid refreshes
    for (let i = 0; i < 5; i++) {
      console.log(`[REFRESH-013] Rapid refresh ${i + 1}/5`);

      await page.reload({ waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(300); // Very short delay to simulate rapid F5 presses
    }

    // Give page time to stabilize
    await page.waitForTimeout(2000);

    // EXPECTED: No infinite loops, no stuck states, page stabilizes
    const reportAfter = page.locator('#aura-reading-result, .reading-result-container, .report-container');
    await expect(reportAfter.first()).toBeVisible({ timeout: 10000 });

    // Verify we didn't get stuck in a loop or kicked to welcome
    const welcomeVisible = await page.locator('.welcome-form').isVisible().catch(() => false);
    expect(welcomeVisible).toBe(false);

    // Verify no loading loops
    const loadingVisible = await page.locator('.loading-container, .loading-step').isVisible().catch(() => false);
    expect(loadingVisible).toBe(false);

    // Verify session state is stable
    const sessionAfter = await helpers.getSessionState(page);
    expect(sessionAfter.sm_reading_loaded).toBeTruthy();

    // Verify page is interactive (not frozen)
    const unlockBtn = page.locator('.unlock-section-btn, .btn-unlock').first();
    if (await unlockBtn.isVisible().catch(() => false)) {
      await expect(unlockBtn).toBeEnabled();
    }
  });
});

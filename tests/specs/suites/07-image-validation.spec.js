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
const MAILPIT_TIMEOUT_MS = USE_LIVE_OPENAI ? 20000 : 12000;
const FORCE_LIVE_IMAGE_REJECT = process.env.E2E_EXPECT_IMAGE_REJECT === '1';

const VALID_PALM_IMAGE = path.resolve(__dirname, '../../helpers/images/valid/valid-palm-1.png');
const INVALID_IMAGE_1 = path.resolve(__dirname, '../../helpers/images/invalid/invalid-1.png');
const INVALID_IMAGE_2 = path.resolve(__dirname, '../../helpers/images/invalid/invalid-2.png');
const PHOTO_PREVIEW_SELECTORS = [
  '#capture-preview',
  '[data-testid="photo-preview"]',
  '#photo-preview',
  '.photo-preview',
  'img[alt*="palm" i]'
];
const POST_UPLOAD_READY_SELECTORS = [
  '.option-btn',
  '.rating-btn',
  'textarea.form-textarea',
  '#next-btn',
  '.loading-container',
  '.loading-step',
  '.loading-text.loading-error',
  '#aura-reading-result',
  '.reading-result-container',
  '.report-container'
];

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

async function waitForUploadInput(page, timeoutMs = 15000) {
  const start = Date.now();
  const candidates = [page.locator('#photo-upload-input').first(), page.locator('input[type="file"]').first()];
  while (Date.now() - start < timeoutMs) {
    for (const candidate of candidates) {
      const valid = await candidate.evaluate((el) => {
        return !!(el && el.isConnected && el.tagName === 'INPUT' && el.type === 'file' && !el.disabled);
      }).catch(() => false);
      if (valid) {
        return candidate;
      }
    }
    await page.waitForTimeout(250).catch(() => {});
  }
  return null;
}

async function fillVisibleInput(locator, value) {
  await locator.scrollIntoViewIfNeeded();
  await locator.click({ force: true });
  await locator.fill('');
  await locator.type(value, { delay: 30 });
  await expect(locator).toHaveValue(value, { timeout: 5000 });
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
    if (!advanced && await isLeadCaptureStep()) {
      advanced = true;
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

  await page.waitForSelector('.code-input', { timeout: 10000 });
}

async function uploadPalmPhoto(page, imagePath) {
  await page.waitForSelector('#upload-btn, #photo-upload-input', { timeout: 20000, state: 'attached' }).catch(() => {});

  const inputTarget = await waitForUploadInput(page, 20000);
  if (!inputTarget) {
    throw new Error('Photo input not ready before upload.');
  }

  await inputTarget.setInputFiles(imagePath);
  const hasFile = await inputTarget.evaluate((el) => Boolean(el.files && el.files.length > 0)).catch(() => false);
  if (!hasFile) {
    throw new Error('Photo input did not receive selected file.');
  }
  await inputTarget.evaluate((input) => {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }).catch(() => {});

  await waitForAnySelector(page, PHOTO_PREVIEW_SELECTORS, 10000);

  const usePhotoBtn = page.locator('#use-photo-btn');
  const usePhotoTextBtn = page.locator('button:has-text("Use This Photo"), button:has-text("Use Photo")');
  await usePhotoBtn.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  if (await usePhotoBtn.isVisible().catch(() => false)) {
    try {
      await usePhotoBtn.click({ force: true, timeout: 5000 });
    } catch (error) {
      const advanced = await waitForAnySelector(page, POST_UPLOAD_READY_SELECTORS, 3000);
      if (!advanced) {
        console.warn(`⚠️  Use photo button click failed: ${error.message}`);
      }
    }
  } else if (await usePhotoTextBtn.isVisible().catch(() => false)) {
    await usePhotoTextBtn.click({ force: true });
  }

  const advanced = await waitForAnySelector(page, POST_UPLOAD_READY_SELECTORS, 15000);
  if (!advanced) {
    throw new Error('Photo upload did not advance to quiz/loading state.');
  }
}

async function completeQuiz(page) {
  const nextBtn = page.locator('#next-btn');

  await nextBtn.waitFor({ state: 'visible', timeout: 15000 });

  const answerStep = async () => {
    const reportVisible = await page.locator('#aura-reading-result, .report-container').isVisible().catch(() => false);
    if (reportVisible || page.url().includes('sm_report=1')) {
      return false;
    }
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
      try {
        await textArea.fill('Test response');
      } catch {
        return false;
      }
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
      const nextHiddenByError = await nextBtn.getAttribute('data-sm-hidden-by-palm-error').catch(() => null);
      const reportNow = await page.locator('#aura-reading-result, .report-container').isVisible().catch(() => false);
      const loadingNow = await page.locator('.loading-container, .loading-step').isVisible().catch(() => false);
      if (reportNow || loadingNow || nextHiddenByError) {
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
  try {
    await page.waitForSelector(selector, { timeout: REPORT_WAIT_MS });
  } catch (error) {
    const loadingError = page.locator('.loading-text.loading-error');
    if (await loadingError.isVisible().catch(() => false)) {
      const errorText = (await loadingError.first().textContent().catch(() => '')) || '';
      throw new Error(`Report did not render; loading error shown: ${errorText.trim()}`);
    }
    if (page.url().includes('sm_report=1')) {
      await page.waitForLoadState('domcontentloaded').catch(() => {});
      await page.waitForSelector(selector, { timeout: 20000 });
    } else {
      throw error;
    }
  }
  return page.locator(selector).first();
}

async function waitForPalmError(page, timeoutMs = 60000, options = {}) {
  const { apiCalls, context } = options;
  const errorText = page.locator('.loading-text.loading-error');
  const report = page.locator('#aura-reading-result, .reading-result-container, .report-container');

  try {
    await errorText.waitFor({ state: 'visible', timeout: timeoutMs });
  } catch (error) {
    const reportVisible = await report.isVisible().catch(() => false);
    if (reportVisible) {
      throw new Error(`${context || 'Palm rejection'}: invalid image was accepted and a report rendered.`);
    }
    const hasApiError = Array.isArray(apiCalls)
      ? apiCalls.some(call => call.body && call.body.error_code === 'palm_image_invalid')
      : false;
    if (!hasApiError) {
      throw new Error(`${context || 'Palm rejection'}: no error UI or palm_image_invalid response observed.`);
    }
    throw error;
  }

  const text = (await errorText.textContent()) || '';
  expect(text.toLowerCase()).toMatch(/palm|unable|could not|try again/);
}

async function waitForPalmLockout(apiCalls, timeoutMs = 15000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    const lockedCall = apiCalls.find(call => {
      const body = call.body || {};
      return body.error_code === 'palm_image_invalid' && body.data && body.data.locked === true;
    });
    if (lockedCall) {
      return lockedCall;
    }
    await new Promise(resolve => setTimeout(resolve, 250));
  }
  throw new Error('Expected locked palm image response was not found.');
}

async function isDevModeEnabled(page) {
  const helpers = new E2EHelpers(BASE_URL);
  return helpers.isDevModeEnabled(page);
}

test.describe('Phase 7 - Image Validation', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(USE_LIVE_OPENAI ? 240000 : 150000);

  test.afterEach(async () => {
    const helpers = new E2EHelpers(BASE_URL);
    try {
      await helpers.cleanupTestData();
    } catch (error) {
      console.warn(`⚠️  Cleanup skipped: ${error.message}`);
    }
  });

  test('IMG-001: Valid palm image - generation succeeds', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Valid Palm User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);

    await waitForReport(page);
  });

  test('IMG-002: Invalid image - not a palm', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const devMode = await isDevModeEnabled(page);
    const expectReject = FORCE_LIVE_IMAGE_REJECT || USE_LIVE_OPENAI || !devMode;
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Invalid Palm User 1';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, INVALID_IMAGE_1);
    await completeQuiz(page);

    if (expectReject) {
      await waitForPalmError(page, 60000, { apiCalls, context: 'IMG-002' });
    } else {
      await waitForReport(page);
    }
  });

  test('IMG-003: Invalid image - non-palm variation', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const devMode = await isDevModeEnabled(page);
    const expectReject = FORCE_LIVE_IMAGE_REJECT || USE_LIVE_OPENAI || !devMode;
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Invalid Palm User 2';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, INVALID_IMAGE_2);
    await completeQuiz(page);

    if (expectReject) {
      await waitForPalmError(page, 60000, { apiCalls, context: 'IMG-003' });
    } else {
      await waitForReport(page);
    }
  });

  test('IMG-004: Retry after invalid image (1st retry)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const devMode = await isDevModeEnabled(page);
    const expectReject = FORCE_LIVE_IMAGE_REJECT || USE_LIVE_OPENAI || !devMode;
    test.skip(!expectReject, 'Invalid image is accepted in DevMode (no retry UI).');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Retry Palm User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, INVALID_IMAGE_1);
    await completeQuiz(page);

    await waitForPalmError(page, 60000, { context: 'IMG-004' });

    const retryBtn = page.locator('.loading-error-actions .btn-primary');
    await retryBtn.waitFor({ state: 'visible', timeout: 15000 });
    await retryBtn.click({ force: true });

    await page.waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 20000, state: 'attached' });
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);

    await waitForReport(page);
  });

  test('IMG-005: Retry after invalid image (2nd retry)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const devMode = await isDevModeEnabled(page);
    const expectReject = FORCE_LIVE_IMAGE_REJECT || USE_LIVE_OPENAI || !devMode;
    test.skip(!expectReject, 'Invalid image is accepted in DevMode (no retry UI).');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Retry Palm User Two';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, INVALID_IMAGE_1);
    await completeQuiz(page);

    await waitForPalmError(page, 60000, { context: 'IMG-005 first' });

    let retryBtn = page.locator('.loading-error-actions .btn-primary');
    await retryBtn.waitFor({ state: 'visible', timeout: 15000 });
    await retryBtn.click({ force: true });

    await page.waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 20000, state: 'attached' });
    await uploadPalmPhoto(page, INVALID_IMAGE_2);
    await completeQuiz(page);

    await waitForPalmError(page, 60000, { context: 'IMG-005 second' });

    retryBtn = page.locator('.loading-error-actions .btn-primary');
    const retryVisible = await retryBtn.isVisible().catch(() => false);
    if (retryVisible) {
      await retryBtn.click({ force: true });

      await page.waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 20000, state: 'attached' });
      await uploadPalmPhoto(page, VALID_PALM_IMAGE);
      await completeQuiz(page);

      await waitForReport(page);
    } else {
      const lockoutText = page.locator('text=/multiple attempts|contact support/i');
      await expect(lockoutText.first()).toBeVisible({ timeout: 10000 });
    }
  });

  test('IMG-006: Email notification after retry success', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025', 5000);
    const devMode = await isDevModeEnabled(page);
    const expectReject = FORCE_LIVE_IMAGE_REJECT || USE_LIVE_OPENAI || !devMode;
    test.skip(!expectReject, 'Invalid image is accepted in DevMode (no retry email flow).');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Retry Mail User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, INVALID_IMAGE_1);
    await completeQuiz(page);

    await waitForPalmError(page, 60000, { context: 'IMG-006' });

    const retryBtn = page.locator('.loading-error-actions .btn-primary');
    await retryBtn.waitFor({ state: 'visible', timeout: 15000 });
    await retryBtn.click({ force: true });

    await page.waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 20000, state: 'attached' });
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);

    await waitForReport(page);

    const magicLink = await mailpit.extractMagicLink(testEmail, MAILPIT_TIMEOUT_MS);
    expect(magicLink).toBeTruthy();
  });

  test('IMG-007: Lockout after 2 invalid images (teaser)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const devMode = await isDevModeEnabled(page);
    const expectReject = FORCE_LIVE_IMAGE_REJECT || USE_LIVE_OPENAI || !devMode;
    test.skip(!expectReject, 'Invalid image is accepted in DevMode (no lockout UI).');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Lockout Teaser User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, INVALID_IMAGE_1);
    await completeQuiz(page);

    await waitForPalmError(page, 60000, { context: 'IMG-007 first' });

    const retryBtn = page.locator('.loading-error-actions .btn-primary');
    await retryBtn.waitFor({ state: 'visible', timeout: 15000 });
    await retryBtn.click({ force: true });

    await page.waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 20000, state: 'attached' });
    await uploadPalmPhoto(page, INVALID_IMAGE_2);
    await completeQuiz(page);

    await waitForPalmError(page, 60000, { context: 'IMG-007 lockout' });
    await waitForPalmLockout(apiCalls);

    const lockoutMessage = page.locator('.loading-text.loading-error');
    await expect(lockoutMessage).toContainText(/contact support|start a new reading/i, { timeout: 10000 });
    await expect(retryBtn).toBeHidden({ timeout: 5000 });
  });
});

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

const VALID_PALM_IMAGE = path.resolve(__dirname, '../../helpers/images/valid/valid-palm-1.png');
const INVALID_IMAGE_1 = path.resolve(__dirname, '../../helpers/images/invalid/invalid-1.png');

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
  const nameInput = page.locator('input[placeholder*="name" i], input[aria-label*="name" i], input.form-input[type="text"], input[type="text"]');
  const identitySelect = page.locator('select[name="identity"], select[aria-label*="identify" i]');
  const ageInput = page.locator('input[type="number"], input[placeholder*="age" i], input[aria-label*="old" i]');
  const gdprCheckbox = page.locator('.checkbox-custom-input, input[type="checkbox"]');
  const gdprLabel = page.locator('.checkbox-custom-label, label:has-text("I agree")');

  if (await emailInput.isVisible().catch(() => false)) {
    await emailInput.fill(testEmail);
  }
  if (await nameInput.first().isVisible().catch(() => false)) {
    await nameInput.first().fill(testName);
  }
  if (await identitySelect.first().isVisible().catch(() => false)) {
    await identitySelect.first().selectOption('prefer-not').catch(async () => {
      await identitySelect.first().selectOption({ label: /prefer not/i }).catch(() => {});
    });
  } else if (await identityByRole.isVisible().catch(() => false)) {
    await identityByRole.selectOption({ label: /prefer not/i }).catch(async () => {
      await identityByRole.click({ force: true });
      await page.locator('option:has-text("Prefer not")').click().catch(() => {});
    });
  }
  if (await ageInput.first().isVisible().catch(() => false)) {
    await ageInput.first().fill('29');
  } else if (await ageInputByRole.isVisible().catch(() => false)) {
    await ageInputByRole.fill('29');
  } else if (await ageInputFallback.first().isVisible().catch(() => false)) {
    await ageInputFallback.first().fill('29');
  }
  if (await gdprCheckbox.isVisible().catch(() => false)) {
    await gdprCheckbox.scrollIntoViewIfNeeded().catch(() => {});
    await gdprCheckbox.click({ force: true });
    const isChecked = await gdprCheckbox.evaluate(el => el.classList.contains('checked')).catch(() => false);
    if (!isChecked && await gdprLabel.isVisible().catch(() => false)) {
      await gdprLabel.click({ force: true });
    }
  }

  if (await continueBtn.isVisible().catch(() => false)) {
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
  await page.waitForSelector(previewSelector, { timeout: 10000 }).catch(() => {});

  const usePhotoBtn = page.locator('#use-photo-btn');
  const quizSelectors = ['.option-btn', '.rating-btn', 'textarea.form-textarea', '#next-btn'];
  await usePhotoBtn.waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
  if (await usePhotoBtn.isVisible().catch(() => false)) {
    try {
      await usePhotoBtn.click({ force: true, timeout: 5000 });
    } catch (error) {
      const advanced = await waitForAnySelector(page, quizSelectors, 3000);
      if (!advanced) {
        console.warn(`⚠️  Use photo button click failed: ${error.message}`);
      }
    }
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
  await page.waitForSelector(selector, { timeout: REPORT_WAIT_MS });
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

async function waitForDashboard(page) {
  await page.waitForSelector('.dashboard-container, #generate-new-reading-btn', { timeout: 15000 });
}

async function ensurePhotoUploadStep(page) {
  const ready = await page
    .waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 15000, state: 'attached' })
    .then(() => true)
    .catch(() => false);
  if (ready) {
    return;
  }

  const startUrl = new URL(BASE_URL);
  startUrl.searchParams.set('start_new', '1');
  await page.goto(startUrl.toString(), { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 15000, state: 'attached' });
}

async function startNewReadingFromDashboard(page) {
  const primaryButton = page.locator('#generate-new-reading-btn');
  if (await primaryButton.isVisible().catch(() => false)) {
    await primaryButton.click();
    await ensurePhotoUploadStep(page);
    return;
  }

  const beginJourney = page.locator('button:has-text("Begin Journey")');
  if (await beginJourney.isVisible().catch(() => false)) {
    await beginJourney.click();
    await ensurePhotoUploadStep(page);
    return;
  }

  const newReadingCardButton = page.locator('.quick-action-card:has-text("New Reading") button');
  if (await newReadingCardButton.isVisible().catch(() => false)) {
    await newReadingCardButton.click();
    await ensurePhotoUploadStep(page);
    return;
  }

  const fallbackButton = page.locator('button:has-text("Generate"), button:has-text("New Reading")').first();
  if (await fallbackButton.isVisible().catch(() => false)) {
    await fallbackButton.click();
    await ensurePhotoUploadStep(page);
    return;
  }

  await ensurePhotoUploadStep(page);
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

async function isMailpitAvailable() {
  const baseUrl = process.env.MAILPIT_URL || 'http://localhost:8025';
  const url = `${baseUrl.replace(/\/$/, '')}/api/v1/messages`;

  try {
    const response = await fetch(url, { method: 'GET' });
    return response.ok;
  } catch {
    return false;
  }
}

test.describe('Phase 8 - Email Notifications', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(USE_LIVE_OPENAI ? 240000 : 160000);

  test.afterEach(async () => {
    const helpers = new E2EHelpers(BASE_URL);
    try {
      await helpers.cleanupTestData();
    } catch (error) {
      console.warn(`⚠️  Cleanup skipped: ${error.message}`);
    }
  });

  test('EMAIL-001: OTP email delivery', async ({ page }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for email validation');

    const helpers = new E2EHelpers(BASE_URL);
    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'OTP Mail User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);

    const email = await mailpit.getLatestEmail(testEmail, MAILPIT_TIMEOUT_MS);
    expect(email).toBeTruthy();
    expect(email.Subject).toContain('SoulMirror verification code');

    const otp = await mailpit.extractOTP(testEmail, MAILPIT_TIMEOUT_MS);
    expect(otp).toBeTruthy();
    expect(otp.length).toBeGreaterThanOrEqual(4);
  });

  test('EMAIL-002: Teaser completion email (magic link)', async ({ page }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for email validation');

    const helpers = new E2EHelpers(BASE_URL);
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);
    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Teaser Mail User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);
    await waitForReport(page);

    const email = await mailpit.getLatestEmail(testEmail, MAILPIT_TIMEOUT_MS);
    expect(email).toBeTruthy();
    expect(email.Subject).toContain('Your Palm Reading Is Ready');

    const magicLink = await mailpit.extractMagicLink(testEmail, MAILPIT_TIMEOUT_MS);
    expect(magicLink).toBeTruthy();
    expect(magicLink).toContain('sm_report=1');
    expect(magicLink).toContain('reading_type=aura_teaser');
  });

  test('EMAIL-003: Paid completion email (magic link)', async ({ page }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for email validation');

    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for mock login');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);
    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Paid Mail User';

    await mockLoginInBrowser(page, testEmail, testName);
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);
    await startNewReadingFromDashboard(page);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);
    await waitForReport(page);

    const email = await mailpit.getLatestEmail(testEmail, MAILPIT_TIMEOUT_MS);
    expect(email).toBeTruthy();
    expect(email.Subject).toContain('Your Full Palm Reading Is Ready');

    const magicLink = await mailpit.extractMagicLink(testEmail, MAILPIT_TIMEOUT_MS);
    expect(magicLink).toBeTruthy();
    expect(magicLink).toContain('sm_report=1');
    expect(magicLink).toContain('reading_type=aura_full');
  });

  test('EMAIL-004: Generation failure email (retry prompt)', async ({ page }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for email validation');

    const helpers = new E2EHelpers(BASE_URL);
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);
    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Failure Mail User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, INVALID_IMAGE_1);
    await completeQuiz(page);

    await waitForPalmError(page, 60000, { apiCalls, context: 'EMAIL-004' });

    const email = await mailpit.getLatestEmail(testEmail, MAILPIT_TIMEOUT_MS);
    expect(email).toBeTruthy();
    expect(email.Subject).toContain('We Need a Clearer Palm Photo');

    const magicLink = await mailpit.extractMagicLink(testEmail, MAILPIT_TIMEOUT_MS);
    expect(magicLink).toBeTruthy();
    expect(magicLink).toContain('sm_resubmit=1');
  });

  test('EMAIL-005: Teaser magic link - click from email', async ({ page, context }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for email validation');

    const helpers = new E2EHelpers(BASE_URL);
    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Teaser Link User';

    await helpers.ensureAppLoaded(page);
    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);
    await waitForReport(page);

    const magicLink = await mailpit.extractMagicLink(testEmail, MAILPIT_TIMEOUT_MS);
    expect(magicLink).toBeTruthy();

    const magicPage = await context.newPage();
    await magicPage.goto(magicLink, { waitUntil: 'domcontentloaded' });
    const report = await waitForReport(magicPage);
    await expect(report).toBeVisible();
    await magicPage.close();
  });

  test('EMAIL-006: Paid magic link - click from email (not logged in)', async ({ page, browser }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for email validation');

    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for mock login');

    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Paid Link User';

    await mockLoginInBrowser(page, testEmail, testName);
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);
    await startNewReadingFromDashboard(page);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);
    await waitForReport(page);

    const magicLink = await mailpit.extractMagicLink(testEmail, MAILPIT_TIMEOUT_MS);
    expect(magicLink).toBeTruthy();

    const loggedOutContext = await browser.newContext();
    const loggedOutPage = await loggedOutContext.newPage();
    let navigated = false;
    try {
      await loggedOutPage.goto(magicLink, { waitUntil: 'domcontentloaded', timeout: 20000 });
      navigated = true;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      const currentUrl = loggedOutPage.url();
      if (currentUrl.includes('/account/login')) {
        navigated = true;
      } else if (/ERR_NAME_NOT_RESOLVED|ERR_CONNECTION_REFUSED|net::ERR/i.test(errorMessage)) {
        test.skip('Account Service login not reachable from test environment');
      } else {
        throw error;
      }
    }

    if (navigated) {
      const reportVisible = await loggedOutPage.locator('#aura-reading-result, .report-container').isVisible().catch(() => false);
      if (reportVisible) {
        await expect(loggedOutPage.locator('#aura-reading-result, .report-container').first()).toBeVisible();
      } else {
        expect(loggedOutPage.url()).toContain('/account/login');
      }
    }

    await loggedOutContext.close();
  });

  test('EMAIL-007: Paid magic link - click from email (already logged in)', async ({ page, context }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for email validation');

    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for mock login');

    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Paid Link Logged User';

    await mockLoginInBrowser(page, testEmail, testName);
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);
    await startNewReadingFromDashboard(page);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);
    await waitForReport(page);

    const magicLink = await mailpit.extractMagicLink(testEmail, MAILPIT_TIMEOUT_MS);
    expect(magicLink).toBeTruthy();

    const magicPage = await context.newPage();
    await magicPage.goto(magicLink, { waitUntil: 'domcontentloaded' });
    const report = await waitForReport(magicPage);
    await expect(report).toBeVisible();
    await magicPage.close();
  });
});

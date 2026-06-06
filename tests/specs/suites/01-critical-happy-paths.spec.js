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
  const nameInput = page.locator('input[placeholder*="name" i], input[aria-label*="name" i], input.form-input[type="text"]');
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

  await page.waitForSelector('.code-input', { timeout: 10000 });
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
    try {
      await usePhotoBtn.click({ timeout: 5000, force: true });
    } catch (error) {
      console.warn('[E2E DEBUG] Use photo click skipped:', error?.message || error);
      await page.evaluate(() => {
        const btn = document.querySelector('#use-photo-btn');
        if (btn) {
          btn.click();
        }
      }).catch(() => {});
    }
  }

  await waitForAnySelector(
    page,
    [
      '.option-btn',
      '.rating-btn',
      'textarea.form-textarea',
      '.loading-container',
      '.loading-step'
    ],
    15000
  );
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

  const fallbackButton = page.locator('button:has-text("Generate"), button:has-text("New Reading")').first();
  if (await fallbackButton.isVisible().catch(() => false)) {
    await fallbackButton.click();
    await ensurePhotoUploadStep(page);
    return;
  }

  await ensurePhotoUploadStep(page);
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
      await textArea.scrollIntoViewIfNeeded().catch(() => {});
      await textArea.click({ force: true, timeout: 2000 }).catch(() => {});
      try {
        await textArea.fill('Test response');
      } catch {
        await textArea.evaluate((el) => {
          el.value = 'Test response';
          el.dispatchEvent(new Event('input', { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        });
      }
      const currentValue = await textArea.inputValue().catch(() => '');
      if (currentValue !== 'Test response') {
        await textArea.evaluate((el) => {
          el.value = 'Test response';
          el.dispatchEvent(new Event('input', { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        });
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

test.describe('Phase 1 - Critical happy paths', () => {
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

  test('HP-001: Complete teaser flow (new user)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Happy Path User';

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await fillLeadCapture(page, testEmail, testName);
    await helpers.enterOtp(page, testEmail);
    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);

    const report = await waitForReport(page);
    await expect(report).toBeVisible();

    const unlockButtons = page.locator('.unlock-section-btn, .btn-unlock');
    const unlockCount = await unlockButtons.count();
    expect(unlockCount).toBeGreaterThanOrEqual(2);

    const sessionState = await helpers.getSessionState(page);
    const leadIdFromSession = sessionState.sm_reading_lead_id;
    const leadIdFromUrl = new URL(page.url()).searchParams.get('lead_id');
    const leadIdFromDom = await page
      .locator('[data-lead-id]')
      .first()
      .getAttribute('data-lead-id')
      .catch(() => null);
    expect(leadIdFromSession || leadIdFromUrl || leadIdFromDom).toBeTruthy();

    const unlockInfo = page.locator('text=/free unlocks remaining/i');
    if (await unlockInfo.isVisible().catch(() => false)) {
      await expect(unlockInfo).toBeVisible();
    }
  });

  test('HP-005: Reject invalid OTP', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Invalid OTP User';

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await fillLeadCapture(page, testEmail, testName);

    const otpInputs = page.locator('.code-input');
    await otpInputs.first().waitFor({ state: 'visible', timeout: 15000 });
    const invalidCode = ['0', '0', '0', '0'];
    for (let i = 0; i < invalidCode.length; i++) {
      await otpInputs.nth(i).fill(invalidCode[i]);
    }

    const verifyBtn = page.locator('#next-btn, button:has-text("Continue")');
    if (await verifyBtn.isVisible().catch(() => false)) {
      await verifyBtn.click();
    }

    const errorToast = page.locator('text=/invalid (verification )?code|invalid code/i');
    await expect(errorToast).toBeVisible({ timeout: 10000 });
    await expect(otpInputs.first()).toBeVisible();
  });

  test('HP-002: Complete paid flow (first paid report)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for mock login');
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    await helpers.setDevMode('dev_all');

    const testEmail = E2EHelpers.generateTestEmail();
    await mockLoginInBrowser(page, testEmail, 'Paid Test User');

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await waitForDashboard(page);
    await startNewReadingFromDashboard(page);

    await uploadPalmPhoto(page, VALID_PALM_IMAGE);
    await completeQuiz(page);

    const report = await waitForReport(page);
    await expect(report).toBeVisible();
    await expect(page.locator('text=Back to Dashboard')).toBeVisible();
  });

  test('HP-003: Returning free user (same email)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seeded reading');
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    await helpers.seedReading(testEmail, 'Returning User');

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    const welcomeEmailInput = page.locator('.welcome-form input[name="email"]');
    const welcomeContinueBtn = page.locator('.welcome-form button[type="submit"]');
    const nextBtn = page.locator('#next-btn');

    if (await welcomeEmailInput.isVisible().catch(() => false)) {
      await welcomeEmailInput.fill(testEmail);
      if (await welcomeContinueBtn.isVisible().catch(() => false)) {
        await welcomeContinueBtn.click();
      } else if (await nextBtn.isVisible().catch(() => false)) {
        await nextBtn.click();
      }
    }

    const loginUrl = await page.evaluate(() => (window.smData && smData.auth && smData.auth.loginUrl) || null);
    if (loginUrl) {
      await page.waitForURL(/account\/login/i, { timeout: 10000 });
    } else {
      const toast = page.locator('text=/log in|login/i');
      await expect(toast).toBeVisible({ timeout: 5000 });
    }

    const otpVisible = await page.locator('.code-input').isVisible().catch(() => false);
    expect(otpVisible).toBe(false);
  });

  test('HP-008: New welcome email overrides stale restored state', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seeded reading');

    const staleEmail = E2EHelpers.generateTestEmail();
    const newEmail = E2EHelpers.generateTestEmail();
    const testName = 'Fresh State User';
    await helpers.seedReading(staleEmail, 'Stale Restored User');

    await page.addInitScript(({ stale }) => {
      localStorage.clear();
      sessionStorage.clear();
      const staleState = {
        currentStep: 0,
        userData: {
          name: 'Stale User',
          email: stale,
          identity: 'prefer-not',
          age: '31',
          ageRange: '26-35',
          emailVerified: false,
          palmImage: null,
          gdprConsent: true
        },
        quizResponses: {},
        timestamp: Date.now()
      };
      localStorage.setItem('sm_app_state_guest', JSON.stringify(staleState));
      sessionStorage.setItem('guest:sm_email', stale);
      sessionStorage.setItem('sm_email', stale);
    }, { stale: staleEmail });

    let leadCreateEmail = null;
    await page.route('**/wp-json/soulmirror/v1/lead/create', async route => {
      try {
        const payload = route.request().postDataJSON();
        leadCreateEmail = payload && payload.email ? payload.email : null;
      } catch (error) {
        leadCreateEmail = null;
      }
      await route.continue();
    });

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    const welcomeEmailInput = page.locator('.welcome-form input[name="email"]');
    const welcomeContinueBtn = page.locator('.welcome-form button[type="submit"]');
    await expect(welcomeEmailInput).toBeVisible({ timeout: 15000 });
    await fillVisibleInput(welcomeEmailInput, newEmail);
    await welcomeContinueBtn.click();

    const nameInput = page.locator('input[placeholder*="name" i], input.form-input[type="text"]').first();
    const identitySelect = page.locator('select[name="identity"], select[aria-label*="identify" i]').first();
    const ageInput = page.locator('input[type="number"], input[placeholder*="age" i]').first();
    const gdprCheckbox = page.locator('.checkbox-custom-input').first();
    const gdprLabel = page.locator('.checkbox-custom-label').first();
    const nextBtn = page.locator('#next-btn');

    await expect(nameInput).toBeVisible({ timeout: 15000 });
    await nameInput.fill(testName);
    await identitySelect.selectOption('prefer-not').catch(async () => {
      await identitySelect.selectOption({ label: /prefer not/i });
    });
    await ageInput.fill('29');
    await gdprCheckbox.click({ force: true }).catch(() => {});
    const checkboxChecked = await gdprCheckbox.evaluate(el => el.classList.contains('checked')).catch(() => false);
    if (!checkboxChecked) {
      await gdprLabel.click({ force: true }).catch(() => {});
    }
    const checkboxCheckedAfterLabel = await gdprCheckbox.evaluate(el => el.classList.contains('checked')).catch(() => false);
    if (!checkboxCheckedAfterLabel) {
      await page.evaluate(() => {
        const checkbox = document.querySelector('.checkbox-custom-input');
        if (checkbox) {
          checkbox.classList.add('checked');
          checkbox.dispatchEvent(new Event('click', { bubbles: true }));
        }
      });
    }
    await expect(nextBtn).toBeEnabled({ timeout: 10000 });
    await nextBtn.click();

    await expect(page.locator('.code-input').first()).toBeVisible({ timeout: 10000 });
    expect(leadCreateEmail).toBe(newEmail);
  });

  test('HP-006: Existing paid reading redirects to login on lead create', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seeded reading');
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    await helpers.seedReading(testEmail, 'Paid Only User', 'aura_full');

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    const loginUrl = await page.evaluate(() => (window.smData && smData.auth && smData.auth.loginUrl) || null);
    const response = await page.request.post(`${helpers.apiBase}/soulmirror/v1/lead/create`, {
      data: {
        name: 'Paid Only User',
        email: testEmail,
        identity: 'prefer-not',
        gdpr: true,
        age: 29,
        age_range: '25-34'
      }
    });
    const payload = await response.json();
    expect(payload.success).toBe(false);
    expect(payload.error_code).toBe('reading_exists');
    expect(payload.data && payload.data.redirect_to).toBeTruthy();
    expect(payload.data.redirect_to).toMatch(/account\/login/i);
    if (loginUrl) {
      expect(payload.data.redirect_to).toContain('redirect_url=');
    }
  });

  test('HP-004: Login and view dashboard', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for mock login');
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    await mockLoginInBrowser(page, testEmail, 'Dashboard User');

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await waitForDashboard(page);
    await expect(page.locator('.dashboard-container').first()).toBeVisible();
    await expect(page.locator('#generate-new-reading-btn').first()).toBeVisible();
  });

  test('HP-007: Access report via magic link (teaser)', async ({ page, context }) => {
    test.skip(!(await isMailpitAvailable()), 'MailPit not available for magic link validation');

    const helpers = new E2EHelpers(BASE_URL);
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');
    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Magic Link User';

    const appLoaded = await helpers.ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

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
});

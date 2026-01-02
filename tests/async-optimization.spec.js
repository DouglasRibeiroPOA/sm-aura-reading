// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');

process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

const DEFAULT_BASE_URL = 'https://sm-aura-reading.local/';
const BASE_URL = process.env.E2E_BASE_URL || DEFAULT_BASE_URL;
const API_BASE = new URL('/wp-json', BASE_URL).toString().replace(/\/$/, '');
const MAILPIT_BASE_URL = process.env.MAILPIT_BASE_URL || '';
const USE_LIVE_OPENAI = process.env.E2E_LIVE_OPENAI === '1';
const MAX_REPORT_SECONDS = Number.parseFloat(process.env.E2E_MAX_REPORT_SECONDS || '0');
const MAX_EMAIL_SECONDS = Number.parseFloat(process.env.E2E_MAX_EMAIL_SECONDS || '0');
const REPORT_WAIT_MS = USE_LIVE_OPENAI ? 120000 : 30000;

const TEST_IMAGE = path.resolve(__dirname, '../assets/test-palm.png');
const NON_HAND_IMAGE = path.resolve(__dirname, '../assets/test-non-hand.png');

function buildReportUrl(leadId) {
  const url = new URL(BASE_URL);
  url.searchParams.set('sm_report', '1');
  if (leadId) {
    url.searchParams.set('lead_id', leadId);
  }
  return url.toString();
}

function generateTestEmail() {
  const timestamp = Date.now();
  const random = Math.floor(Math.random() * 1000);
  return `test-${timestamp}-${random}@example.com`;
}

async function ensureAppLoaded(page) {
  await page.goto(BASE_URL);
  await page.waitForLoadState('domcontentloaded');
  const hasApp = await page.locator('#app-content').isVisible().catch(() => false);
  if (hasApp) {
    return true;
  }
  const hasDashboard = await page.locator('.dashboard-container').isVisible().catch(() => false);
  return hasDashboard;
}

async function getOtp(email) {
  const response = await fetch(`${API_BASE}/soulmirror-test/v1/get-otp?email=${encodeURIComponent(email)}`);
  if (!response.ok) {
    throw new Error(`Failed to get OTP: ${response.statusText}`);
  }
  const data = await response.json();
  return data.otp;
}

async function mockLogin(email, name = 'Test User', accountId = '') {
  const params = new URLSearchParams({ email, name });
  if (accountId) {
    params.set('account_id', accountId);
  }
  const response = await fetch(`${API_BASE}/soulmirror-test/v1/mock-login?${params.toString()}`);
  if (!response.ok) {
    throw new Error(`Mock login failed: ${response.statusText}`);
  }
  return response.json();
}

async function mockLoginInBrowser(page, email, name = 'Test User', accountId = '') {
  const params = new URLSearchParams({ email, name });
  if (accountId) {
    params.set('account_id', accountId);
  }
  const response = await page.request.get(`${API_BASE}/soulmirror-test/v1/mock-login?${params.toString()}`);
  if (!response.ok()) {
    throw new Error(`Mock login failed: ${response.status()}`);
  }
  return response.json();
}

async function seedReading(email, name = 'Test User', accountId = '') {
  const response = await fetch(`${API_BASE}/soulmirror-test/v1/seed-reading`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, name, account_id: accountId }),
  });
  if (!response.ok) {
    throw new Error(`Failed to seed reading: ${response.statusText}`);
  }
  return response.json();
}

async function waitForReport(page) {
  const selector = '#aura-reading-result, #palm-reading-result, .reading-result-container, .report-container';
  await page.waitForSelector(selector, { timeout: REPORT_WAIT_MS });
  return page.locator(selector);
}

async function fillLeadCapture(page, testEmail, testName) {
  const nextBtn = page.locator('#next-btn');
  const welcomeEmailInput = page.locator('.welcome-form input[name="email"]');
  const welcomeContinueBtn = page.locator('.welcome-form button[type="submit"]');

  const leadCaptureVisible = await page.locator('select[name="identity"]').isVisible().catch(() => false);
  if (!leadCaptureVisible) {
    if (await welcomeEmailInput.isVisible().catch(() => false)) {
      await welcomeEmailInput.fill(testEmail);
    }
    if (await welcomeContinueBtn.isVisible().catch(() => false)) {
      await welcomeContinueBtn.click();
    } else if (await nextBtn.isVisible().catch(() => false)) {
      await nextBtn.click();
    }
    await page.waitForSelector('select[name="identity"]', { timeout: 10000 });
  }

  const emailInput = page.locator('input[name="email"]');
  const nameInput = page.locator('input.form-input[type="text"]').first();
  const identitySelect = page.locator('select[name="identity"]');
  const ageInput = page.locator('input[type="number"]');
  const gdprCheckbox = page.locator('.checkbox-custom-input');

  if (await emailInput.isVisible().catch(() => false)) {
    await emailInput.fill(testEmail);
  }
  if (await nameInput.isVisible().catch(() => false)) {
    await nameInput.fill(testName);
  }
  if (await identitySelect.isVisible().catch(() => false)) {
    await identitySelect.selectOption('prefer-not');
  }
  if (await ageInput.isVisible().catch(() => false)) {
    await ageInput.fill('29');
  }
  if (await gdprCheckbox.isVisible().catch(() => false)) {
    await gdprCheckbox.click();
  }

  if (await nextBtn.isVisible().catch(() => false)) {
    await nextBtn.click();
  }

  await page.waitForSelector('.code-input', { timeout: 10000 });
}

async function enterOtp(page, testEmail) {
  const otpInputs = page.locator('.code-input');
  await otpInputs.first().waitFor({ state: 'visible', timeout: 15000 });

  let otpDigits = null;
  const demoHint = page.locator('text=For demo purposes');
  if (await demoHint.isVisible().catch(() => false)) {
    otpDigits = ['1', '2', '3', '4'];
  } else {
    try {
      const otp = await getOtp(testEmail);
      otpDigits = otp.toString().split('');
    } catch (error) {
      otpDigits = ['1', '2', '3', '4'];
    }
  }

  const inputCount = await otpInputs.count();
  for (let i = 0; i < Math.min(inputCount, otpDigits.length); i++) {
    await otpInputs.nth(i).fill(otpDigits[i]);
  }

  const nextBtn = page.locator('#next-btn');
  if (await nextBtn.isVisible().catch(() => false)) {
    await nextBtn.click();
  }
  await page.waitForTimeout(2000);

  const invalidCode = page.locator('text=Invalid code');
  if (await invalidCode.isVisible().catch(() => false)) {
    const fallbackDigits = ['1', '2', '3', '4'];
    for (let i = 0; i < Math.min(inputCount, fallbackDigits.length); i++) {
      await otpInputs.nth(i).fill(fallbackDigits[i]);
    }
    if (await nextBtn.isVisible().catch(() => false)) {
      await nextBtn.click();
    }
    await page.waitForTimeout(2000);
  }
}

async function uploadPalmPhoto(page, imagePath) {
  await page.waitForSelector('#photo-upload-input', { timeout: 30000, state: 'attached' });
  const fileInput = page.locator('#photo-upload-input');
  await fileInput.setInputFiles(imagePath);
  await page.waitForTimeout(1500);
  const usePhotoBtn = page.locator('#use-photo-btn');
  if (await usePhotoBtn.isVisible().catch(() => false)) {
    await usePhotoBtn.click();
  }
  await page.waitForTimeout(1500);
}

async function completeQuiz(page) {
  const nextBtn = page.locator('#next-btn');
  for (let i = 0; i < 5; i++) {
    const optionBtn = page.locator('.option-btn').first();
    const ratingBtn = page.locator('.rating-btn').first();
    const textArea = page.locator('textarea.form-textarea').first();

    if (await optionBtn.isVisible().catch(() => false)) {
      await optionBtn.click();
    } else if (await ratingBtn.isVisible().catch(() => false)) {
      await ratingBtn.click();
    } else if (await textArea.isVisible().catch(() => false)) {
      await textArea.fill('Test response');
    }

    if (await nextBtn.isVisible().catch(() => false)) {
      await nextBtn.click();
      await page.waitForTimeout(800);
    }

    const reportVisible = await page.locator('#aura-reading-result, #palm-reading-result').isVisible().catch(() => false);
    if (reportVisible) {
      break;
    }
  }
}

async function waitForMailpitEmail(recipient, subject) {
  if (!MAILPIT_BASE_URL) {
    console.log('MAILPIT_BASE_URL not set; skipping MailPit assertions.');
    return null;
  }

  for (let attempt = 0; attempt < 10; attempt++) {
    const response = await fetch(`${MAILPIT_BASE_URL.replace(/\/$/, '')}/api/v1/messages`);
    if (response.ok) {
      const data = await response.json();
      const messages = Array.isArray(data.messages) ? data.messages : [];
      const match = messages.find((msg) => {
        const to = Array.isArray(msg.To) ? msg.To.join(',') : msg.To || '';
        const subj = msg.Subject || '';
        return to.includes(recipient) && subj.includes(subject);
      });
      if (match) {
        return match;
      }
    }
    await new Promise((resolve) => setTimeout(resolve, 1500));
  }

  throw new Error(`MailPit email not found: ${recipient} - ${subject}`);
}

test.describe('Async Optimization - Core Scenarios', () => {
  test.describe.configure({ timeout: USE_LIVE_OPENAI ? 240000 : 180000 });
  test.slow();

  test.beforeEach(async ({ page }) => {
    page.on('console', (msg) => {
      const text = msg.text();
      if (text.includes('[SM') || text.includes('ERROR')) {
        console.log(`📋 ${text}`);
      }
    });
    page.on('response', (response) => {
      const status = response.status();
      if (status >= 400 && response.url().includes('/wp-json/')) {
        console.log(`🚨 API ${status}: ${response.url()}`);
      }
    });
  });

  test('Async teaser generation completes and emails notification', async ({ page }) => {
    const testEmail = generateTestEmail();
    const testName = 'Async Test User';

    const appLoaded = await ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await fillLeadCapture(page, testEmail, testName);
    await enterOtp(page, testEmail);
    await uploadPalmPhoto(page, TEST_IMAGE);
    await completeQuiz(page);

    const reportStart = Date.now();
    await waitForReport(page);
    const reportSeconds = (Date.now() - reportStart) / 1000;
    console.log(`⏱️ Teaser report ready in ${reportSeconds.toFixed(1)}s`);
    if (MAX_REPORT_SECONDS > 0) {
      expect(reportSeconds).toBeLessThanOrEqual(MAX_REPORT_SECONDS);
    }

    const emailStart = Date.now();
    await waitForMailpitEmail(testEmail, 'Your Aura Reading Is Ready');
    const emailSeconds = (Date.now() - emailStart) / 1000;
    if (MAILPIT_BASE_URL) {
      console.log(`📨 Teaser email received in ${emailSeconds.toFixed(1)}s`);
      if (MAX_EMAIL_SECONDS > 0) {
        expect(emailSeconds).toBeLessThanOrEqual(MAX_EMAIL_SECONDS);
      }
    }
  });

  test('Paid async generation (new) completes from dashboard flow', async ({ page }) => {
    const testEmail = generateTestEmail();
    await mockLoginInBrowser(page, testEmail, 'Paid Test User');

    const appLoaded = await ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await page.waitForSelector('#generate-new-reading-btn', { timeout: 15000 });
    await page.click('#generate-new-reading-btn');

    await uploadPalmPhoto(page, TEST_IMAGE);
    await completeQuiz(page);
    const reportStart = Date.now();
    await waitForReport(page);
    const reportSeconds = (Date.now() - reportStart) / 1000;
    console.log(`⏱️ Paid report ready in ${reportSeconds.toFixed(1)}s`);
    if (MAX_REPORT_SECONDS > 0) {
      expect(reportSeconds).toBeLessThanOrEqual(MAX_REPORT_SECONDS);
    }
  });

  test('Paid upgrade from teaser removes full-report CTA', async ({ page }) => {
    const testEmail = generateTestEmail();
    const accountId = `test-account-${Date.now()}`;
    await mockLoginInBrowser(page, testEmail, 'Upgrade Test User', accountId);

    const seeded = await seedReading(testEmail, 'Upgrade Test User', accountId);
    const reportUrl = buildReportUrl(seeded.lead_id);

    await page.goto(reportUrl);
    await waitForReport(page);

    const upgradeBtn = page.locator('[data-report-cta]');
    await upgradeBtn.waitFor({ state: 'visible', timeout: 10000 });
    await upgradeBtn.click();

    await page.waitForTimeout(3000);
    await expect(page.locator('[data-report-cta]')).toHaveCount(0);
  });

  test('Vision failure shows resubmit UI and no report', async ({ page }) => {
    const testEmail = generateTestEmail();
    const testName = 'Invalid Image User';

    const appLoaded = await ensureAppLoaded(page);
    expect(appLoaded).toBe(true);

    await fillLeadCapture(page, testEmail, testName);
    await enterOtp(page, testEmail);
    await uploadPalmPhoto(page, NON_HAND_IMAGE);
    await completeQuiz(page);

    const errorActions = page.locator('.loading-error-actions');
    await errorActions.waitFor({ state: 'visible', timeout: 20000 });

    await expect(page.locator('#aura-reading-result')).toHaveCount(0);
    await expect(page.locator('.loading-error-actions .btn-primary')).toBeVisible();

    await waitForMailpitEmail(testEmail, 'Your Reading Generation Failed');
  });
});

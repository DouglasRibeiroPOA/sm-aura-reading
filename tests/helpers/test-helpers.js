/**
 * Shared Playwright Test Helpers
 *
 * Reusable utility functions for E2E tests across all test suites.
 *
 * Usage:
 *   const { E2EHelpers, setupMonitoring } = require('../../helpers/test-helpers');
 *   const helpers = new E2EHelpers(BASE_URL);
 */

// Allow fetch calls to local HTTPS with self-signed certs
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

const MailPitHelper = require('./mailpit-helper');
const fs = require('fs');
const path = require('path');

class E2EHelpers {
  /**
   * Initialize helper with base URL
   *
   * @param {string} baseUrl - Base URL for the application (e.g., https://sm-aura-reading.local/)
   */
  constructor(baseUrl) {
    this.baseUrl = baseUrl || 'https://sm-aura-reading.local/';
    this.apiBase = new URL('/wp-json', this.baseUrl).toString().replace(/\/$/, '');
    this.resolvedBaseUrl = this.baseUrl;
  }

  /**
   * Build dev mode headers for test helper requests.
   *
   * @returns {Object} Headers object.
   */
  getDevModeHeaders() {
    if (process.env.E2E_LIVE_OPENAI === '1') {
      return {};
    }
    const mode = process.env.E2E_DEV_MODE || 'dev_openai_only';
    return mode ? { 'X-SM-Dev-Mode': mode } : {};
  }

  /**
   * Build report URL with parameters
   *
   * @param {string|number} leadId - Lead ID
   * @param {Object} params - Additional URL parameters
   * @returns {string} Full report URL
   */
  buildReportUrl(leadId, params = {}) {
    const url = new URL(this.getBaseUrl());
    url.searchParams.set('sm_report', '1');
    if (leadId) {
      url.searchParams.set('lead_id', leadId);
    }
    Object.entries(params).forEach(([key, value]) => {
      url.searchParams.set(key, value);
    });
    return url.toString();
  }

  /**
   * Get resolved base URL
   */
  getBaseUrl() {
    return this.resolvedBaseUrl || this.baseUrl;
  }

  /**
   * Get base pathname
   */
  getBasePathname() {
    return new URL(this.getBaseUrl()).pathname.replace(/\/$/, '') || '/';
  }

  /**
   * Ensure app is loaded by trying common paths
   *
   * @param {Page} page - Playwright page object
   * @returns {Promise<boolean>} True if app found
   */
  async ensureAppLoaded(page) {
    const origin = new URL(this.baseUrl).origin;
    const basePath = new URL(this.baseUrl).pathname;
    const candidates = [
      basePath,
      '/aura-reading',
      '/aura-reading/',
      '/aura/',
      '/'
    ];
    const seen = new Set();

    for (const path of candidates) {
      const normalized = path || '/';
      if (seen.has(normalized)) {
        continue;
      }
      seen.add(normalized);
      const target = origin + normalized;
      await page.goto(target);
      await page.waitForLoadState('domcontentloaded');
      const hasApp = await page
        .waitForSelector('#app-content', { timeout: 3000, state: 'visible' })
        .then(() => true)
        .catch(() => false);
      const hasDashboard = await page
        .waitForSelector('.dashboard-container, #generate-new-reading-btn', { timeout: 3000, state: 'visible' })
        .then(() => true)
        .catch(() => false);
      if (hasApp || hasDashboard) {
        this.resolvedBaseUrl = target;
        console.log(`✅ App found at: ${target}`);
        return true;
      }
    }

    await this.logPageDiagnostics(page, 'App not found on candidate paths');
    return false;
  }

  /**
   * Generate unique test email
   *
   * @returns {string} Unique test email
   */
  static generateTestEmail() {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 10000);
    return `test-${timestamp}-${random}@example.com`;
  }

  /**
   * Get OTP code from database via test helper endpoint
   *
   * @param {string} email - User email
   * @returns {Promise<string>} OTP code
   */
  async getOTP(email) {
    const response = await fetch(`${this.apiBase}/soulmirror-test/v1/get-otp?email=${encodeURIComponent(email)}`, {
      headers: this.getDevModeHeaders()
    });
    if (!response.ok) {
      throw new Error(`Failed to get OTP: ${response.statusText}`);
    }
    const data = await response.json();
    return data.otp;
  }

  /**
   * Set DevMode mode (DevMode only).
   *
   * @param {string} mode - DevMode mode key.
   * @returns {Promise<Object>} Response payload.
   */
  async setDevMode(mode) {
    const response = await fetch(`${this.apiBase}/soulmirror-test/v1/devmode`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...this.getDevModeHeaders() },
      body: JSON.stringify({ mode })
    });
    if (!response.ok) {
      throw new Error(`Failed to set DevMode: ${response.statusText}`);
    }
    return response.json();
  }

  /**
   * Get current DevMode mode.
   *
   * @returns {Promise<Object>} Response payload.
   */
  async getDevMode() {
    const response = await fetch(`${this.apiBase}/soulmirror-test/v1/devmode`, {
      headers: this.getDevModeHeaders()
    });
    if (!response.ok) {
      throw new Error(`Failed to get DevMode: ${response.statusText}`);
    }
    return response.json();
  }

  /**
   * Fetch recent debug.log lines.
   *
   * @param {Object} options - Query options.
   * @param {string} [options.contains] - Filter substring.
   * @param {number} [options.lines] - Line limit.
   * @returns {Promise<Object>} Response payload.
   */
  async getLogs(options = {}) {
    const params = new URLSearchParams();
    if (options.contains) {
      params.set('contains', options.contains);
    }
    if (options.lines) {
      params.set('lines', String(options.lines));
    }
    const url = `${this.apiBase}/soulmirror-test/v1/logs${params.toString() ? `?${params.toString()}` : ''}`;
    const response = await fetch(url, { headers: this.getDevModeHeaders() });
    if (!response.ok) {
      throw new Error(`Failed to fetch logs: ${response.statusText}`);
    }
    return response.json();
  }

  /**
   * Enter OTP code into input fields
   *
   * @param {Page} page - Playwright page object
   * @param {string} email - User email (to retrieve OTP)
   * @returns {Promise<void>}
   */
  async enterOtp(page, email) {
    const otpInputs = page.locator('.code-input');
    await otpInputs.first().waitFor({ state: 'visible', timeout: 15000 });

    let otpDigits = null;
    const otpSource = (process.env.E2E_OTP_SOURCE || '').toLowerCase();
    const forceMailPit = otpSource === 'mailpit';
    const useMailPit = forceMailPit || Boolean(process.env.MAILPIT_URL);
    const demoHint = page.locator('text=For demo purposes');
    if (!useMailPit && await demoHint.isVisible().catch(() => false)) {
      otpDigits = ['1', '2', '3', '4'];
    } else if (useMailPit) {
      const mailpit = new MailPitHelper(process.env.MAILPIT_URL || 'http://localhost:8025');
      const otp = await mailpit.extractOTP(email, 15000);
      if (!otp) {
        if (forceMailPit) {
          throw new Error('MailPit OTP not found.');
        }
        console.warn('⚠️  MailPit OTP not found, falling back to test helper');
      } else {
        otpDigits = otp.toString().replace(/\D/g, '').split('');
        console.log(`✅ OTP retrieved via MailPit: ${otp}`);
      }
    }
    if (!otpDigits) {
      try {
        const otp = await this.getOTP(email);
        otpDigits = otp.toString().split('');
        console.log(`✅ OTP retrieved: ${otp}`);
      } catch (error) {
        try {
          const otp = await this.getOTPFromPage(page, email);
          otpDigits = otp.toString().split('');
          console.log(`✅ OTP retrieved via page: ${otp}`);
        } catch (pageError) {
          console.warn('⚠️  Failed to retrieve OTP, using demo code');
          otpDigits = ['1', '2', '3', '4'];
        }
      }
    }

    const inputCount = await otpInputs.count();
    if (otpDigits.length < inputCount) {
      throw new Error(`OTP length ${otpDigits.length} does not match input count ${inputCount}.`);
    }
    for (let i = 0; i < inputCount; i++) {
      const target = otpInputs.nth(i);
      await target.click();
      await target.fill('');
      await target.type(otpDigits[i], { delay: 50 });
    }

    const submitBtn = page.locator('#next-btn, button:has-text("Continue"), button:has-text("Verify"), button:has-text("Submit")');
    if (await submitBtn.isVisible().catch(() => false)) {
      await submitBtn.click();
    } else {
      await otpInputs.nth(Math.max(0, inputCount - 1)).press('Enter').catch(() => {});
    }

    let advanced = await page
      .waitForSelector('#upload-btn', { timeout: 20000, state: 'visible' })
      .then(() => true)
      .catch(() => false);
    if (!advanced) {
      advanced = await page
        .waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 10000, state: 'attached' })
        .then(() => true)
        .catch(() => false);
    }

    if (!advanced) {
      // Retry: some OTP widgets only accept full code in first input.
      if (await otpInputs.first().isVisible().catch(() => false)) {
        const firstInput = otpInputs.first();
        await firstInput.click();
        await firstInput.fill('');
        await firstInput.type(otpDigits.slice(0, inputCount).join(''), { delay: 30 });
        if (await submitBtn.isVisible().catch(() => false)) {
          await submitBtn.click({ force: true });
        } else {
          await firstInput.press('Enter').catch(() => {});
        }
        advanced = await page
          .waitForSelector('#upload-btn', { timeout: 10000, state: 'visible' })
          .then(() => true)
          .catch(() => false);
        if (!advanced) {
          advanced = await page
            .waitForSelector('#photo-upload-input, input[type="file"]', { timeout: 10000, state: 'attached' })
            .then(() => true)
            .catch(() => false);
        }
      }
    }

    if (!advanced) {
      const errorToast = page.locator('text=/invalid (verification )?code|invalid code/i');
      if (await errorToast.isVisible().catch(() => false)) {
        throw new Error('OTP verification failed (invalid code).');
      }
      const debugDir = path.resolve(process.cwd(), 'test-results', 'debug');
      fs.mkdirSync(debugDir, { recursive: true });
      const screenshotPath = path.join(debugDir, `otp-stuck-${Date.now()}.png`);
      await page.screenshot({ path: screenshotPath, fullPage: true });
      const currentUrl = page.url();
      const uploadState = await page.evaluate(() => {
        const upload = document.querySelector('#upload-btn');
        const input = document.querySelector('#photo-upload-input');
        return {
          uploadVisible: upload ? !!(upload.offsetWidth || upload.offsetHeight) : false,
          uploadDisabled: upload ? upload.hasAttribute('disabled') : null,
          inputPresent: !!input
        };
      }).catch(() => null);
      const lastValue = await otpInputs.nth(Math.max(0, inputCount - 1)).inputValue().catch(() => '');
      const continueDisabled = await submitBtn.isDisabled().catch(() => false);
      console.warn(`⚠️  OTP did not advance. url=${currentUrl} disabled=${continueDisabled} last=${lastValue} upload=${JSON.stringify(uploadState)} screenshot=${screenshotPath}`);
      throw new Error('OTP verification did not advance to photo upload.');
    }
  }

  /**
   * Fetch OTP using the browser context (helps when Node cannot reach the local host).
   *
   * @param {Page} page - Playwright page object
   * @param {string} email - User email
   * @returns {Promise<string>} OTP code
   */
  async getOTPFromPage(page, email) {
    if (!page) {
      throw new Error('Page context missing for OTP fetch.');
    }
    const devHeaders = this.getDevModeHeaders();
    return page.evaluate(async ({ apiBase, emailValue, headers }) => {
      const response = await fetch(`${apiBase}/soulmirror-test/v1/get-otp?email=${encodeURIComponent(emailValue)}`, {
        headers
      });
      if (!response.ok) {
        throw new Error(`Failed to get OTP: ${response.statusText}`);
      }
      const data = await response.json();
      return data.otp;
    }, { apiBase: this.apiBase, emailValue: email, headers: devHeaders });
  }

  /**
   * Get lead by email from database
   *
   * @param {string} email - User email
   * @returns {Promise<Object>} Lead object
   */
  async getLeadByEmail(email) {
    const response = await fetch(`${this.apiBase}/soulmirror-test/v1/get-lead?email=${encodeURIComponent(email)}`, {
      headers: this.getDevModeHeaders()
    });
    if (!response.ok) {
      throw new Error(`Failed to get lead: ${response.statusText}`);
    }
    const data = await response.json();
    return data.lead;
  }

  /**
   * Wait for lead to be created in database
   *
   * @param {string} email - User email
   * @param {number} attempts - Number of retry attempts
   * @param {number} delayMs - Delay between attempts
   * @returns {Promise<Object|null>} Lead object or null
   */
  async waitForLead(email, attempts = 10, delayMs = 1000) {
    for (let i = 0; i < attempts; i++) {
      try {
        return await this.getLeadByEmail(email);
      } catch (error) {
        if (i === attempts - 1) {
          throw error;
        }
        await this.sleep(delayMs);
      }
    }
    return null;
  }

  /**
   * Seed a complete reading for testing (bypass form flow)
   *
   * @param {string} email - User email
   * @param {string} name - User name
   * @param {string} readingType - Reading type (aura_teaser or aura_full)
   * @param {string} accountId - Optional account ID
   * @returns {Promise<Object>} Seeded reading data
   */
  async seedReading(email, name = 'Test User', readingType = 'aura_teaser', accountId = '') {
    const payload = {
      email,
      name,
      reading_type: readingType,
      account_id: accountId
    };
    const attempts = 3;
    let lastError = null;

    for (let i = 0; i < attempts; i++) {
      let response;
      try {
        response = await fetch(`${this.apiBase}/soulmirror-test/v1/seed-reading`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            ...this.getDevModeHeaders(),
          },
          body: JSON.stringify(payload),
        });
      } catch (error) {
        lastError = error;
        if (i < attempts - 1) {
          await this.sleep(500 * (i + 1));
          continue;
        }
        throw error;
      }

      if (!response.ok) {
        const status = response.status;
        lastError = new Error(`Failed to seed reading: ${response.statusText}`);
        if (status >= 500 && i < attempts - 1) {
          await this.sleep(500 * (i + 1));
          continue;
        }
        throw lastError;
      }

      const data = await response.json();
      console.log(`✅ Reading seeded for ${email}: lead_id=${data.lead_id}`);
      return data;
    }

    throw lastError || new Error('Failed to seed reading after retries.');
  }

  /**
   * Cleanup test data
   *
   * @returns {Promise<boolean>} True if successful
   */
  async cleanupTestData() {
    const response = await fetch(`${this.apiBase}/soulmirror-test/v1/cleanup`, {
      method: 'POST',
      headers: this.getDevModeHeaders(),
    });
    if (!response.ok) {
      console.warn(`⚠️  Cleanup warning: ${response.statusText}`);
    }
    return response.ok;
  }

  /**
   * Wait for element with timeout and retry
   *
   * @param {Page} page - Playwright page object
   * @param {string} selector - CSS selector
   * @param {number} timeout - Timeout in ms
   * @returns {Promise<boolean>} True if found
   */
  async waitForElement(page, selector, timeout = 10000) {
    try {
      await page.waitForSelector(selector, { timeout, state: 'visible' });
      return true;
    } catch (e) {
      console.log(`⚠️  Element ${selector} not found within ${timeout}ms`);
      return false;
    }
  }

  /**
   * Get session state from browser sessionStorage
   *
   * @param {Page} page - Playwright page object
   * @returns {Promise<Object>} Session state object
   */
  async getSessionState(page) {
    return await page.evaluate(() => {
      const keys = [
        'sm_reading_loaded',
        'sm_reading_lead_id',
        'sm_reading_token',
        'sm_existing_reading_id',
        'sm_flow_step_id',
        'sm_email',
        'sm_unlocked_sections',
      ];
      const state = {};
      keys.forEach(key => {
        if (window.smSessionGet) {
          state[key] = window.smSessionGet(key);
        } else {
          state[key] = sessionStorage.getItem(key);
        }
      });
      return state;
    });
  }

  /**
   * Clear session state in browser
   *
   * @param {Page} page - Playwright page object
   * @returns {Promise<void>}
   */
  async clearSessionState(page) {
    await page.evaluate(() => {
      sessionStorage.clear();
    });
    console.log('🧹 Session state cleared');
  }

  /**
   * Take screenshot with descriptive name
   *
   * @param {Page} page - Playwright page object
   * @param {string} name - Screenshot description
   * @param {string} step - Step number (e.g., '01', '02')
   * @returns {Promise<void>}
   */
  async takeScreenshot(page, name, step) {
    const filename = `test-${step}-${name}.png`;
    await page.screenshot({ path: `test-results/${filename}`, fullPage: true });
    console.log(`📸 Screenshot: ${filename}`);
  }

  /**
   * Log current state (URL + session)
   *
   * @param {Page} page - Playwright page object
   * @param {string} label - State label
   * @returns {Promise<void>}
   */
  async logState(page, label) {
    const url = page.url();
    const sessionState = await this.getSessionState(page);
    console.log(`\n${'='.repeat(70)}`);
    console.log(`📊 STATE: ${label}`);
    console.log(`${'='.repeat(70)}`);
    console.log(`URL: ${url}`);
    console.log(`Session:`, JSON.stringify(sessionState, null, 2));
    console.log(`${'='.repeat(70)}\n`);
  }

  /**
   * Log page diagnostics (title, text snippet)
   *
   * @param {Page} page - Playwright page object
   * @param {string} label - Diagnostic label
   * @returns {Promise<void>}
   */
  async logPageDiagnostics(page, label) {
    const title = await page.title();
    const textSnippet = await page.evaluate(() => document.body.innerText.slice(0, 200));
    console.log(`\n[Diagnostics] ${label}`);
    console.log(`Title: ${title}`);
    console.log(`Snippet: ${textSnippet}`);
  }

  /**
   * Pick first available free unlock button
   *
   * @param {Page} page - Playwright page object
   * @param {Array<string>} keys - Section keys to try
   * @returns {Promise<Locator>} Unlock button locator
   */
  async pickFreeUnlockButton(page, keys = ['love', 'challenges', 'phase', 'timeline', 'guidance']) {
    for (const key of keys) {
      const locator = page.locator(`.btn-unlock[data-unlock="${key}"]`);
      if (await locator.isVisible().catch(() => false)) {
        return locator;
      }
    }
    return page.locator('.btn-unlock:not(.btn-premium)').first();
  }

  /**
   * Upload image file
   *
   * @param {Page} page - Playwright page object
   * @param {string} imagePath - Path to image file
   * @returns {Promise<void>}
   */
  async uploadImage(page, imagePath) {
    const fileInput = await page.locator('input[type="file"]');
    await fileInput.setInputFiles(imagePath);
    console.log(`📤 Image uploaded: ${imagePath}`);

    // Wait for preview to appear
    await page.waitForSelector('[data-testid="photo-preview"]', { timeout: 5000 }).catch(() => {
      console.warn('⚠️  Photo preview did not appear');
    });
  }

  /**
   * Sleep/delay for specified duration
   *
   * @param {number} ms - Duration in milliseconds
   * @returns {Promise<void>}
   */
  async sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Check if DevMode is enabled
   *
   * @returns {Promise<boolean>} True if DevMode enabled
   */
  async isDevModeEnabled(page) {
    const url = `${this.apiBase}/soulmirror-test/v1/get-otp?email=test@example.com`;
    if (page && page.request) {
      try {
        const response = await page.request.get(url);
        const status = response.status();
        if (status !== 404) {
          return true;
        }
        let data = null;
        try {
          data = await response.json();
        } catch {
          data = null;
        }
        if (data && data.code && data.code !== 'rest_no_route') {
          return true;
        }
        return false;
      } catch (error) {
        console.warn(`⚠️  DevMode check via page failed: ${error.message}`);
      }
    }
    try {
      const response = await fetch(url, { headers: this.getDevModeHeaders() });
      if (response.status !== 404) {
        return true;
      }
      let data = null;
      try {
        data = await response.json();
      } catch {
        data = null;
      }
      if (data && data.code && data.code !== 'rest_no_route') {
        return true;
      }
      return false;
    } catch {
      return false;
    }
  }
}

/**
 * Setup console and network monitoring
 *
 * Captures console logs and API calls for debugging.
 *
 * @param {Page} page - Playwright page object
 * @param {Array} logs - Array to store console logs
 * @param {Array} apiCalls - Array to store API call data
 * @returns {void}
 */
function setupMonitoring(page, logs, apiCalls) {
  page.on('console', msg => {
    const text = msg.text();
    logs.push(text);
    if (text.includes('[SM') || text.includes('ERROR') || text.includes('500')) {
      console.log('📋 ' + text.substring(0, 120));
    }
  });

  page.on('response', async response => {
    const url = response.url();
    const status = response.status();

    if (url.includes('/wp-json/soulmirror/v1/')) {
      const endpoint = url.split('/wp-json/soulmirror/v1/')[1];
      let body = null;
      try {
        body = await response.json();
      } catch (e) {
        // Not JSON
      }

      apiCalls.push({ endpoint, status, url, body });

      if (status >= 400) {
        console.log(`🚨 API Error: ${endpoint} → ${status}`);
      } else {
        console.log(`✅ API Call: ${endpoint} → ${status}`);
      }
    }
  });
}

/**
 * Fill OTP input fields with provided code
 *
 * @param {Page} page - Playwright page object
 * @param {string} otpCode - 6-digit OTP code
 * @returns {Promise<void>}
 */
async function fillOTP(page, otpCode) {
  const otpInputs = page.locator('.code-input');
  await otpInputs.first().waitFor({ state: 'visible', timeout: 15000 });

  const otpDigits = otpCode.toString().split('');
  const inputCount = await otpInputs.count();

  for (let i = 0; i < Math.min(inputCount, otpDigits.length); i++) {
    await otpInputs.nth(i).fill(otpDigits[i]);
  }

  console.log(`✅ OTP entered: ${otpCode}`);
}

module.exports = {
  E2EHelpers,
  setupMonitoring,
  fillOTP,
};

/**
 * Example usage:
 *
 * const { E2EHelpers, setupMonitoring } = require('../../helpers/test-helpers');
 *
 * test('My test', async ({ page }) => {
 *   const helpers = new E2EHelpers('https://sm-aura-reading.local/');
 *   const consoleLogs = [];
 *   const apiCalls = [];
 *
 *   setupMonitoring(page, consoleLogs, apiCalls);
 *
 *   // Generate test email
 *   const testEmail = E2EHelpers.generateTestEmail();
 *
 *   // Load app
 *   await helpers.ensureAppLoaded(page);
 *   await helpers.takeScreenshot(page, 'welcome', '01');
 *   await helpers.logState(page, 'Welcome Page');
 *
 *   // Enter OTP
 *   await helpers.enterOtp(page, testEmail);
 *
 *   // Seed reading (bypass form)
 *   const seeded = await helpers.seedReading(testEmail, 'Test User');
 *   await page.goto(helpers.buildReportUrl(seeded.lead_id));
 *
 *   // Cleanup
 *   await helpers.cleanupTestData();
 * });
 */

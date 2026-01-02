// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');

// Allow fetch calls to the local HTTPS site with self-signed certs.
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

/**
 * E2E Full Flow Tests
 *
 * Complete end-to-end user journey from welcome page to report unlock testing.
 * These tests use automated email generation, OTP retrieval, and complete the entire flow.
 */

// Configuration
const DEFAULT_BASE_URL = 'https://sm-aura-reading.local/';
const BASE_URL = process.env.E2E_BASE_URL || DEFAULT_BASE_URL;
const API_BASE = new URL('/wp-json', BASE_URL).toString().replace(/\/$/, '');
const USE_LIVE_OPENAI = process.env.E2E_LIVE_OPENAI === '1';
const E2E_TEST_TIMEOUT = USE_LIVE_OPENAI ? 240000 : 60000;

// Test helpers
class E2EHelpers {
  static resolvedBaseUrl = BASE_URL;

  static buildReportUrl(leadId) {
    const url = new URL(E2EHelpers.getBaseUrl());
    url.searchParams.set('sm_report', '1');
    if (leadId) {
      url.searchParams.set('lead_id', leadId);
    }
    return url.toString();
  }

  static getBaseUrl() {
    return E2EHelpers.resolvedBaseUrl || BASE_URL;
  }

  static getBasePathname() {
    return new URL(E2EHelpers.getBaseUrl()).pathname.replace(/\/$/, '') || '/';
  }

  static async ensureAppLoaded(page) {
    const origin = new URL(BASE_URL).origin;
    const basePath = new URL(BASE_URL).pathname;
    const candidates = [
      basePath,
      '/aura-reading',
      '/aura-reading/',
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
      const hasApp = await page.locator('#app-content').isVisible().catch(() => false);
      if (hasApp) {
        E2EHelpers.resolvedBaseUrl = target;
        return true;
      }
    }

    await E2EHelpers.logPageDiagnostics(page, 'App not found on candidate paths');
    return false;
  }

  /**
   * Generate a unique test email
   */
  static generateTestEmail() {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 1000);
    return `test-${timestamp}-${random}@example.com`;
  }

  /**
   * Get OTP code from database via test helper endpoint
   */
  static async getOTP(email) {
    const response = await fetch(`${API_BASE}/soulmirror-test/v1/get-otp?email=${encodeURIComponent(email)}`);
    if (!response.ok) {
      throw new Error(`Failed to get OTP: ${response.statusText}`);
    }
    const data = await response.json();
    return data.otp;
  }

  static async enterOtp(page, email) {
    const otpInputs = page.locator('.code-input');
    await otpInputs.first().waitFor({ state: 'visible', timeout: 15000 });

    let otpDigits = null;
    const demoHint = page.locator('text=For demo purposes');
    if (await demoHint.isVisible().catch(() => false)) {
      otpDigits = ['1', '2', '3', '4'];
    } else {
      try {
        const otp = await E2EHelpers.getOTP(email);
        otpDigits = otp.toString().split('');
        console.log(`✅ OTP retrieved: ${otp}`);
      } catch (error) {
        otpDigits = ['1', '2', '3', '4'];
      }
    }

    const inputCount = await otpInputs.count();
    for (let i = 0; i < Math.min(inputCount, otpDigits.length); i++) {
      await otpInputs.nth(i).fill(otpDigits[i]);
    }
  }

  /**
   * Get lead ID by email
   */
  static async getLeadByEmail(email) {
    const response = await fetch(`${API_BASE}/soulmirror-test/v1/get-lead?email=${encodeURIComponent(email)}`);
    if (!response.ok) {
      throw new Error(`Failed to get lead: ${response.statusText}`);
    }
    const data = await response.json();
    return data.lead;
  }

  static async waitForLead(email, attempts = 10, delayMs = 1000) {
    for (let i = 0; i < attempts; i++) {
      try {
        return await this.getLeadByEmail(email);
      } catch (error) {
        if (i === attempts - 1) {
          throw error;
        }
        await new Promise(resolve => setTimeout(resolve, delayMs));
      }
    }
    return null;
  }

  /**
   * Cleanup test data
   */
  static async cleanupTestData() {
    const response = await fetch(`${API_BASE}/soulmirror-test/v1/cleanup`, {
      method: 'POST',
    });
    if (!response.ok) {
      console.warn(`Cleanup warning: ${response.statusText}`);
    }
    return response.ok;
  }

  /**
   * Seed a complete reading for testing
   */
  static async seedReading(email, name = 'Test User') {
    const response = await fetch(`${API_BASE}/soulmirror-test/v1/seed-reading`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email, name }),
    });
    if (!response.ok) {
      throw new Error(`Failed to seed reading: ${response.statusText}`);
    }
    const data = await response.json();
    return data;
  }

  /**
   * Wait for element with timeout and retry
   */
  static async waitForElement(page, selector, timeout = 10000) {
    try {
      await page.waitForSelector(selector, { timeout, state: 'visible' });
      return true;
    } catch (e) {
      console.log(`Element ${selector} not found within ${timeout}ms`);
      return false;
    }
  }

  /**
   * Get session state from browser
   */
  static async getSessionState(page) {
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
        state[key] = sessionStorage.getItem(key);
      });
      return state;
    });
  }

  /**
   * Take screenshot with descriptive name
   */
  static async takeScreenshot(page, name, step) {
    const filename = `e2e-${step}-${name}.png`;
    await page.screenshot({ path: `test-results/${filename}`, fullPage: true });
    console.log(`📸 Screenshot: ${filename}`);
  }

  /**
   * Log current state
   */
  static async logState(page, label) {
    const url = page.url();
    const sessionState = await this.getSessionState(page);
    console.log(`\n${'='.repeat(70)}`);
    console.log(`📊 STATE: ${label}`);
    console.log(`${'='.repeat(70)}`);
    console.log(`URL: ${url}`);
    console.log(`Session:`, JSON.stringify(sessionState, null, 2));
    console.log(`${'='.repeat(70)}\n`);
  }

  static async logPageDiagnostics(page, label) {
    const title = await page.title();
    const textSnippet = await page.evaluate(() => document.body.innerText.slice(0, 200));
    console.log(`\n[Diagnostics] ${label}`);
    console.log(`Title: ${title}`);
    console.log(`Snippet: ${textSnippet}`);
  }

  static async pickFreeUnlockButton(page, keys = ['love', 'challenges', 'phase', 'timeline', 'guidance']) {
    for (const key of keys) {
      const locator = page.locator(`.btn-unlock[data-unlock="${key}"]`);
      if (await locator.isVisible().catch(() => false)) {
        return locator;
      }
    }
    return page.locator('.btn-unlock:not(.btn-premium)').first();
  }
}

// Setup console and network capture
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
      }
    }
  });
}

test.describe('E2E Full Flow - Complete User Journey', () => {
  test.describe.configure({ timeout: E2E_TEST_TIMEOUT });

  test.beforeAll(async () => {
    // Cleanup old test data before running
    console.log('🧹 Cleaning up old test data...');
    await E2EHelpers.cleanupTestData();
  });

  // ========================================
  // TEST 1: Complete Flow - Welcome to Report
  // ========================================
  test('Full flow - Welcome → Lead Capture → OTP → Photo → Quiz → Report', async ({ page }) => {
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'E2E Test User';
    console.log(`\n🎯 Starting E2E test with email: ${testEmail}\n`);

    // STEP 1: Load welcome page
    console.log('📍 STEP 1: Loading welcome page...');
    const appLoaded = await E2EHelpers.ensureAppLoaded(page);
    await page.waitForLoadState('networkidle');
    await E2EHelpers.takeScreenshot(page, 'welcome', '01');
    await E2EHelpers.logState(page, 'Welcome Page Loaded');

    // Verify welcome step is visible
    const welcomeVisible = appLoaded && await E2EHelpers.waitForElement(page, '#app-content');
    if (!welcomeVisible) {
      await E2EHelpers.logPageDiagnostics(page, 'App container missing');
    }
    expect(welcomeVisible).toBe(true);

    const nextBtn = page.locator('#next-btn');
    const welcomeContinueBtn = page.locator('.welcome-form button[type="submit"]');

    // STEP 2: Submit welcome email to reach lead capture
    console.log('\n📍 STEP 2: Submitting welcome email...');
    const leadCaptureVisible = await page.locator('select[name="identity"]').isVisible().catch(() => false);
    if (!leadCaptureVisible) {
      const welcomeEmailInput = page.locator('.welcome-form input[name="email"]');
      if (await welcomeEmailInput.isVisible().catch(() => false)) {
        await welcomeEmailInput.fill(testEmail);
      }
      if (await welcomeContinueBtn.isVisible().catch(() => false)) {
        await welcomeContinueBtn.click();
      } else if (await nextBtn.isVisible().catch(() => false)) {
        await nextBtn.click();
      }
      await page.waitForSelector('select[name="identity"]', { timeout: 10000 }).catch(async () => {
        await E2EHelpers.logPageDiagnostics(page, 'Lead capture form did not appear after welcome submit');
        throw new Error('Lead capture form did not appear after welcome submit');
      });
    } else {
      console.log('ℹ️  Lead capture already visible; skipping welcome submit.');
    }
    await E2EHelpers.takeScreenshot(page, 'lead-capture', '02');
    await E2EHelpers.logState(page, 'Lead Capture Step');

    // STEP 3: Fill lead capture form
    console.log('\n📍 STEP 3: Filling lead capture form...');
    const emailInput = page.locator('input[name="email"]');
    const nameInput = page.locator('input.form-input[type="text"]').first();
    const identitySelect = page.locator('select[name="identity"]');
    const ageInput = page.locator('input[type="number"]');
    const gdprCheckbox = page.locator('.checkbox-custom-input');

    if (await emailInput.isVisible().catch(() => false) && !(await nameInput.isVisible().catch(() => false))) {
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

    await E2EHelpers.takeScreenshot(page, 'form-filled', '03');

    // Submit form
    if (await nextBtn.isVisible().catch(() => false)) {
      await nextBtn.click();
    }
    console.log('⏳ Waiting for lead creation...');
    await page.waitForTimeout(2000); // Wait for API call
    await E2EHelpers.takeScreenshot(page, 'after-submit', '04');

    // If we are still on the email-only screen, advance to full lead form or OTP
    const nameVisibleAfterSubmit = await nameInput.isVisible().catch(() => false);
    const otpVisibleAfterSubmit = await page.locator('.otp-input input').first().isVisible().catch(() => false);

    if (!nameVisibleAfterSubmit && !otpVisibleAfterSubmit && await nextBtn.isVisible().catch(() => false)) {
      await nextBtn.click();
      await page.waitForTimeout(1500);
      await E2EHelpers.takeScreenshot(page, 'lead-form-expanded', '04b');
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

    // Wait for OTP screen to appear (lead + OTP sent)
    const otpInput = page.locator('.code-input');
    const otpVisible = await otpInput.first().isVisible().catch(() => false);

    if (!otpVisible) {
      const leadCreateResponse = await page.waitForResponse((response) => {
        return response.url().includes('/wp-json/soulmirror/v1/lead/create');
      }, { timeout: 10000 }).catch(() => null);

      if (leadCreateResponse) {
        const leadCreateBody = await leadCreateResponse.json().catch(() => null);
        console.log('✅ Lead creation response:', leadCreateBody);
      }

      await page.waitForSelector('.code-input', { timeout: 10000 }).catch(async () => {
        await E2EHelpers.logPageDiagnostics(page, 'OTP inputs not visible after lead creation');
        throw new Error('OTP inputs not visible after lead creation');
      });
    }

    // STEP 4: Get OTP and enter it
    console.log('\n📍 STEP 4: Retrieving and entering OTP...');
    await page.waitForTimeout(2000); // Ensure OTP is generated
    await E2EHelpers.enterOtp(page, testEmail);
    await E2EHelpers.takeScreenshot(page, 'otp-entered', '05');

    // Wait for verification
    console.log('⏳ Waiting for OTP verification...');
    if (await nextBtn.isVisible().catch(() => false)) {
      await nextBtn.click();
    }
    await page.waitForTimeout(3000);
    await E2EHelpers.takeScreenshot(page, 'otp-verified', '06');
    await E2EHelpers.logState(page, 'After OTP Verification');

    // STEP 5: Upload aura photo
    console.log('\n📍 STEP 5: Uploading aura photo...');
    const testImagePath = path.resolve(__dirname, '../assets/test-palm.png');
    await page.waitForSelector('#photo-upload-input', { timeout: 10000, state: 'attached' });
    const fileInput = page.locator('#photo-upload-input');

    try {
      await fileInput.setInputFiles(testImagePath);
      console.log('✅ Photo uploaded');
    } catch (e) {
      console.log('⚠️  Test image not found, skipping photo upload');
    }
    await page.waitForTimeout(2000);
    await E2EHelpers.takeScreenshot(page, 'photo-uploaded', '07');

    const usePhotoBtn = page.locator('#use-photo-btn');
    if (await usePhotoBtn.isVisible().catch(() => false)) {
      await usePhotoBtn.click();
      await page.waitForTimeout(1500);
    } else {
      await usePhotoBtn.waitFor({ state: 'visible', timeout: 10000 }).catch(() => null);
      if (await usePhotoBtn.isVisible().catch(() => false)) {
        await usePhotoBtn.click();
        await page.waitForTimeout(1500);
      }
    }

    // STEP 6: Complete quiz
    console.log('\n📍 STEP 6: Completing quiz...');
    await E2EHelpers.takeScreenshot(page, 'quiz-start', '08');

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

    await E2EHelpers.takeScreenshot(page, 'quiz-filled', '09');

    // STEP 7: Verify report page loaded
    console.log('\n📍 STEP 7: Verifying report page...');
    await page.waitForTimeout(3000);
    await E2EHelpers.takeScreenshot(page, 'report-loaded', '10');
    await E2EHelpers.logState(page, 'Report Page Loaded');

    // Check for report elements
    const reportContainer = await E2EHelpers.waitForElement(page, '#aura-reading-result, #palm-reading-result, .reading-result-container, .report-container', 20000);
    console.log(`Report container visible: ${reportContainer}`);

    // Wait for flow state to reach result (generation complete).
    await page.waitForFunction(() => sessionStorage.getItem('sm_flow_step_id') === 'result', null, { timeout: 60000 }).catch(async () => {
      await E2EHelpers.logPageDiagnostics(page, 'Report did not reach result state in time');
      throw new Error('Report did not reach result state in time');
    });

    // Get session state
    const sessionState = await E2EHelpers.getSessionState(page);
    console.log('Final session state:', sessionState);

    // Assertions
    expect(sessionState.sm_reading_loaded).toBe('true');
    expect(sessionState.sm_email).toBe(testEmail);
    expect(sessionState.sm_flow_step_id).toBe('result');

    // Check for no 500 errors
    const has500 = apiCalls.some(call => call.status === 500);
    expect(has500).toBe(false);

    console.log('\n✅ Full flow test completed successfully!\n');
  });

  // ========================================
  // TEST 2: Report Unlock + Refresh Behavior
  // ========================================
  test('Report unlocks - test unlock + refresh + back button', async ({ page }) => {
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    await E2EHelpers.ensureAppLoaded(page);

    const testEmail = E2EHelpers.generateTestEmail();
    console.log(`\n🎯 Testing unlock behavior with seeded reading for: ${testEmail}\n`);

    // Seed a reading directly (bypass full flow for speed)
    console.log('🌱 Seeding reading...');
    const seededData = await E2EHelpers.seedReading(testEmail, 'Unlock Test User');
    console.log(`✅ Reading seeded: lead_id=${seededData.lead_id}, reading_id=${seededData.reading_id}`);

    // Navigate to report with URL params
    const reportUrl = E2EHelpers.buildReportUrl(seededData.lead_id);
    console.log(`\n📍 STEP 1: Loading report: ${reportUrl}`);

    await page.goto(reportUrl);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);
    await E2EHelpers.takeScreenshot(page, 'seeded-report-initial', '01');
    await E2EHelpers.logState(page, 'Seeded Report Loaded');

    // Verify report loaded
    const hasReport = await E2EHelpers.waitForElement(page, '#aura-reading-result, #palm-reading-result, .reading-result-container, .report-container');
    expect(hasReport).toBe(true);

    // STEP 2: Click first unlock button
    console.log('\n📍 STEP 2: Clicking first unlock button...');
    const firstUnlockBtn = await E2EHelpers.pickFreeUnlockButton(page);
    const firstUnlockVisible = await firstUnlockBtn.isVisible({ timeout: 5000 });
    expect(firstUnlockVisible).toBe(true);
    if (firstUnlockVisible) {
      await firstUnlockBtn.click();
      await page.waitForTimeout(2000);
      await E2EHelpers.takeScreenshot(page, 'after-first-unlock', '02');
      console.log('✅ First section unlocked');

      // STEP 3: Refresh page
      console.log('\n📍 STEP 3: Refreshing page after first unlock...');
      await page.reload();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
      await E2EHelpers.takeScreenshot(page, 'after-unlock-1-refresh', '03');
      await E2EHelpers.logState(page, 'After First Unlock + Refresh');

      // Verify report stays rendered after refresh
      await E2EHelpers.waitForElement(page, '#aura-reading-result, #palm-reading-result', 10000);

      // Verify design integrity
      const pageStructure = await page.evaluate(() => {
        return {
          hasAppContent: !!document.querySelector('#app-content'),
          hasReportContainer: !!document.querySelector('#aura-reading-result, #palm-reading-result, .reading-result-container, .report-container'),
          bodyClasses: document.body.className,
        };
      });

      console.log('Page structure after refresh:', pageStructure);
      expect(pageStructure.hasAppContent).toBe(true);
      expect(pageStructure.hasReportContainer).toBe(true);

      // Verify unlocked section persisted
      const unlockedCount = await page.evaluate(() => {
        return document.querySelectorAll('[data-lock]:not(.locked)').length;
      });
      console.log(`Unlocked sections after refresh: ${unlockedCount}`);
      expect(unlockedCount).toBeGreaterThan(0);

      // STEP 4: Click second unlock button
      console.log('\n📍 STEP 4: Clicking second unlock button...');
      const secondUnlockBtn = await E2EHelpers.pickFreeUnlockButton(page, ['challenges', 'phase', 'timeline', 'guidance', 'love']);
      const secondUnlockVisible = await secondUnlockBtn.isVisible({ timeout: 5000 });
      expect(secondUnlockVisible).toBe(true);
      if (secondUnlockVisible) {
        await secondUnlockBtn.click();
        await page.waitForTimeout(2000);
        await E2EHelpers.takeScreenshot(page, 'after-second-unlock', '04');

        // Refresh again
        console.log('\n📍 STEP 5: Refreshing page after second unlock...');
        await page.reload();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        await E2EHelpers.takeScreenshot(page, 'after-unlock-2-refresh', '05');

        // Verify both sections still unlocked
        const unlockedCount2 = await page.evaluate(() => {
          return document.querySelectorAll('[data-lock]:not(.locked)').length;
        });
        console.log(`Unlocked sections after 2nd refresh: ${unlockedCount2}`);
        expect(unlockedCount2).toBeGreaterThan(1);

        console.log('\n✅ Unlock + refresh behavior verified!');
      } else {
        console.log('⚠️  Second unlock button not found (may be expected in current implementation)');
      }
    } else {
      console.log('⚠️  First unlock button not found - check report rendering');
    }

    // Check for errors
    const has500 = apiCalls.some(call => call.status === 500);
    expect(has500).toBe(false);

    const hasInfiniteLoop = consoleLogs.some(log => log.includes('INFINITE LOOP DETECTED'));
    expect(hasInfiniteLoop).toBe(false);
  });

  // ========================================
  // TEST 3: Third Unlock Modal (No Redirect)
  // ========================================
  test('Timeline + guidance unlock persist after refresh', async ({ page }) => {
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    await E2EHelpers.ensureAppLoaded(page);

    const testEmail = E2EHelpers.generateTestEmail();
    console.log(`\n🎯 Testing timeline + guidance unlock refresh for: ${testEmail}\n`);

    const seededData = await E2EHelpers.seedReading(testEmail, 'Timeline Guidance User');
    console.log(`✅ Reading seeded: lead_id=${seededData.lead_id}, reading_id=${seededData.reading_id}`);

    await page.goto(E2EHelpers.buildReportUrl(seededData.lead_id));
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    await E2EHelpers.takeScreenshot(page, 'timeline-guidance-initial', '01');

    const timelineBtn = page.locator('.btn-unlock[data-unlock="timeline"]');
    const guidanceBtn = page.locator('.btn-unlock[data-unlock="guidance"]');

    expect(await timelineBtn.isVisible({ timeout: 5000 })).toBe(true);
    await timelineBtn.click();
    await page.waitForTimeout(1500);
    await E2EHelpers.takeScreenshot(page, 'timeline-unlocked', '02');

    expect(await guidanceBtn.isVisible({ timeout: 5000 })).toBe(true);
    await guidanceBtn.click();
    await page.waitForTimeout(1500);
    await E2EHelpers.takeScreenshot(page, 'guidance-unlocked', '03');

    console.log('\n📍 Refreshing after timeline + guidance unlocks...');
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    await E2EHelpers.takeScreenshot(page, 'timeline-guidance-refresh', '04');

    const timelineLocked = await page.locator('[data-lock="timeline"].locked').count();
    const guidanceLocked = await page.locator('[data-lock="guidance"].locked').count();
    const timelineOverlay = await page.locator('[data-lock="timeline"] .lock-overlay').count();
    const guidanceOverlay = await page.locator('[data-lock="guidance"] .lock-overlay').count();
    const timelineButton = await page.locator('[data-lock="timeline"] .btn-unlock').count();
    const guidanceButton = await page.locator('[data-lock="guidance"] .btn-unlock').count();

    expect(timelineLocked).toBe(0);
    expect(guidanceLocked).toBe(0);
    expect(timelineOverlay).toBe(0);
    expect(guidanceOverlay).toBe(0);
    expect(timelineButton).toBe(0);
    expect(guidanceButton).toBe(0);

    const has500 = apiCalls.some(call => call.status === 500);
    expect(has500).toBe(false);
  });

  // ========================================
  // TEST 4: Third Unlock Modal (No Redirect)
  // ========================================
  test('Third unlock shows modal without redirect', async ({ page }) => {
    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    await E2EHelpers.ensureAppLoaded(page);

    const testEmail = E2EHelpers.generateTestEmail();
    console.log(`\n🎯 Testing paywall + back button for: ${testEmail}\n`);

    // Seed reading
    const seededData = await E2EHelpers.seedReading(testEmail, 'Paywall Test User');
    console.log(`✅ Reading seeded: ${seededData.lead_id}`);

    // Navigate to report
    await page.goto(E2EHelpers.buildReportUrl(seededData.lead_id));
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    await E2EHelpers.takeScreenshot(page, 'paywall-test-initial', '01');
    await E2EHelpers.logState(page, 'Report Loaded');

    // Unlock two free sections first
    const unlockKeys = ['love', 'challenges', 'phase', 'timeline', 'guidance'];
    let unlockedCount = 0;

    for (const key of unlockKeys) {
      const btn = page.locator(`.btn-unlock[data-unlock="${key}"]`);
      if (await btn.isVisible().catch(() => false)) {
        await btn.click();
        unlockedCount += 1;
        await page.waitForTimeout(2000);
      }
      if (unlockedCount >= 2) {
        break;
      }
    }

    expect(unlockedCount).toBe(2);

    // Click third unlock button (should trigger modal)
    console.log('\n📍 Clicking THIRD unlock button (should trigger modal)...');
    let thirdUnlockBtn = null;
    for (const key of unlockKeys) {
      const btn = page.locator(`.btn-unlock[data-unlock="${key}"]`);
      if (await btn.isVisible().catch(() => false)) {
        thirdUnlockBtn = btn;
        break;
      }
    }
    if (!thirdUnlockBtn) {
      thirdUnlockBtn = page.locator('.btn-unlock:not(.btn-premium)').first();
    }

    const thirdUnlockVisible = await thirdUnlockBtn.isVisible({ timeout: 5000 });
    expect(thirdUnlockVisible).toBe(true);
    if (thirdUnlockVisible) {
      await thirdUnlockBtn.click();
      await page.waitForTimeout(2000);

      const currentUrl = page.url();
      console.log(`URL after third unlock: ${currentUrl}`);
      await E2EHelpers.takeScreenshot(page, 'after-third-unlock', '02');

      const modalOpen = await page.locator('.sm-modal.is-open, #sm-modal.is-open').isVisible({ timeout: 5000 });
      expect(modalOpen).toBe(true);

      const isStillOnReport = currentUrl.includes('sm_report=1') || currentUrl.includes(`lead_id=${seededData.lead_id}`);
      expect(isStillOnReport).toBe(true);

      console.log('\n✅ Modal displayed and no redirect occurred!');
    } else {
      console.log('⚠️  Third unlock button not found');
    }

    const has500 = apiCalls.some(call => call.status === 500);
    expect(has500).toBe(false);
  });
});

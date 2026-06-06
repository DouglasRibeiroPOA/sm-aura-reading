// @ts-check
const { test, expect } = require('@playwright/test');
const { E2EHelpers, setupMonitoring } = require('../../helpers/test-helpers');

process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

const DEFAULT_BASE_URL = 'https://sm-aura-reading.local/';
const BASE_URL = process.env.E2E_BASE_URL || DEFAULT_BASE_URL;
const USE_LIVE_OPENAI = process.env.E2E_LIVE_OPENAI === '1';
const REPORT_WAIT_MS = Number(process.env.E2E_REPORT_WAIT_MS) || (USE_LIVE_OPENAI ? 180000 : 60000);

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

async function waitForDashboard(page) {
  await page.waitForSelector('.dashboard-container, #generate-new-reading-btn', { timeout: 15000 });
}

function buildReportsUrl(baseUrl) {
  const url = new URL(baseUrl);
  url.searchParams.set('sm_reports', '1');
  return url.toString();
}

async function openReportsList(page, baseUrl) {
  const reportsUrl = buildReportsUrl(baseUrl);
  await page.goto(reportsUrl);
  await page.waitForSelector('.reports-container, #reportsList', { timeout: 15000 });
  return reportsUrl;
}

test.describe('Phase 6 - Account Linking', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(USE_LIVE_OPENAI ? 240000 : 140000);

  test.afterEach(async () => {
    const helpers = new E2EHelpers(BASE_URL);
    try {
      await helpers.cleanupTestData();
    } catch (error) {
      console.warn(`⚠️  Cleanup skipped: ${error.message}`);
    }
  });

  test('LINK-001: Teaser first, then create account (same email)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seed reading/mock login');

    const consoleLogs = [];
    const apiCalls = [];
    setupMonitoring(page, consoleLogs, apiCalls);

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Linking User One';
    const seeded = await helpers.seedReading(testEmail, testName);

    await mockLoginInBrowser(page, testEmail, testName);
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);

    await openReportsList(page, helpers.getBaseUrl());
    await expect(page.locator(`a[href*="lead_id=${seeded.lead_id}"]`).first()).toBeVisible();
  });

  test('LINK-002: Verify teaser appears in dashboard', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seed reading/mock login');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Linking User Two';
    const seeded = await helpers.seedReading(testEmail, testName);

    await mockLoginInBrowser(page, testEmail, testName);
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);

    await openReportsList(page, helpers.getBaseUrl());
    const reportLink = page.locator(`a[href*="lead_id=${seeded.lead_id}"]`).first();
    await expect(reportLink).toBeVisible();
  });

  test('LINK-003: Upgrade teaser to full report', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seed reading/mock login');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Linking User Three';
    const seeded = await helpers.seedReading(testEmail, testName);

    await mockLoginInBrowser(page, testEmail, testName);
    await helpers.ensureAppLoaded(page);

    const fullReportUrl = helpers.buildReportUrl(seeded.lead_id, { reading_type: 'aura_full' });
    await page.goto(fullReportUrl);

    await page.waitForSelector('#aura-reading-result.full-report, .full-report', { timeout: REPORT_WAIT_MS });
    await expect(page.locator('text=Back to Dashboard').first()).toBeVisible();
  });

  test('LINK-004: Logout and re-login (linking persists)', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seed reading/mock login');

    const testEmail = E2EHelpers.generateTestEmail();
    const testName = 'Linking User Four';
    const seeded = await helpers.seedReading(testEmail, testName);

    const loginResponse = await mockLoginInBrowser(page, testEmail, testName);
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);

    await page.context().clearCookies();
    await page.evaluate(() => {
      localStorage.clear();
      sessionStorage.clear();
    });

    await mockLoginInBrowser(page, testEmail, testName, loginResponse.account_id || '');
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);

    await openReportsList(page, helpers.getBaseUrl());
    await expect(page.locator(`a[href*="lead_id=${seeded.lead_id}"]`).first()).toBeVisible();
  });

  test('LINK-005: Multiple teasers with different emails', async ({ page }) => {
    const helpers = new E2EHelpers(BASE_URL);
    test.skip(!(await helpers.isDevModeEnabled(page)), 'DevMode test endpoints not available for seed reading/mock login');

    const email1 = E2EHelpers.generateTestEmail();
    const email2 = E2EHelpers.generateTestEmail();
    const email3 = E2EHelpers.generateTestEmail();

    const seeded1 = await helpers.seedReading(email1, 'Linking User Five');
    const seeded2 = await helpers.seedReading(email2, 'Other User Two');
    const seeded3 = await helpers.seedReading(email3, 'Other User Three');

    await mockLoginInBrowser(page, email1, 'Linking User Five');
    await helpers.ensureAppLoaded(page);
    await waitForDashboard(page);

    await openReportsList(page, helpers.getBaseUrl());

    await expect(page.locator(`a[href*="lead_id=${seeded1.lead_id}"]`).first()).toBeVisible();
    await expect(page.locator(`a[href*="lead_id=${seeded2.lead_id}"]`)).toHaveCount(0);
    await expect(page.locator(`a[href*="lead_id=${seeded3.lead_id}"]`)).toHaveCount(0);
  });
});

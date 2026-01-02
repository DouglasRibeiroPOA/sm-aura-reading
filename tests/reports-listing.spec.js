// @ts-check
const { test, expect } = require('@playwright/test');

// Allow fetch calls to the local HTTPS site with self-signed certs.
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

const DEFAULT_BASE_URL = 'https://sm-aura-reading.local/';
const BASE_URL = process.env.E2E_BASE_URL || DEFAULT_BASE_URL;

const TestHelpers = {
  resolvedBaseUrl: BASE_URL,

  getBaseUrl() {
    return this.resolvedBaseUrl || BASE_URL;
  },

  async ensureAppLoaded(page) {
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
        this.resolvedBaseUrl = target;
        return true;
      }
    }

    return false;
  },

  getApiBase() {
    return new URL('/wp-json', this.getBaseUrl()).toString().replace(/\/$/, '');
  },

  async cleanupTestData() {
    await fetch(`${this.getApiBase()}/soulmirror-test/v1/cleanup`, { method: 'POST' });
  },

  async seedReading(email, name, accountId) {
    const response = await fetch(`${this.getApiBase()}/soulmirror-test/v1/seed-reading`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, name, account_id: accountId }),
    });
    if (!response.ok) {
      throw new Error(`Failed to seed reading: ${response.statusText}`);
    }
    return response.json();
  },

  async mockLogin(page, email, accountId, name = 'Test User') {
    const url = new URL(`${this.getApiBase()}/soulmirror-test/v1/mock-login`);
    url.searchParams.set('email', email);
    url.searchParams.set('account_id', accountId);
    url.searchParams.set('name', name);
    await page.goto(url.toString());
  },
};

test.describe('Reports Listing', () => {
  test('Pagination and items-per-page keep reports route and show reading time', async ({ page }) => {
    const appFound = await TestHelpers.ensureAppLoaded(page);
    expect(appFound).toBe(true);

    const accountId = `test-account-${Date.now()}`;
    const reportCount = 22;
    const emailSeed = Date.now();

    await TestHelpers.cleanupTestData();

    for (let i = 0; i < reportCount; i += 1) {
      const email = `test-${emailSeed}-${i}@example.com`;
      await TestHelpers.seedReading(email, `Test User ${i + 1}`, accountId);
    }

    await TestHelpers.mockLogin(page, `test-${emailSeed}-0@example.com`, accountId);

    const reportsUrl = new URL(TestHelpers.getBaseUrl());
    reportsUrl.searchParams.set('sm_reports', '1');
    await page.goto(reportsUrl.toString());

    await expect(page.locator('.reports-table')).toBeVisible();

    await page.selectOption('#itemsPerPage', '20');
    await page.waitForURL(/per_page=20/);
    await expect(page.url()).toContain('sm_reports=1');

    const nextButton = page.locator('.pagination a[title="Next Page"]');
    await expect(nextButton).toBeVisible();
    await nextButton.click();
    await page.waitForURL(/paged=2/);
    await expect(page.url()).toContain('sm_reports=1');
    await expect(page.locator('.reports-header')).toBeVisible();

    const readingTimeText = await page.locator('.report-time span').first().textContent();
    expect(readingTimeText).not.toBeNull();
    expect(readingTimeText).not.toContain('N/A');
  });
});

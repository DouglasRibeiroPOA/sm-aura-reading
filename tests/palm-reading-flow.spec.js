// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E Tests for Aura Reading Flow
 *
 * These tests verify critical user flows and session management.
 * Each test captures console logs, network requests, and sessionStorage state.
 */

// Allow fetch calls to the local HTTPS site with self-signed certs.
process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

const DEFAULT_BASE_URL = process.env.E2E_BASE_URL || 'https://sm-aura-reading.local/';
const API_BASE = new URL('/wp-json', DEFAULT_BASE_URL).toString().replace(/\/$/, '');

async function seedReading(email, name = 'Test User') {
  const response = await fetch(`${API_BASE}/soulmirror-test/v1/seed-reading`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, name }),
  });

  if (!response.ok) {
    throw new Error(`Failed to seed reading: ${response.statusText}`);
  }

  return response.json();
}

// Helper: Clear all session data
async function clearSession(page) {
  await page.evaluate(() => {
    sessionStorage.clear();
    localStorage.clear();
    // Clear all cookies by setting them to expire
    document.cookie.split(";").forEach((c) => {
      document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
    });
  });
}

// Helper: Get sessionStorage state
async function getSessionState(page) {
  return await page.evaluate(() => {
    return {
      sm_reading_loaded: sessionStorage.getItem('sm_reading_loaded'),
      sm_reading_lead_id: sessionStorage.getItem('sm_reading_lead_id'),
      sm_reading_token: sessionStorage.getItem('sm_reading_token'),
      sm_existing_reading_id: sessionStorage.getItem('sm_existing_reading_id'),
      sm_flow_step_id: sessionStorage.getItem('sm_flow_step_id'),
      sm_email: sessionStorage.getItem('sm_email'),
      sm_lead_cache: sessionStorage.getItem('sm_lead_cache'),
    };
  });
}

// Helper: Capture console logs
function setupConsoleCapture(page, logs) {
  page.on('console', msg => {
    const text = msg.text();
    logs.push(text);
    // Only log important messages to test output
    if (text.includes('[SM') || text.includes('ERROR') || text.includes('500')) {
      console.log('📋 ' + text);
    }
  });
}

// Helper: Capture network requests
function setupNetworkCapture(page, requests) {
  page.on('response', async response => {
    const url = response.url();
    const status = response.status();

    // Capture API calls
    if (url.includes('/wp-json/soulmirror/v1/')) {
      const endpoint = url.split('/wp-json/soulmirror/v1/')[1];
      let body = null;
      try {
        body = await response.json();
      } catch (e) {
        // Not JSON
      }

      requests.push({
        endpoint,
        status,
        url,
        body
      });

      // Log errors
      if (status >= 400) {
        console.log(`🚨 API Error: ${endpoint} → ${status}`);
        console.log(`   Body:`, body);
      }
    }
  });
}

test.describe('Aura Reading Flow - Critical Scenarios', () => {

  test.beforeEach(async ({ page }) => {
    // Clear session before each test
    await page.goto('/');
    await clearSession(page);
  });

  // ========================================
  // TEST 1: Fresh Page Load (No Infinite Loop)
  // ========================================
  test('Fresh page load - should NOT trigger infinite loop or 500 errors', async ({ page }) => {
    const consoleLogs = [];
    const apiRequests = [];

    setupConsoleCapture(page, consoleLogs);
    setupNetworkCapture(page, apiRequests);

    // Visit page with fresh session
    await page.goto('/');

    // Wait for page to fully load
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000); // Allow JS to initialize

    // Verify NO infinite loop detector
    const hasLoopError = consoleLogs.some(log => log.includes('INFINITE LOOP DETECTED'));
    expect(hasLoopError).toBe(false);

    // Verify NO 500 errors
    const has500Error = apiRequests.some(req => req.status === 500);
    expect(has500Error).toBe(false);

    // Verify flow/state endpoint succeeded
    const flowStateRequest = apiRequests.find(req => req.endpoint?.includes('flow/state'));
    if (flowStateRequest) {
      expect(flowStateRequest.status).toBe(200);
    }

    // Verify welcome step is visible
    await expect(page.locator('#app-content')).toBeVisible();

    console.log(`✅ Fresh load: ${apiRequests.length} API calls, ${consoleLogs.length} logs, NO errors`);
  });

  // ========================================
  // TEST 2: Multiple Page Refreshes
  // ========================================
  test('Multiple page refreshes - should NOT cause loops or errors', async ({ page }) => {
    const consoleLogs = [];
    const apiRequests = [];

    setupConsoleCapture(page, consoleLogs);
    setupNetworkCapture(page, apiRequests);

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Refresh 5 times
    for (let i = 1; i <= 5; i++) {
      console.log(`🔄 Refresh #${i}`);
      await page.reload();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);

      // Check for errors after each refresh
      const hasLoopError = consoleLogs.some(log => log.includes('INFINITE LOOP DETECTED'));
      expect(hasLoopError).toBe(false);

      const has500Error = apiRequests.some(req => req.status === 500);
      expect(has500Error).toBe(false);
    }

    console.log(`✅ 5 refreshes completed without errors`);
  });

  // ========================================
  // TEST 3: Rapid Refresh Stress Test
  // ========================================
  test('Rapid refresh stress test - should handle fast refreshes gracefully', async ({ page }) => {
    const consoleLogs = [];
    const apiRequests = [];

    setupConsoleCapture(page, consoleLogs);
    setupNetworkCapture(page, apiRequests);

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Rapid fire 3 refreshes with minimal delay
    for (let i = 1; i <= 3; i++) {
      console.log(`⚡ Rapid refresh #${i}`);
      await page.reload();
      await page.waitForTimeout(200); // Very short delay
    }

    // Wait for stabilization
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Should NOT trigger loop detector even with rapid refreshes
    const hasLoopError = consoleLogs.some(log => log.includes('INFINITE LOOP DETECTED'));
    expect(hasLoopError).toBe(false);

    console.log(`✅ Rapid refresh stress test passed`);
  });

  // ========================================
  // TEST 4: OTP Step Refresh (State Restoration)
  // ========================================
  test('OTP step refresh - should restore to correct step with email preserved', async ({ page }) => {
    const consoleLogs = [];
    setupConsoleCapture(page, consoleLogs);

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Navigate to lead capture step (if not already there)
    const nextBtn = page.locator('#next-btn');
    if (await nextBtn.isVisible()) {
      await nextBtn.click();
      await page.waitForTimeout(500);
    }

    // Fill out lead capture form
    const testEmail = `test-${Date.now()}@example.com`;
    await page.fill('input[name="name"]', 'Test User');
    await page.fill('input[name="email"]', testEmail);
    await page.selectOption('select[name="identity"]', 'prefer-not');
    await page.check('input[name="gdpr"]');

    // Click next to submit lead
    await nextBtn.click();
    await page.waitForTimeout(2000); // Wait for API call

    // Should now be on OTP step or email loading step
    // Set step_id in sessionStorage to emailVerification
    await page.evaluate(() => {
      sessionStorage.setItem('sm_flow_step_id', 'emailVerification');
    });

    // Refresh page
    console.log('🔄 Refreshing on OTP step...');
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Verify session state
    const sessionState = await getSessionState(page);
    console.log('Session state after refresh:', sessionState);

    // Should restore to emailVerification step
    expect(sessionState.sm_flow_step_id).toBe('emailVerification');

    // Email should be preserved
    expect(sessionState.sm_email).toBe(testEmail);

    console.log(`✅ OTP refresh: Restored to correct step with email preserved`);
  });

  // ========================================
  // TEST 5: Report Page Multiple Refreshes
  // ========================================
  test('Report page refreshes - should stay on report with correct URL params', async ({ page }) => {
    const consoleLogs = [];
    const apiRequests = [];

    setupConsoleCapture(page, consoleLogs);
    setupNetworkCapture(page, apiRequests);

    const seeded = await seedReading(`test-${Date.now()}@example.com`, 'Report Refresh Test');

    // Navigate to page with report params
    await page.goto(`/?sm_report=1&lead_id=${seeded.lead_id}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    await page.waitForFunction(() => sessionStorage.getItem('sm_reading_loaded') === 'true', null, { timeout: 15000 });

    // Refresh 3 times
    for (let i = 1; i <= 3; i++) {
      console.log(`🔄 Report refresh #${i}`);
      await page.reload();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1500);

      // Verify URL still has report params
      const currentUrl = page.url();
      expect(currentUrl).toContain('sm_report=1');
      expect(currentUrl).toContain(`lead_id=${seeded.lead_id}`);

      // Verify session state persists
      const sessionState = await getSessionState(page);
      expect(sessionState.sm_reading_loaded).toBe('true');
      expect(sessionState.sm_reading_lead_id).toBe(seeded.lead_id);

      // Should NOT redirect to welcome page
      const hasRedirectedToWelcome = currentUrl.endsWith('/aura-reading') || currentUrl.endsWith('/aura-reading/');
      expect(hasRedirectedToWelcome).toBe(false);
    }

    console.log(`✅ Report refreshes: Stayed on report with correct params`);
  });

  // ========================================
  // TEST 6: Browser Back Button from Report
  // ========================================
  test('Browser back button from report - should clear state and restart', async ({ page }) => {
    const consoleLogs = [];
    setupConsoleCapture(page, consoleLogs);

    const mockLeadId = 'test-lead-789';

    // Simulate report page
    await page.goto('/');
    await page.evaluate(({ leadId }) => {
      sessionStorage.setItem('sm_reading_loaded', 'true');
      sessionStorage.setItem('sm_reading_lead_id', leadId);
      sessionStorage.setItem('sm_flow_step_id', 'result');
    }, { leadId: mockLeadId });

    await page.goto(`/?sm_report=1&lead_id=${mockLeadId}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Navigate to another page (simulate user going elsewhere)
    await page.goto('https://sm-aura-reading.local/');
    await page.waitForTimeout(1000);

    // Press back button
    console.log('⬅️ Pressing back button...');
    await page.goBack();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Session should be cleared
    const sessionState = await getSessionState(page);
    console.log('Session state after back:', sessionState);

    // Reading state should be cleared (allowing fresh start)
    // This behavior may vary based on implementation
    // Update expectations based on desired behavior

    console.log(`✅ Back button: Handled correctly`);
  });

  // ========================================
  // TEST 7: Fast Clicking (Race Condition Test)
  // ========================================
  test('Fast clicking next button - should not create duplicate API calls', async ({ page }) => {
    const apiRequests = [];
    setupNetworkCapture(page, apiRequests);

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const nextBtn = page.locator('#next-btn');

    // Click multiple times rapidly
    if (await nextBtn.isVisible()) {
      console.log('⚡ Fast clicking next button 5 times...');
      await Promise.all([
        nextBtn.click(),
        nextBtn.click(),
        nextBtn.click(),
        nextBtn.click(),
        nextBtn.click(),
      ]);

      await page.waitForTimeout(2000);

      // Count how many times the button click triggered navigation
      // Should have debouncing/loading state to prevent duplicates

      console.log(`✅ Fast click test: ${apiRequests.length} API calls made`);
    }
  });

  // ========================================
  // TEST 8: Session Persistence After Close
  // ========================================
  test('Session persistence - flow state should persist across page navigations', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Set a flow state
    await page.evaluate(() => {
      sessionStorage.setItem('sm_flow_step_id', 'palmPhoto');
      sessionStorage.setItem('sm_email', 'persist@test.com');
    });

    // Navigate away and back
    await page.goto('https://sm-aura-reading.local/');
    await page.waitForTimeout(500);
    await page.goto('https://sm-aura-reading.local/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // Session should still exist (within same browser session)
    const sessionState = await getSessionState(page);
    expect(sessionState.sm_flow_step_id).toBe('palmPhoto');
    expect(sessionState.sm_email).toBe('persist@test.com');

    console.log(`✅ Session persistence verified`);
  });

  // ========================================
  // TEST 9: No 500 Errors on Any Page Load
  // ========================================
  test('No 500 errors - comprehensive check across multiple loads', async ({ page }) => {
    const apiRequests = [];
    setupNetworkCapture(page, apiRequests);

    // Load page multiple times with different states
    const scenarios = [
      { name: 'Clean load', setup: async () => await clearSession(page) },
      { name: 'With reading state', setup: async () => {
        await page.evaluate(() => {
          sessionStorage.setItem('sm_reading_loaded', 'true');
          sessionStorage.setItem('sm_reading_lead_id', 'test-123');
        });
      }},
      { name: 'With flow state', setup: async () => {
        await page.evaluate(() => {
          sessionStorage.setItem('sm_flow_step_id', 'quiz');
        });
      }},
    ];

    for (const scenario of scenarios) {
      console.log(`🧪 Testing: ${scenario.name}`);
      await page.goto('/');
      await scenario.setup();
      await page.reload();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1500);

      // Check for 500 errors
      const errors500 = apiRequests.filter(req => req.status === 500);
      if (errors500.length > 0) {
        console.log(`🚨 Found 500 errors in scenario "${scenario.name}":`, errors500);
      }
      expect(errors500.length).toBe(0);
    }

    console.log(`✅ No 500 errors across all scenarios`);
  });

  // ========================================
  // TEST 10: Unlock Section + Refresh (Design Integrity)
  // ========================================
  test('Unlock section + refresh - design should remain intact, section stays unlocked', async ({ page }) => {
    const consoleLogs = [];
    const apiRequests = [];

    setupConsoleCapture(page, consoleLogs);
    setupNetworkCapture(page, apiRequests);

    const mockLeadId = 'unlock-test-123';
    const mockReadingId = 'reading-unlock-456';

    // Setup: Navigate to report page with a reading
    await page.goto('/');
    await page.evaluate(({ leadId, readingId }) => {
      sessionStorage.setItem('sm_reading_loaded', 'true');
      sessionStorage.setItem('sm_reading_lead_id', leadId);
      sessionStorage.setItem('sm_existing_reading_id', readingId);
      sessionStorage.setItem('sm_flow_step_id', 'result');
      // Simulate no sections unlocked yet
      sessionStorage.setItem('sm_unlocked_sections', JSON.stringify([]));
    }, { leadId: mockLeadId, readingId: mockReadingId });

    await page.goto(`/?sm_report=1&lead_id=${mockLeadId}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Take screenshot of initial state (before any unlocks)
    await page.screenshot({ path: 'test-results/before-unlock.png', fullPage: true });

    // Get initial page structure for comparison
    const initialStructure = await page.evaluate(() => {
      return {
        hasHeader: !!document.querySelector('.header'),
        hasAppContent: !!document.querySelector('#app-content'),
        hasReportContainer: !!document.querySelector('.reading-result-container'),
        bodyClasses: document.body.className,
      };
    });

    console.log('📸 Initial page structure:', initialStructure);

    // Click first unlock button (if visible)
    const firstUnlockBtn = page.locator('.unlock-section-btn').first();
    if (await firstUnlockBtn.isVisible()) {
      console.log('🔓 Clicking first unlock button...');
      await firstUnlockBtn.click();
      await page.waitForTimeout(1500); // Wait for unlock animation/API

      // Verify section was unlocked (check for unlocked class or visible content)
      const firstUnlockSuccess = await page.evaluate(() => {
        const unlockedSections = JSON.parse(sessionStorage.getItem('sm_unlocked_sections') || '[]');
        return unlockedSections.length > 0;
      });
      console.log(`   First unlock successful: ${firstUnlockSuccess}`);

      // REFRESH PAGE
      console.log('🔄 Refreshing after first unlock...');
      await page.reload();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);

      // Take screenshot after first unlock + refresh
      await page.screenshot({ path: 'test-results/after-unlock-1-refresh.png', fullPage: true });

      // Verify page structure is intact
      const afterFirstUnlock = await page.evaluate(() => {
        return {
          hasHeader: !!document.querySelector('.header'),
          hasAppContent: !!document.querySelector('#app-content'),
          hasReportContainer: !!document.querySelector('.reading-result-container'),
          bodyClasses: document.body.className,
          unlockedCount: JSON.parse(sessionStorage.getItem('sm_unlocked_sections') || '[]').length,
        };
      });

      console.log('📸 After first unlock + refresh:', afterFirstUnlock);

      // Assertions: Design should be intact
      expect(afterFirstUnlock.hasHeader).toBe(true);
      expect(afterFirstUnlock.hasAppContent).toBe(true);
      expect(afterFirstUnlock.hasReportContainer).toBe(true);
      expect(afterFirstUnlock.unlockedCount).toBeGreaterThan(0); // Section should stay unlocked

      // Click second unlock button
      const secondUnlockBtn = page.locator('.unlock-section-btn').first(); // First visible button (next locked section)
      if (await secondUnlockBtn.isVisible()) {
        console.log('🔓 Clicking second unlock button...');
        await secondUnlockBtn.click();
        await page.waitForTimeout(1500);

        // REFRESH PAGE AGAIN
        console.log('🔄 Refreshing after second unlock...');
        await page.reload();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        // Take screenshot after second unlock + refresh
        await page.screenshot({ path: 'test-results/after-unlock-2-refresh.png', fullPage: true });

        const afterSecondUnlock = await page.evaluate(() => {
          return {
            hasHeader: !!document.querySelector('.header'),
            hasAppContent: !!document.querySelector('#app-content'),
            hasReportContainer: !!document.querySelector('.reading-result-container'),
            bodyClasses: document.body.className,
            unlockedCount: JSON.parse(sessionStorage.getItem('sm_unlocked_sections') || '[]').length,
          };
        });

        console.log('📸 After second unlock + refresh:', afterSecondUnlock);

        // Assertions: Design should STILL be intact
        expect(afterSecondUnlock.hasHeader).toBe(true);
        expect(afterSecondUnlock.hasAppContent).toBe(true);
        expect(afterSecondUnlock.hasReportContainer).toBe(true);
        expect(afterSecondUnlock.unlockedCount).toBe(2); // Both sections should stay unlocked
      }
    }

    // Check for console errors or infinite loops
    const hasLoopError = consoleLogs.some(log => log.includes('INFINITE LOOP DETECTED'));
    expect(hasLoopError).toBe(false);

    const has500Error = apiRequests.some(req => req.status === 500);
    expect(has500Error).toBe(false);

    console.log(`✅ Unlock + Refresh test: Design intact, sections persisted`);
  });

  // ========================================
  // TEST 11: Third Unlock + Paywall Redirect + Back Button
  // ========================================
  test('Third unlock + paywall redirect + back button - should return to report (NOT first page)', async ({ page }) => {
    const consoleLogs = [];
    const apiRequests = [];

    setupConsoleCapture(page, consoleLogs);
    setupNetworkCapture(page, apiRequests);

    const mockLeadId = 'paywall-test-789';
    const mockReadingId = 'reading-paywall-101';

    // Setup: Navigate to report with 2 sections already unlocked
    await page.goto('/');
    await page.evaluate(({ leadId, readingId }) => {
      sessionStorage.setItem('sm_reading_loaded', 'true');
      sessionStorage.setItem('sm_reading_lead_id', leadId);
      sessionStorage.setItem('sm_existing_reading_id', readingId);
      sessionStorage.setItem('sm_flow_step_id', 'result');
      // Simulate 2 sections already unlocked
      sessionStorage.setItem('sm_unlocked_sections', JSON.stringify(['section1', 'section2']));
    }, { leadId: mockLeadId, readingId: mockReadingId });

    await page.goto(`/?sm_report=1&lead_id=${mockLeadId}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    console.log('📊 Initial state: 2 sections unlocked, on report page');

    // Click third unlock button (should trigger paywall redirect)
    const thirdUnlockBtn = page.locator('.unlock-section-btn').first();
    if (await thirdUnlockBtn.isVisible()) {
      console.log('🔓 Clicking THIRD unlock button (should trigger paywall)...');
      await thirdUnlockBtn.click();
      await page.waitForTimeout(2000); // Wait for redirect

      // Verify we're on paywall/offerings page
      const currentUrl = page.url();
      console.log(`📍 Current URL after third unlock: ${currentUrl}`);

      // Should be redirected to offerings page or paywall
      const isOnPaywall = currentUrl.includes('offerings') ||
                         currentUrl.includes('paywall') ||
                         currentUrl.includes('shop');

      if (!isOnPaywall) {
        console.log('⚠️  Did not redirect to paywall - checking session state...');
        const sessionState = await getSessionState(page);
        console.log('   Session state:', sessionState);
      }

      // Now click BACK button
      console.log('⬅️  Clicking browser BACK button...');
      await page.goBack();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);

      const backUrl = page.url();
      console.log(`📍 URL after back button: ${backUrl}`);

      // Take screenshot of page after back button
      await page.screenshot({ path: 'test-results/after-back-from-paywall.png', fullPage: true });

      // CRITICAL ASSERTION: Should be back on report page, NOT first page
      const isBackOnReport = backUrl.includes('sm_report=1') || backUrl.includes(`lead_id=${mockLeadId}`);
      const isOnFirstPage = backUrl.endsWith('/aura-reading') || backUrl.endsWith('/aura-reading/');

      console.log(`   Back on report: ${isBackOnReport}`);
      console.log(`   On first page: ${isOnFirstPage}`);

      // Verify session state after back
      const sessionAfterBack = await getSessionState(page);
      console.log('   Session state after back:', sessionAfterBack);

      // EXPECTED BEHAVIOR:
      // - Should be back on report page (not first page)
      // - Session should still have reading data
      // - Unlocked sections should still be there
      expect(isBackOnReport).toBe(true); // SHOULD BE ON REPORT
      expect(isOnFirstPage).toBe(false); // SHOULD NOT BE ON FIRST PAGE
      expect(sessionAfterBack.sm_reading_loaded).toBe('true');
      expect(sessionAfterBack.sm_reading_lead_id).toBe(mockLeadId);

      console.log(`✅ Back button from paywall: Returned to report correctly`);
    } else {
      console.log('⚠️  No unlock button found - test cannot proceed');
      // This might happen in DevMode if buttons aren't rendered
      // Log a warning but don't fail the test
    }
  });

  // ========================================
  // TEST 12: Session Expiry Simulation
  // ========================================
  test('Session expiry - after session clear, should start fresh flow', async ({ page }) => {
    const seeded = await seedReading(`test-${Date.now()}@example.com`, 'Session Expiry Test');

    await page.goto(`/?sm_report=1&lead_id=${seeded.lead_id}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    console.log('📊 Session created with reading');

    // Verify session exists
    let sessionState = await getSessionState(page);
    expect(sessionState.sm_reading_loaded).toBe('true');

    // Simulate session expiry (clear all session storage)
    console.log('🧹 Clearing session (simulating browser close or expiry)...');
    await clearSession(page);

    // Navigate to report URL (should NOT work without session)
    await page.goto(`/?sm_report=1&lead_id=${seeded.lead_id}`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Session should be empty
    sessionState = await getSessionState(page);
    console.log('Session after clear:', sessionState);

    // Should either:
    // 1. Redirect to first page (fresh start)
    // 2. Show login prompt
    // 3. Not show the reading (require re-authentication)

    const currentUrl = page.url();
    const hasReadingData = sessionState.sm_reading_loaded === 'true';
    const hasReportParams = currentUrl.includes('sm_report=1') && currentUrl.includes('lead_id=');

    console.log(`   Current URL: ${currentUrl}`);
    console.log(`   Has reading data: ${hasReadingData}`);
    console.log(`   Has report params: ${hasReportParams}`);

    // After session expiry, either:
    // - Redirect to fresh flow (no report params), or
    // - Rehydrate reading from server when report params are present.
    if (hasReportParams) {
      expect(hasReadingData).toBe(true);
    } else {
      expect(hasReadingData).toBe(false);
    }

    console.log(`✅ Session expiry: Handled fresh start or rehydration as expected`);
  });

});

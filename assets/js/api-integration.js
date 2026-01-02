/**
 * API Integration for SoulMirror Aura Reading AI
 *
 * This file bridges the frontend UI (script.js) with the WordPress REST API backend.
 * It intercepts the UI flow and makes real API calls to:
 * - Create leads
 * - Send and verify OTP
 * - Upload aura photos
 * - Save quiz responses
 * - Generate AI readings
 *
 * @requires script.js (must be loaded first)
 * @requires smData (WordPress localized data with nonce and apiUrl)
 */

(function () {
    'use strict';

    // Verify required dependencies
    if (typeof smData === 'undefined') {
        console.error('[SM API] smData is not defined. Make sure wp_localize_script is working.');
        return;
    }

    if (typeof appState === 'undefined') {
        console.error('[SM API] appState is not defined. Make sure script.js is loaded first.');
        return;
    }

    function getReadingResultContainer() {
        return document.getElementById('aura-reading-result') || document.getElementById('palm-reading-result');
    }

    // API Integration State
    const apiState = {
        leadId: null,
        otpSent: false,
        otpVerified: false,
        imageUploaded: false,
        quizSaved: false,
        readingGenerated: false,
        readingStartRequested: false,
        processingRequest: false,
        dynamicQuestions: [],
        demographics: {
            ageRange: '',
            gender: ''
        }
    };
    let magicLinkHandled = false;
    let teaserEventDispatched = false; // New flag to prevent duplicate teaser events
    const STORAGE_KEY = 'sm_lead_cache';
    const STEP_STORAGE_KEY = 'sm_flow_step_id';

    function ensureSwipeTemplateAssets() {
        const swipeTemplate = document.querySelector('.sm-swipe-template');
        if (!swipeTemplate) return;

        document.body.classList.add('sm-swipe-report');
        const appRoot = document.getElementById('sm-aura-reading-app') || document.getElementById('sm-palm-reading-app');
        if (appRoot) {
            appRoot.classList.add('sm-swipe-report');
        }

        if (!smData || !smData.pluginUrl) return;
        const pluginUrl = smData.pluginUrl;

        if (!document.querySelector('link[data-sm-swipe-template]')) {
            const cssLink = document.createElement('link');
            cssLink.rel = 'stylesheet';
            cssLink.href = `${pluginUrl}assets/css/swipe-template.css`;
            cssLink.setAttribute('data-sm-swipe-template', '1');
            document.head.appendChild(cssLink);
        }

        if (!document.querySelector('script[data-sm-swipe-template]')) {
            const script = document.createElement('script');
            script.src = `${pluginUrl}assets/js/swipe-template.js`;
            script.defer = true;
            script.setAttribute('data-sm-swipe-template', '1');
            document.body.appendChild(script);
        }
    }

    document.addEventListener('sm:report_loaded', ensureSwipeTemplateAssets);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureSwipeTemplateAssets);
    } else {
        ensureSwipeTemplateAssets();
    }

    // Cache only lead_id in session storage (clears when browser closes)
    function persistLeadCache(data) {
        try {
            if (!data || !data.leadId) {
                sessionStorage.removeItem(STORAGE_KEY);
                return;
            }
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ leadId: data.leadId }));
        } catch (e) {
            logError('Persist lead cache failed', e);
        }
    }

    function readLeadCache() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            logError('Read lead cache failed', e);
            return null;
        }
    }

    function resetApiState() {
        apiState.leadId = null;
        apiState.otpSent = false;
        apiState.otpVerified = false;
        apiState.imageUploaded = false;
        apiState.quizSaved = false;
        apiState.readingGenerated = false;
        apiState.processingRequest = false;
        apiState.dynamicQuestions = [];
        apiState.demographics = { ageRange: '', gender: '' };
        teaserEventDispatched = false; // Reset the flag when API state is reset
    }

    function clearClientSession() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
            sessionStorage.removeItem(STEP_STORAGE_KEY);
            sessionStorage.removeItem('sm_reading_lead_id');
            sessionStorage.removeItem('sm_existing_reading_id');
            sessionStorage.removeItem('sm_reading_token');
            sessionStorage.removeItem('sm_reading_loaded');
            sessionStorage.removeItem('sm_reading_type');
            sessionStorage.removeItem('sm_email');
            sessionStorage.removeItem('sm_loop_guard');
            sessionStorage.removeItem('sm_paywall_redirect');
            sessionStorage.removeItem('sm_paywall_return_url');
            teaserEventDispatched = false; // Also reset here for comprehensive reset
        } catch (e) {
            logError('Clear client session failed', e);
        }
    }

    function clearReportUrlParams() {
        try {
            const url = new URL(window.location.href);
            url.searchParams.delete('sm_report');
            url.searchParams.delete('lead_id');
            url.searchParams.delete('lead');
            window.history.replaceState({}, document.title, url.pathname + (url.search || '') + (url.hash || ''));
        } catch (error) {
            logError('Failed to clear report URL params', error);
        }
    }

    function resetFlowState(jumpToWelcome = true) {
        resetApiState();
        magicLinkHandled = false; // Reset magic link flag too
        appState.userData = {
            name: '',
            email: '',
            identity: '',
            age: '',
            ageRange: '',
            gdprConsent: false,
            palmImage: null,
            emailVerified: false
        };
        appState.dynamicQuestions = [];
        appState.quizResponses = {};
        clearClientSession();
        if (typeof resetLeadCaptureValidationState === 'function') {
            resetLeadCaptureValidationState();
        }
        if (jumpToWelcome && window.appContent && typeof window.renderStep === 'function') {
            window.renderStep(0);
        } else if (jumpToWelcome) {
            log('Skipped renderStep reset; #app-content not available.');
        }
    }

    let magicOverlay = null;

    function showMagicOverlay() {
        if (magicOverlay) {
            return;
        }
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.right = '0';
        overlay.style.bottom = '0';
        overlay.style.background = 'rgba(11, 15, 23, 0.85)';
        overlay.style.zIndex = '9999';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.color = '#fff';
        overlay.style.fontFamily = 'sans-serif';
        overlay.innerHTML = '<div style="text-align:center;"><div class="loading-spinner" style="margin-bottom:12px;"></div><div>Preparing your reading...</div></div>';
        document.body.appendChild(overlay);
        magicOverlay = overlay;
    }

    function hideMagicOverlay() {
        if (!magicOverlay) {
            return;
        }
        magicOverlay.remove();
        magicOverlay = null;
    }

    function jumpToStepWhenReady(stepId, attemptsLeft = 15) {
        if (!window.appContent || typeof window.renderStep !== 'function' || typeof palmReadingConfig === 'undefined') {
            if (attemptsLeft > 0) {
                setTimeout(() => jumpToStepWhenReady(stepId, attemptsLeft - 1), 150);
            } else {
                log('Unable to jump to step; app not ready.', { stepId });
            }
            return false;
        }

        const stepIndex = palmReadingConfig.steps.findIndex(s => s.id === stepId);
        if (stepIndex < 0) {
            log('Unable to jump to step; step id not found.', { stepId });
            return false;
        }

        window.renderStep(stepIndex);
        return true;
    }

    function enforceStepAfterInit(stepId, attemptsLeft = 20) {
        if (attemptsLeft <= 0) {
            return;
        }

        if (!window.appContent || typeof window.renderStep !== 'function' || typeof palmReadingConfig === 'undefined') {
            setTimeout(() => enforceStepAfterInit(stepId, attemptsLeft - 1), 200);
            return;
        }

        const stepIndex = palmReadingConfig.steps.findIndex(s => s.id === stepId);
        if (stepIndex < 0) {
            log('Unable to enforce step; step id not found.', { stepId });
            return;
        }

        if (typeof appState === 'undefined' || appState.currentStep !== stepIndex) {
            window.renderStep(stepIndex);
        }

        setTimeout(() => enforceStepAfterInit(stepId, attemptsLeft - 1), 200);
    }

    let currentOtpKeyListener = null; // Global to manage the OTP Enter key listener

    function wrapRenderStepForRestore() {
        if (typeof window.renderStep !== 'function' || window.renderStep._smWrapped) {
            return;
        }

        const original = window.renderStep;
        window.renderStep = function (stepIndex) {
            // --- FIX for Enter key submission ---
            // Always remove previous listener to prevent duplicates and ensure it's clean
            if (currentOtpKeyListener) {
                document.removeEventListener('keydown', currentOtpKeyListener);
                currentOtpKeyListener = null;
            }
            // --- END FIX ---

            const result = original(stepIndex);
            try {
                if (typeof palmReadingConfig !== 'undefined' && palmReadingConfig.steps[stepIndex]) {
                    sessionStorage.setItem(STEP_STORAGE_KEY, palmReadingConfig.steps[stepIndex].id);

                    // --- FIX for Enter key submission ---
                    // Add new listener if we are on the emailVerification step
                    const step = palmReadingConfig.steps[stepIndex];
                    if (step.id === 'emailVerification') {
                        currentOtpKeyListener = (event) => {
                            const nextBtn = document.getElementById('next-btn');
                            if (event.key === 'Enter' && nextBtn && !nextBtn.disabled) {
                                event.preventDefault();
                                nextBtn.click();
                            }
                        };
                        document.addEventListener('keydown', currentOtpKeyListener);
                        log('OTP Enter key listener added.');
                    }
                    // --- END FIX ---
                    if (step.id === 'resultLoading' && !apiState.readingGenerated && !apiState.readingStartRequested) {
                        apiState.readingStartRequested = true;
                        generateReading()
                            .then((readingHtml) => {
                                if (!readingHtml) {
                                    apiState.readingStartRequested = false;
                                    return;
                                }
                                if (window.clearLoadingTimers) {
                                    window.clearLoadingTimers();
                                }
                                palmReadingConfig.result.generateReading = function () {
                                    return readingHtml;
                                };
                                window.goToNextStep();
                            })
                            .catch((error) => {
                                apiState.readingStartRequested = false;
                                logError('Immediate reading generation failed', error);
                            });
                    }
                    if (step.id !== 'result' && step.id !== 'resultLoading') {
                        const modal = document.getElementById('sm-modal') || document.querySelector('.sm-modal');
                        if (modal) {
                            modal.remove();
                        }
                    }
                }
            } catch (e) {
                logError('Failed to store step state or manage OTP listener', e);
            }
            return result;
        };
        window.renderStep._smWrapped = true;
    }


    function restoreStepFromSession() {
        if (!window.appContent) {
            return;
        }

        const storedReadingLoaded = sessionStorage.getItem('sm_reading_loaded');
        const stepId = sessionStorage.getItem(STEP_STORAGE_KEY);
        if ((stepId === 'result' || stepId === 'resultLoading') && storedReadingLoaded !== 'true') {
            sessionStorage.removeItem(STEP_STORAGE_KEY);
            return;
        }
        if (storedReadingLoaded === 'true') {
            if (stepId && stepId !== 'result' && stepId !== 'resultLoading') {
                sessionStorage.removeItem('sm_reading_loaded');
            } else {
                return;
            }
        }

        const params = new URLSearchParams(window.location.search);
        if (params.get('sm_magic')) {
            return;
        }

        if (!stepId) {
            return;
        }

        if (jumpToStepWhenReady(stepId)) {
            enforceStepAfterInit(stepId);
        }
    }

    // Logging helper
    function log(message, data = null) {
        console.log(`[SM API] ${message}`, data || '');
    }

    // Error helper
    function logError(message, error = null) {
        console.error(`[SM API ERROR] ${message}`, error || '');
    }

    function normalizeGender(identity) {
        const value = (identity || '').toLowerCase();
        if (value === 'woman' || value === 'female' || value === 'f') return 'female';
        if (value === 'man' || value === 'male' || value === 'm') return 'male';
        if (value === 'prefer-not' || value === 'prefer_not' || value === 'prefer not to say') return 'prefer_not_to_say';
        return 'prefer_not_to_say';
    }

    function mapAgeToRange(ageInput) {
        const age = parseInt(ageInput, 10);
        if (isNaN(age) || age < 18) return '';
        if (age <= 25) return 'age_18_25';
        if (age <= 35) return 'age_26_35';
        if (age <= 50) return 'age_36_50';
        if (age <= 65) return 'age_51_65';
        return 'age_65_plus';
    }

    function getDemographics() {
        const cachedAge = (apiState.demographics && apiState.demographics.ageRange) || '';
        const cachedGender = (apiState.demographics && apiState.demographics.gender) || '';
        const derivedAgeRange = mapAgeToRange(appState.userData.age || appState.userData.ageRange);
        const ageRange = (derivedAgeRange || appState.userData.ageRange || cachedAge || '').trim();
        const gender = normalizeGender(appState.userData.identity) || cachedGender || 'prefer_not_to_say';
        return {
            ageRange,
            gender
        };
    }

    function resolveQuestionKey(question, fallback) {
        if (!question) return fallback;
        return question.id || question.question_id || fallback;
    }

    let isRefreshingNonce = false;

    async function refreshNonce() {
        if (!smData.nonceRefreshUrl || isRefreshingNonce) {
            return null;
        }

        isRefreshingNonce = true;

        try {
            const response = await fetch(smData.nonceRefreshUrl, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-SM-Nonce': smData.nonce
                },
                credentials: 'same-origin'
            });

            const data = await response.json().catch(() => null);

            if (response.ok && data && data.data && data.data.nonce) {
                smData.nonce = data.data.nonce;
                log('Nonce refreshed successfully');
                return smData.nonce;
            }

            logError('Nonce refresh failed', data);
            return null;
        } catch (error) {
            logError('Nonce refresh request failed', error);
            return null;
        } finally {
            isRefreshingNonce = false;
        }
    }

    // API Request Helper
    async function makeApiRequest(endpoint, method = 'POST', body = null) {
        const url = smData.apiUrl + endpoint;

        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-SM-Nonce': smData.nonce  // Use custom header to avoid WP core cookie auth
            },
            credentials: 'same-origin'
        };

        if (body && method !== 'GET') {
            options.body = JSON.stringify(body);
        }

        const performRequest = async () => {
            log(`${method} ${endpoint}`, body);
            console.log('[SM API] Request headers:', {
                'X-SM-Nonce': smData.nonce,
                'Content-Type': 'application/json'
            });

            const response = await fetch(url, options);
            let data = null;
            try {
                data = await response.json();
            } catch (e) {
                data = null;
            }

            if (!response.ok) {
                const err = new Error((data && data.message) || 'API request failed');
                err.data = data;
                err.status = response.status;
                throw err;
            }

            log(`✓ ${endpoint} success`, data);
            return data;
        };

        try {
            return await performRequest();
        } catch (error) {
            const status = error && error.status ? error.status : null;
            if ((status === 401 || status === 403) && await refreshNonce()) {
                options.headers['X-SM-Nonce'] = smData.nonce;
                log('Retrying request after nonce refresh');
                return await performRequest();
            }
            logError(`✗ ${endpoint} failed`, error);
            throw error;
        }
    }

    // Toast Notification Helper
    function showToast(message, type = 'error', duration = 3500) {
        const toast = document.getElementById('toast');
        if (!toast) {
            alert(message);
            return;
        }

        toast.textContent = message;
        toast.classList.remove('toast-offerings');
        toast.className = `toast ${type} show`;

        setTimeout(() => {
            toast.classList.remove('show');
        }, duration);
    }

    function handleApiRedirect(error) {
        const payload = error && error.data && error.data.data ? error.data.data : null;
        if (!payload || !payload.redirect_to) {
            return false;
        }

        const delay = Number.isFinite(payload.redirect_delay_ms) ? payload.redirect_delay_ms : 0;
        setTimeout(() => {
            window.location.href = payload.redirect_to;
        }, delay);
        return true;
    }

    function showAuthErrorToast() {
        try {
            const params = new URLSearchParams(window.location.search);
            const code = params.get('sm_auth_error');
            if (!code) {
                return;
            }

            let message = 'We could not log you in. Please try again.';
            if (code === 'missing_profile') {
                message = 'Your account is missing required details (name, email, or date of birth). Please update your account and try again.';
            }

            showToast(message, 'error', 5000);

            params.delete('sm_auth_error');
            params.delete('sm_auth_missing');
            const newQuery = params.toString();
            const newUrl = `${window.location.pathname}${newQuery ? `?${newQuery}` : ''}${window.location.hash || ''}`;
            window.history.replaceState({}, document.title, newUrl);
        } catch (error) {
            logError('Failed to show auth error toast', error);
        }
    }

    function extractRedirectUrl(error) {
        if (!error || !error.data) return null;
        if (error.data.redirect_to) return error.data.redirect_to;
        if (error.data.data && error.data.data.redirect_to) return error.data.data.redirect_to;
        return null;
    }

    function markReportUrl() {
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('sm_report', '1');

            // CRITICAL: Add lead_id to URL for reliable refresh detection
            if (apiState.leadId) {
                url.searchParams.set('lead_id', apiState.leadId);
            }

            const storedReadingType = sessionStorage.getItem('sm_reading_type');
            const reportContainer = getReadingResultContainer();
            const domReadingType = reportContainer ? reportContainer.dataset.readingType : '';
            const readingType = domReadingType || storedReadingType || getPreferredReadingType();
            if (readingType) {
                url.searchParams.set('reading_type', readingType);
            }

            window.history.pushState({}, '', url.toString());
            log('✅ Marked report URL:', url.toString());
        } catch (error) {
            logError('Failed to mark report URL', error);
        }
    }

    // Render an existing reading and jump to the result step
    function renderExistingReading(readingHtml, readingId, leadId, readingType = 'aura_teaser') {
        if (!readingHtml) return false;

        // Check if reading is already rendered in DOM (prevent duplicate renders)
        const existingReport = getReadingResultContainer();
        if (existingReport && existingReport.querySelector('[data-reading-id]')) {
            log('Reading already rendered in DOM, skipping duplicate render');
            // If already rendered, ensure the event is dispatched if it hasn't been yet
            if (!teaserEventDispatched) {
                const teaserEvent = new CustomEvent('sm:teaser_loaded');
                document.dispatchEvent(teaserEvent);
                teaserEventDispatched = true;
                log('Dispatched sm:teaser_loaded (deduplicated via renderExistingReading).');
            }
            return true;
        }

        // Persist lead id so downstream calls use the same record
        if (leadId) {
            apiState.leadId = leadId;
            appState.leadId = leadId;
            sessionStorage.setItem('sm_reading_lead_id', leadId);
        }

        const lastStepIndex = palmReadingConfig.steps.length - 1;
        appState.currentStep = lastStepIndex;

        // Extract body content if HTML is a full document (with DOCTYPE, html, head, body tags)
        let htmlToInject = readingHtml;
        const bodyMatch = readingHtml.match(/<body[^>]*>([\s\S]*)<\/body>/i);
        if (bodyMatch && bodyMatch[1]) {
            htmlToInject = bodyMatch[1];
            log('Extracted body content from full HTML document');
        }

        appContent.innerHTML = htmlToInject;
        ensureSwipeTemplateAssets();

        if (backBtn) backBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';

        if (typeof updateProgressBar === 'function') {
            updateProgressBar();
        }

        // CRITICAL: Verify reading container exists before marking as loaded
        // This prevents infinite loops when reading HTML is invalid
        const verifyContainer = getReadingResultContainer();
        if (!verifyContainer) {
            logError('Reading HTML injected but result container not found - aborting render');
            return false;
        }

        if (!verifyContainer.dataset.readingType && readingType) {
            verifyContainer.dataset.readingType = readingType;
        }
        const resolvedReadingType = verifyContainer.dataset.readingType || readingType || 'aura_teaser';

        // Dispatch a report-loaded event for both report types
        const reportEvent = new CustomEvent('sm:report_loaded');
        document.dispatchEvent(reportEvent);

        // Only dispatch the teaser event for teaser readings
        if (resolvedReadingType === 'aura_teaser' && !teaserEventDispatched) {
            const teaserEvent = new CustomEvent('sm:teaser_loaded');
            document.dispatchEvent(teaserEvent);
            teaserEventDispatched = true;
            log('Dispatched sm:teaser_loaded (first time via renderExistingReading).');
        }

        // ONLY mark reading as loaded AFTER confirming container exists (CRITICAL for page refresh)
        sessionStorage.setItem('sm_reading_loaded', 'true');
        sessionStorage.setItem('sm_flow_step_id', 'result');
        sessionStorage.setItem('sm_reading_type', resolvedReadingType);

        // Cache reading id and lead id if provided
        if (readingId) {
            sessionStorage.setItem('sm_existing_reading_id', readingId);
        }
        if (leadId) {
            sessionStorage.setItem('sm_reading_lead_id', leadId);
        }

        log('🔥 EXISTING READING RENDERED - About to mark URL with lead_id:', leadId);
        markReportUrl();
        log('🔥 URL marked. Current URL:', window.location.href);

        // For paid reports, prevent browser back navigation
        if (resolvedReadingType === 'aura_full') {
            window.history.pushState(null, '', window.location.href);
            log('🔒 Back navigation disabled for paid report');
        }

        return true;
    }

    // ========================================
    // API INTEGRATION FUNCTIONS
    // ========================================

    /**
     * Create Lead
     * Called after lead capture form is submitted
     */
    async function createLead(name, email, identity, gdprConsent) {
        log('Creating lead...', { name, email, identity });

        try {
            const response = await makeApiRequest('lead/create', 'POST', {
                name: name,
                email: email,
                identity: identity,
                gdpr: gdprConsent,
                age: appState.userData.age || '',
                age_range: appState.userData.ageRange || ''
            });

            if (response.success && response.data) {
                // Existing reading path (no new credits used)
                if (response.data.existing_reading && response.data.reading_html) {
                    log('✓ Existing reading returned for email, skipping OTP/generation', { email });

                    // Update flow state to result page
                    await updateFlowState({
                        step_id: 'result',
                        status: 'reading_ready',
                        lead_id: response.data.lead_id,
                        reading_id: response.data.reading_id,
                        email: email
                    });

                    renderExistingReading(response.data.reading_html, response.data.reading_id, response.data.lead_id || null, response.data.reading_type || 'aura_teaser');
                    return { existingReading: true };
                }

                if (response.data.lead_id) {
                    apiState.leadId = response.data.lead_id;
                    log('✓ Lead created', { leadId: apiState.leadId });
                    persistLeadCache({
                        leadId: apiState.leadId,
                        email: email,
                        name: name,
                        identity: identity,
                        age: appState.userData.age || '',
                        ageRange: appState.userData.ageRange || '',
                        gdprConsent: gdprConsent
                    });

                    // Update flow state - lead created, ready for OTP
                    await updateFlowState({
                        step_id: 'emailVerification',
                        status: 'otp_pending',
                        lead_id: apiState.leadId,
                        email: email
                    });

                    return { leadCreated: true };
                }

                throw new Error('Invalid response from lead creation');
            } else {
                throw new Error('Invalid response from lead creation');
            }

        } catch (error) {
            logError('Lead creation failed', error);

            const redirectUrl = extractRedirectUrl(error);
            if (error && error.data && error.data.error_code === 'credits_exhausted' && redirectUrl) {
                window.location.href = redirectUrl;
                return false;
            }

            showToast(error.message || 'Failed to create your profile. Please try again.', 'error');
            return false;
        }
    }

    /**
     * Send OTP
     * Called after email loading animation
     */
    async function sendOtp(email) {
        if (apiState.otpSent) {
            log('OTP already sent, skipping...');
            return true;
        }

        log('Sending OTP...', { email });

        try {
            const response = await makeApiRequest('otp/send', 'POST', {
                lead_id: apiState.leadId,
                email: email
            });

            if (response.success) {
                apiState.otpSent = true;
                log('✓ OTP sent successfully');
                return true;
            } else {
                throw new Error(response.message || 'Failed to send OTP');
            }

        } catch (error) {
            logError('OTP send failed', error);
            showToast(error.message || 'Failed to send verification code. Please try again.');
            return false;
        }
    }

    /**
     * Verify OTP
     * Called when user submits 4-digit code
     */
    async function verifyOtp(otpCode) {
        log('Verifying OTP...', { code: otpCode });

        try {
            const response = await makeApiRequest('otp/verify', 'POST', {
                lead_id: apiState.leadId,
                otp: otpCode
            });

            if (response.success) {
                apiState.otpVerified = true;
                appState.userData.emailVerified = true;
                if (appState.userData.email) {
                    sessionStorage.setItem('sm_email', appState.userData.email);
                }
                log('✓ OTP verified successfully');

                // Update flow state - OTP verified, ready for palm photo
                await updateFlowState({
                    step_id: 'palmPhoto',
                    status: 'otp_verified'
                });

                // Trigger MailerLite sync (non-blocking)
                syncToMailerLite().catch(err => {
                    logError('MailerLite sync failed (non-blocking)', err);
                });

                return true;
            } else {
                throw new Error(response.message || 'Invalid verification code');
            }

        } catch (error) {
            logError('OTP verification failed', error);
            showToast(error.message || 'Invalid code. Please try again.');
            return false;
        }
    }

    /**
     * Verify OTP via magic link token (auto-validation from email click)
     */
    async function verifyOtpWithMagicLink(token, leadFromUrl) {
        if (magicLinkHandled) {
            return;
        }

        const leadId = leadFromUrl || apiState.leadId;
        if (!token || !leadId) {
            return;
        }

        log('Attempting magic link verification...', { leadId });
        showMagicOverlay();
        if (typeof showToast === 'function') {
            showToast('Verifying your magic link. Please wait...', 'info');
        }
        const verifyStart = performance.now();

        try {
            const response = await makeApiRequest('flow/magic/verify', 'POST', {
                lead_id: leadId,
                token: token
            });
            const verifyDurationMs = Math.round(performance.now() - verifyStart);
            log('Magic link verification completed', { durationMs: verifyDurationMs });

            if (response.success) {
                const leadData = response.data && response.data.lead ? response.data.lead : null;
                const reading = response.data && response.data.reading ? response.data.reading : null;
                const flowState = response.data && response.data.flow ? response.data.flow : null;
                apiState.leadId = (leadData && leadData.id) || leadId;
                apiState.otpVerified = true;
                apiState.otpSent = true;
                appState.userData.emailVerified = true;
                magicLinkHandled = true;

                // Prefill lead data from backend snapshot or cached data
                const cached = readLeadCache();
                const source = leadData || (cached && cached.leadId === leadId ? cached : null);

                if (source) {
                    appState.userData.email = source.email || appState.userData.email;
                    appState.userData.name = source.name || appState.userData.name;
                    appState.userData.identity = source.identity || appState.userData.identity;
                    appState.userData.age = source.age || appState.userData.age;
                    appState.userData.ageRange = source.age_range || source.ageRange || appState.userData.ageRange;
                    appState.userData.gdprConsent = source.gdpr ?? source.gdprConsent ?? appState.userData.gdprConsent;

                    persistLeadCache({
                        leadId: apiState.leadId,
                        email: appState.userData.email,
                        name: appState.userData.name,
                        identity: appState.userData.identity,
                        age: appState.userData.age,
                        ageRange: appState.userData.ageRange,
                        gdprConsent: appState.userData.gdprConsent
                    });
                }
                if (appState.userData.email) {
                    sessionStorage.setItem('sm_email', appState.userData.email);
                }

                log('✓ Magic link verified successfully');
                showToast('Email verified automatically. Continue to the next step.', 'success');

                // If reading already exists, render it immediately and skip the flow.
                if (reading && reading.exists && reading.reading_html) {
                    log('Existing reading found via magic link payload, rendering immediately', { readingId: reading.reading_id });
                    if (window.appContent) {
                        window.appContent.innerHTML = reading.reading_html;
                        ensureSwipeTemplateAssets();
                    } else {
                        log('Cannot render reading HTML; #app-content not available.');
                    }

                    // CRITICAL: Verify reading container exists before marking as loaded
                    const verifyContainer = getReadingResultContainer();
                    if (!verifyContainer) {
                        logError('Magic link: Reading HTML injected but result container not found - skipping state save');
                        // Don't set reading loaded flag if container missing
                        return false;
                    }

                    sessionStorage.setItem('sm_reading_lead_id', apiState.leadId);
                    sessionStorage.setItem('sm_reading_token', token);
                    sessionStorage.setItem('sm_reading_loaded', 'true');
                    markReportUrl();

                    // Hide navigation and update progress to last step
                    if (window.backBtn) backBtn.style.display = 'none';
                    if (window.nextBtn) nextBtn.style.display = 'none';
                    const lastStepIndex = palmReadingConfig.steps.length - 1;
                    appState.currentStep = lastStepIndex;
                    if (typeof updateProgressBar === 'function') {
                        updateProgressBar();
                    }

                    // Fire teaser loaded event
                    if (!teaserEventDispatched) {
                        const teaserEvent = new CustomEvent('sm:teaser_loaded');
                        document.dispatchEvent(teaserEvent);
                        teaserEventDispatched = true;
                        log('Dispatched sm:teaser_loaded (magic link).');
                    }
                    hideMagicOverlay();
                    return true;
                }

                if (flowState && flowState.step_id && jumpToStepWhenReady(flowState.step_id)) {
                    enforceStepAfterInit(flowState.step_id);
                    setTimeout(hideMagicOverlay, 300);
                    return true;
                }

                if (jumpToStepWhenReady('palmPhoto')) {
                    enforceStepAfterInit('palmPhoto');
                    setTimeout(hideMagicOverlay, 300);
                    return true;
                }

                if (jumpToStepWhenReady('emailVerification')) {
                    setTimeout(() => {
                        if (typeof window.goToNextStep === 'function') {
                            window.goToNextStep();
                        }
                    }, 150);
                    setTimeout(hideMagicOverlay, 300);
                }

                return true;
            } else {
                throw new Error(response.message || 'Magic link verification failed');
            }
        } catch (error) {
            magicLinkHandled = true; // Avoid repeated failing retries on load
            logError('Magic link verification failed', error);
            resetFlowState(true);
            showToast((error.message || 'Link invalid or expired. Please enter the code manually.') + ' Starting over...', 'error');
            hideMagicOverlay();
            return false;
        }
    }

    /**
     * Sync to MailerLite
     * Called after OTP verification (non-blocking)
     */
    async function syncToMailerLite() {
        log('Syncing to MailerLite...');

        try {
            const response = await makeApiRequest('mailerlite/sync', 'POST', {
                lead_id: apiState.leadId
            });

            if (response.success) {
                log('✓ MailerLite sync successful');
            } else {
                // Don't throw - this is non-blocking
                logError('MailerLite sync returned error (non-blocking)', response.message);
            }

        } catch (error) {
            // Don't throw - this is non-blocking
            logError('MailerLite sync failed (non-blocking)', error);
        }
    }

    /**
     * Upload Palm Image
     * Called when user captures or uploads palm photo
     */
    async function uploadPalmImage(imageDataUrl) {
        log('Uploading aura photo...');

        try {
            const response = await makeApiRequest('image/upload', 'POST', {
                lead_id: apiState.leadId,
                image: imageDataUrl
            });

            if (response.success && response.data && response.data.image_url) {
                apiState.imageUploaded = true;
                log('✓ Palm image uploaded', { url: response.data.image_url });

                // Update flow state - image uploaded, ready for quiz
                await updateFlowState({
                    step_id: 'quiz',
                    status: 'otp_verified'
                });

                return response.data.image_url;
            } else {
                throw new Error('Invalid response from image upload');
            }

        } catch (error) {
            logError('Image upload failed', error);
            showToast(error.message || 'Failed to upload aura photo. Please try again.');
            return null;
        }
    }

    /**
     * Fetch dynamic quiz questions based on demographics
     * Called before entering the quiz flow
     */
    async function fetchDynamicQuestions(ageRange, gender) {
        const params = new URLSearchParams();
        if (apiState.leadId) {
            params.append('lead_id', apiState.leadId);
        }
        params.append('age_range', ageRange);
        params.append('gender', gender);

        try {
            const response = await makeApiRequest(`quiz/questions?${params.toString()}`, 'GET');

            const questions = response.questions || (response.data && response.data.questions);

            if (response.success && Array.isArray(questions)) {
                apiState.dynamicQuestions = questions;
                apiState.demographics = { ageRange, gender };
                appState.dynamicQuestions = questions;
                log('✓ Dynamic questions fetched', { count: questions.length, ageRange, gender });
                return questions;
            }

            throw new Error('Invalid response from questions endpoint');
        } catch (error) {
            logError('Fetch dynamic questions failed', error);
            showToast(error.message || 'Failed to load your personalized questions. Please try again.');
            return null;
        }
    }

    /**
     * Save Quiz Responses
     * Called after all quiz questions are answered
     */
    async function saveQuiz(quizResponses) {
        log('Saving quiz responses...', quizResponses);

        const hasDynamicQuestions = Array.isArray(appState.dynamicQuestions) && appState.dynamicQuestions.length > 0;
        let payload = null;

        if (hasDynamicQuestions) {
            const demographics = apiState.demographics.ageRange && apiState.demographics.gender
                ? apiState.demographics
                : getDemographics();

            if (!demographics.ageRange || !demographics.gender) {
                showToast('Please select your age range and how you identify to continue.');
                return false;
            }

            const normalizedQuestions = appState.dynamicQuestions.slice(0, 4);
            if (normalizedQuestions.length !== 4) {
                logError('Dynamic question count invalid', { count: normalizedQuestions.length });
                showToast('We had trouble loading your personalized questions. Please go back and try again.');
                return false;
            }

            // DEBUG: Log all question IDs and available response keys
            log('[DEBUG] Dynamic questions:', normalizedQuestions.map((q, i) => ({
                index: i,
                id: q.id,
                question_id: q.question_id,
                type: q.type
            })));
            log('[DEBUG] Available quiz response keys:', Object.keys(quizResponses));

            const questionsPayload = normalizedQuestions.map((question, idx) => {
                const questionId = question.id || question.question_id || `quiz_${idx + 1}`;
                const questionType = question.type || question.question_type || 'multiple_choice';
                const responseKey = resolveQuestionKey(question, questionId);

                // DEBUG: Log the key resolution process
                log(`[DEBUG] Question ${idx + 1} key resolution:`, {
                    question_id_field: question.id,
                    question_question_id_field: question.question_id,
                    resolved_key: responseKey,
                    has_response: typeof quizResponses[responseKey] !== 'undefined'
                });

                const rawAnswer = typeof quizResponses[responseKey] !== 'undefined'
                    ? quizResponses[responseKey]
                    : quizResponses[questionId] || quizResponses[`quiz${idx + 1}`];

                // DEBUG: Log raw answer for this question
                log(`[DEBUG] Question ${idx + 1} raw answer:`, {
                    responseKey,
                    rawAnswer,
                    rawAnswerType: typeof rawAnswer,
                    isUndefined: typeof rawAnswer === 'undefined',
                    isNull: rawAnswer === null,
                    isEmpty: rawAnswer === ''
                });

                const normalizedOptions = Array.isArray(question.options)
                    ? question.options.map(opt => {
                        if (typeof opt === 'string') return opt;
                        return opt.label || opt.value || opt.id || '';
                    }).filter(Boolean)
                    : [];

                let answerValue = rawAnswer;

                if ((questionType === 'multi_select' || questionType === 'multiple_choice_multi') && !Array.isArray(answerValue)) {
                    answerValue = answerValue ? [String(answerValue)] : [];
                } else if (Array.isArray(answerValue)) {
                    answerValue = answerValue.map(val => val.toString());
                } else if (questionType === 'rating') {
                    answerValue = typeof answerValue === 'number' ? answerValue : Number(answerValue || 0);
                } else {
                    answerValue = typeof answerValue === 'undefined' || answerValue === null ? '' : answerValue.toString();
                }

                // Validate answer is not empty
                const isEmptyAnswer = (Array.isArray(answerValue) && answerValue.length === 0) ||
                                    (!Array.isArray(answerValue) && (answerValue === '' || answerValue === 0 && questionType !== 'rating'));

                if (isEmptyAnswer) {
                    logError(`Question ${idx + 1} has empty answer`, {
                        questionId: responseKey,
                        questionText: question.question || question.question_text,
                        answerValue,
                        availableKeys: Object.keys(quizResponses)
                    });
                }

                let category = question.category || '';
                if (!category && Array.isArray(question.category_map) && Array.isArray(question.options)) {
                    const optionLabels = question.options.map(opt => (typeof opt === 'string' ? opt : (opt.label || opt.value || opt.id || '')));
                    const answerIndex = optionLabels.findIndex(label => label === answerValue);
                    if (answerIndex >= 0 && question.category_map[answerIndex]) {
                        category = question.category_map[answerIndex];
                    }
                }

                return {
                    position: question.position || idx + 1,
                    question_id: questionId,
                    question_text: question.question || question.question_text || '',
                    question_type: questionType,
                    category: category,
                    options: normalizedOptions,
                    answer: answerValue
                };
            });

            payload = {
                demographics: {
                    age_range: demographics.ageRange,
                    gender: demographics.gender
                },
                questions: questionsPayload,
                selected_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
            };
        } else {
            // Transform frontend quiz keys (quiz1, quiz2, etc.) to backend keys
            const transformedAnswers = {
                energy: quizResponses.quiz1,        // Question 1: How would you describe your energy lately?
                focus: quizResponses.quiz2,         // Question 2: What is your biggest focus right now?
                element: quizResponses.quiz3,       // Question 3: Which element resonates with you most?
                intentions: quizResponses.quiz4,    // Question 4: What do you seek guidance on? (multi-select)
                future_goals: quizResponses.quiz5   // Question 5: Share an intention or wish for your future (text)
            };

            log('Transformed quiz answers:', transformedAnswers);
            payload = transformedAnswers;
        }

        try {
            const response = await makeApiRequest('quiz/save', 'POST', {
                lead_id: apiState.leadId,
                answers: payload
            });

            if (response.success) {
                apiState.quizSaved = true;
                log('✓ Quiz responses saved');

                // Update flow state - quiz saved, ready for reading generation
                await updateFlowState({
                    step_id: 'resultLoading',
                    status: 'quiz_completed'
                });

                return true;
            } else {
                throw new Error('Invalid response from quiz save');
            }

        } catch (error) {
            logError('Quiz save failed', error);
            showToast(error.message || 'Failed to save your responses. Please try again.');
            return false;
        }
    }

    function getPreferredReadingType() {
        if (window.smData && window.smData.isLoggedIn && window.smData.accountIntegrationEnabled) {
            return 'aura_full';
        }
        return 'aura_teaser';
    }

    /**
     * Generate AI Reading
     * Called during result loading animation
     */
    async function generateReading() {
        log('Generating AI reading...');
        const usePaidFlow = !!(window.smData && window.smData.isLoggedIn && window.smData.accountIntegrationEnabled);
        const endpoint = usePaidFlow ? 'reading/generate-paid' : 'reading/generate';
        const readingType = usePaidFlow ? 'aura_full' : 'aura_teaser';
        try {
            if (!apiState.imageUploaded && appState.userData && appState.userData.palmImage) {
                log('Palm image not uploaded yet. Uploading before reading generation...');
                const imageUrl = await uploadPalmImage(appState.userData.palmImage);
                if (!imageUrl) {
                    throw new Error('Please upload your aura photo first.');
                }
            }

            const response = await makeApiRequest(endpoint, 'POST', {
                lead_id: apiState.leadId,
                async: true,
                reading_type: readingType
            });

            let readingData = response && response.data ? response.data : null;
            if (response.success && readingData && readingData.status === 'processing') {
                updateLoadingNote('This can take a minute. We will email you when it is ready.');
                readingData = await pollReadingStatus(apiState.leadId, readingType);
            }

            if (response.success && readingData && readingData.reading_html) {
                apiState.readingGenerated = true;
                if (window.clearLoadingTimers) {
                    window.clearLoadingTimers();
                }
                log('✓ AI reading generated');
                if (readingData.reading_id) {
                    sessionStorage.setItem('sm_existing_reading_id', readingData.reading_id);
                }

                // Update flow state - reading generated successfully
                await updateFlowState({
                    step_id: 'result',
                    status: 'reading_ready',
                    reading_id: readingData.reading_id
                });

                // Cache reading metadata (but DON'T set sm_reading_loaded yet)
                // The rendering function will set it AFTER validating the container exists
                sessionStorage.setItem('sm_reading_lead_id', apiState.leadId);
                sessionStorage.setItem('sm_existing_reading_id', readingData.reading_id);

                log('🔥 READING GENERATED - About to mark URL with lead_id:', apiState.leadId);
                markReportUrl();
                log('🔥 URL marked. Current URL:', window.location.href);
                return readingData.reading_html;
            } else {
                throw new Error('Invalid response from reading generation');
            }

        } catch (error) {
            logError('Reading generation failed', error);
            clearLoadingEmailNote();
            const redirectUrl = extractRedirectUrl(error);
            const errorCode = error && error.data ? error.data.error_code : null;
            if (errorCode === 'palm_image_invalid') {
                if (window.clearLoadingTimers) {
                    window.clearLoadingTimers();
                }
                if (window.showReadingErrorState) {
                    window.showReadingErrorState(error.message || 'We could not clearly see your aura photo. Please upload a clearer image.');
                }

                const payload = error && error.data && error.data.data ? error.data.data : null;
                const retryRemaining = payload && Number.isFinite(payload.retry_remaining) ? payload.retry_remaining : 0;
                const retryBtn = document.querySelector('.loading-error-actions .btn-primary');
                const subtext = document.querySelector('.loading-subtext');
                const nextBtn = document.getElementById('next-btn');
                const backBtn = document.getElementById('back-btn');

                if (nextBtn) {
                    nextBtn.dataset.smHiddenByPalmError = 'true';
                    nextBtn.style.display = 'none';
                }
                if (backBtn) {
                    backBtn.disabled = true;
                }

                if (retryBtn) {
                    retryBtn.style.display = retryRemaining > 0 ? '' : 'none';
                    if (retryRemaining > 0 && !retryBtn.dataset.smPalmRetryBound) {
                        retryBtn.dataset.smPalmRetryBound = 'true';
                        retryBtn.addEventListener('click', async (event) => {
                            event.preventDefault();
                            event.stopImmediatePropagation();

                            apiState.imageUploaded = false;
                            apiState.readingGenerated = false;
                            appState.userData.palmImage = null;

                            if (nextBtn && nextBtn.dataset.smHiddenByPalmError === 'true') {
                                nextBtn.style.display = '';
                                delete nextBtn.dataset.smHiddenByPalmError;
                            }
                            if (backBtn) {
                                backBtn.disabled = false;
                            }

                            await updateFlowState({
                                step_id: 'palmPhoto',
                                status: 'image_retry'
                            });

                            if (jumpToStepWhenReady('palmPhoto')) {
                                enforceStepAfterInit('palmPhoto');
                            }
                        }, true);
                    }
                }

                if (subtext && retryRemaining <= 0) {
                    subtext.textContent = 'We could not verify your aura photo after multiple attempts. Please contact support if you need help.';
                }

                if (nextBtn && retryRemaining <= 0) {
                    nextBtn.disabled = true;
                }
                if (backBtn && retryRemaining <= 0) {
                    backBtn.disabled = true;
                }
                return null;
            }
            if (errorCode === 'image_not_found') {
                if (window.clearLoadingTimers) {
                    window.clearLoadingTimers();
                }
                if (window.showReadingErrorState) {
                    window.showReadingErrorState(error.message || 'Please upload your aura photo first.');
                }

                const retryBtn = document.querySelector('.loading-error-actions .btn-primary');
                const nextBtn = document.getElementById('next-btn');
                const backBtn = document.getElementById('back-btn');

                if (nextBtn) {
                    nextBtn.dataset.smHiddenByPalmError = 'true';
                    nextBtn.style.display = 'none';
                }
                if (backBtn) {
                    backBtn.disabled = true;
                }

                if (retryBtn && !retryBtn.dataset.smPalmRetryBound) {
                    retryBtn.dataset.smPalmRetryBound = 'true';
                    retryBtn.addEventListener('click', async (event) => {
                        event.preventDefault();
                        event.stopImmediatePropagation();

                        apiState.imageUploaded = false;
                        apiState.readingGenerated = false;
                        appState.userData.palmImage = null;

                        if (nextBtn && nextBtn.dataset.smHiddenByPalmError === 'true') {
                            nextBtn.style.display = '';
                            delete nextBtn.dataset.smHiddenByPalmError;
                        }
                        if (backBtn) {
                            backBtn.disabled = false;
                        }

                        await updateFlowState({
                            step_id: 'palmPhoto',
                            status: 'image_retry'
                        });

                        if (jumpToStepWhenReady('palmPhoto')) {
                            enforceStepAfterInit('palmPhoto');
                        }
                    }, true);
                }
                return null;
            }
            if (redirectUrl && (errorCode === 'credits_exhausted' || errorCode === 'reading_exists')) {
                window.location.href = redirectUrl;
                return null;
            }
            if (window.showReadingErrorState) {
                window.showReadingErrorState(error.message || 'Failed to generate your reading. Please try again.');
            }
            showToast(error.message || 'We could not generate your reading. Please try again or contact support.');
            return null;
        }
    }

    async function pollReadingStatus(leadId, readingType) {
        const maxAttempts = 60;
        const delayMs = 5000; // Poll every 5 seconds per Phase 5 spec

        log(`[SM Polling] Starting job status polling (max ${maxAttempts * delayMs / 1000}s)`);

        for (let attempt = 0; attempt < maxAttempts; attempt++) {
            const response = await makeApiRequest('reading/status', 'POST', {
                lead_id: leadId,
                reading_type: readingType
            });

            // Job completed successfully - return reading data
            if (response && response.success && response.data) {
                if (response.data.status === 'ready' && response.data.reading_html) {
                    log('[SM Polling] Job completed - reading ready');
                    return response.data;
                }
                if (response.data.status === 'processing') {
                    log(`[SM Polling] Job still processing (attempt ${attempt + 1}/${maxAttempts})`);
                    await sleep(delayMs);
                    continue;
                }
                if (response.data.status === 'not_found') {
                    logError('[SM Polling] Job not found');
                    throw new Error('No reading is currently processing.');
                }
            }

            // Job failed - backend returned error response
            if (response && !response.success) {
                const errorMsg = response.message || 'Reading generation failed.';
                const errorCode = response.error_code ? response.error_code : 'generation_error';
                logError('[SM Polling] Job failed', { error_code: errorCode, message: errorMsg });

                // Re-throw the error with backend error data preserved
                const error = new Error(errorMsg);
                error.data = response;
                throw error;
            }

            // Unexpected response - wait and retry
            log(`[SM Polling] Unexpected response (attempt ${attempt + 1}/${maxAttempts})`);
            await sleep(delayMs);
        }

        logError('[SM Polling] Polling timeout after max attempts');
        throw new Error('Reading generation is taking longer than expected. Please try again.');
    }

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Update the primary loading subtext message
     * Stops the rotating messages and shows a static message
     */
    function updateLoadingNote(message) {
        if (!message) {
            return false;
        }

        const subtext = document.querySelector('.loading-subtext');
        if (!subtext) {
            return false;
        }

        // Stop message rotation if active
        if (window.appState && window.appState.loadingMessageInterval) {
            clearInterval(window.appState.loadingMessageInterval);
            window.appState.loadingMessageInterval = null;
        }

        subtext.textContent = message;
        return true;
    }

    function updateLoadingEmailNote(message) {
        if (!message) {
            return false;
        }

        const subtext = document.querySelector('.loading-subtext');
        if (!subtext) {
            return false;
        }

        let note = document.querySelector('.loading-email-note');
        if (!note) {
            note = document.createElement('p');
            note.className = 'loading-subtext loading-email-note';
            note.style.marginTop = '8px';
            note.style.textAlign = 'center';
            subtext.insertAdjacentElement('afterend', note);
        }
        note.textContent = message;
        return true;
    }

    function clearLoadingEmailNote() {
        const note = document.querySelector('.loading-email-note');
        if (note) {
            note.remove();
        }
    }

    function updateLoadingEmailNoteWhenReady(message) {
        if (!message) {
            return;
        }

        if (updateLoadingEmailNote(message)) {
            return;
        }

        let attempts = 0;
        const maxAttempts = 20;
        const interval = setInterval(() => {
            attempts += 1;
            if (updateLoadingEmailNote(message) || attempts >= maxAttempts) {
                clearInterval(interval);
            }
        }, 250);
    }

    // ========================================
    // INTEGRATION HOOKS
    // ========================================

    /**
     * Hook into the existing goToNextStep function
     * This intercepts navigation to trigger API calls at the right times
     */
    const originalGoToNextStep = window.goToNextStep;

    window.goToNextStep = async function () {
        if (apiState.processingRequest) {
            log('Request already in progress. Ignoring additional click.');
            return;
        }

        const currentStep = palmReadingConfig.steps[appState.currentStep];
        const currentStepId = currentStep.id;
        const nextBtn = document.getElementById('next-btn');
        let toggledLoading = false;

        const startLoading = () => {
            if (nextBtn && typeof setButtonLoading === 'function' && !toggledLoading) {
                setButtonLoading(nextBtn, true);
                toggledLoading = true;
            }
        };

        const stopLoading = () => {
            if (toggledLoading && nextBtn && typeof setButtonLoading === 'function') {
                setButtonLoading(nextBtn, false);
            }
        };

        log(`Intercepted goToNextStep - Current step: ${currentStepId}`);

        // OPTIMISTIC UI: Removed blocking check - navigate first, handle API calls in background
        // This prevents the "frozen" feeling on mobile when API calls are slow

        try {
            // Step 2: Lead Capture → Create lead AND Send OTP immediately
            if (currentStepId === 'leadCapture') {
                apiState.processingRequest = true;
                startLoading();

                // Create lead first
                const leadResult = await createLead(
                    appState.userData.name,
                    appState.userData.email,
                    appState.userData.identity,
                    appState.userData.gdprConsent
                );

                if (!leadResult) {
                    return; // Block navigation if lead creation failed
                }

                if (leadResult.existingReading) {
                    stopLoading();
                    apiState.processingRequest = false;
                    return; // We already rendered the reading; halt normal flow
                }

                if (!leadResult.leadCreated) {
                    return; // Block navigation if lead creation failed
                }

                // Send OTP immediately after lead creation
                const otpSuccess = await sendOtp(appState.userData.email);
                if (!otpSuccess) {
                    return; // Block navigation if OTP send failed
                }
            }

            // Step 3: Email Loading → Just animation, OTP already sent
            // No API call needed here, OTP was sent in previous step

            // Step 4: Email Verification → Verify OTP before proceeding
            if (currentStepId === 'emailVerification' && !apiState.otpVerified) {
                apiState.processingRequest = true;
                startLoading();

                // Get OTP code from inputs
                const codeInputs = document.querySelectorAll('.code-input');
                let otpCode = '';
                codeInputs.forEach(input => {
                    otpCode += input.value;
                });

                if (otpCode.length !== 4) {
                    showToast('Please enter all 4 digits');
                    return; // Block navigation
                }

                const success = await verifyOtp(otpCode);
                if (!success) {
                    return; // Block navigation if OTP verification failed
                }
            }

            // Step 5: Palm Photo → Validate image exists, then navigate immediately
            // Upload and question fetching happen in background (non-blocking)
            if (currentStepId === 'palmPhoto') {
                log('Checking aura photo...', {
                    hasPalmImage: !!appState.userData.palmImage,
                    imageUploaded: apiState.imageUploaded,
                    imageLength: appState.userData.palmImage ? appState.userData.palmImage.length : 0
                });

                // Validate that an image was captured
                if (!appState.userData.palmImage) {
                    showToast('Please capture or upload your aura photo first');
                    return; // Block navigation if no image
                }

                // NOTE: Image upload and question fetching moved to background
                // We navigate immediately to prevent mobile "frozen" feeling
                // Background tasks continue after navigation in handleBackgroundPalmPhotoTasks()
            }

            // Step 10: Last Quiz Question → Save quiz before going to result loading
            const quizStepIds = palmReadingConfig.steps.filter(s => s.type === 'quizQuestion').map(s => s.id);
            const lastQuizStepId = quizStepIds[quizStepIds.length - 1];

            if (currentStepId === lastQuizStepId && !apiState.quizSaved) {
                apiState.processingRequest = true;
                startLoading();

                const success = await saveQuiz(appState.quizResponses);
                if (!success) {
                    return; // Block navigation if quiz save failed
                }
            }

            // Step 11: Result Loading → Generate AI reading before showing result
            if (currentStepId === 'resultLoading') {
                if (apiState.readingStartRequested && !apiState.readingGenerated) {
                    return; // Reading already in progress, wait for completion.
                }
                if (!apiState.readingGenerated) {
                    apiState.processingRequest = true;
                    startLoading();

                    const readingHtml = await generateReading();

                    if (!readingHtml) {
                        return; // Block navigation if reading generation failed
                    }

                    // Replace the fake reading with the real one
                    // Override the generateReading function in palmReadingConfig
                    palmReadingConfig.result.generateReading = function () {
                        return readingHtml;
                    };
                }
            }

            // Call original navigation function
            originalGoToNextStep();

            // PHASE 1.3: Handle background tasks after navigation (non-blocking)
            // This prevents the "frozen" feeling on mobile
            if (currentStepId === 'palmPhoto') {
                // Upload image and fetch questions in background
                handleBackgroundPalmPhotoTasks().catch(err => {
                    logError('Background palm photo tasks failed (non-blocking)', err);
                    showToast('Some data may not have saved. Your reading will use default questions.', 'warning', 3000);
                });
            }
        } catch (error) {
            logError('Navigation blocked due to error', error);
            showToast(error.message || 'Something went wrong. Please try again.', 'error', 4000);
        } finally {
            apiState.processingRequest = false;
            stopLoading();
        }
    };

    /**
     * Handle palm photo background tasks (non-blocking)
     * Uploads image and fetches personalized questions after user has navigated to quiz
     */
    async function handleBackgroundPalmPhotoTasks() {
        try {
            // Upload image if not already uploaded
            if (!apiState.imageUploaded && appState.userData.palmImage) {
                log('Background: Uploading aura photo...');
                const imageUrl = await uploadPalmImage(appState.userData.palmImage);
                if (imageUrl) {
                    log('Background: Palm image uploaded successfully');
                } else {
                    logError('Background: Palm image upload failed (non-blocking)');
                }
            }

            // Fetch personalized questions if not already fetched
            if (!apiState.dynamicQuestions.length) {
                const demographics = getDemographics();
                log('Background: Fetching dynamic questions...', demographics);

                // Fallback to safe defaults if demographics missing
                if (!demographics.ageRange) {
                    demographics.ageRange = 'age_26_35';
                    appState.userData.ageRange = demographics.ageRange;
                }
                if (!demographics.gender) {
                    demographics.gender = 'prefer_not_to_say';
                    appState.userData.identity = 'prefer-not';
                }

                const questions = await fetchDynamicQuestions(demographics.ageRange, demographics.gender);
                if (questions && questions.length > 0) {
                    log('Background: Dynamic questions fetched successfully');
                    // Reset quiz answers when new questions load
                    appState.quizResponses = {};
                    // Note: UI will continue using static questions if user already started answering
                } else {
                    logError('Background: Dynamic questions fetch failed (non-blocking)');
                }
            }
        } catch (error) {
            logError('Background palm photo tasks error (non-blocking)', error);
            // Don't throw - this is non-blocking
        }
    }

    /**
     * Start-new flow for logged-in users (skip email/OTP and jump to camera).
     *
     * @returns {Promise<boolean>} True if handled, false otherwise.
     */
    async function bootstrapStartNewFlow() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('start_new') !== '1') {
            return false;
        }

        log('Start-new flag detected - loading profile details.');

        try {
            const response = await makeApiRequest('lead/current?start_new=1', 'GET');
            if (!response.success || !response.data || !response.data.lead) {
                throw new Error(response.message || 'Unable to load your profile.');
            }

            const lead = response.data.lead;
            apiState.leadId = lead.id || null;
            apiState.otpVerified = true;
            apiState.otpSent = true;

            appState.userData = {
                name: lead.name || '',
                email: lead.email || '',
                identity: lead.identity || '',
                age: lead.age || '',
                ageRange: lead.age_range || '',
                gdprConsent: !!lead.gdpr,
                palmImage: null,
                emailVerified: true
            };

            if (lead.email) {
                sessionStorage.setItem('sm_email', lead.email);
            }

            const removeStartNewParam = () => {
                try {
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.delete('start_new');
                    window.history.replaceState({}, document.title, newUrl.toString());
                } catch (e) {
                    logError('Failed to clean start_new param', e);
                }
            };

            const missingFields = response.data.missing_fields || [];
            const gdprOnlyMissing = missingFields.length === 1 && missingFields[0] === 'gdpr';
            log('Start-new profile status', {
                profileComplete: !!response.data.profile_complete,
                missingFields,
                gdprOnlyMissing
            });
            if (response.data.profile_complete || gdprOnlyMissing) {
                const palmPhotoIndex = palmReadingConfig.steps.findIndex(s => s.id === 'palmPhoto');
                if (palmPhotoIndex >= 0) {
                    window.renderStep(palmPhotoIndex);
                    setTimeout(() => {
                        window.renderStep(palmPhotoIndex);
                    }, 250);
                }
                removeStartNewParam();
                return true;
            }

            const leadCaptureIndex = palmReadingConfig.steps.findIndex(s => s.id === 'leadCapture');
            if (leadCaptureIndex >= 0) {
                window.renderStep(leadCaptureIndex);
                setTimeout(() => {
                    window.renderStep(leadCaptureIndex);
                }, 250);
            }
            showToast('Please confirm your details to continue.', 'info');
            removeStartNewParam();
            return true;
        } catch (error) {
            logError('Start-new flow failed', error);
            showToast(error.message || 'Please enter your details to continue.', 'error');
            return false;
        }
    }

    // ========================================
    // MAGIC LINK & PAGE LOAD HANDLERS
    // ========================================

    async function handleReportRefresh() {
        const params = new URLSearchParams(window.location.search);
        if (sessionStorage.getItem('sm_logout_in_progress') === '1') {
            return false;
        }
        if (!params.has('sm_report')) {
            return false; // Not a report refresh
        }

        log('⭐ REPORT REFRESH DETECTED - Forcing report load');

        // Check if reading is already rendered in DOM
        const existingReport = getReadingResultContainer();
        if (existingReport && existingReport.querySelector('[data-reading-id]')) {
            log('Reading already visible in DOM, skipping report refresh');
            return true; // Report already handled
        }

        // Try to get lead_id from multiple sources (in order of preference)
        const urlLeadId = params.get('lead_id') || params.get('lead');
        const cachedLead = readLeadCache();
        const sessionLeadId = sessionStorage.getItem('sm_reading_lead_id');
        const leadId = urlLeadId || (cachedLead && cachedLead.leadId) || sessionLeadId;

        log('Lead ID sources:', { urlLeadId, cachedLeadId: cachedLead?.leadId, sessionLeadId, finalLeadId: leadId });

        if (!leadId) {
            logError('CRITICAL: Report refresh requested but NO lead_id found in URL, cache, or session!');
            // Don't fail - let it fall through to prevent infinite loops
            return false;
        }

        try {
            showMagicOverlay();
            log(`Fetching report for lead ID: ${leadId}`);
            const storedReadingType = sessionStorage.getItem('sm_reading_type');
            const urlReadingType = params.get('reading_type');
            const readingType = urlReadingType || storedReadingType || getPreferredReadingType();
            const response = await makeApiRequest(`reading/get-by-lead?lead_id=${leadId}&reading_type=${readingType}`, 'GET');

            if (response.success && response.data.exists && response.data.reading_html) {
                log('✅ Successfully refetched report. Rendering...');
                renderExistingReading(response.data.reading_html, response.data.reading_id, leadId, response.data.reading_type || readingType || 'aura_teaser');
                hideMagicOverlay();
                return true; // Report handled
            } else {
                throw new Error('Report data not found in response.');
            }
        } catch (error) {
            logError('❌ Failed to reload report.', error);
            if (handleApiRedirect(error)) {
                hideMagicOverlay();
                return true;
            }
            hideMagicOverlay();

            // CRITICAL: Clear reading state when report refresh fails
            // This prevents the page from trying to render a non-existent reading
            log('Clearing reading state flags since report refresh failed');
            sessionStorage.removeItem('sm_reading_loaded');
            sessionStorage.removeItem('sm_reading_lead_id');
            sessionStorage.removeItem('sm_reading_token');
            sessionStorage.removeItem('sm_existing_reading_id');

            // Return false to let normal flow continue (will start from welcome step)
            return false;
        }
    }

    async function fetchFlowState() {
        try {
            const response = await makeApiRequest('flow/state', 'GET');
            if (response && response.success && response.data) {
                return response.data;
            }
        } catch (error) {
            // Flow state is not critical - fail silently if endpoint unavailable
            log('Flow state fetch failed (non-critical), continuing without it');
        }
        return null;
    }

    /**
     * Update flow state on the backend
     * Phase C: Write-Through - Keep backend state synchronized
     */
    async function updateFlowState(updates) {
        try {
            log('Updating flow state...', updates);
            const response = await makeApiRequest('flow/state', 'POST', updates);
            if (response && response.success) {
                log('Flow state updated successfully', response.data);
                return response.data;
            } else {
                log('Flow state update failed (non-critical), continuing');
            }
        } catch (error) {
            // Flow state is not critical - fail silently if endpoint unavailable
            log('Flow state update error (non-critical), continuing');
        }
        return null;
    }

    async function handleFlowStateBootstrap() {
        if (sessionStorage.getItem('sm_logout_in_progress') === '1') {
            return false;
        }
        if (!window.smData || !window.smData.isLoggedIn) {
            return false;
        }
        const flow = await fetchFlowState();
        if (!flow || !flow.step_id) {
            return false;
        }

        if (flow.lead_id) {
            apiState.leadId = flow.lead_id;
        }

        if (flow.status === 'reading_ready' && flow.lead_id) {
            const params = new URLSearchParams(window.location.search);
            const hasReportFlag = params.has('sm_report');
            const storedReadingLoaded = sessionStorage.getItem('sm_reading_loaded') === 'true';
            if (!hasReportFlag && !storedReadingLoaded) {
                return false;
            }
            // Check if reading is already rendered in DOM before fetching
            const existingReport = getReadingResultContainer();
            if (existingReport && existingReport.querySelector('[data-reading-id]')) {
                log('Reading already visible in DOM, skipping flow state bootstrap');
                return true;
            }

            try {
                showMagicOverlay();
                const readingType = getPreferredReadingType();
                const response = await makeApiRequest(`reading/get-by-lead?lead_id=${flow.lead_id}&reading_type=${readingType}`, 'GET');
                if (response.success && response.data.exists && response.data.reading_html) {
                    renderExistingReading(response.data.reading_html, response.data.reading_id, flow.lead_id, response.data.reading_type || readingType || 'aura_teaser');
                    hideMagicOverlay();
                    return true;
                }
        } catch (error) {
            logError('Flow reading reload failed', error);
            if (handleApiRedirect(error)) {
                hideMagicOverlay();
                return true;
            }
        }
            hideMagicOverlay();
        }

        const blockedSteps = ['result', 'resultLoading'];
        if (flow.status !== 'reading_ready' && blockedSteps.includes(flow.step_id)) {
            return false;
        }

        if (flow.step_id && flow.step_id !== 'welcome') {
            if (jumpToStepWhenReady(flow.step_id)) {
                enforceStepAfterInit(flow.step_id);
                return true;
            }
        }

        return false;
    }


    document.addEventListener('DOMContentLoaded', () => {
        (async () => {
            try {
                const reportContainer = getReadingResultContainer();
                if (!reportContainer) {
                    const modal = document.getElementById('sm-modal') || document.querySelector('.sm-modal');
                    if (modal) {
                        modal.remove();
                    }
                }

                showAuthErrorToast();
                wrapRenderStepForRestore();

                // --- EMERGENCY FIX: Check for infinite loop guard ---
                // Uses counter-based detection: requires 5+ rapid refreshes within 500ms to trigger
                const loopGuard = sessionStorage.getItem('sm_loop_guard');
                const currentTime = Date.now();
                if (loopGuard) {
                    try {
                        const guardData = JSON.parse(loopGuard);
                        const timeSinceLastLoad = currentTime - guardData.timestamp;

                        // If within 500ms of last load, increment counter
                        if (timeSinceLastLoad < 500) {
                            guardData.count = (guardData.count || 1) + 1;
                            guardData.timestamp = currentTime;

                            // Only block if 5+ rapid refreshes
                            if (guardData.count >= 5) {
                                logError(`⚠️ INFINITE LOOP DETECTED - Stopping refresh cycle (${guardData.count} rapid refreshes within 500ms)`);
                                sessionStorage.removeItem('sm_loop_guard');
                                return; // Stop all processing to break the loop
                            }

                            sessionStorage.setItem('sm_loop_guard', JSON.stringify(guardData));
                        } else {
                            // More than 500ms since last load, reset counter
                            sessionStorage.setItem('sm_loop_guard', JSON.stringify({ timestamp: currentTime, count: 1 }));
                        }
                    } catch (e) {
                        // Invalid JSON, reset guard
                        sessionStorage.setItem('sm_loop_guard', JSON.stringify({ timestamp: currentTime, count: 1 }));
                    }
                } else {
                    // First load, initialize counter
                    sessionStorage.setItem('sm_loop_guard', JSON.stringify({ timestamp: currentTime, count: 1 }));
                }
                // --- END LOOP GUARD ---

                // --- FIX for report refresh loop ---
                const reportRefreshed = await handleReportRefresh();
                if (reportRefreshed) {
                    sessionStorage.removeItem('sm_loop_guard'); // Clear guard on success
                    return; // Stop further processing if report was handled
                }
                // --- END FIX ---

                const startNewHandled = await bootstrapStartNewFlow();
                if (startNewHandled) {
                    return;
                }

                const appContentEl = document.getElementById('app-content');

            const params = new URLSearchParams(window.location.search);
            const hasMagic = params.get('sm_magic');
            const token = params.get('token');
            const lead = params.get('lead');
            const cached = readLeadCache();

            // Preload cached lead/user data to avoid missing demographics after magic link
            if (cached) {
                apiState.leadId = cached.leadId || apiState.leadId;
            }

            // --- FIX for Email Persistence on OTP page refresh ---
            // If appState.userData.email is empty but sm_email exists in sessionStorage, restore it.
            // This handles cases where the page refreshes directly on the OTP step.
            if (!appState.userData.email && sessionStorage.getItem('sm_email')) {
                appState.userData.email = sessionStorage.getItem('sm_email');
                log('Restored appState.userData.email from sessionStorage.', { email: appState.userData.email });
            }
            // --- END FIX ---

            if (hasMagic && token && lead) {
                if (!appContentEl) {
                    log('Magic link detected but app container missing; skipping auto-verify.');
                    return;
                }
                // If lead param missing but cached exists, prefer cached lead id
                const leadId = lead || (cached ? cached.leadId : null);
                verifyOtpWithMagicLink(token, leadId);
                return;
            }

            const flowHandled = await handleFlowStateBootstrap();
            if (flowHandled) {
                return;
            }
            restoreStepFromSession();
            } catch (error) {
                logError('Magic link parse failed', error);
            }
        })();
    });

    window.addEventListener('pageshow', (event) => {
        // Only handle browser back/forward navigation (persisted cache)
        // Don't run on normal page loads (those are handled by DOMContentLoaded)
        if (!event.persisted) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const hasReportFlag = params.has('sm_report');
        if (!hasReportFlag) {
            return;
        }

        // Check if reading is already visible in DOM before bootstrapping
        const existingReport = getReadingResultContainer();
        if (existingReport && existingReport.querySelector('[data-reading-id]')) {
            log('Reading already visible, skipping pageshow bootstrap');
            return;
        }

        log('Pageshow event with back/forward navigation, bootstrapping flow');
        handleFlowStateBootstrap();
    });

    /**
     * Hook into goToPreviousStep to reset state when going back to welcome
     */
    const originalGoToPreviousStep = window.goToPreviousStep;

    window.goToPreviousStep = function () {
        try {
            // Call original function first
            originalGoToPreviousStep();

            // If we went back to the welcome page (step 0), reset API state
            // This ensures that if user goes back to start, they can proceed forward cleanly
            if (appState.currentStep === 0) {
                log('Returned to welcome page - resetting API state for fresh start');

                // Reset all API flags (but keep processingRequest check to prevent race conditions)
                apiState.leadId = null;
                apiState.otpVerified = false;
                apiState.imageUploaded = false;
                apiState.quizSaved = false;
                apiState.readingGenerated = false;
                apiState.dynamicQuestions = [];
                apiState.demographics = { ageRange: '', gender: '' };
                appState.dynamicQuestions = [];
                appState.quizResponses = {};
                magicLinkHandled = false; // Allow new magic links
                teaserEventDispatched = false; // Reset the flag here as well

                // Also reset user data to clear the form
                appState.userData = {
                    name: '',
                    email: '',
                    identity: '',
                    age: '',
                    ageRange: '',
                    gdprConsent: false,
                    palmImage: null,
                    emailVerified: false
                };

                log('API state reset complete');
            }
        } catch (error) {
            logError('Error in goToPreviousStep wrapper:', error);
            // Still call original to prevent breaking navigation
        }
    };

    /**
     * OTP verification is now handled inline in the goToNextStep wrapper above
     * No need for separate button interception
     */

    // ========================================
    // INITIALIZATION
    // ========================================

    log('API Integration loaded successfully');

    // Debug smData availability
    if (typeof smData === 'undefined') {
        logError('CRITICAL: smData is undefined! WordPress localization failed.');
        return;
    }

    if (!smData.nonce) {
        logError('CRITICAL: smData.nonce is missing!');
        return;
    }

    log('Backend API URL:', smData.apiUrl);
    log('Nonce:', smData.nonce.substring(0, 10) + '...');
    log('Full nonce for debugging:', smData.nonce);

    // Expose API state for debugging
    window.smApiState = apiState;
    window.smApiMethods = {
        createLead,
        sendOtp,
        verifyOtp,
        uploadPalmImage,
        fetchDynamicQuestions,
        saveQuiz,
        generateReading
    };

    log('API methods exposed on window.smApiMethods for debugging');

    // Dashboard UI event handlers
    document.addEventListener('DOMContentLoaded', () => {
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                log('Logout button clicked');
                sessionStorage.setItem('sm_logout_in_progress', '1');
                logoutBtn.disabled = true;
                clearClientSession();
                clearReportUrlParams();
                try {
                    const response = await makeApiRequest('auth/logout', 'POST');
                    resetFlowState(false);
                    if (response.success && response.data.redirect_url) {
                        sessionStorage.removeItem('sm_logout_in_progress');
                        window.location.replace(response.data.redirect_url);
                    } else {
                        // Fallback redirect
                        sessionStorage.removeItem('sm_logout_in_progress');
                        window.location.replace(smData.homeUrl || '/');
                    }
                } catch (error) {
                    logError('Logout failed', error);
                    showToast('Logout failed. Please try again.', 'error');
                    resetFlowState(false);
                    // Force redirect even on failure
                    sessionStorage.removeItem('sm_logout_in_progress');
                    window.location.replace(smData.homeUrl || '/');
                }
            });
        }

        const generateNewReadingBtn = document.getElementById('generate-new-reading-btn');
        if (generateNewReadingBtn) {
            generateNewReadingBtn.addEventListener('click', async () => {
                log('Generate New Reading button clicked');
                setButtonLoading(generateNewReadingBtn, true);

                try {
                    const response = await makeApiRequest('reading/start-new', 'GET');
                    if (response.success && response.data.proceed) {
                        // User has credits, proceed to start the flow
                        window.location.href = response.data.next_step_url || (smData.homeUrl || '/aura-reading');
                    } else {
                        throw new Error('Could not verify credits.');
                    }
                } catch (error) {
                    const redirectUrl = extractRedirectUrl(error);
                    if (redirectUrl) {
                        // No credits, redirect to shop
                        showToast(error.message || 'You need more credits to start a new reading.', 'error');
                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 2000);
                    } else {
                        // Generic error
                        logError('Generate New Reading failed', error);
                        showToast(error.message || 'An error occurred. Please try again.', 'error');
                        setButtonLoading(generateNewReadingBtn, false);
                    }
                }
            });
        }
    });

})();

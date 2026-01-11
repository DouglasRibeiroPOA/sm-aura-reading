/**
 * Teaser Reading Initialization
 *
 * This script initializes all interactive elements in the aura reading teaser template.
 * It listens for a custom event 'sm:teaser_loaded' which is fired after the
 * teaser HTML is injected into the DOM by the main application script.
 */
var smStorage = window.smStorage || (() => {
  const scopedKeys = new Set([
    'sm_reading_loaded',
    'sm_reading_lead_id',
    'sm_reading_token',
    'sm_existing_reading_id',
    'sm_email',
    'sm_flow_step_id',
    'sm_lead_cache',
    'sm_reading_type',
    'sm_paywall_redirect',
    'sm_paywall_return_url',
    'sm_loop_guard',
    'sm_logout_in_progress',
    'sm_dynamic_questions',
    'sm_dynamic_demographics'
  ]);

  const getContext = () => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('sm_magic') === '1') {
      return 'magic';
    }
    if (params.get('sm_flow_auth') === '1') {
      return 'auth';
    }
    if (params.get('start_new') === '1') {
      return 'auth';
    }
    if (typeof smData !== 'undefined' && smData.isLoggedIn) {
      return 'auth';
    }
    return 'guest';
  };

  const context = getContext();
  const key = (base) => (scopedKeys.has(base) ? `${context}:${base}` : base);

  const get = (base) => {
    const scoped = key(base);
    let value = sessionStorage.getItem(scoped);
    if (value === null && scopedKeys.has(base)) {
      const legacy = sessionStorage.getItem(base);
      if (legacy !== null) {
        sessionStorage.setItem(scoped, legacy);
        sessionStorage.removeItem(base);
        value = legacy;
      }
    }
    return value;
  };

  const set = (base, value) => {
    sessionStorage.setItem(key(base), value);
  };

  const remove = (base) => {
    sessionStorage.removeItem(key(base));
    if (scopedKeys.has(base)) {
      sessionStorage.removeItem(base);
    }
  };

  return { context, key, get, set, remove };
})();

window.smStorage = smStorage;

function smClearReadingSession() {
  try {
    smStorage.remove('sm_reading_loaded');
    smStorage.remove('sm_reading_lead_id');
    smStorage.remove('sm_reading_token');
    smStorage.remove('sm_existing_reading_id');
    smStorage.remove('sm_email');
    smStorage.remove('sm_flow_step_id');
    smStorage.remove('sm_lead_cache');
  } catch (error) {
    console.warn('[SM Teaser] Failed to clear reading session:', error);
  }
}

window.addEventListener('pageshow', function () {
  if (smStorage.get('sm_paywall_redirect') === '1') {
    smStorage.remove('sm_paywall_redirect');
    const returnUrl = smStorage.get('sm_paywall_return_url');
    smStorage.remove('sm_paywall_return_url');
    if (returnUrl && !window.location.search.includes('sm_report=1')) {
      window.location.replace(returnUrl);
    }
  }
});

function getReadingResultContainer() {
  return document.getElementById('aura-reading-result') || document.getElementById('palm-reading-result');
}

function smInitReadingInteractions() {
  console.log('[SM Teaser] Event sm:teaser_loaded received. Initializing interactions...');

  const resultContainer = getReadingResultContainer();
  if (!resultContainer) {
    console.error('[SM Teaser] Initialization failed: result container not found in the DOM.');
    return;
  }
  if (resultContainer.dataset.smTeaserInitialized === '1') {
    console.log('[SM Teaser] Initialization skipped: already initialized for this render.');
    return;
  }
  resultContainer.dataset.smTeaserInitialized = '1';
  const readingType = resultContainer.dataset.readingType || 'aura_teaser';
  const isFullReport = readingType === 'aura_full';

  // Set current date
  const dateElement = resultContainer.querySelector('.current-date');
  if (dateElement) {
    const now = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    dateElement.textContent = now.toLocaleDateString('en-US', options);
  }

  // Animate trait bars on scroll into view
  const observerOptions = { threshold: 0.3, rootMargin: '0px 0px -50px 0px' };
  const traitObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        traitObserver.unobserve(entry.target);
      }
    });
  }, observerOptions);

  const traitBars = resultContainer.querySelectorAll('.trait-fill');
  traitBars.forEach(bar => traitObserver.observe(bar));

  // Modal handling
  // Modal lives outside the result container; grab it from either context and relocate to body to avoid transform stacking quirks.
  let modal = resultContainer.querySelector('.sm-modal') || document.getElementById('sm-modal');
  if (modal && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  const fallbackModalCopy = {
    lovePatterns: {
      badge: 'Mini Reading',
      title: 'Love, Relationships & Emotional Connection',
      body: 'This is a quick reflection. Unlock the full reading to explore deeper emotional patterns and connection themes.',
      hint: 'Your full report connects the dots between every section.',
      showCta: false,
      showCounter: false
    },
    careerDirection: {
      badge: 'Mini Reading',
      title: 'Life Direction, Success & Material Flow',
      body: 'This is a quick reflection. Unlock the full report to reveal your direction, momentum, and practical flow.',
      hint: 'Upgrade to unlock every section and timeline.',
      showCta: false,
      showCounter: false
    },
    lifeAlignment: {
      badge: 'Mini Reading',
      title: 'Energy Level & Flow',
      body: 'This is a quick reflection. The full reading shows how your energy moves and where it can be restored.',
      hint: 'Unlock the full report to see everything at once.',
      showCta: false,
      showCounter: false
    },
    emotionalState: {
      badge: 'Mini Reading',
      title: 'Emotional State & Inner Climate',
      body: 'This is a quick reflection. The full reading explores your emotional climate and the tone behind it.',
      hint: 'Unlock the full report to connect the full picture.',
      showCta: false,
      showCounter: false
    },
    spiritualMemory: {
      badge: 'Mini Reading',
      title: 'Spiritual Memory & Deeper Patterns',
      body: 'This is a quick reflection. The full reading reveals the deeper patterns shaping your aura.',
      hint: 'Upgrade to uncover the deeper layers.',
      showCta: false,
      showCounter: false
    },
    intentionsGrowth: {
      badge: 'Mini Reading',
      title: 'Intentions, Healing & Growth',
      body: 'This is a quick reflection. The full reading highlights your healing focus and growth intentions.',
      hint: 'Unlock the full report for all growth insights.',
      showCta: false,
      showCounter: false
    },
    limitReached: {
      badge: 'Free Unlocks Used',
      title: 'You have reached your free unlock limit',
      body: 'You have revealed all free sections. Upgrade to unlock the full report and see every hidden insight instantly.',
      hint: 'Full access includes premium sections, timelines, and guidance.',
      ctaText: 'Unlock Full Report',
      showCta: true,
      showCounter: true
    },
    premiumLocked: {
      badge: 'Premium Insight',
      title: 'This section is premium-only',
      body: 'These insights are part of the full reading experience. Unlock the complete report to access premium sections and deeper guidance.',
      hint: 'Upgrade now to see the full story behind your aura.',
      ctaText: 'View Plans',
      showCta: true,
      showCounter: true
    }
  };

  const modalContent = {
    ...fallbackModalCopy,
    lovePatterns: {
      ...fallbackModalCopy.lovePatterns,
      title: 'Love, Relationships & Emotional Connection (Quick Reflection)',
      body: resultContainer.dataset.modalLove || fallbackModalCopy.lovePatterns.body
    },
    careerDirection: {
      ...fallbackModalCopy.careerDirection,
      title: 'Life Direction, Success & Material Flow (Quick Reflection)',
      body: resultContainer.dataset.modalCareer || fallbackModalCopy.careerDirection.body
    },
    lifeAlignment: {
      ...fallbackModalCopy.lifeAlignment,
      title: 'Energy Level & Flow (Quick Reflection)',
      body: resultContainer.dataset.modalAlignment || fallbackModalCopy.lifeAlignment.body
    },
    emotionalState: {
      ...fallbackModalCopy.emotionalState,
      title: 'Emotional State & Inner Climate (Quick Reflection)',
      body: resultContainer.dataset.modalEmotional || fallbackModalCopy.emotionalState.body
    },
    spiritualMemory: {
      ...fallbackModalCopy.spiritualMemory,
      title: 'Spiritual Memory & Deeper Patterns (Quick Reflection)',
      body: resultContainer.dataset.modalSpiritual || fallbackModalCopy.spiritualMemory.body
    },
    intentionsGrowth: {
      ...fallbackModalCopy.intentionsGrowth,
      title: 'Intentions, Healing & Growth (Quick Reflection)',
      body: resultContainer.dataset.modalIntentions || fallbackModalCopy.intentionsGrowth.body
    }
  };

  const root = document.documentElement;
  let scrollY = 0; // Store scroll position when modal opens
  let openedOnMobile = false; // Track whether modal opened on mobile sizing

  const unlockCountRaw = resultContainer.dataset.unlockCount || '0';
  const maxFreeUnlocksRaw = resultContainer.dataset.maxFreeUnlocks || '2';
  let unlockCount = Number.parseInt(unlockCountRaw, 10);
  let maxFreeUnlocks = Number.parseInt(maxFreeUnlocksRaw, 10);
  if (!Number.isFinite(unlockCount)) {
    unlockCount = 0;
  }
  if (!Number.isFinite(maxFreeUnlocks)) {
    maxFreeUnlocks = 2;
  }

  function getUnlocksRemaining() {
    if (!Number.isFinite(unlockCount) || !Number.isFinite(maxFreeUnlocks)) {
      return 0;
    }
    return Math.max(0, maxFreeUnlocks - unlockCount);
  }

  function formatUnlockStatus() {
    const remaining = getUnlocksRemaining();
    if (remaining <= 0) {
      return "You've reached your unlock limit";
    }
    if (remaining === 1) {
      return 'You have 1 unlock remaining';
    }
    return `You have ${remaining} unlocks remaining`;
  }

  function formatUnlockBadge() {
    if (!Number.isFinite(maxFreeUnlocks)) {
      return 'Free unlocks available';
    }
    const remaining = getUnlocksRemaining();
    return `Free unlocks: ${remaining} of ${maxFreeUnlocks} remaining`;
  }

  function updateUnlockIndicators() {
    if (!Number.isFinite(maxFreeUnlocks) || hasFullAccess) {
      return;
    }
    const badgeText = formatUnlockBadge();
    const badges = resultContainer.querySelectorAll('[data-unlock-badge]');
    badges.forEach((badge) => {
      badge.textContent = badgeText;
    });
  }

  function updateModalCounter() {
    if (!modal) return;
    const counterWrap = modal.querySelector('[data-unlock-counter]');
    if (!counterWrap) return;
    const labelEl = counterWrap.querySelector('.sm-modal__counter-label');
    const valueEl = counterWrap.querySelector('[data-unlock-remaining]');
    if (labelEl && Number.isFinite(maxFreeUnlocks)) {
      labelEl.textContent = `You can unlock up to ${maxFreeUnlocks} sections`;
    }
    if (valueEl) {
      valueEl.textContent = formatUnlockStatus();
    }
  }

  function openModal(key) {
    if (!modal || !modalContent[key]) return;
    const content = modalContent[key];
    const badgeEl = modal.querySelector('#sm-modal-badge');
    const titleEl = modal.querySelector('.sm-modal__title');
    const bodyEl = modal.querySelector('.sm-modal__body');
    const hintEl = modal.querySelector('#sm-modal-hint');
    const ctaButton = modal.querySelector('[data-cta]');
    const counterWrap = modal.querySelector('[data-unlock-counter]');
    if (badgeEl && content.badge) badgeEl.textContent = content.badge;
    if (titleEl) titleEl.textContent = content.title;
    if (bodyEl) bodyEl.textContent = content.body;
    if (hintEl) hintEl.textContent = content.hint || '';

    if (ctaButton) {
      if (content.showCta) {
        const ctaLabel = content.ctaText || 'View Plans';
        ctaButton.innerHTML = `<i class="fas fa-crown"></i> ${ctaLabel}`;
        ctaButton.style.display = '';
      } else {
        ctaButton.style.display = 'none';
      }
    }

    if (counterWrap) {
      if (content.showCounter) {
        counterWrap.style.display = 'inline-flex';
        updateModalCounter();
      } else {
        counterWrap.style.display = 'none';
      }
    }

    if (isFullReport) {
      if (ctaButton) {
        ctaButton.style.display = 'none';
      }
      if (counterWrap) {
        counterWrap.style.display = 'none';
      }
      if (hintEl) {
        hintEl.textContent = '';
      }
      const secondaryBtn = modal ? modal.querySelector('.sm-modal__secondary') : null;
      if (secondaryBtn) {
        secondaryBtn.textContent = 'Close';
      }
      if (badgeEl && (!content.badge || content.badge === 'Mini Reading')) {
        badgeEl.textContent = 'Quick Insight';
      }
    }

    // Store current scroll position before any adjustments
    scrollY = window.scrollY || window.pageYOffset || 0;
    openedOnMobile = window.innerWidth <= 768;

    // On mobile: Scroll to top smoothly before showing modal to ensure visibility
    if (openedOnMobile && scrollY > 0) {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
      // Wait for scroll to settle before showing modal
      setTimeout(() => {
        showModal();
      }, 320);
    } else {
      showModal();
    }
  }

  function lockScroll() {
    if (openedOnMobile) {
      // Mobile: avoid fixed positioning issues, just lock overflow on both roots
      root.style.overflow = 'hidden';
      document.body.style.overflow = 'hidden';
    } else {
      // Desktop: preserve position while locking scroll
      document.body.style.position = 'fixed';
      document.body.style.top = `-${scrollY}px`;
      document.body.style.width = '100%';
      document.body.style.overflow = 'hidden';
      root.style.overflow = 'hidden';
    }
  }

  function unlockScroll() {
    if (openedOnMobile) {
      root.style.overflow = '';
      document.body.style.overflow = '';
    } else {
      document.body.style.position = '';
      document.body.style.top = '';
      document.body.style.width = '';
      document.body.style.overflow = '';
      root.style.overflow = '';
    }

    // Restore scroll position
    window.scrollTo(0, scrollY);
  }

  function showModal() {
    // Lock scroll to prevent background movement
    lockScroll();
    modal.classList.add('is-open');
    // Note: Blur is now handled purely by CSS backdrop-filter on .sm-modal__backdrop
    // No need to add/remove blur classes on parent elements
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('is-open');

    // Restore scroll + overflow state
    unlockScroll();
    // Note: No blur removal needed - handled by CSS
  }

  // Attach modal triggers using event delegation on the container
  resultContainer.addEventListener('click', function(e) {
    const trigger = e.target.closest('.symbol-btn');
    if (trigger) {
      openModal(trigger.getAttribute('data-modal'));
    }
  });

  // Close modal on backdrop click or close button
  if (modal) {
    modal.addEventListener('click', (e) => {
      const closer = e.target ? e.target.closest('[data-close]') : null;
      if (closer) {
        closeModal();
      }
    });
  }

  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
      closeModal();
    }
  });

  if (isFullReport) {
    return;
  }

  // --- Unlock Logic ---

  const readingId = resultContainer.dataset.readingId;
  const leadId = resultContainer.dataset.leadId;
  const offeringsRedirect = (resultContainer.dataset.offeringsUrl && resultContainer.dataset.offeringsUrl.trim())
    || (window.smData && window.smData.offeringsUrl ? window.smData.offeringsUrl : '');
  const isLoggedIn = resultContainer.dataset.isLoggedIn === '1';
  const hasFullAccess = resultContainer.dataset.hasFullAccess === '1';
  const nonce = window.smData ? window.smData.nonce : null;
  const unlockedSectionsRaw = resultContainer.dataset.unlockedSections || '';
  const unlockedSections = new Set();

  // Debugging: Log the values to see what's missing
  console.log('[SM Teaser] Initializing Unlock Logic...');
  console.log('[SM Teaser] readingId from dataset:', readingId);
  console.log('[SM Teaser] leadId from dataset:', leadId);
  console.log('[SM Teaser] Nonce from smData:', nonce);
  console.log('[SM Teaser] offeringsRedirect resolved:', offeringsRedirect);
  if (!offeringsRedirect) {
    console.error('[SM Teaser] Missing offerings redirect URL. Check smData.offeringsUrl or template data attribute.');
  }

  function redirectToOfferings(reason, targetUrl) {
    const destination = (targetUrl && String(targetUrl).trim()) ? String(targetUrl).trim() : offeringsRedirect;
    if (!destination) {
      console.error('[SM Teaser] Redirect suppressed (missing offerings URL).', reason || '');
      return;
    }
    smStorage.set('sm_paywall_redirect', '1');
    smStorage.set('sm_paywall_return_url', window.location.href);
    window.location.href = destination;
  }

  // 1. Initialize state from DOM on load
  function initializeUnlockState() {
    const sections = resultContainer.querySelectorAll('[data-lock]');
    sections.forEach(section => {
      if (!section.classList.contains('locked')) {
        const key = section.dataset.lock;
        if (key) {
          unlockedSections.add(key);
        }
      }
    });
    console.log('[SM Teaser] Initial unlocked sections:', [...unlockedSections]);
  }

  function normalizeUnlockKey(key) {
    const map = {
      life_phase: 'phase',
      'deep-love': 'deep_relationship_analysis',
      purpose: 'life_purpose_soul_mission',
      shadow: 'shadow_work_transformation'
    };
    return map[key] || key;
  }

  function applyUnlockedSectionsFromDataset() {
    if (!unlockedSectionsRaw) {
      return;
    }
    const keys = unlockedSectionsRaw.split(',').map(key => key.trim()).filter(Boolean);
    keys.forEach(key => {
      const normalized = normalizeUnlockKey(key);
      unlockSection(normalized);
      unlockedSections.add(normalized);
    });
  }

  // 2. Visual unlock function (reused from original)
  function unlockSection(key) {
    const section = resultContainer.querySelector(`[data-lock="${key}"]`);
    if (!section) {
      console.warn('[SM Teaser] Section to unlock not found:', key);
      return;
    }
    
    if (!section.classList.contains('locked')) {
        console.log('[SM Teaser] Section already visually unlocked:', key);
        return; // Already unlocked
    }

    console.log('[SM Teaser] Visually unlocking section:', key);
    section.classList.remove('locked');
    const overlay = section.querySelector('.lock-overlay');
    if (overlay) {
       overlay.style.transition = 'opacity 0.3s ease-out';
       overlay.style.opacity = '0';
       setTimeout(() => overlay.remove(), 300);
    }
  }

  // 3. API-driven unlock handler
  async function handleUnlockAttempt(key, buttonEl) {
    if (!key) {
      console.warn('[SM Teaser] Unlock key missing on button.');
      return;
    }

    if (!readingId || !nonce) {
      console.error('[SM Teaser] Missing readingId or nonce. Cannot proceed with unlock. Redirecting to offerings page.');
      redirectToOfferings('missing_reading_or_nonce');
      return;
    }

    if (unlockedSections.has(key)) {
      console.log('[SM Teaser] Section already unlocked:', key);
      return;
    }

    buttonEl.classList.add('is-loading');
    buttonEl.disabled = true;

    try {
      const response = await fetch('/wp-json/soulmirror/v1/reading/unlock', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce
        },
        body: JSON.stringify({
          reading_id: readingId,
          lead_id: leadId,
          section_name: key
        }),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      console.log('[SM Teaser] Unlock API response:', result);

      // Check for backend-level success and the presence of the data object
      if (result.success && result.data) {
        const data = result.data;
        if (data.status === 'unlocked') {
          unlockSection(key);
          unlockedSections.add(key);
          if (Number.isFinite(unlockCount)) {
            unlockCount += 1;
          }
          updateUnlockIndicators();
        } else if (data.status === 'limit_reached') {
          console.log('[SM Teaser] Unlock limit reached. Showing modal...');
          openModal('limitReached');
        } else if (data.status === 'premium_locked') {
          console.log('[SM Teaser] Premium section requires purchase. Showing modal...');
          openModal('premiumLocked');
        } else if (data.status === 'already_unlocked') {
          console.log('[SM Teaser] Section was already unlocked on backend.');
          unlockSection(key); // Ensure UI is consistent
          unlockedSections.add(key);
        } else if (data.status === 'unlocked_all') {
          console.log('[SM Teaser] User has full access (purchased reading).');
          unlockSection(key); // Unlock the requested section
          unlockedSections.add(key);
          updateUnlockIndicators();
        } else {
          // Handle cases where data.status is something unexpected
          console.warn('[SM Teaser] Unknown unlock status:', data.status, data);
          throw new Error(data.message || 'An unknown success response was received.');
        }
      } else {
        // Handle cases where success is false or data is missing
        throw new Error(result.message || 'An unknown error occurred.');
      }

    } catch (error) {
      console.error('[SM Teaser] Unlock request failed:', error);
      console.log('[SM Teaser] Redirecting to offerings page due to error.');
      redirectToOfferings('unlock_error');
    } finally {
      buttonEl.classList.remove('is-loading');
      buttonEl.disabled = false;
    }
  }

  // 4. Attach unlock button listeners
  console.log('[SM Teaser] Setting up API-driven unlock button listener.');
  resultContainer.addEventListener('click', function(e) {
      const unlockButton = e.target.closest('.btn-unlock, .unlock-btn');
      if (unlockButton && !unlockButton.classList.contains('is-loading')) {
          e.preventDefault();
          e.stopPropagation();
          const key = unlockButton.getAttribute('data-unlock');
          console.log('[SM Teaser] Unlock button clicked for section:', key);
          handleUnlockAttempt(key, unlockButton);
      }
  });

  // 5. Run initialization
  initializeUnlockState();
  applyUnlockedSectionsFromDataset();
  updateUnlockIndicators();

  // Report CTA setup
  const reportCta = resultContainer.querySelector('[data-report-cta]');
  const reportCopyTitle = resultContainer.querySelector('.report-action__title');
  const reportCopyBody = resultContainer.querySelector('.report-action__body');
  if (reportCta) {
    if (isLoggedIn && hasFullAccess) {
      reportCta.innerHTML = '<i class="fas fa-arrow-left"></i> Back to Dashboard';
      reportCta.href = `${window.location.origin}${window.location.pathname}`;
      if (reportCopyTitle) reportCopyTitle.textContent = 'Return to your dashboard';
      if (reportCopyBody) reportCopyBody.textContent = 'Pick up another reading or revisit your saved insights.';
    } else {
      reportCta.innerHTML = '<i class="fas fa-crown"></i> Unlock Full Report';
      reportCta.href = offeringsRedirect || '#';
      if (reportCopyTitle) reportCopyTitle.textContent = 'Go deeper with the full report';
      if (reportCopyBody) reportCopyBody.textContent = 'Unlock premium insights, full timelines, and your personalized action plan.';
    }
    reportCta.addEventListener('click', (event) => {
      if (isLoggedIn && hasFullAccess) {
        return;
      }
      event.preventDefault();
      redirectToOfferings('report_cta');
    });
  }

  if (modal) {
    const ctaButton = modal.querySelector('[data-cta]');
    if (ctaButton) {
      ctaButton.addEventListener('click', () => {
        closeModal();
        redirectToOfferings('modal_cta');
      });
    }
  }


  // Ripple effect on buttons (also using event delegation)
  resultContainer.addEventListener('click', function(e) {
      const button = e.target.closest('.action-btn');
      if (button && !button.classList.contains('is-loading')) { // Don't ripple while loading
          const ripple = document.createElement('span');
          const rect = button.getBoundingClientRect();
          const size = Math.max(rect.width, rect.height);
          const x = e.clientX - rect.left - size / 2;
          const y = e.clientY - rect.top - size / 2;

          ripple.style.cssText = `
              position: absolute;
              width: ${size}px;
              height: ${size}px;
              border-radius: 50%;
              background: rgba(255,255,255,0.5);
              animation: ripple-animation 0.6s ease-out;
              top: ${y}px;
              left: ${x}px;
              pointer-events: none;
          `;
          button.appendChild(ripple);

          setTimeout(() => ripple.remove(), 600);
      }
  });

  // Add dynamic styles for ripple and loading spinner
  if (!document.getElementById('sm-dynamic-styles')) {
    const style = document.createElement('style');
    style.id = 'sm-dynamic-styles';
    style.textContent = `
      @keyframes ripple-animation {
        to { transform: scale(4); opacity: 0; }
      }
      .btn-unlock.is-loading {
        cursor: wait !important;
        background: var(--mystic-secondary) !important;
        pointer-events: none;
      }
      .btn-unlock.is-loading > * {
        visibility: hidden;
      }
      .btn-unlock.is-loading::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin-top: -10px;
        margin-left: -10px;
        border: 3px solid rgba(255, 255, 255, 0.2);
        border-top-color: white;
        border-radius: 50%;
        animation: sm-spinner-anim 0.8s linear infinite;
      }
      @keyframes sm-spinner-anim {
        to { transform: rotate(360deg); }
      }
    `;
    document.head.appendChild(style);
  }

  console.log('[SM Teaser] Initialization complete!');
}

document.addEventListener('sm:teaser_loaded', smInitReadingInteractions);
document.addEventListener('sm:report_loaded', smInitReadingInteractions);

console.log('[SM Teaser] teaser-reading.js loaded and event listener is waiting.');

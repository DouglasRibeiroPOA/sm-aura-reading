// ========================================
// UTILITY FUNCTIONS FOR MOBILE OPTIMIZATION
// ========================================

/**
 * Debounce function to prevent rapid button clicks
 * @param {Function} func - Function to debounce
 * @param {number} wait - Delay in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Detect if user is on mobile device
 * @returns {boolean} True if mobile device
 */
const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);

/**
 * Set button loading state with spinner
 * @param {HTMLElement} button - Button element
 * @param {boolean} isLoading - Loading state
 */
function setButtonLoading(button, isLoading) {
    if (!button) return;
    if (isLoading) {
        button.dataset.originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        button.disabled = true;
        button.classList.add('loading');
    } else {
        button.innerHTML = button.dataset.originalHtml || button.innerHTML;
        button.disabled = false;
        button.classList.remove('loading');
        delete button.dataset.originalHtml;
    }
}

/**
 * Compress a base64 image to mobile-friendly dimensions/quality.
 * Falls back to the original string if anything fails.
 */
function compressImageData(imageDataUrl, quality = isMobile ? 0.6 : 0.8) {
    return new Promise(resolve => {
        const img = new Image();
        img.onload = () => {
            const maxWidth = isMobile ? 960 : 1280;
            const maxHeight = isMobile ? 720 : 960;
            let { width, height } = img;
            const scale = Math.min(maxWidth / width, maxHeight / height, 1);
            width = Math.round(width * scale);
            height = Math.round(height * scale);

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);
            try {
                const compressed = canvas.toDataURL('image/jpeg', quality);
                resolve(compressed);
            } catch (err) {
                console.error('Image compression failed, using original data', err);
                resolve(imageDataUrl);
            }
        };
        img.onerror = () => resolve(imageDataUrl);
        img.src = imageDataUrl;
    });
}

// ========================================
// Mystic Aura Reading Configuration
// ========================================
const palmReadingConfig = {
    // Theme configuration
    theme: {
        name: 'SoulMirror Aura Reading',
        primaryColor: '#A88BEB',
        accentColor: '#6FD6C5',
        textColor: '#2F2F3A'
    },

    // Steps configuration
    steps: [
        {
            id: 'welcome',
            type: 'welcome',
            title: 'Reveal Your Aura',
            subtitle: 'Discover what your aura reveals about your energy, relationships, and inner alignment.',
            backgroundStyle: 'mystic'
        },
        {
            id: 'leadCapture',
            type: 'leadCapture',
            title: 'Begin Your Reading',
            subtitle: 'Share a few details to personalize your aura reading',
            fields: {
                name: {
                    label: 'What should we call you?',
                    placeholder: 'Enter your name',
                    required: true,
                    icon: 'fas fa-user'
                },
                identity: {
                    label: 'How do you identify?',
                    type: 'radio',
                    required: true,
                    options: [
                        { id: '', label: 'Select an option...' }, // placeholder
                        { id: 'woman', label: 'Woman' },
                        { id: 'man', label: 'Man' },
                        { id: 'prefer-not', label: 'Prefer not to say – I want to keep it mysterious' }
                    ]
                },
                age: {
                    label: 'How old are you?',
                    type: 'number',
                    required: true,
                    icon: 'fas fa-hourglass-half',
                    min: 18,
                    max: 120,
                    placeholder: 'Enter your age (18+)'
            },
            gdpr: {
                label: 'I agree to receive my aura reading and related emails, and I accept the <a href="#">Privacy Policy</a> and <a href="#">Terms</a>.',
                required: true
            }
        },
            backgroundStyle: 'gradient-1'
        },
        {
            id: 'emailVerification',
            type: 'emailVerification',
            title: 'Verify Your Connection',
            subtitle: 'We\'ve sent a 4-digit verification code to your email',
            codeLength: 4,
            backgroundStyle: 'gradient-3'
        },
        {
            id: 'palmPhoto',
            type: 'palmPhoto',
            title: 'Share Your Aura Photo',
            subtitle: 'Upload a calm shoulders-up photo in good light. Your face does not need to be perfectly visible.',
            instructions: [
                'Frame from shoulders up, facing the camera',
                'Good lighting helps capture your energy clearly',
                'Keep your posture relaxed and natural',
                'Your face can be partially visible or softly lit'
            ],
            backgroundStyle: 'gradient-1'
        },
        {
            id: 'quiz1',
            type: 'quizQuestion',
            title: 'Question 1 of 4',
            question: 'How would you describe your energy lately?',
            questionType: 'singleChoice',
            options: [
                { id: 'energetic', label: 'Energetic and active', icon: 'fas fa-bolt' },
                { id: 'balanced', label: 'Balanced and calm', icon: 'fas fa-scale-balanced' },
                { id: 'creative', label: 'Creative and inspired', icon: 'fas fa-palette' },
                { id: 'reflective', label: 'Reflective and introspective', icon: 'fas fa-brain' }
            ],
            required: true,
            backgroundStyle: 'gradient-2'
        },
        {
            id: 'quiz2',
            type: 'quizQuestion',
            title: 'Question 2 of 4',
            question: 'What is your biggest focus right now?',
            questionType: 'singleChoice',
            options: [
                { id: 'career', label: 'Career and ambitions', icon: 'fas fa-briefcase' },
                { id: 'relationships', label: 'Relationships and connections', icon: 'fas fa-heart' },
                { id: 'personal', label: 'Personal growth and learning', icon: 'fas fa-seedling' },
                { id: 'health', label: 'Health and wellbeing', icon: 'fas fa-heartbeat' }
            ],
            required: true,
            backgroundStyle: 'gradient-3'
        },
        {
            id: 'quiz3',
            type: 'quizQuestion',
            title: 'Question 3 of 4',
            question: 'Which element resonates with you most?',
            questionType: 'singleChoice',
            options: [
                { id: 'fire', label: 'Fire - Passion and transformation', icon: 'fas fa-fire' },
                { id: 'water', label: 'Water - Emotion and intuition', icon: 'fas fa-water' },
                { id: 'earth', label: 'Earth - Stability and growth', icon: 'fas fa-mountain' },
                { id: 'air', label: 'Air - Communication and intellect', icon: 'fas fa-wind' }
            ],
            required: true,
            backgroundStyle: 'gradient-1'
        },
        {
            id: 'quiz4',
            type: 'quizQuestion',
            title: 'Question 4 of 4',
            question: 'Share an intention or wish for your future',
            questionType: 'text', // <-- this is the question type
            placeholder: 'Type your intention here...',
            required: false,
            backgroundStyle: 'gradient-3'
        },
        {
            id: 'resultLoading',
            type: 'resultLoading',
            title: 'Preparing Your Aura Reading',
            messages: [
                "Tuning into your energy...",
                "Reading the colors around your presence...",
                "Mapping your aura layers...",
                "Sensing emotional currents...",
                "Noticing the flow of your vitality...",
                "Aligning with your inner climate...",
                "Listening to subtle energetic patterns...",
                "Tracing where your energy feels open...",
                "Highlighting areas of calm and intensity...",
                "Observing your spiritual alignment...",
                "Translating aura signals into insight...",
                "Weaving your responses into a clear picture...",
                "Interpreting your energetic signature...",
                "Connecting with your intuitive blueprint...",
                "Attuning to your heart-centered signals...",
                "Balancing light and shadow in your aura...",
                "Clarifying your direction and momentum...",
                "Gathering symbols of growth and healing...",
                "Revealing your core aura tone...",
                "Preparing your personalized reading..."
            ],
            duration: 60000, // 60 seconds - actual duration controlled by API response
            backgroundStyle: 'mystic'
        },
        {
            id: 'result',
            type: 'result',
            title: 'Your Aura Reading',
            backgroundStyle: 'mystic'
        }

    ],

    // Final result configuration
    result: {
        generateReading: function (userData, quizResponses) {
            const readings = [
                "Your aura shows a steady core that points to resilience and grounded strength. You have the capacity to move through challenges and come out centered.",
                "A warm, open aura tone suggests deep emotional intelligence. Your connections thrive when you lead with honesty and intuition.",
                "Shifts in your energy field indicate a season of growth and discovery. New opportunities are emerging as you align with your path.",
                "A bright, focused aura highlights natural leadership and clarity. Trust your inner guidance when making important decisions.",
                "Subtle layers in your aura hint at creative gifts ready to expand. Expressive pursuits will bring you momentum and joy."
            ];

            // Select reading based on user's name (simple hash)
            const nameHash = userData.name ? userData.name.length % readings.length : 0;
            return readings[nameHash];
        },

        insights: [
            "You possess strong intuitive abilities that guide your decisions",
            "Your aura points to creative expression and fresh ideas",
            "Relationships play a significant role in your personal growth",
            "You adapt quickly as your energy shifts and evolves",
            "Your aura shows a balance between practical grounding and spiritual growth"
        ]
    }
};


const appState = {
    currentStep: 0,
    userData: {
        name: '',
        email: '',
        identity: '',
        age: '',
        ageRange: '',
        emailVerified: false,
        palmImage: null,
        gdprConsent: false
    },
    dynamicQuestions: [],
    quizResponses: {},
    isTransitioning: false,
    loadingTimer: null,
    loadingMessageInterval: null,
    cameraStream: null, // Add this line
    leadCaptureValidation: {
        touchedFields: [],
        showErrors: false
    }
};

// ========================================
// STATE PERSISTENCE (localStorage)
// ========================================

/**
 * Save current appState to localStorage for refresh persistence
 * Expires after 24 hours
 */
function saveStateToLocalStorage() {
    try {
        const stateToSave = {
            currentStep: appState.currentStep,
            userData: {
                ...appState.userData,
                palmImage: appState.userData.palmImage ? 'SAVED' : null // Don't save large image data
            },
            quizResponses: appState.quizResponses,
            timestamp: Date.now()
        };
        localStorage.setItem('sm_app_state', JSON.stringify(stateToSave));
        console.log('[SM] App state saved to localStorage');
    } catch (error) {
        console.error('[SM] Failed to save state to localStorage:', error);
    }
}

/**
 * Restore appState from localStorage if valid
 * Clears expired state (>24 hours old)
 * @returns {boolean} True if state was restored
 */
function restoreStateFromLocalStorage() {
    // NOTE: Disabled localStorage restoration as it causes issues with navigating
    // back to a completed report state. The sessionStorage flow handles refreshes
    // on the report page itself, which is sufficient for now.
    const saved = localStorage.getItem('sm_app_state');
    if (saved) {
        console.log('[SM] Found saved state in localStorage, clearing it to prevent invalid restoration.');
        localStorage.removeItem('sm_app_state');
    }
    return false;
}

/**
 * Clear saved state from localStorage
 */
function clearSavedState() {
    localStorage.removeItem('sm_app_state');
    sessionStorage.removeItem('sm_reading_loaded');
    sessionStorage.removeItem('sm_reading_lead_id');
    sessionStorage.removeItem('sm_reading_token');
    console.log('[SM] Saved state cleared');
}

// Add this function to clean up camera when leaving photo step
function cleanupCamera() {
    if (appState.cameraStream) {
        stopCamera();
        appState.cameraStream = null;
    }
}



// DOM Elements
const appContent = document.getElementById('app-content');
// Expose key DOM nodes for cross-script access (e.g., api-integration.js magic link rendering)
window.appContent = appContent;
const backBtn = document.getElementById('back-btn');
const nextBtn = document.getElementById('next-btn');
const progressFill = document.querySelector('.progress-fill');
const currentStepEl = document.querySelector('.current-step');
const stepNameEl = document.querySelector('.step-name');
const totalStepsEl = document.querySelector('.total-steps');
const toast = document.getElementById('toast');

const categoryIcons = {
    emotional_state: 'fas fa-cloud',
    energy_flow: 'fas fa-bolt',
    relationships: 'fas fa-heart',
    life_direction: 'fas fa-compass',
    spiritual_memory: 'fas fa-moon',
    intentions_growth: 'fas fa-seedling',
    default: 'fas fa-sparkles'
};

function getReadingResultContainer() {
    return document.getElementById('aura-reading-result') || getReadingResultContainer();
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

function resetLeadCaptureValidationState() {
    appState.leadCaptureValidation = {
        touchedFields: [],
        showErrors: false
    };
}

function registerLeadCaptureInteraction(fieldName) {
    if (!appState.leadCaptureValidation) {
        resetLeadCaptureValidationState();
    }

    const state = appState.leadCaptureValidation;

    if (!state.touchedFields.includes(fieldName)) {
        state.touchedFields.push(fieldName);
    }

    if (state.touchedFields.length >= 2) {
        state.showErrors = true;
    }
}

function mapQuestionType(rawType, fallback = 'singleChoice') {
    switch ((rawType || '').toLowerCase()) {
        case 'multiple_choice':
        case 'singlechoice':
            return 'singleChoice';
        case 'multi_select':
        case 'multiplechoice':
            return 'multipleChoice';
        case 'free_text':
        case 'text':
            return 'text';
        case 'rating':
            return 'rating';
        default:
            return fallback;
    }
}

function getDynamicQuestionForStep(step) {
    if (!appState.dynamicQuestions || !appState.dynamicQuestions.length) {
        return null;
    }
    const quizSteps = palmReadingConfig.steps.filter(s => s.type === 'quizQuestion');
    const idx = quizSteps.findIndex(s => s.id === step.id);
    if (idx === -1) return null;
    return appState.dynamicQuestions[idx] || null;
}

function getQuestionKey(question, fallback) {
    if (!question) return fallback;
    return question.id || question.question_id || fallback;
}

function normalizeOption(option, index, question) {
    if (typeof option === 'string') {
        return {
            value: option,
            label: option,
            icon: question && question.category_map ? categoryIcons[question.category_map[index]] || categoryIcons.default : ''
        };
    }
    return {
        value: option.id || option.value || option.label || option.text || `option_${index}`,
        label: option.label || option.text || option.value || option.id || `Option ${index + 1}`,
        icon: option.icon || (question && question.category ? categoryIcons[question.category] : '')
    };
}

// Initialize the app
function initApp() {
    // Try to restore state from localStorage (handles page refresh)
    const stateRestored = restoreStateFromLocalStorage();

    // Set total steps
    totalStepsEl.textContent = palmReadingConfig.steps.length;

    // Check if there's a flow step in sessionStorage (takes precedence over localStorage)
    const sessionStepId = sessionStorage.getItem('sm_flow_step_id');
    const storedReadingLoaded = sessionStorage.getItem('sm_reading_loaded');
    let initialStep = 0;

    if (sessionStepId) {
        if ((sessionStepId === 'result' || sessionStepId === 'resultLoading') && storedReadingLoaded !== 'true') {
            console.log('[SM] Ignoring stale result step without loaded reading');
            sessionStorage.removeItem('sm_flow_step_id');
        } else {
        // Find the step index for the stored step ID
        const stepIndex = palmReadingConfig.steps.findIndex(s => s.id === sessionStepId);
        if (stepIndex >= 0) {
            initialStep = stepIndex;
            console.log(`[SM] Restoring to step '${sessionStepId}' (index ${stepIndex}) from sessionStorage`);
        }
        }
    } else if (stateRestored) {
        initialStep = appState.currentStep;
    }

    // Render the first step (or restored step)
    renderStep(initialStep);

    // Setup event listeners - removed debouncing for instant response
    // isTransitioning flag prevents double-clicks naturally
    backBtn.addEventListener('click', goToPreviousStep);
    nextBtn.addEventListener('click', goToNextStep);

    // Handle keyboard navigation
    document.addEventListener('keydown', handleKeyboardNavigation);

    // Update progress bar
    updateProgressBar();
}

// Render a specific step
function renderStep(stepIndex) {
    if (stepIndex < 0 || stepIndex >= palmReadingConfig.steps.length) return;

    const step = palmReadingConfig.steps[stepIndex];
    appState.currentStep = stepIndex;

    // Clear current content
    appContent.innerHTML = '';

    // Create step container
    const stepContainer = document.createElement('div');
    stepContainer.className = 'app-step';
    stepContainer.setAttribute('data-step', stepIndex);
    stepContainer.setAttribute('role', 'region');
    stepContainer.setAttribute('aria-labelledby', `step-title-${stepIndex}`);

    // Render based on step type
    switch (step.type) {
        case 'welcome':
            renderWelcomeStep(stepContainer, step);
            break;
        case 'leadCapture':
            renderLeadCaptureStep(stepContainer, step);
            break;
        case 'emailLoading':
            renderEmailLoadingStep(stepContainer, step);
            break;
        case 'emailVerification':
            renderEmailVerificationStep(stepContainer, step);
            break;
        case 'palmPhoto':
            renderPalmPhotoStep(stepContainer, step);
            break;
        case 'quizQuestion':
            renderQuizQuestionStep(stepContainer, step);
            break;
        case 'resultLoading':                     // ⬅️ NEW
            renderResultLoadingStep(stepContainer, step);
            break;
        case 'result':
            renderResultStep(stepContainer, step);
            break;
    }


    appContent.appendChild(stepContainer);

    // Update navigation buttons
    updateNavigationButtons();

    // Update progress indicator
    updateProgressBar();

    // Set focus for accessibility
    setTimeout(() => {
        const stepTitle = document.querySelector('.step-title');
        if (stepTitle) {
            stepTitle.setAttribute('tabindex', '-1');
            stepTitle.focus();
        }
    }, 100);
}

/**
 * Check if a reading exists for the given email address
 * @param {string} email - Email address to check
 * @returns {Promise<object|null>} Reading data if exists, null if not
 */
async function checkExistingReadingByEmail(email) {
    try {
        const apiConfig = (typeof smData !== 'undefined' && smData.apiUrl && smData.nonce)
            ? { root: smData.apiUrl, nonce: smData.nonce }
            : (typeof wpApiSettings !== 'undefined' && wpApiSettings.root && wpApiSettings.nonce)
                ? { root: wpApiSettings.root, nonce: wpApiSettings.nonce }
                : null;

        if (!apiConfig) {
            console.error('[SM] API config missing for email check.');
            return { action: 'continue_free' }; // Default to free flow if API config is missing
        }

        const response = await fetch(
            `${apiConfig.root}reading/check-by-email`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': apiConfig.nonce
                },
                body: JSON.stringify({ email: email })
            }
        );

        if (!response.ok) {
            // If the server returns an error (e.g., 404, 500), assume we should proceed with the free flow.
            console.log('[SM] API error during email check, proceeding with free flow.');
            return { action: 'continue_free' };
        }

        const data = await response.json();

        // The backend now sends a structured response with an 'action'.
        if (data.success && data.data && data.data.action) {
            console.log(`[SM] Email check completed. Action: ${data.data.action}`);
            return data.data; // Return the whole data object { action: '...', message: '...' }
        }

        // Fallback for safety, though the backend should always respond correctly.
        return { action: 'continue_free' };
    } catch (error) {
        console.error('[SM] Error checking for existing reading:', error);
        return { action: 'continue_free' }; // On network error, default to free flow.
    }

}

// Render welcome step
function renderWelcomeStep(container, step) {
    const title = document.createElement('h1');
    title.className = 'step-title';
    title.id = `step-title-${appState.currentStep}`;
    title.textContent = step.title;
    container.appendChild(title);

    const subtitle = document.createElement('p');
    subtitle.className = 'step-subtitle';
    subtitle.textContent = step.subtitle;
    container.appendChild(subtitle);

    // Add aura welcome icon with animation
    const icon = document.createElement('div');
    icon.className = 'result-icon welcome-icon';
    icon.innerHTML = '<i class="fas fa-hands-praying"></i>';
    container.appendChild(icon);

    // Create email form
    const form = document.createElement('form');
    form.className = 'form-container welcome-form';
    form.setAttribute('novalidate', 'true');

    const emailField = document.createElement('div');
    emailField.className = 'form-field';

    const emailLabel = document.createElement('label');
    emailLabel.className = 'field-label';
    emailLabel.setAttribute('for', 'welcome-email');
    emailLabel.innerHTML = '<i class="fas fa-envelope"></i> What is your best email?';
    emailField.appendChild(emailLabel);

    const emailInput = document.createElement('input');
    emailInput.type = 'email';
    emailInput.id = 'welcome-email';
    emailInput.name = 'email';
    emailInput.className = 'field-input';
    emailInput.placeholder = 'your.email@example.com';
    emailInput.required = true;
    emailInput.autocomplete = 'email';

    // Restore email from sessionStorage if available
    const savedEmail = sessionStorage.getItem('sm_email');
    if (savedEmail) {
        emailInput.value = savedEmail;
    }

    emailField.appendChild(emailInput);

    const errorMessage = document.createElement('span');
    errorMessage.className = 'field-error';
    errorMessage.setAttribute('role', 'alert');
    emailField.appendChild(errorMessage);

    form.appendChild(emailField);

    // Action buttons
    const actions = document.createElement('div');
    actions.className = 'form-actions';

    if (typeof smData !== 'undefined' && smData.auth && smData.auth.showLoginButton && smData.auth.loginUrl) {
        const loginBtn = document.createElement('a');
        loginBtn.href = smData.auth.loginUrl;
        loginBtn.className = 'btn btn-primary sm-login-inline';

        const loginIcon = document.createElement('i');
        loginIcon.className = 'fas fa-sign-in-alt';
        loginBtn.appendChild(loginIcon);

        const loginText = document.createElement('span');
        loginText.textContent = smData.auth.loginText || 'Login / Sign Up';
        loginBtn.appendChild(loginText);

        actions.appendChild(loginBtn);
    }

    // Continue button
    const continueBtn = document.createElement('button');
    continueBtn.type = 'submit';
    continueBtn.className = 'btn btn-primary btn-large';
    continueBtn.innerHTML = '<span>Continue</span> <i class="fas fa-arrow-right"></i>';
    actions.appendChild(continueBtn);

    form.appendChild(actions);

    // Form submit handler
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const email = emailInput.value.trim();

        // Validate email
        if (!email) {
            errorMessage.textContent = 'Please enter your email address';
            emailInput.focus();
            return;
        }

        if (!isValidEmail(email)) {
            errorMessage.textContent = 'Please enter a valid email address';
            emailInput.focus();
            return;
        }

        // Clear error
        errorMessage.textContent = '';

        // Store email in sessionStorage
        sessionStorage.setItem('sm_email', email);

        // Disable submit button and show loading state
        setButtonLoading(continueBtn, true);

        // Check user status by email
        const checkResult = await checkExistingReadingByEmail(email);

        if (checkResult && checkResult.action === 'redirect_login') {
            // User has an account or a past reading, redirect to login.
            showToast(checkResult.message || 'Redirecting to login...', 3000);

            // Get login URL from localized data
            const loginUrl = (typeof smData !== 'undefined' && smData.auth && smData.auth.loginUrl) ? smData.auth.loginUrl : null;

            if (loginUrl) {
                // Redirect after a short delay to allow the toast to be seen.
                setTimeout(() => {
                    window.location.href = loginUrl;
                }, 2000);
            } else {
                showToast('Login is currently unavailable. Please try again later.', 3000, true);
                setButtonLoading(continueBtn, false);
            }
        } else if (checkResult && checkResult.action === 'continue_free') {
            // New user, proceed to the next step (lead capture).
            goToNextStep();
            setButtonLoading(continueBtn, false);
        } else {
            // Fallback or error case, proceed with free flow.
            goToNextStep();
            setButtonLoading(continueBtn, false);
        }
    });

    container.appendChild(form);
    stepNameEl.textContent = 'Welcome';
}

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Render lead capture step
function renderLeadCaptureStep(container, step) {
    const title = document.createElement('h1');
    title.className = 'step-title';
    title.textContent = step.title;
    container.appendChild(title);

    const subtitle = document.createElement('p');
    subtitle.className = 'step-subtitle';
    subtitle.textContent = step.subtitle;
    container.appendChild(subtitle);

    // Create form
    const form = document.createElement('form');
    form.className = 'form-container';
    form.setAttribute('novalidate', 'true');

    if (!appState.leadCaptureValidation) {
        resetLeadCaptureValidationState();
    }

    const handleLeadFieldFocus = (fieldName) => {
        registerLeadCaptureInteraction(fieldName);
        validateLeadCaptureForm();
    };

    // Name field
    const nameGroup = document.createElement('div');
    nameGroup.className = 'form-group';

    const nameLabel = document.createElement('label');
    nameLabel.className = 'form-label';
    nameLabel.innerHTML = `<i class="${step.fields.name.icon}"></i> ${step.fields.name.label}`;

    const nameInput = document.createElement('input');
    nameInput.type = 'text';
    nameInput.className = 'form-input';
    nameInput.placeholder = step.fields.name.placeholder;
    nameInput.required = step.fields.name.required;
    nameInput.value = appState.userData.name;

    nameInput.addEventListener('focus', () => handleLeadFieldFocus('name'));
    nameInput.addEventListener('input', () => {
        appState.userData.name = nameInput.value.trim();
        validateLeadCaptureForm();
    });

    nameGroup.appendChild(nameLabel);
    nameGroup.appendChild(nameInput);
    form.appendChild(nameGroup);

    // Retrieve email from sessionStorage (captured in welcome step)
    const savedEmail = sessionStorage.getItem('sm_email');
    if (savedEmail && !appState.userData.email) {
        appState.userData.email = savedEmail;
    }

    // Identity field
    // Identity field (dropdown version)
    const identityGroup = document.createElement('div');
    identityGroup.className = 'form-group';

    const identityLabel = document.createElement('label');
    identityLabel.className = 'form-label';
    identityLabel.innerHTML = `<i class="fas fa-user-friends"></i> ${step.fields.identity.label}`;

    const identitySelect = document.createElement('select');
    identitySelect.className = 'form-input';
    identitySelect.name = 'identity';

    // Build options
    step.fields.identity.options.forEach(option => {
        const opt = document.createElement('option');
        opt.value = option.id;
        opt.textContent = option.label;

        // Set placeholder option as selected when no value yet
        if (!appState.userData.identity && option.id === '') {
            opt.selected = true;
        }

        // Restore previous choice if user goes back
        if (appState.userData.identity === option.id) {
            opt.selected = true;
        }

        identitySelect.appendChild(opt);
    });

    identitySelect.addEventListener('focus', () => handleLeadFieldFocus('identity'));
    identitySelect.addEventListener('change', () => {
        // Ignore placeholder value ('')
        appState.userData.identity = identitySelect.value;
        validateLeadCaptureForm();
    });

    identityGroup.appendChild(identityLabel);
    identityGroup.appendChild(identitySelect);
    form.appendChild(identityGroup);

    // Age field (numeric)
    const ageGroup = document.createElement('div');
    ageGroup.className = 'form-group';

    const ageLabel = document.createElement('label');
    ageLabel.className = 'form-label';
    ageLabel.innerHTML = `<i class="${step.fields.age.icon}"></i> ${step.fields.age.label}`;

    const ageInput = document.createElement('input');
    ageInput.type = 'number';
    ageInput.className = 'form-input';
    ageInput.placeholder = step.fields.age.placeholder;
    ageInput.required = step.fields.age.required;
    ageInput.min = step.fields.age.min;
    ageInput.max = step.fields.age.max;
    ageInput.value = appState.userData.age;

    ageInput.addEventListener('focus', () => handleLeadFieldFocus('age'));
    ageInput.addEventListener('input', () => {
        const val = ageInput.value;
        appState.userData.age = val;
        appState.userData.ageRange = mapAgeToRange(val);
        validateLeadCaptureForm();
    });

    ageGroup.appendChild(ageLabel);
    ageGroup.appendChild(ageInput);
    form.appendChild(ageGroup);


    // GDPR consent
    const gdprGroup = document.createElement('div');
    gdprGroup.className = 'form-group';

    const gdprContainer = document.createElement('div');
    gdprContainer.className = 'checkbox-custom';

    const gdprCheckbox = document.createElement('div');
    gdprCheckbox.className = `checkbox-custom-input ${appState.userData.gdprConsent ? 'checked' : ''}`;
    gdprCheckbox.addEventListener('click', () => {
        registerLeadCaptureInteraction('gdpr');
        appState.userData.gdprConsent = !appState.userData.gdprConsent;
        gdprCheckbox.classList.toggle('checked', appState.userData.gdprConsent);
        validateLeadCaptureForm();
    });

    const gdprLabel = document.createElement('label');
    gdprLabel.className = 'checkbox-custom-label';
    gdprLabel.innerHTML = step.fields.gdpr.label;

    gdprContainer.appendChild(gdprCheckbox);
    gdprContainer.appendChild(gdprLabel);
    gdprGroup.appendChild(gdprContainer);
    form.appendChild(gdprGroup);

    // Error message area
    const errorDiv = document.createElement('div');
    errorDiv.className = 'form-error';
    errorDiv.style.display = 'none';
    form.appendChild(errorDiv);

    container.appendChild(form);

    stepNameEl.textContent = 'Your Details';

    // Initial validation
    validateLeadCaptureForm();

}

// Validate lead capture form
function validateLeadCaptureForm() {
    const user = appState.userData;
    let errorMessage = '';

    if (!user.name.trim()) {
        errorMessage = 'Please enter your name.';
    } else if (!user.email.trim()) {
        errorMessage = 'Please enter your email address.';
    } else if (!validateEmail(user.email)) {
        errorMessage = 'Please enter a valid email address.';
    } else if (!user.identity) {
        errorMessage = 'Please select how you identify.';
    } else if (!user.age || isNaN(Number(user.age)) || Number(user.age) < 18) {
        errorMessage = 'Please enter your age (18+).';
    } else if (!user.gdprConsent) {
        errorMessage = 'Please accept the terms so we can send your reading.';
    }

    const validationState = appState.leadCaptureValidation || { showErrors: false };
    const errorDiv = document.querySelector('.form-error');
    if (errorDiv) {
        const shouldShowErrors = validationState.showErrors && !!errorMessage;
        errorDiv.textContent = shouldShowErrors ? errorMessage : '';
        errorDiv.style.display = shouldShowErrors ? 'block' : 'none';
    }

    nextBtn.disabled = !!errorMessage;
}


// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Render email loading step
function renderEmailLoadingStep(container, step) {
    const title = document.createElement('h1');
    title.className = 'step-title';
    title.textContent = step.title;
    container.appendChild(title);

    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';
    container.appendChild(spinner);

    const loadingText = document.createElement('p');
    loadingText.className = 'loading-text';
    loadingText.textContent = step.messages[0];
    container.appendChild(loadingText);

    const subtext = document.createElement('p');
    subtext.className = 'loading-subtext';
    subtext.textContent = `Verification code will be sent to ${appState.userData.email}`;
    container.appendChild(subtext);

    stepNameEl.textContent = 'Verifying';

    // Disable navigation during loading
    backBtn.disabled = true;
    nextBtn.disabled = true;

    // Simulate API call with timeout
    let messageIndex = 0;
    const messageInterval = setInterval(() => {
        messageIndex = (messageIndex + 1) % step.messages.length;
        loadingText.textContent = step.messages[messageIndex];
    }, 1500);

    appState.loadingTimer = setTimeout(() => {
        clearInterval(messageInterval);
        goToNextStep();
    }, step.duration);
}

// Render email verification step
function renderEmailVerificationStep(container, step) {
    const title = document.createElement('h1');
    title.className = 'step-title';
    title.textContent = step.title;
    container.appendChild(title);

    const subtitle = document.createElement('p');
    subtitle.className = 'step-subtitle';
    subtitle.textContent = `${step.subtitle} (${appState.userData.email})`;
    container.appendChild(subtitle);

    // Create code inputs
    const codeContainer = document.createElement('div');
    codeContainer.className = 'verification-container';

    const codeInputs = document.createElement('div');
    codeInputs.className = 'code-inputs';

    function clearInputsFrom(index) {
        for (let j = index; j < step.codeLength; j++) {
            codeInputs.children[j].value = '';
        }
    }

    function fillDigitsFromIndex(digits, startIndex) {
        const sanitized = digits.replace(/\D/g, '').slice(0, step.codeLength);
        clearInputsFrom(startIndex);

        let cursor = startIndex;
        for (const char of sanitized) {
            if (cursor >= step.codeLength) {
                break;
            }
            codeInputs.children[cursor].value = char;
            cursor += 1;
        }

        const nextFocusIndex = Math.min(cursor, step.codeLength - 1);
        codeInputs.children[nextFocusIndex].focus();
        validateVerificationCode();
    }

    for (let i = 0; i < step.codeLength; i++) {
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'code-input';
        input.maxLength = 1;
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('pattern', '[0-9]*');
        input.setAttribute('autocomplete', 'one-time-code');
        input.dataset.index = i.toString();

        input.addEventListener('focus', () => {
            input.select();
        });

        input.addEventListener('input', (e) => {
            const value = e.target.value.replace(/\D/g, '');
            if (!value) {
                e.target.value = '';
                validateVerificationCode();
                return;
            }

            // Support OS auto-fill that injects multiple digits into one box
            fillDigitsFromIndex(value, i);
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            if (!pasted) {
                return;
            }
            fillDigitsFromIndex(pasted, i);
        });

        input.addEventListener('keydown', (e) => {
            const isDigit = /^[0-9]$/.test(e.key);

            if (isDigit) {
                e.preventDefault();
                fillDigitsFromIndex(e.key, i);
                return;
            }

            if (e.key === 'Backspace') {
                e.preventDefault();

                if (e.target.value !== '') {
                    e.target.value = '';
                    validateVerificationCode();
                    return;
                }

                if (i > 0) {
                    const prev = codeInputs.children[i - 1];
                    prev.value = '';
                    prev.focus();
                    validateVerificationCode();
                }
                return;
            }

            if (e.key === 'ArrowLeft' && i > 0) {
                e.preventDefault();
                codeInputs.children[i - 1].focus();
                return;
            }

            if (e.key === 'ArrowRight' && i < step.codeLength - 1) {
                e.preventDefault();
                codeInputs.children[i + 1].focus();
                return;
            }
        });

        codeInputs.appendChild(input);
    }

    codeContainer.appendChild(codeInputs);

    // Verification hint
    const hint = document.createElement('p');
    hint.className = 'code-hint';
    hint.textContent = 'For demo purposes, enter any 4-digit code';
    codeContainer.appendChild(hint);

    // Resend link
    const resendLink = document.createElement('button');
    resendLink.type = 'button';
    resendLink.className = 'resend-link';
    resendLink.textContent = 'Resend code';
    resendLink.addEventListener('click', () => {
        showToast('New verification code sent!');
    });

    codeContainer.appendChild(resendLink);
    container.appendChild(codeContainer);

    stepNameEl.textContent = 'Verify Email';

    // Focus first input
    setTimeout(() => {
        codeInputs.children[0].focus();
    }, 100);
}

// Validate verification code
function validateVerificationCode() {
    const inputs = document.querySelectorAll('.code-input');
    let code = '';
    inputs.forEach(input => {
        code += input.value;
    });

    nextBtn.disabled = code.length !== 4;
}

// Render palm photo step - UPDATED VERSION
// Render palm photo step - COMPLETELY REVISED VERSION
function renderPalmPhotoStep(container, step) {
    // Clear any existing camera
    if (window.currentCameraStream) {
        window.currentCameraStream.getTracks().forEach(track => track.stop());
        window.currentCameraStream = null;
    }

    // Clear container and rebuild
    container.innerHTML = '';

    const title = document.createElement('h1');
    title.className = 'step-title';
    title.textContent = step.title;
    container.appendChild(title);

    const subtitle = document.createElement('p');
    subtitle.className = 'step-subtitle';
    subtitle.textContent = step.subtitle;
    container.appendChild(subtitle);

    // Instructions
    const instructions = document.createElement('div');
    instructions.className = 'step-subtitle';
    instructions.style.fontSize = '1rem';
    instructions.style.color = 'var(--color-accent)';
    instructions.style.marginBottom = 'var(--spacing-lg)';
    instructions.innerHTML = `
        <p><i class="fas fa-lightbulb"></i> For best results:</p>
        <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
            <li>Frame from shoulders up</li>
            <li>Soft, even lighting</li>
            <li>Relax your posture and expression</li>
            <li>Your face can be partially visible</li>
        </ul>
    `;
    container.appendChild(instructions);

    // Create photo container
    const photoContainer = document.createElement('div');
    photoContainer.className = 'photo-container';
    photoContainer.id = 'photo-container';

    // Preview container
    const previewContainer = document.createElement('div');
    previewContainer.className = 'photo-preview-container';
    previewContainer.id = 'preview-container';

    // Placeholder (shown by default)
    const placeholder = document.createElement('div');
    placeholder.className = 'photo-placeholder';
    placeholder.id = 'photo-placeholder';
    placeholder.innerHTML = `
        <i class="fas fa-user"></i>
        <p>Take or upload a shoulders-up photo</p>
        <small style="font-size: 0.8rem; margin-top: 10px;">
            <i class="fas fa-info-circle"></i> Camera access requires HTTPS
        </small>
    `;

    // Video element (hidden by default)
    const video = document.createElement('video');
    video.id = 'video-preview';
    video.setAttribute('autoplay', '');
    video.setAttribute('playsinline', '');
    video.style.display = 'none';

    // Image preview (hidden by default)
    const imagePreview = document.createElement('img');
    imagePreview.id = 'capture-preview';
    imagePreview.style.display = 'none';
    imagePreview.alt = 'Captured aura photo';

    previewContainer.appendChild(video);
    previewContainer.appendChild(imagePreview);
    previewContainer.appendChild(placeholder);
    photoContainer.appendChild(previewContainer);

    // Camera controls container
    const controls = document.createElement('div');
    controls.className = 'photo-controls';
    controls.id = 'photo-controls';

    // Camera button
    const cameraBtn = document.createElement('button');
    cameraBtn.className = 'btn btn-primary';
    cameraBtn.id = 'camera-btn';
    cameraBtn.innerHTML = '<i class="fas fa-camera"></i> Use Camera';
    cameraBtn.onclick = () => {
        if (window.cameraActive && document.getElementById('video-preview').style.display === 'block') {
            // Camera is active, capture photo
            capturePhotoFromCamera();
        } else {
            // Start camera
            initializeCamera();
        }
    };

    // Use photo button (hidden initially)
    const usePhotoBtn = document.createElement('button');
    usePhotoBtn.className = 'btn btn-primary';
    usePhotoBtn.id = 'use-photo-btn';
    usePhotoBtn.innerHTML = '<i class="fas fa-check"></i> Use This Photo';
    usePhotoBtn.style.display = 'none';
    usePhotoBtn.onclick = () => {
        if (appState.userData.palmImage) {
            goToNextStep();
        } else {
            showToast('No photo available. Please capture or upload one first.');
        }
    };

    // Retake button (hidden initially)
    const retakeBtn = document.createElement('button');
    retakeBtn.className = 'btn btn-secondary';
    retakeBtn.id = 'retake-btn';
    retakeBtn.innerHTML = '<i class="fas fa-redo"></i> Retake';
    retakeBtn.style.display = 'none';
    retakeBtn.onclick = () => {
        resetPhotoInterface();
    };

    const uploadBtn = document.createElement('button');
    uploadBtn.className = 'btn btn-secondary';
    uploadBtn.id = 'upload-btn';
    uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Photo';
    uploadBtn.type = 'button';

    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.className = 'file-input';
    fileInput.id = 'photo-upload-input';
    fileInput.accept = 'image/*';
    fileInput.style.display = 'none';

    uploadBtn.onclick = () => {
        fileInput.click();
    };

    fileInput.onchange = (e) => {
        handlePhotoUpload(e);
    };

    controls.appendChild(cameraBtn);
    controls.appendChild(uploadBtn);
    controls.appendChild(fileInput);
    controls.appendChild(usePhotoBtn);
    controls.appendChild(retakeBtn);
    photoContainer.appendChild(controls);
    container.appendChild(photoContainer);

    stepNameEl.textContent = 'Capture Aura Photo';

    // Initialize state
    window.cameraActive = false;
    window.cameraStream = null;
    nextBtn.disabled = true;
}

// Initialize camera
async function initializeCamera() {
    const video = document.getElementById('video-preview');
    const placeholder = document.getElementById('photo-placeholder');
    const cameraBtn = document.getElementById('camera-btn');
    const useBtn = document.getElementById('use-photo-btn');
    const retakeBtn = document.getElementById('retake-btn');

    if (!video || !placeholder) {
        showToast('Camera interface not ready. Please refresh the page.');
        return;
    }

    // Check for camera support
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showToast('Camera not supported in this browser. Please upload a photo instead.');
        cameraBtn.disabled = true;
        cameraBtn.innerHTML = '<i class="fas fa-times"></i> Camera Not Supported';
        return;
    }

    try {
        // Try to get camera access - use lower resolution on mobile for better performance
        const constraints = {
            video: {
                width: { ideal: isMobile ? 640 : 1280 },
                height: { ideal: isMobile ? 480 : 720 },
                facingMode: { ideal: 'environment' },
                frameRate: { ideal: isMobile ? 15 : 30 } // Lower frame rate on mobile
            }
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);

        // Store reference globally
        window.cameraStream = stream;
        window.cameraActive = true;

        // Set video source
        video.srcObject = stream;
        video.style.display = 'block';
        placeholder.style.display = 'none';

        // Update button
        cameraBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
        cameraBtn.disabled = false;

        // Show success message
        showToast('Camera activated! Click "Capture Photo" when ready.', 2000);

    } catch (error) {
        console.error('Camera error:', error);

        let errorMessage = 'Could not access camera. ';
        if (error.name === 'NotAllowedError') {
            errorMessage += 'Permission denied. Please allow camera access or upload a photo.';
        } else if (error.name === 'NotFoundError') {
            errorMessage += 'No camera found on this device.';
        } else if (error.name === 'NotReadableError') {
            errorMessage += 'Camera is in use by another application.';
        } else {
            errorMessage += 'Please upload a photo instead.';
        }

        showToast(errorMessage);

        // Update button
        cameraBtn.innerHTML = '<i class="fas fa-times"></i> Camera Failed';
        cameraBtn.disabled = true;

        // Show placeholder with error
        placeholder.innerHTML = `
            <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
            <p>${errorMessage}</p>
        `;
    }
}

// Capture photo from camera
function capturePhotoFromCamera() {
    const video = document.getElementById('video-preview');
    const imagePreview = document.getElementById('capture-preview');
    const placeholder = document.getElementById('photo-placeholder');
    const cameraBtn = document.getElementById('camera-btn');
    const useBtn = document.getElementById('use-photo-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const uploadBtn = document.getElementById('upload-btn');

    if (!video || !imagePreview) return;

    // Create canvas
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;

    // Draw video frame
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convert to data URL - use lower quality on mobile to save memory and bandwidth
    const imageData = canvas.toDataURL('image/jpeg', isMobile ? 0.6 : 0.8);

    // Store in app state
    appState.userData.palmImage = imageData;

    // Update UI
    imagePreview.src = imageData;
    imagePreview.style.display = 'block';
    video.style.display = 'none';
    placeholder.style.display = 'none';

    // Update buttons
    cameraBtn.style.display = 'none';
    useBtn.style.display = 'flex';
    useBtn.classList.add('btn-ready');
    retakeBtn.style.display = 'flex';
    if (uploadBtn) uploadBtn.style.display = 'none';

    // Enable next button
    nextBtn.disabled = false;

    // Stop camera stream
    if (window.cameraStream) {
        window.cameraStream.getTracks().forEach(track => track.stop());
        window.cameraStream = null;
        window.cameraActive = false;
    }

    showToast('Photo captured! Ready to continue.', 2000);
}

// Handle photo upload
function handlePhotoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file
    if (!file.type.startsWith('image/')) {
        showToast('Please select an image file (JPEG, PNG, etc.)');
        return;
    }

    // Check file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
        showToast('File too large. Maximum size is 5MB.');
        return;
    }

    const reader = new FileReader();
    const imagePreview = document.getElementById('capture-preview');
    const placeholder = document.getElementById('photo-placeholder');
    const cameraBtn = document.getElementById('camera-btn');
    const useBtn = document.getElementById('use-photo-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const uploadBtn = document.getElementById('upload-btn');
    const video = document.getElementById('video-preview');

    reader.onload = function (e) {
        // Store in app state
        appState.userData.palmImage = e.target.result;

        // Update UI
        imagePreview.src = e.target.result;
        imagePreview.style.display = 'block';

        if (placeholder) placeholder.style.display = 'none';
        if (video) video.style.display = 'none';
        if (cameraBtn) cameraBtn.style.display = 'none';
        if (useBtn) {
            useBtn.style.display = 'flex';
            useBtn.disabled = false;
            useBtn.classList.add('btn-ready');
        }
        if (retakeBtn) retakeBtn.style.display = 'flex';
        if (uploadBtn) uploadBtn.style.display = 'none';

        // Enable next button
        nextBtn.disabled = false;

        // Stop camera if active
        if (window.cameraStream) {
            window.cameraStream.getTracks().forEach(track => track.stop());
            window.cameraStream = null;
            window.cameraActive = false;
        }

        showToast('Photo uploaded! Ready to continue.', 2000);
    };

    reader.onerror = function () {
        showToast('Error reading file. Please try another image.');
    };

    reader.readAsDataURL(file);
}

// Reset photo interface
function resetPhotoInterface() {
    const video = document.getElementById('video-preview');
    const imagePreview = document.getElementById('capture-preview');
    const placeholder = document.getElementById('photo-placeholder');
    const cameraBtn = document.getElementById('camera-btn');
    const useBtn = document.getElementById('use-photo-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const uploadBtn = document.getElementById('upload-btn');

    // Reset UI
    if (imagePreview) imagePreview.style.display = 'none';
    if (video) video.style.display = 'none';
    if (placeholder) {
        placeholder.style.display = 'flex';
        placeholder.innerHTML = `
            <i class="fas fa-user"></i>
            <p>Take or upload a shoulders-up photo</p>
        `;
    }

    if (cameraBtn) {
        cameraBtn.style.display = 'flex';
        cameraBtn.innerHTML = '<i class="fas fa-camera"></i> Use Camera';
        cameraBtn.disabled = false;
    }

    if (useBtn) useBtn.style.display = 'none';
    if (useBtn) useBtn.classList.remove('btn-ready');
    if (retakeBtn) retakeBtn.style.display = 'none';
    if (uploadBtn) uploadBtn.style.display = 'flex';

    // Clear stored image
    appState.userData.palmImage = null;

    // Disable next button
    nextBtn.disabled = true;

    // Stop camera if active
    if (window.cameraStream) {
        window.cameraStream.getTracks().forEach(track => track.stop());
        window.cameraStream = null;
        window.cameraActive = false;
    }
}

// Add cleanup when leaving the page
window.addEventListener('beforeunload', () => {
    if (window.cameraStream) {
        window.cameraStream.getTracks().forEach(track => track.stop());
    }
});

// Also clean up when going to next/previous step
const originalGoToNextStep = goToNextStep;
const originalGoToPreviousStep = goToPreviousStep;

goToNextStep = function () {
    // Clean up camera
    if (window.cameraStream) {
        window.cameraStream.getTracks().forEach(track => track.stop());
        window.cameraStream = null;
        window.cameraActive = false;
    }

    // Call original function
    return originalGoToNextStep.apply(this, arguments);
};

goToPreviousStep = function () {
    // Clean up camera
    if (window.cameraStream) {
        window.cameraStream.getTracks().forEach(track => track.stop());
        window.cameraStream = null;
        window.cameraActive = false;
    }

    // Call original function
    return originalGoToPreviousStep.apply(this, arguments);
};

// Start camera - IMPROVED VERSION
async function startCamera() {
    const video = document.getElementById('video-preview');
    const placeholder = document.querySelector('.photo-placeholder');
    const captureBtn = document.getElementById('open-camera-btn');

    // Check if browser supports mediaDevices
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showToast('Camera access not supported in this browser. Please upload a photo instead.');
        return;
    }

    try {
        // Request camera permissions
        const constraints = {
            video: {
                facingMode: 'environment', // Prefer rear camera
                width: { ideal: 1280 },
                height: { ideal: 720 }
            }
        };

        // Try to get camera stream
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: isMobile ? 640 : 1280 },
                height: { ideal: isMobile ? 480 : 720 },
                facingMode: 'environment',
                frameRate: { ideal: isMobile ? 15 : 30 }
            }
        });

        // Store stream reference for cleanup
        appState.cameraStream = stream;

        // Set video source
        video.srcObject = stream;
        video.style.display = 'block';
        placeholder.style.display = 'none';

        // Update button text
        captureBtn.innerHTML = '<i class="fas fa-camera"></i> Capture Photo';
        captureBtn.disabled = false;

        // Enable next button temporarily (will be disabled again until capture)
        nextBtn.disabled = false;

    } catch (error) {
        console.error('Camera access error:', error);

        // Handle specific errors
        let errorMessage = 'Could not access camera. ';

        if (error.name === 'NotAllowedError') {
            errorMessage += 'Camera permission was denied.';
        } else if (error.name === 'NotFoundError') {
            errorMessage += 'No camera found on this device.';
        } else if (error.name === 'NotReadableError') {
            errorMessage += 'Camera is already in use by another application.';
        } else if (error.name === 'OverconstrainedError') {
            errorMessage += 'Camera constraints could not be satisfied.';
        } else {
            errorMessage += 'Please upload a photo instead.';
        }

        showToast(errorMessage);

        // Fallback: show placeholder and enable file upload
        video.style.display = 'none';
        placeholder.style.display = 'flex';
        placeholder.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <p>${errorMessage}</p>
        `;

        captureBtn.disabled = true;
        nextBtn.disabled = true; // Still need a photo
    }
}

// Stop camera stream
function stopCamera() {
    if (appState.cameraStream) {
        appState.cameraStream.getTracks().forEach(track => {
            track.stop();
        });
        appState.cameraStream = null;
    }

    const video = document.getElementById('video-preview');
    if (video) {
        video.srcObject = null;
        video.style.display = 'none';
    }
}

// Capture photo from camera - IMPROVED VERSION
async function capturePhoto() {
    const video = document.getElementById('video-preview');
    const canvas = document.createElement('canvas');
    const imagePreview = document.getElementById('capture-preview');
    const placeholder = document.querySelector('.photo-placeholder');
    const captureBtn = document.getElementById('open-camera-btn');
    const useBtn = document.getElementById('use-photo-btn');
    const retakeBtn = document.getElementById('retake-btn');

    // Set canvas dimensions to match video
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    // Draw current video frame to canvas
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convert to data URL (JPEG format, mobile-friendly quality)
    const rawImageData = canvas.toDataURL('image/jpeg', isMobile ? 0.6 : 0.8);
    const imageData = await compressImageData(rawImageData);

    // Set image preview
    imagePreview.src = imageData;
    imagePreview.style.display = 'block';
    video.style.display = 'none';
    placeholder.style.display = 'none';

    // Stop camera to save battery
    stopCamera();

    // Update button visibility
    captureBtn.style.display = 'none';
    useBtn.style.display = 'flex';
    retakeBtn.style.display = 'flex';

    // Enable next button
    nextBtn.disabled = false;
    appState.userData.palmImage = imageData;

    // Show success message
    showToast('Photo captured! Click "Use This Photo" to continue.');
}

// Handle file upload - IMPROVED VERSION
async function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Check file type
    if (!file.type.match('image.*')) {
        showToast('Please select an image file (JPEG, PNG, etc.)');
        return;
    }

    // Check file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        showToast('Image file is too large. Maximum size is 5MB.');
        return;
    }

    const reader = new FileReader();
    const imagePreview = document.getElementById('capture-preview');
    const placeholder = document.querySelector('.photo-placeholder');
    const captureBtn = document.getElementById('open-camera-btn');
    const useBtn = document.getElementById('use-photo-btn');
    const retakeBtn = document.getElementById('retake-btn');
    const video = document.getElementById('video-preview');

    reader.onload = async function (e) {
        showToast('Compressing photo for upload...');
        const compressed = await compressImageData(e.target.result);
        imagePreview.src = compressed;
        imagePreview.style.display = 'block';
        placeholder.style.display = 'none';

        // Stop camera if it's running
        if (appState.cameraStream) {
            stopCamera();
        }

        // Hide video
        if (video) {
            video.style.display = 'none';
        }

        // Update button visibility
        captureBtn.style.display = 'none';
        useBtn.style.display = 'flex';
        retakeBtn.style.display = 'flex';

        // Enable next button
        nextBtn.disabled = false;
        appState.userData.palmImage = compressed;

        showToast('Photo loaded! Click "Use This Photo" to continue.');
    };

    reader.onerror = function () {
        showToast('Error reading file. Please try another image.');
    };

    reader.readAsDataURL(file);
}

// Render quiz question step - FIXED VERSION
function renderQuizQuestionStep(container, step) {
    // Clear container first
    container.innerHTML = '';

    const quizSteps = palmReadingConfig.steps.filter(s => s.type === 'quizQuestion');
    const currentQuizStep = quizSteps.findIndex(s => s.id === step.id) + 1;
    const totalQuizQuestions = appState.dynamicQuestions.length || quizSteps.length;
    const dynamicQuestion = getDynamicQuestionForStep(step);
    const uiQuestionType = mapQuestionType(dynamicQuestion ? dynamicQuestion.type : step.questionType);
    const questionKey = getQuestionKey(dynamicQuestion, step.id);
    const questionText = dynamicQuestion ? (dynamicQuestion.question || dynamicQuestion.question_text || 'Please answer the question below') : (step.question || 'Please answer the question below');
    const required = typeof (dynamicQuestion && dynamicQuestion.required) === 'boolean'
        ? dynamicQuestion.required
        : (typeof step.required === 'boolean' ? step.required : true);

    // Title
    const title = document.createElement('h1');
    title.className = 'step-title';
    title.textContent = step.title || `Question ${currentQuizStep} of ${totalQuizQuestions}`;
    container.appendChild(title);

    // Question text
    const question = document.createElement('p');
    question.className = 'step-subtitle';
    question.textContent = questionText;
    container.appendChild(question);

    // SINGLE / MULTIPLE CHOICE
    if (uiQuestionType === 'singleChoice' || uiQuestionType === 'multipleChoice') {
        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'options-container';

        const rawOptions = dynamicQuestion ? dynamicQuestion.options : step.options;

        if (!rawOptions || !Array.isArray(rawOptions)) {
            console.error('No options defined for question:', dynamicQuestion || step);
            optionsContainer.innerHTML = '<p class="error">Question configuration error</p>';
        } else {
            rawOptions.map((option, idx) => normalizeOption(option, idx, dynamicQuestion)).forEach(option => {
                const optionBtn = document.createElement('button');
                optionBtn.type = 'button';
                optionBtn.className = 'option-btn';
                optionBtn.innerHTML = `
                    ${option.icon ? `<i class="${option.icon} option-icon"></i>` : ''}
                    <span>${option.label || option.text || 'Option'}</span>
                `;

                // Existing response
                const response = appState.quizResponses[questionKey];
                let isSelected = false;

                if (uiQuestionType === 'singleChoice') {
                    isSelected = response === option.value;
                } else {
                    isSelected = Array.isArray(response) && response.includes(option.value);
                }

                if (isSelected) {
                    optionBtn.classList.add('selected');
                }

                optionBtn.addEventListener('click', () => {
                    if (uiQuestionType === 'singleChoice') {
                        // Deselect all
                        container.querySelectorAll('.option-btn').forEach(btn => {
                            btn.classList.remove('selected');
                        });

                        // Select this one
                        optionBtn.classList.add('selected');
                        appState.quizResponses[questionKey] = option.value;
                    } else {
                        // Multiple choice - toggle
                        optionBtn.classList.toggle('selected');

                        if (!Array.isArray(appState.quizResponses[questionKey])) {
                            appState.quizResponses[questionKey] = [];
                        }

                        const idx = appState.quizResponses[questionKey].indexOf(option.value);
                        if (idx > -1) {
                            appState.quizResponses[questionKey].splice(idx, 1);
                        } else {
                            appState.quizResponses[questionKey].push(option.value);
                        }
                    }

                    // Validate
                    validateQuizQuestion({ questionKey, questionType: uiQuestionType, required });
                });

                optionsContainer.appendChild(optionBtn);
            });
        }

        container.appendChild(optionsContainer);

        // TEXT QUESTION
    } else if (uiQuestionType === 'text') {
        const textContainer = document.createElement('div');
        textContainer.className = 'form-container';

        const textarea = document.createElement('textarea');
        textarea.className = 'form-textarea';
        textarea.placeholder = (dynamicQuestion && dynamicQuestion.placeholder) || step.placeholder || 'Type your answer here...';
        textarea.value = appState.quizResponses[questionKey] || '';

        textarea.addEventListener('input', () => {
            appState.quizResponses[questionKey] = textarea.value.trim();
            validateQuizQuestion({ questionKey, questionType: uiQuestionType, required });
        });

        textContainer.appendChild(textarea);
        container.appendChild(textContainer);

        // RATING
    } else if (uiQuestionType === 'rating') {
        const ratingContainer = document.createElement('div');
        ratingContainer.className = 'rating-container';

        const scaleMax = (dynamicQuestion && dynamicQuestion.scale_max) || (dynamicQuestion && Array.isArray(dynamicQuestion.options) ? dynamicQuestion.options.length : 5);
        const scaleMin = (dynamicQuestion && dynamicQuestion.scale_min) || 1;
        const currentValue = Number(appState.quizResponses[questionKey]) || 0;

        for (let value = scaleMin; value <= scaleMax; value++) {
            const ratingBtn = document.createElement('button');
            ratingBtn.type = 'button';
            ratingBtn.className = `rating-btn ${currentValue === value ? 'selected' : ''}`;
            ratingBtn.innerHTML = `<i class="fas fa-star"></i> ${value}`;

            ratingBtn.addEventListener('click', () => {
                container.querySelectorAll('.rating-btn').forEach(btn => btn.classList.remove('selected'));
                ratingBtn.classList.add('selected');
                appState.quizResponses[questionKey] = value;
                validateQuizQuestion({ questionKey, questionType: uiQuestionType, required });
            });

            ratingContainer.appendChild(ratingBtn);
        }

        container.appendChild(ratingContainer);

        // UNKNOWN TYPE
    } else {
        const errorMsg = document.createElement('p');
        errorMsg.className = 'step-subtitle';
        errorMsg.style.color = 'var(--color-error)';
        errorMsg.textContent = 'Question type not supported';
        container.appendChild(errorMsg);
        nextBtn.disabled = true;
        return;
    }

    stepNameEl.textContent = `Question ${currentQuizStep}`;

    // Initial validation
    validateQuizQuestion({ questionKey, questionType: uiQuestionType, required });
}


// Validate quiz question
function validateQuizQuestion({ questionKey, questionType, required = true }) {
    const response = appState.quizResponses[questionKey];

    if (!required) {
        nextBtn.disabled = false;
        return;
    }

    if (questionType === 'singleChoice') {
        nextBtn.disabled = !response;
    } else if (questionType === 'multipleChoice') {
        nextBtn.disabled = !response || response.length === 0;
    } else if (questionType === 'text') {
        nextBtn.disabled = !response || response.trim().length === 0;
    } else if (questionType === 'rating') {
        nextBtn.disabled = typeof response === 'undefined' || response === null || response === '';
    } else {
        // Unknown type: be safe and disable
        nextBtn.disabled = true;
    }
}


// Render result step
function renderResultStep(container, step) {
    // The reading HTML is fetched by api-integration.js and placed in this function.
    const readingHtml = palmReadingConfig.result.generateReading(appState.userData, appState.quizResponses);

    // The container is the '.app-step' div. The returned HTML is a complete,
    // self-contained structure, so we inject it directly.
    container.innerHTML = readingHtml;

    // Clear localStorage state (no longer needed, reading is complete)
    // Keep sessionStorage for page refresh support
    localStorage.removeItem('sm_app_state');
    console.log('[SM] LocalStorage state cleared (reading complete)');

    // Dispatch a custom event to signal that the teaser content has been loaded.
    // A timeout of 0 ensures this fires *after* the browser has finished updating
    // the DOM and the `container` has been appended by the parent `renderStep` function.
    setTimeout(() => {
        // Safety check: verify the reading container exists before firing event
        const resultContainer = getReadingResultContainer();
        if (!resultContainer) {
            console.log('[SM] Reading HTML injected but result container not found - clearing state and restarting');
            sessionStorage.removeItem('sm_reading_loaded');
            sessionStorage.removeItem('sm_reading_lead_id');
            sessionStorage.removeItem('sm_reading_token');
            // Remove sm_report param to prevent infinite redirect loop, but keep lead_id
            const url = new URL(window.location.href);
            url.searchParams.delete('sm_report');
            window.location.replace(url.pathname + (url.search || ''));
            return;
        }

        // ONLY set reading loaded flag AFTER confirming container exists
        // This prevents infinite loops when reading HTML is invalid
        sessionStorage.setItem('sm_reading_loaded', 'true');
        console.log('[SM] Reading loaded - sessionStorage flag set for refresh persistence');

        const readingType = resultContainer ? resultContainer.dataset.readingType : 'aura_teaser';
        sessionStorage.setItem('sm_reading_type', readingType);
        if (readingType) {
            const url = new URL(window.location.href);
            url.searchParams.set('reading_type', readingType);
            window.history.replaceState({}, document.title, url.toString());
        }
        const reportLoadedEvent = new Event('sm:report_loaded');
        document.dispatchEvent(reportLoadedEvent);
        if (readingType === 'aura_teaser') {
            console.log('[SM] Firing sm:teaser_loaded event after timeout.');
            const teaserLoadedEvent = new Event('sm:teaser_loaded');
            document.dispatchEvent(teaserLoadedEvent);
        }
    }, 0);

    // Update the name of the current step in the progress bar UI.
    stepNameEl.textContent = 'Your Reading';

    // Hide the main navigation buttons ('back' and 'next') as the result
    // page is the final step and has its own internal controls.
    backBtn.style.display = 'none';
    nextBtn.style.display = 'none';
}

// Update navigation buttons
function updateNavigationButtons() {
    const step = palmReadingConfig.steps[appState.currentStep];

    if (step.type === 'palmPhoto') {
        backBtn.style.display = 'none';
        nextBtn.style.display = 'none';
        return;
    }

    // Get navigation container
    const navigation = document.querySelector('.navigation');

    // Hide back button for OTP verification, report generation, and result steps
    if (step.type === 'emailVerification' || step.type === 'resultLoading' || step.type === 'result') {
        backBtn.style.display = 'none';
        // Center the next button when back button is hidden (if next button is visible)
        if (navigation && step.type !== 'result') {
            navigation.style.justifyContent = 'center';
        }
    } else {
        // Update back button
        backBtn.style.display = 'flex';
        backBtn.disabled = appState.currentStep === 0;
        backBtn.style.visibility = appState.currentStep === 0 ? 'hidden' : 'visible';
        // Restore space-between layout
        if (navigation) {
            navigation.style.justifyContent = 'space-between';
        }
    }

    // Update next button text and visibility
    // Hide for welcome step (has its own continue button) and result step
    if (step.type === 'result' || step.type === 'welcome') {
        nextBtn.style.display = 'none';
    } else {
        nextBtn.style.display = 'flex';

        if (step.type === 'emailVerification') {
            nextBtn.innerHTML = 'Verify & Continue <i class="fas fa-check"></i>';
        } else if (step.type === 'quizQuestion' && appState.currentStep === palmReadingConfig.steps.length - 2) {
            nextBtn.innerHTML = 'See Results <i class="fas fa-crystal-ball"></i>';
        } else {
            nextBtn.innerHTML = 'Continue <i class="fas fa-chevron-right"></i>';
        }
    }

    if (!backBtn.dataset.defaultPadding) {
        backBtn.dataset.defaultPadding = backBtn.style.padding || '';
        backBtn.dataset.defaultFontSize = backBtn.style.fontSize || '';
        nextBtn.dataset.defaultPadding = nextBtn.style.padding || '';
        nextBtn.dataset.defaultFontSize = nextBtn.style.fontSize || '';
    }

    if (step.type === 'quizQuestion') {
        backBtn.style.padding = '0.9rem 1.6rem';
        backBtn.style.fontSize = '1rem';
        nextBtn.style.padding = '0.9rem 1.6rem';
        nextBtn.style.fontSize = '1rem';
    } else {
        backBtn.style.padding = backBtn.dataset.defaultPadding;
        backBtn.style.fontSize = backBtn.dataset.defaultFontSize;
        nextBtn.style.padding = nextBtn.dataset.defaultPadding;
        nextBtn.style.fontSize = nextBtn.dataset.defaultFontSize;
    }
}

// Update progress bar
function updateProgressBar() {
    const progressPercentage = ((appState.currentStep + 1) / palmReadingConfig.steps.length) * 100;
    progressFill.style.width = `${progressPercentage}%`;
    currentStepEl.textContent = appState.currentStep + 1;
}

// Go to next step
function goToNextStep() {
    if (appState.isTransitioning) return;

    const currentStep = palmReadingConfig.steps[appState.currentStep];


    if (currentStep.type === 'palmPhoto') {
        cleanupCamera();
    }

    // OTP verification is now handled by api-integration.js
    // The fake verification logic has been removed to use real backend verification

    // Check if we're on the last step
    if (appState.currentStep === palmReadingConfig.steps.length - 1) {
        return;
    }

    appState.isTransitioning = true;

    // Add transition effect
    appContent.style.opacity = '0.5';
    appContent.style.transform = 'translateX(-20px)';

    setTimeout(() => {
        renderStep(appState.currentStep + 1);
        appContent.style.opacity = '1';
        appContent.style.transform = 'translateX(0)';
        appState.isTransitioning = false;

        // Save state for refresh persistence
        saveStateToLocalStorage();
    }, 300);
}

// Go to previous step
function goToPreviousStep() {
    if (appState.isTransitioning || appState.currentStep === 0) return;

    const currentStep = palmReadingConfig.steps[appState.currentStep];

    // Clean up camera if we're leaving the photo step
    if (currentStep.type === 'palmPhoto') {
        cleanupCamera();
    }

    // Cancel loading timer if active
    if (appState.loadingTimer) {
        clearTimeout(appState.loadingTimer);
        appState.loadingTimer = null;
    }

    appState.isTransitioning = true;

    // Add transition effect
    appContent.style.opacity = '0.5';
    appContent.style.transform = 'translateX(20px)';

    setTimeout(() => {
        renderStep(appState.currentStep - 1);
        appContent.style.opacity = '1';
        appContent.style.transform = 'translateX(0)';
        appState.isTransitioning = false;

        // If we returned to lead capture, clear stored lead/api state to allow new email
        const newStep = palmReadingConfig.steps[appState.currentStep];
        if (newStep && newStep.id === 'leadCapture') {
            if (window.smApiState) {
                window.smApiState.leadId = null;
                window.smApiState.otpSent = false;
                window.smApiState.otpVerified = false;
                window.smApiState.imageUploaded = false;
                window.smApiState.quizSaved = false;
                window.smApiState.readingGenerated = false;
            }
            appState.userData = {
                name: '',
                email: '',
                identity: '',
                age: '',
                ageRange: '',
                palmImage: null,
                gdprConsent: false,
                emailVerified: false
            };
            resetLeadCaptureValidationState();
            appState.quizResponses = {};
            sessionStorage.removeItem('sm_lead_cache');
        }

        // Save state for refresh persistence
        saveStateToLocalStorage();
    }, 300);
}

// Handle keyboard navigation
function handleKeyboardNavigation(e) {
    // Don't handle if user is typing in an input/textarea
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

    switch (e.key) {
        case 'ArrowLeft':
            if (!backBtn.disabled) goToPreviousStep();
            break;
        case 'ArrowRight':
        case 'Enter':
            if (!nextBtn.disabled) goToNextStep();
            break;
    }
}

// Show toast notification
function showToast(message, duration = 3000) {
    toast.textContent = message;
    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, duration);
}

/**
 * Check if user arrived via magic link with existing reading
 * If reading exists, show "Credits expired" message and redirect to first page
 * @returns {Promise<boolean>} True if user should be blocked (reading exists), false if should proceed
 */
async function checkForExistingReading() {
    // First, check if we have stored reading state from a previous load (handles page refresh)
    const storedLeadId = sessionStorage.getItem('sm_reading_lead_id');
    const storedToken = sessionStorage.getItem('sm_reading_token');
    const storedReadingLoaded = sessionStorage.getItem('sm_reading_loaded');
    const storedReadingType = sessionStorage.getItem('sm_reading_type');

    // Get URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const hasMagic = urlParams.get('sm_magic');
    const leadFromUrl = urlParams.get('lead_id') || urlParams.get('lead');
    const readingTypeFromUrl = urlParams.get('reading_type');
    const leadId = leadFromUrl || storedLeadId;
    const token = urlParams.get('token') || storedToken;

    // Debug: Log what we found in sessionStorage
    console.log('[SM DEBUG] Session state check:', {
        storedReadingLoaded,
        storedLeadId,
        storedToken,
        leadFromUrl,
        finalLeadId: leadId
    });

    // If no magic link parameters and no stored state, proceed with normal flow
    if (!leadId && !storedReadingLoaded) {
        console.log('[SM] No magic link parameters or stored reading state found, proceeding with normal flow');
        return false;
    }

    // If reading was already loaded in this session (page refresh on report page), allow it
    if (storedReadingLoaded === 'true') {
        console.log('[SM] Reading already loaded in this session, allowing page refresh');
        return await loadExistingReading(leadId, token);
    }

    if (!leadId) {
        console.log('[SM] Magic link present but no lead id available, continuing with normal flow');
        return false;
    }

    console.log('[SM] Magic link detected, checking for existing reading...', {
        leadId,
        hasToken: !!token,
        hasMagic: !!hasMagic
    });

    try {
        // Check if reading exists for this lead
        const query = new URLSearchParams({ lead_id: leadId });
        if (readingTypeFromUrl) {
            query.set('reading_type', readingTypeFromUrl);
        }
        if (storedReadingType) {
            query.set('reading_type', storedReadingType);
        }
        if (token) {
            query.set('token', token);
        }

        const response = await fetch(
            `${smData.apiUrl}reading/get-by-lead?${query.toString()}`,
            {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': smData.nonce
                }
            }
        );

        if (!response.ok) {
            console.error('[SM] Failed to check for existing reading:', response.status);
            return false;
        }

        const result = await response.json();

        if (!result.success) {
            console.error('[SM] Reading check failed:', result);
            return false;
        }

        // If reading exists, load it immediately
        if (result.data.exists && result.data.reading_html) {
            console.log('[SM] Reading already exists - loading it immediately with unlock state from database');

            // Inject the reading HTML (backend has already applied unlock state from database)
            appContent.innerHTML = result.data.reading_html;

            // Verify the reading container exists after injection
            // If it doesn't exist, the page state is inconsistent - clear and restart
            setTimeout(function() {
                const resultContainer = getReadingResultContainer();
                if (!resultContainer) {
                    console.log('[SM] Reading HTML injected but result container not found - clearing state and restarting');
                    sessionStorage.removeItem('sm_reading_loaded');
                    sessionStorage.removeItem('sm_reading_lead_id');
                    sessionStorage.removeItem('sm_reading_token');
                    // Remove sm_report param to prevent infinite redirect loop, but keep lead_id
                    const url = new URL(window.location.href);
                    url.searchParams.delete('sm_report');
                    window.location.href = url.pathname + (url.search || '');
                    return;
                }

                // Container exists - proceed with initialization
                // Mark session state
                sessionStorage.setItem('sm_reading_lead_id', leadId);
                sessionStorage.setItem('sm_reading_token', token);
                sessionStorage.setItem('sm_reading_loaded', 'true');

                // Hide navigation buttons
                backBtn.style.display = 'none';
                nextBtn.style.display = 'none';

                // Update progress bar to last step
                const lastStepIndex = palmReadingConfig.steps.length - 1;
                appState.currentStep = lastStepIndex;
                updateProgressBar();

                // Fire teaser loaded event so teaser-reading.js can initialize
                const readingType = resultContainer ? resultContainer.dataset.readingType : 'aura_teaser';
                sessionStorage.setItem('sm_reading_type', readingType);
                if (readingType === 'aura_teaser') {
                    const teaserEvent = new CustomEvent('sm:teaser_loaded');
                    document.dispatchEvent(teaserEvent);
                }
            }, 100);

            return true; // Block further initialization
        }

        console.log('[SM] No existing reading found, proceeding with normal flow');
        return false;

    } catch (error) {
        console.error('[SM] Error checking for existing reading:', error);
        return false;
    }
}

/**
 * Load existing reading for page refresh scenario
 * @param {string} leadId
 * @param {string} token
 * @returns {Promise<boolean>}
 */
async function loadExistingReading(leadId, token) {
    const storedReadingType = sessionStorage.getItem('sm_reading_type');
    const urlParams = new URLSearchParams(window.location.search);
    const readingTypeFromUrl = urlParams.get('reading_type');
    try {
        const query = new URLSearchParams({ lead_id: leadId });
        if (readingTypeFromUrl) {
            query.set('reading_type', readingTypeFromUrl);
        }
        if (storedReadingType) {
            query.set('reading_type', storedReadingType);
        }
        if (token) {
            query.set('token', token);
        }

        const response = await fetch(
            `${smData.apiUrl}reading/get-by-lead?${query.toString()}`,
            {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': smData.nonce
                }
            }
        );

        if (!response.ok) {
            // This happens on 403/400 errors when token is expired/invalid
            console.error('[SM] Failed to load existing reading, API check failed with status:', response.status);
            console.log('[SM] Token may be expired. Clearing session and redirecting to start.');
            sessionStorage.removeItem('sm_reading_loaded');
            sessionStorage.removeItem('sm_reading_lead_id');
            sessionStorage.removeItem('sm_reading_token');
            // Remove sm_report param to prevent infinite redirect loop
            const url = new URL(window.location.href);
            url.searchParams.delete('sm_report');
            window.location.href = url.pathname + (url.search || '');
            return true; // Return true to block further app initialization
        }

        const result = await response.json();

        if (result.success && result.data.exists && result.data.reading_html) {
            // Inject the reading HTML
            appContent.innerHTML = result.data.reading_html;

            // Verify the reading container exists after injection
            // If it doesn't exist, the page state is inconsistent - clear and restart
            setTimeout(function() {
                const resultContainer = getReadingResultContainer();
                if (!resultContainer) {
                    console.log('[SM] Reading HTML injected but result container not found - clearing state and restarting');
                    sessionStorage.removeItem('sm_reading_loaded');
                    sessionStorage.removeItem('sm_reading_lead_id');
                    sessionStorage.removeItem('sm_reading_token');
                    // Remove sm_report param to prevent infinite redirect loop, but keep lead_id
                    const url = new URL(window.location.href);
                    url.searchParams.delete('sm_report');
                    window.location.href = url.pathname + (url.search || '');
                    return;
                }

                // Container exists - proceed with initialization
                // Mark session state
                sessionStorage.setItem('sm_reading_lead_id', leadId);
                sessionStorage.setItem('sm_reading_token', token);
                sessionStorage.setItem('sm_reading_loaded', 'true');

                // Hide navigation buttons
                backBtn.style.display = 'none';
                nextBtn.style.display = 'none';

                // Update progress bar
                const lastStepIndex = palmReadingConfig.steps.length - 1;
                appState.currentStep = lastStepIndex;
                updateProgressBar();

                // Fire teaser loaded event
                const readingType = resultContainer ? resultContainer.dataset.readingType : 'aura_teaser';
                sessionStorage.setItem('sm_reading_type', readingType);
                if (readingType === 'aura_teaser') {
                    const teaserEvent = new CustomEvent('sm:teaser_loaded');
                    document.dispatchEvent(teaserEvent);
                }

                console.log('[SM] Existing reading loaded (page refresh)');
            }, 100);

            return true;
        }
        
        // This case means the API call succeeded but the backend says no reading exists.
        // This can happen if the reading was deleted. Clear state and redirect.
        console.log('[SM] Backend reports no existing reading. Clearing session and redirecting.');
        sessionStorage.removeItem('sm_reading_loaded');
        sessionStorage.removeItem('sm_reading_lead_id');
        sessionStorage.removeItem('sm_reading_token');
        // Remove sm_report param to prevent infinite redirect loop
        const url = new URL(window.location.href);
        url.searchParams.delete('sm_report');
        window.location.href = url.pathname + (url.search || '');
        return true; // Block further app initialization

    } catch (error) {
        console.error('[SM] Error loading existing reading:', error);
        console.log('[SM] Clearing session and redirecting to start due to error.');
        sessionStorage.removeItem('sm_reading_loaded');
        sessionStorage.removeItem('sm_reading_lead_id');
        sessionStorage.removeItem('sm_reading_token');
        // Remove sm_report param to prevent infinite redirect loop
        const url = new URL(window.location.href);
        url.searchParams.delete('sm_report');
        window.location.href = url.pathname + (url.search || '');
        return true; // Return true to block further app initialization
    }
}


// Initialize the app when DOM is loaded
// Initialize the app - WordPress compatible
async function mprInitialize() {
    const urlParams = new URLSearchParams(window.location.search);
    const hasReportFlag = urlParams.has('sm_report');

    if (hasReportFlag) {
        const reportReady = await waitForReportRender(2500);
        if (reportReady) {
            console.log('[SM] Report detected on load - skipping normal init');
            return;
        }
        console.log('[SM] Report not ready after wait - falling back to normal init');
    }

    // First, check if user has existing reading via magic link
    const hasExistingReading = await checkForExistingReading();

    // If existing reading was loaded, don't initialize the normal flow
    if (hasExistingReading) {
        console.log('[SM] Skipped normal app initialization (existing reading loaded)');
        return;
    }
    
    const appContent = document.getElementById('app-content');
    if (appContent) {
        // Wait a bit to ensure WordPress admin bar is loaded, then init normal flow
        setTimeout(initApp, 100);
    } else {
        console.log('[SM] Skipped normal app initialization (no #app-content element found)');
    }
}

// Immediate state validation on page load
// If reading is marked as loaded but container doesn't exist, clear and restart
(function validateReadingState() {
    const storedReadingLoaded = sessionStorage.getItem('sm_reading_loaded');
    const urlParams = new URLSearchParams(window.location.search);
    const hasReportFlag = urlParams.has('sm_report');

    if (storedReadingLoaded === 'true') {
        const storedStepId = sessionStorage.getItem('sm_flow_step_id');
        if (storedStepId && storedStepId !== 'result' && storedStepId !== 'resultLoading') {
            console.log('[SM] Reading state present during in-progress flow - clearing stale report flag');
            sessionStorage.removeItem('sm_reading_loaded');
            sessionStorage.removeItem('sm_reading_token');
            sessionStorage.removeItem('sm_existing_reading_id');
            return;
        }

        // Check if the reading container exists
        const resultContainer = getReadingResultContainer();

        if (!resultContainer) {
            if (hasReportFlag) {
                console.log('[SM] Report refresh detected - deferring reading state cleanup');
                return;
            }
            console.log('[SM] Reading marked as loaded but container missing - clearing state and restarting immediately');
            sessionStorage.removeItem('sm_reading_loaded');
            sessionStorage.removeItem('sm_reading_lead_id');
            sessionStorage.removeItem('sm_reading_token');
            sessionStorage.removeItem('sm_email');

            if (!hasReportFlag) {
                // Immediate redirect to prevent flickering
                window.location.replace(window.location.pathname);
            }
        }
    }
})();

async function waitForReportRender(timeoutMs = 2000) {
    const start = Date.now();
    while (Date.now() - start < timeoutMs) {
        if (getReadingResultContainer()) {
            return true;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    return false;
}

// Handle browser back button - prevent navigation on paid reports, clear state on teasers
window.addEventListener('popstate', function(event) {
    const storedReadingLoaded = sessionStorage.getItem('sm_reading_loaded');
    const storedReadingType = sessionStorage.getItem('sm_reading_type');

    // If a paid/full report is loaded, prevent back navigation
    if (storedReadingLoaded === 'true' && storedReadingType === 'aura_full') {
        console.log('[SM] Back button blocked - paid report active');
        // Push state again to prevent navigation away from report
        window.history.pushState(null, '', window.location.href);
        return;
    }

    // For teaser reports: clear session and start fresh
    if (storedReadingLoaded === 'true') {
        console.log('[SM] Back button detected with reading loaded - clearing state and restarting');
        sessionStorage.removeItem('sm_reading_loaded');
        sessionStorage.removeItem('sm_reading_lead_id');
        sessionStorage.removeItem('sm_reading_token');
        sessionStorage.removeItem('sm_email');

        // Reload the page to start fresh from step 1
        window.location.replace(window.location.pathname);
    }
});

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mprInitialize);
} else {
    mprInitialize();
}

// Render result loading step (after last question)
function renderResultLoadingStep(container, step) {
    const title = document.createElement('h1');
    title.className = 'step-title';
    title.textContent = step.title || 'Preparing Your Aura Reading';
    container.appendChild(title);

    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';
    container.appendChild(spinner);

    const loadingText = document.createElement('p');
    loadingText.className = 'loading-text';
    loadingText.style.textAlign = 'center';

    const messages = Array.isArray(step.messages) && step.messages.length
        ? step.messages
        : [
            "Tuning into your energy...",
            "Reading the colors around your presence...",
            "Mapping your aura layers...",
            "Sensing emotional currents...",
            "Noticing the flow of your vitality...",
            "Aligning with your inner climate...",
            "Listening to subtle energetic patterns...",
            "Tracing where your energy feels open...",
            "Highlighting areas of calm and intensity...",
            "Observing your spiritual alignment...",
            "Translating aura signals into insight...",
            "Weaving your responses into a clear picture...",
            "Interpreting your energetic signature...",
            "Connecting with your intuitive blueprint...",
            "Attuning to your heart-centered signals...",
            "Balancing light and shadow in your aura...",
            "Clarifying your direction and momentum...",
            "Gathering symbols of growth and healing...",
            "Revealing your core aura tone...",
            "Preparing your personalized reading..."
        ];

    loadingText.textContent = messages[0];
    container.appendChild(loadingText);

    // Optional subtext (you can remove this entirely if you don't want it)
    const subtext = document.createElement('p');
    subtext.className = 'loading-subtext';
    subtext.style.textAlign = 'center';
    subtext.textContent = 'Attuning to the energy around you...';
    container.appendChild(subtext);

    const errorActions = document.createElement('div');
    errorActions.className = 'loading-error-actions';

    const retryBtn = document.createElement('button');
    retryBtn.className = 'btn btn-primary';
    retryBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Try Again';
    retryBtn.addEventListener('click', () => {
        backBtn.disabled = false;
        nextBtn.disabled = false;
        spinner.style.display = 'block';
        loadingText.textContent = messages[0];
        loadingText.classList.remove('loading-error');
        subtext.textContent = 'Attuning to the energy around you...';
        errorActions.style.display = 'none';
        goToNextStep();
    });

    const supportBtn = document.createElement('button');
    supportBtn.className = 'btn btn-secondary';
    supportBtn.innerHTML = '<i class="fas fa-life-ring"></i> Contact Support';
    supportBtn.addEventListener('click', () => {
        showToast('Please try again in a few moments or contact support if the issue persists.');
    });

    errorActions.appendChild(retryBtn);
    errorActions.appendChild(supportBtn);
    errorActions.style.display = 'none';
    container.appendChild(errorActions);

    stepNameEl.textContent = 'Reading';

    // Disable navigation during loading
    backBtn.disabled = true;
    nextBtn.disabled = true;

    // Rotate messages while loading
    let messageIndex = 0;
    if (appState.loadingMessageInterval) {
        clearInterval(appState.loadingMessageInterval);
    }
    let messageInterval = null;

    if (messages.length > 1) {
        messageInterval = setInterval(() => {
            messageIndex = (messageIndex + 1) % messages.length;
            loadingText.textContent = messages[messageIndex];
        }, 3000);
        appState.loadingMessageInterval = messageInterval;
    }

    // Simulate API call with timeout
    appState.loadingTimer = setTimeout(() => {
        if (messageInterval) {
            clearInterval(messageInterval);
            appState.loadingMessageInterval = null;
        }
        // Go straight to final result step
        goToNextStep();
    }, step.duration || 4000);
}

// Helpers to manage loading timers and show errors during result generation
function clearLoadingTimers() {
    if (appState.loadingTimer) {
        clearTimeout(appState.loadingTimer);
        appState.loadingTimer = null;
    }
    if (appState.loadingMessageInterval) {
        clearInterval(appState.loadingMessageInterval);
        appState.loadingMessageInterval = null;
    }
}

function showReadingErrorState(message) {
    clearLoadingTimers();

    const spinner = document.querySelector('.loading-spinner');
    if (spinner) {
        spinner.style.display = 'none';
    }

    const loadingText = document.querySelector('.loading-text');
    if (loadingText) {
        loadingText.textContent = message || 'We could not generate your reading right now.';
        loadingText.classList.add('loading-error');
    }

    const subtext = document.querySelector('.loading-subtext');
    if (subtext) {
        subtext.textContent = 'Please try again in a moment or contact support.';
    }

    const actions = document.querySelector('.loading-error-actions');
    if (actions) {
        actions.style.display = 'flex';
    }

    backBtn.disabled = false;
    nextBtn.disabled = false;
}

function stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
}

// Expose helpers for API integration to use
window.clearLoadingTimers = clearLoadingTimers;
window.showReadingErrorState = showReadingErrorState;

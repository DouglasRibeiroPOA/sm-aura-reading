/**
 * Swipeable Card Template JavaScript
 *
 * SCOPED to .sm-swipe-template to prevent interference with existing templates.
 * Wrapped in IIFE with template detection check.
 *
 * @package MysticPalmReading
 * @since 4.0.0
 */

(function() {
    'use strict';

    // Only run if swipeable template is present
    const swipeContainer = document.querySelector('.sm-swipe-template');
    if (!swipeContainer) {
        return; // Exit if not using swipeable template
    }

    // DOM Elements (scoped to swipe template)
    const appContainer = swipeContainer.querySelector('.app-container');
    const cardsContainer = swipeContainer.querySelector('.cards-container');
    const cards = swipeContainer.querySelectorAll('.reading-card');
    const progressFill = swipeContainer.querySelector('#progressFill');
    const restartBtn = swipeContainer.querySelector('#restartBtn');
    const shareBtn = swipeContainer.querySelector('#shareBtn');
    const backToDashboardBtn = swipeContainer.querySelector('#backToDashboardBtn');
    const swipeInstruction = swipeContainer.querySelector('#swipeInstruction');
    const startBtn = swipeContainer.querySelector('#startBtn');
    const modal = document.getElementById('sm-modal') || swipeContainer.querySelector('.sm-modal');
    const modalTitle = modal ? modal.querySelector('.sm-modal__title') : null;
    const modalBody = modal ? modal.querySelector('.sm-modal__body') : null;

    // State
    let currentCardIndex = 0;
    let totalCards = cards.length;
    let isDragging = false;
    let dragStartX = 0;
    let dragStartTime = 0;
    let dragDistance = 0;
    let canVibrate = 'vibrate' in navigator;
    let suppressNavigation = false;

    /**
     * Initialize the app
     */
    function initApp() {
        buildDesktopNavButtons();
        ensureSwipeHints();
        updateNavOffsets();
        setupEventListeners();

        if (swipeInstruction) {
            swipeInstruction.classList.remove('hidden');
        }

        // Update UI - with animation fix
        setTimeout(() => {
            currentCardIndex = 0;
            updateUI();
            // Force the animation on first card
            const firstCard = swipeContainer.querySelector('#card1');
            if (firstCard) {
                firstCard.classList.add('animate-in');
            }
        }, 100);

        // Setup navigation buttons
        setupNavigationButtons();
    }

    /**
     * Build navigation buttons for desktop only.
     */
    function buildDesktopNavButtons() {
        if (!window.matchMedia || !window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }

        cards.forEach((card, index) => {
            const cardIndex = index + 1;
            const existing = card.querySelector('.nav-buttons');
            if (existing) return;

            const cardContent = card.querySelector('.card-content');
            if (!cardContent) return;

            const nav = document.createElement('div');
            nav.className = 'nav-buttons';

            if (cardIndex > 1) {
                const prev = document.createElement('button');
                prev.className = 'nav-btn prev';
                prev.id = `prevBtn${cardIndex}`;
                prev.innerHTML = '<i class="fas fa-arrow-left"></i> Back';
                nav.appendChild(prev);
            }

            if (cardIndex < totalCards) {
                const next = document.createElement('button');
                next.className = 'nav-btn next';
                next.id = `nextBtn${cardIndex}`;
                next.innerHTML = cardIndex === 1
                    ? '<i class="fas fa-arrow-right"></i> Begin Reading'
                    : 'Next <i class="fas fa-arrow-right"></i>';
                nav.appendChild(next);
            }

            cardContent.appendChild(nav);
            cardContent.classList.add('has-nav');

            requestAnimationFrame(() => {
                const navOffset = cardContent.clientHeight - nav.offsetTop;
                if (navOffset > 0) {
                    cardContent.style.setProperty('--nav-buttons-offset', `${navOffset}px`);
                }
            });
        });
    }

    function updateNavOffsets() {
        cards.forEach((card) => {
            const cardContent = card.querySelector('.card-content');
            const nav = cardContent ? cardContent.querySelector('.nav-buttons') : null;
            if (!cardContent || !nav) return;
            const navOffset = cardContent.clientHeight - nav.offsetTop;
            if (navOffset > 0) {
                cardContent.style.setProperty('--nav-buttons-offset', `${navOffset}px`);
            }
        });
    }

    /**
     * Ensure each card has a swipe hint footer.
     */
    function ensureSwipeHints() {
        const isDesktop = window.matchMedia && window.matchMedia('(min-width: 1024px)').matches;

        cards.forEach((card, index) => {
            const existing = card.querySelector('.nav-hint');
            if (isDesktop && index > 0 && existing) {
                existing.remove();
                return;
            }

            if (existing) {
                return;
            }

            if (isDesktop && index > 0) {
                return;
            }

            const cardContent = card.querySelector('.card-content');
            if (!cardContent) return;

            const hint = document.createElement('div');
            hint.className = 'nav-hint';
            hint.setAttribute('aria-hidden', 'true');
            hint.innerHTML = '<div class="nav-icons"><i class="fas fa-arrow-left"></i><i class="fas fa-arrow-right"></i></div><span>Swipe to navigate</span>';
            cardContent.appendChild(hint);
        });
    }

    /**
     * Setup navigation buttons for each card
     */
    function setupNavigationButtons() {
        // Setup buttons for all cards
        for (let i = 1; i <= totalCards; i++) {
            const prevBtn = swipeContainer.querySelector(`#prevBtn${i}`);
            const nextBtn = swipeContainer.querySelector(`#nextBtn${i}`);

            if (prevBtn) {
                prevBtn.addEventListener('click', () => prevCard());
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', () => nextCard());
            }
        }
    }

    /**
     * Update UI based on current card
     */
    function updateUI() {
        // Update progress bar
        const progressPercentage = ((currentCardIndex + 1) / totalCards) * 100;
        if (progressFill) {
            progressFill.style.width = `${progressPercentage}%`;
        }

        // Update cards visibility with proper transitions
        cards.forEach((card, index) => {
            card.classList.remove('active', 'previous', 'next', 'enter-from-right', 'enter-from-left', 'animate-in');

            if (index === currentCardIndex) {
                card.classList.add('active');
                // Add animation with a slight delay to ensure it triggers
                setTimeout(() => {
                    card.classList.add('animate-in');
                }, 10);
            } else if (index < currentCardIndex) {
                card.classList.add('previous');
            } else {
                card.classList.add('next');
            }
        });

        // Animate trait bars when the active card contains traits.
        if (activeCard && activeCard.querySelector('.trait-visual')) {
            setTimeout(animateTraitBars, 400);
        }

        const activeCard = cards[currentCardIndex];
        if (activeCard) {
            const wrapper = activeCard.querySelector('.content-wrapper');
            updateFadeForWrapper(wrapper);
        }

        // Enable/disable navigation buttons
        updateNavigationButtons();
    }

    /**
     * Update navigation button states
     */
    function updateNavigationButtons() {
        // Enable all buttons by default
        for (let i = 1; i <= totalCards; i++) {
            const prevBtn = swipeContainer.querySelector(`#prevBtn${i}`);
            const nextBtn = swipeContainer.querySelector(`#nextBtn${i}`);

            if (prevBtn) prevBtn.disabled = false;
            if (nextBtn) nextBtn.disabled = false;
        }
    }

    /**
     * Animate the trait bars
     */
    function animateTraitBars() {
        const traitFills = swipeContainer.querySelectorAll('.trait-fill');
        traitFills.forEach(fill => {
            const width = fill.dataset.width || fill.getAttribute('data-width');
            if (width) {
                fill.style.width = `${width}%`;
            }
        });
    }

    /**
     * Update fade effect for scrollable content
     */
    function updateFadeForWrapper(wrapper) {
        if (!wrapper) return;

        const hasOverflow = wrapper.scrollHeight > wrapper.clientHeight + 1;
        const atBottom = wrapper.scrollTop + wrapper.clientHeight >= wrapper.scrollHeight - 1;

        if (hasOverflow && !atBottom) {
            wrapper.classList.add('fade-bottom');
        } else {
            wrapper.classList.remove('fade-bottom');
        }
    }

    /**
     * Dismiss swipe instruction overlay
     */
    function dismissSwipeInstruction() {
        if (!swipeInstruction || swipeInstruction.classList.contains('hidden')) return;

        swipeInstruction.classList.add('hidden');
        suppressNavigation = true;

        const firstCard = swipeContainer.querySelector('#card1');
        if (firstCard) {
            firstCard.style.zIndex = '2';
        }

        currentCardIndex = 0;
        updateUI();

        setTimeout(() => {
            suppressNavigation = false;
        }, 350);
    }

    /**
     * Go to specific card
     */
    function goToCard(index, direction = 'auto') {
        if (index < 0 || index >= totalCards) return;

        const oldIndex = currentCardIndex;
        currentCardIndex = index;

        // Add exit animation to old card
        if (oldIndex !== currentCardIndex) {
            const oldCard = cards[oldIndex];
            if (index > oldIndex) {
                oldCard.classList.add('swipe-left');
            } else {
                oldCard.classList.add('swipe-right');
            }

            // Add entrance animation to new card
            const newCard = cards[currentCardIndex];
            if (index > oldIndex) {
                newCard.classList.add('enter-from-right');
            } else {
                newCard.classList.add('enter-from-left');
            }

            // Vibrate if available
            if (canVibrate) {
                navigator.vibrate(20);
            }

            // Remove animations after they complete
            setTimeout(() => {
                oldCard.classList.remove('swipe-left', 'swipe-right');
                newCard.classList.remove('enter-from-right', 'enter-from-left');
            }, 500);
        }

        updateUI();
    }

    /**
     * Next card
     */
    function nextCard() {
        if (currentCardIndex < totalCards - 1) {
            goToCard(currentCardIndex + 1, 'right');
        } else {
            // Vibrate to indicate end
            if (canVibrate) {
                navigator.vibrate([30, 80, 30]);
            }
        }
    }

    /**
     * Previous card
     */
    function prevCard() {
        if (currentCardIndex > 0) {
            goToCard(currentCardIndex - 1, 'left');
        } else {
            // Vibrate to indicate beginning
            if (canVibrate) {
                navigator.vibrate([30, 80, 30]);
            }
        }
    }

    /**
     * Handle drag/swipe start
     */
    function handleDragStart(e) {
        isDragging = true;
        dragStartTime = Date.now();

        // Get starting position
        if (e.type.includes('touch')) {
            dragStartX = e.touches[0].clientX;
        } else {
            dragStartX = e.clientX;
        }

        dragDistance = 0;
    }

    /**
     * Handle drag/swipe move
     */
    function handleDragMove(e) {
        if (!isDragging) return;

        let clientX;

        if (e.type.includes('touch')) {
            clientX = e.touches[0].clientX;
            e.preventDefault(); // Prevent scrolling
        } else {
            clientX = e.clientX;
        }

        // Calculate drag distance
        dragDistance = clientX - dragStartX;
    }

    /**
     * Handle drag/swipe end
     */
    function handleDragEnd(e) {
        if (!isDragging) return;

        isDragging = false;

        let clientX;

        if (e.type.includes('touch')) {
            clientX = e.changedTouches[0].clientX;
        } else {
            clientX = e.clientX;
        }

        // Calculate final drag distance
        const finalDragDistance = clientX - dragStartX;
        const dragDuration = Date.now() - dragStartTime;

        // Determine if it's a valid swipe
        const isFastSwipe = dragDuration < 300 && Math.abs(finalDragDistance) > 25;
        const isLongSwipe = Math.abs(finalDragDistance) > 80;

        if (isFastSwipe || isLongSwipe) {
            if (finalDragDistance > 0 && currentCardIndex > 0) {
                // Swipe right - go to previous card
                prevCard();
            } else if (finalDragDistance < 0 && currentCardIndex < totalCards - 1) {
                // Swipe left - go to next card
                nextCard();
            }
        }
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Swipe instruction dismissal
        if (startBtn) {
            startBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dismissSwipeInstruction();
            });
        }

        if (swipeInstruction) {
            swipeInstruction.addEventListener('click', (e) => {
                e.preventDefault();
                if (e.target === swipeInstruction) {
                    dismissSwipeInstruction();
                }
            });
        }

        // Touch events for mobile
        if (cardsContainer) {
            cardsContainer.addEventListener('touchstart', handleDragStart);
            cardsContainer.addEventListener('touchmove', handleDragMove, { passive: false });
            cardsContainer.addEventListener('touchend', handleDragEnd);

            // Mouse events for desktop
            cardsContainer.addEventListener('mousedown', handleDragStart);
            cardsContainer.addEventListener('mousemove', handleDragMove);
            cardsContainer.addEventListener('mouseup', handleDragEnd);
            cardsContainer.addEventListener('mouseleave', handleDragEnd);

            cardsContainer.addEventListener('touchstart', dismissSwipeInstruction);
            cardsContainer.addEventListener('mousedown', dismissSwipeInstruction);
        }

        // Scroll fade effect
        swipeContainer.querySelectorAll('.content-wrapper').forEach((wrapper) => {
            wrapper.addEventListener('scroll', () => updateFadeForWrapper(wrapper));
            updateFadeForWrapper(wrapper);
        });

        // Resize handler
        window.addEventListener('resize', () => {
            const activeCard = cards[currentCardIndex];
            if (activeCard) {
                updateFadeForWrapper(activeCard.querySelector('.content-wrapper'));
            }
            updateNavOffsets();
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            // Only handle if swipe template is visible
            if (!swipeContainer.offsetParent) return;

            if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') {
                nextCard();
                e.preventDefault();
            } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                prevCard();
                e.preventDefault();
            }
        });

        // Restart button
        if (restartBtn) {
            restartBtn.addEventListener('click', () => {
                goToCard(0);
            });
        }

        // Share button
        if (shareBtn) {
            shareBtn.addEventListener('click', () => {
                // In a real app, this would share the reading
                alert('Thank you for exploring your SoulMirror reading! Share your insights with friends who might appreciate this reflective experience.');

                // Vibrate for feedback
                if (canVibrate) {
                    navigator.vibrate(80);
                }
            });
        }

        // Back to Dashboard button (paid template)
        if (backToDashboardBtn) {
            backToDashboardBtn.addEventListener('click', () => {
                const dashboardUrl = backToDashboardBtn.dataset.dashboardUrl || '/';
                window.location.href = dashboardUrl;
            });
        }

        // Also allow clicking on card edges to navigate
        if (cardsContainer) {
            cardsContainer.addEventListener('click', (e) => {
                if (swipeInstruction && !swipeInstruction.classList.contains('hidden')) {
                    return;
                }

                if (e.target.closest('button')) {
                    return;
                }

                if (isDragging) return;
                if (suppressNavigation) return;

                const clickX = e.clientX;
                const screenWidth = window.innerWidth;

                // Click on right third of screen
                if (clickX > screenWidth * 0.7) {
                    nextCard();
                }
                // Click on left third of screen
                else if (clickX < screenWidth * 0.3) {
                    prevCard();
                }
            });
        }

        // Quick insight modal triggers
        swipeContainer.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal]');
            if (!trigger) return;
            e.preventDefault();

            if (!modal || !modalTitle || !modalBody) return;
            const key = trigger.getAttribute('data-modal');
            const content = getModalContent(key);
            if (!content) return;

            modalTitle.textContent = content.title;
            modalBody.textContent = content.body;
            modal.classList.add('is-open');
        });

        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target.matches('[data-modal-close]') || e.target.closest('[data-modal-close]')) {
                    modal.classList.remove('is-open');
                }
            });
        }
    }

    function getModalContent(key) {
        if (!swipeContainer) return null;
        const fallback = {
            lovePatterns: {
                title: 'Love Patterns (Quick Reflection)',
                body: 'This quick insight offers a glimpse into the emotional patterns shaped by your heart line.'
            },
            careerDirection: {
                title: 'Career Direction (Quick Reflection)',
                body: 'This reflection highlights where your palm suggests you thrive most in work and purpose.'
            },
            lifeAlignment: {
                title: 'Life Alignment (Quick Reflection)',
                body: 'A short reflection on how your daily path aligns with your deeper values.'
            }
        };

        const modalContent = {
            lovePatterns: {
                title: 'Love Patterns (Quick Reflection)',
                body: swipeContainer.dataset.modalLove || fallback.lovePatterns.body
            },
            careerDirection: {
                title: 'Career Direction (Quick Reflection)',
                body: swipeContainer.dataset.modalCareer || fallback.careerDirection.body
            },
            lifeAlignment: {
                title: 'Life Alignment (Quick Reflection)',
                body: swipeContainer.dataset.modalAlignment || fallback.lifeAlignment.body
            }
        };

        return modalContent[key] || null;
    }

    // Initialize the app when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }

    // Prevent context menu on long press (within swipe template only)
    swipeContainer.addEventListener('contextmenu', (e) => {
        e.preventDefault();
    });

})();

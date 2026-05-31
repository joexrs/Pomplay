
document.addEventListener('DOMContentLoaded', function() {
  initPageLoader();
  initScrollEffects();
  initVideoCards();
});

/**
 * Initialize Page Loader
 */
function initPageLoader() {
  const loader = document.querySelector('#loader-wrapper');

  if (!loader) return;

  // Hide loader immediately when DOM is ready (already loaded at this point)
  setTimeout(() => {
    loader.classList.add('loaded');
  }, 100);
}

/**
 * Scroll Effects
 */
function initScrollEffects() {
  const videoBg = document.querySelector('.hero-bg');

  if (!videoBg) return;

  window.addEventListener('scroll', function() {
    const scrollPos = window.pageYOffset;
    videoBg.style.transform = `scale(${1 + scrollPos * 0.0002})`;
  });
}

/**
 * Video Card Interactions
 */
function initVideoCards() {
  document.querySelectorAll('.video-card').forEach(card => {
    const playBtn = card.querySelector('.play-btn');

    if (playBtn) {
      playBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const href = card.querySelector('a')?.getAttribute('href');
        if (href) {
          window.location.href = href;
        }
      });
    }
  });
}

// Export for external use
window.PomplayApp = {
  initPageLoader,
  initScrollEffects,
  initVideoCards
};

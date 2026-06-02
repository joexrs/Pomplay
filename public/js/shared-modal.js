(function(){
  'use strict';

  const shareDownBtn = document.getElementById('shareDownloadBtn');
  if (shareDownBtn) {
    shareDownBtn.addEventListener('click', function(e){
      e.preventDefault();
      const modal = document.getElementById('mobileShareModal');
      if (modal) {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        setTimeout(() => { modal.style.display = ''; }, 50);
      }
      document.getElementById('downloadFullBtn')?.click();
    });
  }

  document.querySelectorAll('.mobile-share-grid .share-icon[href*="whatsapp"], .mobile-share-grid .share-icon[href*="facebook"], .mobile-share-grid .share-icon[href*="twitter"]')
    .forEach(link => {
      link.addEventListener('click', function() {
        const dlBtn = document.getElementById('downloadFullBtn');
        if (dlBtn) {
          setTimeout(() => dlBtn.click(), 300);
        }
      });
    });
})();
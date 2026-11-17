(function () {
  'use strict';

  const overlay = document.getElementById('pinOverlay');
  const form = document.getElementById('videoPasswordForm');
  const input = document.getElementById('videoPasswordInput');
  const button = document.getElementById('pinSubmitBtn');
  const message = document.getElementById('pinMessage');
  const visibilityToggle = document.getElementById('videoPasswordToggle');
  if (!overlay || !form || !input || !button || !message || !visibilityToggle) return;

  const base = window.POMPLAY_BASE || '';
  const videoCode = overlay.dataset.videoCode || '';
  const buttonText = button.querySelector('.btn-text');
  const defaultButtonText = buttonText.innerHTML;

  visibilityToggle.addEventListener('click', function () {
    const showPassword = input.type === 'password';
    input.type = showPassword ? 'text' : 'password';
    visibilityToggle.setAttribute('aria-pressed', String(showPassword));
    visibilityToggle.setAttribute('aria-label', showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
    visibilityToggle.innerHTML = `<i class="fas fa-eye${showPassword ? '-slash' : ''}" aria-hidden="true"></i>`;
    input.focus();
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!input.value || button.disabled) return;
    button.disabled = true;
    button.disabled = true;
    if (buttonText) buttonText.innerHTML = '<i class="fas fa-shield-alt" aria-hidden="true"></i> Verificando tu clave...';
    message.className = 'pin-message';
    message.textContent = '';

    try {
      const response = await fetch(
        `${base}/api/videos/${encodeURIComponent(videoCode)}/verificar-contrasena`,
        {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password: input.value }),
        }
      );
      const result = await response.json();

      if (!response.ok || result.valid !== true) {
        message.className = 'pin-message visible error';
        message.textContent = result.message || 'No se pudo verificar la contraseña.';
        input.value = '';
        input.focus();
        return;
      }

      const player = document.getElementById('videoPlayer');
      const source = player?.querySelector('source[data-src]');
      if (!player || !source) {
        throw new Error('No se pudo preparar la reproducción del video.');
      }

      const videoUrl = source.dataset.src?.trim();
      if (!videoUrl) {
        throw new Error('No se encontró la dirección del video. Inténtalo nuevamente.');
      }

      await new Promise(function (resolve, reject) {
        let timeout;
        const cleanup = function () {
          clearTimeout(timeout);
          player.removeEventListener('loadedmetadata', onLoaded);
          player.removeEventListener('error', onError);
        };
        const onLoaded = function () {
          cleanup();
          resolve();
        };
        const onError = function () {
          cleanup();
          reject(new Error('La contraseña es correcta, pero no se pudo cargar el video.'));
        };

        player.addEventListener('loadedmetadata', onLoaded, { once: true });
        player.addEventListener('error', onError, { once: true });
        timeout = window.setTimeout(function () {
          cleanup();
          reject(new Error('El video está tardando demasiado en cargar. Inténtalo nuevamente.'));
        }, 30000);

        source.src = videoUrl;
        player.load();
        if (player.readyState >= 1) onLoaded();
      });

      source.removeAttribute('data-src');
      overlay.classList.add('removing');
      window.setTimeout(function () {
        overlay.remove();
        document.dispatchEvent(new Event('pomplay:video-authorized'));
      }, 400);
    } catch (error) {
      const player = document.getElementById('videoPlayer');
      const source = player?.querySelector('source[data-src]');
      if (player && source && source.hasAttribute('src')) {
        source.removeAttribute('src');
        player.load();
      }
      message.className = 'pin-message visible error';
      message.textContent = error.message || 'Error de conexión. Inténtalo nuevamente.';
    } finally {
      button.disabled = false;
      if (buttonText) buttonText.innerHTML = defaultButtonText;
    }
  });
}());

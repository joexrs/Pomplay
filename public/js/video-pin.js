/**
 * Video PIN Verification — Frontend público
 * Maneja el flujo de ingreso de PIN para videos privados.
 */
(function () {
  'use strict';

  const overlay = document.getElementById('pinOverlay');
  if (!overlay) return;

  const BASE = window.POMPLAY_BASE || '';
  const VIDEO_CODE = overlay.dataset.videoCode || '';
  const PIN_LENGTH = parseInt(overlay.dataset.pinLength || '6', 10);

  const digits = overlay.querySelectorAll('.pin-digit');
  const submitBtn = overlay.querySelector('#pinSubmitBtn');
  const messageEl = overlay.querySelector('#pinMessage');
  const attemptsEl = overlay.querySelector('#pinAttempts');
  const btnText = submitBtn?.querySelector('.btn-text');
  const btnSpinner = submitBtn?.querySelector('.spinner-sm');

  let isSubmitting = false;

  // ── Auto-focus en el primer input ────────────────────────
  if (digits.length > 0) {
    setTimeout(() => digits[0].focus(), 300);
  }

  // ── Manejo de inputs individuales ────────────────────────
  digits.forEach((input, idx) => {
    // Solo permitir dígitos
    input.addEventListener('input', (e) => {
      const value = e.target.value.replace(/\D/g, '');
      e.target.value = value.slice(0, 1);

      // Actualizar estado visual
      if (value) {
        input.classList.add('filled');
        // Auto-avanzar al siguiente input
        if (idx < digits.length - 1) {
          digits[idx + 1].focus();
        }
      } else {
        input.classList.remove('filled');
      }

      clearMessage();
      updateSubmitState();

      // Auto-submit cuando se completan todos los dígitos
      if (getPin().length === PIN_LENGTH) {
        submitPin();
      }
    });

    // Tecla Backspace: retroceder al input anterior
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !input.value && idx > 0) {
        digits[idx - 1].focus();
        digits[idx - 1].value = '';
        digits[idx - 1].classList.remove('filled');
        updateSubmitState();
      }

      // Enter: enviar
      if (e.key === 'Enter') {
        e.preventDefault();
        submitPin();
      }

      // Flechas izquierda/derecha
      if (e.key === 'ArrowLeft' && idx > 0) {
        e.preventDefault();
        digits[idx - 1].focus();
      }
      if (e.key === 'ArrowRight' && idx < digits.length - 1) {
        e.preventDefault();
        digits[idx + 1].focus();
      }
    });

    // Pegar: distribuir entre inputs
    input.addEventListener('paste', (e) => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)
        .getData('text')
        .replace(/\D/g, '')
        .slice(0, PIN_LENGTH);

      pasted.split('').forEach((char, i) => {
        if (digits[i]) {
          digits[i].value = char;
          digits[i].classList.add('filled');
        }
      });

      // Foco en el último input lleno o el siguiente vacío
      const nextIdx = Math.min(pasted.length, digits.length - 1);
      digits[nextIdx].focus();
      updateSubmitState();

      // Auto-submit si se pegó completo
      if (pasted.length === PIN_LENGTH) {
        setTimeout(() => submitPin(), 100);
      }
    });

    // Focus: seleccionar contenido
    input.addEventListener('focus', () => {
      input.select();
    });
  });

  // ── Botón submit ────────────────────────────────────────
  if (submitBtn) {
    submitBtn.addEventListener('click', (e) => {
      e.preventDefault();
      submitPin();
    });
  }

  // ── Obtener PIN completo ────────────────────────────────
  function getPin() {
    return Array.from(digits)
      .map((d) => d.value)
      .join('');
  }

  // ── Actualizar estado del botón ─────────────────────────
  function updateSubmitState() {
    if (submitBtn) {
      submitBtn.disabled = getPin().length < PIN_LENGTH || isSubmitting;
    }
  }

  // ── Enviar PIN ──────────────────────────────────────────
  async function submitPin() {
    const pin = getPin();
    if (pin.length < PIN_LENGTH || isSubmitting) return;

    isSubmitting = true;
    setLoading(true);
    clearMessage();

    try {
      const response = await fetch(
        `${BASE}/api/videos/${encodeURIComponent(VIDEO_CODE)}/verificar-pin`,
        {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pin }),
        }
      );

      const data = await response.json();

      if (response.status === 429) {
        // Rate limited
        showMessage('warning', data.message || 'Demasiados intentos.');
        setDigitsState('error');
        if (attemptsEl) attemptsEl.textContent = '';
        return;
      }

      if (data.valid === true) {
        // ¡Éxito! PIN correcto
        setDigitsState('success');
        showMessage('success', data.message || '¡Código verificado!');

        // Animar salida y cargar video
        setTimeout(() => {
          overlay.classList.add('removing');
          setTimeout(() => {
            overlay.classList.add('hidden');
            overlay.style.display = 'none';

            // Cargar la fuente de video bloqueada
            const videoPlayer = document.getElementById('videoPlayer');
            if (videoPlayer) {
              const source = videoPlayer.querySelector('source[data-src]');
              if (source && source.dataset.src) {
                source.src = source.dataset.src;
                source.removeAttribute('data-src');
              }
              videoPlayer.load();
            }

            // Mostrar controles que estaban ocultos
            document.querySelectorAll('.pin-hidden-until-access').forEach((el) => {
              el.style.display = '';
            });
          }, 500);
        }, 800);
        return;
      }

      // PIN inválido
      setDigitsState('error');

      if (data.expired) {
        showMessage('warning', data.message || 'El código ha expirado.');
      } else {
        showMessage('error', data.message || 'Código incorrecto.');
      }

      // Mostrar intentos restantes
      if (data.remaining !== undefined && attemptsEl) {
        attemptsEl.textContent =
          data.remaining > 0
            ? `${data.remaining} intento(s) restante(s)`
            : 'Sin intentos restantes';
      }

      // Limpiar inputs y volver a enfocar el primero
      setTimeout(() => {
        resetDigits();
        digits[0]?.focus();
      }, 1200);

    } catch (err) {
      showMessage('error', 'Error de conexión. Intenta nuevamente.');
      setDigitsState('error');
      setTimeout(() => resetDigits(), 1200);
    } finally {
      isSubmitting = false;
      setLoading(false);
      updateSubmitState();
    }
  }

  // ── Helpers UI ──────────────────────────────────────────

  function setLoading(loading) {
    if (btnText) btnText.style.display = loading ? 'none' : '';
    if (btnSpinner) btnSpinner.style.display = loading ? 'block' : 'none';
    if (submitBtn) submitBtn.disabled = loading;
  }

  function showMessage(type, text) {
    if (!messageEl) return;
    messageEl.className = 'pin-message visible ' + type;

    const icons = {
      error: 'fas fa-exclamation-circle',
      warning: 'fas fa-exclamation-triangle',
      success: 'fas fa-check-circle',
      info: 'fas fa-info-circle',
    };

    messageEl.innerHTML = `<i class="${icons[type] || icons.info}"></i><span>${text}</span>`;
  }

  function clearMessage() {
    if (messageEl) {
      messageEl.className = 'pin-message';
      messageEl.innerHTML = '';
    }
    if (attemptsEl) attemptsEl.textContent = '';
  }

  function setDigitsState(state) {
    digits.forEach((d) => {
      d.classList.remove('error', 'success');
      if (state) d.classList.add(state);
    });
  }

  function resetDigits() {
    digits.forEach((d) => {
      d.value = '';
      d.classList.remove('filled', 'error', 'success');
    });
    updateSubmitState();
  }
})();

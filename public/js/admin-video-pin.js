/**
 * Admin/Owner Video PIN Generation
 * Maneja la generación de PINs desde el panel de owner.
 */
(function () {
  'use strict';

  const BASE = window.POMPLAY_BASE || '';
  const modal = document.getElementById('pinGenerateModal');
  if (!modal) return;

  const overlay = modal;
  const codeVideoInput = modal.querySelector('#pinGenVideoCode');
  const longitudSelect = modal.querySelector('#pinGenLongitud');
  const minutosSelect = modal.querySelector('#pinGenMinutos');
  const generateBtn = modal.querySelector('#pinGenBtn');
  const resultWrap = modal.querySelector('#pinGenResult');
  const pinDisplay = modal.querySelector('#pinGenDisplay');
  const pinExpiry = modal.querySelector('#pinGenExpiry');
  const copyBtn = modal.querySelector('#pinGenCopy');
  const statusWrap = modal.querySelector('#pinGenStatus');
  const btnText = generateBtn?.querySelector('.btn-text');
  const btnSpinner = generateBtn?.querySelector('.spinner-sm');

  let isGenerating = false;

  // ── Abrir modal ─────────────────────────────────────────
  document.querySelectorAll('[data-pin-generate]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const videoCode = btn.dataset.pinGenerate;

      if (codeVideoInput) codeVideoInput.value = videoCode;

      // Reset estado
      if (resultWrap) resultWrap.style.display = 'none';
      if (statusWrap) {
        statusWrap.className = 'pin-gen-status';
        statusWrap.textContent = '';
      }

      // Verificar si hay PIN activo
      checkActivePin(videoCode);

      overlay.classList.add('active');
    });
  });

  // ── Cerrar modal ────────────────────────────────────────
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  modal.querySelectorAll('[data-action="close"]').forEach((btn) => {
    btn.addEventListener('click', closeModal);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.classList.contains('active')) {
      closeModal();
    }
  });

  function closeModal() {
    overlay.classList.remove('active');
  }

  // ── Verificar PIN activo ────────────────────────────────
  async function checkActivePin(videoCode) {
    if (statusWrap) {
      statusWrap.className = 'pin-gen-status info';
      statusWrap.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando PIN activo...';
    }

    // No hay endpoint GET para esto por seguridad, solo mostramos interfaz de generación
    if (statusWrap) {
      statusWrap.className = 'pin-gen-status';
      statusWrap.textContent = '';
    }
  }

  // ── Generar PIN ─────────────────────────────────────────
  if (generateBtn) {
    generateBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (isGenerating) return;

      const videoCode = codeVideoInput?.value || '';
      if (!videoCode) return;

      const longitud = parseInt(longitudSelect?.value || '6', 10);
      const minutos = parseInt(minutosSelect?.value || '30', 10);

      isGenerating = true;
      setLoading(true);

      try {
        const response = await fetch(
          `${BASE}/api/videos/${encodeURIComponent(videoCode)}/generar-pin`,
          {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              longitud: longitud,
              minutos_expiracion: minutos,
            }),
          }
        );

        const data = await response.json();

        if (data.success && data.pin) {
          // Mostrar PIN generado
          if (pinDisplay) {
            pinDisplay.textContent = formatPin(data.pin);
          }

          if (pinExpiry) {
            const expDate = new Date(data.expira_en.replace(' ', 'T'));
            const now = new Date();
            const diffMin = Math.round((expDate - now) / 60000);
            pinExpiry.textContent = `Expira en ${diffMin} minutos (${formatTime(expDate)})`;
          }

          if (resultWrap) resultWrap.style.display = 'block';

          if (statusWrap) {
            statusWrap.className = 'pin-gen-status success';
            statusWrap.innerHTML =
              '<i class="fas fa-check-circle"></i> PIN generado exitosamente. Comparte este código con el usuario.';
          }
        } else {
          if (statusWrap) {
            statusWrap.className = 'pin-gen-status error';
            statusWrap.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${data.message || 'Error al generar PIN'}`;
          }
        }
      } catch (err) {
        if (statusWrap) {
          statusWrap.className = 'pin-gen-status error';
          statusWrap.innerHTML =
            '<i class="fas fa-exclamation-circle"></i> Error de conexión. Intenta nuevamente.';
        }
      } finally {
        isGenerating = false;
        setLoading(false);
      }
    });
  }

  // ── Copiar PIN ──────────────────────────────────────────
  if (copyBtn) {
    copyBtn.addEventListener('click', () => {
      const pin = pinDisplay?.textContent?.replace(/\s/g, '') || '';
      if (!pin) return;

      navigator.clipboard
        .writeText(pin)
        .then(() => {
          const originalHTML = copyBtn.innerHTML;
          copyBtn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
          copyBtn.classList.add('copied');
          setTimeout(() => {
            copyBtn.innerHTML = originalHTML;
            copyBtn.classList.remove('copied');
          }, 2000);
        })
        .catch(() => {
          // Fallback para navegadores antiguos
          const textArea = document.createElement('textarea');
          textArea.value = pin;
          document.body.appendChild(textArea);
          textArea.select();
          document.execCommand('copy');
          document.body.removeChild(textArea);

          const originalHTML = copyBtn.innerHTML;
          copyBtn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
          setTimeout(() => (copyBtn.innerHTML = originalHTML), 2000);
        });
    });
  }

  // ── Helpers ─────────────────────────────────────────────
  function setLoading(loading) {
    if (btnText) btnText.style.display = loading ? 'none' : '';
    if (btnSpinner) btnSpinner.style.display = loading ? 'inline-block' : 'none';
    if (generateBtn) generateBtn.disabled = loading;
  }

  function formatPin(pin) {
    // Agregar espacio en medio para legibilidad (ej: "123 456")
    if (pin.length === 6) return pin.slice(0, 3) + ' ' + pin.slice(3);
    if (pin.length === 4) return pin.slice(0, 2) + ' ' + pin.slice(2);
    return pin;
  }

  function formatTime(date) {
    return date.toLocaleTimeString('es-PE', {
      hour: '2-digit',
      minute: '2-digit',
    });
  }
})();

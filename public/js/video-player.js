/**
 * REPRODUCTOR DE VIDEO PERSONALIZADO — pomplay.com.pe
 * Controles avanzados + grabación de clips + descarga con marca de agua
 *
 * FIXES v2.8:
 *  - downloadClipWithWatermark: WebM de MediaRecorder no es seekable (duration=Infinity).
 *    Se reemplaza el loop basado en 'ended' + timeout por un render loop con
 *    requestAnimationFrame que se auto-detiene cuando currentTime deja de avanzar
 *    (= video terminó silenciosamente). También se fuerza muted=true y autoplay
 *    para evitar bloqueos de política de autoplay en móvil.
 *  - Botón grabar en móvil: se añade clase .recording-mobile que reemplaza
 *    width:auto por un ancho fijo controlado, evitando desbordamiento.
 */

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const videoPlayer     = document.getElementById('videoPlayer');
  const videoControls   = document.getElementById('videoControls');
  const videoPlayerWrap = document.getElementById('videoPlayerWrap');

  if (!videoPlayer || !videoControls) return;

  /* ── Elementos de control ─────────────────────────────────── */
  const playPauseBtn  = document.getElementById('playPauseBtn');
  const rewindBtn     = document.getElementById('rewindBtn');
  const forwardBtn    = document.getElementById('forwardBtn');
  const volumeBtn     = document.getElementById('volumeBtn');
  const volumeSlider  = document.getElementById('volumeSlider');
  const speedBtn      = document.getElementById('speedBtn');
  const speedMenu     = document.getElementById('speedMenu');
  const pipBtn        = document.getElementById('pipBtn');
  const fullscreenBtn = document.getElementById('fullscreenBtn');
  const progressBar   = document.getElementById('progressBar');
  const progressFilled = document.getElementById('progressFilled');
  const currentTimeEl = document.getElementById('currentTime');
  const durationEl    = document.getElementById('duration');
  const loadingOverlay = document.getElementById('loadingOverlay');
  const zoomInBtn     = document.getElementById('zoomInBtn');
  const zoomOutBtn    = document.getElementById('zoomOutBtn');
  const zoomLevel     = document.getElementById('zoomLevel');

  /* ── Grabación ────────────────────────────────────────────── */
  const recordBtn      = document.getElementById('recordBtn');
  const clipsBtn       = document.getElementById('clipsBtn');
  const clipsBadge     = document.getElementById('clipsBadge');
  const recIndicator   = document.getElementById('recIndicator');
  const recTimer       = document.getElementById('recTimer');

  /* ── Descarga con marca de agua ───────────────────────────── */
  const downloadFullBtn = document.getElementById('downloadFullBtn');

  /* ── Modales ──────────────────────────────────────────────── */
  const clipsModal       = document.getElementById('clipsModal');
  const clipsListEl      = document.getElementById('clipsList');
  const clipsCountLabel  = document.getElementById('clipsCountLabel');
  const downloadAllBtn   = document.getElementById('downloadAllClipsBtn');
  const shareAllBtn      = document.getElementById('shareAllClipsBtn');
  const wmProgress       = document.getElementById('wmProgress');
  const wmBarFill        = document.getElementById('wmBarFill');
  const wmProgressLabel  = document.getElementById('wmProgressLabel');

  /* ── Estado ───────────────────────────────────────────────── */
  let currentZoom  = 1;
  const minZoom    = 1;
  const maxZoom    = 3;
  const zoomStep   = 0.1;

  /* ══ CLIPS STORAGE ═════════════════════════════════════════ */
  const CLIPS_KEY = 'pomplay_clips';

  function loadClips() {
    try {
      const raw = localStorage.getItem(CLIPS_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch { return []; }
  }

  function saveClips(clips) {
    try { localStorage.setItem(CLIPS_KEY, JSON.stringify(clips)); } catch {}
  }

  function updateClipsBadge() {
    const clips = loadClips();
    if (!clipsBadge) return;
    if (clips.length > 0) {
      clipsBadge.textContent = clips.length > 99 ? '99+' : clips.length;
      clipsBadge.classList.add('visible');
    } else {
      clipsBadge.classList.remove('visible');
    }
  }

  /* ══ GRABACIÓN DE CLIPS ════════════════════════════════════ */
  let mediaRecorder     = null;
  let recordedChunks    = [];
  let recordStart       = null;
  let recTimerInt       = null;
  let isRecording       = false;
  let _pendingThumbnail = null;

  function formatDuration(ms) {
    const s = Math.floor(ms / 1000);
    const m = Math.floor(s / 60);
    return `${String(m).padStart(2,'0')}:${String(s % 60).padStart(2,'0')}`;
  }

  function updateRecordBtnTimer(elapsed) {
    if (!recordBtn) return;
    const t = formatDuration(elapsed);
    recordBtn.innerHTML = `<i class="fas fa-stop"></i><span class="rec-btn-timer">${t}</span>`;
  }

  function startRecording() {
    const videoSrc = videoPlayer.currentSrc || videoPlayer.src;
    if (!videoPlayer.srcObject && !videoSrc) {
      showToast('⚠ No hay video cargado para grabar');
      return;
    }

    let stream;
    try {
      const captureFps = 24;
      if (videoPlayer.captureStream) {
        stream = videoPlayer.captureStream(captureFps);
      } else if (videoPlayer.mozCaptureStream) {
        stream = videoPlayer.mozCaptureStream(captureFps);
      } else {
        showToast('⚠ Tu navegador no soporta grabación de clips');
        return;
      }
    } catch (e) {
      console.error('[record] captureStream error:', e);
      showToast('⚠ No se puede grabar este video (error CORS o permisos)');
      return;
    }

    if (!stream || stream.getTracks().length === 0) {
      showToast('⚠ No se pudo capturar el stream del video');
      return;
    }

    recordedChunks = [];

    const mimeType = [
      'video/webm;codecs=vp8',
      'video/webm;codecs=vp9',
      'video/webm',
      'video/mp4'
    ].find(t => MediaRecorder.isTypeSupported(t)) || '';

    try {
      mediaRecorder = new MediaRecorder(stream, {
        ...(mimeType ? { mimeType } : {}),
        videoBitsPerSecond: 1_500_000,
      });
    } catch (e) {
      try { mediaRecorder = new MediaRecorder(stream); }
      catch (e2) {
        showToast('⚠ Formato de grabación no soportado en este navegador');
        return;
      }
    }

    mediaRecorder.ondataavailable = (e) => {
      if (e.data && e.data.size > 0) recordedChunks.push(e.data);
    };

    mediaRecorder.onstop = () => {
      if (recordedChunks.length === 0) {
        showToast('⚠ No se capturó ningún dato — intenta grabar más tiempo');
        return;
      }
      const finalMime = mediaRecorder.mimeType || mimeType || 'video/webm';
      const blob = new Blob(recordedChunks, { type: finalMime });
      if (blob.size < 500) {
        showToast('⚠ Clip demasiado corto, graba al menos 1 segundo');
        return;
      }
      const duration = Date.now() - recordStart;
      saveClipToStorage(blob, duration, finalMime);
    };

    recordStart = Date.now();
    mediaRecorder.start(500);
    isRecording = true;

    if (videoPlayer.paused) videoPlayer.play();

    if (recordBtn) {
      recordBtn.classList.add('recording');
      recordBtn.setAttribute('title', 'Detener grabación');
      recordBtn.innerHTML = `<i class="fas fa-stop"></i><span class="rec-btn-timer">00:00</span>`;
    }
    if (recIndicator) recIndicator.classList.add('active');

    recTimerInt = setInterval(() => {
      const elapsed = Date.now() - recordStart;
      const t = formatDuration(elapsed);
      if (recTimer) recTimer.textContent = t;
      updateRecordBtnTimer(elapsed);
    }, 500);

    showToast('● Grabando — toca ⏹ para detener');
  }

  function captureVideoFrame() {
    try {
      const vw = videoPlayer.videoWidth;
      const vh = videoPlayer.videoHeight;
      if (!vw || !vh) return null;
      const c = document.createElement('canvas');
      c.width  = vw;
      c.height = vh;
      c.getContext('2d').drawImage(videoPlayer, 0, 0, vw, vh);
      return c.toDataURL('image/jpeg', 0.82);
    } catch (e) {
      console.warn('[captureVideoFrame]', e);
      return null;
    }
  }

  function stopRecording() {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') return;
    _pendingThumbnail = captureVideoFrame();
    mediaRecorder.requestData();
    mediaRecorder.stop();
    isRecording = false;
    clearInterval(recTimerInt);

    if (recordBtn) {
      recordBtn.classList.remove('recording');
      recordBtn.setAttribute('title', 'Grabar clip');
      recordBtn.innerHTML = '<i class="fas fa-circle"></i>';
    }
    if (recIndicator) recIndicator.classList.remove('active');
    if (recTimer) recTimer.textContent = '00:00';
  }

  function saveClipToStorage(blob, durationMs, mimeType) {
    const ext = mimeType.includes('mp4') ? 'mp4' : 'webm';
    const thumbnail = _pendingThumbnail || captureVideoFrame();
    _pendingThumbnail = null;

    const reader = new FileReader();
    reader.onloadend = () => {
      try {
        const clips = loadClips();
        clips.push({
          id:        Date.now(),
          name:      `clip_${clips.length + 1}.${ext}`,
          data:      reader.result,
          thumbnail,
          duration:  durationMs,
          mimeType,
          createdAt: new Date().toISOString(),
        });
        saveClips(clips);
        updateClipsBadge();
        renderClipsList();
        showToast('✓ Clip guardado', 'success');
      } catch (err) {
        console.error('Error guardando clip:', err);
        showToast('⚠ Sin espacio — descargando clip directamente');
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `clip_${Date.now()}.${ext}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(url), 5000);
      }
    };
    reader.onerror = () => showToast('⚠ Error al procesar el clip');
    reader.readAsDataURL(blob);
  }

  if (recordBtn) {
    recordBtn.addEventListener('click', () => {
      if (isRecording) stopRecording();
      else startRecording();
    });
  }

  /* ══ MODAL DE CLIPS ════════════════════════════════════════ */
  function openClipsModal() {
    if (!clipsModal) return;
    renderClipsList();
    clipsModal.style.display = 'block';
    requestAnimationFrame(() => clipsModal.classList.add('open'));
  }

  function closeClipsModal() {
    if (!clipsModal) return;
    clipsModal.classList.remove('open');
    setTimeout(() => { clipsModal.style.display = ''; }, 50);
  }

  async function renderClipsList() {
    if (!clipsListEl) return;
    const clips = loadClips();
    if (clipsCountLabel) clipsCountLabel.textContent = clips.length;

    if (clips.length === 0) {
      clipsListEl.innerHTML = `
        <div class="clips-empty">
          <i class="fas fa-film"></i>
          No hay clips grabados todavía
        </div>`;
      return;
    }

    clipsListEl.innerHTML = clips.map(clip => {
      const thumbContent = clip.thumbnail
        ? `<img src="${clip.thumbnail}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" alt="Clip thumbnail">`
        : `<i class="fas fa-film" style="color:rgba(255,255,255,0.25);font-size:22px;"></i>`;
      return `
      <div class="clip-item" data-id="${clip.id}">
        <div class="clip-thumb" id="thumb-${clip.id}"
             style="display:flex;align-items:center;justify-content:center;background:#12121e;overflow:hidden;">
          ${thumbContent}
        </div>
        <div class="clip-info">
          <div class="clip-name">${escapeHtml(clip.name)}</div>
          <div class="clip-meta">${formatDuration(clip.duration)} · ${new Date(clip.createdAt).toLocaleDateString()}</div>
        </div>
        <div class="clip-actions">
          <button class="clip-action-btn" data-action="download" data-id="${clip.id}" title="Descargar">
            <i class="fas fa-download"></i>
          </button>
          <button class="clip-action-btn" data-action="share" data-id="${clip.id}" title="Compartir">
            <i class="fas fa-share-alt"></i>
          </button>
          <button class="clip-action-btn danger" data-action="delete" data-id="${clip.id}" title="Eliminar">
            <i class="fas fa-trash"></i>
          </button>
        </div>
      </div>`;
    }).join('');

    for (const clip of clips) {
      if (clip.thumbnail) continue;
      const wrap = document.getElementById(`thumb-${clip.id}`);
      if (!wrap) continue;
      captureThumbnail(clip, wrap);
    }
  }

  async function captureThumbnail(clip, wrap) {
    let blobUrl;
    let vid;
    try {
      const blob = await fetch(clip.data).then(r => r.blob());
      blobUrl = URL.createObjectURL(blob);

      vid = document.createElement('video');
      vid.muted    = true;
      vid.playsInline = true;
      vid.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:320px;height:180px;opacity:0;pointer-events:none;';
      vid.src = blobUrl;
      document.body.appendChild(vid);

      await new Promise(res => {
        vid.addEventListener('loadedmetadata', res, { once: true });
        vid.addEventListener('canplay',        res, { once: true });
        vid.onerror = res;
        setTimeout(res, 6000);
        vid.load();
      });

      await new Promise(res => {
        let done = false;
        const finish = (ok) => { if (!done) { done = true; res(ok); } };
        vid.addEventListener('timeupdate', () => { if (vid.currentTime > 0) finish(true); }, { once: true });
        vid.onerror = () => finish(false);
        setTimeout(() => finish(true), 3000);
        vid.play().catch(() => finish(false));
      });

      vid.pause();

      const W = (vid.videoWidth  > 0 ? vid.videoWidth  : 320);
      const H = (vid.videoHeight > 0 ? vid.videoHeight : 180);
      const c = document.createElement('canvas');
      c.width = W; c.height = H;
      c.getContext('2d').drawImage(vid, 0, 0, W, H);

      const imgSrc = c.toDataURL('image/jpeg', 0.82);
      document.body.removeChild(vid);
      URL.revokeObjectURL(blobUrl);

      wrap.innerHTML = `<img src="${imgSrc}"
        style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"
        alt="Thumbnail del clip">`;
    } catch (err) {
      console.warn('[captureThumbnail] Error:', err);
      if (vid && vid.parentNode) { try { document.body.removeChild(vid); } catch {} }
      if (blobUrl) URL.revokeObjectURL(blobUrl);
    }
  }

  if (clipsListEl) {
    clipsListEl.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const action = btn.dataset.action;
      const id     = parseInt(btn.dataset.id);
      const clips  = loadClips();
      const clip   = clips.find(c => c.id === id);
      if (!clip) return;

      if (action === 'download') {
        downloadClipWithWatermark(clip);
      } else if (action === 'share') {
        shareClip(clip);
      } else if (action === 'delete') {
        const updated = clips.filter(c => c.id !== id);
        saveClips(updated);
        updateClipsBadge();
        renderClipsList();
        showToast('Clip eliminado');
      }
    });
  }

  /* ══ LOGO ══════════════════════════════════════════════════ */
  let _logoImg = null;
  function loadLogo() {
    if (_logoImg) return Promise.resolve(_logoImg);
    return new Promise(res => {
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload  = () => { _logoImg = img; res(img); };
      img.onerror = () => res(null);
      img.src = (window.POMPLAY_BASE || '') + '/public/images/pomplay sin fondo.png';
    });
  }

  /* ══ DESCARGA DE CLIP CON MARCA DE AGUA + OUTRO ════════════
   *
   * PROBLEMA RAÍZ: El archivo WebM generado por MediaRecorder no tiene
   * índice de duración correcto (duration = Infinity o NaN). Esto hace que:
   *   - tmpVideo.duration sea Infinity → timeout de seguridad nunca activa
   *   - El evento 'ended' puede no dispararse en algunos navegadores
   *   - currentTime no se puede setear (no seekable)
   *
   * SOLUCIÓN: render loop con requestAnimationFrame que detecta que el video
   * terminó comparando currentTime entre dos frames consecutivos. Si el tiempo
   * no avanza por ~500ms, consideramos que el video terminó.
   */
  async function downloadClipWithWatermark(clip) {
    showToast('⏳ Procesando clip con marca de agua…');

    const logo = await loadLogo();

    /* 1. Convertir data URL → blob URL */
    let srcBlobUrl;
    try {
      const blob = await fetch(clip.data).then(r => r.blob());
      srcBlobUrl = URL.createObjectURL(blob);
    } catch {
      showToast('⚠ Error preparando el clip');
      return;
    }

    /* 2. Cargar video temporal en el DOM (necesario para que el navegador
       decodifique frames — los elementos off-DOM no reciben frames en algunos
       navegadores, especialmente WebM de MediaRecorder) */
    const tmpVideo = document.createElement('video');
    tmpVideo.muted       = true;
    tmpVideo.playsInline = true;
    tmpVideo.preload     = 'auto';
    tmpVideo.src         = srcBlobUrl;
    /* Tamaño real y visible (aunque off-screen) para decodificación correcta */
    tmpVideo.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:320px;height:180px;opacity:0;pointer-events:none;';
    document.body.appendChild(tmpVideo);

    const cleanupSrc = () => {
      try { document.body.removeChild(tmpVideo); } catch {}
      URL.revokeObjectURL(srcBlobUrl);
    };

    /* Esperar a que el video tenga metadatos y pueda reproducirse */
    await new Promise(res => {
      tmpVideo.addEventListener('loadedmetadata', res, { once: true });
      tmpVideo.addEventListener('canplay',        res, { once: true });
      tmpVideo.onerror = () => res();
      tmpVideo.load();
      setTimeout(res, 8000);
    });

    /* 3. Dimensiones reales */
    const W = (tmpVideo.videoWidth  > 0 ? tmpVideo.videoWidth  : 1280);
    const H = (tmpVideo.videoHeight > 0 ? tmpVideo.videoHeight : 720);

    /* 4. Canvas de salida */
    const canvas = document.createElement('canvas');
    canvas.width  = W;
    canvas.height = H;
    canvas.style.cssText = 'position:fixed;top:-9998px;left:-9999px;width:1px;height:1px;pointer-events:none;';
    document.body.appendChild(canvas);
    const ctx = canvas.getContext('2d');
    const cleanupCanvas = () => { try { document.body.removeChild(canvas); } catch {}; };

    /* 5. MediaRecorder sobre canvas */
    const mimeOut = ['video/webm;codecs=vp9','video/webm;codecs=vp8','video/webm']
      .find(t => MediaRecorder.isTypeSupported(t)) || 'video/webm';

    let canvasStream;
    try {
      canvasStream = canvas.captureStream(30);
    } catch (e) {
      showToast('⚠ Tu navegador no puede procesar el clip');
      cleanupSrc(); cleanupCanvas();
      return;
    }

    const recorder = new MediaRecorder(canvasStream, {
      mimeType: mimeOut,
      videoBitsPerSecond: 4_000_000
    });
    const chunks = [];
    recorder.ondataavailable = e => { if (e.data && e.data.size > 0) chunks.push(e.data); };
    recorder.start(100);

    /* Helpers de dibujo */
    function drawOverlay() {
      if (logo && logo.naturalWidth > 0) {
        const ls = Math.max(48, W * 0.07);
        const lh = ls * (logo.naturalHeight / logo.naturalWidth);
        ctx.globalAlpha = 0.9;
        ctx.drawImage(logo, W - ls - 14, 10, ls, lh);
        ctx.globalAlpha = 1;
        ctx.font = `600 ${Math.max(11, W * 0.014)}px 'DM Mono',monospace`;
        ctx.textAlign = 'right'; ctx.textBaseline = 'top';
        ctx.shadowColor = 'rgba(0,0,0,0.85)'; ctx.shadowBlur = 5;
        ctx.fillStyle = 'rgba(255,255,255,0.92)';
        ctx.fillText('pomplay.com.pe', W - 14, 10 + lh + 4);
      } else {
        ctx.font = `600 ${Math.max(14, W * 0.018)}px 'DM Mono',monospace`;
        ctx.textAlign = 'right'; ctx.textBaseline = 'top';
        ctx.shadowColor = 'rgba(0,0,0,0.85)'; ctx.shadowBlur = 6;
        ctx.fillStyle = 'rgba(255,255,255,0.9)';
        ctx.fillText('pomplay.com.pe', W - 16, 14);
      }
      /* Marca de agua diagonal semitransparente */
      ctx.save();
      ctx.translate(W / 2, H / 2);
      ctx.rotate(-Math.PI / 6);
      ctx.font = `500 ${Math.max(20, W * 0.032)}px 'DM Sans',sans-serif`;
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillStyle = 'rgba(255,255,255,0.06)';
      ctx.fillText('pomplay.com.pe', 0, 0);
      ctx.restore();

      ctx.shadowBlur = 0; ctx.shadowColor = 'transparent';
    }

    function drawOutro(alpha) {
      ctx.fillStyle = '#08080f';
      ctx.fillRect(0, 0, W, H);
      if (logo && logo.naturalWidth > 0) {
        const lw = Math.min(W * 0.38, 320);
        const lh = lw * (logo.naturalHeight / logo.naturalWidth);
        ctx.globalAlpha = alpha;
        ctx.drawImage(logo, (W - lw) / 2, H / 2 - lh / 2 - H * 0.06, lw, lh);
        ctx.globalAlpha = 1;
      }
      ctx.font = `700 ${Math.max(18, W * 0.026)}px 'DM Sans',sans-serif`;
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.shadowColor = 'rgba(231,76,60,0.6)'; ctx.shadowBlur = 16;
      ctx.globalAlpha = alpha; ctx.fillStyle = '#ffffff';
      ctx.fillText('pomplay.com.pe', W / 2, H / 2 + (logo ? H * 0.14 : 0));
      ctx.globalAlpha = 1; ctx.shadowBlur = 0; ctx.shadowColor = 'transparent';
    }

    /* 6. Reproducir el clip
       CLAVE: los WebM de MediaRecorder no son seekables. Solo usamos play()
       y detectamos el fin del video por inactividad de currentTime. */
    try {
      await tmpVideo.play();
    } catch (e) {
      showToast('⚠ El clip no pudo reproducirse');
      recorder.stop();
      cleanupSrc(); cleanupCanvas();
      return;
    }

    /* Esperar primer frame decodificado */
    await new Promise(res => {
      if (tmpVideo.currentTime > 0) { res(); return; }
      const check = () => {
        if (tmpVideo.currentTime > 0) res();
        else setTimeout(check, 50);
      };
      setTimeout(check, 50);
      setTimeout(res, 2000); // fallback
    });

    /* ── Render loop: copia frames al canvas hasta que el video se detenga.
       Detectamos el fin comparando currentTime entre dos animationFrames
       separados ~500ms. Si no avanzó, el video terminó. ── */
    await new Promise(res => {
      let lastTime = -1;
      let stuckSince = 0;
      const STUCK_MS = 600; // ms sin avance → consideramos terminado

      function loop(now) {
        try {
          ctx.drawImage(tmpVideo, 0, 0, W, H);
          drawOverlay();
        } catch {}

        const ct = tmpVideo.currentTime;
        if (ct !== lastTime) {
          lastTime = ct;
          stuckSince = now;
        } else if (now - stuckSince > STUCK_MS) {
          /* currentTime no avanzó en STUCK_MS → video terminó */
          res();
          return;
        }

        /* También salir si el video dispara ended explícitamente */
        if (tmpVideo.ended) { res(); return; }

        requestAnimationFrame(loop);
      }

      /* Listener explícito por si 'ended' sí dispara */
      tmpVideo.addEventListener('ended', res, { once: true });

      /* Timeout de seguridad absoluto: duración del clip * 1.5 + 10s */
      const safeMs = isFinite(tmpVideo.duration)
        ? tmpVideo.duration * 1500 + 10000
        : clip.duration * 1.5 + 10000;
      setTimeout(res, Math.max(safeMs, 15000));

      requestAnimationFrame(loop);
    });

    tmpVideo.pause();

    /* 7. Outro con fade — 3 segundos */
    const OUTRO_MS = 3000;
    const t0 = performance.now();
    await new Promise(res => {
      const f = now => {
        const p = (now - t0) / OUTRO_MS;
        if (p >= 1) { drawOutro(1); res(); return; }
        const a = p < 0.18 ? p / 0.18 : p > 0.82 ? (1 - p) / 0.18 : 1;
        drawOutro(a);
        requestAnimationFrame(f);
      };
      requestAnimationFrame(f);
    });

    /* 8. Finalizar grabación */
    recorder.stop();
    await new Promise(res => { recorder.onstop = res; });
    cleanupSrc();
    cleanupCanvas();

    const finalBlob = new Blob(chunks, { type: mimeOut });
    if (finalBlob.size < 1000) {
      showToast('⚠ Error: el clip procesado está vacío');
      return;
    }

    const url = URL.createObjectURL(finalBlob);
    const a = document.createElement('a');
    a.href = url;
    a.download = clip.name.replace(/\.[^.]+$/, '') + '_pomplay.webm';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 8000);
    showToast('✓ Clip descargado con marca de agua', 'success');
  }

  function triggerDownload(dataUrl, filename) {
    const a = document.createElement('a');
    a.href = dataUrl; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  }

  async function shareClip(clip) {
    if (navigator.share && navigator.canShare) {
      try {
        const blob = await fetch(clip.data).then(r => r.blob());
        const file = new File([blob], clip.name, { type: clip.mimeType });
        if (navigator.canShare({ files: [file] })) {
          await navigator.share({ files: [file], title: clip.name });
          return;
        }
      } catch (e) {
        if (e.name === 'AbortError') return;
      }
    }
    const blob = await fetch(clip.data).then(r => r.blob());
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = clip.name;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 5000);
    showToast('Compartir solo disponible en móvil — descargado');
  }

  if (downloadAllBtn) {
    downloadAllBtn.addEventListener('click', async () => {
      const clips = loadClips();
      if (clips.length === 0) { showToast('No hay clips'); return; }
      showToast(`Procesando ${clips.length} clip${clips.length !== 1 ? 's' : ''}…`);
      for (const clip of clips) {
        await downloadClipWithWatermark(clip);
      }
    });
  }

  if (shareAllBtn) {
    shareAllBtn.addEventListener('click', async () => {
      const clips = loadClips();
      if (clips.length === 0) { showToast('No hay clips'); return; }
      if (navigator.share) {
        try {
          const files = await Promise.all(clips.map(async c => {
            const blob = await dataURLtoBlob(c.data, c.mimeType);
            return new File([blob], c.name, { type: c.mimeType });
          }));
          await navigator.share({ files, title: 'Mis clips - pomplay.com.pe' });
        } catch (e) {
          if (e.name !== 'AbortError') {
            clips.forEach((c, i) => setTimeout(() => triggerDownload(c.data, c.name), i * 300));
            showToast('Compartir no disponible — descargando');
          }
        }
      } else {
        clips.forEach((c, i) => setTimeout(() => triggerDownload(c.data, c.name), i * 300));
        showToast('Descargando todos los clips…');
      }
    });
  }

  if (clipsBtn) clipsBtn.addEventListener('click', openClipsModal);

  if (clipsModal) {
    const backdrop = clipsModal.querySelector('.clips-modal-backdrop');
    const closeBtn = clipsModal.querySelector('.clips-modal-close');
    if (backdrop) backdrop.addEventListener('click', closeClipsModal);
    if (closeBtn) closeBtn.addEventListener('click', closeClipsModal);
  }

  /* ══ DESCARGA COMPLETA CON MARCA DE AGUA ═══════════════════ */
  if (downloadFullBtn) {
    downloadFullBtn.addEventListener('click', () => downloadWithWatermark());
  }

  async function downloadWithWatermark() {
    const videoSrc = videoPlayer.currentSrc || videoPlayer.src || videoPlayer.querySelector('source')?.src;
    if (!videoSrc) { showToast('No hay video disponible'); return; }

    showToast('Preparando descarga con marca de agua…');
    if (wmProgress) wmProgress.classList.add('active');
    if (wmBarFill)  wmBarFill.style.width = '5%';
    if (wmProgressLabel) wmProgressLabel.textContent = 'Cargando video…';

    try {
      const tmpVideo = document.createElement('video');
      tmpVideo.crossOrigin = 'anonymous';
      tmpVideo.src = videoSrc;
      tmpVideo.muted = true;
      tmpVideo.preload = 'auto';

      await new Promise((res, rej) => {
        tmpVideo.onloadeddata = res;
        tmpVideo.onerror = rej;
        tmpVideo.load();
      });

      const canvas  = document.createElement('canvas');
      const ctx     = canvas.getContext('2d');
      canvas.width  = tmpVideo.videoWidth  || 1280;
      canvas.height = tmpVideo.videoHeight || 720;

      const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9')
        ? 'video/webm;codecs=vp9'
        : 'video/webm';

      const canvasStream = canvas.captureStream(30);
      const recorder = new MediaRecorder(canvasStream, {
        mimeType,
        videoBitsPerSecond: 4000000
      });
      const chunks = [];
      recorder.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };

      function drawWatermark() {
        ctx.drawImage(tmpVideo, 0, 0, canvas.width, canvas.height);
        const topRightSize = Math.max(14, canvas.width * 0.018);
        ctx.font        = `600 ${topRightSize}px 'DM Mono', monospace`;
        ctx.textAlign   = 'right';
        ctx.textBaseline = 'top';
        ctx.shadowColor   = 'rgba(0,0,0,0.8)';
        ctx.shadowBlur    = 6;
        ctx.shadowOffsetX = 1;
        ctx.shadowOffsetY = 1;
        ctx.fillStyle   = 'rgba(255,255,255,0.88)';
        ctx.fillText('pomplay.com.pe', canvas.width - 18, 14);
        ctx.shadowBlur = 0;
        ctx.shadowColor = 'transparent';

        ctx.save();
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(-Math.PI / 6);
        ctx.font      = `500 ${Math.max(28, canvas.width * 0.04)}px 'DM Sans', sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = 'rgba(255,255,255,0.07)';
        ctx.fillText('pomplay.com.pe', 0, 0);
        ctx.restore();

        drawLogoCorner(ctx, canvas.width, canvas.height);
      }

      function drawLogoCorner(ctx, w, h) {
        const size   = Math.max(32, w * 0.045);
        const x      = w - size - 14;
        const y      = h - size - 14;
        const radius = size * 0.22;
        ctx.beginPath();
        roundRect(ctx, x, y, size, size, radius);
        ctx.fillStyle = 'rgba(231,76,60,0.85)';
        ctx.fill();
        const cx = x + size / 2;
        const cy = y + size / 2;
        const ts = size * 0.32;
        ctx.beginPath();
        ctx.moveTo(cx - ts * 0.6, cy - ts);
        ctx.lineTo(cx - ts * 0.6, cy + ts);
        ctx.lineTo(cx + ts,       cy);
        ctx.closePath();
        ctx.fillStyle = '#fff';
        ctx.fill();
      }

      function roundRect(ctx, x, y, w, h, r) {
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
      }

      tmpVideo.currentTime = 0;
      recorder.start(100);

      await new Promise(res => { tmpVideo.onseeked = res; tmpVideo.currentTime = 0; });

      if (wmBarFill) wmBarFill.style.width = '15%';
      if (wmProgressLabel) wmProgressLabel.textContent = 'Procesando…';

      tmpVideo.play();

      await new Promise((res) => {
        const renderLoop = () => {
          if (tmpVideo.ended || tmpVideo.paused) { res(); return; }
          drawWatermark();
          const pct = tmpVideo.duration > 0
            ? 15 + (tmpVideo.currentTime / tmpVideo.duration) * 80
            : 50;
          if (wmBarFill) wmBarFill.style.width = `${Math.min(95, pct)}%`;
          requestAnimationFrame(renderLoop);
        };
        tmpVideo.onended  = res;
        tmpVideo.onerror  = res;
        renderLoop();
      });

      recorder.stop();
      await new Promise(res => { recorder.onstop = res; });

      if (wmBarFill) wmBarFill.style.width = '100%';
      if (wmProgressLabel) wmProgressLabel.textContent = 'Generando archivo…';

      const blob = new Blob(chunks, { type: mimeType });
      const url  = URL.createObjectURL(blob);
      const ext  = mimeType.includes('mp4') ? 'mp4' : 'webm';
      const a    = document.createElement('a');
      a.href     = url;
      a.download = `pomplay_video_${Date.now()}.${ext}`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);

      showToast('Video descargado con marca de agua ✓');
    } catch (err) {
      console.error('Error en descarga con marca de agua:', err);
      const a  = document.createElement('a');
      a.href   = videoSrc;
      a.download = 'pomplay_video.mp4';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      showToast('Descargando video (sin marca de agua en este navegador)');
    } finally {
      setTimeout(() => {
        if (wmProgress) wmProgress.classList.remove('active');
        if (wmBarFill)  wmBarFill.style.width = '0%';
      }, 1200);
    }
  }

  /* ══ UTILIDADES ════════════════════════════════════════════ */
  async function dataURLtoBlob(dataUrl, mimeType) {
    try {
      const res  = await fetch(dataUrl);
      const blob = await res.blob();
      return (blob.type && blob.type !== 'application/octet-stream')
        ? blob
        : new Blob([blob], { type: mimeType });
    } catch {
      const b64   = dataUrl.split(',')[1].replace(/-/g,'+').replace(/_/g,'/');
      const pad   = b64.padEnd(b64.length + (4 - b64.length % 4) % 4, '=');
      const bstr  = atob(pad);
      const u8arr = new Uint8Array(bstr.length);
      for (let i = 0; i < bstr.length; i++) u8arr[i] = bstr.charCodeAt(i);
      return new Blob([u8arr], { type: mimeType });
    }
  }

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }

  function showToast(msg, type = 'info') {
    let toast = document.querySelector('.toast-notification');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'toast-notification';
      document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.borderColor = '';
    toast.style.color = '';
    if (type === 'success' || msg.startsWith('✓')) {
      toast.style.borderColor = 'rgba(46,213,115,0.5)';
      toast.style.color = '#2ed573';
    } else if (type === 'error' || msg.startsWith('⚠')) {
      toast.style.borderColor = 'rgba(231,76,60,0.5)';
      toast.style.color = '#ff6b5b';
    } else if (msg.startsWith('●')) {
      toast.style.borderColor = 'rgba(231,76,60,0.45)';
    }

    toast.classList.add('show');
    clearTimeout(toast._t);
    const duration = (type === 'success' || msg.startsWith('✓')) ? 3500
                   : (type === 'error')  ? 4000 : 3000;
    toast._t = setTimeout(() => toast.classList.remove('show'), duration);
  }

  /* ══ PLAY / PAUSE ══════════════════════════════════════════ */
  function syncPlayIcon() {
    if (!playPauseBtn) return;
    playPauseBtn.innerHTML = videoPlayer.paused
      ? '<i class="fas fa-play"></i>'
      : '<i class="fas fa-pause"></i>';
  }

  videoPlayer.addEventListener('play',  syncPlayIcon);
  videoPlayer.addEventListener('pause', syncPlayIcon);
  videoPlayer.addEventListener('ended', syncPlayIcon);

  if (playPauseBtn) {
    playPauseBtn.addEventListener('click', function () {
      if (videoPlayer.paused || videoPlayer.ended) {
        const p = videoPlayer.play();
        if (p && typeof p.catch === 'function') {
          p.catch(err => { console.warn('[play] bloqueado:', err); syncPlayIcon(); });
        }
      } else {
        videoPlayer.pause();
      }
    });
  }

  videoPlayer.addEventListener('click', () => playPauseBtn?.click());

  /* ══ RETROCEDER / ADELANTAR ════════════════════════════════ */
  rewindBtn?.addEventListener('click',  () => { videoPlayer.currentTime = Math.max(0, videoPlayer.currentTime - 10); });
  forwardBtn?.addEventListener('click', () => { videoPlayer.currentTime = Math.min(videoPlayer.duration, videoPlayer.currentTime + 10); });

  /* ══ VOLUMEN ═══════════════════════════════════════════════ */
  function updateVolumeIcon() {
    if (!volumeBtn) return;
    const v = videoPlayer.volume;
    let icon = 'fa-volume-up';
    if (v === 0 || videoPlayer.muted) icon = 'fa-volume-mute';
    else if (v < 0.5) icon = 'fa-volume-down';
    volumeBtn.innerHTML = `<i class="fas ${icon}"></i>`;
  }

  volumeBtn?.addEventListener('click', function () {
    if (videoPlayer.muted) {
      videoPlayer.muted = false;
      if (volumeSlider) volumeSlider.value = videoPlayer.volume * 100;
    } else {
      videoPlayer.muted = true;
    }
    updateVolumeIcon();
  });

  volumeSlider?.addEventListener('input', function () {
    videoPlayer.volume = this.value / 100;
    videoPlayer.muted  = false;
    updateVolumeIcon();
  });

  /* ══ VELOCIDAD ═════════════════════════════════════════════ */
  speedBtn?.addEventListener('click', (e) => {
    e.stopPropagation();
    speedMenu?.classList.toggle('open');
  });

  document.addEventListener('click', () => speedMenu?.classList.remove('open'));

  speedMenu?.addEventListener('click', (e) => {
    if (e.target.tagName !== 'BUTTON') return;
    const speed = parseFloat(e.target.dataset.speed);
    videoPlayer.playbackRate = speed;
    const st = speedBtn?.querySelector('.speed-text');
    if (st) st.textContent = speed + 'x';
    speedMenu.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
  });

  /* ══ PICTURE-IN-PICTURE ════════════════════════════════════ */
  if (document.pictureInPictureEnabled && pipBtn) {
    pipBtn.addEventListener('click', async () => {
      try {
        if (document.pictureInPictureElement) await document.exitPictureInPicture();
        else await videoPlayer.requestPictureInPicture();
      } catch (e) { console.error(e); }
    });
  } else if (pipBtn) {
    pipBtn.style.display = 'none';
  }

  /* ══ FULLSCREEN ════════════════════════════════════════════ */
  function requestFS() {
    if (!document.fullscreenElement) {
      (videoPlayerWrap.requestFullscreen || videoPlayerWrap.webkitRequestFullscreen || videoPlayerWrap.msRequestFullscreen)?.call(videoPlayerWrap);
      if (fullscreenBtn) fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
    } else {
      document.exitFullscreen?.();
      if (fullscreenBtn) fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
    }
  }

  fullscreenBtn?.addEventListener('click', requestFS);
  document.getElementById('railFullscreenBtn')?.addEventListener('click', requestFS);

  document.addEventListener('fullscreenchange', () => {
    if (fullscreenBtn) {
      fullscreenBtn.innerHTML = document.fullscreenElement
        ? '<i class="fas fa-compress"></i>'
        : '<i class="fas fa-expand"></i>';
    }
  });

  /* ══ PROGRESO ══════════════════════════════════════════════ */
  videoPlayer.addEventListener('timeupdate', () => {
    if (!videoPlayer.duration) return;
    const pct = (videoPlayer.currentTime / videoPlayer.duration) * 100;
    if (progressFilled) progressFilled.style.width = pct + '%';
    if (currentTimeEl)  currentTimeEl.textContent  = formatTime(videoPlayer.currentTime);
  });

  videoPlayer.addEventListener('loadedmetadata', () => {
    if (durationEl) durationEl.textContent = formatTime(videoPlayer.duration);
  });

  progressBar?.addEventListener('click', (e) => {
    const rect = progressBar.getBoundingClientRect();
    const pct  = (e.clientX - rect.left) / rect.width;
    videoPlayer.currentTime = pct * videoPlayer.duration;
  });

  function formatTime(s) {
    const m = Math.floor(s / 60);
    return `${m}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
  }

  /* ══ CONTROLES AUTO-FADE ═══════════════════════════════════ */
  const wrap = videoPlayerWrap;

  if (wrap && window.matchMedia('(hover: none)').matches) {
    wrap.classList.add('controls-visible');
  }

  if (wrap && window.matchMedia('(hover: hover)').matches) {
    let fadeTimer;
    const showCtrls = () => {
      wrap.classList.add('controls-visible');
      clearTimeout(fadeTimer);
      fadeTimer = setTimeout(() => {
        if (!videoPlayer.paused) wrap.classList.remove('controls-visible');
      }, 3000);
    };
    wrap.addEventListener('mousemove',  showCtrls);
    wrap.addEventListener('mouseenter', showCtrls);
    videoPlayer.addEventListener('play',  () => {
      fadeTimer = setTimeout(() => wrap.classList.remove('controls-visible'), 3000);
    });
    videoPlayer.addEventListener('pause', () => wrap.classList.add('controls-visible'));
  }

  /* ══ LOADING ═══════════════════════════════════════════════ */
  function hideLoader() {
    if (!loadingOverlay) return;
    loadingOverlay.classList.remove('show');
    loadingOverlay.style.pointerEvents = 'none';
    setTimeout(() => {
      if (!loadingOverlay.classList.contains('show')) {
        loadingOverlay.style.display = 'none';
      }
    }, 350);
  }
  function showLoader() {
    if (!loadingOverlay) return;
    loadingOverlay.style.display = 'flex';
    void loadingOverlay.offsetHeight;
    loadingOverlay.classList.add('show');
  }

  ['canplay','canplaythrough','loadeddata','loadedmetadata','playing'].forEach(ev =>
    videoPlayer.addEventListener(ev, hideLoader)
  );
  videoPlayer.addEventListener('waiting', showLoader);
  videoPlayer.addEventListener('stalled', showLoader);
  videoPlayer.addEventListener('error', () => { hideLoader(); showToast('Error al cargar el video'); });
  const _loaderTimer = setTimeout(hideLoader, 5000);
  videoPlayer.addEventListener('canplay', () => clearTimeout(_loaderTimer), { once: true });
  if (videoPlayer.readyState >= 2) hideLoader();

  /* ══ ZOOM ══════════════════════════════════════════════════ */
  function setZoom(level) {
    currentZoom = Math.max(minZoom, Math.min(maxZoom, level));
    videoPlayer.style.transform       = `scale(${currentZoom})`;
    videoPlayer.style.transformOrigin = 'center center';
    if (zoomLevel) zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
    showZoomIndicator(currentZoom);
  }

  function showZoomIndicator(zoom) {
    let ind = document.getElementById('zoomIndicator');
    if (!ind) {
      ind = document.createElement('div');
      ind.id = 'zoomIndicator';
      ind.style.cssText = `position:absolute;top:20px;right:20px;background:rgba(0,0,0,0.8);
        color:#fff;padding:8px 16px;border-radius:8px;font-size:14px;font-weight:600;
        z-index:1000;transition:opacity 0.3s;pointer-events:none;font-family:'DM Mono',monospace;`;
      videoPlayerWrap?.appendChild(ind);
    }
    ind.textContent = `Zoom: ${Math.round(zoom * 100)}%`;
    ind.style.opacity = '1';
    clearTimeout(ind._t);
    ind._t = setTimeout(() => { ind.style.opacity = '0'; }, 2000);
  }

  zoomInBtn?.addEventListener('click',  () => setZoom(currentZoom + zoomStep));
  zoomOutBtn?.addEventListener('click', () => setZoom(currentZoom - zoomStep));

  videoPlayerWrap?.addEventListener('wheel', (e) => {
    if (e.ctrlKey || e.metaKey) {
      e.preventDefault();
      setZoom(currentZoom + (e.deltaY < 0 ? zoomStep : -zoomStep));
    }
  }, { passive: false });

  /* ══ MODAL COMPARTIR ═══════════════════════════════════════ */
  const shareModal2 = document.getElementById('mobileShareModal');

  function openShareModal() {
    if (!shareModal2) return;
    shareModal2.style.display = 'block';
    requestAnimationFrame(() => shareModal2.classList.add('open'));
    shareModal2.setAttribute('aria-hidden', 'false');
  }

  function closeShareModal() {
    if (!shareModal2) return;
    shareModal2.classList.remove('open');
    shareModal2.setAttribute('aria-hidden', 'true');
    setTimeout(() => { shareModal2.style.display = ''; }, 50);
  }

  const shareTriggerBtn = document.getElementById('shareMainTrigger');
  if (shareTriggerBtn) shareTriggerBtn.addEventListener('click', openShareModal);

  shareModal2?.querySelectorAll('[data-action="close"]').forEach(el => {
    el.addEventListener('click', closeShareModal);
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeShareModal();
      closeClipsModal();
    }
  });

  /* ══ ATAJOS DE TECLADO ═════════════════════════════════════ */
  document.addEventListener('keydown', (e) => {
    if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    switch (e.key) {
      case ' ':
      case 'k': e.preventDefault(); playPauseBtn?.click(); break;
      case 'ArrowLeft':  e.preventDefault(); rewindBtn?.click(); break;
      case 'ArrowRight': e.preventDefault(); forwardBtn?.click(); break;
      case 'ArrowUp':
        e.preventDefault();
        videoPlayer.volume = Math.min(1, videoPlayer.volume + 0.1);
        if (volumeSlider) volumeSlider.value = videoPlayer.volume * 100;
        updateVolumeIcon(); break;
      case 'ArrowDown':
        e.preventDefault();
        videoPlayer.volume = Math.max(0, videoPlayer.volume - 0.1);
        if (volumeSlider) volumeSlider.value = videoPlayer.volume * 100;
        updateVolumeIcon(); break;
      case 'm': e.preventDefault(); volumeBtn?.click(); break;
      case 'f': e.preventDefault(); requestFS(); break;
      case 'r': e.preventDefault(); recordBtn?.click(); break;
      case '+': case '=': e.preventDefault(); setZoom(currentZoom + zoomStep); break;
      case '-': case '_': e.preventDefault(); setZoom(currentZoom - zoomStep); break;
      case '0': e.preventDefault(); setZoom(1); break;
    }
  });

  /* ══ INIT ══════════════════════════════════════════════════ */
  updateVolumeIcon();
  updateClipsBadge();
});
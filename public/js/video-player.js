document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const videoPlayer = document.getElementById('videoPlayer');
  const videoControls = document.getElementById('videoControls');
  const videoPlayerWrap = document.getElementById('videoPlayerWrap');

  if (!videoPlayer || !videoControls) return;

  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  if (isIOS) {
    videoPlayer.setAttribute('playsinline', 'true');
    videoPlayer.setAttribute('webkit-playsinline', 'true');
    videoPlayerWrap.style.touchAction = 'manipulation';
  }

  const playPauseBtn = document.getElementById('playPauseBtn');
  const rewindBtn = document.getElementById('rewindBtn');
  const forwardBtn = document.getElementById('forwardBtn');
  const volumeBtn = document.getElementById('volumeBtn');
  const volumeSlider = document.getElementById('volumeSlider');
  const speedBtn = document.getElementById('speedBtn');
  const speedMenu = document.getElementById('speedMenu');
  const pipBtn = document.getElementById('pipBtn');
  const fullscreenBtn = document.getElementById('fullscreenBtn');
  const progressBar = document.getElementById('progressBar');
  const progressFilled = document.getElementById('progressFilled');
  const currentTimeEl = document.getElementById('currentTime');
  const durationEl = document.getElementById('duration');
  const loadingOverlay = document.getElementById('loadingOverlay');
  const zoomInBtn = document.getElementById('zoomInBtn');
  const zoomOutBtn = document.getElementById('zoomOutBtn');
  const zoomLevel = document.getElementById('zoomLevel');
  const recordBtn = document.getElementById('recordBtn');
  const clipsBtn = document.getElementById('clipsBtn');
  const clipsBadge = document.getElementById('clipsBadge');
  const recIndicator = document.getElementById('recIndicator');
  const recTimer = document.getElementById('recTimer');
  const downloadFullBtn = document.getElementById('downloadFullBtn');
  const clipsModal = document.getElementById('clipsModal');
  const clipsListEl = document.getElementById('clipsList');
  const clipsCountLabel = document.getElementById('clipsCountLabel');
  const downloadAllBtn = document.getElementById('downloadAllClipsBtn');
  const shareAllBtn = document.getElementById('shareAllClipsBtn');

  // ── Config ────────────────────────────────────────────────
  const API_CREATE_CLIP = (window.POMPLAY_BASE || '') + '/api/create-clip';
  const CLIPS_LS_KEY = 'pomplay_clips_v2';

  // ── Logs ──────────────────────────────────────────────────
  const DEBUG = false;
  function log(...a) { if (DEBUG) console.log('[CLIP]', ...a); }
  function warn(...a) { console.warn('[CLIP]', ...a); }
  function err(...a) { console.error('[CLIP]', ...a); }

  // ── Play/Pause ────────────────────────────────────────────
  function togglePlayPause() {
    if (videoPlayer.paused || videoPlayer.ended) {
      videoPlayer.play().catch(e => { warn('[play]', e); syncPlayIcon(); });
    } else {
      videoPlayer.pause();
    }
  }

  // ── Zoom ──────────────────────────────────────────────────
  let currentZoom = 1;
  const minZoom = 1, maxZoom = 3, zoomStep = 0.1;
  let panX = 0, panY = 0, isDragging = false;
  let dragStartX = 0, dragStartY = 0, panStartX = 0, panStartY = 0;

  function applyTransform() {
    if (currentZoom <= 1) { panX = 0; panY = 0; }
    videoPlayer.style.transform = `scale(${currentZoom}) translate(${panX}px, ${panY}px)`;
    videoPlayer.style.transformOrigin = 'center center';
  }

  function clampPan() {
    if (currentZoom <= 1) { panX = 0; panY = 0; return; }
    const mx = (videoPlayer.offsetWidth * (currentZoom - 1)) / (2 * currentZoom);
    const my = (videoPlayer.offsetHeight * (currentZoom - 1)) / (2 * currentZoom);
    panX = Math.max(-mx, Math.min(mx, panX));
    panY = Math.max(-my, Math.min(my, panY));
  }

  videoPlayer.addEventListener('mousedown', e => {
    if (currentZoom <= 1) return;
    isDragging = true;
    dragStartX = e.clientX; dragStartY = e.clientY;
    panStartX = panX; panStartY = panY;
    videoPlayer.style.cursor = 'grabbing';
    e.preventDefault();
  });
  document.addEventListener('mousemove', e => {
    if (!isDragging) return;
    panX = panStartX + (e.clientX - dragStartX) / currentZoom;
    panY = panStartY + (e.clientY - dragStartY) / currentZoom;
    clampPan(); applyTransform();
  });
  document.addEventListener('mouseup', e => {
    if (!isDragging) return;
    isDragging = false;
    videoPlayer.style.cursor = currentZoom > 1 ? 'grab' : '';
    if (Math.abs(e.clientX - dragStartX) < 5 && Math.abs(e.clientY - dragStartY) < 5) togglePlayPause();
  });

  let touchStartX = 0, touchStartY = 0, touchPanStartX = 0, touchPanStartY = 0, touchMoved = false;
  videoPlayer.addEventListener('touchstart', e => {
    if (currentZoom <= 1) return;
    const t = e.touches[0];
    touchStartX = t.clientX; touchStartY = t.clientY;
    touchPanStartX = panX; touchPanStartY = panY; touchMoved = false;
  }, { passive: true });
  videoPlayer.addEventListener('touchmove', e => {
    if (currentZoom <= 1) return;
    const t = e.touches[0];
    const dx = (t.clientX - touchStartX) / currentZoom;
    const dy = (t.clientY - touchStartY) / currentZoom;
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) touchMoved = true;
    panX = touchPanStartX + dx; panY = touchPanStartY + dy;
    clampPan(); applyTransform(); e.preventDefault();
  }, { passive: false });
  videoPlayer.addEventListener('touchend', e => {
    if (currentZoom <= 1 && !touchMoved) { e.preventDefault(); togglePlayPause(); }
  }, { passive: false });

  function setZoom(level) {
    const prev = currentZoom;
    currentZoom = Math.max(minZoom, Math.min(maxZoom, level));
    if (currentZoom === 1) { panX = 0; panY = 0; }
    if (prev !== currentZoom) clampPan();
    applyTransform();
    videoPlayer.style.cursor = currentZoom > 1 ? 'grab' : '';
    if (zoomLevel) zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
    let ind = document.getElementById('zoomIndicator');
    if (!ind) {
      ind = document.createElement('div'); ind.id = 'zoomIndicator';
      ind.style.cssText = 'position:absolute;top:20px;right:20px;background:rgba(0,0,0,0.8);color:#fff;padding:8px 16px;border-radius:8px;font-size:14px;font-weight:600;z-index:1000;transition:opacity 0.3s;pointer-events:none;';
      videoPlayerWrap?.appendChild(ind);
    }
    ind.textContent = `Zoom: ${Math.round(currentZoom * 100)}%`;
    ind.style.opacity = '1';
    clearTimeout(ind._t);
    ind._t = setTimeout(() => { ind.style.opacity = '0'; }, 2000);
  }

  zoomInBtn?.addEventListener('click', () => setZoom(currentZoom + zoomStep));
  zoomOutBtn?.addEventListener('click', () => setZoom(currentZoom - zoomStep));
  videoPlayerWrap?.addEventListener('wheel', e => {
    e.preventDefault();
    setZoom(currentZoom + (e.deltaY < 0 ? zoomStep : -zoomStep));
  }, { passive: false });


  function loadClipsFromStorage() {
    try {
      const raw = localStorage.getItem(CLIPS_LS_KEY);
      if (!raw) return [];
      const arr = JSON.parse(raw);
      return Array.isArray(arr) ? arr : [];
    } catch { return []; }
  }

  function saveClipsToStorage(clips) {
    try {
      localStorage.setItem(CLIPS_LS_KEY, JSON.stringify(clips));
    } catch (e) {
      warn('localStorage write error:', e);
    }
  }

  // Array en memoria de clips de la sesión actual
  let clips = loadClipsFromStorage();

  function addClip(clip) {
    clips.push(clip);
    saveClipsToStorage(clips);
    updateClipsBadge();
  }

  function removeClip(id) {
    clips = clips.filter(c => c.id !== id);
    saveClipsToStorage(clips);
    updateClipsBadge();
  }

  function updateClipsBadge() {
    if (!clipsBadge) return;
    const n = clips.length;
    clipsBadge.textContent = n > 99 ? '99+' : n;
    clipsBadge.classList.toggle('visible', n > 0);
  }

  // ── Formateo de tiempo ────────────────────────────────────
  function formatTime(s) {
    const m = Math.floor(s / 60);
    return `${m}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
  }

  function formatDuration(secs) {
    const s = Math.round(secs);
    const m = Math.floor(s / 60);
    return `${String(m).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
  }

  // ── Captura de miniatura (thumbnail) ─────────────────────
  // Captura el frame actual del <video> sin tocar blobs
  function captureCurrentFrame() {
    try {
      const vw = videoPlayer.videoWidth, vh = videoPlayer.videoHeight;
      if (!vw || !vh) return null;
      const c = document.createElement('canvas');
      // Reducida para ahorrar espacio en localStorage
      const scale = Math.min(1, 320 / vw);
      c.width = Math.round(vw * scale);
      c.height = Math.round(vh * scale);
      c.getContext('2d').drawImage(videoPlayer, 0, 0, c.width, c.height);
      return c.toDataURL('image/jpeg', 0.72);
    } catch { return null; }
  }

  // ── Sistema de Clips (nuevo — sin MediaRecorder) ──────────
  let isClipping = false;   // true entre ● y ⏹
  let clipStart = 0;       // tiempo de inicio en segundos
  let clipTimerInt = null;    // intervalo del contador en pantalla
  let clipStartWall = 0;       // Date.now() al iniciar

  function setRecordBtnState(state) {
    if (!recordBtn) return;
    recordBtn.classList.remove('recording');
    recordBtn.disabled = false;
    if (state === 'idle') {
      recordBtn.setAttribute('title', 'Grabar clip');
      recordBtn.innerHTML = '<i class="fas fa-circle"></i>';
    } else if (state === 'marking') {
      recordBtn.classList.add('recording');
      recordBtn.setAttribute('title', 'Detener clip');
      // Solo ícono stop — el timer va al recIndicator, no al botón
      recordBtn.innerHTML = '<i class="fas fa-stop"></i>';
    } else if (state === 'processing') {
      recordBtn.setAttribute('title', 'Procesando en servidor…');
      recordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      recordBtn.disabled = true;
    }
  }

  function startClip() {
    if (isClipping) return;

    const videoSrc = videoPlayer.currentSrc || videoPlayer.src;
    if (!videoSrc) { showToast('⚠ No hay video cargado'); return; }

    clipStart = videoPlayer.currentTime;
    clipStartWall = Date.now();
    isClipping = true;

    setRecordBtnState('marking');
    if (recIndicator) recIndicator.classList.add('active');

    // Solo actualizar el contador del recIndicator (no el botón)
    clipTimerInt = setInterval(() => {
      const elapsed = (Date.now() - clipStartWall) / 1000;
      if (recTimer) recTimer.textContent = formatDuration(elapsed);
    }, 500);

    log('clipStart =', clipStart);
  }

  async function stopClip() {
    if (!isClipping) return;
    isClipping = false;
    clearInterval(clipTimerInt);

    // 1. Pausar el video inmediatamente
    videoPlayer.pause();

    const clipEnd = videoPlayer.currentTime;
    const duration = clipEnd - clipStart;

    if (recIndicator) recIndicator.classList.remove('active');
    if (recTimer) recTimer.textContent = '00:00';

    if (duration < 1) {
      showToast('⚠ El clip debe durar al menos 1 segundo');
      setRecordBtnState('idle');
      return;
    }

    // 2. Capturar thumbnail del frame actual
    const thumbnail = captureCurrentFrame();

    // 3. Volver al estado idle pero mantener botón deshabilitado
    //    El modal de procesamiento es el único indicador — sin spinner en el botón
    setRecordBtnState('idle');
    if (recordBtn) recordBtn.disabled = true;
    if (clipsBtn) clipsBtn.disabled = true;
    showProcModal('processing', 'Procesando video…');

    const videoUrl = videoPlayer.currentSrc || videoPlayer.src;
    const clipData = {
      videoUrl,
      startTime: clipStart,
      endTime: clipEnd,
      duration,
      cameraId: currentCameraId,
      zoom: currentZoom,
      panX,
      panY,
    };

    log('Enviando clip:', clipData);

    // Animación sintética de progreso mientras espera al servidor
    let fakeProgress = 10;
    const fakeInterval = setInterval(() => {
      fakeProgress = Math.min(85, fakeProgress + Math.random() * 7);
      updateProcProgress(fakeProgress);
    }, 1200);

    try {
      const result = await sendClipToBackend(clipData);

      clearInterval(fakeInterval);
      updateProcProgress(100);

      const clip = {
        id: Date.now(),
        name: `Clip ${clips.length + 1}`,
        clipUrl: result.clipUrl,
        filename: result.filename || `clip_${Date.now()}.mp4`,
        startTime: clipStart,
        endTime: clipEnd,
        duration,
        cameraId: currentCameraId,
        thumbnail,
        createdAt: new Date().toISOString(),
      };

      addClip(clip);
      renderClipsList();

      // 4. Mostrar éxito → esperar 1.5s → cerrar → abrir historial
      showProcModal('success', 'Clip generado correctamente');
      await new Promise(r => setTimeout(r, 1500));
      closeProcModal();
      openClipsModal();

    } catch (error) {
      clearInterval(fakeInterval);
      err('stopClip error:', error);
      showProcModal('error', error.message || 'Sin conexión al servidor');
    } finally {
      if (clipsBtn) clipsBtn.disabled = false;
      setRecordBtnState('idle');
    }
  }

  // ── Envío al backend ──────────────────────────────────────
  async function sendClipToBackend(clipData, retries = 2) {
    for (let attempt = 0; attempt <= retries; attempt++) {
      try {
        if (attempt > 0) {
          const subEl = document.getElementById('clipProcSub');
          if (subEl) subEl.textContent = `Reintentando (${attempt}/${retries})…`;
          await new Promise(r => setTimeout(r, 1500 * attempt));
        }

        const resp = await fetch(API_CREATE_CLIP, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(clipData),
        });

        const json = await resp.json().catch(() => null);

        if (!resp.ok || !json) {
          const msg = json?.error || `Error HTTP ${resp.status}`;
          if (attempt < retries) { warn('Intento fallido:', msg); continue; }
          throw new Error(msg);
        }

        if (!json.success || !json.clipUrl) {
          const msg = json.error || 'El servidor no devolvió una URL de clip';
          if (attempt < retries) { warn('Respuesta inválida:', msg); continue; }
          throw new Error(msg);
        }

        return json;

      } catch (e) {
        if (attempt >= retries) throw e;
        warn(`Intento ${attempt + 1} fallido:`, e.message);
      }
    }
    throw new Error('No se pudo conectar al servidor tras varios intentos');
  }

  // ── Modal de procesamiento de clip ───────────────────────
  function showProcModal(state, message) {
    const modal = document.getElementById('clipProcessingModal');
    if (!modal) return;
    modal.classList.add('open');
    modal.removeAttribute('aria-hidden');

    const iconWrap = document.getElementById('clipProcIconWrap');
    const titleEl  = document.getElementById('clipProcTitle');
    const subEl    = document.getElementById('clipProcSub');

    // Limpiar botón de cierre previo
    modal.querySelector('.clip-proc-close-btn')?.remove();

    if (state === 'processing') {
      if (iconWrap) {
        iconWrap.className = 'clip-proc-icon-wrap';
        iconWrap.innerHTML = '<div class="spinner"></div>';
      }
      if (titleEl) titleEl.textContent = message || 'Procesando video…';
      if (subEl)   subEl.textContent   = 'Esto puede tomar unos segundos';
    } else if (state === 'success') {
      if (iconWrap) {
        iconWrap.className = 'clip-proc-icon-wrap success';
        iconWrap.innerHTML = '<i class="fas fa-check"></i>';
      }
      if (titleEl) titleEl.textContent = message || 'Clip generado';
      if (subEl)   subEl.textContent   = 'Abriendo historial de clips…';
    } else if (state === 'error') {
      if (iconWrap) {
        iconWrap.className = 'clip-proc-icon-wrap error';
        iconWrap.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
      }
      if (titleEl) titleEl.textContent = 'Error al procesar';
      if (subEl)   subEl.textContent   = message || 'Sin conexión al servidor';
      // Añadir botón de cierre en estado error
      const content = modal.querySelector('.clip-proc-content');
      if (content) {
        const btn = document.createElement('button');
        btn.className = 'clip-proc-close-btn';
        btn.textContent = 'Cerrar';
        btn.onclick = closeProcModal;
        content.appendChild(btn);
      }
    }
  }

  function closeProcModal() {
    const modal = document.getElementById('clipProcessingModal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    modal.querySelector('.clip-proc-close-btn')?.remove();
    updateProcProgress(0);
  }

  function updateProcProgress(percent) {
    const fill = document.getElementById('clipProcBarFill');
    if (fill) fill.style.width = Math.min(100, Math.round(percent)) + '%';
  }

  // ── Selector de cámaras ───────────────────────────────────
  let currentCameraId = null;
  const availableCameras = window.VIDEO_CAMERAS || [];

  function initCameraSelector() {
    if (!availableCameras || availableCameras.length <= 1) return;
    const html = `<div class="camera-selector" id="cameraSelector">
      ${availableCameras.map((cam, idx) => `
        <button class="cam-btn ${idx === 0 ? 'active' : ''}"
                data-camera-id="${cam.id_camara}"
                data-video-url="${cam.video_url}">
          ${cam.nombre || 'Cam ' + (idx + 1)}
        </button>`).join('')}
    </div>`;
    document.getElementById('videoPlayerWrap')?.insertAdjacentHTML('afterbegin', html);
    document.querySelectorAll('.cam-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        switchCamera(this.dataset.cameraId, this.dataset.videoUrl);
        document.querySelectorAll('.cam-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
      });
    });
    if (availableCameras[0]) currentCameraId = availableCameras[0].id_camara;
  }

  function switchCamera(cameraId, videoUrl) {
    if (!videoPlayer || currentCameraId === cameraId) return;
    if (isClipping) { showToast('⚠ Detén el clip antes de cambiar cámara'); return; }
    const wasPlaying = !videoPlayer.paused;
    const t = videoPlayer.currentTime;
    videoPlayer.src = videoUrl; videoPlayer.load(); videoPlayer.currentTime = t;
    if (wasPlaying) videoPlayer.play().catch(() => { });
    currentCameraId = cameraId;
    showToast('📹 Cámara cambiada', 'success');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCameraSelector);
  else initCameraSelector();

  // ── Compartir ─────────────────────────────────────────────
  const shareModal2 = document.getElementById('mobileShareModal');
  const openShareModal = () => {
    if (!shareModal2) return;
    shareModal2.style.display = 'block';
    requestAnimationFrame(() => { shareModal2.classList.add('open'); shareModal2.setAttribute('aria-hidden', 'false'); });
  };
  const closeShareModal = () => {
    if (!shareModal2) return;
    shareModal2.classList.remove('open'); shareModal2.setAttribute('aria-hidden', 'true');
    setTimeout(() => { shareModal2.style.display = ''; }, 300);
  };
  document.getElementById('shareMainTrigger')?.addEventListener('click', openShareModal);
  shareModal2?.querySelectorAll('[data-action="close"]').forEach(el => el.addEventListener('click', closeShareModal));

  // ── Descargar todos los clips (secuencial con progreso visual) ─
  downloadAllBtn?.addEventListener('click', async function () {
    const allClips = [...clips]; // copia inmutable
    if (!allClips.length) { showToast('⚠ No hay clips para descargar'); return; }

    this.disabled = true;
    const origHtml = this.innerHTML;

    for (let i = 0; i < allClips.length; i++) {
      const clip = allClips[i];

      // Indicador de progreso en el botón
      this.innerHTML = `<i class="fas fa-spinner fa-spin"></i>&nbsp;${i + 1}/${allClips.length}`;

      // Resaltar el clip que se está descargando
      clipsListEl?.querySelectorAll('.clip-item').forEach(el => el.classList.remove('downloading'));
      clipsListEl?.querySelector(`.clip-item[data-id="${clip.id}"]`)?.classList.add('downloading');

      await downloadClip(clip);
      await new Promise(r => setTimeout(r, 800)); // pausa entre descargas
    }

    // Limpiar highlights
    clipsListEl?.querySelectorAll('.clip-item').forEach(el => el.classList.remove('downloading'));
    this.innerHTML = origHtml;
    this.disabled = false;
    showToast(`✓ ${allClips.length} clip(s) descargados`, 'success');
  });

  // ── Compartir todos los clips ─────────────────────────────
  shareAllBtn?.addEventListener('click', function () {
    const allClips = clips;
    if (!allClips.length) { showToast('⚠ No hay clips para compartir'); return; }
    // Compartir el primero o abrir una lista
    if (allClips.length === 1) {
      shareClip(allClips[0]);
    } else {
      const urls = allClips.map((c, i) => `Clip ${i + 1}: ${c.clipUrl}`).join('\n');
      if (navigator.share) {
        navigator.share({ title: 'Clips PomPlay', text: urls }).catch(() => { });
      } else {
        navigator.clipboard?.writeText(urls);
        showToast('✓ URLs copiadas al portapapeles', 'success');
      }
    }
  });

  // ── Descargar video completo ──────────────────────────────
  downloadFullBtn?.addEventListener('click', async function () {
    const videoSrc = videoPlayer.currentSrc || videoPlayer.src;
    if (!videoSrc) { showToast('No hay video disponible'); return; }

    this.disabled = true;
    const orig = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
      const a = document.createElement('a');
      a.href = videoSrc;
      a.download = 'pomplay_video_' + Date.now() + '.mp4';
      a.target = '_blank';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      showToast('Descargando video…');
    } catch (e) {
      err('[downloadFull]', e);
      showToast('⚠ No se pudo descargar el video');
    } finally {
      this.disabled = false; this.innerHTML = orig;
    }
  });

  // ── Descarga individual de clip ───────────────────────────
  // Usa el proxy /api/download-clip para forzar descarga real:
  // el atributo `download` del <a> es ignorado para URLs cross-origin.
  function downloadClip(clip) {
    if (!clip.clipUrl) { showToast('⚠ Este clip no tiene URL de descarga'); return Promise.resolve(); }

    const filename = clip.filename || (clip.name.replace(/\s+/g, '_') + '.mp4');
    const proxyUrl = (window.POMPLAY_BASE || '')
      + '/api/download-clip?url=' + encodeURIComponent(clip.clipUrl)
      + '&filename=' + encodeURIComponent(filename);

    return new Promise(resolve => {
      const a = document.createElement('a');
      a.href = proxyUrl;
      a.download = filename;   // ahora sí funciona (mismo origen)
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      setTimeout(resolve, 600);
    });
  }

  // ── Compartir clip individual ─────────────────────────────
  function shareClip(clip) {
    if (!clip.clipUrl) { showToast('⚠ El clip no tiene URL'); return; }
    if (navigator.share) {
      navigator.share({
        title: clip.name || 'Clip PomPlay',
        text: `Mira este clip de CCTV: ${clip.name}`,
        url: clip.clipUrl,
      }).catch(e => {
        if (e.name !== 'AbortError') {
          navigator.clipboard?.writeText(clip.clipUrl);
          showToast('✓ URL copiada al portapapeles', 'success');
        }
      });
    } else {
      navigator.clipboard?.writeText(clip.clipUrl).then(() => {
        showToast('✓ URL del clip copiada', 'success');
      }).catch(() => {
        // Fallback: abrir en nueva pestaña
        window.open(clip.clipUrl, '_blank');
      });
    }
  }

  // ── Modal de clips ────────────────────────────────────────
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

  function renderClipsList() {
    if (!clipsListEl) return;
    if (clipsCountLabel) clipsCountLabel.textContent = clips.length;
    if (!clips.length) {
      clipsListEl.innerHTML = `<div class="clips-empty"><i class="fas fa-film"></i>No hay clips todavía.<br><small>Pulsa ● para marcar inicio y ⏹ para enviar al servidor.</small></div>`;
      return;
    }

    clipsListEl.innerHTML = clips.map(clip => {
      const dur = clip.duration != null ? formatDuration(clip.duration) : '--:--';
      const date = clip.createdAt ? new Date(clip.createdAt).toLocaleDateString() : '';
      const thumbContent = clip.thumbnail
        ? `<img src="${clip.thumbnail}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" alt="" loading="lazy">`
        : `<i class="fas fa-film" style="color:rgba(255,255,255,0.25);font-size:22px;"></i>`;

      return `
      <div class="clip-item" data-id="${clip.id}">
        <div class="clip-thumb" style="display:flex;align-items:center;justify-content:center;background:#12121e;overflow:hidden;">
          ${thumbContent}
        </div>
        <div class="clip-info">
          <div class="clip-name">${escapeHtml(clip.name)}</div>
          <div class="clip-meta">${dur}${date ? ' · ' + date : ''}</div>
          <div class="clip-url-preview">${clip.clipUrl ? '🔗 ' + clip.clipUrl.split('/').pop() : '⚠ Sin URL'}</div>
        </div>
        <div class="clip-actions">
          <button class="clip-action-btn" data-action="download" data-id="${clip.id}" title="Descargar MP4">
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
  }

  if (clipsListEl) {
    clipsListEl.addEventListener('click', e => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const id = parseInt(btn.dataset.id);
      const clip = clips.find(c => c.id === id);
      if (!clip) return;

      if (btn.dataset.action === 'download') {
        downloadClip(clip);
      } else if (btn.dataset.action === 'share') {
        shareClip(clip);
      } else if (btn.dataset.action === 'delete') {
        removeClip(id);
        renderClipsList();
        showToast('Clip eliminado');
      }
    });
  }

  clipsBtn?.addEventListener('click', openClipsModal);
  clipsModal?.querySelector('.clips-modal-backdrop')?.addEventListener('click', closeClipsModal);
  clipsModal?.querySelector('.clips-modal-close')?.addEventListener('click', closeClipsModal);

  // ── Botón Grabar / Detener ────────────────────────────────
  recordBtn?.addEventListener('click', () => {
    if (isClipping) stopClip();
    else startClip();
  });

  // ── Utilidades ────────────────────────────────────────────
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
    toast.style.borderColor = ''; toast.style.color = '';
    if (type === 'success' || msg.startsWith('✓')) {
      toast.style.borderColor = 'rgba(46,213,115,0.5)';
      toast.style.color = '#2ed573';
    } else if (type === 'error' || msg.startsWith('⚠')) {
      toast.style.borderColor = 'rgba(231,76,60,0.5)';
      toast.style.color = '#ff6b5b';
    }
    toast.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(() => toast.classList.remove('show'), type === 'success' ? 3500 : 4000);
  }

  // ── Play/Pause sync ───────────────────────────────────────
  function syncPlayIcon() {
    if (!playPauseBtn) return;
    playPauseBtn.innerHTML = videoPlayer.paused ? '<i class="fas fa-play"></i>' : '<i class="fas fa-pause"></i>';
  }
  videoPlayer.addEventListener('play', syncPlayIcon);
  videoPlayer.addEventListener('pause', syncPlayIcon);
  videoPlayer.addEventListener('ended', syncPlayIcon);
  playPauseBtn?.addEventListener('click', e => { e.stopPropagation(); togglePlayPause(); });
  videoPlayer.addEventListener('click', e => { if (currentZoom > 1) return; e.stopPropagation(); togglePlayPause(); });

  rewindBtn?.addEventListener('click', () => { videoPlayer.currentTime = Math.max(0, videoPlayer.currentTime - 10); });
  forwardBtn?.addEventListener('click', () => { videoPlayer.currentTime = Math.min(videoPlayer.duration || 0, videoPlayer.currentTime + 10); });

  // ── Volumen ───────────────────────────────────────────────
  function updateVolumeIcon() {
    if (!volumeBtn) return;
    const v = videoPlayer.volume;
    volumeBtn.innerHTML = `<i class="fas ${v === 0 || videoPlayer.muted ? 'fa-volume-mute' : v < 0.5 ? 'fa-volume-down' : 'fa-volume-up'}"></i>`;
  }
  volumeBtn?.addEventListener('click', () => {
    videoPlayer.muted = !videoPlayer.muted;
    if (!videoPlayer.muted && volumeSlider) volumeSlider.value = videoPlayer.volume * 100;
    updateVolumeIcon();
  });
  volumeSlider?.addEventListener('input', function () {
    videoPlayer.volume = this.value / 100;
    videoPlayer.muted = false;
    updateVolumeIcon();
  });

  // ── Velocidad ─────────────────────────────────────────────
  speedBtn?.addEventListener('click', e => { e.stopPropagation(); speedMenu?.classList.toggle('open'); });
  document.addEventListener('click', () => speedMenu?.classList.remove('open'));
  speedMenu?.addEventListener('click', e => {
    if (e.target.tagName !== 'BUTTON') return;
    const speed = parseFloat(e.target.dataset.speed);
    videoPlayer.playbackRate = speed;
    const st = speedBtn?.querySelector('.speed-text');
    if (st) st.textContent = speed + 'x';
    speedMenu.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
  });

  // ── PiP ───────────────────────────────────────────────────
  if (document.pictureInPictureEnabled && pipBtn) {
    pipBtn.addEventListener('click', async () => {
      try {
        if (document.pictureInPictureElement) await document.exitPictureInPicture();
        else await videoPlayer.requestPictureInPicture();
      } catch { }
    });
  } else if (pipBtn) pipBtn.style.display = 'none';

  // ── Fullscreen ────────────────────────────────────────────
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
    if (fullscreenBtn) fullscreenBtn.innerHTML = document.fullscreenElement ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>';
  });

  // ── Progreso ──────────────────────────────────────────────
  videoPlayer.addEventListener('timeupdate', () => {
    if (!videoPlayer.duration) return;
    const pct = (videoPlayer.currentTime / videoPlayer.duration) * 100;
    if (progressFilled) progressFilled.style.width = pct + '%';
    if (currentTimeEl) currentTimeEl.textContent = formatTime(videoPlayer.currentTime);
  });
  videoPlayer.addEventListener('loadedmetadata', () => {
    if (durationEl) durationEl.textContent = formatTime(videoPlayer.duration);
  });
  progressBar?.addEventListener('click', e => {
    const rect = progressBar.getBoundingClientRect();
    videoPlayer.currentTime = ((e.clientX - rect.left) / rect.width) * videoPlayer.duration;
  });

  // ── Auto-fade controles ───────────────────────────────────
  const wrap = videoPlayerWrap;
  if (wrap && window.matchMedia('(hover: none)').matches) wrap.classList.add('controls-visible');
  if (wrap && window.matchMedia('(hover: hover)').matches) {
    let fadeTimer;
    const showCtrls = () => {
      wrap.classList.add('controls-visible'); clearTimeout(fadeTimer);
      fadeTimer = setTimeout(() => { if (!videoPlayer.paused) wrap.classList.remove('controls-visible'); }, 3000);
    };
    wrap.addEventListener('mousemove', showCtrls); wrap.addEventListener('mouseenter', showCtrls);
    videoPlayer.addEventListener('play', () => { fadeTimer = setTimeout(() => wrap.classList.remove('controls-visible'), 3000); });
    videoPlayer.addEventListener('pause', () => wrap.classList.add('controls-visible'));
  }

  // ── Loading overlay ───────────────────────────────────────
  function hideLoader() {
    if (!loadingOverlay) return;
    loadingOverlay.classList.remove('show'); loadingOverlay.style.pointerEvents = 'none';
    setTimeout(() => { if (!loadingOverlay.classList.contains('show')) loadingOverlay.style.display = 'none'; }, 350);
  }
  function showLoader() {
    if (!loadingOverlay) return;
    loadingOverlay.style.display = 'flex'; void loadingOverlay.offsetHeight; loadingOverlay.classList.add('show');
  }
  ['canplay', 'canplaythrough', 'loadeddata', 'loadedmetadata', 'playing'].forEach(ev => videoPlayer.addEventListener(ev, hideLoader));
  videoPlayer.addEventListener('waiting', showLoader);
  videoPlayer.addEventListener('stalled', showLoader);
  videoPlayer.addEventListener('error', () => { hideLoader(); showToast('Error al cargar el video'); });
  const _lt = setTimeout(hideLoader, 5000);
  videoPlayer.addEventListener('canplay', () => clearTimeout(_lt), { once: true });
  if (videoPlayer.readyState >= 2) hideLoader();

  // ── Atajos de teclado ─────────────────────────────────────
  document.addEventListener('keydown', e => {
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
    switch (e.key) {
      case ' ': case 'k': e.preventDefault(); togglePlayPause(); break;
      case 'ArrowLeft': e.preventDefault(); rewindBtn?.click(); break;
      case 'ArrowRight': e.preventDefault(); forwardBtn?.click(); break;
      case 'ArrowUp': e.preventDefault(); videoPlayer.volume = Math.min(1, videoPlayer.volume + 0.1); if (volumeSlider) volumeSlider.value = videoPlayer.volume * 100; updateVolumeIcon(); break;
      case 'ArrowDown': e.preventDefault(); videoPlayer.volume = Math.max(0, videoPlayer.volume - 0.1); if (volumeSlider) volumeSlider.value = videoPlayer.volume * 100; updateVolumeIcon(); break;
      case 'm': e.preventDefault(); volumeBtn?.click(); break;
      case 'f': e.preventDefault(); requestFS(); break;
      case 'r': e.preventDefault(); if (!recordBtn?.disabled) recordBtn?.click(); break;
      case '+': case '=': e.preventDefault(); setZoom(currentZoom + zoomStep); break;
      case '-': case '_': e.preventDefault(); setZoom(currentZoom - zoomStep); break;
      case '0': e.preventDefault(); setZoom(1); break;
    }
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeShareModal(); closeClipsModal(); }
  });

  // ── Exponer renderClipsList globalmente ──────────────────
  window.renderClipsList = renderClipsList;

  // ── Init ──────────────────────────────────────────────────
  updateVolumeIcon();
  updateClipsBadge();
});
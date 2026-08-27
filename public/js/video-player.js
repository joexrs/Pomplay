document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const videoPlayer = document.getElementById('videoPlayer');
  const videoControls = document.getElementById('videoControls');
  const videoPlayerWrap = document.getElementById('videoPlayerWrap');

  if (!videoPlayer || !videoControls) return;

  // ── Detección de iOS ──────────────────────────────────────
  // Desde iOS 13, iPadOS reporta su userAgent como si fuera un Mac
  // de escritorio (para recibir la versión "desktop" de las webs).
  // Por eso el chequeo clásico /iPad|iPhone|iPod/ ya NO detecta
  // iPads modernos. Se agrega un segundo chequeo: MacIntel + touch
  // (un Mac real de escritorio nunca tiene maxTouchPoints > 1).
  const isIOS = (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  if (isIOS) {
    videoPlayer.setAttribute('playsinline', 'true');
    videoPlayer.setAttribute('webkit-playsinline', 'true');
    videoPlayerWrap.style.touchAction = 'manipulation';
  }

  // FIX: silenciar el video como propiedad JS desde el inicio (no sólo
  // justo antes del play()). Safari valida "¿este video es elegible
  // para autoplay?" en base al estado muted+playsinline lo antes
  // posible; hacerlo aquí, junto con el atributo `muted` ya presente
  // en el HTML, maximiza que el primer play() automático sea aceptado
  // en vez de bloqueado.
  videoPlayer.muted = true;

  // FIX iOS: Safari nunca permite audio con autoplay sin gesto del
  // usuario — es una restricción del sistema, no algo que se pueda
  // evitar con código. `iosAudioUnlocked` trackea si ya ocurrió ese
  // primer gesto. unlockIOSAudio() se llama desde cualquier primera
  // interacción (toque en el video, en la página, en un botón del UI)
  // y SOLO activa el audio — no toca play/pause, eso lo maneja cada
  // listener por su cuenta (ver el click del video más abajo, que
  // usa esta bandera para saltarse el toggle de pausa en el primer tap).
  let iosAudioUnlocked = false;
  const unlockIOSAudio = () => {
    if (iosAudioUnlocked) return;
    iosAudioUnlocked = true;
    videoPlayer.muted = false;
    if (videoPlayer.paused) videoPlayer.play().catch(() => {});
  };
  if (isIOS) {
    // Nota: solo escuchamos 'click', no 'touchstart'. 'touchstart' se
    // dispara ANTES que 'click', así que si lo usáramos aquí, el audio
    // quedaría desbloqueado una fracción de segundo antes de que el
    // click handler del video decida si pausar o no — y volveríamos al
    // mismo bug (pausa antes de que se alcance a escuchar algo). Con
    // solo 'click', el propio handler del video (más abajo) intercepta
    // su click y decide correctamente si este es el primer toque.
    document.addEventListener('click', unlockIOSAudio, { once: true });
  }

  // ── Poster Overlay (TODOS los dispositivos) ────────────────
  // Muestra thumbnail (o placeholder animado) mientras el video no
  // tiene un primer frame disponible. Evita la pantalla negra en
  // iOS, Android y cualquier conexión lenta.
  //
  // Reglas:
  //  · Siempre visible al cargar la página.
  //  · Se oculta cuando: canplay / loadeddata / playing / error.
  //  · En iOS Safari (sin autoplay): espera el tap del usuario.
  //  · En Android/Desktop: intenta autoplay silencioso; si falla,
  //    muestra el botón play sobre el placeholder.
  //  · Al cambiar cámara: vuelve a mostrarse.
  const iosPosterOverlay = document.getElementById('iosPosterOverlay');
  const iosPosterPlayBtn = document.getElementById('iosPosterPlayBtn');
  const posterPlaceholder = document.getElementById('posterPlaceholder');

  function hideIOSPoster() {
    if (!iosPosterOverlay) return;
    iosPosterOverlay.classList.add('hidden');
  }

  function showIOSPoster() {
    if (!iosPosterOverlay) return;
    iosPosterOverlay.classList.remove('hidden');
  }

  if (iosPosterOverlay) {
    // Siempre re-mostrar el overlay cuando empieza a cargar un nuevo src
    videoPlayer.addEventListener('loadstart', showIOSPoster);

    // Ocultar cuando el video realmente tiene datos o está reproduciendo
    ['canplay', 'canplaythrough', 'loadeddata', 'playing'].forEach(ev =>
      videoPlayer.addEventListener(ev, hideIOSPoster)
    );

    // Ante error, quitar el overlay para que el usuario vea el mensaje
    videoPlayer.addEventListener('error', () => {
      hideIOSPoster();
    });

    // FIX: antes se esperaba a `readyState >= 3` (evento 'canplay', que
    // exige buffer "de sobra": HAVE_FUTURE_DATA) antes de siquiera llamar
    // a play(). Eso agrega latencia innecesaria: play() es seguro de
    // llamar en cualquier momento — el navegador lo encola y lo resuelve
    // en cuanto haya datos mínimos. Ahora se intenta de inmediato al
    // cargar, y además se reintenta en los primeros eventos disponibles
    // (loadedmetadata / loadeddata / canplay) por si el intento inicial
    // fue rechazado por no haber datos todavía. Llamar play() varias
    // veces es inofensivo: si ya está reproduciendo, es un no-op.
    {
      if (videoPlayer.readyState >= 2) {
        hideIOSPoster();
      }

      let autoplaySucceeded = false;
      const tryAutoplay = () => {
        if (autoplaySucceeded) return;
        videoPlayer.muted = true;
        videoPlayer.play().then(() => {
          autoplaySucceeded = true;
          // Autoplay exitoso: el evento 'playing' ocultará el overlay.
          // En iOS NO restauramos el audio automáticamente: un unmute
          // programático fuera del gesto de autoplay puede ser bloqueado
          // o, peor, sorprender al usuario con sonido inesperado.
          if (!videoPlayer._userSetVolume && !isIOS) {
            setTimeout(() => { videoPlayer.muted = false; }, 100);
          }
        }).catch(() => {
          // Todavía no se pudo — se reintentará en el próximo evento
          // (loadedmetadata → loadeddata → canplay). Sólo si TODOS
          // fallan se muestra el botón play como último recurso.
        });
      };

      // Intento inmediato: el atributo `autoplay` del HTML ya está
      // intentando reproducir por su cuenta; este es un refuerzo por si
      // el navegador lo ignoró (p. ej. cambiaste el src por JS).
      tryAutoplay();

      // Reintentos en los eventos más tempranos posibles — no esperamos
      // a 'canplaythrough' (exige demasiado buffer) ni sólo a 'canplay'.
      ['loadedmetadata', 'loadeddata', 'canplay'].forEach(ev =>
        videoPlayer.addEventListener(ev, tryAutoplay)
      );

      // Fallback de seguridad: si tras 5s el autoplay no arrancó,
      // recién ahí mostramos el botón play para que el usuario lo inicie.
      setTimeout(() => {
        if (!autoplaySucceeded && iosPosterOverlay && !iosPosterOverlay.classList.contains('hidden')) {
          if (iosPosterPlayBtn) iosPosterPlayBtn.style.display = '';
        }
      }, 5000);
    }
    const startVideoFromPoster = (e) => {
      e.preventDefault();
      e.stopPropagation();
      videoPlayer.muted = false;
      videoPlayer.play().then(() => {
        hideIOSPoster();
      }).catch(() => {
        // Si play() falla, quitar overlay para que el usuario use controles
        hideIOSPoster();
      });
    };

    iosPosterPlayBtn?.addEventListener('click', startVideoFromPoster);
    iosPosterOverlay.addEventListener('click', startVideoFromPoster);
  }

  const playPauseBtn = document.getElementById('playPauseBtn');
  const rewindBtn = document.getElementById('rewindBtn');
  const forwardBtn = document.getElementById('forwardBtn');
  const volumeBtn = document.getElementById('volumeBtn');
  const volumeSlider = document.getElementById('volumeSlider');
  const railVolumeBtn = document.getElementById('railVolumeBtn');
  const speedBtn = document.getElementById('speedBtn');
  const speedMenu = document.getElementById('speedMenu');
  const pipBtn = document.getElementById('pipBtn');
  const fullscreenBtn = document.getElementById('fullscreenBtn');
  const progressBar    = document.getElementById('Barprogress');
  const progressFilled = document.getElementById('Barfilled');
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

  // ── Zoom & Pan ────────────────────────────────────────────
  // Reglas:
  //  · zoom = 1  → sin pan, tap = play/pause
  //  · zoom > 1  → pan libre DENTRO de los límites del contenedor
  let currentZoom = 1;
  const minZoom = 1, maxZoom = 3, zoomStep = 0.1;
  let panX = 0, panY = 0, isDragging = false;
  let dragStartX = 0, dragStartY = 0, panStartX = 0, panStartY = 0;

  function applyTransform() {
    if (currentZoom <= 1) { panX = 0; panY = 0; }
    videoPlayer.style.transform = `scale(${currentZoom}) translate(${panX}px, ${panY}px)`;
    videoPlayer.style.transformOrigin = 'center center';
  }

  // Clamp exacto: los bordes del video nunca salen del contenedor
  function clampPan() {
    if (currentZoom <= 1) { panX = 0; panY = 0; return; }
    // El translate actúa en coordenadas pre-escala, por eso se divide por zoom
    const mx = (videoPlayer.offsetWidth  * (currentZoom - 1)) / (2 * currentZoom);
    const my = (videoPlayer.offsetHeight * (currentZoom - 1)) / (2 * currentZoom);
    panX = Math.max(-mx, Math.min(mx, panX));
    panY = Math.max(-my, Math.min(my, panY));
  }

  // ── Detección de dispositivo ──────────────────────────────
  const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);

  // ── DESKTOP: drag con mouse ───────────────────────────────
  if (!isTouchDevice) {
    videoPlayer.addEventListener('mousedown', e => {
      if (currentZoom <= 1) return;         // sin zoom → no arrastra
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
  }

  // ── MOBILE: gestos táctiles ───────────────────────────────
  // · 1 dedo  → pan (solo si zoom > 1)  /  tap → play/pause
  // · 2 dedos → pinch para zoom
  if (isTouchDevice) {
    videoPlayer.style.touchAction = 'none'; // capturamos todos los gestos nosotros

    let touchStartX = 0, touchStartY = 0;
    let touchPanStartX = 0, touchPanStartY = 0;
    let touchMoved = false;
    let isPinching = false;
    let pinchStartDist = 0, pinchStartZoom = 1;

    // Distancia entre dos puntos táctiles
    function getTouchDist(e) {
      const a = e.touches[0], b = e.touches[1];
      return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
    }

    videoPlayer.addEventListener('touchstart', e => {
      if (e.touches.length === 2) {
        // Inicio de pinch
        isPinching = true;
        touchMoved = true; // evita toggle play/pause al soltar
        pinchStartDist = getTouchDist(e);
        pinchStartZoom = currentZoom;
        e.preventDefault();
      } else if (e.touches.length === 1) {
        // Inicio de pan o tap
        isPinching = false;
        const t = e.touches[0];
        touchStartX = t.clientX; touchStartY = t.clientY;
        touchPanStartX = panX; touchPanStartY = panY;
        touchMoved = false;
      }
    }, { passive: false });

    videoPlayer.addEventListener('touchmove', e => {
      e.preventDefault(); // siempre: evita scroll/zoom nativo del browser

      if (e.touches.length === 2 && isPinching) {
        // ── Pinch zoom ──
        const newDist = getTouchDist(e);
        const scale  = newDist / pinchStartDist;
        setZoom(pinchStartZoom * scale);

      } else if (e.touches.length === 1 && !isPinching) {
        // ── Pan 1 dedo (solo con zoom activo) ──
        if (currentZoom <= 1) return;
        const t = e.touches[0];
        const dx = (t.clientX - touchStartX) / currentZoom;
        const dy = (t.clientY - touchStartY) / currentZoom;
        if (Math.abs(dx) > 4 || Math.abs(dy) > 4) {
          touchMoved = true;
          panX = touchPanStartX + dx;
          panY = touchPanStartY + dy;
          clampPan(); applyTransform();
        }
      }
    }, { passive: false });

    videoPlayer.addEventListener('touchend', e => {
      if (e.touches.length < 2) isPinching = false;
      if (e.touches.length === 0 && !touchMoved) {
        e.preventDefault();
        togglePlayPause();
        // NOTA: requestMobileLandscape() se removió de aquí.
        // Antes se ejecutaba en CUALQUIER tap (incluido play/pause),
        // forzando rotación a landscape sin que el usuario lo pidiera.
        // Ahora solo se dispara desde el botón de fullscreen (ver requestFS()).
      }
    }, { passive: false });
  }


  function setZoom(level) {
    const prev = currentZoom;
    currentZoom = Math.max(minZoom, Math.min(maxZoom, level));
    // Al cambiar zoom se reclampea sin perder el encuadre del usuario
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
    const videoMeta = window.POMPLAY_VIDEO || {};
    const clipData = {
      videoUrl,
      startTime: clipStart,
      endTime: clipEnd,
      duration,
      cameraId: currentCameraId,
      zoom: currentZoom,
      panX,
      panY,
      codigoVideo: videoMeta.codigo || null,
      idLocal: videoMeta.id_local || null,
      codigoCancha: videoMeta.codigo_cancha || null,
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
    // Guarda: evitar doble inserción si ya existe el selector en el DOM
    if (document.getElementById('cameraSelector')) return;

    if (!Array.isArray(availableCameras) || availableCameras.length < 1) return;

    // Normaliza los nombres de propiedad (el backend puede usar distintos campos)
    const cams = availableCameras.map((cam, idx) => ({
      id: String(cam.id_camara ?? cam.id ?? cam.camera_id ?? idx),
      url: cam.video_url ?? cam.url ?? cam.videoUrl ?? '',
      nombre: cam.nombre ?? cam.name ?? cam.label ?? ('Cam ' + (idx + 1)),
    }));

    // FIX: Siempre inicializar el ID de la cámara actual desde la primera
    // cámara disponible, aunque haya solo 1. Esto es necesario para que
    // el guard en switchCamera funcione correctamente (currentCameraId !== null).
    if (cams[0]) currentCameraId = cams[0].id;

    // Solo mostrar el selector visual si hay más de una cámara
    if (cams.length <= 1) return;

    const wrap = document.getElementById('videoPlayerWrap');
    if (!wrap) return;

    const html = `<div class="camera-selector" id="cameraSelector">
      ${cams.map((cam, idx) => `
        <button class="cam-btn ${idx === 0 ? 'active' : ''}"
                data-camera-id="${cam.id}"
                data-video-url="${cam.url}">
          ${cam.nombre}
        </button>`).join('')}
    </div>`;

    wrap.insertAdjacentHTML('afterbegin', html);

    document.querySelectorAll('#cameraSelector .cam-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        switchCamera(this.dataset.cameraId, this.dataset.videoUrl);
        document.querySelectorAll('#cameraSelector .cam-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
      });
    });
  }

  function switchCamera(cameraId, videoUrl) {
    // Los valores de dataset siempre son strings — comparar como strings
    // FIX: también se verifica que currentCameraId no sea null (estado inicial)
    if (!videoPlayer || (currentCameraId !== null && currentCameraId === String(cameraId))) return;
    if (isClipping) { showToast('⚠ Detén el clip antes de cambiar cámara'); return; }
    if (!videoUrl) { showToast('⚠ Esta cámara no tiene URL de video'); return; }

    const wasPlaying = !videoPlayer.paused;
    const t = videoPlayer.currentTime;

    // En iOS / Android, mostrar el overlay antes de cambiar el src para evitar
    // pantalla negra mientras carga el nuevo video.
    if (iosPosterOverlay) showIOSPoster();

    currentCameraId = String(cameraId);

    videoPlayer.src = videoUrl;
    videoPlayer.load();

    // FIX MÓVIL: en móvil el video no tiene metadatos hasta que carga;
    // setear currentTime antes de loadedmetadata causa que se ignore o
    // falle. Esperar el evento antes de hacer seek.
    if (t > 0) {
      const onMeta = () => {
        videoPlayer.removeEventListener('loadedmetadata', onMeta);
        if (t < (videoPlayer.duration || Infinity)) videoPlayer.currentTime = t;
        if (wasPlaying) videoPlayer.play().catch(() => {});
      };
      videoPlayer.addEventListener('loadedmetadata', onMeta);
    } else {
      if (wasPlaying) videoPlayer.play().catch(() => {});
    }

    showToast('Cámara cambiada', 'success');
  }

  // Inicializar una sola vez — el script ya corre después de DOMContentLoaded
  // porque video-player.js está al final del body.
  initCameraSelector();

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
  videoPlayer.addEventListener('click', e => {
    if (currentZoom > 1) return;
    e.stopPropagation();
    // FIX iOS: si este es el primer toque y todavía no se desbloqueó
    // el audio, lo usamos SOLO para eso — no togglear play/pause. Así
    // el video sigue reproduciendo (no se pausa) y el usuario ya
    // escucha sonido desde este mismo tap, en vez de necesitar un
    // segundo toque para "recién" oírlo.
    if (isIOS && !iosAudioUnlocked) {
      unlockIOSAudio();
      return;
    }
    togglePlayPause();
  });

  rewindBtn?.addEventListener('click', () => { videoPlayer.currentTime = Math.max(0, videoPlayer.currentTime - 10); });
  forwardBtn?.addEventListener('click', () => { videoPlayer.currentTime = Math.min(videoPlayer.duration || 0, videoPlayer.currentTime + 10); });

  // ── Volumen ───────────────────────────────────────────────
  function updateVolumeIcon() {
    const v = videoPlayer.volume;
    const iconClass = v === 0 || videoPlayer.muted ? 'fa-volume-mute' : v < 0.5 ? 'fa-volume-down' : 'fa-volume-up';
    if (volumeBtn) volumeBtn.innerHTML = `<i class="fas ${iconClass}"></i>`;
    if (railVolumeBtn) railVolumeBtn.innerHTML = `<i class="fas ${iconClass}"></i>`;
  }
  function toggleMute() {
    videoPlayer.muted = !videoPlayer.muted;
    if (!videoPlayer.muted && volumeSlider) volumeSlider.value = videoPlayer.volume * 100;
    // Este botón es un gesto explícito del usuario para controlar el
    // sonido — cuenta como el "primer toque" válido en iOS, así el
    // resto de la lógica de autoplay/desmute no vuelve a tocar el
    // estado de mute por su cuenta después de esto.
    if (isIOS) iosAudioUnlocked = true;
    if (!videoPlayer.muted && videoPlayer.paused) videoPlayer.play().catch(() => {});
    updateVolumeIcon();
  }
  volumeBtn?.addEventListener('click', toggleMute);
  railVolumeBtn?.addEventListener('click', toggleMute);
  volumeSlider?.addEventListener('input', function () {
    videoPlayer.volume = this.value / 100;
    videoPlayer.muted = false;
    if (isIOS) iosAudioUnlocked = true;
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
  // PROBLEMA iOS: .detail-content tiene position:fixed + overflow:hidden + z-index:9000.
  // Un hijo con position:fixed queda atrapado dentro de ese stacking context en Safari.
  // SOLUCIÓN ROBUSTA: pseudo-fullscreen vía clases CSS (sin re-parenting en el DOM,
  // porque mover el <video> en Safari rompe su render pipeline).
  //
  // ROTACIÓN: screen.orientation.lock('landscape') NO está soportado en
  // Safari/WebKit — nunca lo estuvo, y no hay forma de forzar la rotación
  // física del dispositivo desde JS en iOS. La única alternativa real es
  // SIMULAR landscape rotando el wrapper con CSS (transform: rotate(90deg))
  // cuando el teléfono sigue físicamente en portrait. Ver .ios-force-rotate
  // en video-player.css / video-detail-responsive.css.

  // ── Reubicación de modales durante fullscreen nativo (Android/Desktop) ──
  // #mobileShareModal, #clipsModal y #clipProcessingModal viven fuera de
  // videoPlayerWrap en el DOM (son hermanos de .detail-content). La
  // Fullscreen API nativa solo pinta al elemento que entra en fullscreen
  // y sus descendientes ("top layer"): todo lo que quede fuera de ese
  // árbol, aunque tenga z-index altísimo, simplemente no se renderiza.
  // Por eso los modales no aparecían en Android. Solución: moverlos
  // dentro de videoPlayerWrap SOLO mientras dure el fullscreen nativo,
  // y devolverlos a su lugar original al salir.
  // (videoPlayerWrap no tiene transform en fullscreen nativo — a diferencia
  // del pseudo-FS de iOS — así que position:fixed en los modales sigue
  // funcionando igual respecto al viewport una vez reubicados.)
  const RELOCATE_MODAL_IDS = ['mobileShareModal', 'clipsModal', 'clipProcessingModal'];
  let relocatedModals = [];

  function relocateModalsIntoFullscreen() {
    if (relocatedModals.length) return; // ya reubicados
    relocatedModals = RELOCATE_MODAL_IDS.map(id => {
      const el = document.getElementById(id);
      if (!el) return null;
      const info = { el, parent: el.parentNode, next: el.nextSibling };
      videoPlayerWrap.appendChild(el);
      return info;
    }).filter(Boolean);
  }

  function restoreModalsFromFullscreen() {
    if (!relocatedModals.length) return;
    relocatedModals.forEach(({ el, parent, next }) => {
      if (next && next.parentNode === parent) parent.insertBefore(el, next);
      else parent.appendChild(el);
    });
    relocatedModals = [];
  }

  let iosPseudoFS = false;

  function isInFullscreen() {
    return iosPseudoFS || !!(document.fullscreenElement || document.webkitFullscreenElement);
  }

  function isPhysicalLandscape() {
    return window.innerWidth > window.innerHeight;
  }

  function enterIOSPseudoFS() {
    if (iosPseudoFS) return;
    iosPseudoFS = true;

    videoPlayerWrap.classList.add('fullscreen-pseudo');
    document.documentElement.classList.add('pseudo-fs-active');

    // Se intenta igual por si el navegador lo soporta (Chrome/Android sí lo hace,
    // pero esta rama es específicamente para iOS/Safari donde fallará en silencio).
    screen.orientation?.lock?.('landscape').catch(() => {});

    // Si el teléfono sigue en portrait, forzar apariencia landscape con CSS
    if (!isPhysicalLandscape()) {
      videoPlayerWrap.classList.add('ios-force-rotate');
    }

    syncFSIcon();
  }

  function exitIOSPseudoFS() {
    if (!iosPseudoFS) return;
    iosPseudoFS = false;
    videoPlayerWrap.classList.remove('fullscreen-pseudo', 'ios-force-rotate');
    document.documentElement.classList.remove('pseudo-fs-active');
    screen.orientation?.unlock?.();
    syncFSIcon();
  }

  // Si el usuario gira físicamente el teléfono estando en pseudo-FS,
  // quitar la rotación CSS forzada para no rotar dos veces.
  window.addEventListener('orientationchange', () => {
    if (!iosPseudoFS) return;
    videoPlayerWrap.classList.toggle('ios-force-rotate', !isPhysicalLandscape());
  });
  window.addEventListener('resize', () => {
    if (!iosPseudoFS) return;
    videoPlayerWrap.classList.toggle('ios-force-rotate', !isPhysicalLandscape());
  });

  function requestFS() {
    // ── Salir de cualquier modo fullscreen ──
    if (isInFullscreen()) {
      if (iosPseudoFS) { exitIOSPseudoFS(); return; }
      (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
      return;
    }

    // ── iOS Safari: SIEMPRE pseudo-fullscreen con rotación CSS ──
    // No se debe intentar la Fullscreen API nativa aquí ni caer a un
    // fallback que llame a videoPlayer.requestFullscreen(): eso es lo
    // que antes abría el reproductor nativo de iOS en vez de nuestra UI.
    if (isIOS) {
      enterIOSPseudoFS();
      return;
    }

    // ── Android / Chrome / Desktop: Fullscreen API estándar (SIN CAMBIOS) ──
    const target = videoPlayerWrap;
    const fn = target.requestFullscreen
            || target.webkitRequestFullscreen
            || target.mozRequestFullScreen
            || target.msRequestFullscreen;

    if (!fn) {
      // Último fallback: pseudo-fullscreen también para escritorios sin soporte
      enterIOSPseudoFS();
      return;
    }

    fn.call(target)
      .then(() => {
        // Bloquear orientación landscape en Android (Chrome ≥ 79)
        if (isTouchDevice && screen.orientation?.lock) {
          screen.orientation.lock('landscape').catch(() => {});
        }
      })
      .catch(() => {
        // Fallback: intentar en el propio <video>
        const vfn = videoPlayer.requestFullscreen || videoPlayer.webkitRequestFullscreen;
        if (vfn) vfn.call(videoPlayer);
        else enterIOSPseudoFS(); // último recurso: pseudo-FS
      });
  }

  function syncFSIcon() {
    const icon = isInFullscreen() ? 'fa-compress' : 'fa-expand';
    if (fullscreenBtn) fullscreenBtn.innerHTML = `<i class="fas ${icon}"></i>`;
    const railBtn = document.getElementById('railFullscreenBtn');
    if (railBtn) railBtn.innerHTML = `<i class="fas ${icon}"></i>`;
  }

  fullscreenBtn?.addEventListener('click', requestFS);
  document.getElementById('railFullscreenBtn')?.addEventListener('click', requestFS);

  // Salir del pseudo-FS con Escape (teclado físico o botón Atrás en Android)
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && iosPseudoFS) exitIOSPseudoFS(); });

  document.addEventListener('fullscreenchange', () => {
    syncFSIcon();
    if (document.fullscreenElement === videoPlayerWrap) {
      relocateModalsIntoFullscreen();
    } else if (!document.fullscreenElement) {
      restoreModalsFromFullscreen();
      if (screen.orientation?.unlock) screen.orientation.unlock();
    }
  });
  document.addEventListener('webkitfullscreenchange', () => {
    syncFSIcon();
    if (document.webkitFullscreenElement === videoPlayerWrap) {
      relocateModalsIntoFullscreen();
    } else if (!document.webkitFullscreenElement) {
      restoreModalsFromFullscreen();
    }
  });
  videoPlayer.addEventListener('webkitendfullscreen', syncFSIcon);



  // ── Progreso ──────────────────────────────────────────────
  const progressBuffered = document.getElementById('Barbuffered');
  const progressHandle   = document.getElementById('Barhandle');

  let isScrubbing = false;   // true mientras el usuario arrastra
  let wasPlayingBeforeScrub = false;
  let scrubStartClientX = 0;
  let scrubStartFrac = 0;

  // FIX: antes el arrastre mapeaba la posición del dedo 1:1 contra
  // TODO el ancho de la barra (pctFromClientX = posición absoluta).
  // En un video largo eso es brutal: en una barra de ~300px
  // representando 60 minutos, mover el dedo 1cm salta varios minutos
  // — se sentía "demasiado rápido" comparado con apps como YouTube.
  // Ahora se usa MOVIMIENTO RELATIVO desde el punto donde empezó el
  // arrastre, multiplicado por un factor de sensibilidad < 1: hace
  // falta mover el dedo más para avanzar el mismo tiempo de video,
  // dando control fino real.
  const SCRUB_SENSITIVITY = 0.4; // 1 = igual que antes (1:1); más bajo = más lento/fino

  // Convierte una coordenada X de pantalla (mouse o touch) en fracción 0..1
  // dentro de la barra, con clamp para que no se salga del rango.
  // (Se sigue usando para el punto de partida del drag y para clicks
  // directos sobre la barra, donde SÍ tiene sentido ir 1:1 al punto tocado).
  function pctFromClientX(clientX) {
    const rect = progressBar.getBoundingClientRect();
    const raw = (clientX - rect.left) / rect.width;
    return Math.min(1, Math.max(0, raw));
  }

  // Actualiza visualmente el relleno (sin tocar currentTime todavía;
  // eso se hace aparte para no generar cientos de seeks por segundo).
  function paintProgress(frac) {
    const pct = frac * 100;
    if (progressFilled) progressFilled.style.width = pct + '%';
    if (progressHandle) progressHandle.style.left = pct + '%';
  }

  // Pinta la barra de buffer: usa el rango de `buffered` que contiene
  // (o está más cerca de) currentTime, que es lo que realmente le
  // importa al usuario ("¿cuánto tengo ya descargado desde donde estoy?").
  function paintBuffered() {
    if (!progressBuffered || !videoPlayer.duration) return;
    const buf = videoPlayer.buffered;
    if (!buf || buf.length === 0) return;
    let end = 0;
    for (let i = 0; i < buf.length; i++) {
      if (buf.start(i) <= videoPlayer.currentTime && videoPlayer.currentTime <= buf.end(i)) {
        end = buf.end(i);
        break;
      }
      // fallback: el tramo con el final más lejano
      if (buf.end(i) > end) end = buf.end(i);
    }
    progressBuffered.style.width = (end / videoPlayer.duration * 100) + '%';
  }

  videoPlayer.addEventListener('timeupdate', () => {
    if (!videoPlayer.duration || isScrubbing) return; // no pelear con el drag
    paintProgress(videoPlayer.currentTime / videoPlayer.duration);
    if (currentTimeEl) currentTimeEl.textContent = formatTime(videoPlayer.currentTime);
  });
  videoPlayer.addEventListener('progress', paintBuffered);
  videoPlayer.addEventListener('loadedmetadata', () => {
    if (durationEl) durationEl.textContent = formatTime(videoPlayer.duration);
    paintBuffered();
  });

  function startScrub(clientX) {
    isScrubbing = true;
    wasPlayingBeforeScrub = !videoPlayer.paused;
    videoPlayer.pause(); // evita que 'timeupdate' pise el drag mientras se arrastra
    progressBar.classList.add('scrubbing');
    // Punto de partida del arrastre: el primer toque SÍ salta
    // directamente a la posición tocada (como tocar cualquier parte
    // de la barra para saltar ahí) — solo el arrastre posterior desde
    // este punto es relativo/atenuado, no el toque inicial.
    scrubStartClientX = clientX;
    scrubStartFrac = pctFromClientX(clientX);
    paintProgress(scrubStartFrac);
  }
  function updateScrub(clientX) {
    const rect = progressBar.getBoundingClientRect();
    // Movimiento relativo desde el inicio del drag, atenuado por
    // SCRUB_SENSITIVITY — así el avance en el video es más lento que
    // el movimiento real del dedo, permitiendo precisión.
    const deltaFrac = ((clientX - scrubStartClientX) / rect.width) * SCRUB_SENSITIVITY;
    const frac = Math.min(1, Math.max(0, scrubStartFrac + deltaFrac));
    paintProgress(frac);
    if (currentTimeEl && videoPlayer.duration) {
      currentTimeEl.textContent = formatTime(frac * videoPlayer.duration);
    }
    return frac;
  }
  function endScrub(clientX) {
    if (!isScrubbing) return;
    const frac = updateScrub(clientX);
    if (videoPlayer.duration) videoPlayer.currentTime = frac * videoPlayer.duration;
    isScrubbing = false;
    progressBar.classList.remove('scrubbing');
    if (wasPlayingBeforeScrub) {
      videoPlayer.play().catch(() => {});
    }
  }

  // ── Mouse ──
  progressBar?.addEventListener('mousedown', e => {
    e.preventDefault();
    startScrub(e.clientX);
    const onMove = ev => updateScrub(ev.clientX);
    const onUp = ev => {
      endScrub(ev.clientX);
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  });

  // ── Touch (móvil) ──
  progressBar?.addEventListener('touchstart', e => {
    startScrub(e.touches[0].clientX);
  }, { passive: true });
  progressBar?.addEventListener('touchmove', e => {
    if (!isScrubbing) return;
    e.preventDefault(); // evita que la página haga scroll mientras se arrastra
    updateScrub(e.touches[0].clientX);
  }, { passive: false });
  progressBar?.addEventListener('touchend', e => {
    endScrub(e.changedTouches[0].clientX);
  });
  progressBar?.addEventListener('touchcancel', () => {
    isScrubbing = false;
    progressBar.classList.remove('scrubbing');
  });

  // Click simple (desktop, sin arrastre) sigue funcionando gracias a
  // mousedown+mouseup en el mismo punto, así que no hace falta un
  // listener de 'click' aparte.

  // ── Auto-fade controles ─────────────────────────────────
  const wrap = videoPlayerWrap;

  
  if (isIOS && wrap) {
    // Forzar visible de entrada
    wrap.classList.add('controls-visible');

    let iosFadeTimer = null;
    const IOS_FADE_DELAY = 4000; 

    function iosShowControls() {
      wrap.classList.add('controls-visible');
      clearTimeout(iosFadeTimer);
      if (!videoPlayer.paused) {
        iosFadeTimer = setTimeout(() => wrap.classList.remove('controls-visible'), IOS_FADE_DELAY);
      }
    }

  
    wrap.addEventListener('touchstart', () => iosShowControls(), { passive: true });

    
    videoPlayer.addEventListener('pause', () => {
      clearTimeout(iosFadeTimer);
      wrap.classList.add('controls-visible');
    });
    videoPlayer.addEventListener('play', () => {
      clearTimeout(iosFadeTimer);
      iosFadeTimer = setTimeout(() => wrap.classList.remove('controls-visible'), IOS_FADE_DELAY);
    });

   
    videoPlayer.addEventListener('webkitendfullscreen', () => {
      wrap.classList.add('controls-visible');
    });

  } else if (wrap && window.matchMedia('(hover: none)').matches) {
    // Android / touch no-iOS: siempre visible
    wrap.classList.add('controls-visible');
  } else if (wrap && window.matchMedia('(hover: hover)').matches) {
    // Desktop: mostrar al mover el ratón, ocultar 3 s después de soltar
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
    // Early-return: si ya está oculto, no tocar el DOM (se llama muy
    // seguido desde 'timeupdate' en iOS, ~4 veces por segundo).
    if (!loadingOverlay.classList.contains('show') && loadingOverlay.style.display === 'none') return;
    loadingOverlay.classList.remove('show');
    loadingOverlay.style.pointerEvents = 'none';
    setTimeout(() => {
      if (!loadingOverlay.classList.contains('show')) loadingOverlay.style.display = 'none';
    }, 350);
  }
  function showLoader() {
    if (!loadingOverlay) return;
    loadingOverlay.style.display = 'flex';
    void loadingOverlay.offsetHeight;
    loadingOverlay.classList.add('show');
  }

  // Siempre ocultar cuando hay datos o está reproduciendo
  ['canplay', 'canplaythrough', 'loadeddata', 'loadedmetadata', 'playing'].forEach(ev =>
    videoPlayer.addEventListener(ev, hideLoader)
  );
  videoPlayer.addEventListener('error', () => { hideLoader(); showToast('Error al cargar el video'); });

  if (isIOS) {
    // iOS Safari NO carga datos del video sin gesto del usuario.
    // canplay / loadeddata nunca se disparan en page load → pantalla negra + spinner infinito.
    // Solución: ocultar overlay de inmediato. Mostrar spinner SOLO si el video ya
    // empezó a reproducir y necesita buffering (waiting tras primer play).
    hideLoader();

    // FIX: se removió la bandera `iosPlayStarted` que bloqueaba el
    // spinner hasta que se disparara el primer evento 'play' nativo.
    // Ese supuesto ("iOS nunca carga datos sin gesto") ya no aplica:
    // desde que arreglamos el autoplay muted+playsinline+autoplay,
    // iOS SÍ empieza a cargar datos solo, y el usuario quiere ver el
    // spinner también durante ESA carga inicial, no solo después del
    // primer play. Ahora 'waiting'/'stalled' muestran el spinner en
    // cualquier momento, igual que en Android — el debounce de 400ms
    // de abajo sigue evitando parpadeos por micro-cortes.
    let iosWaitingTimer = null;

    // Solo mostrar el spinner si el buffering es real y persiste (>400ms).
    // Evita el falso "loading" ante seeks normales o micro-cortes de red
    // que se resuelven solos en milisegundos.
    function scheduleIOSLoader() {
      clearTimeout(iosWaitingTimer);
      iosWaitingTimer = setTimeout(showLoader, 400);
    }
    function cancelIOSLoader() {
      clearTimeout(iosWaitingTimer);
      // Importante: si el spinner YA se mostró (el timer de 400ms ya corrió),
      // hay que ocultarlo explícitamente aquí. No basta con esperar el
      // evento 'playing': en iOS Safari, tras resolverse un 'waiting',
      // muchas veces NO se vuelve a disparar 'playing', solo 'timeupdate'.
      // Por eso antes el spinner se quedaba pegado varios segundos.
      hideLoader();
    }

    videoPlayer.addEventListener('waiting', scheduleIOSLoader);
    videoPlayer.addEventListener('stalled', scheduleIOSLoader);
    ['playing', 'canplay', 'timeupdate'].forEach(ev =>
      videoPlayer.addEventListener(ev, cancelIOSLoader)
    );
  } else {
    // Android / desktop: mostrar spinner mientras carga datos
    videoPlayer.addEventListener('waiting', showLoader);
    videoPlayer.addEventListener('stalled',  showLoader);
    // Fallback: ocultar tras 3s si canplay no se dispara (Android sin interacción)
    const _lt = setTimeout(hideLoader, 3000);
    videoPlayer.addEventListener('canplay', () => clearTimeout(_lt), { once: true });
    if (videoPlayer.readyState >= 2) {
      hideLoader();
    } else {
      videoPlayer.addEventListener('loadeddata', hideLoader, { once: true });
    }
  }

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
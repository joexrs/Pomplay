/**
 * REPRODUCTOR DE VIDEO PERSONALIZADO — pomplay.com.pe
 * v3.1 — FIXES:
 *   1. Zoom: arrastrar con mouse/touch cuando zoom > 1
 *   2. Clips: descarga directa en WebM funcional (sin conversión que rompe el archivo)
 *   3. Compartir: descarga el video + abre la red social
 *   4. Play button: corregido conflicto de eventos
 */

document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const videoPlayer     = document.getElementById('videoPlayer');
  const videoControls   = document.getElementById('videoControls');
  const videoPlayerWrap = document.getElementById('videoPlayerWrap');

  if (!videoPlayer || !videoControls) return;

  const playPauseBtn   = document.getElementById('playPauseBtn');
  const rewindBtn      = document.getElementById('rewindBtn');
  const forwardBtn     = document.getElementById('forwardBtn');
  const volumeBtn      = document.getElementById('volumeBtn');
  const volumeSlider   = document.getElementById('volumeSlider');
  const speedBtn       = document.getElementById('speedBtn');
  const speedMenu      = document.getElementById('speedMenu');
  const pipBtn         = document.getElementById('pipBtn');
  const fullscreenBtn  = document.getElementById('fullscreenBtn');
  const progressBar    = document.getElementById('progressBar');
  const progressFilled = document.getElementById('progressFilled');
  const currentTimeEl  = document.getElementById('currentTime');
  const durationEl     = document.getElementById('duration');
  const loadingOverlay = document.getElementById('loadingOverlay');
  const zoomInBtn      = document.getElementById('zoomInBtn');
  const zoomOutBtn     = document.getElementById('zoomOutBtn');
  const zoomLevel      = document.getElementById('zoomLevel');

  const recordBtn    = document.getElementById('recordBtn');
  const clipsBtn     = document.getElementById('clipsBtn');
  const clipsBadge   = document.getElementById('clipsBadge');
  const recIndicator = document.getElementById('recIndicator');
  const recTimer     = document.getElementById('recTimer');

  const downloadFullBtn = document.getElementById('downloadFullBtn');

  const clipsModal      = document.getElementById('clipsModal');
  const clipsListEl     = document.getElementById('clipsList');
  const clipsCountLabel = document.getElementById('clipsCountLabel');
  const downloadAllBtn  = document.getElementById('downloadAllClipsBtn');
  const shareAllBtn     = document.getElementById('shareAllClipsBtn');
  const wmProgress      = document.getElementById('wmProgress');
  const wmBarFill       = document.getElementById('wmBarFill');
  const wmProgressLabel = document.getElementById('wmProgressLabel');

  /* ══ FIX 4: PLAY BUTTON ════════════════════════════════════
   * El problema era que el click en el <video> propagaba al botón
   * y a los controles simultáneamente. Separamos la lógica:
   * - Click en el <video> directamente → toggle play/pause
   * - El botón #playPauseBtn maneja su propio click
   * - Se usa stopPropagation para evitar doble disparo
   */
  function togglePlayPause() {
    if (videoPlayer.paused || videoPlayer.ended) {
      videoPlayer.play().catch(err => { console.warn('[play]', err); syncPlayIcon(); });
    } else {
      videoPlayer.pause();
    }
  }

  /* ══ ZOOM CON DRAG/PAN ══════════════════════════════════════ */
  let currentZoom = 1;
  const minZoom = 1, maxZoom = 3, zoomStep = 0.1;

  // Pan state
  let panX = 0, panY = 0;
  let isDragging = false;
  let dragStartX = 0, dragStartY = 0;
  let panStartX = 0, panStartY = 0;

  function applyTransform() {
    if (currentZoom <= 1) {
      panX = 0; panY = 0;
    }
    videoPlayer.style.transform = `scale(${currentZoom}) translate(${panX}px, ${panY}px)`;
    videoPlayer.style.transformOrigin = 'center center';
  }

  function clampPan() {
    if (currentZoom <= 1) { panX = 0; panY = 0; return; }
    const maxPanX = (videoPlayer.offsetWidth  * (currentZoom - 1)) / (2 * currentZoom);
    const maxPanY = (videoPlayer.offsetHeight * (currentZoom - 1)) / (2 * currentZoom);
    panX = Math.max(-maxPanX, Math.min(maxPanX, panX));
    panY = Math.max(-maxPanY, Math.min(maxPanY, panY));
  }

  // Mouse drag
  videoPlayer.addEventListener('mousedown', e => {
    if (currentZoom <= 1) return;
    isDragging = true;
    dragStartX = e.clientX;
    dragStartY = e.clientY;
    panStartX  = panX;
    panStartY  = panY;
    videoPlayer.style.cursor = 'grabbing';
    e.preventDefault();
  });

  document.addEventListener('mousemove', e => {
    if (!isDragging) return;
    const dx = (e.clientX - dragStartX) / currentZoom;
    const dy = (e.clientY - dragStartY) / currentZoom;
    panX = panStartX + dx;
    panY = panStartY + dy;
    clampPan();
    applyTransform();
  });

  document.addEventListener('mouseup', e => {
    if (!isDragging) return;
    isDragging = false;
    videoPlayer.style.cursor = currentZoom > 1 ? 'grab' : '';
    // Si apenas se movió, es un click → toggle play
    const dx = Math.abs(e.clientX - dragStartX);
    const dy = Math.abs(e.clientY - dragStartY);
    if (dx < 5 && dy < 5) togglePlayPause();
  });

  // Touch drag
  let touchStartX = 0, touchStartY = 0;
  let touchPanStartX = 0, touchPanStartY = 0;
  let touchMoved = false;

  videoPlayer.addEventListener('touchstart', e => {
    if (currentZoom <= 1) return;
    const t = e.touches[0];
    touchStartX = t.clientX; touchStartY = t.clientY;
    touchPanStartX = panX; touchPanStartY = panY;
    touchMoved = false;
  }, { passive: true });

  videoPlayer.addEventListener('touchmove', e => {
    if (currentZoom <= 1) return;
    const t = e.touches[0];
    const dx = (t.clientX - touchStartX) / currentZoom;
    const dy = (t.clientY - touchStartY) / currentZoom;
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) touchMoved = true;
    panX = touchPanStartX + dx;
    panY = touchPanStartY + dy;
    clampPan();
    applyTransform();
    e.preventDefault();
  }, { passive: false });

  videoPlayer.addEventListener('touchend', () => {
    if (currentZoom <= 1 && !touchMoved) togglePlayPause();
  });

  function setZoom(level) {
    const prev = currentZoom;
    currentZoom = Math.max(minZoom, Math.min(maxZoom, level));

    // Si se reduce al mínimo, resetear pan
    if (currentZoom === 1) { panX = 0; panY = 0; }
    // Si venía de 1 y ahora sube, no hace falta ajustar pan
    if (prev !== currentZoom) clampPan();

    applyTransform();
    videoPlayer.style.cursor = currentZoom > 1 ? 'grab' : '';

    if (zoomLevel) zoomLevel.textContent = Math.round(currentZoom * 100) + '%';

    let ind = document.getElementById('zoomIndicator');
    if (!ind) {
      ind = document.createElement('div');
      ind.id = 'zoomIndicator';
      ind.style.cssText = 'position:absolute;top:20px;right:20px;background:rgba(0,0,0,0.8);color:#fff;padding:8px 16px;border-radius:8px;font-size:14px;font-weight:600;z-index:1000;transition:opacity 0.3s;pointer-events:none;font-family:\'DM Mono\',monospace;';
      videoPlayerWrap?.appendChild(ind);
    }
    ind.textContent = `Zoom: ${Math.round(currentZoom * 100)}%`;
    ind.style.opacity = '1';
    clearTimeout(ind._t);
    ind._t = setTimeout(() => { ind.style.opacity = '0'; }, 2000);
  }

  zoomInBtn?.addEventListener('click',  () => setZoom(currentZoom + zoomStep));
  zoomOutBtn?.addEventListener('click', () => setZoom(currentZoom - zoomStep));
  videoPlayerWrap?.addEventListener('wheel', e => {
    if (e.ctrlKey || e.metaKey) {
      e.preventDefault();
      setZoom(currentZoom + (e.deltaY < 0 ? zoomStep : -zoomStep));
    }
  }, { passive: false });

  /* ══ CLIPS STORAGE ═════════════════════════════════════════ */
  const CLIPS_KEY = 'pomplay_clips';

  function loadClips() {
    try { return JSON.parse(localStorage.getItem(CLIPS_KEY) || '[]'); }
    catch { return []; }
  }

  function saveClips(clips) {
    try { localStorage.setItem(CLIPS_KEY, JSON.stringify(clips)); } catch {}
  }

  function updateClipsBadge() {
    if (!clipsBadge) return;
    const n = loadClips().length;
    clipsBadge.textContent = n > 99 ? '99+' : n;
    clipsBadge.classList.toggle('visible', n > 0);
  }

  function formatDuration(ms) {
    const s = Math.floor(ms / 1000), m = Math.floor(s / 60);
    return `${String(m).padStart(2,'0')}:${String(s % 60).padStart(2,'0')}`;
  }

  /* ══ LOGO (precarga única) ══════════════════════════════════ */
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
  loadLogo();

  /* ══ HELPERS DE DIBUJO ══════════════════════════════════════ */
  function drawWatermarkOverlay(ctx, W, H, logo) {
    if (logo && logo.naturalWidth > 0) {
      const ls = Math.max(48, W * 0.07);
      const lh = ls * (logo.naturalHeight / logo.naturalWidth);
      ctx.globalAlpha = 0.88;
      ctx.drawImage(logo, W - ls - 14, 10, ls, lh);
      ctx.globalAlpha = 1;
    }
    ctx.save();
    ctx.translate(W / 2, H / 2);
    ctx.rotate(-Math.PI / 6);
    ctx.font = `500 ${Math.max(20, W * 0.032)}px 'DM Sans',sans-serif`;
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillStyle = 'rgba(255,255,255,0.06)';
    ctx.shadowBlur = 0;
    ctx.fillText('pomplay.com.pe', 0, 0);
    ctx.restore();
    ctx.shadowBlur = 0; ctx.shadowColor = 'transparent';
  }

  function drawOutroFrame(ctx, W, H, logo, alpha) {
    ctx.fillStyle = '#08080f';
    ctx.fillRect(0, 0, W, H);

    const fontSize = Math.max(18, W * 0.026);
    const gap = 16;

    if (logo && logo.naturalWidth > 0) {
      const lw = Math.min(W * 0.38, 320);
      const lh = lw * (logo.naturalHeight / logo.naturalWidth);
      const blockCY = H / 2 + H * 0.08;
      const totalH  = lh + gap + fontSize;
      const logoY   = blockCY - totalH / 2;
      const textY   = logoY + lh + gap + fontSize / 2;

      ctx.globalAlpha = alpha;
      ctx.drawImage(logo, (W - lw) / 2, logoY, lw, lh);

      ctx.font = `700 ${fontSize}px 'DM Sans',sans-serif`;
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.shadowColor = 'rgba(231,76,60,0.6)'; ctx.shadowBlur = 16;
      ctx.fillStyle = '#ffffff';
      ctx.fillText('pomplay.com.pe', W / 2, textY);
    } else {
      ctx.font = `700 ${fontSize}px 'DM Sans',sans-serif`;
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.shadowColor = 'rgba(231,76,60,0.6)'; ctx.shadowBlur = 16;
      ctx.globalAlpha = alpha; ctx.fillStyle = '#ffffff';
      ctx.fillText('pomplay.com.pe', W / 2, H / 2 + H * 0.08);
    }
    ctx.globalAlpha = 1; ctx.shadowBlur = 0; ctx.shadowColor = 'transparent';
  }

  /* ══ GRABACIÓN CON WATERMARK EN TIEMPO REAL ════════════════ */
  let mediaRecorder  = null;
  let recordedChunks = [];
  let recordStart    = null;
  let recTimerInt    = null;
  let isRecording    = false;
  let _recCanvas     = null;
  let _recCtx        = null;
  let _recRafId      = null;
  let _recStopping   = false;

  function updateRecordBtnTimer(elapsed) {
    if (!recordBtn) return;
    recordBtn.innerHTML = `<i class="fas fa-stop"></i><span class="rec-btn-timer">${formatDuration(elapsed)}</span>`;
  }

  async function startRecording() {
    if (_recStopping) return;

    const videoSrc = videoPlayer.currentSrc || videoPlayer.src;
    if (!videoSrc && !videoPlayer.srcObject) {
      showToast('⚠ No hay video cargado para grabar'); return;
    }

    const logo = await loadLogo();

    const W = videoPlayer.videoWidth  || 1280;
    const H = videoPlayer.videoHeight || 720;

    _recCanvas = document.createElement('canvas');
    _recCanvas.width  = W;
    _recCanvas.height = H;
    _recCanvas.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;pointer-events:none;';
    document.body.appendChild(_recCanvas);
    _recCtx = _recCanvas.getContext('2d');

    let canvasStream;
    try {
      canvasStream = _recCanvas.captureStream(30);
    } catch (e) {
      showToast('⚠ Tu navegador no soporta grabación de canvas');
      cleanupRecCanvas(); return;
    }

    try {
      const videoStream = videoPlayer.captureStream
        ? videoPlayer.captureStream()
        : videoPlayer.mozCaptureStream
          ? videoPlayer.mozCaptureStream()
          : null;
      if (videoStream) {
        videoStream.getAudioTracks().forEach(track => canvasStream.addTrack(track));
      }
    } catch (e) {
      console.warn('[record] audio track no disponible:', e);
    }

    const mimeType = [
      'video/webm;codecs=vp8,opus',
      'video/webm;codecs=vp9,opus',
      'video/webm;codecs=vp8',
      'video/webm;codecs=vp9',
      'video/webm'
    ].find(t => MediaRecorder.isTypeSupported(t)) || 'video/webm';

    try {
      mediaRecorder = new MediaRecorder(canvasStream, {
        mimeType,
        videoBitsPerSecond: 2500000,
        audioBitsPerSecond: 128000,
      });
    } catch {
      try { mediaRecorder = new MediaRecorder(canvasStream); }
      catch {
        showToast('⚠ Formato de grabación no soportado');
        cleanupRecCanvas(); return;
      }
    }

    recordedChunks = [];
    mediaRecorder.ondataavailable = e => {
      if (e.data && e.data.size > 0) recordedChunks.push(e.data);
    };

    mediaRecorder.onstop = () => {
      if (recordedChunks.length === 0) {
        showToast('⚠ No se capturó ningún dato');
        _recStopping = false; return;
      }
      const finalMime = mediaRecorder.mimeType || mimeType || 'video/webm';
      const blob = new Blob(recordedChunks, { type: finalMime });
      if (blob.size < 500) {
        showToast('⚠ Clip demasiado corto');
        _recStopping = false; return;
      }
      saveClipToStorage(blob, Date.now() - recordStart, finalMime);
      _recStopping = false;
    };

    function renderFrame() {
      try {
        _recCtx.drawImage(videoPlayer, 0, 0, W, H);
        drawWatermarkOverlay(_recCtx, W, H, logo);
      } catch {}
      _recRafId = requestAnimationFrame(renderFrame);
    }

    recordStart = Date.now();
    mediaRecorder.start(500);
    isRecording = true;

    if (videoPlayer.paused) videoPlayer.play();

    renderFrame();

    if (recordBtn) {
      recordBtn.classList.add('recording');
      recordBtn.setAttribute('title', 'Detener grabación');
      recordBtn.innerHTML = `<i class="fas fa-stop"></i><span class="rec-btn-timer">00:00</span>`;
    }
    if (recIndicator) recIndicator.classList.add('active');

    recTimerInt = setInterval(() => {
      updateRecordBtnTimer(Date.now() - recordStart);
      if (recTimer) recTimer.textContent = formatDuration(Date.now() - recordStart);
    }, 500);

    showToast('● Grabando — toca ⏹ para detener');
  }

  function captureVideoFrame() {
    try {
      const vw = videoPlayer.videoWidth, vh = videoPlayer.videoHeight;
      if (!vw || !vh) return null;
      const c = document.createElement('canvas');
      c.width = vw; c.height = vh;
      c.getContext('2d').drawImage(videoPlayer, 0, 0, vw, vh);
      return c.toDataURL('image/jpeg', 0.82);
    } catch { return null; }
  }

  function cleanupRecCanvas() {
    if (_recCanvas && _recCanvas.parentNode) {
      try { document.body.removeChild(_recCanvas); } catch {}
    }
    _recCanvas = null; _recCtx = null;
  }

  async function stopRecording() {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') return;
    if (_recStopping) return;
    _recStopping = true;

    const thumbnail = captureVideoFrame();

    cancelAnimationFrame(_recRafId);
    _recRafId = null;

    clearInterval(recTimerInt);
    if (recordBtn) {
      recordBtn.classList.remove('recording');
      recordBtn.setAttribute('title', 'Procesando outro…');
      recordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      recordBtn.disabled = true;
    }
    if (recIndicator) recIndicator.classList.remove('active');
    if (recTimer) recTimer.textContent = '00:00';
    isRecording = false;

    if (_recCtx && _recCanvas) {
      const logo = await loadLogo();
      const W = _recCanvas.width, H = _recCanvas.height;
      const OUTRO_MS = 3000;
      const t0 = performance.now();

      await new Promise(res => {
        const f = now => {
          const p = (now - t0) / OUTRO_MS;
          if (p >= 1) { drawOutroFrame(_recCtx, W, H, logo, 1); res(); return; }
          const alpha = p < 0.18 ? p / 0.18 : p > 0.82 ? (1 - p) / 0.18 : 1;
          drawOutroFrame(_recCtx, W, H, logo, alpha);
          requestAnimationFrame(f);
        };
        requestAnimationFrame(f);
      });
    }

    try { mediaRecorder.requestData(); } catch {}
    mediaRecorder.stop();
    cleanupRecCanvas();

    if (recordBtn) {
      recordBtn.setAttribute('title', 'Grabar clip');
      recordBtn.innerHTML = '<i class="fas fa-circle"></i>';
      recordBtn.disabled = false;
    }

    _pendingThumbnail = thumbnail;
  }

  let _pendingThumbnail = null;

  function saveClipToStorage(blob, durationMs, mimeType) {
    const thumbnail = _pendingThumbnail || null;
    _pendingThumbnail = null;

    const reader = new FileReader();
    reader.onloadend = () => {
      try {
        const clips = loadClips();
        const idx = clips.length + 1;
        clips.push({
          id: Date.now(),
          name: `clip_${idx}.webm`,
          data: reader.result,
          thumbnail,
          duration: durationMs,
          mimeType,
          hasWatermark: true,
          createdAt: new Date().toISOString(),
        });
        saveClips(clips);
        updateClipsBadge();
        renderClipsList();
        showToast('✓ Clip guardado — toca descargar para obtenerlo', 'success');
      } catch (err) {
        console.error('Error guardando clip:', err);
        showToast('⚠ Sin espacio — descargando directamente');
        _triggerBlobDownload(blob, `clip_pomplay_${Date.now()}.webm`);
      }
    };
    reader.onerror = () => showToast('⚠ Error al procesar el clip');
    reader.readAsDataURL(blob);
  }

  /* ══ FIX 2: DESCARGA DE CLIP — WebM directo ════════════════
   *
   * La conversión WebM→MP4 con WebCodecs requiere que el WebM
   * sea perfectamente seekable, lo cual el MediaRecorder no garantiza.
   * En su lugar:
   *   - Si WebCodecs está disponible → intentar conversión MP4
   *   - Si falla o no está disponible → descargar WebM directamente
   *     (WebM es compatible con Chrome, Firefox, Edge, Android)
   *
   * La clave es que el archivo sea reproducible, no el contenedor.
   */
  async function downloadClipDirect(clip) {
    showToast('⏳ Preparando descarga…');
    try {
      const blob = await fetch(clip.data).then(r => r.blob());
      const baseName = clip.name.replace(/\.[^.]+$/, '') + '_pomplay';

      // Intentar MP4 solo si WebCodecs está disponible
      if (typeof VideoDecoder !== 'undefined' && typeof VideoEncoder !== 'undefined') {
        try {
          await convertAndDownload(blob, baseName);
          return;
        } catch (convErr) {
          console.warn('[mp4] Conversión falló, descargando WebM:', convErr);
        }
      }

      // Fallback: WebM directo (siempre funciona)
      _triggerBlobDownload(blob, baseName + '.webm');
      showToast('✓ Clip descargado (WebM)', 'success');
    } catch {
      showToast('⚠ Error descargando el clip');
    }
  }

  /* ══ CONVERSIÓN WEBM → MP4 (mp4-muxer + WebCodecs) ════════ */
  let _mp4MuxerPromise = null;
  function loadMp4Muxer() {
    if (_mp4MuxerPromise) return _mp4MuxerPromise;
    _mp4MuxerPromise = new Promise((res, rej) => {
      if (window.Mp4Muxer) { res(window.Mp4Muxer); return; }
      const s = document.createElement('script');
      s.src = 'https://cdn.jsdelivr.net/npm/mp4-muxer@4/build/mp4-muxer.min.js';
      s.onload  = () => res(window.Mp4Muxer);
      s.onerror = () => rej(new Error('No se pudo cargar mp4-muxer'));
      document.head.appendChild(s);
    });
    return _mp4MuxerPromise;
  }

  async function convertAndDownload(webmBlob, baseName) {
    if (typeof VideoDecoder === 'undefined' || typeof VideoEncoder === 'undefined') {
      _triggerBlobDownload(webmBlob, baseName + '.webm');
      showToast('Tu navegador no soporta MP4 — descargado como WebM');
      return;
    }

    showToast('⏳ Convirtiendo a MP4…');

    let Mp4Muxer;
    try {
      Mp4Muxer = await loadMp4Muxer();
    } catch {
      _triggerBlobDownload(webmBlob, baseName + '.webm');
      showToast('Error de red — descargado como WebM');
      return;
    }

    const blobUrl = URL.createObjectURL(webmBlob);
    const vid = document.createElement('video');
    vid.src = blobUrl;
    vid.muted = true;
    vid.playsInline = true;
    vid.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:320px;height:180px;opacity:0;pointer-events:none;';
    document.body.appendChild(vid);

    const cleanup = () => {
      try { document.body.removeChild(vid); } catch {}
      URL.revokeObjectURL(blobUrl);
    };

    await new Promise(res => {
      vid.addEventListener('loadedmetadata', res, { once: true });
      vid.addEventListener('canplay', res, { once: true });
      vid.onerror = res;
      setTimeout(res, 8000);
      vid.load();
    });

    const VW = vid.videoWidth  || 1280;
    const VH = vid.videoHeight || 720;
    const FPS = 30;

    const { Muxer, ArrayBufferTarget } = Mp4Muxer;
    const target  = new ArrayBufferTarget();
    const muxer   = new Muxer({
      target,
      video: { codec: 'avc', width: VW, height: VH },
      fastStart: 'in-memory',
    });

    let encoderError = null;
    const encoder = new VideoEncoder({
      output: (chunk, meta) => muxer.addVideoChunk(chunk, meta),
      error:  (e) => { encoderError = e; console.error('[VideoEncoder]', e); },
    });

    encoder.configure({
      codec:     'avc1.42001f',
      width:     VW,
      height:    VH,
      bitrate:   3000000,
      framerate: FPS,
    });

    const offCanvas = new OffscreenCanvas(VW, VH);
    const offCtx    = offCanvas.getContext('2d');

    let frameCount  = 0;
    let convDone    = false;

    await new Promise(async (res) => {
      const safeDur = isFinite(vid.duration) ? vid.duration * 1000 + 8000 : 60000;
      const safeTimeout = setTimeout(() => { convDone = true; res(); }, safeDur);

      const encodeCurrentFrame = () => {
        if (encoderError || convDone) return;
        try {
          offCtx.drawImage(vid, 0, 0, VW, VH);
          const vf = new VideoFrame(offCanvas, {
            timestamp: Math.round((frameCount / FPS) * 1000000),
          });
          encoder.encode(vf, { keyFrame: frameCount % (FPS * 2) === 0 });
          vf.close();
          frameCount++;
        } catch (e) { console.warn('[encode frame]', e); }
      };

      if (typeof vid.requestVideoFrameCallback === 'function') {
        const onFrame = (_, meta) => {
          encodeCurrentFrame();
          if (!vid.ended && !convDone) vid.requestVideoFrameCallback(onFrame);
          else { convDone = true; clearTimeout(safeTimeout); res(); }
        };
        vid.addEventListener('ended', () => { convDone = true; clearTimeout(safeTimeout); res(); }, { once: true });
        vid.requestVideoFrameCallback(onFrame);
        vid.play().catch(() => { convDone = true; res(); });
      } else {
        vid.addEventListener('ended', () => { convDone = true; clearTimeout(safeTimeout); res(); }, { once: true });
        vid.addEventListener('timeupdate', encodeCurrentFrame);
        vid.play().catch(() => { convDone = true; res(); });
      }
    });

    vid.pause();
    cleanup();

    if (encoderError) {
      showToast('⚠ Error encodando — descargando como WebM');
      _triggerBlobDownload(webmBlob, baseName + '.webm');
      return;
    }

    await encoder.flush();
    encoder.close();
    muxer.finalize();

    const mp4Buf  = target.buffer;
    const mp4Blob = new Blob([mp4Buf], { type: 'video/mp4' });

    if (mp4Blob.size < 1000) {
      showToast('⚠ MP4 vacío — descargando como WebM');
      _triggerBlobDownload(webmBlob, baseName + '.webm');
      return;
    }

    _triggerBlobDownload(mp4Blob, baseName + '.mp4');
    showToast('✓ Descargado como MP4', 'success');
  }

  function _triggerBlobDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 8000);
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
      clipsListEl.innerHTML = `<div class="clips-empty"><i class="fas fa-film"></i>No hay clips grabados todavía</div>`;
      return;
    }

    clipsListEl.innerHTML = clips.map(clip => {
      const thumbContent = clip.thumbnail
        ? `<img src="${clip.thumbnail}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" alt="">`
        : `<i class="fas fa-film" style="color:rgba(255,255,255,0.25);font-size:22px;"></i>`;
      const wmBadge = clip.hasWatermark
        ? `<span style="font-size:9px;background:rgba(231,76,60,0.2);border:1px solid rgba(231,76,60,0.4);color:#ff6b5b;padding:1px 5px;border-radius:4px;font-family:'DM Mono',monospace;">WM</span>`
        : '';
      return `
      <div class="clip-item" data-id="${clip.id}">
        <div class="clip-thumb" id="thumb-${clip.id}"
             style="display:flex;align-items:center;justify-content:center;background:#12121e;overflow:hidden;">
          ${thumbContent}
        </div>
        <div class="clip-info">
          <div class="clip-name" style="display:flex;align-items:center;gap:5px;">${escapeHtml(clip.name)} ${wmBadge}</div>
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
      if (wrap) captureThumbnailFromClip(clip, wrap);
    }
  }

  async function captureThumbnailFromClip(clip, wrap) {
    let blobUrl, vid;
    try {
      const blob = await fetch(clip.data).then(r => r.blob());
      blobUrl = URL.createObjectURL(blob);
      vid = document.createElement('video');
      vid.muted = true; vid.playsInline = true;
      vid.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:320px;height:180px;opacity:0;pointer-events:none;';
      vid.src = blobUrl;
      document.body.appendChild(vid);
      await new Promise(res => {
        vid.addEventListener('loadedmetadata', res, { once: true });
        vid.addEventListener('canplay', res, { once: true });
        vid.onerror = res; setTimeout(res, 6000); vid.load();
      });
      await new Promise(res => {
        let done = false;
        const finish = () => { if (!done) { done = true; res(); } };
        vid.addEventListener('timeupdate', () => { if (vid.currentTime > 0) finish(); }, { once: true });
        vid.onerror = finish; setTimeout(finish, 3000);
        vid.play().catch(finish);
      });
      vid.pause();
      const W = vid.videoWidth || 320, H = vid.videoHeight || 180;
      const c = document.createElement('canvas');
      c.width = W; c.height = H;
      c.getContext('2d').drawImage(vid, 0, 0, W, H);
      wrap.innerHTML = `<img src="${c.toDataURL('image/jpeg', 0.82)}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" alt="">`;
      document.body.removeChild(vid);
      URL.revokeObjectURL(blobUrl);
    } catch {
      if (vid && vid.parentNode) try { document.body.removeChild(vid); } catch {}
      if (blobUrl) URL.revokeObjectURL(blobUrl);
    }
  }

  if (clipsListEl) {
    clipsListEl.addEventListener('click', e => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const action = btn.dataset.action;
      const id = parseInt(btn.dataset.id);
      const clips = loadClips();
      const clip = clips.find(c => c.id === id);
      if (!clip) return;

      if (action === 'download') {
        downloadClipDirect(clip);
      } else if (action === 'share') {
        shareClip(clip);
      } else if (action === 'delete') {
        saveClips(clips.filter(c => c.id !== id));
        updateClipsBadge();
        renderClipsList();
        showToast('Clip eliminado');
      }
    });
  }

  /* ══ FIX 3: COMPARTIR — descarga primero + abre red social ═
   *
   * Las redes sociales no pueden recibir un video directamente
   * desde el browser por seguridad. El flujo correcto es:
   *   1. Descargar el clip al dispositivo
   *   2. El usuario lo sube manualmente desde la app de la red social
   *
   * Para WhatsApp Web se intenta Web Share API (en móvil funciona
   * para compartir archivos directamente). En desktop se descarga.
   */
  async function shareClip(clip) {
    const blob = await fetch(clip.data).then(r => r.blob());

    // Intentar Web Share API (solo móvil/Android/iOS)
    if (navigator.share && navigator.canShare) {
      try {
        const file = new File([blob], clip.name, { type: clip.mimeType });
        if (navigator.canShare({ files: [file] })) {
          await navigator.share({
            files: [file],
            title: 'Clip de pomplay.com.pe',
            text: 'Mira este clip de video'
          });
          return;
        }
      } catch (e) {
        if (e.name === 'AbortError') return;
      }
    }

    // Fallback: descargar el archivo y mostrar instrucciones
    _triggerBlobDownload(blob, clip.name);
    showToast('Clip descargado — súbelo desde tu app de la red social', 'info');
  }

  /* ══ DESCARGA DEL VIDEO COMPLETO CON MARCA DE AGUA ══════════ */
  if (downloadFullBtn) downloadFullBtn.addEventListener('click', downloadWithWatermark);

  async function downloadWithWatermark() {
    const videoSrc = videoPlayer.currentSrc || videoPlayer.src;
    if (!videoSrc) { showToast('No hay video disponible'); return; }

    showToast('Preparando descarga con marca de agua…');
    if (wmProgress) wmProgress.classList.add('active');
    if (wmBarFill) wmBarFill.style.width = '5%';
    if (wmProgressLabel) wmProgressLabel.textContent = 'Cargando video…';

    const logo = await loadLogo();

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

      const W = tmpVideo.videoWidth || 1280;
      const H = tmpVideo.videoHeight || 720;
      const canvas = document.createElement('canvas');
      canvas.width = W; canvas.height = H;
      const ctx = canvas.getContext('2d');

      const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9')
        ? 'video/webm;codecs=vp9' : 'video/webm';

      const recorder = new MediaRecorder(canvas.captureStream(30), {
        mimeType, videoBitsPerSecond: 4000000
      });
      const chunks = [];
      recorder.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };

      function drawFrame() {
        ctx.drawImage(tmpVideo, 0, 0, W, H);
        drawWatermarkOverlay(ctx, W, H, logo);
        const sz = Math.max(32, W * 0.045);
        const x = W - sz - 14, y = H - sz - 14, r = sz * 0.22;
        ctx.beginPath();
        ctx.moveTo(x+r,y); ctx.lineTo(x+sz-r,y);
        ctx.quadraticCurveTo(x+sz,y,x+sz,y+r);
        ctx.lineTo(x+sz,y+sz-r); ctx.quadraticCurveTo(x+sz,y+sz,x+sz-r,y+sz);
        ctx.lineTo(x+r,y+sz); ctx.quadraticCurveTo(x,y+sz,x,y+sz-r);
        ctx.lineTo(x,y+r); ctx.quadraticCurveTo(x,y,x+r,y);
        ctx.fillStyle = 'rgba(231,76,60,0.85)'; ctx.fill();
        const cx = x+sz/2, cy = y+sz/2, ts = sz*0.32;
        ctx.beginPath();
        ctx.moveTo(cx-ts*0.6, cy-ts); ctx.lineTo(cx-ts*0.6, cy+ts); ctx.lineTo(cx+ts, cy);
        ctx.closePath(); ctx.fillStyle = '#fff'; ctx.fill();
      }

      tmpVideo.currentTime = 0;
      recorder.start(100);
      await new Promise(res => { tmpVideo.onseeked = res; tmpVideo.currentTime = 0; });

      if (wmBarFill) wmBarFill.style.width = '15%';
      if (wmProgressLabel) wmProgressLabel.textContent = 'Procesando…';

      tmpVideo.play();
      await new Promise(res => {
        const loop = () => {
          if (tmpVideo.ended || tmpVideo.paused) { res(); return; }
          drawFrame();
          const pct = tmpVideo.duration > 0 ? 15 + (tmpVideo.currentTime / tmpVideo.duration) * 70 : 50;
          if (wmBarFill) wmBarFill.style.width = `${Math.min(85, pct)}%`;
          requestAnimationFrame(loop);
        };
        tmpVideo.onended = res; tmpVideo.onerror = res;
        loop();
      });

      if (wmProgressLabel) wmProgressLabel.textContent = 'Generando outro…';
      const t0 = performance.now();
      await new Promise(res => {
        const f = now => {
          const p = (now - t0) / 3000;
          if (p >= 1) { drawOutroFrame(ctx, W, H, logo, 1); res(); return; }
          const a = p < 0.18 ? p / 0.18 : p > 0.82 ? (1 - p) / 0.18 : 1;
          drawOutroFrame(ctx, W, H, logo, a);
          if (wmBarFill) wmBarFill.style.width = `${85 + p * 12}%`;
          requestAnimationFrame(f);
        };
        requestAnimationFrame(f);
      });

      recorder.stop();
      await new Promise(res => { recorder.onstop = res; });

      if (wmBarFill) wmBarFill.style.width = '100%';
      if (wmProgressLabel) wmProgressLabel.textContent = 'Generando archivo…';

      const blob = new Blob(chunks, { type: mimeType });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `pomplay_video_${Date.now()}.webm`;
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      URL.revokeObjectURL(url);
      showToast('✓ Video descargado con marca de agua', 'success');

    } catch (err) {
      console.error('[downloadWithWatermark]', err);
      const a = document.createElement('a');
      a.href = videoSrc; a.download = 'pomplay_video.mp4';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      showToast('Descargando sin marca de agua (error en este navegador)');
    } finally {
      setTimeout(() => {
        if (wmProgress) wmProgress.classList.remove('active');
        if (wmBarFill) wmBarFill.style.width = '0%';
      }, 1200);
    }
  }

  /* ══ UTILIDADES ════════════════════════════════════════════ */
  function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str; return d.innerHTML;
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
    } else if (msg.startsWith('●')) {
      toast.style.borderColor = 'rgba(231,76,60,0.45)';
    }
    toast.classList.add('show');
    clearTimeout(toast._t);
    const dur = (type === 'success' || msg.startsWith('✓')) ? 3500 : (type === 'error') ? 4000 : 3000;
    toast._t = setTimeout(() => toast.classList.remove('show'), dur);
  }

  /* ══ FIX 4: PLAY / PAUSE ════════════════════════════════════
   * Separamos completamente el click del <video> del click del botón.
   * El video solo reacciona a clicks directos sobre él (no propagados).
   * El botón maneja su propio evento de forma independiente.
   */
  function syncPlayIcon() {
    if (!playPauseBtn) return;
    playPauseBtn.innerHTML = videoPlayer.paused ? '<i class="fas fa-play"></i>' : '<i class="fas fa-pause"></i>';
  }

  videoPlayer.addEventListener('play',  syncPlayIcon);
  videoPlayer.addEventListener('pause', syncPlayIcon);
  videoPlayer.addEventListener('ended', syncPlayIcon);

  // Botón play/pause — independiente del click en el video
  if (playPauseBtn) {
    playPauseBtn.addEventListener('click', (e) => {
      e.stopPropagation(); // evitar que suba al wrap y dispare otros handlers
      togglePlayPause();
    });
  }

  // Click directo sobre el <video> — solo cuando NO se está arrastrando
  // La lógica de drag ya llama a togglePlayPause() en mouseup si no hubo movimiento
  // Para touch sin zoom se maneja en touchend arriba
  // Para desktop sin zoom → listener directo en el video
  videoPlayer.addEventListener('click', (e) => {
    if (currentZoom > 1) return; // con zoom, el drag maneja el click
    e.stopPropagation();
    togglePlayPause();
  });

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

  /* ══ VELOCIDAD ═════════════════════════════════════════════ */
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

  /* ══ PICTURE-IN-PICTURE ════════════════════════════════════ */
  if (document.pictureInPictureEnabled && pipBtn) {
    pipBtn.addEventListener('click', async () => {
      try {
        if (document.pictureInPictureElement) await document.exitPictureInPicture();
        else await videoPlayer.requestPictureInPicture();
      } catch (e) { console.error(e); }
    });
  } else if (pipBtn) { pipBtn.style.display = 'none'; }

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
    if (fullscreenBtn) fullscreenBtn.innerHTML = document.fullscreenElement
      ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>';
  });

  /* ══ PROGRESO ══════════════════════════════════════════════ */
  function formatTime(s) {
    const m = Math.floor(s / 60);
    return `${m}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
  }

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

  /* ══ CONTROLES AUTO-FADE ═══════════════════════════════════ */
  const wrap = videoPlayerWrap;

  if (wrap && window.matchMedia('(hover: none)').matches) wrap.classList.add('controls-visible');

  if (wrap && window.matchMedia('(hover: hover)').matches) {
    let fadeTimer;
    const showCtrls = () => {
      wrap.classList.add('controls-visible');
      clearTimeout(fadeTimer);
      fadeTimer = setTimeout(() => { if (!videoPlayer.paused) wrap.classList.remove('controls-visible'); }, 3000);
    };
    wrap.addEventListener('mousemove', showCtrls);
    wrap.addEventListener('mouseenter', showCtrls);
    videoPlayer.addEventListener('play', () => {
      fadeTimer = setTimeout(() => wrap.classList.remove('controls-visible'), 3000);
    });
    videoPlayer.addEventListener('pause', () => wrap.classList.add('controls-visible'));
  }

  /* ══ LOADING ═══════════════════════════════════════════════ */
  function hideLoader() {
    if (!loadingOverlay) return;
    loadingOverlay.classList.remove('show');
    loadingOverlay.style.pointerEvents = 'none';
    setTimeout(() => { if (!loadingOverlay.classList.contains('show')) loadingOverlay.style.display = 'none'; }, 350);
  }
  function showLoader() {
    if (!loadingOverlay) return;
    loadingOverlay.style.display = 'flex';
    void loadingOverlay.offsetHeight;
    loadingOverlay.classList.add('show');
  }

  ['canplay','canplaythrough','loadeddata','loadedmetadata','playing'].forEach(ev => videoPlayer.addEventListener(ev, hideLoader));
  videoPlayer.addEventListener('waiting', showLoader);
  videoPlayer.addEventListener('stalled', showLoader);
  videoPlayer.addEventListener('error', () => { hideLoader(); showToast('Error al cargar el video'); });
  const _loaderTimer = setTimeout(hideLoader, 5000);
  videoPlayer.addEventListener('canplay', () => clearTimeout(_loaderTimer), { once: true });
  if (videoPlayer.readyState >= 2) hideLoader();

  /* ══ MODAL COMPARTIR ═══════════════════════════════════════ */
  const shareModal2 = document.getElementById('mobileShareModal');
  const openShareModal  = () => {
    if (!shareModal2) return;
    shareModal2.style.display = 'block';
    requestAnimationFrame(() => shareModal2.classList.add('open'));
    shareModal2.setAttribute('aria-hidden','false');
  };
  const closeShareModal = () => {
    if (!shareModal2) return;
    shareModal2.classList.remove('open');
    shareModal2.setAttribute('aria-hidden','true');
    setTimeout(() => { shareModal2.style.display = ''; }, 50);
  };

  document.getElementById('shareMainTrigger')?.addEventListener('click', openShareModal);
  shareModal2?.querySelectorAll('[data-action="close"]').forEach(el => el.addEventListener('click', closeShareModal));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeShareModal(); closeClipsModal(); } });

  /* ══ ATAJOS DE TECLADO ═════════════════════════════════════ */
  document.addEventListener('keydown', e => {
    if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) return;
    switch (e.key) {
      case ' ': case 'k': e.preventDefault(); togglePlayPause(); break;
      case 'ArrowLeft':  e.preventDefault(); rewindBtn?.click(); break;
      case 'ArrowRight': e.preventDefault(); forwardBtn?.click(); break;
      case 'ArrowUp':
        e.preventDefault(); videoPlayer.volume = Math.min(1, videoPlayer.volume + 0.1);
        if (volumeSlider) volumeSlider.value = videoPlayer.volume * 100; updateVolumeIcon(); break;
      case 'ArrowDown':
        e.preventDefault(); videoPlayer.volume = Math.max(0, videoPlayer.volume - 0.1);
        if (volumeSlider) volumeSlider.value = videoPlayer.volume * 100; updateVolumeIcon(); break;
      case 'm': e.preventDefault(); volumeBtn?.click(); break;
      case 'f': e.preventDefault(); requestFS(); break;
      case 'r': e.preventDefault(); if (!recordBtn?.disabled) recordBtn?.click(); break;
      case '+': case '=': e.preventDefault(); setZoom(currentZoom + zoomStep); break;
      case '-': case '_': e.preventDefault(); setZoom(currentZoom - zoomStep); break;
      case '0': e.preventDefault(); setZoom(1); break;
    }
  });

  if (downloadAllBtn) {
    downloadAllBtn.addEventListener('click', async () => {
      const clips = loadClips();
      if (!clips.length) { showToast('No hay clips'); return; }
      clips.forEach((c, i) => setTimeout(() => downloadClipDirect(c), i * 400));
      showToast(`Descargando ${clips.length} clip${clips.length > 1 ? 's' : ''}…`);
    });
  }

  if (shareAllBtn) {
    shareAllBtn.addEventListener('click', async () => {
      const clips = loadClips();
      if (!clips.length) { showToast('No hay clips'); return; }
      if (navigator.share) {
        try {
          const files = await Promise.all(clips.map(async c => {
            const blob = await fetch(c.data).then(r => r.blob());
            return new File([blob], c.name, { type: c.mimeType });
          }));
          await navigator.share({ files, title: 'Mis clips - pomplay.com.pe' });
          return;
        } catch (e) { if (e.name === 'AbortError') return; }
      }
      // Fallback: descargar todos
      clips.forEach((c, i) => setTimeout(async () => {
        const blob = await fetch(c.data).then(r => r.blob());
        _triggerBlobDownload(blob, c.name);
      }, i * 300));
      showToast('Descargando todos los clips…');
    });
  }

  if (clipsBtn) clipsBtn.addEventListener('click', openClipsModal);

  if (clipsModal) {
    const backdrop = clipsModal.querySelector('.clips-modal-backdrop');
    const closeBtn = clipsModal.querySelector('.clips-modal-close');
    if (backdrop) backdrop.addEventListener('click', closeClipsModal);
    if (closeBtn) closeBtn.addEventListener('click', closeClipsModal);
  }

  /* ══ INIT ══════════════════════════════════════════════════ */
  updateVolumeIcon();
  updateClipsBadge();
});
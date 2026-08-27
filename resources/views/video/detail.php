<?php require __DIR__ . '/../layouts/header.php'; ?>

<!-- ══════════════════════════════════════════════════════════════
     FIX: CSS / preconnect / fuentes movidos aquí arriba, ANTES de
     cualquier markup del video. Antes estos <link> estaban a mitad
     del <body> (después del overlay de PIN), lo que causaba FOUC:
     el navegador podía pintar el <video>/overlay sin las reglas de
     position/background/z-index aplicadas todavía.

     IDEALMENTE estos tags deberían vivir dentro de <head> en
     layouts/header.php, no aquí. Los dejo en este punto porque es
     lo más temprano a lo que se puede llegar sin tocar ese archivo.
     Si tienen acceso a header.php, muévanlos ahí para el máximo
     beneficio (elimina por completo el riesgo de FOUC).
     ══════════════════════════════════════════════════════════════ -->

<!-- Preconectar con el CDN/host que sirve el video para adelantar
     DNS + TLS handshake antes de que el <video> pida el primer byte.
     ⚠️ Reemplazar por el dominio real donde vive $playableVideoUrl. -->
<link rel="preconnect" href="https://cctv.pomplay.com.pe" crossorigin>
<link rel="dns-prefetch" href="https://cctv.pomplay.com.pe">

<!-- Fuente cargada como <link> no bloqueante en vez de @import
     (el @import que estaba dentro de video-detail-responsive.css
     bloqueaba el parseo de esa hoja completa). -->
<link rel="preload" as="style"
      href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap">
<link rel="stylesheet"
      href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap">

<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-detail-page.css?v=1.0" />
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-player.css?v=16.1" />
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-detail-responsive.css?v=16.1" />

<?php if ($video): ?>
<?php $playableVideoUrl = $videoUrl ?? ($video['video_url'] ?? ''); ?>
<?php $pinLongitud = 6; // Longitud esperada del PIN ?>

<?php if (!empty($requiresPin) && empty($hasAccess)): ?>
<!-- ══ OVERLAY DE PIN — bloquea el video hasta verificar ══ -->
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-pin.css?v=1.0" />

<div class="pin-overlay" id="pinOverlay"
     data-video-code="<?= htmlspecialchars($codigoVideo) ?>"
     data-pin-length="<?= $pinLongitud ?>">
  <div class="pin-card">
    <div class="pin-icon-wrap">
      <i class="fas fa-lock"></i>
    </div>
    <h2 class="pin-title">Video Privado</h2>
    <p class="pin-subtitle">
      Este video requiere un código de acceso.<br>
      Solicítalo al propietario del local.
    </p>

    <div class="pin-digits-wrap" id="pinDigitsWrap">
      <?php for ($i = 0; $i < $pinLongitud; $i++): ?>
        <input type="number" class="pin-digit" maxlength="1" min="0" max="9"
               inputmode="numeric" pattern="\d" autocomplete="off"
               aria-label="Dígito <?= $i + 1 ?>">
      <?php endfor; ?>
    </div>

    <button class="pin-submit-btn" id="pinSubmitBtn" disabled>
      <span class="btn-text"><i class="fas fa-unlock-alt"></i> Verificar código</span>
      <div class="spinner-sm" style="display:none;"></div>
    </button>

    <div class="pin-message" id="pinMessage"></div>
    <div class="pin-attempts" id="pinAttempts"></div>

    <a href="<?= $baseUrl ?>/index.php" class="pin-back-link">
      <i class="fas fa-arrow-left"></i> Volver al inicio
    </a>
  </div>
</div>
<?php endif; ?>

<!-- (CSS ya cargado arriba, cerca del <head> — ver comentario al inicio del archivo) -->

<!-- Fix Android: calcula la altura real del viewport sin la barra del navegador -->
<script>
(function () {
  var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  var isAndroid = /Android/i.test(navigator.userAgent);
  
  // Agregar clase al body para CSS específico
  if (isAndroid) {
    document.documentElement.classList.add('is-android');
  }
  if (isIOS) {
    document.documentElement.classList.add('is-ios');
  }
  
  // FIX: antes solo se escuchaba window 'resize', que en Chrome Android
  // no siempre se dispara a tiempo cuando la barra inferior dinámica
  // (atrás/adelante/+/pestañas) aparece o desaparece — dejaba el
  // contenedor con una altura vieja y esa barra gris quedaba expuesta
  // debajo del video. `visualViewport` es la API pensada exactamente
  // para esto: reporta el viewport realmente visible y sí se actualiza
  // de forma confiable en esas transiciones.
  function setRealVH() {
    var vh = (window.visualViewport ? window.visualViewport.height : window.innerHeight);
    document.documentElement.style.setProperty('--real-vh', vh + 'px');
    document.documentElement.style.setProperty('--android-vh', vh + 'px');
  }
  setRealVH();
  window.addEventListener('resize', setRealVH, { passive: true });
  window.addEventListener('orientationchange', function () {
    setTimeout(setRealVH, 200);
  }, { passive: true });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', setRealVH, { passive: true });
  }
  
  // Fix adicional para Android: actualizar después de que el teclado se cierre
  if (isAndroid) {
    window.addEventListener('focusout', function() {
      setTimeout(setRealVH, 300);
    }, { passive: true });
  }
}());
</script>

<div class="detail-content">

  <!-- SECCIÓN DE VIDEO -->
  <main class="video-section reveal" aria-label="Reproductor de video">

    <div class="video-player-wrap" id="videoPlayerWrap">

      <!-- INDICADOR DE GRABACIÓN -->
      <div class="rec-indicator" id="recIndicator" aria-live="polite">
        <div class="rec-dot"></div>
        REC <span id="recTimer">00:00</span>
      </div>

      <!-- BARRA SUPERIOR  -->
      <div class="video-top-bar">
        <span class="video-top-title">
          <?= htmlspecialchars($video['descripcion']) ?>
        </span>
        <div class="video-top-meta">
          <span><i class="fas fa-calendar"></i><?= date('d/m/Y', strtotime($video['fecha_partido'])) ?></span>
          <span><i class="fas fa-clock"></i><?= htmlspecialchars($video['hora_partido']) ?></span>
          <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($video['codigo_cancha']) ?></span>
        </div>
      </div>

      <!-- RIEL IZQUIERDO  -->
      <aside class="player-rail player-rail-left" aria-label="Controles izquierdos">

        <!-- Volver — único botón en la parte superior -->
        <a href="<?= $baseUrl ?>/index.php"
           class="rail-btn-back"
           title="Volver"
           aria-label="Volver a canchas">
          <i class="fas fa-arrow-left"></i>
        </a>

        <!-- Spacer: empuja el grupo inferior hacia abajo -->
        <div class="rail-spacer"></div>

        <!-- Pantalla completa -->
        <button class="rail-btn" id="railFullscreenBtn" title="Pantalla completa" aria-label="Pantalla completa">
          <i class="fas fa-expand"></i>
        </button>

        <!-- Velocidad -->
        <div class="speed-control">
          <button class="rail-btn" id="speedBtn" title="Velocidad" aria-label="Velocidad de reproduccion">
            <span class="speed-text">1x</span>
          </button>
          <div class="speed-menu" id="speedMenu">
            <button data-speed="0.25">0.25x</button>
            <button data-speed="0.5">0.5x</button>
            <button data-speed="0.75">0.75x</button>
            <button data-speed="1" class="active">1x</button>
            <button data-speed="1.25">1.25x</button>
            <button data-speed="1.5">1.5x</button>
            <button data-speed="2">2x</button>
          </div>
        </div>

        <!-- Retroceder 10s -->
        <button class="rail-btn" id="rewindBtn" title="Retroceder 10s" aria-label="Retroceder 10 segundos">
          <i class="fas fa-backward"></i>
        </button>

      </aside>

      <!-- RIEL DERECHO -->
      <aside class="player-rail player-rail-right" aria-label="Controles derechos">

        <!-- Spacer: ocupa todo el espacio superior -->
        <div class="rail-spacer"></div>

        <!-- Compartir — más arriba -->
        <button class="rail-btn accent"
                id="shareMainTrigger"
                title="Compartir"
                aria-label="Compartir video">
          <i class="fas fa-share-alt"></i>
        </button>

        <div class="rail-sep"></div>

        <!-- Clips grabados -->
        <button class="rail-btn"
                id="clipsBtn"
                title="Mis clips"
                aria-label="Ver clips grabados">
          <i class="fas fa-film"></i>
          <span class="badge-count" id="clipsBadge">0</span>
        </button>

        <!-- Volumen — botón fijo en el riel para reactivar sonido
             sin depender del control de abajo (que solo aparece al
             hacer hover/tap en los controles inferiores) -->
        <button class="rail-btn" id="railVolumeBtn" title="Silenciar/Activar sonido" aria-label="Silenciar o activar sonido">
          <i class="fas fa-volume-up"></i>
        </button>

        <!-- Adelantar 10s — justo encima de la barra -->
        <button class="rail-btn" id="forwardBtn" title="Adelantar 10s" aria-label="Adelantar 10 segundos">
          <i class="fas fa-forward"></i>
        </button>

      </aside>

      <!-- VIDEO -->
      <?php $videoPoster = $video['thumbnail_url'] ?? $video['portada'] ?? ''; ?>
      
      <video id="videoPlayer" class="video-player" preload="auto" autoplay muted
             <?= $videoPoster ? 'poster="' . htmlspecialchars($videoPoster) . '"' : '' ?>
             playsinline webkit-playsinline x5-playsinline x5-video-player-type="h5">
        <?php if (empty($requiresPin) || !empty($hasAccess)): ?>
          <source src="<?= htmlspecialchars($playableVideoUrl) ?>" type="video/mp4" />
        <?php else: ?>
          <!-- Fuente bloqueada hasta verificar PIN -->
          <source src="" type="video/mp4" data-src="<?= htmlspecialchars($playableVideoUrl) ?>" />
        <?php endif; ?>
        Tu navegador no soporta videos HTML5.
      </video>

      <!-- CONTROLES INFERIORES -->
      <div class="custom-controls" id="videoControls">

        <!-- FILA 1: botón de grabación centrado -->
        <div class="controls-rec-row">
          <button class="record-center-btn"
                  id="recordBtn"
                  title="Grabar clip"
                  aria-label="Grabar clip de video">
            <i class="fas fa-circle"></i>
          </button>
        </div>

        <!-- FILA 2: barra de progreso + tiempo -->
        <div class="bar-progress-container">
          <div class="bar-progress" id="Barprogress">
            <div class="bar-buffered" id="Barbuffered"></div>
            <div class="bar-filled" id="Barfilled"></div>
            <div class="bar-handle" id="Barhandle"></div>
          </div>
          <div class="time-display">
            <span id="currentTime">0:00</span>
            <span id="duration">0:00</span>
          </div>
        </div>

      </div>

     

      <!-- POSTER OVERLAY — muestra thumbnail (o placeholder) + botón play
           mientras el video carga el primer frame. Evita pantalla negra
           en iOS, Android y cualquier dispositivo con carga lenta. -->
      <div class="ios-poster-overlay" id="iosPosterOverlay">
        <?php if ($videoPoster): ?>
        <img src="<?= htmlspecialchars($videoPoster) ?>" alt="" class="ios-poster-img" />
        <?php else: ?>
        <!-- Sin thumbnail: placeholder elegante -->
        <div class="poster-placeholder" id="posterPlaceholder">
          <div class="poster-placeholder-icon">
            <i class="fas fa-video"></i>
          </div>
          <div class="poster-placeholder-text">Cargando video…</div>
          <div class="poster-placeholder-dots">
            <span></span><span></span><span></span>
          </div>
        </div>
        <?php endif; ?>
        <button class="ios-poster-play-btn" id="iosPosterPlayBtn" aria-label="Reproducir video"
                <?php echo !$videoPoster ? 'style="display:none"' : ''; ?>>
          <i class="fas fa-play"></i>
        </button>
      </div>

      <!-- LOADING -->
      <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
      </div>

    </div><!-- /.video-player-wrap -->
  </main>

</div><!-- /.detail-content -->


<!-- MODAL COMPARTIR -->
<div id="mobileShareModal" class="mobile-share-modal" aria-hidden="true"
     role="dialog" aria-modal="true" aria-label="Compartir video">
  <div class="mobile-share-backdrop" data-action="close" aria-label="Cerrar modal"></div>
  <div class="mobile-share-content">
    <button class="mobile-share-close" data-action="close" aria-label="Cerrar">×</button>
    <h3>Compartir</h3>
    <div class="mobile-share-grid">

      <a class="share-icon"
         href="https://api.whatsapp.com/send?text=<?= urlencode('Mira este video: ' . ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $baseUrl . '/video/' . $codigoVideo) ?>"
         target="_blank" rel="noopener">
        <div class="share-icon-circle si-whatsapp"><i class="fab fa-whatsapp"></i></div>
        <span>WhatsApp</span>
      </a>

      <a class="share-icon"
         href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $baseUrl . '/video/' . $codigoVideo) ?>"
         target="_blank" rel="noopener">
        <div class="share-icon-circle si-facebook"><i class="fab fa-facebook-f"></i></div>
        <span>Facebook</span>
      </a>

      <a class="share-icon"
         href="https://twitter.com/intent/tweet?url=<?= urlencode(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $baseUrl . '/video/' . $codigoVideo) ?>"
         target="_blank" rel="noopener">
        <div class="share-icon-circle si-twitter"><i class="fab fa-twitter"></i></div>
        <span>Twitter</span>
      </a>

      <a class="share-icon" href="#" id="shareDownloadBtn">
        <div class="share-icon-circle si-download"><i class="fas fa-download"></i></div>
        <span>Descargar</span>
      </a>

    </div>
  </div>
</div>


<!-- ══ MODAL CLIPS ══ -->
<div id="clipsModal" class="clips-modal" aria-hidden="true"
     role="dialog" aria-modal="true" aria-label="Mis clips grabados">
  <div class="clips-modal-backdrop" data-action="close-clips" aria-label="Cerrar modal"></div>
  <div class="clips-modal-content">

    <div class="clips-modal-header">
      <h3>
        <i class="fas fa-film" style="color:var(--accent);font-size:14px;"></i>
        Mis clips
        <span id="clipsCountLabel">0</span>
      </h3>
      <button class="clips-modal-close" data-action="close-clips" aria-label="Cerrar">×</button>
    </div>

    <div class="clips-list" id="clipsList"></div>

    <div class="clips-modal-footer">
      <button class="clips-footer-btn" id="downloadAllClipsBtn">
        <i class="fas fa-download"></i> Descargar todos
      </button>
      <button class="clips-footer-btn primary" id="shareAllClipsBtn">
        <i class="fas fa-share-alt"></i> Compartir todos
      </button>
    </div>

  </div>
</div>


<!-- ══ MODAL PROCESAMIENTO CLIP ══ -->
<div id="clipProcessingModal" class="clip-proc-modal" aria-hidden="true"
     role="dialog" aria-modal="true" aria-label="Procesando clip">
  <div class="clip-proc-backdrop"></div>
  <div class="clip-proc-content">
    <div class="clip-proc-icon-wrap" id="clipProcIconWrap">
      <div class="spinner"></div>
    </div>
    <div class="clip-proc-title" id="clipProcTitle">Procesando video…</div>
    <div class="clip-proc-sub" id="clipProcSub">Esto puede tomar unos segundos</div>
    <div class="clip-proc-bar-wrap">
      <div class="clip-proc-bar-fill" id="clipProcBarFill"></div>
    </div>
  </div>
</div>


<script>
(function(){
  'use strict';

  // ── Helpers para abrir/cerrar modales de forma cross-browser (iOS safe) ──
  function openModal(id){
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.add('open');
    m.setAttribute('aria-hidden', 'false');
    // Bloquear scroll del body en iOS
    document.body.style.overflow = 'hidden';
    document.body.style.touchAction = 'none';
  }
  function closeModal(id){
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('open');
    m.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    document.body.style.touchAction = '';
  }

  // Exponer helpers para video-player.js
  window.POMPLAY_modal = { open: openModal, close: closeModal };

  // Botón descargar en modal compartir → activa la descarga directa del video
  var shareDownBtn = document.getElementById('shareDownloadBtn');
  if (shareDownBtn) {
    shareDownBtn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      closeModal('mobileShareModal');
      document.getElementById('downloadFullBtn')?.click();
    });
  }

  // Cerrar modal share al tocar backdrop o X
  document.querySelectorAll('#mobileShareModal [data-action="close"]').forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      closeModal('mobileShareModal');
    });
  });

  // Cerrar modal clips al tocar backdrop o X
  document.querySelectorAll('#clipsModal [data-action="close-clips"]').forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      closeModal('clipsModal');
    });
  });

  // Click en el botón compartir del riel
  var shareTrigger = document.getElementById('shareMainTrigger');
  if (shareTrigger) {
    shareTrigger.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      openModal('mobileShareModal');
    });
  }

  // Click en el botón clips
  var clipsTrigger = document.getElementById('clipsBtn');
  if (clipsTrigger) {
    clipsTrigger.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      openModal('clipsModal');
      if (typeof renderClipsList === 'function') renderClipsList();
    });
  }

  // ESC para cerrar
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      closeModal('mobileShareModal');
      closeModal('clipsModal');
    }
  });
})();
</script>

<?php elseif ($error): ?>

<div class="error-container">
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <h3>Video no encontrado</h3>
    <p><?= htmlspecialchars($error) ?></p>
    <a href="<?= $baseUrl ?>/index.php" class="rail-btn-back" style="
      display:inline-flex;align-items:center;gap:8px;
      padding:10px 18px;border-radius:8px;text-decoration:none;font-size:13px;">
      <i class="fas fa-arrow-left"></i> Volver al inicio
    </a>
  </div>
</div>

<?php endif; ?>

<script>window.POMPLAY_BASE = '<?= $baseUrl ?>';</script>
<?php if (!empty($video)): ?>
<script>
window.POMPLAY_VIDEO = {
  codigo: <?= json_encode($codigoVideo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  id_local: <?= !empty($video['id_local']) ? (int) $video['id_local'] : 'null' ?>,
  codigo_cancha: <?= json_encode($video['codigo_cancha'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
};
</script>
<?php endif; ?>
<script>
// Pasar cámaras disponibles al JavaScript
window.VIDEO_CAMERAS = <?= json_encode($cameras ?? []) ?>;
</script>
<!-- Detectar Android para aplicar estilos sólo en ese OS -->
<script src="<?= $baseUrl ?>/public/js/platform-detect.js?v=2.0"></script>
<script src="<?= $baseUrl ?>/public/js/video-player.js?v=16.1"></script>
<?php if (!empty($requiresPin) && empty($hasAccess)): ?>
<script src="<?= $baseUrl ?>/public/js/video-pin.js?v=1.0"></script>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
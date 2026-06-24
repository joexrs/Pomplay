<?php require __DIR__ . '/../layouts/header.php'; ?>

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


<style>
  html, body {
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    background: #000 !important;
    -webkit-text-size-adjust: 100%;
    overscroll-behavior: none;
  }
  .container, .container-fluid, .container-xl, .container-lg,
  .wrapper, .page-wrapper, .main-wrapper, .content-wrapper,
  .site-wrapper, .layout-wrapper, .main-content, .page-content,
  #wrapper, #container, #page, #main, #content, #app, #root,
  [class*="container"], [class*="wrapper"] {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  header, nav, footer, .site-nav, .site-footer,
  .page-header, .page-header-detail, .navbar, .topbar {
    display: none !important;
  }
  .detail-content {
    position: fixed !important;
    inset: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    height: 100dvh !important;
    max-width: 100vw !important;
    z-index: 9000 !important;
    background: #000 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    -webkit-overflow-scrolling: touch;
  }
</style>

<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-player.css?v=4.0" />
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-detail-responsive.css?v=3.0" />

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

        <!-- Volver -->
        <a href="<?= $baseUrl ?>/index.php"
           class="rail-btn-back"
           title="Volver"
           aria-label="Volver a canchas">
          <i class="fas fa-arrow-left"></i>
        </a>

        <!-- Retroceder 10s -->
        <button class="rail-btn" id="rewindBtn" title="Retroceder 10s" aria-label="Retroceder 10 segundos">
          <i class="fas fa-undo"></i>
        </button>

      </aside>

      <!-- RIEL DERECHO -->
      <aside class="player-rail player-rail-right" aria-label="Controles derechos">

        <!-- Adelantar 10s -->
        <button class="rail-btn" id="forwardBtn" title="Adelantar 10s" aria-label="Adelantar 10 segundos">
          <i class="fas fa-redo"></i>
        </button>

        <!-- Pantalla completa -->
        <button class="rail-btn" id="railFullscreenBtn" title="Pantalla completa" aria-label="Pantalla completa">
          <i class="fas fa-expand"></i>
        </button>

        <!-- Clips grabados -->
        <button class="rail-btn"
                id="clipsBtn"
                title="Mis clips"
                aria-label="Ver clips grabados">
          <i class="fas fa-film"></i>
          <span class="badge-count" id="clipsBadge">0</span>
        </button>

        <div class="rail-sep"></div>

        <!-- Compartir -->
        <button class="rail-btn accent"
                id="shareMainTrigger"
                title="Compartir"
                aria-label="Compartir video">
          <i class="fas fa-share-alt"></i>
        </button>

    

        <!-- Velocidad -->
        <div class="speed-control">
          <button class="rail-btn" id="speedBtn" title="Velocidad" aria-label="Velocidad de reproducción">
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

        <div class="rail-spacer"></div>

      </aside>

      <!-- VIDEO -->
      <video id="videoPlayer" class="video-player" preload="metadata" crossorigin="anonymous" playsinline webkit-playsinline>
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
        <div class="progress-bar-container">
          <div class="progress-bar" id="progressBar">
            <div class="progress-filled" id="progressFilled"></div>
          </div>
          <div class="time-display">
            <span id="currentTime">0:00</span>
            <span id="duration">0:00</span>
          </div>
        </div>

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
<script>
// Pasar cámaras disponibles al JavaScript
window.VIDEO_CAMERAS = <?= json_encode($cameras ?? []) ?>;
</script>
<script src="<?= $baseUrl ?>/public/js/video-player.js?v=4.0"></script>
<?php if (!empty($requiresPin) && empty($hasAccess)): ?>
<script src="<?= $baseUrl ?>/public/js/video-pin.js?v=1.0"></script>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
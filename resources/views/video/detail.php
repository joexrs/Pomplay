<?php require __DIR__ . '/../layouts/header.php'; ?>

<?php if ($video): ?>
<?php $playableVideoUrl = $videoUrl ?? ($video['video_url'] ?? ''); ?>

<!-- Escape del layout padre — debe ir ANTES de cualquier otro CSS -->
<style>
  html, body {
    overflow: hidden !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    background: #000 !important;
  }
  /* Anular contenedores típicos de layouts PHP/Bootstrap/Tailwind */
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
    height: 100dvh !important;
    max-width: 100vw !important;
    z-index: 9000 !important;
    background: #000 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
  }
</style>

<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-player.css?v=2.0" />
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/video-detail-responsive.css?v=2.0" />

<div class="detail-content">

  <!-- ══ SECCIÓN DE VIDEO — ocupa toda la pantalla ══ -->
  <main class="video-section reveal" aria-label="Reproductor de video">

    <div class="video-player-wrap" id="videoPlayerWrap">

      <!-- INDICADOR DE GRABACIÓN (flota centrado arriba) -->
      <div class="rec-indicator" id="recIndicator" aria-live="polite">
        <div class="rec-dot"></div>
        REC <span id="recTimer">00:00</span>
      </div>

      <!-- BARRA SUPERIOR (título + metadatos) -->
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

      <!-- RIEL IZQUIERDO — overlay sobre el video -->
      <aside class="player-rail player-rail-left" aria-label="Controles izquierdos">

        <!-- Volver -->
        <a href="<?= $baseUrl ?>/index.php"
           class="rail-btn-back"
           title="Volver"
           aria-label="Volver a canchas">
          <i class="fas fa-arrow-left"></i>
        </a>

        <div class="rail-spacer"></div>

        <!-- Zoom -->
        <button class="rail-btn" id="zoomOutBtn" title="Reducir zoom" aria-label="Reducir zoom">
          <i class="fas fa-search-minus"></i>
        </button>
        <span class="zoom-level-label" id="zoomLevel">100%</span>
        <button class="rail-btn" id="zoomInBtn" title="Aumentar zoom" aria-label="Aumentar zoom">
          <i class="fas fa-search-plus"></i>
        </button>

        <div class="rail-spacer"></div>

      </aside>

      <!-- RIEL DERECHO — overlay sobre el video -->
      <aside class="player-rail player-rail-right" aria-label="Controles derechos">

        <div class="rail-spacer"></div>

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

        <!-- Descargar con marca de agua -->
        <button class="rail-btn"
                id="downloadFullBtn"
                title="Descargar video"
                aria-label="Descargar video completo con marca de agua">
          <i class="fas fa-download"></i>
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
      <video id="videoPlayer" class="video-player" preload="metadata" crossorigin="anonymous">
        <source src="<?= htmlspecialchars($playableVideoUrl) ?>" type="video/mp4" />
        Tu navegador no soporta videos HTML5.
      </video>

      <!-- CONTROLES INFERIORES -->
      <div class="custom-controls" id="videoControls">
        <div class="progress-bar-container">
          <div class="progress-bar" id="progressBar">
            <div class="progress-filled" id="progressFilled"></div>
          </div>
          <div class="time-display">
            <span id="currentTime">0:00</span>
            <span id="duration">0:00</span>
          </div>
        </div>

        <div class="controls-row">
          <!-- BOTÓN GRABAR — centro inferior -->
          <button class="record-center-btn"
                  id="recordBtn"
                  title="Grabar clip"
                  aria-label="Grabar clip de video">
            <i class="fas fa-circle"></i>
          </button>

          <div class="controls-left">
            <button class="control-btn" id="playPauseBtn" title="Reproducir/Pausar" aria-label="Reproducir">
              <i class="fas fa-play"></i>
            </button>
            <button class="control-btn" id="rewindBtn" title="Retroceder 10s" aria-label="Retroceder 10 segundos">
              <i class="fas fa-backward"></i>
            </button>
            <button class="control-btn" id="forwardBtn" title="Adelantar 10s" aria-label="Adelantar 10 segundos">
              <i class="fas fa-forward"></i>
            </button>
            <div class="volume-control">
              <button class="control-btn" id="volumeBtn" aria-label="Silenciar">
                <i class="fas fa-volume-up"></i>
              </button>
              <input type="range" id="volumeSlider" min="0" max="100" value="100"
                     class="volume-slider" aria-label="Volumen">
            </div>
          </div>
          <div class="controls-right">
            <button class="control-btn" id="pipBtn" title="Picture-in-Picture" aria-label="Ventana flotante">
              <i class="fas fa-external-link-alt"></i>
            </button>
            <button class="control-btn" id="fullscreenBtn" title="Pantalla completa" aria-label="Pantalla completa">
              <i class="fas fa-expand"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- PROGRESS MARCA DE AGUA -->
      <div class="watermark-progress" id="wmProgress">
        <div class="wm-bar-wrap">
          <div class="wm-bar-fill" id="wmBarFill"></div>
        </div>
        <span class="wm-label" id="wmProgressLabel">Procesando…</span>
      </div>

      <!-- LOADING -->
      <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
      </div>

    </div><!-- /.video-player-wrap -->
  </main>

</div><!-- /.detail-content -->


<!-- ══ MODAL COMPARTIR ══ -->
<div id="mobileShareModal" class="mobile-share-modal" aria-hidden="true"
     role="dialog" aria-modal="true" aria-label="Compartir video">
  <div class="mobile-share-backdrop" data-action="close"></div>
  <div class="mobile-share-content">
    <button class="mobile-share-close" data-action="close" aria-label="Cerrar">×</button>
    <h3>Compartir</h3>
    <div class="mobile-share-grid">

      <a class="share-icon"
         href="https://api.whatsapp.com/send?text=<?= urlencode('Mira este video: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $baseUrl . '/video/' . $codigoVideo) ?>"
         target="_blank" rel="noopener">
        <div class="share-icon-circle si-whatsapp"><i class="fab fa-whatsapp"></i></div>
        <span>WhatsApp</span>
      </a>

      <a class="share-icon"
         href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $baseUrl . '/video/' . $codigoVideo) ?>"
         target="_blank" rel="noopener">
        <div class="share-icon-circle si-facebook"><i class="fab fa-facebook-f"></i></div>
        <span>Facebook</span>
      </a>

      <a class="share-icon"
         href="https://twitter.com/intent/tweet?url=<?= urlencode($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $baseUrl . '/video/' . $codigoVideo) ?>"
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
  <div class="clips-modal-backdrop"></div>
  <div class="clips-modal-content">

    <div class="clips-modal-header">
      <h3>
        <i class="fas fa-film" style="color:var(--accent);font-size:14px;"></i>
        Mis clips
        <span id="clipsCountLabel">0</span>
      </h3>
      <button class="clips-modal-close" aria-label="Cerrar">×</button>
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


<script>
(function(){
  'use strict';
  // Botón descargar en modal compartir → redirige a descarga con watermark
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
<script src="<?= $baseUrl ?>/public/js/video-player.js?v=2.7"></script>
<script src="<?= $baseUrl ?>/public/js/shared-modal.js?v=1.0"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
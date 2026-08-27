<?php
// ═══════════════════════════════════════════════════════════════
//  videos.php — Panel de Grabaciones del Propietario
// ═══════════════════════════════════════════════════════════════
session_start();
include __DIR__ . '/../conexion.php';

$userId        = $_SESSION['user_id']        ?? null;
$propietarioId = $_SESSION['id_propietario'] ?? null;
$localId       = $_SESSION['id_local']       ?? null;

if (!$userId || !$propietarioId) {
    header('Location: ../login.php');
    exit;
}

// ── Locales del propietario ──────────────────────────────────────
try {
    $stmtLocales = $pdo->prepare("CALL GetOwnerLocalList(:id_propietario)");
    $stmtLocales->execute([':id_propietario' => $propietarioId]);
    $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
    $stmtLocales->closeCursor();
} catch (PDOException $e) {
    die('Error al obtener locales: ' . $e->getMessage());
}

if (!$localId && !empty($locales)) {
    $localId = $locales[0]['id_local'];
    $_SESSION['id_local'] = $localId;
}
if (!$localId) die('No tienes locales asignados');

// ── Verificar si el local es privado ─────────────────────────────
$esLocalPrivado = false;
try {
    $stmtPriv = $pdo->prepare("SELECT es_privado FROM locales WHERE id_local = :id_local LIMIT 1");
    $stmtPriv->execute([':id_local' => $localId]);
    $localPriv = $stmtPriv->fetch(PDO::FETCH_ASSOC);
    $esLocalPrivado = (bool) ($localPriv['es_privado'] ?? false);
} catch (PDOException) {
    $esLocalPrivado = false;
}

// ── Cambiar local ────────────────────────────────────────────────
if (!empty($_GET['change_local'])) {
    $newId = (int)$_GET['change_local'];
    foreach ($locales as $loc) {
        if ($loc['id_local'] == $newId) {
            $_SESSION['id_local'] = $newId;
            header('Location: videos.php');
            exit;
        }
    }
}

// ── Filtros ──────────────────────────────────────────────────────
$filtroFecha  = !empty($_GET['fecha'])  ? $_GET['fecha']  : null;
$filtroCancha = !empty($_GET['cancha']) ? $_GET['cancha'] : null;
$paginaActual = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina    = 20;
$offset       = ($paginaActual - 1) * $porPagina;

// ── Canchas del local (para filtro) ─────────────────────────────
try {
    $stmtCanchas = $pdo->prepare("CALL GetCanchasByLocal(:id_local)");
    $stmtCanchas->execute([':id_local' => $localId]);
    $canchas = $stmtCanchas->fetchAll(PDO::FETCH_ASSOC);
    $stmtCanchas->closeCursor();
} catch (PDOException) {
    $canchas = [];
}

// ── Videos con paginación ────────────────────────────────────────
try {
    $stmtV = $pdo->prepare("CALL GetOwnerVideosFiltered(:id_local, :fecha, :codigo_cancha)");
    $stmtV->execute([
        ':id_local'      => $localId,
        ':fecha'         => $filtroFecha,
        ':codigo_cancha' => $filtroCancha,
    ]);
    $todosVideos  = $stmtV->fetchAll(PDO::FETCH_ASSOC);
    $stmtV->closeCursor();

    $todosVideos = array_values(array_filter(
        $todosVideos,
        static fn(array $v): bool => (int)($v['estado'] ?? 0) === 1
    ));

    $totalVideos  = count($todosVideos);
    $totalPaginas = $totalVideos > 0 ? (int)ceil($totalVideos / $porPagina) : 1;
    $videos       = array_slice($todosVideos, $offset, $porPagina);
} catch (PDOException) {
    $videos = $todosVideos = [];
    $totalVideos  = 0;
    $totalPaginas = 1;
}

// ── Helper: formatear duración en segundos ───────────────────────
function formatDuracion(int $seg): string {
    if ($seg <= 0) return '—';
    $h = intdiv($seg, 3600);
    $m = intdiv($seg % 3600, 60);
    $s = $seg % 60;
    if ($h > 0) return sprintf('%dh %02dm %02ds', $h, $m, $s);
    if ($m > 0) return sprintf('%dm %02ds', $m, $s);
    return sprintf('%ds', $s);
}

$meses = [
    'January' => 'enero', 'February' => 'febrero', 'March'     => 'marzo',
    'April'   => 'abril', 'May'      => 'mayo',    'June'      => 'junio',
    'July'    => 'julio', 'August'   => 'agosto',  'September' => 'septiembre',
    'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
];
?>
<?php include __DIR__ . '/../admin/shared/header_admin.php'; ?>
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/modals.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/tables.css">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/owner-videos.css">




<main class="app-content">

  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Mis Grabaciones</h1>
      <p class="page-subtitle">Historial de videos grabados por tus cámaras</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item"><a href="canchas.php" style="color:inherit;">Canchas</a></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Videos</span>
      </div>
    </div>
  </div>

  <!-- Selector de local (reutiliza .adm-form-card + .local-selector-* de owner-videos.css) -->
  <?php if (count($locales) > 1): ?>
  <div class="adm-form-card local-selector-card">
    <div class="local-selector-wrapper">
      <div class="local-selector-field">
        <label class="local-selector-label">
          <i class="bi bi-shop" style="margin-right:5px;"></i>Local activo
        </label>
        <select class="form-control local-selector-select"
                onchange="window.location.href='videos.php?change_local='+this.value">
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>"
              <?= $localId == $loc['id_local'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filtros (reutiliza .adm-form-card + .filters-card + clases de owner-videos.css) -->
  <div class="adm-form-card filters-card">
    <form method="GET" class="filters-form">
      <div class="filter-field">
        <label class="filter-label">Fecha</label>
        <input type="date" name="fecha" class="form-control filter-input"
               value="<?= htmlspecialchars($filtroFecha ?? '') ?>" />
      </div>
      <div class="filter-field">
        <label class="filter-label">Cancha</label>
        <select name="cancha" class="form-control filter-select">
          <option value="">Todas las canchas</option>
          <?php foreach ($canchas as $c): ?>
            <option value="<?= htmlspecialchars($c['codigo_cancha']) ?>"
              <?= $filtroCancha === $c['codigo_cancha'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['descripcion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn-filter">
          <i class="bi bi-funnel-fill"></i> Filtrar
        </button>
        <a href="videos.php" class="btn-filter-clear">
          <i class="bi bi-x-lg"></i> Limpiar
        </a>
      </div>
    </form>
  </div>

  <!-- Stats -->
  <div class="stats-bar">
    <p class="stats-text">
      <strong><?= $totalVideos ?></strong> grabaciones encontradas
      <?php if ($filtroFecha || $filtroCancha): ?>
        &nbsp;·&nbsp;
        <?php if ($filtroFecha): ?>
          Fecha: <strong><?= htmlspecialchars(date('d/m/Y', strtotime($filtroFecha))) ?></strong>
        <?php endif; ?>
        <?php if ($filtroCancha): ?>
          &nbsp;Cancha: <strong><?= htmlspecialchars($filtroCancha) ?></strong>
        <?php endif; ?>
      <?php endif; ?>
    </p>
    <a href="canchas.php" class="btn-filter-clear" style="font-size:0.82rem;padding:7px 13px;">
      <i class="bi bi-camera-video"></i> Ver cámaras en vivo
    </a>
  </div>

  <!-- Tabla (reutiliza .adm-table + .table-actions + .table-btn-icon + .td-id + .badge-active de admin-modern-v2.css) -->
  <div class="videos-table-wrap">
    <?php if (empty($videos)): ?>
      <!-- Reutiliza .videos-empty-state, .videos-empty-icon, .videos-empty-text de owner-videos.css -->
      <div class="videos-empty-state">
        <div class="videos-empty-icon"><i class="bi bi-play-circle"></i></div>
        <p class="videos-empty-text">
          No se encontraron grabaciones<?= ($filtroFecha || $filtroCancha) ? ' con los filtros aplicados' : '' ?>.
        </p>
        <?php if ($filtroFecha || $filtroCancha): ?>
          <a href="videos.php" class="btn-filter-clear" style="margin-top:12px;">
            <i class="bi bi-x-lg"></i> Limpiar filtros
          </a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- .adm-table de admin-modern-v2.css -->
      <table class="adm-table">
        <thead>
          <tr>
            <th>Código</th>
            <th>Descripción</th>
            <th>Cancha</th>
            <th>Fecha</th>
            <th class="th-duracion">Duración</th>
            <th style="text-align:right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($videos as $video):
            $fd  = str_replace(array_keys($meses), array_values($meses), date('d M Y', strtotime($video['fecha_partido'])));
            $dur = (int)($video['duracion'] ?? 0);
            $esDescargable = (bool)($video['es_descargable'] ?? false);
            $videoUrl = $video['video_url'] ?? '';
            $descr    = $video['descripcion'] ?? '—';
            $codCancha = $video['cancha_nombre'] ?? $video['codigo_cancha'] ?? '—';
            $hora = substr($video['hora_partido'] ?? '', 0, 5);
          ?>
          <tr>
            <!-- .td-id de admin-modern-v2.css -->
            <td><span class="td-id"><?= htmlspecialchars($video['codigo_video']) ?></span></td>

            <td>
              <div class="td-desc" title="<?= htmlspecialchars($descr) ?>">
                <?= htmlspecialchars($descr) ?>
              </div>
              <?php if ($hora): ?>
                <div style="font-size:0.75rem;color:var(--color-text-muted);margin-top:3px;">
                  <i class="bi bi-clock" style="font-size:0.7rem;"></i> <?= $hora ?>
                </div>
              <?php endif; ?>
            </td>

            <!-- .badge-active de admin-modern-v2.css -->
            <td><span class="badge-active"><?= htmlspecialchars($codCancha) ?></span></td>

            <td class="td-fecha"><?= $fd ?></td>

            <td class="td-duracion">
              <span class="dur-badge">
                <i class="bi bi-stopwatch"></i>
                <?= formatDuracion($dur) ?>
              </span>
            </td>

            <td>
              <!-- .table-actions + .table-btn-icon de tables.css -->
              <div class="table-actions" style="justify-content:flex-end;">
                <button class="table-btn-icon btn-vid-play"
                  title="Reproducir"
                  data-vid-url="<?= htmlspecialchars($videoUrl, ENT_QUOTES) ?>"
                  data-vid-titulo="<?= htmlspecialchars($descr, ENT_QUOTES) ?>"
                  data-vid-cancha="<?= htmlspecialchars($codCancha, ENT_QUOTES) ?>"
                  data-vid-dur="<?= (int)$dur ?>"
                  data-vid-fecha="<?= htmlspecialchars($fd . ($hora ? ' · ' . $hora : ''), ENT_QUOTES) ?>">
                  <i class="bi bi-play-fill"></i>
                </button>
                <?php if ($esDescargable && $videoUrl): ?>
                <a class="table-btn-icon btn-vid-dl"
                   href="<?= htmlspecialchars($videoUrl) ?>"
                   download="<?= htmlspecialchars($video['codigo_video']) ?>.mp4"
                   target="_blank"
                   title="Descargar video">
                  <i class="bi bi-download"></i>
                </a>
                <?php endif; ?>
                <?php if ($esLocalPrivado): ?>
                <button class="table-btn-icon btn-vid-pin"
                        title="Generar código de acceso"
                        data-pin-generate="<?= htmlspecialchars($video['codigo_video']) ?>">
                  <i class="bi bi-key-fill"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Paginación -->
      <?php if ($totalPaginas > 1): ?>
      <div class="pagination-bar">
        <a class="btn-page <?= $paginaActual <= 1 ? 'disabled' : '' ?>"
           href="?pagina=<?= $paginaActual - 1 ?>&fecha=<?= urlencode($filtroFecha ?? '') ?>&cancha=<?= urlencode($filtroCancha ?? '') ?>">
          <i class="bi bi-chevron-left"></i>
        </a>
        <?php
        for ($p = max(1, $paginaActual - 2); $p <= min($totalPaginas, $paginaActual + 2); $p++):
        ?>
          <a class="btn-page <?= $p === $paginaActual ? 'active' : '' ?>"
             href="?pagina=<?= $p ?>&fecha=<?= urlencode($filtroFecha ?? '') ?>&cancha=<?= urlencode($filtroCancha ?? '') ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>
        <a class="btn-page <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>"
           href="?pagina=<?= $paginaActual + 1 ?>&fecha=<?= urlencode($filtroFecha ?? '') ?>&cancha=<?= urlencode($filtroCancha ?? '') ?>">
          <i class="bi bi-chevron-right"></i>
        </a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</main>

<!-- ═══ MODAL DE REPRODUCCIÓN ════════════════════════════════════════
     Usa .modal-overlay + .modal-container de modals.css
     Solo override: max-width y padding=0 (vid-modal-wrap)
════════════════════════════════════════════════════════════════════ -->
<div id="vidModal" class="modal-overlay" onclick="if(event.target===this)cerrarVideo()">
  <div class="modal-container vid-modal-wrap">

    <!-- .modal-header de modals.css -->
    <div class="modal-header" style="padding:14px 20px;margin-bottom:0;">
      <h3 class="modal-title" style="font-size:1rem;">
        <i class="bi bi-play-circle"></i>
        <span id="vid-modal-title-text">Reproducción</span>
      </h3>
      <!-- .modal-close de modals.css -->
      <button class="modal-close" onclick="cerrarVideo()" title="Cerrar">
        <i class="bi bi-x-lg" style="font-size:1rem;"></i>
      </button>
    </div>

    <!-- Cuerpo del modal: video sin padding -->
    <div class="vid-modal-body-inner">
      <video id="vid-player" controls preload="metadata" playsinline>
        <source id="vid-player-src" src="" type="video/mp4">
        Tu navegador no soporta HTML5 video.
      </video>
      <!-- Metadatos (solo en este contexto) -->
      <div class="vid-modal-meta" id="vid-modal-meta"></div>
    </div>

  </div>
</div>

<?php if ($esLocalPrivado): ?>
<!-- ═══ MODAL GENERAR PIN ═══════════════════════════════════════ -->
<div id="pinGenerateModal" class="modal-overlay" style="z-index:10000;">
  <div class="modal-container" style="max-width:460px;">
    <div class="modal-header" style="padding:18px 24px;margin-bottom:0;">
      <h3 class="modal-title" style="font-size:1rem;">
        <i class="bi bi-key-fill" style="color:var(--color-brand);"></i>
        Generar Código de Acceso
      </h3>
      <button class="modal-close" data-action="close" title="Cerrar">
        <i class="bi bi-x-lg" style="font-size:1rem;"></i>
      </button>
    </div>

    <div style="padding:20px 24px;">
      <input type="hidden" id="pinGenVideoCode" value="">

      <p style="color:var(--color-text-muted);font-size:0.85rem;margin:0 0 20px;line-height:1.5;">
        Genera un código temporal para compartir con el usuario que necesita ver este video.
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div>
          <label class="adm-label" for="pinGenLongitud" style="font-size:0.8rem;">Dígitos</label>
          <select id="pinGenLongitud" class="form-control" style="padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#f0f0f5;font-size:0.85rem;width:100%;">
            <option value="4">4 dígitos</option>
            <option value="6" selected>6 dígitos</option>
          </select>
        </div>
        <div>
          <label class="adm-label" for="pinGenMinutos" style="font-size:0.8rem;">Duración</label>
          <select id="pinGenMinutos" class="form-control" style="padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:#f0f0f5;font-size:0.85rem;width:100%;">
            <option value="15">15 minutos</option>
            <option value="30" selected>30 minutos</option>
            <option value="60">1 hora</option>
            <option value="120">2 horas</option>
          </select>
        </div>
      </div>

      <button id="pinGenBtn" class="btn-filter" style="width:100%;justify-content:center;padding:12px 20px;font-size:0.9rem;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;color:#fff;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;">
        <span class="btn-text"><i class="bi bi-key-fill"></i> Generar código</span>
        <div class="spinner-sm" style="display:none;width:18px;height:18px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;"></div>
      </button>

      <!-- Resultado -->
      <div id="pinGenResult" style="display:none;margin-top:20px;">
        <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:14px;padding:20px;text-align:center;">
          <p style="color:rgba(255,255,255,0.5);font-size:0.75rem;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.05em;">Código de acceso</p>
          <div id="pinGenDisplay" style="font-size:2.2rem;font-weight:800;color:#f0f0f5;letter-spacing:0.2em;font-family:'Courier New',monospace;margin-bottom:8px;"></div>
          <p id="pinGenExpiry" style="color:rgba(255,255,255,0.4);font-size:0.78rem;margin:0 0 14px;"></p>
          <button id="pinGenCopy" type="button" style="padding:8px 18px;border-radius:8px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);color:#a5b4fc;font-size:0.82rem;cursor:pointer;transition:all .2s ease;display:inline-flex;align-items:center;gap:6px;">
            <i class="bi bi-clipboard"></i> Copiar código
          </button>
        </div>
      </div>

      <!-- Status message -->
      <div id="pinGenStatus" class="pin-gen-status" style="margin-top:14px;font-size:0.82rem;padding:0;border-radius:8px;"></div>
    </div>
  </div>
</div>

<style>
@keyframes spin { to { transform:rotate(360deg); } }
.btn-vid-pin {
  background: rgba(99,102,241,0.12) !important;
  color: #818cf8 !important;
  border: 1px solid rgba(99,102,241,0.2) !important;
}
.btn-vid-pin:hover {
  background: rgba(99,102,241,0.2) !important;
  transform: translateY(-1px);
}
.pin-gen-status { line-height:1.4; }
.pin-gen-status.success { color:#86efac; }
.pin-gen-status.error { color:#fca5a5; }
.pin-gen-status.info { color:#a5b4fc; }
.pin-gen-status i { margin-right:6px; }
#pinGenCopy.copied { background:rgba(34,197,94,0.15) !important; color:#86efac !important; border-color:rgba(34,197,94,0.3) !important; }
</style>
<?php endif; ?>

<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>

<script>window.POMPLAY_BASE = '<?= $baseUrl ?>';</script>
<script>
/* ── Video Modal ───────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {

  // Delegated click: un solo listener para todos los botones de reproducción
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-vid-play');
    if (!btn) return;
    e.preventDefault();
    const url     = btn.dataset.vidUrl    || '';
    const titulo  = btn.dataset.vidTitulo || 'Grabación';
    const cancha  = btn.dataset.vidCancha || '';
    const duracion = parseInt(btn.dataset.vidDur, 10) || 0;
    const fecha   = btn.dataset.vidFecha  || '';
    abrirVideoModal(url, titulo, cancha, duracion, fecha);
  });

  // Cerrar al hacer click en el overlay (fuera del contenedor)
  const overlay = document.getElementById('vidModal');
  if (overlay) {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) cerrarVideo();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') cerrarVideo();
  });
});

function abrirVideoModal(url, titulo, cancha, duracion, fecha) {
  const modal  = document.getElementById('vidModal');
  const player = document.getElementById('vid-player');
  const source = document.getElementById('vid-player-src');
  const meta   = document.getElementById('vid-modal-meta');

  if (!modal || !player || !source) {
    console.error('vidModal: elementos no encontrados en el DOM');
    return;
  }

  document.getElementById('vid-modal-title-text').textContent = titulo;

  // Cargar el video
  source.src = url;
  player.load();

  // Metadatos
  meta.innerHTML = [
    fecha  ? `<div class="vid-modal-meta-item"><i class="bi bi-calendar3"></i> ${fecha}</div>`   : '',
    cancha ? `<div class="vid-modal-meta-item"><i class="bi bi-geo-alt"></i> ${cancha}</div>`    : '',
    `<div class="vid-modal-meta-item"><i class="bi bi-stopwatch"></i> ${formatDurJS(duracion)}</div>`,
  ].join('');

  // Mostrar modal
  modal.style.display = 'flex';
  // Forzar reflow para que la transición CSS arranque
  modal.offsetHeight;
  modal.classList.add('active');

  // Intentar reproducir (puede fallar por política del navegador, está controlado)
  player.play().catch(() => {});
}

function cerrarVideo() {
  const modal  = document.getElementById('vidModal');
  const player = document.getElementById('vid-player');
  const source = document.getElementById('vid-player-src');
  if (!modal) return;
  player.pause();
  source.src = '';
  player.load();
  modal.classList.remove('active');
  // Ocultar tras la animación de salida
  setTimeout(() => { modal.style.display = 'none'; }, 310);
}

function formatDurJS(seg) {
  if (!seg || seg <= 0) return '—';
  const h = Math.floor(seg / 3600);
  const m = Math.floor((seg % 3600) / 60);
  const s = seg % 60;
  if (h > 0) return `${h}h ${String(m).padStart(2,'0')}m ${String(s).padStart(2,'0')}s`;
  if (m > 0) return `${m}m ${String(s).padStart(2,'0')}s`;
  return `${s}s`;
}
</script>
<?php if ($esLocalPrivado): ?>
<script src="<?= $baseUrl ?>/public/js/admin-video-pin.js?v=1.0"></script>
<?php endif; ?>
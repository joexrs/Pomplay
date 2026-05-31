<?php
// 1. PRIMERO INICIAR SESIÓN Y TODA LA LÓGICA DE PHP (Cero HTML antes de esto)
session_start();
include __DIR__ . '/../conexion.php';

$userId = $_SESSION['user_id'] ?? null;
$propietarioId = $_SESSION['id_propietario'] ?? null;
$localId = $_SESSION['id_local'] ?? null;

if (!$userId || !$propietarioId) {
    die('Acceso denegado');
}

try {
    // Obtener locales del propietario
    $stmtLocales = $pdo->prepare("CALL GetOwnerLocalList(:id_propietario)");
    $stmtLocales->execute([':id_propietario' => $propietarioId]);
    $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
    $stmtLocales->closeCursor();
} catch (PDOException $e) {
    die('Error al obtener locales: ' . $e->getMessage());
}

// Si no hay local seleccionado, usar el primero
if (!$localId && !empty($locales)) {
    $localId = $locales[0]['id_local'];
    $_SESSION['id_local'] = $localId;
}

if (!$localId) {
    die('No tienes locales asignados');
}

// Cambiar local si se selecciona uno diferente
if (isset($_GET['change_local']) && !empty($_GET['change_local'])) {
    $newLocalId = (int)$_GET['change_local'];
    $valid = false;
    foreach ($locales as $loc) {
        if ($loc['id_local'] == $newLocalId) {
            $valid = true;
            break;
        }
    }
    if ($valid) {
        $_SESSION['id_local'] = $newLocalId;
        $localId = $newLocalId;
        header('Location: videos.php'); // Redirección segura, no hay HTML previo
        exit;
    }
}

// Filtros
$fecha = $_GET['fecha'] ?? null;
$cancha = $_GET['cancha'] ?? null;

try {
    // Obtener videos
    $stmt = $pdo->prepare("CALL GetOwnerVideosFiltered(:id_local, :fecha, :codigo_cancha)");
    $stmt->execute([
        ':id_local' => $localId,
        ':fecha' => $fecha ?: null,
        ':codigo_cancha' => $cancha ?: null,
    ]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
} catch (PDOException $e) {
    $videos = [];
}

try {
    // Obtener canchas del local
    $stmtCanchas = $pdo->prepare("CALL GetCanchasByLocal(:id_local)");
    $stmtCanchas->execute([':id_local' => $localId]);
    $canchas = $stmtCanchas->fetchAll(PDO::FETCH_ASSOC);
    $stmtCanchas->closeCursor();
} catch (PDOException $e) {
    $canchas = [];
}
?>

<?php 
// 2. RECIÉN AQUÍ SE INCLUYE EL DISEÑO VISUAL
include __DIR__ . '/../admin/shared/header_admin.php'; 
?>

<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/owner-videos.css">

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Mis Videos</h1>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Videos</span>
      </div>
    </div>
  </div>

  <?php if (count($locales) > 1): ?>
  <div class="adm-form-card local-selector-card">
    <div class="local-selector-wrapper">
      <div class="local-selector-field">
        <label class="local-selector-label">Local Activo</label>
        <select class="form-control local-selector-select" onchange="window.location.href='videos.php?change_local='+this.value">
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>" <?= $localId == $loc['id_local'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="adm-form-card filters-card">
    <form method="GET" class="filters-form">
      <div class="filter-field">
        <label class="filter-label">Fecha</label>
        <input type="date" name="fecha" value="<?= htmlspecialchars($fecha) ?>" class="form-control filter-input">
      </div>
      <div class="filter-field">
        <label class="filter-label">Cancha</label>
        <select name="cancha" class="form-control filter-select">
          <option value="">Todas las canchas</option>
          <?php foreach ($canchas as $c): ?>
            <option value="<?= $c['codigo_cancha'] ?>" <?= $cancha == $c['codigo_cancha'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['descripcion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn-filter">
          <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="videos.php" class="btn-filter-clear">
          <i class="fas fa-times"></i> Limpiar
        </a>
      </div>
    </form>
  </div>

  <div class="table-container">
    <table class="adm-table">
      <thead>
        <tr>
          <th>Código</th>
          <th>Descripción</th>
          <th>Cancha</th>
          <th>Fecha</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $meses = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril','May'=>'mayo','June'=>'junio','July'=>'julio','August'=>'agosto','September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];
        if (empty($videos)): ?>
          <tr>
            <td colspan="5" class="videos-empty-state">
              <i class="bi bi-play-circle videos-empty-icon"></i>
              <p class="videos-empty-text">No hay videos encontrados.</p>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($videos as $video): 
            $fd = str_replace(array_keys($meses), array_values($meses), date('d M Y', strtotime($video['fecha_partido'])));
          ?>
          <tr>
            <td data-label="Código"><span class="td-id"><?= htmlspecialchars($video['codigo_video']) ?></span></td>
            <td data-label="Descripción" class="td-description"><?= htmlspecialchars($video['descripcion']) ?></td>
            <td data-label="Cancha"><span class="badge-active"><?= htmlspecialchars($video['cancha_nombre'] ?? $video['codigo_cancha']) ?></span></td>
            <td data-label="Fecha" class="td-date"><?= $fd ?></td>
            <td data-label="Acciones">
              <div class="table-actions">
                <a href="#" class="btn-icon" title="Ver" onclick="verVideo('<?= htmlspecialchars($video['video_url']) ?>', '<?= htmlspecialchars($video['descripcion']) ?>')">
                  <i class="bi bi-eye"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</main>

<!-- Modal Video -->
<div id="videoModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#11132d; width:90%; max-width:900px; border-radius:12px; overflow:hidden; box-shadow:0 20px 40px rgba(0,0,0,0.5);">
    <div style="padding:15px 20px; border-bottom:1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; align-items:center;">
      <h3 id="videoModalTitle" style="margin:0; font-size:1.1rem; color:#fff;"></h3>
      <button onclick="cerrarVideoModal()" style="background:none; border:none; color:#fff; font-size:2rem; cursor:pointer; line-height:1;">&times;</button>
    </div>
    <div style="padding:20px;">
      <video id="videoModalPlayer" controls style="width:100%; border-radius:8px; background:#000; max-height:500px;">
        Tu navegador no soporta videos HTML5.
      </video>
    </div>
  </div>
</div>

<script>
function verVideo(url, titulo) {
    const modal  = document.getElementById('videoModal');
    const player = document.getElementById('videoModalPlayer');
    const title  = document.getElementById('videoModalTitle');

    title.textContent = titulo;
    player.src = url;
    player.load();
    modal.style.display = 'flex';
    player.play();
}

function cerrarVideoModal() {
    const modal  = document.getElementById('videoModal');
    const player = document.getElementById('videoModalPlayer');
    player.pause();
    player.src = '';
    modal.style.display = 'none';
}

// Cerrar con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarVideoModal();
});

// Cerrar al hacer clic fuera
document.getElementById('videoModal').addEventListener('click', function(e) {
    if (e.target === this) cerrarVideoModal();
});
</script>

<?php include __DIR__ . '/../admin/shared/footer_admin.php'; ?>
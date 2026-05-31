<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
// Obtener filtros
$localFilter = $_GET['local'] ?? null;
$canchaFilter = $_GET['cancha'] ?? null;
$fechaFilter = $_GET['fecha'] ?? null;

// Obtener videos con filtros
$stmt = $pdo->prepare("CALL GetAdminVideosFiltered(:id_local, :codigo_cancha, :fecha)");
$stmt->execute([
    ':id_local' => $localFilter ?: null,
    ':codigo_cancha' => $canchaFilter ?: null,
    ':fecha' => $fechaFilter ?: null
]);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Obtener locales para el filtro
$stmtLocales = $pdo->query("CALL GetLocalesPaginadoSimple(200, 0)");
$locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
$stmtLocales->closeCursor();

// Obtener canchas para el filtro
$stmtCanchas = $pdo->query("CALL GetAllCanchas()");
$canchas = $stmtCanchas->fetchAll(PDO::FETCH_ASSOC);
$stmtCanchas->closeCursor();
?>

<main class="app-content">
<div class="page-container">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Videos</h1>
      <p class="page-subtitle">Gestión de videos de partidos en Pomplay</p>
    </div>
    <div class="page-header__actions">
      <a href="<?= $baseUrl ?>/admin/videos/add.php" class="btn-adm-add">
        <i class="bi bi-plus-circle"></i> Nuevo Video
      </a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="adm-form-card" style="margin-bottom: 20px; padding: 20px;">
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
      <div>
        <label class="adm-label" for="filter_local">Local</label>
        <select name="local" id="filter_local" class="adm-select">
          <option value="">Todos los locales</option>
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>" <?= $localFilter == $loc['id_local'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div>
        <label class="adm-label" for="filter_cancha">Cancha</label>
        <select name="cancha" id="filter_cancha" class="adm-select">
          <option value="">Todas las canchas</option>
          <?php foreach ($canchas as $c): ?>
            <option value="<?= htmlspecialchars($c['codigo_cancha']) ?>" <?= $canchaFilter == $c['codigo_cancha'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['descripcion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div>
        <label class="adm-label" for="filter_fecha">Fecha</label>
        <input type="date" name="fecha" id="filter_fecha" class="adm-input" value="<?= htmlspecialchars($fechaFilter ?? '') ?>">
      </div>
      
      <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn-adm-save" style="flex: 1;">
          <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/admin/videos/index.php" class="btn-adm-cancel" style="display: inline-flex; align-items: center; justify-content: center; padding: 12px 16px; text-decoration: none;">
          <i class="fas fa-times"></i>
        </a>
      </div>
    </form>
  </div>

  <!-- Table Card -->
  <div class="videos-table-card">  
      <table class="adm-table" id="videosTable">
        <thead>
          <tr>
            <th>Código</th>
            <th>Fecha</th>
            <th>Descripción</th>
            <th>Cancha</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($videos as $row): ?>
          <tr>
            <td data-label="Código"><span class="td-id"><?= htmlspecialchars($row['codigo_video']) ?></span></td>
            <td data-label="Fecha"><span class="td-date"><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($row['fecha_partido'])) ?></span></td>
            <td data-label="Descripción" class="td-description"><?= htmlspecialchars($row['descripcion']) ?></td>
            <td data-label="Cancha"><span class="badge-active"><?= htmlspecialchars($row['nombre_cancha'] ?? 'N/A') ?></span></td>
            <td data-label="Acciones">
              <div class="table-actions">
                <a href="<?= $baseUrl ?>/admin/videos/edit.php?codigo_video=<?= urlencode($row['codigo_video']) ?>"
                   class="btn-icon btn-icon-edit" title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="procesos/delete.php?codigo_video=<?= urlencode($row['codigo_video']) ?>"
                   onclick="return confirm('¿Estás seguro de eliminar este video?')"
                   class="btn-icon btn-icon-delete" title="Eliminar">
                  <i class="bi bi-trash"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
  </div>

</div>

<?php include '../shared/footer_admin.php'; ?>

<script>
$(document).ready(function () {
  $('#videosTable').DataTable({
    language: { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json' },
    pageLength: 25,
    lengthChange: false,
    searching: false,
    ordering: true,
    info: false,
    paging: false,
    autoWidth: false,
    responsive: true
  });
});
</script>

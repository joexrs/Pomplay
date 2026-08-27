<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
$localFilter = $_GET['local'] ?? null;
$stmt = $pdo->prepare("CALL GetAdminCanchas(:id_local)");
$stmt->bindValue(':id_local', $localFilter ?: null, $localFilter ? PDO::PARAM_INT : PDO::PARAM_NULL);
$stmt->execute();
$canchas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();
?>

<main class="app-content">
<div class="page-container">

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Canchas</h1>
      <p class="page-subtitle">Gestión de canchas deportivas por local</p>
    </div>
    <div class="page-header__actions">
      <a href="<?= $baseUrl ?>/admin/canchas/add.php" class="btn-adm-add">
        <i class="bi bi-plus-circle"></i> Nueva Cancha
      </a>
    </div>
  </div>

  <!-- Filter by Local -->
  <div class="adm-form-card" style="margin-bottom: 20px; padding: 20px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: flex-end;">
      <div style="flex: 1;">
        <label style="display: block; margin-bottom: 8px; font-size: 0.9rem; color: var(--dk-muted);">Filtrar por Local</label>
        <select name="local" class="form-control" style="width: 100%;">
          <option value="">Todos los locales</option>
          <?php
          $stmtLocales = $pdo->prepare("CALL GetLocalesPaginadoSimple(:p_limit, :p_offset)");
          $stmtLocales->execute([':p_limit' => 200, ':p_offset' => 0]);
          $locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
          $stmtLocales->closeCursor();
          foreach ($locales as $loc):
          ?>
            <option value="<?= $loc['id_local'] ?>" <?= $localFilter == $loc['id_local'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <button type="submit" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--dk-accent);color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;">
          <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="<?= $baseUrl ?>/admin/canchas/index.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:rgba(255,255,255,.1);color:#fff;border:none;border-radius:6px;text-decoration:none;font-weight:600;margin-left:8px;">
          <i class="fas fa-times"></i> Limpiar
        </a>
      </div>
    </form>
  </div>

  <!-- Table Card -->
  <div class="videos-table-card"> 
      <table class="adm-table" id="canchasTable">
        <thead>
          <tr>
            <th>Código</th>
            <th>Descripción</th>
            <th>Local</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($canchas as $row):
            $local = htmlspecialchars($row['nombre_local'] ?? 'Sin local');
          ?>
          <tr>
            <td data-label="Código"><span class="td-id"><?= htmlspecialchars($row['codigo_cancha']) ?></span></td>
            <td data-label="Descripción" class="td-description"><?= htmlspecialchars($row['descripcion']) ?></td>
            <td data-label="Local"><span class="badge-active"><?= $local ?></span></td>
            <td data-label="Acciones">
              <div class="table-actions">
                <a href="<?= $baseUrl ?>/admin/canchas/edit.php?id_cancha=<?= $row['id_cancha'] ?>"
                   class="btn-icon btn-icon-edit" title="Editar">
                   <i class="bi bi-pencil"></i>
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
  $('#canchasTable').DataTable({
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
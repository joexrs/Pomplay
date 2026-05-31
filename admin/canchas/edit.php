<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>
<main class="app-content">
<?php
if (!isset($_GET['codigo_cancha'])) { header("Location: " . $baseUrl . "/admin/canchas/index.php"); exit(); }
$codigo_cancha = $_GET['codigo_cancha'];
$stmt = $pdo->prepare("CALL GetAdminCanchaByCodigo(:codigo_cancha)");
$stmt->execute([':codigo_cancha' => $codigo_cancha]);
$cancha = $stmt->fetch();
$stmt->closeCursor();
if (!$cancha) { header("Location: " . $baseUrl . "/admin/canchas/index.php"); exit(); }
$stmt_loc = $pdo->prepare("CALL GetLocalesPaginadoSimple(:p_limit, :p_offset)");
$stmt_loc->execute([':p_limit' => 200, ':p_offset' => 0]);
$locales = $stmt_loc->fetchAll(PDO::FETCH_ASSOC);
$stmt_loc->closeCursor();
?>

<div class="adm-form-wrap">
  <div class="adm-breadcrumb">
    <a href="<?= $baseUrl ?>/admin/canchas/index.php">Canchas</a>
    <span class="sep">›</span>
    <span>Editar cancha</span>
  </div>

  <div class="adm-form-heading">
    <h1><i class="fas fa-edit" style="color:var(--dk-accent);margin-right:8px;"></i>Editar Cancha</h1>
    <p>Modifique los datos de la cancha seleccionada</p>
  </div>

  <div class="adm-form-card">
    <form action="procesos/procesar_editar.php" method="POST" autocomplete="off" id="formEditCancha">
      <input type="hidden" name="codigo_cancha" value="<?= htmlspecialchars($cancha['codigo_cancha']) ?>" />

      <div class="adm-field">
        <label class="adm-label">Código de la cancha</label>
        <input type="text" class="adm-input"
               value="<?= htmlspecialchars($cancha['codigo_cancha']) ?>"
               readonly
               style="opacity:.6;cursor:not-allowed;" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="descripcion">Descripción</label>
        <input type="text" id="descripcion" name="descripcion" class="adm-input"
               value="<?= htmlspecialchars($cancha['descripcion']) ?>" 
               required maxlength="70" minlength="3" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="id_local">Local</label>
        <select id="id_local" name="id_local" class="adm-select" required>
          <option value="">Seleccione un local</option>
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>"
              <?= ($cancha['id_local'] == $loc['id_local']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <hr class="adm-divider">
      <div class="adm-form-actions">
        <button type="submit" class="btn-adm-save">
          <i class="fas fa-check"></i> Actualizar Cancha
        </button>
        <a href="<?= $baseUrl ?>/admin/canchas/index.php" class="btn-adm-cancel">
          <i class="fas fa-times"></i> Cancelar
        </a>
      </div>
    </form>
  </div>
</div>

<script src="/js/jquery-3.7.0.min.js"></script>
<script src="/js/bootstrap.min.js"></script>
<script src="/js/main.js"></script>
<script>
document.getElementById('formEditCancha').addEventListener('submit', function(e) {
    const descripcion = document.getElementById('descripcion').value.trim();
    const local = document.getElementById('id_local').value;
    
    if (!descripcion || !local) {
        e.preventDefault();
        alert('Por favor complete todos los campos obligatorios');
        return false;
    }
});
</script>
</main>
<?php include '../shared/footer_admin.php'; ?>

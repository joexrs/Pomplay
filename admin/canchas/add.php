<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>
<main class="app-content">
<?php
$stmt = $pdo->prepare("CALL GetLocalesPaginadoSimple(:p_limit, :p_offset)");
$stmt->execute([':p_limit' => 200, ':p_offset' => 0]);
$locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();
?>

<div class="adm-form-wrap">
  <div class="adm-form-heading">
    <h1><i class="fas fa-map-marker-alt" style="color:var(--dk-accent);margin-right:8px;"></i>Nueva Cancha</h1>
    <p>Complete los datos para registrar una nueva cancha deportiva</p>
  </div>

  <div class="adm-form-card">
    <form action="procesos/procesar_cancha.php" method="POST" autocomplete="off" id="formCancha">

      <div class="adm-field">
        <label class="adm-label" for="codigo_cancha">Código de la cancha</label>
        <input type="text" id="codigo_cancha" name="codigo_cancha"
               class="adm-input" placeholder="Ej: C01, C02…" 
               required maxlength="10" pattern="[A-Za-z0-9]+" 
               title="Solo letras y números, máximo 10 caracteres" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="descripcion">Descripción</label>
        <input type="text" id="descripcion" name="descripcion"
               class="adm-input" placeholder="Nombre descriptivo de la cancha" 
               required maxlength="70" minlength="3" />
      </div>

      <div class="adm-field">
        <label class="adm-label" for="id_local">Local</label>
        <select id="id_local" name="id_local" class="adm-select" required>
          <option value="">Seleccione un local</option>
          <?php foreach ($locales as $loc): ?>
            <option value="<?= $loc['id_local'] ?>">
              <?= htmlspecialchars($loc['nombre_local']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <hr class="adm-divider">

      <div class="adm-form-actions">
        <button type="submit" class="btn-adm-save">
          <i class="fas fa-check"></i> Guardar Cancha
        </button>
        <a href="<?= $baseUrl ?>/admin/canchas/index.php" class="btn-adm-cancel">
          <i class="fas fa-times"></i> Cancelar
        </a>
      </div>

    </form>
  </div>
</div>

<script>
document.getElementById('formCancha').addEventListener('submit', function(e) {
    const codigo = document.getElementById('codigo_cancha').value.trim();
    const descripcion = document.getElementById('descripcion').value.trim();
    const local = document.getElementById('id_local').value;
    
    if (!codigo || !descripcion || !local) {
        e.preventDefault();
        alert('Por favor complete todos los campos obligatorios');
        return false;
    }
    
    if (codigo.length > 10) {
        e.preventDefault();
        alert('El código no puede exceder 10 caracteres');
        return false;
    }
    
    if (!/^[A-Za-z0-9]+$/.test(codigo)) {
        e.preventDefault();
        alert('El código solo puede contener letras y números');
        return false;
    }
});
</script>
</main>
<?php include '../shared/footer_admin.php'; ?>

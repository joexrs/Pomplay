<?php include '../../conexion.php'; ?>

<?php
$localId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = null;
$success = null;

// Obtener datos del local
$stmt = $pdo->prepare("SELECT * FROM locales WHERE id_local = :id");
$stmt->execute([':id' => $localId]);
$local = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$local) {
    die('Local no encontrado');
}

// Obtener propietarios existentes
$propietarios = $pdo->query("SELECT id_propietario, nombres, apellidos, email FROM propietarios WHERE estado = 1 ORDER BY nombres, apellidos")->fetchAll(PDO::FETCH_ASSOC);

// Obtener propietario y membresía del local
$stmtOwner = $pdo->prepare("
    SELECT p.*, m.fecha_inicio, m.fecha_vencimiento, m.tipo_membresia, m.id_membresia
    FROM propietarios p
    LEFT JOIN usuarios u ON p.id_propietario = u.id_propietario AND u.id_rol = 2
    LEFT JOIN membresias m ON u.id_usuario = m.id_usuario AND m.id_local = :id_local
    WHERE p.id_propietario = :id_propietario
    LIMIT 1
");
$stmtOwner->execute([':id_propietario' => $local['id_propietario'], ':id_local' => $localId]);
$propietario = $stmtOwner->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Actualizar local (incluyendo es_privado)
        $esPrivado = isset($_POST['es_privado']) ? 1 : 0;
        $stmt = $pdo->prepare("
            UPDATE locales
            SET nombre_local = :nombre_local,
                id_propietario = :id_propietario,
                es_privado = :es_privado
            WHERE id_local = :id
        ");
        $stmt->execute([
            ':nombre_local' => $_POST['nombre_local'],
            ':id_propietario' => !empty($_POST['id_propietario']) ? $_POST['id_propietario'] : null,
            ':es_privado' => $esPrivado,
            ':id' => $localId,
        ]);

        // Manejar membresía si se proporciona un propietario
        if (!empty($_POST['id_propietario']) && !empty($_POST['fecha_inicio']) && !empty($_POST['fecha_fin'])) {
            // Obtener el usuario asociado al propietario
            $stmtUser = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE id_propietario = :id_propietario LIMIT 1");
            $stmtUser->execute([':id_propietario' => $_POST['id_propietario']]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                // Verificar si ya existe una membresía para este usuario y local
                $stmtCheck = $pdo->prepare("SELECT id_membresia FROM membresias WHERE id_usuario = :id_usuario AND id_local = :id_local LIMIT 1");
                $stmtCheck->execute([':id_usuario' => $usuario['id_usuario'], ':id_local' => $localId]);
                $membresiaExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($membresiaExistente) {
                    // Actualizar membresía existente
                    $stmtMembership = $pdo->prepare("
                        UPDATE membresias
                        SET fecha_inicio = :fecha_inicio,
                            fecha_vencimiento = :fecha_vencimiento,
                            tipo_membresia = :tipo_membresia,
                            estado = 1
                        WHERE id_membresia = :id
                    ");
                    $stmtMembership->execute([
                        ':fecha_inicio' => $_POST['fecha_inicio'],
                        ':fecha_vencimiento' => $_POST['fecha_fin'],
                        ':tipo_membresia' => $_POST['tipo_membresia'] ?? 'BASICA',
                        ':id' => $membresiaExistente['id_membresia'],
                    ]);
                } else {
                    // Crear nueva membresía
                    $stmtMembership = $pdo->prepare("
                        INSERT INTO membresias (id_usuario, id_local, fecha_inicio, fecha_vencimiento, tipo_membresia, estado)
                        VALUES (:id_usuario, :id_local, :fecha_inicio, :fecha_vencimiento, :tipo_membresia, 1)
                    ");
                    $stmtMembership->execute([
                        ':id_usuario' => $usuario['id_usuario'],
                        ':id_local' => $localId,
                        ':fecha_inicio' => $_POST['fecha_inicio'],
                        ':fecha_vencimiento' => $_POST['fecha_fin'],
                        ':tipo_membresia' => $_POST['tipo_membresia'] ?? 'BASICA',
                    ]);
                }
            }
        }

        header('Location: ' . $baseUrl . '/admin/locales/index.php?success=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Error al actualizar el local: ' . $e->getMessage();
    }
}

// Ahora incluir el header después de procesar el POST
include '../shared/header_admin.php';
include '../config.php';
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Editar Local</h1>
      <p class="page-subtitle">Modificar información del local y su membresía</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Gestión</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Locales</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Editar</span>
      </div>
    </div>
  </div>

  <div class="adm-form-wrap" style="max-width:900px;">
    <?php if ($error): ?>
      <div style="background:rgba(238,62,70,.1);border:1px solid rgba(238,62,70,.3);color:#EE3E46;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" class="adm-form">
      <!-- Card: Información del Local -->
      <div class="adm-form-card" style="margin-bottom:24px;">
        <div class="adm-form-heading" style="margin-top:0;">
          <h1><i class="fas fa-edit" style="color:var(--color-brand);margin-right:12px;"></i>Información del Local</h1>
          <p>Modifica los datos básicos del local</p>
        </div>

        <div class="adm-field">
          <label class="adm-label" for="nombre_local">Nombre del Local *</label>
          <input type="text" id="nombre_local" name="nombre_local" required 
                 class="adm-input" value="<?= htmlspecialchars($local['nombre_local']) ?>">
        </div>

        <div class="adm-field">
          <label class="adm-label" for="id_propietario">Propietario</label>
          <select id="id_propietario" name="id_propietario" class="adm-select">
            <option value="">Sin propietario</option>
            <?php foreach ($propietarios as $prop): ?>
              <option value="<?= $prop['id_propietario'] ?>" <?= ($local['id_propietario'] == $prop['id_propietario']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($prop['nombres'] . ' ' . $prop['apellidos']) ?> (<?= htmlspecialchars($prop['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="adm-field" style="margin-top:20px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.06);">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
            <div>
              <label class="adm-label" style="margin-bottom:4px; display:block;">
                <i class="fas fa-lock" style="color:var(--color-brand); margin-right:6px;"></i>Videos Privados
              </label>
              <small style="color:var(--color-text-muted); font-size:0.8rem;">
                Los videos de este local requerirán un código PIN para ser vistos
              </small>
            </div>
            <label class="switch-toggle" for="es_privado" style="flex-shrink:0;">
              <input type="checkbox" id="es_privado" name="es_privado" value="1"
                     <?= !empty($local['es_privado']) ? 'checked' : '' ?>>
              <span class="switch-slider"></span>
            </label>
          </div>
        </div>
      </div>

      <?php if ($propietario): ?>
      <!-- Card: Membresía del Propietario -->
      <div class="adm-form-card" style="margin-bottom:24px;">
        <div class="adm-form-heading" style="margin-top:0;">
          <h1><i class="fas fa-crown" style="color:var(--color-brand);margin-right:12px;"></i>Membresía del Propietario</h1>
          <p>Propietario: <strong><?= htmlspecialchars($propietario['nombres'] . ' ' . $propietario['apellidos']) ?></strong></p>
        </div>

        <div class="adm-field">
          <label class="adm-label" for="tipo_membresia">Tipo de Membresía</label>
          <select id="tipo_membresia" name="tipo_membresia" class="adm-select">
            <option value="BASICA" <?= ($propietario['tipo_membresia'] ?? '') == 'BASICA' ? 'selected' : '' ?>>Básica (1 mes)</option>
            <option value="PREMIUM" <?= ($propietario['tipo_membresia'] ?? '') == 'PREMIUM' ? 'selected' : '' ?>>Premium (6 meses)</option>
            <option value="ANUAL" <?= ($propietario['tipo_membresia'] ?? '') == 'ANUAL' ? 'selected' : '' ?>>Anual (1 año)</option>
          </select>
        </div>

        <div class="adm-grid-2">
          <div class="adm-field">
            <label class="adm-label" for="fecha_inicio">Fecha de Inicio</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" class="adm-input" 
                   value="<?= $propietario['fecha_inicio'] ?? '' ?>">
          </div>
          <div class="adm-field">
            <label class="adm-label" for="fecha_fin">Fecha de Vencimiento</label>
            <input type="date" id="fecha_fin" name="fecha_fin" class="adm-input"
                   value="<?= $propietario['fecha_vencimiento'] ?? '' ?>">
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Acciones -->
      <div class="adm-form-actions">
        <button type="submit" class="btn-adm-save">
          <i class="fas fa-save"></i> Guardar Cambios
        </button>
        <a href="<?= $baseUrl ?>/admin/locales/index.php" class="btn-adm-cancel">
          <i class="fas fa-times"></i> Cancelar
        </a>
      </div>
    </form>
  </div>
</main>

<script>
// Auto-calcular fecha de vencimiento según tipo de membresía
document.getElementById('tipo_membresia').addEventListener('change', function() {
  const tipo = this.value;
  const fechaInicio = new Date(document.getElementById('fecha_inicio').value);
  let meses = 1;
  
  if (tipo === 'PREMIUM') meses = 6;
  else if (tipo === 'ANUAL') meses = 12;
  
  fechaInicio.setMonth(fechaInicio.getMonth() + meses);
  document.getElementById('fecha_fin').value = fechaInicio.toISOString().split('T')[0];
});
</script>

<style>
/* Toggle Switch para es_privado */
.switch-toggle {
  position: relative;
  display: inline-block;
  width: 52px;
  height: 28px;
  cursor: pointer;
}
.switch-toggle input {
  opacity: 0;
  width: 0;
  height: 0;
}
.switch-slider {
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,0.1);
  border-radius: 28px;
  transition: all 0.3s ease;
}
.switch-slider::before {
  content: '';
  position: absolute;
  height: 22px;
  width: 22px;
  left: 3px;
  bottom: 3px;
  background: #fff;
  border-radius: 50%;
  transition: all 0.3s ease;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.switch-toggle input:checked + .switch-slider {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
}
.switch-toggle input:checked + .switch-slider::before {
  transform: translateX(24px);
}
.switch-toggle input:focus + .switch-slider {
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
}
</style>

<?php include '../shared/footer_admin.php'; ?>

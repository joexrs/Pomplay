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
        // Actualizar local
        $stmt = $pdo->prepare("
            UPDATE locales
            SET nombre_local = :nombre_local,
                id_propietario = :id_propietario
            WHERE id_local = :id
        ");
        $stmt->execute([
            ':nombre_local' => $_POST['nombre_local'],
            ':id_propietario' => !empty($_POST['id_propietario']) ? $_POST['id_propietario'] : null,
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

<style>
  .app-content {
    color: #ffffff;
  }

  .page-header {
    margin-bottom: 22px;
  }

  .page-title {
    color: #ffffff;
    font-size: clamp(1.6rem, 4vw, 2.2rem);
    font-weight: 800;
  }

  .page-subtitle {
    color: rgba(255,255,255,.68);
  }

  .adm-form-wrap {
    max-width: 980px;
    margin: 0 auto;
    padding: 24px 16px 44px;
  }

  .adm-form-card,
  .form-card,
  .card {
    background: linear-gradient(180deg, rgba(17,19,45,.98), rgba(8,10,30,.98)) !important;
    border: 1px solid rgba(255,255,255,.08) !important;
    border-radius: 18px !important;
    padding: 28px !important;
    box-shadow: 0 18px 45px rgba(0,0,0,.28) !important;
    color: #ffffff;
  }

  .adm-field,
  .form-group {
    margin-bottom: 20px;
  }

  .adm-label,
  label {
    display: block;
    color: #ffffff;
    font-weight: 700;
    font-size: .92rem;
    margin-bottom: 8px;
  }

  .adm-input,
  .adm-select,
  .form-control,
  input,
  select,
  textarea {
    width: 100%;
    min-height: 48px;
    background: rgba(255,255,255,.055) !important;
    border: 1px solid rgba(255,255,255,.12) !important;
    color: #ffffff !important;
    border-radius: 12px !important;
    padding: 12px 14px !important;
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
  }

  input::placeholder,
  textarea::placeholder {
    color: rgba(255,255,255,.42) !important;
  }

  select option {
    color: #111;
  }

  input:focus,
  select:focus,
  textarea:focus,
  .form-control:focus {
    border-color: rgba(236,66,55,.75) !important;
    box-shadow: 0 0 0 4px rgba(236,66,55,.14) !important;
    background: rgba(255,255,255,.075) !important;
  }

  small,
  .form-text {
    color: rgba(255,255,255,.55) !important;
    font-size: .82rem;
  }

  .adm-form-actions,
  .form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    align-items: center;
    margin-top: 10px;
  }

  .btn-adm-save,
  .btn-primary,
  button[type="submit"] {
    min-height: 46px;
    padding: 12px 18px;
    border-radius: 12px;
    font-weight: 800;
    background: #ec4237 !important;
    color: #ffffff !important;
    border: 0 !important;
    box-shadow: 0 12px 28px rgba(236,66,55,.25);
  }

  .btn-adm-cancel,
  .btn-secondary,
  .btn-cancel {
    min-height: 46px;
    padding: 12px 18px;
    border-radius: 12px;
    font-weight: 800;
    background: rgba(255,255,255,.06) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255,255,255,.12) !important;
    text-decoration: none;
  }

  @media (max-width: 720px) {
    .adm-form-wrap {
      padding: 18px 12px 34px;
    }

    .adm-form-card,
    .form-card,
    .card {
      padding: 20px !important;
      border-radius: 16px !important;
    }

    .row,
    .adm-grid-2 {
      display: block !important;
    }

    .adm-form-actions,
    .form-actions {
      flex-direction: column;
    }

    .btn-adm-save,
    .btn-adm-cancel,
    .btn-primary,
    .btn-secondary,
    .btn-cancel,
    button[type="submit"] {
      width: 100%;
      justify-content: center;
      text-align: center;
    }
  }
</style>

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
      <div style="background:rgba(236,66,55,.1);border:1px solid rgba(236,66,55,.3);color:#ec4237;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
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

<?php include '../shared/footer_admin.php'; ?>

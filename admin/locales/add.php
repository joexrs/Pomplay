<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
// Obtener propietarios existentes
$stmtPropietarios = $pdo->query("CALL GetActivePropietarios()");
$propietarios = $stmtPropietarios->fetchAll(PDO::FETCH_ASSOC);
$stmtPropietarios->closeCursor();
?>

<style>
  .app-content {
    color: #ffffff;
  }

  .adm-form-wrap {
    max-width: 980px !important;
    margin: 0 auto;
    padding: 24px 16px 44px;
  }

  .adm-form-card {
    background: linear-gradient(180deg, rgba(17,19,45,.98), rgba(8,10,30,.98));
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 18px 45px rgba(0,0,0,.28);
  }

  .adm-form-heading {
    margin-bottom: 22px;
  }

  .adm-form-heading h1 {
    color: #ffffff;
    font-size: clamp(1.3rem, 3vw, 1.7rem);
    font-weight: 800;
    margin-bottom: 8px;
  }

  .adm-form-heading p {
    color: rgba(255,255,255,.68);
    margin: 0;
  }

  .adm-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
  }

  .adm-field {
    margin-bottom: 20px;
  }

  .adm-label {
    display: block;
    color: #ffffff;
    font-weight: 700;
    font-size: .92rem;
    margin-bottom: 8px;
  }

  .adm-input,
  .adm-select {
    width: 100%;
    min-height: 48px;
    background: rgba(255,255,255,.055);
    border: 1px solid rgba(255,255,255,.12);
    color: #ffffff;
    border-radius: 12px;
    padding: 12px 14px;
    outline: none;
    transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
  }

  .adm-input::placeholder {
    color: rgba(255,255,255,.42);
  }

  .adm-select option {
    color: #111;
  }

  .adm-input:focus,
  .adm-select:focus {
    border-color: rgba(236,66,55,.75);
    box-shadow: 0 0 0 4px rgba(236,66,55,.14);
    background: rgba(255,255,255,.075);
  }

  .adm-field small {
    color: rgba(255,255,255,.55) !important;
    font-size: .82rem !important;
    line-height: 1.35;
  }

  .adm-form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    align-items: center;
    margin-top: 10px;
  }

  .btn-adm-save,
  .btn-adm-cancel {
    min-height: 46px;
    padding: 12px 18px;
    border-radius: 12px;
    font-weight: 800;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    cursor: pointer;
  }

  .btn-adm-save {
    background: #ec4237;
    color: #ffffff;
    box-shadow: 0 12px 28px rgba(236,66,55,.25);
  }

  .btn-adm-cancel {
    background: rgba(255,255,255,.06);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,.12);
  }

  @media (max-width: 720px) {
    .adm-form-wrap {
      padding: 18px 12px 34px;
    }

    .adm-form-card {
      padding: 20px;
      border-radius: 16px;
    }

    .adm-grid-2 {
      grid-template-columns: 1fr;
      gap: 0;
    }

    .adm-form-actions {
      flex-direction: column;
    }

    .btn-adm-save,
    .btn-adm-cancel {
      width: 100%;
    }
  }
</style>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Nuevo Local</h1>
      <p class="page-subtitle">Registrar un nuevo local con propietario y membresía</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Gestión</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Locales</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Nuevo</span>
      </div>
    </div>
  </div>

  <div class="adm-form-wrap" style="max-width:900px;">
    <?php if (isset($_SESSION['error'])): ?>
      <div style="background:rgba(236,66,55,.1);border:1px solid rgba(236,66,55,.3);color:#ec4237;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" action="procesos/procesar_local.php" class="adm-form">
      <!-- Card: Información del Local -->
      <div class="adm-form-card" style="margin-bottom:24px;">
        <div class="adm-form-heading" style="margin-top:0;">
          <h1><i class="fas fa-store" style="color:var(--color-brand);margin-right:12px;"></i>Información del Local</h1>
          <p>Completa los datos básicos del local</p>
        </div>
        <div class="adm-field">
          <label class="adm-label" for="nombre_local">Nombre del Local *</label>
          <input type="text" id="nombre_local" name="nombre_local" required maxlength="100" minlength="3"
                 class="adm-input" placeholder="Ej: PomPlay Centro">
        </div>

        <div class="adm-field">
          <label class="adm-label" for="id_propietario">Propietario Existente</label>
          <select id="id_propietario" name="id_propietario" class="adm-select">
            <option value="">Seleccionar propietario existente o crear uno nuevo</option>
            <?php foreach ($propietarios as $prop): ?>
              <option value="<?= $prop['id_propietario'] ?>">
                <?= htmlspecialchars($prop['nombres'] . ' ' . $prop['apellidos']) ?> (<?= htmlspecialchars($prop['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color:var(--color-text-muted);font-size:0.8rem;margin-top:4px;display:block;">
            <i class="fas fa-info-circle"></i> Si no seleccionas uno, debes crear un nuevo propietario abajo
          </small>
        </div>
      </div>

      <!-- Card: Nuevo Propietario -->
      <div class="adm-form-card" style="margin-bottom:24px;">
        <div class="adm-form-heading" style="margin-top:0;">
          <h1><i class="fas fa-user-plus" style="color:var(--color-brand);margin-right:12px;"></i>Nuevo Propietario</h1>
          <p>Completa estos datos solo si vas a crear un nuevo propietario</p>
        </div>

        <div class="adm-grid-2">
          <div class="adm-field">
            <label class="adm-label" for="nuevo_propietario_nombre">Nombres</label>
            <input type="text" id="nuevo_propietario_nombre" name="nuevo_propietario_nombre" class="adm-input" 
                   placeholder="Ej: Juan" maxlength="255">
          </div>
          <div class="adm-field">
            <label class="adm-label" for="nuevo_propietario_apellidos">Apellidos</label>
            <input type="text" id="nuevo_propietario_apellidos" name="nuevo_propietario_apellidos" class="adm-input" 
                   placeholder="Ej: Pérez" maxlength="255">
          </div>
        </div>

        <div class="adm-field">
          <label class="adm-label" for="nuevo_propietario_email">Email</label>
          <input type="email" id="nuevo_propietario_email" name="nuevo_propietario_email" class="adm-input" 
                 placeholder="Ej: juan@ejemplo.com" maxlength="255">
        </div>

        <div class="adm-grid-2">
          <div class="adm-field">
            <label class="adm-label" for="nuevo_propietario_telefono">Teléfono</label>
            <input type="text" id="nuevo_propietario_telefono" name="nuevo_propietario_telefono" class="adm-input" 
                   placeholder="Ej: +51 123 456 789" maxlength="20">
          </div>
          <div class="adm-field">
            <label class="adm-label" for="nuevo_propietario_direccion">Dirección</label>
            <input type="text" id="nuevo_propietario_direccion" name="nuevo_propietario_direccion" class="adm-input" 
                   placeholder="Ej: Av. Principal 123" maxlength="255">
          </div>
        </div>

        <div class="adm-field">
          <label class="adm-label" for="nuevo_propietario_password">Contraseña</label>
          <input type="password" id="nuevo_propietario_password" name="nuevo_propietario_password" class="adm-input" 
                 placeholder="Mínimo 6 caracteres" minlength="6">
        </div>
      </div>

      <!-- Card: Membresía -->
      <div class="adm-form-card" style="margin-bottom:24px;">
        <div class="adm-form-heading" style="margin-top:0;">
          <h1><i class="fas fa-crown" style="color:var(--color-brand);margin-right:12px;"></i>Membresía Inicial</h1>
          <p>Configura la membresía del local (obligatorio)</p>
        </div>

        <div class="adm-field">
          <label class="adm-label" for="tipo_membresia">Tipo de Membresía *</label>
          <select id="tipo_membresia" name="tipo_membresia" class="adm-select" required>
            <option value="BASICA">Básica (1 mes)</option>
            <option value="PREMIUM">Premium (6 meses)</option>
            <option value="ANUAL">Anual (1 año)</option>
          </select>
        </div>

        <div class="adm-grid-2">
          <div class="adm-field">
            <label class="adm-label" for="fecha_inicio">Fecha de Inicio *</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" class="adm-input" required
                   value="<?= date('Y-m-d') ?>">
          </div>
          <div class="adm-field">
            <label class="adm-label" for="fecha_fin">Fecha de Vencimiento *</label>
            <input type="date" id="fecha_fin" name="fecha_fin" class="adm-input" required>
          </div>
        </div>
      </div>

      <!-- Acciones -->
      <div class="adm-form-actions">
        <button type="submit" class="btn-adm-save">
          <i class="fas fa-save"></i> Guardar Local
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
document.getElementById('tipo_membresia').addEventListener('change', calcularFechaVencimiento);
document.getElementById('fecha_inicio').addEventListener('change', calcularFechaVencimiento);

function calcularFechaVencimiento() {
  const tipo = document.getElementById('tipo_membresia').value;
  const fechaInicioInput = document.getElementById('fecha_inicio').value;
  
  if (!fechaInicioInput) return;
  
  const fechaInicio = new Date(fechaInicioInput);
  let meses = 1;
  
  if (tipo === 'PREMIUM') meses = 6;
  else if (tipo === 'ANUAL') meses = 12;
  
  fechaInicio.setMonth(fechaInicio.getMonth() + meses);
  document.getElementById('fecha_fin').value = fechaInicio.toISOString().split('T')[0];
}

// Calcular fecha inicial
calcularFechaVencimiento();

// Validación del formulario
document.querySelector('form').addEventListener('submit', function(e) {
  const propietarioExistente = document.getElementById('id_propietario').value;
  const nuevoNombre = document.getElementById('nuevo_propietario_nombre').value.trim();
  const nuevoApellidos = document.getElementById('nuevo_propietario_apellidos').value.trim();
  const nuevoEmail = document.getElementById('nuevo_propietario_email').value.trim();
  const nuevoPassword = document.getElementById('nuevo_propietario_password').value;
  
  // Validar que haya propietario existente O datos completos de nuevo propietario
  if (!propietarioExistente && (!nuevoNombre || !nuevoApellidos || !nuevoEmail || !nuevoPassword)) {
    e.preventDefault();
    alert('Debe seleccionar un propietario existente o completar todos los datos para crear uno nuevo (Nombres, Apellidos, Email y Contraseña)');
    return false;
  }
  
  // Si está creando nuevo propietario, validar email
  if (nuevoEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(nuevoEmail)) {
    e.preventDefault();
    alert('El email del propietario no es válido');
    return false;
  }
  
  // Validar contraseña
  if (nuevoPassword && nuevoPassword.length < 6) {
    e.preventDefault();
    alert('La contraseña debe tener al menos 6 caracteres');
    return false;
  }
  
  // Validar fechas
  const fechaInicio = document.getElementById('fecha_inicio').value;
  const fechaFin = document.getElementById('fecha_fin').value;
  
  if (!fechaInicio || !fechaFin) {
    e.preventDefault();
    alert('Las fechas de membresía son obligatorias');
    return false;
  }
  
  if (new Date(fechaFin) <= new Date(fechaInicio)) {
    e.preventDefault();
    alert('La fecha de vencimiento debe ser posterior a la fecha de inicio');
    return false;
  }
});
</script>

<?php include '../shared/footer_admin.php'; ?>

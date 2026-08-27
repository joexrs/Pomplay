<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
// Obtener locales con información de propietarios y membresías
$stmtLocales = $pdo->query("CALL GetAdminLocalesWithMemberships()");
$locales = $stmtLocales->fetchAll(PDO::FETCH_ASSOC);
$stmtLocales->closeCursor();
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Gestión de Locales</h1>
      <p class="page-subtitle">Administra los locales y sus membresías</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item">Gestión</span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Locales</span>
      </div>
    </div>
  </div>

  <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">
    <?php if (isset($_SESSION['success'])): ?>
      <div style="background:rgba(61,240,194,.1);border:1px solid rgba(61,240,194,.3);color:#3DF0C2;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($_SESSION['success']) ?></span>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div style="background:rgba(238,62,70,.1);border:1px solid rgba(238,62,70,.3);color:#EE3E46;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($_SESSION['error']) ?></span>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['info'])): ?>
      <div style="background:rgba(255,193,7,.1);border:1px solid rgba(255,193,7,.3);color:#FFC107;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-info-circle" style="font-size:1.2rem;"></i>
        <span><?= htmlspecialchars($_SESSION['info']) ?></span>
      </div>
      <?php unset($_SESSION['info']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['vps_warning'])): ?>
      <div style="background:rgba(255,140,0,.1);border:1px solid rgba(255,140,0,.4);color:#FF8C00;padding:16px 20px;border-radius:12px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
        <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;"></i>
        <span><strong>Advertencia VPS:</strong> <?= htmlspecialchars($_SESSION['vps_warning']) ?></span>
      </div>
      <?php unset($_SESSION['vps_warning']); ?>
    <?php endif; ?>

    <div style="margin-bottom: 20px;">
      <a href="<?= $baseUrl ?>/admin/locales/add.php" class="btn-primary" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--dk-accent);color:#fff;border:none;border-radius:6px;text-decoration:none;font-weight:600;">
        <i class="fas fa-plus"></i> Nuevo Local
      </a>
    </div>

    <?php if (empty($locales)): ?>
      <div class="empty-state" style="text-align:center;padding:60px 20px;">
        <i class="fas fa-store" style="font-size:4rem;color:var(--dk-muted);margin-bottom:20px;"></i>
        <p style="font-size:1.1rem;color:var(--dk-muted);">No hay locales registrados aún.</p>
        <a href="<?= $baseUrl ?>/admin/locales/add.php" style="color:var(--dk-accent);text-decoration:none;font-weight:600;margin-top:10px;display:inline-block;">
          Registrar primer local
        </a>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table class="adm-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Camara</th>
              <th>Propietario</th>
              <th>Email</th>
              <th>Membresía</th>
              <th>Estado</th>
              <th>Vencimiento</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($locales as $local): ?>
            <tr>
              <td data-label="ID"><span class="td-id"><?= $local['id_local'] ?></span></td>
              <td data-label="Nombre Local" class="td-description"><?= htmlspecialchars($local['nombre_local']) ?></td>
              <td data-label="Propietario"><?= htmlspecialchars(($local['nombres'] ?? '') . ' ' . ($local['apellidos'] ?? '')) ?: 'Sin asignar' ?></td>
              <td data-label="Email"><?= htmlspecialchars($local['propietario_email'] ?? '-') ?></td>
              <td data-label="Membresía">
                <span class="badge-active"><?= htmlspecialchars($local['tipo_membresia'] ?? 'Sin membresía') ?></span>
              </td>
              <td data-label="Estado">
                <?php if (empty($local['tipo_membresia'])): ?>
                  <span class="badge-inactive">Sin membresía</span>
                <?php elseif ($local['estado_membresia'] == 'ACTIVA'): ?>
                  <span class="badge-active">ACTIVA</span>
                <?php else: ?>
                  <span class="badge-inactive">VENCIDA</span>
                <?php endif; ?>
              </td>
              <td data-label="Vencimiento" class="td-date">
                <?= $local['fecha_vencimiento'] ? date('d/m/Y', strtotime($local['fecha_vencimiento'])) : '-' ?>
              </td>
              <td data-label="Acciones">
                <div class="table-actions">
                  <a href="<?= $baseUrl ?>/admin/locales/edit.php?id=<?= $local['id_local'] ?>" class="btn-icon btn-icon-edit" title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="../membresias/renew.php?id=<?= $local['id_local'] ?>" class="btn-icon" style="background:rgba(255,193,7,.1);color:#FFC107;" title="Renovar Membresía">
                    <i class="fas fa-sync-alt"></i>
                  </a>
                  <button
                    type="button"
                    class="btn-icon btn-delete-local"
                    style="background:rgba(238,62,70,.1);color:#EE3E46;border:none;cursor:pointer;"
                    title="Eliminar Local"
                    data-id="<?= $local['id_local'] ?>"
                    data-nombre="<?= htmlspecialchars($local['nombre_local']) ?>"
                  >
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include '../shared/footer_admin.php'; ?>

<!-- Modal de Confirmación de Eliminación -->
<div id="modal-delete" style="
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);
  align-items:center; justify-content:center;
">
  <div style="
    background:#1a1d2e; border:1px solid rgba(238,62,70,.3);
    border-radius:16px; padding:32px; max-width:420px; width:90%;
    box-shadow:0 20px 60px rgba(0,0,0,0.5);
    animation: modalIn .2s ease;
  ">
    <div style="text-align:center; margin-bottom:24px;">
      <div style="
        width:64px; height:64px; border-radius:50%;
        background:rgba(238,62,70,.15); border:2px solid rgba(238,62,70,.3);
        display:flex; align-items:center; justify-content:center;
        margin:0 auto 16px;
      ">
        <i class="fas fa-trash-alt" style="font-size:1.5rem; color:#EE3E46;"></i>
      </div>
      <h3 style="color:#fff; margin:0 0 8px; font-size:1.2rem;">Eliminar Local</h3>
      <p style="color:rgba(255,255,255,0.6); margin:0; font-size:0.9rem;">
        ¿Estás seguro que deseas eliminar el local?
        <strong id="modal-local-nombre" style="color:#fff;"></strong>?
      </p>
      
    </div>
    <div style="display:flex; gap:12px; justify-content:center;">
      <button id="btn-cancel-delete" style="
        flex:1; padding:10px; border-radius:8px;
        border:1px solid rgba(255,255,255,0.15);
        background:rgba(255,255,255,0.05); color:rgba(255,255,255,0.7);
        cursor:pointer; font-size:0.9rem; transition:all .2s;
      ">Cancelar</button>
      <button id="btn-confirm-delete" style="
        flex:1; padding:10px; border-radius:8px;
        border:none; background:#EE3E46; color:#fff;
        cursor:pointer; font-size:0.9rem; font-weight:600; transition:all .2s;
      ">
        <i class="fas fa-trash-alt"></i> Sí, eliminar
      </button>
    </div>
  </div>
</div>

<style>
@keyframes modalIn {
  from { transform: scale(0.9); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
#btn-cancel-delete:hover { background:rgba(255,255,255,0.1); color:#fff; }
#btn-confirm-delete:hover { background:#c0313a; }
</style>

<script>
(function () {
  const modal     = document.getElementById('modal-delete');
  const modalName = document.getElementById('modal-local-nombre');
  const btnCancel = document.getElementById('btn-cancel-delete');
  const btnConfirm= document.getElementById('btn-confirm-delete');
  let currentId   = null;
  let currentRow  = null;

  // Abrir modal al hacer click en cualquier botón eliminar
  document.querySelectorAll('.btn-delete-local').forEach(function(btn) {
    btn.addEventListener('click', function() {
      currentId   = this.dataset.id;
      currentRow  = this.closest('tr');
      modalName.textContent = '"' + this.dataset.nombre + '"';
      modal.style.display   = 'flex';
    });
  });

  // Cerrar modal
  btnCancel.addEventListener('click', function() {
    modal.style.display = 'none';
    currentId  = null;
    currentRow = null;
  });

  // Cerrar al hacer click fuera del cuadro
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      modal.style.display = 'none';
      currentId  = null;
      currentRow = null;
    }
  });

  // Confirmar eliminación
  btnConfirm.addEventListener('click', function() {
    if (!currentId) return;

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';

    const formData = new FormData();
    formData.append('id_local', currentId);

    fetch('procesos/eliminar_local.php', {
      method: 'POST',
      body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        // Animar y eliminar fila
        currentRow.style.transition = 'opacity .4s, transform .4s';
        currentRow.style.opacity    = '0';
        currentRow.style.transform  = 'translateX(20px)';
        setTimeout(function() {
          currentRow.remove();
          // Si no quedan filas, recargar para mostrar empty-state
          const tbody = document.querySelector('tbody');
          if (!tbody || tbody.querySelectorAll('tr').length === 0) {
            window.location.reload();
          }
        }, 400);
        modal.style.display = 'none';
      } else {
        alert('Error: ' + (data.message || 'No se pudo eliminar el local'));
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash-alt"></i> Sí, eliminar';
      }
    })
    .catch(function() {
      alert('Error de conexión. Intenta nuevamente.');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-trash-alt"></i> Sí, eliminar';
    });
  });
})();
</script>

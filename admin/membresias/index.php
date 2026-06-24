<?php
// Incluir el header primero (que maneja la sesión y autenticación)
include __DIR__ . '/../shared/header_admin.php';

// Incluir conexión
require __DIR__ . '/../../conexion.php';

// Incluir configuración
include __DIR__ . '/../config.php';

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
$precios = [];

try {
    // Obtener todos los precios de membresías directamente sin procedimiento
    $stmt = $pdo->query("SELECT id_precio, tipo_membresia, nombre_display, precio, duracion_meses, descripcion, activo 
                         FROM precios_membresias 
                         ORDER BY duracion_meses ASC");
    $precios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error al obtener precios: ' . $e->getMessage();
    $precios = [];
}
?>

<main class="app-content">
  <div class="page-header">
    <div class="page-header__content">
      <h1 class="page-title">Gestión de Membresías</h1>
      <p class="page-subtitle">Administra los precios de las membresías</p>
    </div>
    <div class="page-header__breadcrumb">
      <div class="breadcrumb">
        <span class="breadcrumb-item"><i class="bi bi-house-door"></i></span>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-item active">Membresías</span>
      </div>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert-success-membership">
      <i class="fas fa-check-circle"></i>
      <span>Precio actualizado exitosamente</span>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert-error-membership">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <div class="membership-container">
    <div class="membership-header-card">
      <div class="membership-header-icon">
        <i class="fas fa-crown"></i>
      </div>
      <div class="membership-header-text">
        <h2>Planes de Membresía</h2>
        <p>Configura los precios de cada plan</p>
      </div>
    </div>

    <?php if (empty($precios)): ?>
      <div class="empty-state-membership">
        <i class="fas fa-inbox"></i>
        <p>No hay precios configurados. Ejecuta el script SQL para crear la tabla.</p>
      </div>
    <?php else: ?>
      <div class="membership-grid">
        <?php 
        $iconos = [
          'BASICA' => ['icon' => 'fa-star', 'color' => '#60a5fa'],
          'PREMIUM' => ['icon' => 'fa-gem', 'color' => '#A855F7'],
          'ANUAL' => ['icon' => 'fa-crown', 'color' => '#EE3E46']
        ];
        
        foreach ($precios as $precio): 
          $icono = $iconos[$precio['tipo_membresia']] ?? ['icon' => 'fa-tag', 'color' => '#888'];
        ?>
          <div class="membership-card" data-tipo="<?= $precio['tipo_membresia'] ?>">
            <div class="membership-card-icon" style="--icon-color: <?= $icono['color'] ?>;">
              <i class="fas <?= $icono['icon'] ?>"></i>
            </div>
            
            <h3 class="membership-card-title"><?= htmlspecialchars($precio['nombre_display']) ?></h3>
            <p class="membership-card-duration"><?= $precio['duracion_meses'] ?> <?= $precio['duracion_meses'] == 1 ? 'mes' : 'meses' ?></p>
            
            <div class="membership-card-price">
              <span class="price-label">Precio Actual</span>
              <span class="price-value" style="color: <?= $icono['color'] ?>;">S/ <?= number_format($precio['precio'], 2) ?></span>
            </div>

            <button 
              onclick="editarPrecio(<?= $precio['id_precio'] ?>, '<?= htmlspecialchars($precio['nombre_display']) ?>', <?= $precio['precio'] ?>)"
              class="btn-edit-membership"
              style="--btn-color: <?= $icono['color'] ?>;">
              <i class="fas fa-edit"></i>
              Editar Precio
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<!-- Modal de Edición -->
<div id="modalEditarPrecio" class="modal-overlay-membership" style="display: none;">
  <div class="modal-content-membership">
    
    <button type="button" id="btnCerrarModal" class="modal-close-membership">
      <i class="fas fa-times"></i>
    </button>

    <div class="modal-header-membership">
      <div class="modal-icon-membership">
        <i class="fas fa-edit"></i>
      </div>
      <h2>Editar Precio</h2>
      <p id="nombreMembresia" class="modal-subtitle"></p>
    </div>

    <form method="POST" action="procesos/actualizar_precio.php" id="formEditarPrecio">
      <input type="hidden" name="id_precio" id="idPrecio">
      
      <div class="form-group-membership">
        <label for="precio" class="form-label-membership">
          <i class="fas fa-dollar-sign"></i>
          Nuevo Precio (S/)
        </label>
        <input 
          type="number" 
          name="precio" 
          id="precio" 
          step="0.01" 
          min="0" 
          class="form-input-membership" 
          required
          placeholder="Ejemplo: 50.00"
          autocomplete="off">
      </div>

      <div class="modal-actions-membership">
        <button type="button" id="btnCancelar" class="btn-cancel-membership">
          <i class="fas fa-times"></i>
          Cancelar
        </button>
        <button type="submit" class="btn-save-membership">
          <i class="fas fa-check"></i>
          Guardar Cambios
        </button>
      </div>
    </form>
  </div>
</div>

<style>
/* Alertas */
.alert-success-membership,
.alert-error-membership {
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 600;
  animation: slideDownMembership 0.3s ease;
}

.alert-success-membership {
  background: linear-gradient(135deg, rgba(34,197,94,0.3), rgba(22,163,74,0.25));
  border: 1px solid rgba(34,197,94,0.6);
  color: #4ade80;
  box-shadow: 0 4px 20px rgba(34,197,94,0.3);
}

.alert-error-membership {
  background: rgba(238,62,70,.1);
  border: 1px solid rgba(238,62,70,.3);
  color: #EE3E46;
}

/* Container Principal */
.membership-container {
  max-width: 1200px;
  margin: 0 auto;
}

/* Header de Membresías */
.membership-header-card {
  background: rgba(0,0,0,.3);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 32px;
  display: flex;
  align-items: center;
  gap: 20px;
}

.membership-header-icon {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, rgba(238,62,70,0.2), rgba(238,62,70,0.1));
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.membership-header-icon i {
  font-size: 2rem;
  color: #EE3E46;
}

.membership-header-text h2 {
  margin: 0 0 8px;
  color: #fff;
  font-size: 1.5rem;
  font-weight: 700;
}

.membership-header-text p {
  margin: 0;
  color: rgba(255,255,255,0.6);
  font-size: 0.95rem;
}

/* Grid de Membresías */
.membership-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
}

/* Tarjeta de Membresía */
.membership-card {
  background: rgba(0,0,0,.3);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
  padding: 32px 24px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.membership-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, var(--icon-color, #3B82F6)20, transparent);
  opacity: 0.1;
  transition: all 0.3s ease;
}

.membership-card:hover {
  transform: translateY(-4px);
  border-color: rgba(255,255,255,.2);
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}

.membership-card:hover::before {
  opacity: 0.2;
}

.membership-card-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 20px;
  background: linear-gradient(135deg, var(--icon-color)33, var(--icon-color)1a);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 1;
}

.membership-card-icon i {
  font-size: 2.5rem;
  color: var(--icon-color);
}

.membership-card-title {
  margin: 0 0 8px;
  color: #fff;
  font-size: 1.5rem;
  font-weight: 700;
}

.membership-card-duration {
  margin: 0 0 24px;
  color: rgba(255,255,255,0.6);
  font-size: 0.95rem;
}

.membership-card-price {
  background: rgba(255,255,255,0.05);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.price-label {
  color: rgba(255,255,255,0.6);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 700;
}

.price-value {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1;
}

.btn-edit-membership {
  width: 100%;
  padding: 14px 24px;
  background: linear-gradient(135deg, var(--btn-color), var(--btn-color)cc);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
  position: relative;
  z-index: 1;
}

.btn-edit-membership:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px var(--btn-color)50;
}

.btn-edit-membership:active {
  transform: translateY(0);
}

/* Estado Vacío */
.empty-state-membership {
  text-align: center;
  padding: 80px 40px;
  background: rgba(0,0,0,.2);
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 16px;
}

.empty-state-membership i {
  font-size: 4rem;
  color: rgba(255,255,255,0.3);
  margin-bottom: 20px;
  display: block;
}

.empty-state-membership p {
  color: rgba(255,255,255,0.6);
  margin: 0;
  font-size: 1rem;
}

/* Modal */
.modal-overlay-membership {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.9);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  padding: 20px;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.modal-overlay-membership.show {
  display: flex;
  opacity: 1;
}

.modal-content-membership {
  background: linear-gradient(135deg, #1e1b3c 0%, #2d1b3d 100%);
  border-radius: 24px;
  max-width: 500px;
  width: 100%;
  padding: 48px 40px;
  box-shadow: 0 25px 70px rgba(0,0,0,0.6);
  border: 1px solid rgba(255,255,255,0.15);
  position: relative;
  transform: scale(0.9);
  transition: transform 0.3s ease;
}

.modal-overlay-membership.show .modal-content-membership {
  transform: scale(1);
}

.modal-close-membership {
  position: absolute;
  top: 24px;
  right: 24px;
  background: rgba(255,255,255,0.1);
  border: none;
  color: #fff;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
  font-size: 1.2rem;
  z-index: 10;
}

.modal-close-membership:hover {
  background: rgba(255,255,255,0.2);
  transform: rotate(90deg);
}

.modal-header-membership {
  text-align: center;
  margin-bottom: 36px;
}

.modal-icon-membership {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  background: linear-gradient(135deg, rgba(238,62,70,0.25), rgba(238,62,70,0.1));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 32px rgba(238,62,70,0.3);
}

.modal-icon-membership i {
  font-size: 2.2rem;
  color: #EE3E46;
}

.modal-header-membership h2 {
  margin: 0 0 12px;
  color: #fff;
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.modal-subtitle {
  margin: 0;
  color: rgba(255,255,255,0.7);
  font-size: 1.15rem;
  font-weight: 500;
}

.form-group-membership {
  margin-bottom: 32px;
}

.form-label-membership {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  color: #fff;
  font-weight: 600;
  font-size: 1rem;
}

.form-label-membership i {
  color: #EE3E46;
  font-size: 0.9rem;
}

.form-input-membership {
  width: 100%;
  padding: 16px 20px;
  background: rgba(255,255,255,0.06);
  border: 2px solid rgba(255,255,255,0.15);
  border-radius: 14px;
  color: #fff;
  font-size: 1.4rem;
  font-weight: 700;
  transition: all 0.3s;
  box-sizing: border-box;
  text-align: center;
}

.form-input-membership::placeholder {
  color: rgba(255,255,255,0.3);
  font-weight: 600;
}

.form-input-membership:focus {
  outline: none;
  border-color: #EE3E46;
  background: rgba(255,255,255,0.08);
  box-shadow: 0 0 0 4px rgba(238,62,70,0.15);
}

.modal-actions-membership {
  display: flex;
  gap: 14px;
  margin-top: 32px;
}

.btn-cancel-membership,
.btn-save-membership {
  flex: 1;
  padding: 16px 28px;
  border: none;
  border-radius: 14px;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  letter-spacing: 0.02em;
}

.btn-cancel-membership {
  background: rgba(255,255,255,0.08);
  color: #fff;
  border: 2px solid rgba(255,255,255,0.15);
}

.btn-cancel-membership:hover {
  background: rgba(255,255,255,0.12);
  border-color: rgba(255,255,255,0.25);
  transform: translateY(-2px);
}

.btn-save-membership {
  background: linear-gradient(135deg, #EE3E46, #CC2E36);
  color: #fff;
  border: 2px solid transparent;
  box-shadow: 0 4px 20px rgba(238,62,70,0.3);
}

.btn-save-membership:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(238,62,70,0.5);
}

.btn-save-membership:active,
.btn-cancel-membership:active {
  transform: translateY(0);
}

/* Animaciones */
@keyframes fadeInMembership {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUpMembership {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideDownMembership {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .membership-header-card {
    flex-direction: column;
    text-align: center;
    padding: 24px;
  }

  .membership-header-text h2 {
    font-size: 1.3rem;
  }

  .membership-grid {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .membership-card {
    padding: 28px 20px;
  }

  .membership-card-icon {
    width: 70px;
    height: 70px;
  }

  .membership-card-icon i {
    font-size: 2rem;
  }

  .price-value {
    font-size: 2rem;
  }

  .modal-content-membership {
    padding: 32px 24px;
  }

  .modal-header-membership h2 {
    font-size: 1.5rem;
  }

  .modal-actions-membership {
    flex-direction: column;
  }

  .btn-cancel-membership,
  .btn-save-membership {
    width: 100%;
  }
}

@media (max-width: 480px) {
  .page-title {
    font-size: 1.5rem;
  }

  .membership-header-card {
    padding: 20px;
  }

  .membership-header-icon {
    width: 56px;
    height: 56px;
  }

  .membership-header-icon i {
    font-size: 1.5rem;
  }

  .membership-card-title {
    font-size: 1.3rem;
  }

  .price-value {
    font-size: 1.8rem;
  }

  .modal-content-membership {
    padding: 28px 20px;
  }

  .modal-icon-membership {
    width: 64px;
    height: 64px;
  }

  .modal-icon-membership i {
    font-size: 1.75rem;
  }
}
</style>

<script>
const modal = document.getElementById('modalEditarPrecio');
const btnCerrar = document.getElementById('btnCerrarModal');
const btnCancelar = document.getElementById('btnCancelar');
const inputPrecio = document.getElementById('precio');
const formEditarPrecio = document.getElementById('formEditarPrecio');

function editarPrecio(id, nombre, precioActual) {
  console.log('Editando precio:', { id, nombre, precioActual });
  
  document.getElementById('idPrecio').value = id;
  document.getElementById('nombreMembresia').textContent = 'Membresía ' + nombre;
  document.getElementById('precio').value = precioActual;
  
  // Verificar que los valores se asignaron correctamente
  console.log('Valores asignados:', {
    idPrecio: document.getElementById('idPrecio').value,
    precio: document.getElementById('precio').value
  });
  
  // Mostrar modal con animación
  modal.style.display = 'flex';
  setTimeout(() => {
    modal.classList.add('show');
  }, 10);
  
  // Enfocar el input después de la animación
  setTimeout(() => {
    inputPrecio.focus();
    inputPrecio.select();
  }, 300);
  
  // Bloquear scroll del body
  document.body.style.overflow = 'hidden';
}

function cerrarModal() {
  modal.classList.remove('show');
  setTimeout(() => {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }, 300);
}

// Event listeners
btnCerrar.addEventListener('click', cerrarModal);
btnCancelar.addEventListener('click', cerrarModal);

// Cerrar al hacer clic fuera del modal
modal.addEventListener('click', (e) => {
  if (e.target === modal) {
    cerrarModal();
  }
});

// Cerrar con tecla ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modal.classList.contains('show')) {
    cerrarModal();
  }
});

// Prevenir cierre accidental al hacer clic dentro del contenido
document.querySelector('.modal-content-membership').addEventListener('click', (e) => {
  e.stopPropagation();
});

// Debug del formulario antes de enviar
formEditarPrecio.addEventListener('submit', function(e) {
  const formData = new FormData(this);
  console.log('Enviando formulario con datos:', {
    id_precio: formData.get('id_precio'),
    precio: formData.get('precio')
  });
  // No prevenir el envío, solo loguear
});
</script>

<?php include __DIR__ . '/../shared/footer_admin.php'; ?>

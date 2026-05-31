<?php require __DIR__ . '/../layouts/header.php'; ?>



<div class="auth-hero">
  <div class="login-grid">
    
    <!-- Lado Izquierdo: Animación y Texto -->
    <div class="login-graphic reveal">
      <div class="login-text-content">
        <h1>Gestión<br><span style="color:var(--color-brand)">Local Deportiva</span></h1>
        <p>Accede al panel de control de Pomplay para gestionar grabaciones y las canchas afiliadas.</p>
      </div>
    </div>

    <!-- Lado Derecho: Formulario -->
    <div class="login-form-wrapper reveal">
      <div class="glass-card">
        <h2>Panel de <span style="color:var(--color-brand)">Administración</span></h2>
        
        <?php if (!empty($error)): ?>
          <div style="background: rgba(236, 66, 55, 0.2); border: 1px solid var(--color-brand); padding: 12px; border-radius: 8px; margin-bottom: 20px; color: #fff; font-size: 0.85rem; text-align: left;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/login.php" method="POST" autocomplete="off">
          <div class="form-group">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" placeholder="Ingresa tu usuario" />
          </div>
          <div class="form-group">
            <label for="pass">Contraseña</label>
            <input type="password" id="pass" name="pass" required placeholder="Ingresa tu contraseña" />
          </div>
          <button type="submit" class="glass-btn">Iniciar Sesión &nbsp;</button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

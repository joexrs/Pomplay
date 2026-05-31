<?php require __DIR__ . '/../layouts/header.php'; ?>

<!-- Memberships Page -->
<div class="memberships-hero reveal">
  <div class="hero-content">
    <h1>Nuestras <span>Membresías</span></h1>
    <p>Selecciona el plan que mejor se adapte a tu local y empieza a ofrecer contenido profesional.</p>
    <div class="hero-divider"></div>
  </div>
</div>

<?php if (!empty($memberships)): ?>
<section class="membership-section reveal">
  

  <div class="membership-grid">
    <?php 

    
    foreach ($memberships as $index => $plan): 
      $isFeatured = isset($plan['tipo_membresia']) && $plan['tipo_membresia'] === 'PREMIUM';
      $icon = isset($plan['tipo_membresia']) && isset($icons[$plan['tipo_membresia']]) ? $icons[$plan['tipo_membresia']] : 'fa-certificate';
    ?>
    <div class="membership-card <?= $isFeatured ? 'featured' : '' ?> reveal" style="animation-delay: <?= $index * 0.1 ?>s">
      <?php if ($isFeatured): ?>
        <div class="membership-badge">
          <i class="fas fa-award"></i>
          Más Popular
        </div>
      <?php endif; ?>
      
      <div class="membership-header-card">
        <div class="membership-icon">
          <i class="fas <?= htmlspecialchars($icon) ?>"></i>
        </div>
        <h3 class="membership-name"><?= htmlspecialchars($plan['nombre_display'] ?? 'Plan') ?></h3>
      </div>
      
      <div class="membership-period">
        <span class="membership-duration"><?= isset($plan['duracion_meses']) ? $plan['duracion_meses'] : '1' ?></span>
        <span class="membership-unit"><?= (isset($plan['duracion_meses']) && $plan['duracion_meses'] == 1) ? 'mes' : 'meses' ?></span>
      </div>
      
      <div class="membership-pricing">
        <div class="membership-price">
          <span class="membership-currency">S/</span>
          <span class="membership-amount"><?= number_format(isset($plan['precio']) ? $plan['precio'] : 0, 2) ?></span>
        </div>
        <span class="membership-period-price">al <?= (isset($plan['duracion_meses']) && $plan['duracion_meses'] == 1) ? 'mes' : 'período' ?></span>
      </div>
      
      <p class="membership-description">
        <?= htmlspecialchars($plan['descripcion'] ?? 'Acceso completo a todas las funcionalidades de la plataforma durante ' . (isset($plan['duracion_meses']) ? $plan['duracion_meses'] : '1') . ' ' . ((isset($plan['duracion_meses']) && $plan['duracion_meses'] == 1) ? 'mes' : 'meses')) ?>
      </p>

      <ul class="membership-features">
        <li><i class="fas fa-check-circle"></i> Grabación de partidos</li>
        <li><i class="fas fa-check-circle"></i> Partidos subidos en menos de 24hrs</li>
        <li><i class="fas fa-check-circle"></i> Distribución digital</li>
        <li><i class="fas fa-check-circle"></i> Acceso prioritario</li>
      </ul>
      
      <a href="https://wa.me/51944957261?text=Hola,%20estoy%20interesado%20en%20el%20plan%20<?= urlencode($plan['nombre_display'] ?? 'de membresía') ?>" target="_blank" rel="noopener noreferrer" class="membership-cta">
        Comenzar ahora
        <i class="fab fa-whatsapp"></i>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php else: ?>
<section class="membership-empty">
  <p>No hay planes disponibles en este momento.</p>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

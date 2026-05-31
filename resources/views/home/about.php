<?php require __DIR__ . '/../layouts/header.php'; ?>

<!-- Hero Section -->
<div class="about-hero reveal">
  <div class="hero-content">
    <h1>Vive el partido. <br>Revivelo en <span>Pomplay</span></h1>
    <p>Elevando el valor de cada partido local.</p>
    <div class="hero-divider"></div>
  </div>
</div>

<!-- Bento Grid Section -->
<section class="bento-grid">
  
  <!-- Misión -->
  <div class="bento-item bento-mision reveal">
    <div class="glow-red"></div>
    <div class="pattern-dots"></div>
    <div class="bento-icon-wrapper">
      
    </div>
    <h3 class="bento-title">Misión</h3>
    <p class="bento-text">
      Transformar partidos locales en experiencias deportivas que conecten a jugadores, de equipos y aficionados, elevando el valor de cada encuentro.
    </p>
  </div>

  <!-- Video de referencia -->
  <div class="bento-item bento-image reveal">
    <div class="pattern-dots"></div>
    <div class="pattern-diagonal"></div>
    <video src="<?= $baseUrl ?>/public/images/pomplay logo.png" class="pomplay-logo-large" alt="Pomplay Logo" onerror="this.src='<?= $baseUrl ?>/public/img/logo.png'" />
  </div>

  <!-- Visión -->
  <div class="bento-item bento-vision reveal">
    <div class="glow-purple"></div>
    <div class="pattern-waves"></div>
    <div class="bento-icon-wrapper">
      
    </div>
    <h3 class="bento-title">Visión</h3>
    <p class="bento-text">
      Ser la plataforma lider que impulse el deporte una nueva forma de vivir y compartir el deporte amaterur en Latinoamerica. 
    </p>
  </div>

  <!-- Valores -->
  <div class="bento-item bento-valores reveal">
    <div class="glow-red"></div>
    <div class="pattern-lines"></div>
    <div class="bento-icon-wrapper">
      
    </div>
    <h3 class="bento-title">Valores</h3>
    <p class="bento-text">
      Pasion, Comunidad, Innovacion, Accesibilidad, Compromiso. 
    </p>
  </div>

</section>

<!-- CTA al final de la página -->
<div class="about-cta-footer">
  <a href="<?= $baseUrl ?>/memberships.php" class="btn-hero-memberships">MEMBRESÍAS</a>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

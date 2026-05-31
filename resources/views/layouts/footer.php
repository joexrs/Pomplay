    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <a href="<?= $baseUrl ?>/index.php" class="nav-brand" style="display:inline-flex;margin-bottom:.75rem;">
                    <span class="brand-icon"><img src="<?= $baseUrl ?>/public/images/pomplay%20sin%20fondo.png" alt="Pomplay Logo" style="height: 48px; object-fit: contain; padding-bottom: 8px;"></span>
                </a>
                <p>La plataforma que transforma cada partido local en una experiencia deportiva.</p>
            </div>
            <div class="footer-section">
                <h4>Navegación</h4>
                <ul class="footer-links">
                    <li><a href="<?= $baseUrl ?>/index.php"><i class="fas fa-play-circle"></i> Inicio</a></li>
                    <li><a href="<?= $baseUrl ?>/about.php"><i class="fas fa-info-circle"></i> Nosotros</a></li>
                    <li><a href="<?= $baseUrl ?>/login.php"><i class="fas fa-lock"></i> Acceso </a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contacto</h4>
                <ul class="footer-links">
                    <li><a href="mailto:pomplaycix@gmail.com"><i class="fas fa-envelope"></i> pomplaycix@gmail.com </a></li>
                    <li><a href="tel:+51944957261"><i class="fas fa-phone"></i> +51944957261</a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i> @pomplay</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Pomplay. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Botón Flotante de WhatsApp -->
    <a href="https://wa.me/+51944957261?text=Hola,%20tengo%20una%20consulta%20sobre%20Pomplay" 
       class="whatsapp-float" 
       target="_blank" 
       rel="noopener noreferrer"
       aria-label="Contactar por WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <script src="<?= $baseUrl ?>/public/js/responsive-menu.js?v=1.2"></script>
    <script src="<?= $baseUrl ?>/public/js/main-v2.js?v=1.1"></script>
        <script>
            // Reveal bento items when they enter the viewport
            (function(){
                try {
                    const observer = new IntersectionObserver((entries, obs) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('visible');
                                obs.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.12 });
                    document.querySelectorAll('.bento-grid .reveal').forEach(el => observer.observe(el));
                } catch (e) { console.warn('Reveal observer not supported', e); }
            })();
        </script>
    
   
    
    <!-- Limpiar localStorage al hacer logout -->
    <script>
        document.addEventListener('click', function(e) {
            const logoutLink = e.target.closest('a[href*="logout.php"]');
            if (logoutLink) {
                e.preventDefault();
                // Limpiar todo el localStorage y sessionStorage
                localStorage.clear();
                sessionStorage.clear();
                // Redirigir al logout
                window.location.href = logoutLink.href;
            }
        });
    </script>
</body>
</html>

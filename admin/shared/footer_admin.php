    </main>
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?= $baseUrl ?>/public/js/bootstrap.min.js"></script>
    <script src="<?= $baseUrl ?>/public/js/plugins.js"></script>
    
    <!-- DataTables -->
    <script src="<?= $baseUrl ?>/public/datatables/datatables.min.js"></script>
    <script src="<?= $baseUrl ?>/public/datatables/exportar.js"></script>

    <!-- 📅 Flatpickr - Date & Time Picker -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script src="<?= $baseUrl ?>/public/js/flatpickr-init.js"></script>

    <!-- Chart.js para gráficos -->
    <script src="<?= $baseUrl ?>/public/js/chart.js"></script>

    <!-- Main JS (SideBar Fix) -->
    <script src="<?= $baseUrl ?>/public/js/main.js?v=1.5"></script>

    <!-- Detección de cambio de sesión entre pestañas -->
    <script>
        (function() {
            // Guardar el rol del usuario actual al cargar la página
            const currentUserRole = '<?= $_SESSION['rol'] ?? '' ?>';
            const currentUserId = '<?= $_SESSION['user_id'] ?? '' ?>';
            const sessionKey = 'pomplay_session_' + currentUserId + '_' + currentUserRole;
            
            // Marcar esta sesión como activa
            sessionStorage.setItem('currentSession', sessionKey);
            
            // Limpiar localStorage relacionado con el sidebar al cargar la página
            if (!sessionStorage.getItem('pageLoadedOnce')) {
                localStorage.removeItem('sidebarOpen');
                sessionStorage.setItem('pageLoadedOnce', 'true');
            }

            // Función para verificar si la sesión cambió
            function checkSessionChange() {
                fetch('<?= $baseUrl ?>/admin/shared/check_session.php', {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                })
                .then(response => response.json())
                .then(data => {
                    const newSessionKey = 'pomplay_session_' + data.user_id + '_' + data.rol;
                    const storedSession = sessionStorage.getItem('currentSession');
                    
                    // Si la sesión cambió o no existe, recargar la página
                    if (!data.authenticated || (storedSession && storedSession !== newSessionKey)) {
                        console.log('Sesión cambió, recargando página...');
                        localStorage.clear();
                        sessionStorage.clear();
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Error verificando sesión:', err);
                });
            }

            // Verificar sesión cuando la pestaña recupera el foco
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    checkSessionChange();
                }
            });

            // Verificar sesión cuando la ventana recupera el foco
            window.addEventListener('focus', function() {
                checkSessionChange();
            });

            // Escuchar eventos de storage (cuando otra pestaña hace logout)
            window.addEventListener('storage', function(e) {
                if (e.key === 'logout_event' || e.key === null) {
                    console.log('Logout detectado en otra pestaña');
                    localStorage.clear();
                    sessionStorage.clear();
                    window.location.href = '<?= $baseUrl ?>/login.php';
                }
            });

            // Detectar si se vuelve atrás después de logout
            window.addEventListener('pageshow', function(event) {
                if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                    localStorage.removeItem('sidebarOpen');
                    sessionStorage.clear();
                    window.location.reload();
                }
            });

            // Limpiar localStorage al hacer clic en logout
            document.addEventListener('click', function(e) {
                const logoutLink = e.target.closest('a[href*="logout.php"]');
                if (logoutLink) {
                    // Notificar a otras pestañas que se está haciendo logout
                    localStorage.setItem('logout_event', Date.now());
                    localStorage.clear();
                    sessionStorage.clear();
                }
            });
        })();
    </script>

</body>

</html>



class ResponsiveMenu {
    constructor() {
        this.navToggle = document.getElementById('navToggle');
        this.navLinks = document.getElementById('navLinks');
        this.siteNav = document.getElementById('siteNav');
        this.adminSidebar = document.querySelector('.app-sidebar');
        this.adminSidebarToggle = document.querySelector('.app-sidebar__toggle');
        this.adminSidebarOverlay = document.querySelector('.app-sidebar__overlay');
        this.appBody = document.querySelector('body.app');
        // Desktop nav trigger
        this.navDesktopTrigger = document.getElementById('navDesktopTrigger');

        this.isOpen = false;
        this.isDesktopOpen = false;
        this.init();
    }

    init() {
        
        
        // Menú principal (header)
        if (this.navToggle && this.navLinks) {

            
            this.navToggle.addEventListener('click', (e) => {
              
                this.toggleMainMenu();
            });
            
            // Cerrar menú cuando hace click en un link
            this.navLinks.querySelectorAll('a').forEach((link, index) => {
               
                
                // Agregar múltiples eventos para debug
                link.addEventListener('click', (e) => {
                   
                    this.closeMainMenu();
                });
                
                link.addEventListener('mouseenter', (e) => {
                   
                });
                
                link.addEventListener('touchstart', (e) => {
                    
                });
            });

            // Cerrar menú cuando hace click en el overlay (::before del body)
            document.addEventListener('click', (e) => {
                // Si el menú está abierto y se hace clic fuera del nav
                if (this.isOpen) {
                    const clickedInLinks = this.navLinks.contains(e.target);
                    const clickedInToggle = this.navToggle.contains(e.target);
                    const clickedInNav = this.siteNav.contains(e.target);
                    
                    
                    
                    if (!clickedInLinks && !clickedInToggle && !clickedInNav) {
                       
                        this.closeMainMenu();
                    }
                }
            });

            // Tecla ESC para cerrar menú
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
            
                    this.closeMainMenu();
                }
            });
        } else {
            
        }

        // Menú admin sidebar - Solo para páginas que NO tienen jQuery/main.js
        // Si existe jQuery, main.js ya maneja la sidebar
        if (typeof jQuery === 'undefined' && this.adminSidebarToggle && this.appBody) {
            
            
            this.adminSidebarToggle.addEventListener('click', (e) => {
                e.preventDefault();
                
                this.toggleAdminSidebar();
            });
            
            // Cerrar sidebar al hacer click en el overlay
            if (this.adminSidebarOverlay) {
                this.adminSidebarOverlay.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    this.closeAdminSidebar();
                });
            }
            
            // Cerrar sidebar con tecla ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.appBody.classList.contains('sidenav-toggled')) {
                    this.closeAdminSidebar();
                }
            });
        } else {
            if (typeof jQuery !== 'undefined') {
                    
            }
        }

        // ── Desktop nav trigger (inline reveal) ─────────────────
        if (this.navDesktopTrigger && this.navLinks) {
            this.navDesktopTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                if (window.innerWidth > 768) {
                    this.toggleDesktopNav();
                }
            });

            // Cerrar al hacer click en un enlace
            this.navLinks.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    if (window.innerWidth > 768) {
                        this.closeDesktopNav();
                    }
                });
            });

            // Cerrar al hacer click fuera del nav
            document.addEventListener('click', (e) => {
                if (this.isDesktopOpen && !this.siteNav.contains(e.target)) {
                    this.closeDesktopNav();
                }
            });

            // Tecla ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isDesktopOpen) {
                    this.closeDesktopNav();
                }
            });
        }

        // Manejar cambios de tamaño de ventana
        window.addEventListener('resize', () => this.handleResize());
        
        
    }

    /**
     * Toggle main menu
     */
    toggleMainMenu() {
        
        if (this.isOpen) {
            this.closeMainMenu();
        } else {
            this.openMainMenu();
        }
    }

    /**
     * Open main menu
     */
    openMainMenu() {
        
        this.navToggle.classList.add('active');
        this.navLinks.classList.add('active');
        this.siteNav.classList.add('menu-open');
        document.body.classList.add('menu-open');
        this.isOpen = true;

        // Prevenir scroll del body
        document.body.style.overflow = 'hidden';
        
       
    }

    /**
     * Close main menu
     */
    closeMainMenu() {
        
        this.navToggle.classList.remove('active');
        this.navLinks.classList.remove('active');
        this.siteNav.classList.remove('menu-open');
        document.body.classList.remove('menu-open');
        this.isOpen = false;

        // Restaurar scroll del body
        document.body.style.overflow = '';
        
        
    }

    /**
     * Toggle admin sidebar
     */
    toggleAdminSidebar() {
        
        if (this.appBody) {
            this.appBody.classList.toggle('sidenav-toggled');
            
            // Prevenir scroll cuando sidebar está abierta
            if (this.appBody.classList.contains('sidenav-toggled')) {
                document.body.style.overflow = 'hidden';
                
            } else {
                document.body.style.overflow = '';
                
            }
        }
    }

    /**
     * Close admin sidebar
     */
    closeAdminSidebar() {
        
        if (this.appBody) {
            this.appBody.classList.remove('sidenav-toggled');
            document.body.style.overflow = '';
        }
    }

    /**
     * Toggle desktop inline nav
     */
    toggleDesktopNav() {
        if (this.isDesktopOpen) {
            this.closeDesktopNav();
        } else {
            this.openDesktopNav();
        }
    }

    /**
     * Open desktop inline nav (links appear inside navbar)
     */
    openDesktopNav() {
        this.navLinks.classList.add('desktop-open');
        this.navDesktopTrigger.classList.add('is-open');
        this.isDesktopOpen = true;
    }

    /**
     * Close desktop inline nav
     */
    closeDesktopNav() {
        this.navLinks.classList.remove('desktop-open');
        this.navDesktopTrigger.classList.remove('is-open');
        this.isDesktopOpen = false;
    }

    /**
     * Manejar cambios de tamaño de ventana
     */
    handleResize() {
        const width = window.innerWidth;
        
        // Si la pantalla es lo suficientemente grande, cerrar menú móvil
        if (width > 768 && this.isOpen) {
            this.closeMainMenu();
        }
        // Si la pantalla es pequeña, cerrar sidebar desktop
        if (width <= 768 && this.isDesktopOpen) {
            this.closeDesktopSidebar();
        }
    }
}

// Inicializar cuando DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    
    new ResponsiveMenu();
});

// Manejar animación de carga
window.addEventListener('load', () => {
   
    const loaderWrapper = document.getElementById('loader-wrapper');
    if (loaderWrapper) {
        loaderWrapper.style.opacity = '0';
        loaderWrapper.style.visibility = 'hidden';
        loaderWrapper.style.transition = 'opacity 0.3s ease, visibility 0.3s ease';
        
    }
});

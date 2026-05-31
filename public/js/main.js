(function () {
	"use strict";

	console.log('Main.js cargado - Inicializando sidebar');

	var treeviewMenu = $('.app-menu');

	// Toggle Sidebar - Improved for mobile
	$('[data-toggle="sidebar"], #sidebarToggleBtn').click(function(event) {
		console.log('Sidebar toggle clicked');
		event.preventDefault();
		event.stopPropagation();
		
		// Usar classList nativo de JavaScript para mayor confiabilidad
		var body = document.body;
		var app = document.querySelector('.app');
		
		console.log('Antes del toggle:');
		console.log('  Body tiene sidenav-toggled:', body.classList.contains('sidenav-toggled'));
		console.log('  App tiene sidenav-toggled:', app ? app.classList.contains('sidenav-toggled') : 'No encontrado');
		console.log('  Body classList:', body.classList);
		console.log('  App classList:', app ? app.classList : 'No encontrado');
		
		// Intentar toggle de múltiples formas
		try {
			// Método 1: classList.toggle
			body.classList.toggle('sidenav-toggled');
			if (app) {
				app.classList.toggle('sidenav-toggled');
			}
			console.log('Método 1 (toggle) ejecutado');
		} catch(e) {
			console.error('Error en método 1:', e);
		}
		
		// Verificar si funcionó
		var hasClass = body.classList.contains('sidenav-toggled');
		console.log('Después del toggle, tiene clase:', hasClass);
		
		// Si no funcionó, forzar con add/remove
		if (!hasClass) {
			console.log('Toggle no funcionó, intentando add...');
			try {
				body.classList.add('sidenav-toggled');
				if (app) {
					app.classList.add('sidenav-toggled');
				}
				console.log('Clase agregada con add()');
			} catch(e) {
				console.error('Error agregando clase:', e);
				// Último recurso: modificar className directamente
				body.className = body.className + ' sidenav-toggled';
				if (app) {
					app.className = app.className + ' sidenav-toggled';
				}
				console.log('Clase agregada modificando className directamente');
			}
		} else {
			// Si ya tiene la clase, quitarla
			console.log('Ya tiene la clase, removiendo...');
			body.classList.remove('sidenav-toggled');
			if (app) {
				app.classList.remove('sidenav-toggled');
			}
		}
		
		console.log('Después de todo:');
		console.log('  Body tiene sidenav-toggled:', body.classList.contains('sidenav-toggled'));
		console.log('  App tiene sidenav-toggled:', app ? app.classList.contains('sidenav-toggled') : 'No encontrado');
		
		// Log del estado actual
		var isOpen = body.classList.contains('sidenav-toggled');
		console.log('Sidebar estado:', isOpen ? 'ABIERTO' : 'CERRADO');
		console.log('Body classes:', body.className);
		console.log('App classes:', app ? app.className : 'No encontrado');

		// Prevenir scroll en móvil cuando sidebar está abierto
		if (isOpen) {
			body.style.overflow = 'hidden';
		} else {
			body.style.overflow = '';
		}
	});

	// Activate sidebar treeview toggle - mejorado para móviles
	$("[data-toggle='treeview']").click(function(event) {
		event.preventDefault();
		
		var $parent = $(this).parent();
		var wasExpanded = $parent.hasClass('is-expanded');
		
		// En móviles, permitir múltiples menús abiertos
		// En desktop, cerrar otros menús
		if (window.innerWidth >= 768) {
			if(!wasExpanded) {
				treeviewMenu.find("[data-toggle='treeview']").parent().removeClass('is-expanded');
			}
		}
		
		$parent.toggleClass('is-expanded');
		
		// Asegurar que el menú se muestre correctamente
		var $treeviewMenu = $parent.find('.treeview-menu');
		if ($parent.hasClass('is-expanded')) {
			$treeviewMenu.slideDown(300);
		} else {
			$treeviewMenu.slideUp(300);
		}
	});

	// Prevenir que los elementos del menú mantengan el foco después del clic
	$('.app-menu__item, .treeview-item').on('click', function() {
		// Remover el foco del elemento después de un breve delay
		var $this = $(this);
		setTimeout(function() {
			$this.blur();
		}, 100);
	});
	
	// Cerrar sidebar al hacer clic en overlay
	$(document).on('click', '.app-sidebar__overlay, #sidebarOverlay', function() {
		console.log('Overlay clicked - cerrando sidebar');
		var body = document.body;
		var app = document.querySelector('.app');
		
		body.classList.remove('sidenav-toggled');
		if (app) {
			app.classList.remove('sidenav-toggled');
		}
		body.style.overflow = '';
	});
	
	// Cerrar sidebar al hacer clic en un enlace en móviles
	if (window.innerWidth < 768) {
		$('.app-menu__item:not([data-toggle="treeview"]), .treeview-item').on('click', function() {
			// Solo cerrar si no es un treeview toggle
			if (!$(this).attr('data-toggle')) {
				setTimeout(function() {
					var body = document.body;
					var app = document.querySelector('.app');
					
					body.classList.remove('sidenav-toggled');
					if (app) {
						app.classList.remove('sidenav-toggled');
					}
					body.style.overflow = '';
				}, 200);
			}
		});
	}
	
	// Ajustar comportamiento al cambiar tamaño de ventana
	var resizeTimer;
	$(window).on('resize', function() {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function() {
			// Si cambiamos a desktop, cerrar sidebar móvil
			if (window.innerWidth >= 768) {
				var body = document.body;
				var app = document.querySelector('.app');
				
				body.classList.remove('sidenav-toggled');
				if (app) {
					app.classList.remove('sidenav-toggled');
				}
				body.style.overflow = '';
			}
		}, 250);
	});

	console.log('Main.js inicializado correctamente');
	console.log('Botones sidebar encontrados:', $('[data-toggle="sidebar"], #sidebarToggleBtn').length);
	console.log('Body inicial classes:', document.body.className);
	console.log('App inicial classes:', document.querySelector('.app') ? document.querySelector('.app').className : 'No encontrado');

})();
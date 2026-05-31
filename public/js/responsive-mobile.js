/**
 * POMPLAY - JavaScript para Responsividad Móvil
 * Mejoras de interactividad y UX en dispositivos móviles
 */

(function($) {
  'use strict';

  // ===== DETECCIÓN DE DISPOSITIVO =====
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

  // ===== SIDEBAR MÓVIL =====
  function initMobileSidebar() {
    // El toggle del sidebar ya funciona con el main.js existente
    // Solo agregamos funcionalidad adicional para móvil
    
    const $body = $('body.app, .app');
    const $overlay = $('.app-sidebar__overlay');
    const $sidebar = $('.app-sidebar');

    // Crear overlay si no existe
    if ($overlay.length === 0 && $sidebar.length > 0) {
      $('<div class="app-sidebar__overlay" data-toggle="sidebar"></div>').insertAfter($sidebar);
    }

    // Prevenir scroll del body cuando sidebar está abierto en móvil
    // Este código se ejecuta después del toggle de main.js
    $(document).on('click', '.app-sidebar__toggle, [data-toggle="sidebar"]', function() {
      setTimeout(function() {
        if ($body.hasClass('sidenav-toggled')) {
          $('body').css('overflow', 'hidden');
        } else {
          $('body').css('overflow', '');
        }
      }, 50);
    });

    // Cerrar sidebar con tecla ESC
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape' && $body.hasClass('sidenav-toggled')) {
        $body.removeClass('sidenav-toggled');
        $('body').css('overflow', '');
      }
    });
  }

  // ===== TREEVIEW SIDEBAR =====
  function initTreeview() {
    // El treeview ya funciona con main.js
    // Solo agregamos animación suave para el menú
    $('[data-toggle="treeview"]').on('click', function() {
      const $parent = $(this).parent();
      const $menu = $parent.find('.treeview-menu');
      
      if ($menu.length) {
        if ($parent.hasClass('is-expanded')) {
          $menu.css('max-height', $menu[0].scrollHeight + 'px');
        } else {
          $menu.css('max-height', '0');
        }
      }
    });
  }

  // ===== DROPDOWN BOOTSTRAP =====
  function initDropdowns() {
    // Mejorar dropdowns para móvil
    $('[data-bs-toggle="dropdown"]').on('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const $parent = $(this).closest('.dropdown');
      const $menu = $parent.find('.dropdown-menu');
      
      // Cerrar otros dropdowns
      $('.dropdown-menu.show').not($menu).removeClass('show');
      
      // Toggle este dropdown
      $menu.toggleClass('show');
    });
    
    // Cerrar dropdown al hacer clic fuera
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.dropdown').length) {
        $('.dropdown-menu.show').removeClass('show');
      }
    });
  }

  // ===== TABLAS RESPONSIVAS - INDICADOR DE SCROLL =====
  function initTableScrollIndicator() {
    $('.table-wrapper').each(function() {
      const $wrapper = $(this);
      const $table = $wrapper.find('table');
      
      if (!$table.length) return;
      
      function checkScroll() {
        const hasScroll = $wrapper[0].scrollWidth > $wrapper[0].clientWidth;
        const isAtEnd = $wrapper.scrollLeft() >= ($wrapper[0].scrollWidth - $wrapper[0].clientWidth - 10);
        
        if (hasScroll && !isAtEnd && $(window).width() < 1024) {
          $wrapper.addClass('has-scroll');
        } else {
          $wrapper.removeClass('has-scroll');
        }
      }
      
      checkScroll();
      $wrapper.on('scroll', checkScroll);
      $(window).on('resize', checkScroll);
    });
  }

  // ===== CONFIRMACIÓN DE ELIMINACIÓN MEJORADA =====
  function initDeleteConfirmation() {
    // Mejorar confirmaciones en móvil
    $('a[onclick*="confirm"]').each(function() {
      const $link = $(this);
      const onclickAttr = $link.attr('onclick');
      
      if (onclickAttr) {
        // Extraer el mensaje del confirm
        const match = onclickAttr.match(/confirm\(['"](.+?)['"]\)/);
        const message = match ? match[1] : '¿Estás seguro de eliminar este elemento?';
        
        // Remover onclick y agregar nuevo handler
        $link.removeAttr('onclick');
        
        $link.on('click', function(e) {
          e.preventDefault();
          
          if (confirm(message)) {
            window.location.href = $link.attr('href');
          }
        });
      }
    });
  }

  // ===== INPUTS - PREVENIR ZOOM EN iOS =====
  function preventIOSZoom() {
    if (!/(iPhone|iPad|iPod)/i.test(navigator.userAgent)) return;
    
    $('input, select, textarea').each(function() {
      const fontSize = parseFloat($(this).css('font-size'));
      
      // Si el font-size es menor a 16px, ajustarlo
      if (fontSize < 16) {
        $(this).css('font-size', '16px');
      }
    });
  }

  // ===== SMOOTH SCROLL PARA ANCLAS =====
  function initSmoothScroll() {
    $('a[href^="#"]').on('click', function(e) {
      const href = $(this).attr('href');
      if (href === '#' || href === '#!') return;
      
      const $target = $(href);
      if ($target.length) {
        e.preventDefault();
        $('html, body').animate({
          scrollTop: $target.offset().top
        }, 500);
      }
    });
  }

  // ===== FEEDBACK TÁCTIL =====
  function initTouchFeedback() {
    if (!isTouch) return;
    
    $('button, .btn, .btn-icon, a.app-menu__item').on('touchstart', function() {
      $(this).css('opacity', '0.7');
    }).on('touchend touchcancel', function() {
      $(this).css('opacity', '');
    });
  }

  // ===== DATATABLES RESPONSIVE CONFIG =====
  function enhanceDataTables() {
    // Esperar a que DataTables esté disponible
    if (typeof $.fn.DataTable === 'undefined') return;
    
    // Configuración global para DataTables en móvil
    if (window.innerWidth < 768) {
      $.extend(true, $.fn.dataTable.defaults, {
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50], [10, 25, 50]],
        language: {
          search: '',
          searchPlaceholder: 'Buscar...',
          lengthMenu: '_MENU_',
          info: '_START_ - _END_ de _TOTAL_',
          infoEmpty: 'Sin resultados',
          infoFiltered: '(filtrado de _MAX_)',
          paginate: {
            first: '«',
            last: '»',
            next: '›',
            previous: '‹'
          }
        }
      });
    }
  }

  // ===== LAZY LOADING DE IMÁGENES =====
  function initLazyLoading() {
    if ('IntersectionObserver' in window) {
      const imageObserver = new IntersectionObserver(function(entries, observer) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            const $img = $(entry.target);
            $img.attr('src', $img.data('src'));
            $img.removeAttr('data-src');
            imageObserver.unobserve(entry.target);
          }
        });
      });
      
      $('img[data-src]').each(function() {
        imageObserver.observe(this);
      });
    }
  }

  // ===== DETECCIÓN DE ORIENTACIÓN =====
  function handleOrientationChange() {
    $(window).on('orientationchange', function() {
      // Recargar DataTables si existen
      if (typeof $.fn.DataTable !== 'undefined') {
        setTimeout(function() {
          $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }, 300);
      }
    });
  }

  // ===== PULL TO REFRESH (OPCIONAL) =====
  function initPullToRefresh() {
    if (!isMobile) return;
    
    let startY = 0;
    let currentY = 0;
    let pulling = false;
    
    $(document).on('touchstart', function(e) {
      if ($(window).scrollTop() === 0) {
        startY = e.originalEvent.touches[0].pageY;
        pulling = true;
      }
    });
    
    $(document).on('touchmove', function(e) {
      if (!pulling) return;
      currentY = e.originalEvent.touches[0].pageY;
      
      if (currentY - startY > 100) {
        // Aquí podrías mostrar un indicador de "suelta para recargar"
      }
    });
    
    $(document).on('touchend', function() {
      if (pulling && currentY - startY > 100) {
        // Recargar página
        // window.location.reload();
      }
      pulling = false;
    });
  }

  // ===== INICIALIZACIÓN =====
  $(document).ready(function() {
    console.log('🚀 Inicializando responsividad móvil...');

    // Inicializar componentes
    initMobileSidebar();
    initTreeview();
    initDropdowns();
    initTableScrollIndicator();
    initDeleteConfirmation();
    preventIOSZoom();
    initSmoothScroll();
    initTouchFeedback();
    enhanceDataTables();
    initLazyLoading();
    handleOrientationChange();
    // initPullToRefresh(); // Descomentado si se desea

    // Agregar clase al body si es móvil
    if (isMobile) {
      $('body').addClass('is-mobile');
    }
    if (isTouch) {
      $('body').addClass('is-touch');
    }

    console.log('✅ Responsividad móvil inicializada');
  });

})(jQuery);

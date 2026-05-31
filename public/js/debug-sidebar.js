/**
 * Script de Diagnóstico - Sidebar Móvil
 * Agregar temporalmente al footer para verificar funcionamiento
 */

(function($) {
  'use strict';
  
  console.log('🔍 Iniciando diagnóstico del sidebar...');
  
  // Verificar jQuery
  if (typeof $ === 'undefined') {
    console.error('❌ jQuery no está cargado');
  } else {
    console.log('✅ jQuery cargado:', $.fn.jquery);
  }
  
  // Verificar elementos
  const checks = {
    'Botón hamburguesa': '.app-sidebar__toggle, [data-toggle="sidebar"]',
    'Sidebar': '.app-sidebar',
    'Body/App': 'body, .app',
    'Overlay': '.app-sidebar__overlay'
  };
  
  console.log('\n📋 Verificando elementos:');
  Object.keys(checks).forEach(name => {
    const selector = checks[name];
    const $element = $(selector);
    if ($element.length > 0) {
      console.log(`✅ ${name}: ${$element.length} encontrado(s)`);
    } else {
      console.warn(`⚠️ ${name}: No encontrado`);
    }
  });
  
  // Verificar ancho de ventana
  const width = $(window).width();
  console.log(`\n📱 Ancho de ventana: ${width}px`);
  if (width < 768) {
    console.log('✅ Modo móvil activo');
  } else {
    console.log('ℹ️ Modo desktop activo');
  }
  
  // Verificar clases
  const $body = $('body, .app');
  console.log('\n🎨 Clases en body/app:', $body.attr('class'));
  
  // Agregar listener al botón
  $(document).on('click', '.app-sidebar__toggle, [data-toggle="sidebar"]', function(e) {
    console.log('🖱️ Click en botón hamburguesa');
    
    setTimeout(function() {
      const hasClass = $('body, .app').hasClass('sidenav-toggled');
      console.log('📊 Estado después del click:');
      console.log('  - sidenav-toggled:', hasClass);
      console.log('  - Sidebar transform:', $('.app-sidebar').css('transform'));
      console.log('  - Overlay visible:', $('.app-sidebar__overlay').css('visibility'));
    }, 100);
  });
  
  // Agregar listener al overlay
  $(document).on('click', '.app-sidebar__overlay', function() {
    console.log('🖱️ Click en overlay');
  });
  
  // Verificar CSS cargado
  const $sidebar = $('.app-sidebar');
  if ($sidebar.length > 0) {
    console.log('\n🎨 Estilos del sidebar:');
    console.log('  - Position:', $sidebar.css('position'));
    console.log('  - Transform:', $sidebar.css('transform'));
    console.log('  - Z-index:', $sidebar.css('z-index'));
    console.log('  - Width:', $sidebar.css('width'));
  }
  
  // Verificar archivos CSS
  console.log('\n📄 Archivos CSS cargados:');
  $('link[rel="stylesheet"]').each(function() {
    const href = $(this).attr('href');
    if (href && (href.includes('responsive') || href.includes('admin-modern'))) {
      console.log('  ✅', href);
    }
  });
  
  // Verificar archivos JS
  console.log('\n📄 Archivos JS cargados:');
  $('script[src]').each(function() {
    const src = $(this).attr('src');
    if (src && (src.includes('main.js') || src.includes('responsive'))) {
      console.log('  ✅', src);
    }
  });
  
  console.log('\n✨ Diagnóstico completado');
  console.log('💡 Tip: Abre DevTools en modo responsive (Ctrl+Shift+M) y prueba el botón hamburguesa');
  
})(jQuery);

/* platform-detect.js
   Añade clases al elemento raíz para detectar la plataforma.
   Esto permite aplicar estilos CSS específicos sin afectar otras plataformas.
*/
(function(){
  'use strict';

  try {
    var ua = navigator.userAgent || '';
    var isAndroid = /Android/i.test(ua);
    var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    
    if (isAndroid) {
      document.documentElement.classList.add('is-android');
      document.documentElement.classList.add('android');
    }
    
    if (isIOS) {
      document.documentElement.classList.add('is-ios');
    }
  } catch (e) {
    // No bloquear si algo sale mal
    console && console.warn && console.warn('platform-detect failed', e);
  }
}());

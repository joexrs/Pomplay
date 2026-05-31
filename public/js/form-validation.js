/**
 * Validaciones de formularios - PomPlay
 * Funciones reutilizables para validación de datos
 */

// Validar email
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// Validar teléfono (números, espacios, guiones y +)
function validarTelefono(telefono) {
    const regex = /^[\d\s\-+()]+$/;
    return regex.test(telefono);
}

// Validar que un campo no esté vacío
function validarRequerido(valor) {
    return valor && valor.trim().length > 0;
}

// Validar longitud mínima
function validarLongitudMinima(valor, minimo) {
    return valor && valor.trim().length >= minimo;
}

// Validar longitud máxima
function validarLongitudMaxima(valor, maximo) {
    return valor && valor.trim().length <= maximo;
}

// Validar que sea un número
function validarNumero(valor) {
    return !isNaN(valor) && valor.trim() !== '';
}

// Validar que sea un número positivo
function validarNumeroPositivo(valor) {
    return validarNumero(valor) && parseFloat(valor) > 0;
}

// Validar fecha (formato YYYY-MM-DD)
function validarFecha(fecha) {
    const regex = /^\d{4}-\d{2}-\d{2}$/;
    if (!regex.test(fecha)) return false;
    
    const date = new Date(fecha);
    return date instanceof Date && !isNaN(date);
}

// Validar que fecha2 sea posterior a fecha1
function validarFechaPosterior(fecha1, fecha2) {
    return new Date(fecha2) > new Date(fecha1);
}

// Validar código alfanumérico
function validarCodigoAlfanumerico(codigo) {
    const regex = /^[A-Za-z0-9]+$/;
    return regex.test(codigo);
}

// Mostrar mensaje de error
function mostrarError(mensaje) {
    alert(mensaje);
}

// Validar formulario genérico
function validarFormulario(formId, reglas) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    form.addEventListener('submit', function(e) {
        const errores = [];
        
        reglas.forEach(function(regla) {
            const campo = document.getElementById(regla.id);
            if (!campo) return;
            
            const valor = campo.value.trim();
            
            // Validar requerido
            if (regla.requerido && !validarRequerido(valor)) {
                errores.push(regla.nombre + ' es obligatorio');
                return;
            }
            
            // Si el campo está vacío y no es requerido, saltar otras validaciones
            if (!valor && !regla.requerido) return;
            
            // Validar tipo
            if (regla.tipo === 'email' && !validarEmail(valor)) {
                errores.push(regla.nombre + ' no es un email válido');
            }
            
            if (regla.tipo === 'telefono' && !validarTelefono(valor)) {
                errores.push(regla.nombre + ' no es un teléfono válido');
            }
            
            if (regla.tipo === 'numero' && !validarNumero(valor)) {
                errores.push(regla.nombre + ' debe ser un número');
            }
            
            if (regla.tipo === 'numero_positivo' && !validarNumeroPositivo(valor)) {
                errores.push(regla.nombre + ' debe ser un número positivo');
            }
            
            if (regla.tipo === 'fecha' && !validarFecha(valor)) {
                errores.push(regla.nombre + ' no es una fecha válida');
            }
            
            if (regla.tipo === 'alfanumerico' && !validarCodigoAlfanumerico(valor)) {
                errores.push(regla.nombre + ' solo puede contener letras y números');
            }
            
            // Validar longitud
            if (regla.minimo && !validarLongitudMinima(valor, regla.minimo)) {
                errores.push(regla.nombre + ' debe tener al menos ' + regla.minimo + ' caracteres');
            }
            
            if (regla.maximo && !validarLongitudMaxima(valor, regla.maximo)) {
                errores.push(regla.nombre + ' no puede exceder ' + regla.maximo + ' caracteres');
            }
        });
        
        if (errores.length > 0) {
            e.preventDefault();
            mostrarError(errores.join('\n'));
            return false;
        }
    });
}

// Agregar validación HTML5 a todos los formularios
document.addEventListener('DOMContentLoaded', function() {
    // Prevenir envío de formularios con campos inválidos
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                
                // Encontrar el primer campo inválido y enfocarlo
                const invalidField = form.querySelector(':invalid');
                if (invalidField) {
                    invalidField.focus();
                    
                    // Mostrar mensaje personalizado
                    if (invalidField.validity.valueMissing) {
                        mostrarError('Por favor complete todos los campos obligatorios');
                    } else if (invalidField.validity.typeMismatch) {
                        mostrarError('Por favor ingrese un valor válido en ' + (invalidField.name || 'este campo'));
                    } else if (invalidField.validity.patternMismatch) {
                        mostrarError('El formato del campo ' + (invalidField.name || 'este campo') + ' no es válido');
                    } else if (invalidField.validity.tooShort) {
                        mostrarError('El campo es demasiado corto');
                    } else if (invalidField.validity.tooLong) {
                        mostrarError('El campo es demasiado largo');
                    }
                }
            }
        });
    });
    
    // Limpiar espacios en blanco al enviar
    forms.forEach(function(form) {
        form.addEventListener('submit', function() {
            const inputs = form.querySelectorAll('input[type="text"], input[type="email"], textarea');
            inputs.forEach(function(input) {
                input.value = input.value.trim();
            });
        });
    });
});

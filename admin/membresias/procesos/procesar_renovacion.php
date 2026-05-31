<?php
// NO iniciar sesión aquí, ya está iniciada por el sistema
require __DIR__ . '/../../../conexion.php';

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Método no permitido';
    header('Location: ../locales/index.php');
    exit;
}

// ===== VALIDACIONES =====
$errors = [];

$idMembresia = $_POST['id_membresia'] ?? null;
$idLocal = $_POST['id_local'] ?? null;
$tipoMembresia = $_POST['tipo_membresia'] ?? null;
$estaVencida = $_POST['esta_vencida'] ?? '0';

// Validar ID de membresía
if (empty($idMembresia) || !is_numeric($idMembresia)) {
    $errors[] = 'ID de membresía inválido';
}

// Validar ID de local
if (empty($idLocal) || !is_numeric($idLocal)) {
    $errors[] = 'ID de local inválido';
}

// Validar tipo de membresía
if (empty($tipoMembresia)) {
    $errors[] = 'Debe seleccionar un tipo de membresía';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('. ', $errors);
    header('Location: ../../locales/index.php');
    exit;
}

// Obtener información del precio de la membresía seleccionada
try {
    $stmtPrecio = $pdo->prepare("SELECT * FROM precios_membresias WHERE tipo_membresia = :tipo AND activo = 1");
    $stmtPrecio->execute([':tipo' => $tipoMembresia]);
    $precioMembresia = $stmtPrecio->fetch(PDO::FETCH_ASSOC);

    if (!$precioMembresia) {
        $_SESSION['error'] = 'No se encontró información de precio para la membresía seleccionada';
        header('Location: ../../locales/index.php');
        exit;
    }

    $duracionMeses = $precioMembresia['duracion_meses'];
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error al obtener información de la membresía: ' . $e->getMessage();
    header('Location: ../../locales/index.php');
    exit;
}

// ===== PROCESAMIENTO =====
try {
    // Obtener información actual de la membresía
    $stmtMembresia = $pdo->prepare("SELECT * FROM membresias WHERE id_membresia = :id_membresia");
    $stmtMembresia->execute([':id_membresia' => $idMembresia]);
    $membresia = $stmtMembresia->fetch(PDO::FETCH_ASSOC);

    if (!$membresia) {
        $_SESSION['error'] = 'Membresía no encontrada';
        header('Location: ../../locales/index.php');
        exit;
    }

    // Verificar si la membresía está vencida
    $fechaActual = new DateTime();
    $fechaVencimiento = new DateTime($membresia['fecha_vencimiento']);
    $estaVencidaReal = $fechaVencimiento < $fechaActual;
    
    // Calcular nueva fecha de vencimiento
    if ($estaVencidaReal) {
        // Si está vencida, renovar desde hoy
        $fechaBase = new DateTime();
        $nuevaFechaVencimiento = $fechaBase->modify("+{$duracionMeses} months")->format('Y-m-d');
        $nuevaFechaInicio = date('Y-m-d');
        $nuevoEstado = 1; // Activar la membresía
        $tipoRenovacion = 'vencida';
    } else {
        // Si está activa, extender desde la fecha de vencimiento actual
        $fechaBase = new DateTime($membresia['fecha_vencimiento']);
        $nuevaFechaVencimiento = $fechaBase->modify("+{$duracionMeses} months")->format('Y-m-d');
        $nuevaFechaInicio = $membresia['fecha_inicio']; // Mantener la fecha de inicio original
        $nuevoEstado = 1; // Asegurar que esté activa
        $tipoRenovacion = 'activa';
    }

    // Actualizar la membresía
    $stmtUpdate = $pdo->prepare("
        UPDATE membresias 
        SET fecha_inicio = :fecha_inicio,
            fecha_vencimiento = :fecha_vencimiento,
            estado = :estado,
            tipo_membresia = :tipo_membresia
        WHERE id_membresia = :id_membresia
    ");
    
    $resultado = $stmtUpdate->execute([
        ':fecha_inicio' => $nuevaFechaInicio,
        ':fecha_vencimiento' => $nuevaFechaVencimiento,
        ':estado' => $nuevoEstado,
        ':tipo_membresia' => $tipoMembresia,
        ':id_membresia' => $idMembresia
    ]);

    if ($resultado) {
        // Obtener nombre del local para el mensaje
        $stmtLocal = $pdo->prepare("SELECT nombre_local FROM locales WHERE id_local = :id_local");
        $stmtLocal->execute([':id_local' => $idLocal]);
        $local = $stmtLocal->fetch(PDO::FETCH_ASSOC);

        $mensaje = 'Membresía renovada exitosamente para ' . htmlspecialchars($local['nombre_local']);
        
        if ($tipoRenovacion == 'vencida') {
            $mensaje .= '. Estado cambiado a ACTIVA. Nueva fecha de vencimiento: ' . date('d/m/Y', strtotime($nuevaFechaVencimiento));
        } else {
            $mensaje .= '. Extendida hasta: ' . date('d/m/Y', strtotime($nuevaFechaVencimiento));
        }
        
        $_SESSION['success'] = $mensaje;
        header('Location: ../../locales/index.php');
    } else {
        $_SESSION['error'] = 'No se pudo renovar la membresía';
        header('Location: ../../locales/index.php');
    }
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error al renovar la membresía: ' . $e->getMessage();
    header('Location: ../../locales/index.php');
    exit;
}

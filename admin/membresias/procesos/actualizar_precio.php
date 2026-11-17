<?php
session_start();
require_once __DIR__ . '/../../../bootstrap/autoload.php';

if (!\App\Core\Auth::isAuthenticated() || !\App\Core\Auth::hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido');
}

if (!\App\Core\Csrf::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitud no válida. Recarga la página e inténtalo nuevamente.');
}

require __DIR__ . '/../../../conexion.php';

$idPrecio = $_POST['id_precio'] ?? null;
$precio = $_POST['precio'] ?? null;

// Validaciones
if (!$idPrecio || $precio === null || $precio === '') {
    header('Location: ../index.php?error=' . urlencode('Datos incompletos'));
    exit;
}

if ($precio < 0) {
    header('Location: ../index.php?error=' . urlencode('El precio no puede ser negativo'));
    exit;
}

try {
    // Actualizar el precio directamente
    $stmt = $pdo->prepare("UPDATE precios_membresias 
                           SET precio = :precio, 
                               fecha_actualizacion = CURRENT_TIMESTAMP 
                           WHERE id_precio = :id_precio");
    
    $stmt->execute([
        ':id_precio' => (int)$idPrecio,
        ':precio' => (float)$precio
    ]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: ../index.php?success=1');
    } else {
        header('Location: ../index.php?error=' . urlencode('No se encontró el registro o el precio es el mismo'));
    }
    exit;
    
} catch (PDOException $e) {
    header('Location: ../index.php?error=' . urlencode('Error: ' . $e->getMessage()));
    exit;
}

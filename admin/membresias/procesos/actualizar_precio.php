<?php
// NO iniciar sesión aquí, ya está iniciada por el sistema
require __DIR__ . '/../../../conexion.php';

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?error=Método no permitido');
    exit;
}

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

<?php
session_start();
include '../../../conexion.php';

header('Content-Type: application/json');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_local = (int) ($_POST['id_local'] ?? 0);

if (!$id_local) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de local inválido']);
    exit;
}

try {
    // Eliminado lógico: estado = 0
    $stmt = $pdo->prepare("CALL DeleteLocal(:id)");
    $stmt->execute([':id' => $id_local]);
    $stmt->closeCursor();

    echo json_encode(['success' => true, 'message' => 'Local desactivado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}

<?php
/**
 * Endpoint AJAX: devuelve las canchas de un local en formato JSON.
 * GET  ?id_local=<int>
 */
session_start();

// Validar que el usuario esté autenticado
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'canchas' => [], 'error' => 'No autorizado']);
    exit;
}

include '../../conexion.php';

header('Content-Type: application/json; charset=utf-8');

$idLocal = isset($_GET['id_local']) ? (int)$_GET['id_local'] : 0;

if ($idLocal <= 0) {
    echo json_encode(['ok' => false, 'canchas' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT c.codigo_cancha, c.descripcion, c.id_local
        FROM cancha c
        WHERE c.id_local = :id_local
        ORDER BY c.codigo_cancha
    ");
    $stmt->execute([':id_local' => $idLocal]);
    $canchas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    echo json_encode(['ok' => true, 'canchas' => $canchas]);
} catch (Exception $e) {
    error_log('get_canchas_by_local.php: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'canchas' => [], 'error' => 'Error al consultar las canchas']);
}
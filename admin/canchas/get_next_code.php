<?php
include '../../conexion.php';

header('Content-Type: application/json');

$idLocal = $_GET['id_local'] ?? null;

if (empty($idLocal) || !is_numeric($idLocal)) {
    echo json_encode([
        'success' => false,
        'message' => 'Local inválido'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("CALL GetNextCanchaCodeByLocal(:id_local)");
    $stmt->execute([':id_local' => (int)$idLocal]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    echo json_encode([
        'success' => true,
        'next_code' => $row['next_code'] ?? 'C01'
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar el código'
    ]);
}
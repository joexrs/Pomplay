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
    // Intentar usar el stored procedure primero
    try {
        $stmt = $pdo->prepare("CALL GetNextCanchaCodeByLocal(:id_local)");
        $stmt->execute([':id_local' => (int)$idLocal]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row && isset($row['next_code'])) {
            echo json_encode([
                'success' => true,
                'next_code' => $row['next_code']
            ]);
            exit;
        }
    } catch (PDOException $spError) {
        // El stored procedure no existe, continuar con fallback
    }

    // Fallback: generar el código directamente con SQL
    // Buscar el número más alto de código existente para este local (formato CXX)
    $stmt = $pdo->prepare("
        SELECT codigo_cancha 
        FROM cancha 
        WHERE id_local = :id_local 
        ORDER BY codigo_cancha DESC 
        LIMIT 1
    ");
    $stmt->execute([':id_local' => (int)$idLocal]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($row && !empty($row['codigo_cancha'])) {
        // Extraer el número del código (ej: "C05" -> 5)
        $currentCode = $row['codigo_cancha'];
        $numericPart = intval(preg_replace('/[^0-9]/', '', $currentCode));
        $nextNumber = $numericPart + 1;
    } else {
        $nextNumber = 1;
    }

    // Generar el siguiente código con formato C + número con padding de 2 dígitos
    $nextCode = 'C' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);

    echo json_encode([
        'success' => true,
        'next_code' => $nextCode
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al generar el código'
    ]);
}
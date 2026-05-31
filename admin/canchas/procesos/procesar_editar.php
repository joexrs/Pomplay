<?php
include '../../../conexion.php';

// Calcular baseUrl dinámicamente
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(dirname(dirname(dirname(__FILE__))))), '/');
$baseUrl = '';
if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}
$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');
if ($baseUrl === '/') {
    $baseUrl = '';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validación de datos
    $errors = [];
    
    $codigo_cancha = trim($_POST['codigo_cancha'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_local = $_POST['id_local'] ?? '';

    // Validar código de cancha
    if (empty($codigo_cancha)) {
        $errors[] = "El código de la cancha es obligatorio";
    }

    // Validar descripción
    if (empty($descripcion)) {
        $errors[] = "La descripción es obligatoria";
    } elseif (strlen($descripcion) > 70) {
        $errors[] = "La descripción no puede exceder 70 caracteres";
    }

    // Validar local
    if (empty($id_local) || !is_numeric($id_local)) {
        $errors[] = "Debe seleccionar un local válido";
    }

    // Si hay errores, redirigir con mensaje
    if (!empty($errors)) {
        session_start();
        $_SESSION['error'] = implode(", ", $errors);
        header("Location: $baseUrl/admin/canchas/edit.php?codigo_cancha=" . urlencode($codigo_cancha));
        exit();
    }

    try {
        $stmt = $pdo->prepare("CALL UpdateCanchaLocal(:codigo_cancha, :descripcion, :id_local)");
        $stmt->execute([
            ':codigo_cancha' => $codigo_cancha,
            ':descripcion' => $descripcion,
            ':id_local' => $id_local,
        ]);

        session_start();
        $_SESSION['success'] = "Cancha actualizada exitosamente";
        header("Location: $baseUrl/admin/canchas/index.php");
        exit();
    } catch (PDOException $e) {
        session_start();
        $_SESSION['error'] = "Error al actualizar la cancha: " . $e->getMessage();
        header("Location:/admin/canchas/edit.php?codigo_cancha=" . urlencode($codigo_cancha));
        exit();
    }
}

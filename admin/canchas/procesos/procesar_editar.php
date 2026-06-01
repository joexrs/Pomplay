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
    $errors = [];

    $codigo_cancha = trim($_POST['codigo_cancha'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_local = $_POST['id_local'] ?? '';
    $tipo_cancha = trim($_POST['tipo_cancha'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $imagen_url = null;

    if (empty($codigo_cancha)) {
        $errors[] = "El código de la cancha es obligatorio";
    }

    if (empty($descripcion)) {
        $errors[] = "La descripción es obligatoria";
    } elseif (strlen($descripcion) > 70) {
        $errors[] = "La descripción no puede exceder 70 caracteres";
    }

    if (empty($id_local) || !is_numeric($id_local)) {
        $errors[] = "Debe seleccionar un local válido";
    }

    if (!empty($_FILES['imagen_cancha']['name'])) {
        $uploadDir = __DIR__ . '/../../../public/images/canchas/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $fileType = $_FILES['imagen_cancha']['type'];

        if (!in_array($fileType, $allowedTypes, true)) {
            $errors[] = 'Formato de imagen no permitido. Use JPG, PNG o WEBP.';
        }

        if ($_FILES['imagen_cancha']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'La imagen no puede pesar más de 3MB.';
        }

        if (empty($errors)) {
            $extension = pathinfo($_FILES['imagen_cancha']['name'], PATHINFO_EXTENSION);
            $fileName = 'cancha_' . $codigo_cancha . '_' . time() . '.' . strtolower($extension);
            $destination = $uploadDir . $fileName;

            if (!move_uploaded_file($_FILES['imagen_cancha']['tmp_name'], $destination)) {
                $errors[] = 'No se pudo subir la imagen de la cancha.';
            } else {
                $imagen_url = $baseUrl . '/public/images/canchas/' . $fileName;
            }
        }
    }

    if (!empty($errors)) {
        session_start();
        $_SESSION['error'] = implode(", ", $errors);
        header("Location: $baseUrl/admin/canchas/edit.php?codigo_cancha=" . urlencode($codigo_cancha));
        exit();
    }

    try {
        $stmt = $pdo->prepare("CALL UpdateCanchaLocal(:codigo_cancha, :descripcion, :id_local, :imagen_url, :tipo_cancha, :ubicacion)");
        $stmt->execute([
            ':codigo_cancha' => $codigo_cancha,
            ':descripcion' => $descripcion,
            ':id_local' => (int)$id_local,
            ':imagen_url' => $imagen_url,
            ':tipo_cancha' => $tipo_cancha !== '' ? $tipo_cancha : null,
            ':ubicacion' => $ubicacion !== '' ? $ubicacion : null
        ]);

        session_start();
        $_SESSION['success'] = "Cancha actualizada exitosamente";
        header("Location: $baseUrl/admin/canchas/index.php");
        exit();
    } catch (PDOException $e) {
        session_start();
        $_SESSION['error'] = "Error al actualizar la cancha: " . $e->getMessage();
        header("Location: $baseUrl/admin/canchas/edit.php?codigo_cancha=" . urlencode($codigo_cancha));
        exit();
    }
}
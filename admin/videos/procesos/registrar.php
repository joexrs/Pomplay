<?php
session_start();
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ===== VALIDACIONES =====
    $errors = [];
    
    $codigo_video = trim($_POST['codigo_video'] ?? '');
    $fecha_partido = trim($_POST['fecha_partido'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $hora_partido = trim($_POST['hora_partido'] ?? '');
    $codigo_cancha = trim($_POST['codigo_cancha'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $observacion = trim($_POST['observacion'] ?? '');
    $duracion = trim($_POST['duracion'] ?? '');

    // Validar código de video
    if (empty($codigo_video)) {
        $errors[] = 'El código del video es obligatorio';
    } elseif (strlen($codigo_video) > 10) {
        $errors[] = 'El código del video no puede exceder 10 caracteres';
    }

    // Validar descripción
    if (empty($descripcion)) {
        $errors[] = 'La descripción es obligatoria';
    } elseif (strlen($descripcion) > 50) {
        $errors[] = 'La descripción no puede exceder 50 caracteres';
    }

    // Validar cancha
    if (empty($codigo_cancha)) {
        $errors[] = 'Debe seleccionar una cancha';
    }

    // Validar video URL
    if (empty($video_url)) {
        $errors[] = 'La URL del video es obligatoria';
    } elseif (strlen($video_url) > 255) {
        $errors[] = 'La URL del video no puede exceder 255 caracteres';
    }

    // Validar duración
    if (empty($duracion)) {
        $errors[] = 'La duración es obligatoria';
    }

    // Convertir fecha de flatpickr (d/m/Y) a formato MySQL (Y-m-d)
    if (empty($fecha_partido)) {
        $errors[] = 'La fecha del partido es obligatoria';
    } else {
        $fecha_obj = DateTime::createFromFormat('d/m/Y', $fecha_partido);
        if ($fecha_obj) {
            $fecha_partido = $fecha_obj->format('Y-m-d');
        } else {
            // Si no se pudo convertir, verificar si ya viene en formato Y-m-d
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_partido)) {
                $errors[] = 'El formato de fecha es inválido';
            }
        }
    }

    // Validar hora
    if (empty($hora_partido)) {
        $errors[] = 'La hora del partido es obligatoria';
    } else {
        // Convertir hora de flatpickr (H:i) a formato TIME (H:i:s)
        if (strlen($hora_partido) == 5) {
            $hora_partido .= ':00';
        }
    }

    // Si hay errores, redirigir
    if (!empty($errors)) {
        $_SESSION['error'] = implode('. ', $errors);
        header("Location: $baseUrl/admin/videos/add.php");
        exit;
    }

    // ===== PROCESAMIENTO =====
    try {
        $stmt = $pdo->prepare("
            INSERT INTO video (
                codigo_video, fecha_partido, descripcion, hora_partido, 
                codigo_cancha, video_url, observacion, fecha_registro, 
                duracion, foto_referencia, estado
            ) VALUES (
                :codigo_video, :fecha_partido, :descripcion, :hora_partido,
                :codigo_cancha, :video_url, :observacion, CURDATE(),
                :duracion, '', 1
            )
        ");
        
        $result = $stmt->execute([
            ':codigo_video' => $codigo_video,
            ':fecha_partido' => $fecha_partido,
            ':descripcion' => $descripcion,
            ':hora_partido' => $hora_partido,
            ':codigo_cancha' => $codigo_cancha,
            ':video_url' => $video_url,
            ':observacion' => $observacion,
            ':duracion' => $duracion,
        ]);
        
        if (!$result) {
            $_SESSION['error'] = 'Error al insertar el video';
            header("Location: $baseUrl/admin/videos/add.php");
            exit;
        }

        $_SESSION['success'] = 'Video registrado exitosamente';
        header("Location: $baseUrl/admin/videos/index.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al insertar el video: ' . $e->getMessage();
        header("Location: $baseUrl/admin/videos/add.php");
        exit;
    }
}

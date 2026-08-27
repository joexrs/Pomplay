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

    $codigo_video  = trim($_POST['codigo_video']  ?? '');
    $fecha_partido = trim($_POST['fecha_partido'] ?? '');
    $descripcion   = trim($_POST['descripcion']   ?? '');
    $hora_partido  = trim($_POST['hora_partido']  ?? '');
    $codigo_cancha = trim($_POST['codigo_cancha'] ?? '');
    $video_url     = trim($_POST['video_url']     ?? '');
    $observacion   = trim($_POST['observacion']   ?? '');
    $duracion      = trim($_POST['duracion']      ?? '');


    $id_local = trim($_POST['id_local'] ?? '');
$id_local = (!empty($id_local) && is_numeric($id_local)) ? (int) $id_local : null;
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

    // Validar duración
    if (empty($duracion)) {
        $errors[] = 'La duración es obligatoria';
    }

    // Validar video_url (generada por el VPS después de la subida del archivo)
    if (empty($video_url)) {
        $errors[] = 'El video no fue subido al servidor. Por favor, suba el archivo primero.';
    } elseif (!filter_var($video_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'La URL del video generada no es válida. Intente subir el archivo nuevamente.';
    } elseif (strlen($video_url) > 255) {
        $errors[] = 'La URL del video excede el límite permitido';
    }

    // Convertir fecha de flatpickr (d/m/Y) a formato MySQL (Y-m-d)
    $fecha_mysql = '';
    if (empty($fecha_partido)) {
        $errors[] = 'La fecha del partido es obligatoria';
    } else {
        $fecha_obj = DateTime::createFromFormat('d/m/Y', $fecha_partido);
        if ($fecha_obj) {
            $fecha_mysql = $fecha_obj->format('Y-m-d');
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_partido)) {
                $fecha_mysql = $fecha_partido;
            } else {
                $errors[] = 'El formato de fecha es inválido';
            }
        }
    }

    // Validar y normalizar hora
    $hora_mysql = '';
    if (empty($hora_partido)) {
        $errors[] = 'La hora del partido es obligatoria';
    } else {
        if (strlen($hora_partido) == 5) {
            $hora_partido .= ':00';
        }
        $hora_mysql = $hora_partido;
    }

    // Si hay errores, redirigir
    if (!empty($errors)) {
        $_SESSION['error'] = implode('. ', $errors);
        header("Location: $baseUrl/admin/videos/add.php");
        exit;
    }

    // ===== REGISTRAR EN BD =====
    // El archivo ya fue subido al VPS por el navegador (chunked upload).
    // Solo necesitamos insertar el registro con la video_url generada por el VPS.
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("
            INSERT INTO video (
                codigo_video, id_local, fecha_partido, descripcion, hora_partido,
                codigo_cancha, video_url, observacion, fecha_registro,
                duracion, estado
            ) VALUES (
                :codigo_video, :id_local, :fecha_partido, :descripcion, :hora_partido,
                :codigo_cancha, :video_url, :observacion, CURDATE(),
                :duracion, 1
            )
        ");

        $result = $stmt->execute([
            ':codigo_video'  => $codigo_video,
            ':id_local'      => $id_local,
            ':fecha_partido' => $fecha_mysql,
            ':descripcion'   => $descripcion,
            ':hora_partido'  => $hora_mysql,
            ':codigo_cancha' => $codigo_cancha,
            ':video_url'     => $video_url,
            ':observacion'   => $observacion,
            ':duracion'      => $duracion,
        ]);

        if (!$result) {
            $_SESSION['error'] = 'Error al insertar el video en la base de datos';
            header("Location: $baseUrl/admin/videos/add.php");
            exit;
        }

        $_SESSION['success'] = 'Video registrado exitosamente. Archivo almacenado en el servidor.';
        header("Location: $baseUrl/admin/videos/index.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = 'Error real: ' . $e->getMessage();
        header("Location: $baseUrl/admin/videos/add.php");
        exit;
    }
}

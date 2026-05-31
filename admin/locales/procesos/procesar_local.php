<?php
session_start();
include '../../../conexion.php';

// Calcular la ruta base del proyecto de forma dinámica
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(dirname(dirname(__DIR__)))), '/');
$baseUrl = '';

if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}

$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');

// Si $baseUrl es solo '/', dejarlo vacío para producción
if ($baseUrl === '/') {
    $baseUrl = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ===== VALIDACIONES =====
        $errors = [];
        
        // Validar nombre del local
        $nombre_local = trim($_POST['nombre_local'] ?? '');
        if (empty($nombre_local)) {
            $errors[] = 'El nombre del local es obligatorio';
        } elseif (strlen($nombre_local) > 100) {
            $errors[] = 'El nombre del local no puede exceder 100 caracteres';
        }
        
        // Validar propietario
        $id_propietario = $_POST['id_propietario'] ?? '';
        $nuevo_nombre = trim($_POST['nuevo_propietario_nombre'] ?? '');
        $nuevo_apellidos = trim($_POST['nuevo_propietario_apellidos'] ?? '');
        $nuevo_email = trim($_POST['nuevo_propietario_email'] ?? '');
        $nuevo_password = $_POST['nuevo_propietario_password'] ?? '';
        
        // Debe seleccionar propietario existente O crear uno nuevo
        if (empty($id_propietario) && (empty($nuevo_nombre) || empty($nuevo_apellidos) || empty($nuevo_email) || empty($nuevo_password))) {
            $errors[] = 'Debe seleccionar un propietario existente o completar todos los datos para crear uno nuevo';
        }
        
        // Si está creando nuevo propietario, validar datos
        if (!empty($nuevo_nombre) || !empty($nuevo_apellidos) || !empty($nuevo_email) || !empty($nuevo_password)) {
            if (empty($nuevo_nombre)) $errors[] = 'El nombre del propietario es obligatorio';
            if (empty($nuevo_apellidos)) $errors[] = 'Los apellidos del propietario son obligatorios';
            
            if (empty($nuevo_email)) {
                $errors[] = 'El email del propietario es obligatorio';
            } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El email del propietario no es válido';
            }
            
            if (empty($nuevo_password)) {
                $errors[] = 'La contraseña es obligatoria';
            } elseif (strlen($nuevo_password) < 6) {
                $errors[] = 'La contraseña debe tener al menos 6 caracteres';
            }
        }
        
        // Validar fechas de membresía
        $fecha_inicio = $_POST['fecha_inicio'] ?? '';
        $fecha_fin = $_POST['fecha_fin'] ?? '';
        
        if (empty($fecha_inicio) || empty($fecha_fin)) {
            $errors[] = 'Las fechas de inicio y vencimiento de la membresía son obligatorias';
        } elseif (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
            $errors[] = 'La fecha de vencimiento debe ser posterior a la fecha de inicio';
        }
        
        // Si hay errores, redirigir
        if (!empty($errors)) {
            $_SESSION['error'] = implode('. ', $errors);
            header('Location: ' . $baseUrl . '/admin/locales/add.php');
            exit;
        }
        
        // ===== PROCESAMIENTO =====
        
        // Insertar local
        $stmt = $pdo->prepare("CALL CreateLocal(:nombre_local, :id_propietario)");
        $stmt->execute([
            ':nombre_local' => $nombre_local,
            ':id_propietario' => !empty($id_propietario) ? $id_propietario : null,
        ]);
        
        $localId = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['id_local'] ?? 0);
        $stmt->closeCursor();
        
        if (!$localId) {
            $_SESSION['error'] = 'Error al crear el local';
            header('Location: ' . $baseUrl . '/admin/locales/add.php');
            exit;
        }
        
        $propietarioId = null;
        $userId = null;
        
        // Si se seleccionó un propietario existente
        if (!empty($id_propietario)) {
            $propietarioId = $id_propietario;
            
            // Verificar si ya existe usuario para este propietario
            $stmtCheckUser = $pdo->prepare("CALL GetOwnerUserByPropietario(:id_propietario)");
            $stmtCheckUser->execute([':id_propietario' => $propietarioId]);
            $existingUser = $stmtCheckUser->fetch(PDO::FETCH_ASSOC);
            $stmtCheckUser->closeCursor();
            
            if ($existingUser) {
                $userId = $existingUser['id_usuario'];
            } else {
                // Crear usuario para el propietario existente (usuario = email)
                $propData = $pdo->prepare("CALL GetPropietarioById(:id_propietario)");
                $propData->execute([':id_propietario' => $propietarioId]);
                $prop = $propData->fetch(PDO::FETCH_ASSOC);
                $propData->closeCursor();
                
                if ($prop) {
                    $stmtUser = $pdo->prepare("CALL CreateOwnerUser(:usuario, :password, :id_propietario)");
                    $stmtUser->execute([
                        ':usuario' => $prop['email'],
                        ':password' => password_hash('123456', PASSWORD_BCRYPT),
                        ':id_propietario' => $propietarioId,
                    ]);
                    $userId = (int) ($stmtUser->fetch(PDO::FETCH_ASSOC)['id_usuario'] ?? 0);
                    $stmtUser->closeCursor();
                }
            }
        }
        // Si se proporciona información de nuevo propietario
        elseif (!empty($nuevo_nombre) && !empty($nuevo_apellidos) && !empty($nuevo_email) && !empty($nuevo_password)) {
            // Crear propietario
            $stmtProp = $pdo->prepare("CALL CreatePropietario(:nombres, :apellidos, :email, :telefono, :direccion)");
            $stmtProp->execute([
                ':nombres' => $nuevo_nombre,
                ':apellidos' => $nuevo_apellidos,
                ':email' => $nuevo_email,
                ':telefono' => trim($_POST['nuevo_propietario_telefono'] ?? ''),
                ':direccion' => trim($_POST['nuevo_propietario_direccion'] ?? ''),
            ]);
            
            $propietarioId = (int) ($stmtProp->fetch(PDO::FETCH_ASSOC)['id_propietario'] ?? 0);
            $stmtProp->closeCursor();
            
            if ($propietarioId) {
                // Actualizar local con el nuevo propietario
                $stmtUpdateLocal = $pdo->prepare("CALL AssignLocalOwner(:id_local, :id_propietario)");
                $stmtUpdateLocal->execute([':id_propietario' => $propietarioId, ':id_local' => $localId]);
                $stmtUpdateLocal->closeCursor();
                
                // Crear usuario propietario
                $stmtUser = $pdo->prepare("CALL CreateOwnerUser(:usuario, :password, :id_propietario)");
                $stmtUser->execute([
                    ':usuario' => $nuevo_email,
                    ':password' => password_hash($nuevo_password, PASSWORD_BCRYPT),
                    ':id_propietario' => $propietarioId,
                ]);
                
                $userId = (int) ($stmtUser->fetch(PDO::FETCH_ASSOC)['id_usuario'] ?? 0);
                $stmtUser->closeCursor();
            }
        }
        
        // Crear membresía
        if ($userId) {
            $stmtMembership = $pdo->prepare("CALL CreateMembership(:id_usuario, :id_local, :fecha_inicio, :fecha_vencimiento, :tipo_membresia)");
            $stmtMembership->execute([
                ':id_usuario' => $userId,
                ':id_local' => $localId,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_vencimiento' => $fecha_fin,
                ':tipo_membresia' => $_POST['tipo_membresia'] ?? 'BASICA',
            ]);
            $stmtMembership->closeCursor();
            
            $_SESSION['success'] = 'Local creado exitosamente con membresía activa';
        } else {
            $_SESSION['info'] = 'Local creado, pero no se pudo crear la membresía';
        }
        
        header('Location: ' . $baseUrl . '/admin/locales/index.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al crear el local: ' . $e->getMessage();
        header('Location: ' . $baseUrl . '/admin/locales/add.php');
        exit;
    }
} else {
    header('Location: ' . $baseUrl . '/admin/locales/add.php');
    exit;
}

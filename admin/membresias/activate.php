<?php include '../shared/header_admin.php'; ?>
<?php include '../../conexion.php'; ?>
<?php include '../config.php'; ?>

<?php
// Verificar que se reciba el ID del local
$localId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$localId) {
    $_SESSION['error'] = 'ID de local no proporcionado';
    header('Location: ../locales/index.php');
    exit;
}

try {
    // Obtener información del local
    $stmtLocal = $pdo->prepare("CALL GetLocalById(:id_local)");
    $stmtLocal->execute([':id_local' => $localId]);
    $local = $stmtLocal->fetch(PDO::FETCH_ASSOC);
    $stmtLocal->closeCursor();

    if (!$local) {
        $_SESSION['error'] = 'Local no encontrado';
        header('Location: ../locales/index.php');
        exit;
    }

    // Obtener propietario del local
    $stmtProp = $pdo->prepare("CALL GetPropietarioByLocal(:id_local)");
    $stmtProp->execute([':id_local' => $localId]);
    $propietario = $stmtProp->fetch(PDO::FETCH_ASSOC);
    $stmtProp->closeCursor();

    if (!$propietario) {
        $_SESSION['error'] = 'Este local no tiene un propietario asignado';
        header('Location: ../locales/index.php');
        exit;
    }

    // Obtener usuario del propietario
    $stmtUser = $pdo->prepare("CALL GetOwnerUserByPropietario(:id_propietario)");
    $stmtUser->execute([':id_propietario' => $propietario['id_propietario']]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $stmtUser->closeCursor();

    // Si no tiene usuario, crear uno automáticamente
    if (!$usuario) {
        $stmtCreateUser = $pdo->prepare("CALL CreateOwnerUser(:usuario, :password, :email, :nombre_completo, :id_propietario)");
        $stmtCreateUser->execute([
            ':usuario' => $propietario['email'], // Usuario es el correo
            ':password' => password_hash('123456', PASSWORD_BCRYPT), // Contraseña por defecto
            ':email' => $propietario['email'],
            ':nombre_completo' => $propietario['nombres'] . ' ' . $propietario['apellidos'],
            ':id_propietario' => $propietario['id_propietario'],
        ]);
        $usuarioCreado = $stmtCreateUser->fetch(PDO::FETCH_ASSOC);
        $stmtCreateUser->closeCursor();
        
        $userId = $usuarioCreado['id_usuario'] ?? null;
        
        if (!$userId) {
            $_SESSION['error'] = 'No se pudo crear el usuario para el propietario';
            header('Location: ../locales/index.php');
            exit;
        }
        
        // Obtener el usuario recién creado
        $stmtUser2 = $pdo->prepare("CALL GetOwnerUserByPropietario(:id_propietario)");
        $stmtUser2->execute([':id_propietario' => $propietario['id_propietario']]);
        $usuario = $stmtUser2->fetch(PDO::FETCH_ASSOC);
        $stmtUser2->closeCursor();
    }

    // Obtener membresía del local
    $stmtMembership = $pdo->prepare("CALL GetMembershipByUserLocal(:user_id, :local_id)");
    $stmtMembership->execute([
        ':user_id' => $usuario['id_usuario'],
        ':local_id' => $localId
    ]);
    $membership = $stmtMembership->fetch(PDO::FETCH_ASSOC);
    $stmtMembership->closeCursor();

    if (!$membership) {
        $_SESSION['error'] = 'Este local no tiene una membresía registrada';
        header('Location: ../locales/index.php');
        exit;
    }

    // Verificar el estado actual
    if ($membership['estado'] == 1) {
        $_SESSION['info'] = 'La membresía ya está activa';
        header('Location: ../locales/index.php');
        exit;
    }

    // Activar la membresía (cambiar estado de 0 a 1)
    $stmtActivate = $pdo->prepare("UPDATE membresias SET estado = 1 WHERE id_membresia = :id_membresia");
    $stmtActivate->execute([':id_membresia' => $membership['id_membresia']]);

    $_SESSION['success'] = 'Membresía activada exitosamente para ' . htmlspecialchars($local['nombre_local']);
    header('Location: ../locales/index.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error'] = 'Error al activar la membresía: ' . $e->getMessage();
    header('Location: ../locales/index.php');
    exit;
}
?>

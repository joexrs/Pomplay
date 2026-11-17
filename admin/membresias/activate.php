<?php
session_start();
require_once __DIR__ . '/../../bootstrap/autoload.php';
if (!\App\Core\Auth::isAuthenticated() || !\App\Core\Auth::hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido');
}

if (!\App\Core\Csrf::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Solicitud no válida. Recarga la página e inténtalo nuevamente.');
}

require_once __DIR__ . '/../../conexion.php';

// Verificar que se reciba el ID del local
$localId = isset($_POST['id']) ? (int)$_POST['id'] : null;

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

    // La cuenta se crea al dar de alta al propietario; no generar credenciales compartidas.
    if (!$usuario) {
        $_SESSION['error'] = 'Este propietario no tiene un usuario asignado. Contacta a soporte antes de activar la membresía.';
        header('Location: ../locales/index.php');
        exit;
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

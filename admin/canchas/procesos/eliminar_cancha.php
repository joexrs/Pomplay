<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap/autoload.php';

use App\Core\Auth;
use App\Services\VpsStorageService;

const COURT_DELETE_ERROR_MESSAGE = 'No pudimos eliminar la cancha en este momento. Verifica tu conexión e inténtalo nuevamente. Si el problema continúa, comunícate con soporte.';

function logCourtDeletionError(string $message, array $context = []): void
{
    $logPath = __DIR__ . '/../../../logs/eliminar_canchas.log';
    $entry = [
        'fecha' => date(DATE_ATOM),
        'mensaje' => $message,
        'contexto' => $context,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($line === false || @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        error_log('No se pudo escribir logs/eliminar_canchas.log. ' . ($line ?: $message));
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Método no permitido');
}

if (!Auth::isAuthenticated() || !Auth::hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
    http_response_code(401);
    exit('No autorizado');
}

$csrfToken = (string) ($_SESSION['court_delete_csrf'] ?? '');
$requestToken = (string) ($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || $requestToken === '' || !hash_equals($csrfToken, $requestToken)) {
    logCourtDeletionError('Solicitud rechazada por token CSRF inválido.');
    $_SESSION['error'] = COURT_DELETE_ERROR_MESSAGE;
    header('Location: ../index.php');
    exit;
}

$idCancha = filter_var($_POST['id_cancha'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($idCancha === false) {
    logCourtDeletionError('Solicitud rechazada por ID de cancha inválido.');
    $_SESSION['error'] = COURT_DELETE_ERROR_MESSAGE;
    header('Location: ../index.php');
    exit;
}

$pdo = null;
$cancha = null;
$vpsDeletionConfirmed = false;
try {
    require_once __DIR__ . '/../../../conexion.php';

    $pdo->beginTransaction();

    $stmtCancha = $pdo->prepare(
        'SELECT id_cancha, id_local, codigo_cancha, descripcion
         FROM cancha
         WHERE id_cancha = :id_cancha
         FOR UPDATE'
    );
    $stmtCancha->execute([':id_cancha' => $idCancha]);
    $cancha = $stmtCancha->fetch(PDO::FETCH_ASSOC);

    if (!$cancha) {
        throw new RuntimeException('La cancha solicitada ya no existe.');
    }

    if (empty($cancha['id_local'])) {
        throw new RuntimeException('La cancha no está asociada a un local; no es seguro determinar qué carpeta eliminar.');
    }

    $stmtOrphanedVideos = $pdo->prepare(
        'SELECT COUNT(*)
         FROM video
         WHERE codigo_cancha = :codigo_cancha
           AND id_local IS NULL'
    );
    $stmtOrphanedVideos->execute([':codigo_cancha' => $cancha['codigo_cancha']]);
    if ((int) $stmtOrphanedVideos->fetchColumn() > 0) {
        throw new RuntimeException(
            'Hay videos con este código sin local asignado. Asígnalos correctamente antes de eliminar la cancha.'
        );
    }

    $stmtDeleteVideos = $pdo->prepare(
        'DELETE FROM video
         WHERE id_local = :id_local
           AND codigo_cancha = :codigo_cancha'
    );
    $stmtDeleteVideos->execute([
        ':id_local' => (int) $cancha['id_local'],
        ':codigo_cancha' => $cancha['codigo_cancha'],
    ]);
    $deletedVideos = $stmtDeleteVideos->rowCount();

    $stmtDeleteCancha = $pdo->prepare('DELETE FROM cancha WHERE id_cancha = :id_cancha');
    $stmtDeleteCancha->execute([':id_cancha' => $idCancha]);
    if ($stmtDeleteCancha->rowCount() !== 1) {
        throw new RuntimeException('No se pudo eliminar el registro de la cancha.');
    }

    $vpsStorage = new VpsStorageService();
    $vpsResult = $vpsStorage->deleteCanchaDirectory(
        (int) $cancha['id_local'],
        (string) $cancha['codigo_cancha']
    );
    if (!($vpsResult['ok'] ?? false)) {
        throw new RuntimeException(
            'El VPS no confirmó la eliminación de las grabaciones: '
            . (string) ($vpsResult['error'] ?? 'respuesta no válida')
        );
    }
    $vpsDeletionConfirmed = true;

    $pdo->commit();
    $_SESSION['success'] = sprintf(
        'Cancha %s eliminada junto con %d video(s); su carpeta de grabaciones fue eliminada o ya no existía.',
        $cancha['codigo_cancha'],
        $deletedVideos
    );
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logCourtDeletionError($e->getMessage(), [
        'id_cancha' => $idCancha,
        'id_local' => $cancha['id_local'] ?? null,
        'codigo_cancha' => $cancha['codigo_cancha'] ?? null,
        'vps_eliminacion_confirmada' => $vpsDeletionConfirmed,
        'excepcion' => get_class($e),
    ]);
    $_SESSION['error'] = COURT_DELETE_ERROR_MESSAGE;
}

header('Location: ../index.php');
exit;

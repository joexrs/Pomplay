<?php
declare(strict_types=1);

// Iniciar sesión si no está activa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/../../bootstrap/autoload.php';

use App\Core\Auth;

// Prevenir caché
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json');

// Verificar si el usuario está autenticado
$authenticated = Auth::isAuthenticated();

$response = [
    'authenticated' => $authenticated,
    'user_id' => $_SESSION['user_id'] ?? null,
    'rol' => $_SESSION['rol'] ?? null,
];

echo json_encode($response);

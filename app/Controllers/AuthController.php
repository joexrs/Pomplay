<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Repositories\AuthRepository;

final class AuthController
{
    // Roles que pueden acceder al panel de administración
    private const ADMIN_ROLES = ['ADMIN', 'SUPER_ADMIN'];

    public function __construct(private AuthRepository $auth)
    {
    }

    public function login(array $context): void
    {
        // Si viene de un logout, asegurar que la sesión esté completamente limpia
        if (isset($_GET['logout'])) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION = [];
            if (isset($_COOKIE[session_name()])) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            // Redirigir sin el parámetro logout
            header('Location: ' . $context['baseUrl'] . '/login.php');
            exit;
        }

        // Si ya está autenticado, redirigir según rol
        if (Auth::isAuthenticated()) {
            $this->redirectByRole(Auth::getRole(), $context['baseUrl']);
            return;
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario  = trim((string) ($_POST['usuario'] ?? ''));
            $password = (string) ($_POST['pass'] ?? '');

            if ($usuario === '' || $password === '') {
                $error = 'Por favor completa ambos campos.';
            } else {
                $user = $this->auth->findByUsername($usuario);

                if ($user === null) {
                    $error = 'Usuario no encontrado o inactivo.';
                } elseif (!password_verify($password, $user['password'])) {
                    $error = 'Contraseña incorrecta.';
                } else {
                    // Registrar sesión con todos los datos disponibles
                    Auth::login(
                        userId:        (int) $user['id_usuario'],
                        usuario:       $user['usuario'],
                        rol:           $user['rol'] ?? 'ADMIN',
                        idLocal:       null,           // no existe en el schema actual
                        nombreCompleto: $user['usuario'], // fallback: usar el nombre de usuario
                        email:         null,
                        foto:          null
                    );

                    $_SESSION['id_propietario'] = $user['id_propietario'] ?? null;
                    if (!empty($user['nombres']) || !empty($user['apellidos'])) {
                        $_SESSION['nombre_completo'] = trim(($user['nombres'] ?? '') . ' ' . ($user['apellidos'] ?? ''));
                    }

                    // Actualizar último acceso en BD
                    $this->auth->updateLastAccess((int) $user['id_usuario']);

                    $this->redirectByRole($user['rol'] ?? '', $context['baseUrl']);
                    return;
                }
            }
        }

        View::render('auth/login', [
            'baseUrl' => $context['baseUrl'],
            'error'   => $error,
        ]);
    }

    /**
     * Redirige al dashboard correcto según el rol del usuario
     */
    private function redirectByRole(?string $rol, string $baseUrl): void
    {
        if (in_array($rol, self::ADMIN_ROLES, true)) {
            header('Location: ' . $baseUrl . '/admin/dashboard.php');
        } elseif ($rol === 'DUENO' || $rol === 'DUEÑO') {
            header('Location: ' . $baseUrl . '/owner/canchas.php');
        } else {
            // Rol desconocido: redirigir a admin por defecto
            header('Location: ' . $baseUrl . '/admin/dashboard.php');
        }
        exit;
    }
}

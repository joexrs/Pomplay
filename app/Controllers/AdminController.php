<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Repositories\LocalRepository;
use App\Repositories\AuthRepository;
use App\Repositories\VideoRepository;

/**
 * Admin Controller - Gestión de Locales y Dueños
 * Funcionalidades mejoradas con roles
 */
final class AdminController
{
    public function __construct(
        private LocalRepository $locals,
        private AuthRepository $auth,
        private VideoRepository $videos
    ) {
    }

    /**
     * Dashboard Admin
     */
    public function dashboard(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        // Obtener estadísticas
        $allLocals = $this->locals->getLocalsWithCourts();
        $localsWithOwners = $this->locals->getLocalsWithOwners();

        View::render('admin/dashboard', [
            'baseUrl' => $context['baseUrl'],
            'locals' => $allLocals,
            'localsWithOwners' => $localsWithOwners,
            'totalLocals' => count($allLocals),
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Listar locales
     */
    public function listLocals(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $allLocals = $this->locals->getAllLocals();
        $localsWithData = $this->locals->getLocalsWithCourts();

        $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        View::render('admin/locals/list', [
            'baseUrl' => $context['baseUrl'],
            'locals' => $allLocals,
            'localsWithData' => $localsWithData,
            'currentPage' => $currentPage,
            'totalPages' => 1, // Ajustar si implementas paginación real en el nuevo SP
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Ver detalle de local
     */
    public function viewLocal(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $localId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$localId) {
            http_response_code(400);
            die('ID de local no proporcionado');
        }

        $local = $this->locals->getLocalById($localId);

        if (!$local) {
            http_response_code(404);
            die('Local no encontrado');
        }

        // Obtener canchas del local
        $canchas = $this->videos->getCourtsInLocal($localId);

        // Obtener dueños del local
        $owners = $this->auth->getUsersByLocal($localId);

        View::render('admin/locals/detail', [
            'baseUrl' => $context['baseUrl'],
            'local' => $local,
            'canchas' => $canchas,
            'owners' => $owners,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Crear local
     */
    public function createLocal(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nombre_local' => $_POST['nombre_local'] ?? '',
                'estado' => 1,
            ];

            $localId = $this->locals->createLocal($data);

            if ($localId) {
                header('Location: ' . $context['baseUrl'] . '/admin/locales.php?view=' . $localId . '&success=1');
                exit;
            } else {
                $error = 'Error al crear local';
            }
        }

        View::render('admin/locals/create', [
            'baseUrl' => $context['baseUrl'],
            'error' => $error ?? null,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Editar local
     */
    public function editLocal(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $localId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$localId) {
            http_response_code(400);
            die('ID de local no proporcionado');
        }

        $local = $this->locals->getLocalById($localId);

        if (!$local) {
            http_response_code(404);
            die('Local no encontrado');
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'nombre_local' => $_POST['nombre_local'] ?? $local['nombre_local'],
            ];

            $result = $this->locals->updateLocal($localId, $data);

            if ($result) {
                header('Location: ' . $context['baseUrl'] . '/admin/locales.php?view=' . $localId . '&success=1');
                exit;
            } else {
                $error = 'Error al actualizar local';
            }
        }

        View::render('admin/locals/edit', [
            'baseUrl' => $context['baseUrl'],
            'local' => $local,
            'error' => $error,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Eliminar local (lógico)
     */
    public function deleteLocal(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $localId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$localId) {
            http_response_code(400);
            die('ID de local no proporcionado');
        }

        $result = $this->locals->deleteLocal($localId);

        if ($result) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Local eliminado']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al eliminar local']);
        }
    }

    /**
     * Listar dueños
     */
    public function listOwners(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $perPage = 20;
        $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($currentPage - 1) * $perPage;

        // Obtener role_id para DUEÑO
        $ownerRole = $this->auth->getRoleByName('DUENO');
        $roleId = $ownerRole['id_rol'] ?? null;

        $owners = $roleId ? $this->auth->getUsersByRole($roleId, $perPage, $offset) : [];

        View::render('admin/owners/list', [
            'baseUrl' => $context['baseUrl'],
            'owners' => $owners,
            'currentPage' => $currentPage,
            'totalPages' => (int) ceil(count($owners) / $perPage),
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Ver detalle de dueño
     */
    public function viewOwner(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $ownerId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$ownerId) {
            http_response_code(400);
            die('ID de dueño no proporcionado');
        }

        $owner = $this->auth->findById($ownerId);

        if (!$owner || ($owner['rol'] !== 'DUENO' && $owner['rol'] !== 'DUEÑO')) {
            http_response_code(404);
            die('Dueño no encontrado');
        }

        // Obtener local del dueño
        $local = $owner['id_local'] ? $this->locals->getLocalById($owner['id_local']) : null;

        View::render('admin/owners/detail', [
            'baseUrl' => $context['baseUrl'],
            'owner' => $owner,
            'local' => $local,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Crear dueño
     */
    public function createOwner(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $locales = $this->locals->getAllLocals(100);
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Obtener role_id para DUEÑO
            $ownerRole = $this->auth->getRoleByName('DUENO');

            $data = [
                'usuario' => $_POST['usuario'] ?? '',
                'email' => $_POST['email'] ?? '',
                'password' => $_POST['password'] ?? '',
                'nombre_completo' => $_POST['nombre_completo'] ?? '',
                'id_rol' => $ownerRole['id_rol'] ?? 2,
                'id_local' => (int) ($_POST['id_local'] ?? 0),
                'estado' => 1,
            ];

            $userId = $this->auth->createUser($data);

            if ($userId) {
                header('Location: ' . $context['baseUrl'] . '/admin/duenos.php?id=' . $userId . '&success=1');
                exit;
            } else {
                $error = 'Error al crear dueño';
            }
        }

        View::render('admin/owners/create', [
            'baseUrl' => $context['baseUrl'],
            'locales' => $locales,
            'error' => $error,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Editar dueño
     */
    public function editOwner(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $ownerId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$ownerId) {
            http_response_code(400);
            die('ID de dueño no proporcionado');
        }

        $owner = $this->auth->findById($ownerId);

        if (!$owner || ($owner['rol'] !== 'DUENO' && $owner['rol'] !== 'DUEÑO')) {
            http_response_code(404);
            die('Dueño no encontrado');
        }

        $locales = $this->locals->getAllLocals(100);
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'usuario' => $_POST['usuario'] ?? $owner['usuario'],
                'email' => $_POST['email'] ?? $owner['email'],
                'nombre_completo' => $_POST['nombre_completo'] ?? $owner['nombre_completo'],
                'id_local' => (int) ($_POST['id_local'] ?? $owner['id_local']),
            ];

            $result = $this->auth->updateUser($ownerId, $data);

            if ($result) {
                header('Location: ' . $context['baseUrl'] . '/admin/duenos.php?id=' . $ownerId . '&success=1');
                exit;
            } else {
                $error = 'Error al actualizar dueño';
            }
        }

        View::render('admin/owners/edit', [
            'baseUrl' => $context['baseUrl'],
            'owner' => $owner,
            'locales' => $locales,
            'error' => $error,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Desactivar dueño
     */
    public function deactivateOwner(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        $ownerId = isset($_GET['id']) ? (int) $_GET['id'] : null;

        if (!$ownerId) {
            http_response_code(400);
            die('ID de dueño no proporcionado');
        }

        $result = $this->auth->deactivateUser($ownerId);

        if ($result) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Dueño desactivado']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al desactivar dueño']);
        }
    }

    /**
     * Habilitar descarga de videos para un local
     */
    public function enableVideoDownload(array $context): void
    {
        Auth::requireAnyRole(['ADMIN', 'SUPER_ADMIN'], $context['baseUrl'] . '/login.php');

        // Este método requeriría actualizar la tabla video
        // Lógica para habilitar/deshabilitar descarga de videos de un local
        
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Descarga de videos habilitada']);
    }
}

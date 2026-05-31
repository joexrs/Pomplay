<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Repositories\VideoRepository;
use App\Repositories\MembershipRepository;

/**
 * Owner Controller
 * Panel para propietarios de canchas/locales
 */
final class OwnerController
{
    public function __construct(
        private VideoRepository $videos,
        private MembershipRepository $memberships
    ) {
    }

    /**
     * Dashboard del dueño
     */
    public function dashboard(array $context): void
    {
        Auth::requireAnyRole(['DUENO', 'DUEÑO'], $context['baseUrl'] . '/login.php');

        $localId = Auth::getLocalId();
        $userId = Auth::getUserId();

        if (!$localId || !$userId) {
            http_response_code(403);
            die('Acceso denegado');
        }

        // Obtener videos del local
        $perPage = 12;
        $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($currentPage - 1) * $perPage;

        $videos = $this->videos->getOwnerVideos($localId, $perPage, $offset);
        $totalVideos = $this->videos->countOwnerVideos($localId);

        // Obtener membresía actual
        $membership = $this->memberships->getActiveMembership($userId, $localId);
        $isActive = $membership !== null && new \DateTime($membership['fecha_vencimiento']) > new \DateTime();
        $daysRemaining = 0;

        if ($membership && $isActive) {
            $expiry = new \DateTime($membership['fecha_vencimiento']);
            $today = new \DateTime();
            $daysRemaining = $expiry->diff($today)->days;
        }

        View::render('owner/dashboard', [
            'baseUrl' => $context['baseUrl'],
            'videos' => $videos,
            'totalVideos' => $totalVideos,
            'currentPage' => $currentPage,
            'totalPages' => (int) ceil($totalVideos / $perPage),
            'membership' => $membership,
            'isActive' => $isActive,
            'daysRemaining' => $daysRemaining,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Ver videos del local
     */
    public function videos(array $context): void
    {
        Auth::requireAnyRole(['DUENO', 'DUEÑO'], $context['baseUrl'] . '/login.php');

        $localId = Auth::getLocalId();

        if (!$localId) {
            http_response_code(403);
            die('Acceso denegado');
        }

        // Filtros
        $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : null;
        $hora = isset($_GET['hora']) ? (int) $_GET['hora'] : null;
        $codigoCancha = isset($_GET['cancha']) ? $_GET['cancha'] : null;

        $perPage = 12;
        $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $offset = ($currentPage - 1) * $perPage;

        $videos = $this->videos->findAdvanced($localId, $codigoCancha, $fecha, $hora, $perPage, $offset);
        $total = $this->videos->countAdvanced($localId, $codigoCancha, $fecha, $hora);

        // Obtener datos para filtros
        $canchas = $this->videos->getCourtsInLocal($localId);
        $horasDisponibles = $codigoCancha && $fecha 
            ? $this->videos->getAvailableHours($fecha, $codigoCancha)
            : [];

        View::render('owner/videos', [
            'baseUrl' => $context['baseUrl'],
            'videos' => $videos,
            'canchas' => $canchas,
            'horasDisponibles' => $horasDisponibles,
            'filters' => [
                'fecha' => $fecha,
                'hora' => $hora,
                'cancha' => $codigoCancha,
            ],
            'currentPage' => $currentPage,
            'totalPages' => (int) ceil($total / $perPage),
            'totalResults' => $total,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Ver membresía y renovación
     */
    public function membership(array $context): void
    {
        Auth::requireAnyRole(['DUENO', 'DUEÑO'], $context['baseUrl'] . '/login.php');

        $userId = Auth::getUserId();
        $localId = Auth::getLocalId();

        if (!$userId || !$localId) {
            http_response_code(403);
            die('Acceso denegado');
        }

        $membership = $this->memberships->getActiveMembership($userId, $localId);
        $isActive = $membership !== null && new \DateTime($membership['fecha_vencimiento']) > new \DateTime();

        $daysRemaining = 0;
        if ($membership && $isActive) {
            $expiry = new \DateTime($membership['fecha_vencimiento']);
            $today = new \DateTime();
            $daysRemaining = $expiry->diff($today)->days;
        }

        View::render('owner/membership', [
            'baseUrl' => $context['baseUrl'],
            'membership' => $membership,
            'isActive' => $isActive,
            'daysRemaining' => $daysRemaining,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Perfil del dueño
     */
    public function profile(array $context): void
    {
        Auth::requireAnyRole(['DUENO', 'DUEÑO'], $context['baseUrl'] . '/login.php');

        $user = Auth::getCurrentUser();

        if (!$user) {
            http_response_code(403);
            die('Acceso denegado');
        }

        View::render('owner/profile', [
            'baseUrl' => $context['baseUrl'],
            'user' => $user,
        ]);
    }

    /**
     * Descargar video
     */
    public function downloadVideo(array $context): void
    {
        Auth::requireAnyRole(['DUENO', 'DUEÑO'], $context['baseUrl'] . '/login.php');

        $videoId = isset($_GET['id']) ? $_GET['id'] : null;
        $localId = Auth::getLocalId();

        if (!$videoId || !$localId) {
            http_response_code(400);
            die('Datos inválidos');
        }

        // Verificar que el video pertenece al local del dueño
        $video = $this->videos->findByCode($videoId);

        if (!$video || $video['id_local'] !== $localId || !$video['es_descargable']) {
            http_response_code(403);
            die('Acceso denegado');
        }

        // Enviar archivo para descargar
        $filePath = __DIR__ . '/../../public/video/' . $video['video_url'];

        if (!file_exists($filePath)) {
            http_response_code(404);
            die('Archivo no encontrado');
        }

        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
    }
}

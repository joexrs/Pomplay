<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Repositories\MembershipRepository;

/**
 * Controller para Membresías
 * Solo verifica estado y muestra imagen de renovación
 */
final class PaymentController
{
    public function __construct(
        private MembershipRepository $memberships
    ) {
    }

    /**
     * Ver membresía actual y estado
     */
    public function viewMembership(array $context): void
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

        // Calcular días restantes
        $daysRemaining = 0;
        if ($membership && $isActive) {
            $expiry = new \DateTime($membership['fecha_vencimiento']);
            $today = new \DateTime();
            $daysRemaining = $expiry->diff($today)->days;
        }

        View::render('payment/membership', [
            'baseUrl' => $context['baseUrl'],
            'membership' => $membership,
            'isActive' => $isActive,
            'daysRemaining' => $daysRemaining,
            'user' => Auth::getCurrentUser(),
        ]);
    }

    /**
     * Mostrar imagen de renovación
     */
    public function showRenewalImage(array $context): void
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

        $renewalImagePath = '/images/renewal-instructions.png';

        View::render('payment/renewal-image', [
            'baseUrl' => $context['baseUrl'],
            'membership' => $membership,
            'isActive' => $isActive,
            'renewalImagePath' => $renewalImagePath,
            'user' => Auth::getCurrentUser(),
        ]);
    }
}

<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use PDO;
use PDOException;

final class MembershipsController
{
    public function index(array $context): void
    {
        $memberships = [];
        try {
            $db = Database::connection();
            if ($db) {
                $stmt = $db->prepare("SELECT * FROM precios_membresias WHERE activo = 1 ORDER BY duracion_meses ASC");
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (is_array($result)) {
                    $memberships = $result;
                }
            }
        } catch (PDOException $e) {
            error_log('MembershipsController::index PDOException: ' . $e->getMessage());
            $memberships = [];
        } catch (\Exception $e) {
            error_log('MembershipsController::index Exception: ' . $e->getMessage());
            $memberships = [];
        }

        View::render('home/memberships', [
            'baseUrl' => $context['baseUrl'],
            'memberships' => $memberships
        ]);
    }
}

<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Database;
use PDO;
use PDOException;

final class AboutController
{
    public function index(array $context): void
    {
        // Debug: Verificar que llegamos aquí
        error_log("AboutController::index - Iniciando");
        
        // Obtener planes de membresía
        $memberships = [];
        try {
            error_log("AboutController::index - Intentando conectar a BD");
            $db = Database::connection();
            
            if ($db) {
                error_log("AboutController::index - Conexión exitosa");
                $stmt = $db->prepare("SELECT * FROM precios_membresias WHERE activo = 1 ORDER BY duracion_meses ASC");
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("AboutController::index - Registros encontrados: " . count($result));
                
                // Validar que los datos sean correctos
                if (is_array($result)) {
                    $memberships = $result;
                }
            } else {
                error_log("AboutController::index - ERROR: No se pudo obtener conexión a BD");
            }
        } catch (PDOException $e) {
            // Si hay error, continuar sin membresías y registrar el error
            error_log("AboutController::index - PDOException: " . $e->getMessage());
            $memberships = [];
        } catch (\Exception $e) {
            // Capturar cualquier otro error
            error_log("AboutController::index - Exception: " . $e->getMessage());
            $memberships = [];
        }

        error_log("AboutController::index - Renderizando vista con " . count($memberships) . " membresías");
        
        try {
            View::render('home/about', [
                'baseUrl' => $context['baseUrl'],
                'memberships' => $memberships
            ]);
        } catch (\Exception $e) {
            error_log("AboutController::index - Error al renderizar: " . $e->getMessage());
            throw $e;
        }
    }
}

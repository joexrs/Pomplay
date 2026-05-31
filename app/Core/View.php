<?php

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $file = __DIR__ . '/../../resources/views/' . $view . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            echo 'Vista no encontrada: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        extract($data, EXTR_SKIP);
        require $file;
    }
}

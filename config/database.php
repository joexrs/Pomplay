<?php

// Cargar variables de entorno
require_once __DIR__ . '/../bootstrap/env.php';

return [
    'host' => env('DB_HOST', 'localhost'),
    'database' => env('DB_DATABASE', 'bd_futbol'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];

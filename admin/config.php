<?php
/**
 * Configuración global del panel de administración
 */

// Calcular la ruta base del proyecto de forma dinámica
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$baseUrl = '';

if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}

$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');


if ($baseUrl === '/') {
    $baseUrl = '';
}

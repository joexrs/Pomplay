<?php
// Archivo de prueba para verificar configuración
echo "<h1>Configuración del Sistema</h1>";
echo "<pre>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "\n--- Cálculo de baseUrl ---\n";

$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$projRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$baseUrl = '';

if (stripos($projRoot, $docRoot) === 0) {
    $baseUrl = substr($projRoot, strlen($docRoot));
}

$baseUrl = '/' . ltrim(str_replace('\\', '/', $baseUrl), '/');
$baseUrl = rtrim($baseUrl, '/');

echo "docRoot: $docRoot\n";
echo "projRoot: $projRoot\n";
echo "baseUrl calculado: '$baseUrl'\n";
echo "</pre>";

echo "<h2>Prueba de Sesión</h2>";
session_start();
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "</pre>";

echo "<h2>Prueba de Base de Datos</h2>";
try {
    require __DIR__ . '/../bootstrap/env.php';
    require __DIR__ . '/../config/database.php';
    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<pre style='color: green;'>✓ Conexión a base de datos exitosa</pre>";
} catch (Exception $e) {
    echo "<pre style='color: red;'>✗ Error de conexión: " . $e->getMessage() . "</pre>";
}
?>

<?php
/**
 * Logo Proxy — Sirve el logo de un propietario almacenado en BD como BLOB.
 *
 * Uso: /public/logo.php?id=<id_propietario>
 *
 * Parámetros GET:
 *   id  (int, requerido) — id_propietario cuyo logo se quiere servir.
 *
 * Respuestas:
 *   200  + imagen binaria   — logo encontrado
 *   400                     — id inválido o no proporcionado
 *   404                     — propietario sin logo registrado
 */

// ── Conexión ──────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../conexion.php';

// ── Validar parámetro ─────────────────────────────────────────────────────────
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit;
}

// ── Consultar BD ──────────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT logo, logo_mime FROM propietarios WHERE id_propietario = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['logo'])) {
    http_response_code(404);
    exit;
}

// ── Servir imagen ─────────────────────────────────────────────────────────────
$mime = !empty($row['logo_mime']) ? $row['logo_mime'] : 'image/png';

header('Content-Type: '    . $mime);
header('Cache-Control: public, max-age=86400');  // caché 24 h en navegador
header('Content-Length: '  . strlen($row['logo']));

echo $row['logo'];

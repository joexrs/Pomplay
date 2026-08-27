<?php
$pdo = new PDO("mysql:host=localhost;dbname=pomplay;charset=utf8mb4", "root", "");
$sql = "
CREATE TABLE IF NOT EXISTS `reproduccion_video` (
  `id_reproduccion`  INT(11)      NOT NULL AUTO_INCREMENT,
  `codigo_video`     CHAR(10)     DEFAULT NULL,
  `id_local`         INT(11)      DEFAULT NULL,
  `codigo_cancha`    CHAR(10)     DEFAULT NULL,
  `ip_address`       VARCHAR(45)  DEFAULT NULL,
  `user_agent`       VARCHAR(255) DEFAULT NULL,
  `creado_en`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_reproduccion`),
  KEY `idx_rep_fecha`        (`creado_en`),
  KEY `idx_rep_local_cancha` (`id_local`, `codigo_cancha`),
  KEY `idx_rep_video`        (`codigo_video`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
try {
    $pdo->exec($sql);
    echo "OK: tabla reproduccion_video creada\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
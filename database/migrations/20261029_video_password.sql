-- Video passwords replace the former local-level privacy and temporary PIN flow.
-- Existing videos remain public because the new column starts as NULL.
ALTER TABLE video
    ADD COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Optional bcrypt hash protecting this video';

-- Local privacy is replaced by the per-video password.
ALTER TABLE locales
    DROP COLUMN es_privado;

DROP PROCEDURE IF EXISTS CleanExpiredPins;
DROP PROCEDURE IF EXISTS GetActivePinByVideo;
DROP PROCEDURE IF EXISTS GetAnyPinByVideo;
DROP PROCEDURE IF EXISTS InsertVideoPin;
DROP PROCEDURE IF EXISTS InvalidatePinsByVideo;
DROP PROCEDURE IF EXISTS IsLocalPrivado;
DROP PROCEDURE IF EXISTS UpdateLocalPrivado;
DROP PROCEDURE IF EXISTS GetVideoWithLocalPrivacy;

DROP TABLE IF EXISTS video_pins;

DROP PROCEDURE IF EXISTS GetCamerasForVideoSession;
DELIMITER $$
CREATE PROCEDURE GetCamerasForVideoSession (
    IN p_id_local INT,
    IN p_codigo_cancha CHAR(10),
    IN p_fecha_partido DATE,
    IN p_hora_partido TIME
)
BEGIN
    SELECT v.codigo_video, v.video_url, v.id_camara AS id_camara,
           c.nombre AS nombre, c.posicion AS posicion_camara, v.duracion,
           (COALESCE(v.password_hash, '') <> '') AS requiere_contrasena
    FROM video v
    INNER JOIN camara c
      ON c.id_camara = v.id_camara
     AND c.id_local = v.id_local
     AND c.codigo_cancha = v.codigo_cancha
    WHERE v.id_local = p_id_local
      AND v.codigo_cancha = p_codigo_cancha
      AND DATE(v.fecha_partido) = p_fecha_partido
      AND v.hora_partido = p_hora_partido
      AND v.estado = 1
      AND c.activa = 1
    ORDER BY c.posicion ASC;
END$$
DELIMITER ;

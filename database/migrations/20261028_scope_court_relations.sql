-- Run 20261028_scope_court_relations_preflight.sql first and resolve all
-- returned records before running this migration. The existing schema already
-- contains camara.id_local; this migration scopes its constraints and relations.

UPDATE camara cam
JOIN (
    SELECT codigo_cancha, MIN(id_local) AS id_local
    FROM cancha
    GROUP BY codigo_cancha
    HAVING COUNT(*) = 1 AND MIN(id_local) IS NOT NULL
) c ON c.codigo_cancha = cam.codigo_cancha
SET cam.id_local = c.id_local
WHERE cam.id_local IS NULL;

ALTER TABLE camara
    MODIFY id_local INT NOT NULL,
    DROP FOREIGN KEY camara_ibfk_1,
    DROP FOREIGN KEY camara_local_fk,
    ADD KEY idx_camara_local_codigo (id_local, codigo_cancha),
    ADD CONSTRAINT camara_locales_fk_20261028
        FOREIGN KEY (id_local)
        REFERENCES locales (id_local)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    ADD CONSTRAINT camara_cancha_local_fk
        FOREIGN KEY (id_local, codigo_cancha)
        REFERENCES cancha (id_local, codigo_cancha)
        ON DELETE CASCADE;

ALTER TABLE video
    DROP FOREIGN KEY video_ibfk_1,
    ADD CONSTRAINT video_cancha_local_fk
        FOREIGN KEY (id_local, codigo_cancha)
        REFERENCES cancha (id_local, codigo_cancha);

DROP PROCEDURE IF EXISTS InsertCamera;
DROP PROCEDURE IF EXISTS GetCamerasByCancha;
DROP PROCEDURE IF EXISTS GetCamerasForVideoSession;
DROP PROCEDURE IF EXISTS DeleteCancha;
DELIMITER $$
CREATE PROCEDURE DeleteCancha (IN p_id_cancha INT)
BEGIN
    DELETE FROM cancha
    WHERE id_cancha = p_id_cancha;
END$$

CREATE PROCEDURE InsertCamera (
    IN p_id_local INT,
    IN p_codigo_cancha CHAR(10),
    IN p_nombre VARCHAR(50),
    IN p_posicion TINYINT,
    IN p_url_stream VARCHAR(255)
)
BEGIN
    INSERT INTO camara (id_local, codigo_cancha, nombre, posicion, go2rtc_stream)
    VALUES (p_id_local, p_codigo_cancha, p_nombre, p_posicion, p_url_stream);

    SELECT LAST_INSERT_ID() AS id_camara;
END$$

CREATE PROCEDURE GetCamerasByCancha (
    IN p_id_local INT,
    IN p_codigo_cancha CHAR(10)
)
BEGIN
    SELECT id_camara, id_local, codigo_cancha, nombre, posicion,
           go2rtc_stream AS url_stream, activa AS estado, created_at AS fecha_creacion
    FROM camara
    WHERE id_local = p_id_local
      AND codigo_cancha = p_codigo_cancha
      AND activa = 1
    ORDER BY posicion ASC;
END$$

CREATE PROCEDURE GetCamerasForVideoSession (
    IN p_id_local INT,
    IN p_codigo_cancha CHAR(10),
    IN p_fecha_partido DATE,
    IN p_hora_partido TIME
)
BEGIN
    SELECT v.codigo_video, v.video_url, v.id_camara AS id_camara,
           c.nombre AS nombre, c.posicion AS posicion_camara, v.duracion
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

-- Drop the unused legacy table only after the scoped relations are installed.
DROP TABLE IF EXISTS camaras;

SELECT
    'camara' AS tabla,
    cam.id_camara AS id_registro,
    cam.codigo_cancha,
    cam.id_local,
    COUNT(c.id_cancha) AS coincidencias,
    GROUP_CONCAT(
        CONCAT('local=', c.id_local, ', cancha=', c.id_cancha)
        ORDER BY c.id_local
        SEPARATOR '; '
    ) AS posibles_canchas
FROM camara cam
LEFT JOIN cancha c
  ON c.id_local = cam.id_local
 AND c.codigo_cancha = cam.codigo_cancha
LEFT JOIN (
    SELECT codigo_cancha, COUNT(*) AS cantidad, MIN(id_local) AS id_local_unico
    FROM cancha
    GROUP BY codigo_cancha
) codigos ON codigos.codigo_cancha = cam.codigo_cancha
GROUP BY cam.id_camara, cam.codigo_cancha, cam.id_local, codigos.cantidad
HAVING (cam.id_local IS NOT NULL AND COUNT(c.id_cancha) = 0)
    OR (
        cam.id_local IS NULL
        AND (
            COALESCE(codigos.cantidad, 0) <> 1
            OR MAX(codigos.id_local_unico) IS NULL
        )
    );

SELECT v.codigo_video, v.id_local, v.codigo_cancha
FROM video v
LEFT JOIN cancha c
  ON c.id_local = v.id_local
 AND c.codigo_cancha = v.codigo_cancha
WHERE v.id_local IS NULL
   OR c.id_cancha IS NULL;

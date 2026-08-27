-- Tablas para métricas de Estadísticas (admin)
-- Ejecutar una vez en la base de datos PomPlay

CREATE TABLE IF NOT EXISTS `busqueda_video` (
  `id_busqueda`            INT(11)      NOT NULL AUTO_INCREMENT,
  `id_local`               INT(11)      DEFAULT NULL,
  `codigo_cancha`          CHAR(10)     DEFAULT NULL,
  `fecha_buscada`          DATE         DEFAULT NULL,
  `hora_buscada`           VARCHAR(20)  DEFAULT NULL,
  `resultados_encontrados` INT(11)      NOT NULL DEFAULT 0,
  `codigo_video_resultado` CHAR(10)     DEFAULT NULL,
  `ip_address`             VARCHAR(45)  DEFAULT NULL,
  `user_agent`             VARCHAR(255) DEFAULT NULL,
  `creado_en`              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_busqueda`),
  KEY `idx_busqueda_fecha` (`creado_en`),
  KEY `idx_busqueda_local_cancha` (`id_local`, `codigo_cancha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clip` (
  `id_clip`       INT(11)       NOT NULL AUTO_INCREMENT,
  `codigo_video`  CHAR(10)      DEFAULT NULL,
  `id_local`      INT(11)       DEFAULT NULL,
  `codigo_cancha` CHAR(10)      DEFAULT NULL,
  `video_url`     VARCHAR(255)  NOT NULL,
  `clip_url`      VARCHAR(255)  NOT NULL,
  `filename`      VARCHAR(100)  NOT NULL,
  `start_time`    DECIMAL(10,2) NOT NULL,
  `end_time`      DECIMAL(10,2) NOT NULL,
  `duracion`      DECIMAL(10,2) NOT NULL,
  `id_camara`     INT(11)       DEFAULT NULL,
  `ip_address`    VARCHAR(45)   DEFAULT NULL,
  `creado_en`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id_clip`),
  KEY `idx_clip_fecha` (`creado_en`),
  KEY `idx_clip_local_cancha` (`id_local`, `codigo_cancha`),
  KEY `idx_clip_video` (`codigo_video`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

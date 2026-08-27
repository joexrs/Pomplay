-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 24-07-2026 a las 06:51:27
-- Versión del servidor: 11.8.8-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u462193081_pomplay`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `ActivarMembresia` (IN `p_id_membresia` INT)   BEGIN
    UPDATE membresias 
    SET estado = 1,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_membresia = p_id_membresia;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `AssignLocalOwner` (IN `id_local_param` INT, IN `id_propietario_param` INT)   BEGIN
    UPDATE locales
    SET id_propietario = id_propietario_param,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_local = id_local_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CheckMembresiaExists` (IN `p_id_usuario` INT, IN `p_id_local` INT)   BEGIN
    SELECT id_membresia 
    FROM membresias 
    WHERE id_usuario = p_id_usuario 
    AND id_local = p_id_local 
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CleanExpiredPins` ()   BEGIN
    DELETE FROM video_pins
    WHERE expira_en <= NOW();
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CountOwnerVideos` (IN `id_local_param` INT)   BEGIN
    SELECT COUNT(*) AS total
    FROM video
    WHERE id_local = id_local_param
      AND estado = 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CountVideosByLocal` (IN `id_local_param` INT)   BEGIN
    SELECT COUNT(*) AS total
    FROM video
    WHERE id_local = id_local_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CountVideosFiltros` (IN `fecha_param` DATE, IN `codigo_cancha_param` CHAR(10), IN `id_categoria_param` INT)   BEGIN
    SELECT COUNT(*) AS total
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.estado = 1
      AND (fecha_param IS NULL OR DATE(v.fecha_partido) = fecha_param)
      AND (codigo_cancha_param IS NULL OR v.codigo_cancha = codigo_cancha_param)
      AND (id_categoria_param IS NULL OR c.id_categoria = id_categoria_param);
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CreateLocal` (IN `nombre_local_param` VARCHAR(100), IN `id_propietario_param` INT)   BEGIN
    INSERT INTO locales (nombre_local, id_propietario, estado)
    VALUES (nombre_local_param, id_propietario_param, 1);

    SELECT LAST_INSERT_ID() AS id_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CreateLocalSimple` (IN `nombre_local_param` VARCHAR(100), IN `estado_param` TINYINT)   BEGIN
    INSERT INTO locales (nombre_local, estado)
    VALUES (nombre_local_param, estado_param);

    SELECT LAST_INSERT_ID() AS id_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CreateMembership` (IN `id_usuario_param` INT, IN `id_local_param` INT, IN `fecha_inicio_param` DATE, IN `fecha_vencimiento_param` DATE, IN `tipo_membresia_param` VARCHAR(50))   BEGIN
    INSERT INTO membresias (id_usuario, id_local, fecha_inicio, fecha_vencimiento, tipo_membresia, estado)
    VALUES (id_usuario_param, id_local_param, fecha_inicio_param, fecha_vencimiento_param, tipo_membresia_param, 1);
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CreateNuevaMembresia` (IN `p_id_usuario` INT, IN `p_id_local` INT, IN `p_fecha_inicio` DATE, IN `p_fecha_vencimiento` DATE, IN `p_tipo_membresia` VARCHAR(50))   BEGIN
    INSERT INTO membresias (
        id_usuario, 
        id_local, 
        fecha_inicio, 
        fecha_vencimiento, 
        tipo_membresia, 
        estado
    ) VALUES (
        p_id_usuario,
        p_id_local,
        p_fecha_inicio,
        p_fecha_vencimiento,
        p_tipo_membresia,
        1
    );
    
    SELECT LAST_INSERT_ID() AS id_membresia;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CreateOwnerUser` (IN `usuario_param` VARCHAR(255), IN `password_param` VARCHAR(255), IN `id_propietario_param` INT)   BEGIN
    INSERT INTO usuarios (usuario, password, id_rol, id_propietario, estado)
    VALUES (usuario_param, password_param, 2, id_propietario_param, 1);

    SELECT LAST_INSERT_ID() AS id_usuario;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `CreatePropietario` (IN `nombres_param` VARCHAR(255), IN `apellidos_param` VARCHAR(255), IN `email_param` VARCHAR(255), IN `telefono_param` VARCHAR(20), IN `direccion_param` VARCHAR(255))   BEGIN
    INSERT INTO propietarios (nombres, apellidos, email, telefono, direccion, estado)
    VALUES (nombres_param, apellidos_param, email_param, telefono_param, direccion_param, 1);

    SELECT LAST_INSERT_ID() AS id_propietario;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `DeleteCancha` (IN `codigo_cancha_param` CHAR(10))   BEGIN
    DELETE FROM cancha 
    WHERE codigo_cancha = codigo_cancha_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `DeleteLocal` (IN `id_local_param` INT)   BEGIN
    UPDATE locales
    SET estado = 0
    WHERE id_local = id_local_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `DeleteVideo` (IN `codigo_video_param` CHAR(10))   BEGIN
    DELETE FROM video 
    WHERE codigo_video = codigo_video_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `FindAdminByUser` (IN `usuario_param` VARCHAR(255))   BEGIN
    SELECT u.*, r.nombre_rol AS rol
    FROM usuarios u
    LEFT JOIN roles r ON u.id_rol = r.id_rol
    WHERE u.usuario = usuario_param
      AND u.estado = 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `FindUserByUsername` (IN `username_param` VARCHAR(255))   BEGIN
    SELECT
        u.id_usuario,
        u.usuario,
        u.password,
        u.id_rol,
        u.id_propietario,
        u.estado,
        u.ultimo_acceso,
        r.nombre_rol AS rol,
        p.nombres,
        p.apellidos
    FROM usuarios u
    LEFT JOIN roles r ON u.id_rol = r.id_rol
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE u.usuario = username_param
      AND u.estado = 1
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetActiveMembershipPrices` ()   BEGIN
    SELECT 
        id_precio, 
        tipo_membresia, 
        nombre_display, 
        precio, 
        duracion_meses, 
        descripcion, 
        activo
    FROM precios_membresias 
    WHERE activo = 1 
    ORDER BY duracion_meses ASC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetActivePinByVideo` (IN `p_codigo_video` CHAR(10))   BEGIN
    SELECT id, codigo_video, pin_hash, pin_longitud, expira_en, creado_en
    FROM video_pins
    WHERE codigo_video = p_codigo_video
      AND expira_en > NOW()
    ORDER BY creado_en DESC
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetActivePropietarios` ()   BEGIN
    SELECT id_propietario, nombres, apellidos, email
    FROM propietarios
    WHERE estado = 1
    ORDER BY nombres, apellidos;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminCanchaById` (IN `p_id_cancha` INT)   BEGIN
    SELECT * FROM cancha WHERE id_cancha = p_id_cancha;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminCanchas` (IN `id_local_param` INT)   BEGIN
    SELECT c.*, l.nombre_local
    FROM cancha c
    LEFT JOIN locales l ON c.id_local = l.id_local
    WHERE id_local_param IS NULL OR c.id_local = id_local_param
    ORDER BY l.nombre_local, c.codigo_cancha;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminDashboardStats` ()   BEGIN
    SELECT
        (SELECT COUNT(*) FROM video) AS total_videos,
        (SELECT COUNT(*) FROM cancha) AS total_canchas,
        (SELECT COUNT(*) FROM usuarios WHERE id_rol = 1) AS total_admins,
        (SELECT COUNT(*) FROM locales WHERE estado = 1) AS total_locales,
        (SELECT COUNT(*) FROM usuarios WHERE id_rol = 2) AS total_owners;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminLocalesWithMemberships` ()   BEGIN
    SELECT
        l.*,
        p.nombres,
        p.apellidos,
        p.email AS propietario_email,
        m.fecha_inicio,
        m.fecha_vencimiento,
        m.tipo_membresia,
        CASE
            WHEN m.fecha_vencimiento >= CURDATE() THEN 'ACTIVA'
            ELSE 'VENCIDA'
        END AS estado_membresia
    FROM locales l
    LEFT JOIN propietarios p ON l.id_propietario = p.id_propietario
    LEFT JOIN usuarios u ON l.id_propietario = u.id_propietario
    LEFT JOIN membresias m ON u.id_usuario = m.id_usuario AND l.id_local = m.id_local
    WHERE l.estado = 1
    ORDER BY l.fecha_creacion DESC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminOwners` ()   BEGIN
    SELECT
        u.id_usuario,
        u.usuario,
        u.password,
        r.nombre_rol AS rol,
        p.nombres,
        p.apellidos,
        p.telefono,
        p.email
    FROM usuarios u
    LEFT JOIN roles r ON u.id_rol = r.id_rol
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE u.id_rol = 2 AND u.estado = 1
    ORDER BY p.nombres ASC, p.apellidos ASC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminRecentVideos` (IN `limit_val` INT)   BEGIN
    SELECT v.*, c.descripcion AS cancha_nombre
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha AND c.id_local = v.id_local
    WHERE v.estado = 1
    ORDER BY v.fecha_partido DESC
    LIMIT limit_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminTopCanchas` (IN `limit_val` INT)   BEGIN
    SELECT c.descripcion, COUNT(v.codigo_video) AS total
    FROM video v
    JOIN cancha c ON v.codigo_cancha = c.codigo_cancha AND c.id_local = v.id_local
    WHERE v.estado = 1
    GROUP BY c.id_cancha, c.descripcion
    ORDER BY total DESC
    LIMIT limit_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminVideos` ()   BEGIN
    SELECT v.*, c.descripcion AS nombre_cancha
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha AND c.id_local = v.id_local
    WHERE v.estado = 1
    ORDER BY v.codigo_video DESC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminVideosByMonth` ()   BEGIN
    SELECT MONTH(fecha_partido) AS mes, COUNT(*) AS total
    FROM video
    WHERE YEAR(fecha_partido) = YEAR(CURDATE())
    GROUP BY MONTH(fecha_partido)
    ORDER BY mes;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAdminVideosFiltered` (IN `p_id_local` INT, IN `p_codigo_cancha` VARCHAR(10), IN `p_fecha` DATE)   BEGIN
    SELECT v.*, c.descripcion AS nombre_cancha, l.nombre_local
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha AND c.id_local = v.id_local
    LEFT JOIN locales l ON v.id_local = l.id_local
    WHERE 
        (p_id_local IS NULL OR v.id_local = p_id_local)
        AND (p_codigo_cancha IS NULL OR v.codigo_cancha = p_codigo_cancha)
        AND (p_fecha IS NULL OR DATE(v.fecha_partido) = p_fecha)
        AND v.estado = 1
    ORDER BY v.fecha_partido DESC, v.codigo_video DESC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAllCanchas` ()   BEGIN
    SELECT * FROM cancha WHERE estado = 1 ORDER BY descripcion;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAllCanchasActivas` ()   BEGIN
    SELECT * 
    FROM cancha 
    WHERE estado = 1 
    ORDER BY descripcion;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAllCategorias` ()   BEGIN
    SELECT * FROM categorias WHERE estado = 1 ORDER BY nombre_categoria;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAllFechaYPaginado` (IN `fecha_param` DATE, IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE DATE(v.fecha_partido) = fecha_param
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAllLocales` (IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        id_local,
        nombre_local,
        descripcion,
        ciudad,
        direccion,
        telefono,
        email,
        id_propietario,
        estado,
        fecha_creacion,
        fecha_actualizacion
    FROM locales 
    WHERE estado = 1 
    ORDER BY nombre_local
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAllMembershipPrices` ()   BEGIN
    SELECT 
        id_precio,
        tipo_membresia,
        nombre_display,
        precio,
        duracion_meses,
        descripcion,
        activo
    FROM precios_membresias
    ORDER BY duracion_meses ASC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAllPaginado` (IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha,
        cat.nombre_categoria AS nombre_categoria, cat.imagen AS imagen_categoria
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    LEFT JOIN categoria cat ON c.id_categoria = cat.id_categoria
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAvailableHours` (IN `p_fecha` DATE, IN `p_codigo_cancha` CHAR(10))   BEGIN
    SELECT DISTINCT HOUR(fecha_partido) AS hora
    FROM video
    WHERE DATE(fecha_partido) = p_fecha
      AND codigo_cancha = p_codigo_cancha
    ORDER BY hora;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetAvailableLocalsWithVideos` ()   BEGIN
    SELECT DISTINCT l.*
    FROM locales l
    INNER JOIN video v ON l.id_local = v.id_local
    WHERE l.estado = 1
    ORDER BY l.nombre_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetByCategoriaCodigoYFechaYPaginado` (IN `id_categoria_param` INT, IN `codigo_cancha_param` CHAR(10), IN `fecha_param` DATE, IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha,
        cat.nombre_categoria AS nombre_categoria
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    INNER JOIN categoria cat ON c.id_categoria = cat.id_categoria
    WHERE c.id_categoria = id_categoria_param
        AND v.codigo_cancha = codigo_cancha_param
        AND DATE(v.fecha_partido) = fecha_param
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetByCategoriaCodigoYPaginado` (IN `id_categoria_param` INT, IN `codigo_cancha_param` CHAR(10), IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha,
        cat.nombre_categoria AS nombre_categoria
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    INNER JOIN categoria cat ON c.id_categoria = cat.id_categoria
    WHERE c.id_categoria = id_categoria_param
        AND v.codigo_cancha = codigo_cancha_param
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetByCategoriaYFechaYPaginado` (IN `id_categoria_param` INT, IN `fecha_param` DATE, IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha,
        cat.nombre_categoria AS nombre_categoria
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    INNER JOIN categoria cat ON c.id_categoria = cat.id_categoria
    WHERE c.id_categoria = id_categoria_param
        AND DATE(v.fecha_partido) = fecha_param
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetByCategoriaYPaginado` (IN `id_categoria_param` INT, IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha,
        cat.nombre_categoria AS nombre_categoria
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    INNER JOIN categoria cat ON c.id_categoria = cat.id_categoria
    WHERE c.id_categoria = id_categoria_param
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetByCodigoYFechaYPaginado` (IN `fecha_param` DATE, IN `codigo_cancha_param` CHAR(10), IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.codigo_cancha = codigo_cancha_param
        AND DATE(v.fecha_partido) = fecha_param
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetByCodigoYPaginado` (IN `codigo_cancha_param` CHAR(10), IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.codigo_cancha = codigo_cancha_param
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetCamerasByCancha` (IN `p_codigo_cancha` CHAR(10))   BEGIN
    SELECT 
        id_camara,
        codigo_cancha,
        nombre,
        posicion,
        go2rtc_stream AS url_stream,
        activa AS estado,
        created_at AS fecha_creacion
    FROM camara
    WHERE codigo_cancha = p_codigo_cancha
      AND activa = 1
    ORDER BY posicion ASC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetCamerasForVideoSession` (IN `p_codigo_cancha` CHAR(10), IN `p_fecha_partido` DATE, IN `p_hora_partido` TIME)   BEGIN
    SELECT 
        v.codigo_video, v.video_url, v.id_camara AS id_camara,
        c.nombre AS nombre, c.posicion AS posicion_camara, v.duracion
    FROM video v
    INNER JOIN camara c ON v.id_camara = c.id_camara
    WHERE v.codigo_cancha = p_codigo_cancha
      AND DATE(v.fecha_partido) = p_fecha_partido
      AND v.hora_partido = p_hora_partido
      AND v.estado = 1
      AND c.activa = 1
    ORDER BY c.posicion ASC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetCanchasByCategoria` (IN `p_categoria` INT)   BEGIN
    SELECT * FROM cancha WHERE id_categoria = p_categoria AND estado = 1 ORDER BY descripcion;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetCanchasByLocal` (IN `p_id_local` INT)   BEGIN
    SELECT * FROM cancha WHERE id_local = p_id_local AND estado = 1 ORDER BY descripcion;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetCourtsInLocal` (IN `p_id_local` INT)   BEGIN
    SELECT * FROM cancha WHERE id_local = p_id_local AND estado = 1 ORDER BY descripcion;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetLocalById` (IN `id_local_param` INT)   BEGIN
    SELECT *
    FROM locales
    WHERE id_local = id_local_param
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetLocalConPropietarioYMembresia` (IN `p_id_local` INT)   BEGIN
    SELECT 
        l.*,
        p.nombres,
        p.apellidos,
        p.email,
        p.telefono,
        p.direccion,
        m.fecha_inicio,
        m.fecha_vencimiento,
        m.tipo_membresia,
        m.id_membresia,
        m.estado AS estado_membresia
    FROM locales l
    LEFT JOIN propietarios p ON l.id_propietario = p.id_propietario
    LEFT JOIN usuarios u ON p.id_propietario = u.id_propietario AND u.id_rol = 2
    LEFT JOIN membresias m ON u.id_usuario = m.id_usuario AND m.id_local = l.id_local
    WHERE l.id_local = p_id_local
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetLocalesPaginadoSimple` (IN `p_limit` INT, IN `p_offset` INT)   BEGIN
    SELECT * 
    FROM locales 
    WHERE estado = 1 
    ORDER BY nombre_local 
    LIMIT p_limit OFFSET p_offset;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetLocalNombre` (IN `p_id_local` INT)   BEGIN
    SELECT nombre_local 
    FROM locales 
    WHERE id_local = p_id_local
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetLocalsWithCourts` ()   BEGIN
    SELECT
        l.*,
        COUNT(c.codigo_cancha) AS total_canchas,
        COUNT(DISTINCT c.codigo_cancha) AS canchas_activas
    FROM locales l
    LEFT JOIN cancha c ON l.id_local = c.id_local AND c.estado = 1
    WHERE l.estado = 1
    GROUP BY l.id_local
    ORDER BY l.nombre_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetLocalsWithOwners` ()   BEGIN
    SELECT
        l.*,
        GROUP_CONCAT(DISTINCT u.usuario) AS duenos,
        COUNT(DISTINCT u.id_usuario) AS total_duenos
    FROM locales l
    LEFT JOIN usuarios u ON u.id_propietario = l.id_propietario AND u.id_rol = 2 AND u.estado = 1
    WHERE l.estado = 1
    GROUP BY l.id_local
    ORDER BY l.nombre_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetMembershipByUserLocal` (IN `id_usuario_param` INT, IN `id_local_param` INT)   BEGIN
    SELECT m.*, l.nombre_local
    FROM membresias m
    LEFT JOIN locales l ON m.id_local = l.id_local
    WHERE m.id_usuario = id_usuario_param
      AND m.id_local = id_local_param
    ORDER BY m.fecha_vencimiento DESC
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetMembershipPriceByType` (IN `p_tipo_membresia` VARCHAR(50))   BEGIN
    SELECT 
        id_precio,
        tipo_membresia,
        nombre_display,
        precio,
        duracion_meses,
        descripcion,
        activo
    FROM precios_membresias
    WHERE tipo_membresia = p_tipo_membresia
    AND activo = 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetMembresiaById` (IN `p_id_membresia` INT)   BEGIN
    SELECT * 
    FROM membresias 
    WHERE id_membresia = p_id_membresia
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetNextCanchaCodeByLocal` (IN `id_local_param` INT)   BEGIN
    SELECT CONCAT(
        'C',
        LPAD(
            COALESCE(MAX(CAST(SUBSTRING(codigo_cancha, 2) AS UNSIGNED)), 0) + 1,
            2,
            '0'
        )
    ) AS next_code
    FROM cancha
    WHERE id_local = id_local_param
      AND codigo_cancha REGEXP '^C[0-9]+$';
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetNextVideoCode` ()   BEGIN
    SELECT 
        CONCAT('V', LPAD(COALESCE(MAX(CAST(SUBSTRING(codigo_video, 2) AS UNSIGNED)), 0) + 1, 3, '0')) AS next_code
    FROM video 
    WHERE codigo_video LIKE 'V%';
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetOwnerDashboardVideos` (IN `id_local_param` INT, IN `limit_val` INT)   BEGIN
    SELECT v.*, c.descripcion AS cancha_nombre
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.id_local = id_local_param
    ORDER BY v.fecha_partido DESC
    LIMIT limit_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetOwnerLocales` (IN `id_propietario_param` INT)   BEGIN
    SELECT nombre_local
    FROM locales
    WHERE id_propietario = id_propietario_param
      AND estado = 1
    ORDER BY nombre_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetOwnerLocalList` (IN `id_propietario_param` INT)   BEGIN
    SELECT *
    FROM locales
    WHERE id_propietario = id_propietario_param
      AND estado = 1
    ORDER BY nombre_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetOwnerProfileForEdit` (IN `id_usuario_param` INT)   BEGIN
    SELECT
        u.*,
        r.nombre_rol AS rol,
        p.nombres,
        p.apellidos,
        p.email,
        p.email AS propietario_email,
        p.telefono,
        p.direccion
    FROM usuarios u
    LEFT JOIN roles r ON u.id_rol = r.id_rol
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE u.id_usuario = id_usuario_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetOwnerUserByPropietario` (IN `id_propietario_param` INT)   BEGIN
    SELECT id_usuario
    FROM usuarios
    WHERE id_propietario = id_propietario_param
    AND estado = 1
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetOwnerVideos` (IN `id_local_param` INT, IN `limit_val` INT, IN `offset_val` INT)   BEGIN
    SELECT
        v.*,
        c.descripcion AS cancha_nombre,
        c.descripcion AS descripcion_cancha,
        cat.nombre_categoria,
        l.nombre_local
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    LEFT JOIN categoria cat ON c.id_categoria = cat.id_categoria
    LEFT JOIN locales l ON v.id_local = l.id_local
    WHERE v.id_local = id_local_param
      AND v.estado = 1
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC
    LIMIT limit_val OFFSET offset_val;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetOwnerVideosFiltered` (IN `id_local_param` INT, IN `fecha_param` DATE, IN `codigo_cancha_param` CHAR(10))   BEGIN
    SELECT v.*, c.descripcion AS cancha_nombre
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.id_local = id_local_param
      AND (fecha_param IS NULL OR DATE(v.fecha_partido) = fecha_param)
      AND (codigo_cancha_param IS NULL OR v.codigo_cancha = codigo_cancha_param)
    ORDER BY v.fecha_partido DESC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetPrecioByTipo` (IN `p_tipo_membresia` VARCHAR(50))   BEGIN
    SELECT * 
    FROM precios_membresias 
    WHERE tipo_membresia = p_tipo_membresia 
    AND activo = 1
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetProfileByUserId` (IN `id_usuario_param` INT)   BEGIN
    SELECT
        u.id_usuario,
        u.usuario,
        u.password,
        u.id_propietario,
        r.nombre_rol AS rol,
        p.nombres,
        p.apellidos,
        p.email,
        p.telefono,
        p.direccion
    FROM usuarios u
    LEFT JOIN roles r ON u.id_rol = r.id_rol
    LEFT JOIN propietarios p ON u.id_propietario = p.id_propietario
    WHERE u.id_usuario = id_usuario_param
      AND u.estado = 1
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetPropietarioById` (IN `id_propietario_param` INT)   BEGIN
    SELECT *
    FROM propietarios
    WHERE id_propietario = id_propietario_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetPropietarioByLocal` (IN `id_local_param` INT)   BEGIN
    SELECT p.*
    FROM propietarios p
    INNER JOIN locales l ON l.id_propietario = p.id_propietario
    WHERE l.id_local = id_local_param
    AND p.estado = 1
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetVideoByCodigo` (IN `codigo_video_param` CHAR(10))   BEGIN
    SELECT 
        v.codigo_video, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, c.descripcion AS descripcion_cancha
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.codigo_video = codigo_video_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetVideoForEdit` (IN `p_codigo_video` CHAR(10))   BEGIN
    SELECT 
        v.*,
        c.descripcion AS nombre_cancha,
        c.id_local
    FROM video v
    LEFT JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE v.codigo_video = p_codigo_video
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetVideoPorCodigo` (IN `codigo_video_param` CHAR(10))   BEGIN
    SELECT 
        v.codigo_video,
        v.fecha_partido,
        v.descripcion,
        v.hora_partido,
        v.codigo_cancha,
        v.video_url,
        v.observacion,
        v.fecha_registro,
        v.duracion,
        c.descripcion AS descripcion_cancha
    FROM 
        video v
    INNER JOIN 
        cancha c ON v.codigo_cancha = c.codigo_cancha
    WHERE 
        v.codigo_video = codigo_video_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetVideosByCanchaWithCameras` (IN `p_codigo_cancha` CHAR(10), IN `p_fecha` DATE)   BEGIN
    SELECT 
        v.codigo_video,
        v.id_local,
        v.fecha_partido,
        v.descripcion,
        v.hora_partido,
        v.codigo_cancha,
        v.video_url,
        v.duracion,
        v.estado,
        v.es_descargable,
        v.id_camara,
        c.nombre AS nombre_camara,
        c.posicion AS posicion_camara,
        ca.descripcion AS nombre_cancha
    FROM video v
    LEFT JOIN camara c ON v.id_camara = c.id_camara
    INNER JOIN cancha ca ON v.codigo_cancha = ca.codigo_cancha
    WHERE v.codigo_cancha = p_codigo_cancha
      AND v.estado = 1
      AND (p_fecha IS NULL OR DATE(v.fecha_partido) = p_fecha)
    ORDER BY v.fecha_partido DESC, v.hora_partido DESC, c.posicion ASC;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `GetVideoWithLocalPrivacy` (IN `p_codigo_video` CHAR(10))   BEGIN
    SELECT 
        v.codigo_video, v.id_local, v.fecha_partido, v.descripcion, v.hora_partido,
        v.codigo_cancha, v.video_url, v.observacion, v.fecha_registro,
        v.duracion, v.estado,
        c.descripcion AS descripcion_cancha,
        l.nombre_local, COALESCE(l.es_privado, 0) AS es_privado
    FROM video v
    INNER JOIN cancha c ON v.codigo_cancha = c.codigo_cancha
    LEFT JOIN locales l ON v.id_local = l.id_local
    WHERE v.codigo_video = p_codigo_video;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `InsertCamera` (IN `p_codigo_cancha` CHAR(10), IN `p_nombre` VARCHAR(50), IN `p_posicion` TINYINT, IN `p_url_stream` VARCHAR(255))   BEGIN
    INSERT INTO camara (codigo_cancha, nombre, posicion, go2rtc_stream)
    VALUES (p_codigo_cancha, p_nombre, p_posicion, p_url_stream);

    SELECT LAST_INSERT_ID() AS id_camara;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `InsertCanchaLocal` (IN `codigo_cancha_param` CHAR(10), IN `descripcion_param` VARCHAR(70), IN `id_local_param` INT, IN `imagen_url_param` VARCHAR(255), IN `tipo_cancha_param` VARCHAR(100), IN `ubicacion_param` VARCHAR(150))   BEGIN
    INSERT INTO cancha (
        codigo_cancha,
        descripcion,
        id_local,
        imagen_url,
        tipo_cancha,
        ubicacion
    )
    VALUES (
        codigo_cancha_param,
        descripcion_param,
        id_local_param,
        imagen_url_param,
        tipo_cancha_param,
        ubicacion_param
    );
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `InsertVideo` (IN `codigo_video_param` CHAR(10), IN `fecha_partido_param` DATE, IN `descripcion_param` VARCHAR(50), IN `hora_partido_param` TIME, IN `codigo_cancha_param` CHAR(10), IN `video_url_param` VARCHAR(255), IN `observacion_param` TEXT, IN `duracion_param` VARCHAR(50))   BEGIN
    INSERT INTO video (
        codigo_video, fecha_partido, descripcion, hora_partido,
        codigo_cancha, video_url, observacion, fecha_registro, duracion
    ) VALUES (
        codigo_video_param, fecha_partido_param, descripcion_param, hora_partido_param,
        codigo_cancha_param, video_url_param, observacion_param, CURDATE(), duracion_param
    );
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `InsertVideoBasic` (IN `codigo_video_param` CHAR(10), IN `fecha_partido_param` DATE, IN `descripcion_param` VARCHAR(50), IN `hora_partido_param` TIME, IN `codigo_cancha_param` CHAR(10), IN `video_url_param` VARCHAR(255), IN `observacion_param` TEXT, IN `duracion_param` VARCHAR(50))   BEGIN
    INSERT INTO video (codigo_video, fecha_partido, descripcion, hora_partido, codigo_cancha, video_url, observacion, fecha_registro, duracion)
    VALUES (codigo_video_param, fecha_partido_param, descripcion_param, hora_partido_param, codigo_cancha_param, video_url_param, observacion_param, CURDATE(), duracion_param);
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `InsertVideoCompleto` (IN `p_codigo_video` CHAR(10), IN `p_fecha_partido` DATE, IN `p_descripcion` VARCHAR(50), IN `p_hora_partido` TIME, IN `p_codigo_cancha` CHAR(10), IN `p_video_url` VARCHAR(255), IN `p_observacion` TEXT, IN `p_duracion` VARCHAR(50))   BEGIN
    INSERT INTO video (
        codigo_video, fecha_partido, descripcion, hora_partido,
        codigo_cancha, video_url, observacion, fecha_registro, duracion,
        estado, es_descargable
    ) VALUES (
        p_codigo_video, p_fecha_partido, p_descripcion, p_hora_partido,
        p_codigo_cancha, p_video_url, p_observacion, CURDATE(), p_duracion,
        1, 1
    );
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `InsertVideoPin` (IN `p_codigo_video` CHAR(10), IN `p_pin_hash` VARCHAR(255), IN `p_pin_longitud` TINYINT, IN `p_expira_en` DATETIME)   BEGIN
    INSERT INTO video_pins (codigo_video, pin_hash, pin_longitud, expira_en)
    VALUES (p_codigo_video, p_pin_hash, p_pin_longitud, p_expira_en);
    
    SELECT LAST_INSERT_ID() AS id;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `InvalidatePinsByVideo` (IN `p_codigo_video` CHAR(10))   BEGIN
    DELETE FROM video_pins
    WHERE codigo_video = p_codigo_video;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `IsLocalPrivado` (IN `p_id_local` INT)   BEGIN
    SELECT es_privado
    FROM locales
    WHERE id_local = p_id_local
    LIMIT 1;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `RenovarMembresia` (IN `p_id_membresia` INT, IN `p_fecha_inicio` DATE, IN `p_fecha_vencimiento` DATE, IN `p_tipo_membresia` VARCHAR(50))   BEGIN
    UPDATE membresias 
    SET fecha_inicio = p_fecha_inicio,
        fecha_vencimiento = p_fecha_vencimiento,
        tipo_membresia = p_tipo_membresia,
        estado = 1,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_membresia = p_id_membresia;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateCamera` (IN `p_id_camara` INT, IN `p_nombre` VARCHAR(50), IN `p_posicion` TINYINT, IN `p_url_stream` VARCHAR(255), IN `p_estado` TINYINT)   BEGIN
    UPDATE camara
    SET nombre = p_nombre,
        posicion = p_posicion,
        go2rtc_stream = p_url_stream,
        activa = p_estado
    WHERE id_camara = p_id_camara;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateCanchaLocal` (IN `p_id_cancha` INT, IN `p_descripcion` VARCHAR(70), IN `p_id_local` INT, IN `p_imagen_url` VARCHAR(255), IN `p_tipo_cancha` VARCHAR(100), IN `p_ubicacion` VARCHAR(150))   BEGIN
    UPDATE cancha
    SET descripcion = p_descripcion,
        id_local = p_id_local,
        imagen_url = COALESCE(p_imagen_url, imagen_url),
        tipo_cancha = p_tipo_cancha,
        ubicacion = p_ubicacion
    WHERE id_cancha = p_id_cancha;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateLastAccess` (IN `id_usuario_param` INT)   BEGIN
    UPDATE usuarios
    SET ultimo_acceso = NOW()
    WHERE id_usuario = id_usuario_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateLocalAdmin` (IN `id_local_param` INT, IN `nombre_local_param` VARCHAR(100), IN `descripcion_param` TEXT, IN `ciudad_param` VARCHAR(100), IN `direccion_param` VARCHAR(255), IN `telefono_param` VARCHAR(20), IN `email_param` VARCHAR(255), IN `id_propietario_param` INT)   BEGIN
    UPDATE locales
    SET nombre_local = nombre_local_param,
        descripcion = descripcion_param,
        ciudad = ciudad_param,
        direccion = direccion_param,
        telefono = telefono_param,
        email = email_param,
        id_propietario = id_propietario_param
    WHERE id_local = id_local_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateLocalCompleto` (IN `p_id_local` INT, IN `p_nombre_local` VARCHAR(100), IN `p_id_propietario` INT)   BEGIN
    UPDATE locales
    SET nombre_local = p_nombre_local,
        id_propietario = p_id_propietario,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_local = p_id_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateLocalPrivado` (IN `p_id_local` INT, IN `p_es_privado` TINYINT)   BEGIN
    UPDATE locales
    SET es_privado = p_es_privado,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_local = p_id_local;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateLocalSimple` (IN `id_local_param` INT, IN `nombre_local_param` VARCHAR(100), IN `descripcion_param` TEXT, IN `ciudad_param` VARCHAR(100), IN `direccion_param` VARCHAR(255), IN `telefono_param` VARCHAR(20), IN `email_param` VARCHAR(255), IN `estado_param` TINYINT)   BEGIN
    UPDATE locales
    SET nombre_local = COALESCE(nombre_local_param, nombre_local),
        descripcion = COALESCE(descripcion_param, descripcion),
        ciudad = COALESCE(ciudad_param, ciudad),
        direccion = COALESCE(direccion_param, direccion),
        telefono = COALESCE(telefono_param, telefono),
        email = COALESCE(email_param, email),
        estado = COALESCE(estado_param, estado)
    WHERE id_local = id_local_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateMembership` (IN `id_membresia_param` INT, IN `fecha_inicio_param` DATE, IN `fecha_vencimiento_param` DATE, IN `tipo_membresia_param` VARCHAR(50))   BEGIN
    UPDATE membresias
    SET fecha_inicio = fecha_inicio_param,
        fecha_vencimiento = fecha_vencimiento_param,
        tipo_membresia = tipo_membresia_param,
        estado = 1
    WHERE id_membresia = id_membresia_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateMembershipPrice` (IN `p_id_precio` INT, IN `p_precio` DECIMAL(10,2))   BEGIN
    UPDATE precios_membresias
    SET precio = p_precio,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_precio = p_id_precio;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateMembresiaExistente` (IN `p_id_membresia` INT, IN `p_fecha_inicio` DATE, IN `p_fecha_vencimiento` DATE, IN `p_tipo_membresia` VARCHAR(50))   BEGIN
    UPDATE membresias
    SET fecha_inicio = p_fecha_inicio,
        fecha_vencimiento = p_fecha_vencimiento,
        tipo_membresia = p_tipo_membresia,
        estado = 1,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE id_membresia = p_id_membresia;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateOwnerProfile` (IN `id_propietario_param` INT, IN `id_usuario_param` INT, IN `nombres_param` VARCHAR(255), IN `apellidos_param` VARCHAR(255), IN `email_param` VARCHAR(255), IN `telefono_param` VARCHAR(20), IN `direccion_param` VARCHAR(255), IN `nombre_completo_param` VARCHAR(255))   BEGIN
    UPDATE propietarios
    SET nombres = nombres_param,
        apellidos = apellidos_param,
        email = email_param,
        telefono = telefono_param,
        direccion = direccion_param
    WHERE id_propietario = id_propietario_param;

    UPDATE usuarios
    SET email = email_param,
        nombre_completo = nombre_completo_param
    WHERE id_usuario = id_usuario_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdatePrecioMembresia` (IN `p_id_precio` INT, IN `p_precio` DECIMAL(10,2))   BEGIN
    UPDATE precios_membresias 
    SET precio = p_precio, 
        fecha_actualizacion = CURRENT_TIMESTAMP 
    WHERE id_precio = p_id_precio;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdatePropietarioProfile` (IN `id_propietario_param` INT, IN `nombres_param` VARCHAR(255), IN `apellidos_param` VARCHAR(255), IN `email_param` VARCHAR(255), IN `telefono_param` VARCHAR(20), IN `direccion_param` VARCHAR(255))   BEGIN
    UPDATE propietarios
    SET nombres = nombres_param,
        apellidos = apellidos_param,
        email = email_param,
        telefono = telefono_param,
        direccion = direccion_param
    WHERE id_propietario = id_propietario_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateUserPassword` (IN `id_usuario_param` INT, IN `password_param` VARCHAR(255))   BEGIN
    UPDATE usuarios
    SET password = password_param
    WHERE id_usuario = id_usuario_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateVideo` (IN `codigo_video_param` CHAR(10), IN `fecha_partido_param` DATE, IN `descripcion_param` VARCHAR(50), IN `hora_partido_param` TIME, IN `codigo_cancha_param` CHAR(10), IN `video_url_param` VARCHAR(255), IN `observacion_param` TEXT, IN `duracion_param` VARCHAR(50))   BEGIN
    UPDATE video SET
        fecha_partido = fecha_partido_param,
        descripcion = descripcion_param,
        hora_partido = hora_partido_param,
        codigo_cancha = codigo_cancha_param,
        video_url = video_url_param,
        observacion = observacion_param,
        duracion = duracion_param
    WHERE codigo_video = codigo_video_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateVideoBasic` (IN `codigo_video_param` CHAR(10), IN `fecha_partido_param` DATE, IN `hora_partido_param` TIME, IN `descripcion_param` VARCHAR(50), IN `codigo_cancha_param` CHAR(10), IN `video_url_param` VARCHAR(255), IN `duracion_param` VARCHAR(50), IN `observacion_param` TEXT)   BEGIN
    UPDATE video
    SET fecha_partido = fecha_partido_param,
        hora_partido = hora_partido_param,
        descripcion = descripcion_param,
        codigo_cancha = codigo_cancha_param,
        video_url = video_url_param,
        duracion = duracion_param,
        observacion = observacion_param
    WHERE codigo_video = codigo_video_param;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateVideoCompleto` (IN `p_codigo_video` CHAR(10), IN `p_fecha_partido` DATE, IN `p_descripcion` VARCHAR(50), IN `p_hora_partido` TIME, IN `p_codigo_cancha` CHAR(10), IN `p_video_url` VARCHAR(255), IN `p_observacion` TEXT, IN `p_duracion` VARCHAR(50))   BEGIN
    UPDATE video 
    SET fecha_partido = p_fecha_partido,
        descripcion = p_descripcion,
        hora_partido = p_hora_partido,
        codigo_cancha = p_codigo_cancha,
        video_url = p_video_url,
        observacion = p_observacion,
        duracion = p_duracion,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE codigo_video = p_codigo_video;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `UpdateVideoSinFoto` (IN `p_codigo_video` CHAR(10), IN `p_fecha_partido` DATE, IN `p_descripcion` VARCHAR(50), IN `p_hora_partido` TIME, IN `p_codigo_cancha` CHAR(10), IN `p_video_url` VARCHAR(255), IN `p_observacion` TEXT, IN `p_duracion` VARCHAR(50))   BEGIN
    UPDATE video 
    SET fecha_partido = p_fecha_partido,
        descripcion = p_descripcion,
        hora_partido = p_hora_partido,
        codigo_cancha = p_codigo_cancha,
        video_url = p_video_url,
        observacion = p_observacion,
        duracion = p_duracion,
        fecha_actualizacion = CURRENT_TIMESTAMP
    WHERE codigo_video = p_codigo_video;
END$$

CREATE DEFINER=`u462193081_pomplay`@`127.0.0.1` PROCEDURE `VerifyLocalOwner` (IN `p_id_local` INT, IN `p_id_propietario` INT)   BEGIN
    SELECT id_local, nombre_local 
    FROM locales 
    WHERE id_local = p_id_local 
    AND id_propietario = p_id_propietario
    AND estado = 1
    LIMIT 1;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id_auditoria` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `valores_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_anteriores`)),
  `valores_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_nuevos`)),
  `direccion_ip` varchar(45) DEFAULT NULL,
  `fecha_accion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `camara`
--

CREATE TABLE `camara` (
  `id_camara` int(11) NOT NULL,
  `codigo_cancha` char(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `go2rtc_stream` varchar(100) DEFAULT NULL,
  `posicion` tinyint(2) NOT NULL DEFAULT 1,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `grabando` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `camara`
--

INSERT INTO `camara` (`id_camara`, `codigo_cancha`, `nombre`, `created_at`, `go2rtc_stream`, `posicion`, `activa`, `grabando`) VALUES
(1, 'C02', 'Cámara 1', '2026-07-02 10:53:45', 'C02_cam1_hd', 1, 1, 0),
(2, 'C02', 'Cámara 2', '2026-07-02 10:53:45', 'C02_cam2_hd', 2, 1, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `camaras`
--

CREATE TABLE `camaras` (
  `id_camara` int(11) NOT NULL,
  `codigo_cancha` char(10) NOT NULL COMMENT 'FK a cancha',
  `nombre` varchar(50) NOT NULL DEFAULT 'Principal' COMMENT 'Nombre descriptivo: Principal, Panorámica, etc',
  `posicion` tinyint(2) NOT NULL DEFAULT 1 COMMENT 'Orden de visualización (1=primera, 2=segunda)',
  `url_stream` varchar(255) DEFAULT NULL COMMENT 'URL del stream en vivo (opcional)',
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cancha`
--

CREATE TABLE `cancha` (
  `id_cancha` int(11) NOT NULL,
  `codigo_cancha` char(10) NOT NULL,
  `id_local` int(11) DEFAULT NULL,
  `descripcion` varchar(70) NOT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `tipo_cancha` varchar(100) DEFAULT NULL,
  `ubicacion` varchar(150) DEFAULT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cancha`
--

INSERT INTO `cancha` (`id_cancha`, `codigo_cancha`, `id_local`, `descripcion`, `imagen_url`, `tipo_cancha`, `ubicacion`, `id_categoria`, `estado`, `fecha_creacion`) VALUES
(1, 'C01', 1, 'Principal', 'public/images/pomplay logo.png\r\n', 'Básquet', 'Zona Centro', 1, 1, '2026-05-01 21:02:38'),
(2, 'C02', 2, 'Principal', 'public/images/pomplay logo.png', 'Cancha de futbol', 'Zona Centro', 1, 1, '2026-05-01 21:02:38'),
(3, 'C01', 3, 'SUPERLIGA 7', NULL, 'Futbol', 'SUPERLIGA 7', NULL, 1, '2026-07-21 12:06:37'),
(4, 'C01', 4, 'SAN AGUSTIN', NULL, 'PARTIDO DE ENTRENAMIENTO', 'MEGO', NULL, 1, '2026-07-24 06:19:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(50) NOT NULL,
  `descripcion_categoria` text DEFAULT NULL,
  `imagen` varchar(150) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1 COMMENT '1=activo, 0=inactivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categoria`
--

INSERT INTO `categoria` (`id_categoria`, `nombre_categoria`, `descripcion_categoria`, `imagen`, `estado`) VALUES
(1, 'Fútbol', 'Deporte más popular del mundo', 'futbol.jpg', 1),
(2, 'Vóley', 'Deporte de equipo con red', 'voley.png', 1),
(3, 'Tenis', 'Deporte de raqueta individual o dobles', 'tenis.png', 1),
(4, 'Básquetbol', 'Deporte de equipo con canasta', 'basquet.png', 1),
(5, 'Padel', 'Deporte de paleta similar al tenis', 'padel.png', 1),
(6, 'Béisbol', 'Deporte de bate y pelota', 'beisbol.png', 1),
(7, 'Natación', 'Deporte acuático competitivo', 'natacion.png', 1),
(8, 'Atletismo', 'Deportes de pista y campo', 'atletismo.png', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `locales`
--

CREATE TABLE `locales` (
  `id_local` int(11) NOT NULL,
  `nombre_local` varchar(100) NOT NULL,
  `id_propietario` int(11) DEFAULT NULL,
  `es_privado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=videos de este local requieren PIN de acceso',
  `estado` tinyint(1) DEFAULT 1 COMMENT '1=activo, 0=inactivo (eliminado lógico)',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `locales`
--

INSERT INTO `locales` (`id_local`, `nombre_local`, `id_propietario`, `es_privado`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'IDP - Básquet', 2, 0, 1, '2026-05-01 23:39:39', '2026-07-07 12:04:30'),
(2, 'City Gol', 3, 0, 1, '2026-06-15 00:59:38', '2026-07-23 21:38:04'),
(3, 'LA PICHANGA - SUPERLIGA', 1, 0, 1, '2026-07-21 03:58:56', '2026-07-21 04:56:43'),
(4, 'MEGO - SA', 1, 0, 1, '2026-07-24 06:17:18', '2026-07-24 06:17:18'),
(5, 'MEGO - SA', 1, 0, 1, '2026-07-24 06:17:18', '2026-07-24 06:17:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `membresias`
--

CREATE TABLE `membresias` (
  `id_membresia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_local` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `tipo_membresia` varchar(50) DEFAULT 'BASICA' COMMENT 'BASICA, PREMIUM, etc.',
  `estado` tinyint(1) DEFAULT 1 COMMENT '1=activa, 0=expirada',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `membresias`
--

INSERT INTO `membresias` (`id_membresia`, `id_usuario`, `id_local`, `fecha_inicio`, `fecha_vencimiento`, `tipo_membresia`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 2, 1, '2026-05-30', '2026-07-30', 'BASICA', 1, '2026-05-02 10:09:32', '2026-06-29 23:45:34'),
(2, 3, 2, '2026-06-15', '2026-07-15', 'BASICA', 1, '2026-06-15 00:59:38', '2026-06-15 00:59:38'),
(3, 1, 3, '2026-07-20', '2026-08-20', 'BASICA', 1, '2026-07-21 03:58:56', '2026-07-21 04:56:43'),
(4, 2, 3, '2026-07-20', '2026-08-20', 'BASICA', 1, '2026-07-21 04:10:42', '2026-07-21 04:10:42'),
(5, 1, 4, '2026-07-24', '2026-08-24', 'BASICA', 1, '2026-07-24 06:17:18', '2026-07-24 06:17:18'),
(6, 1, 5, '2026-07-24', '2026-08-24', 'BASICA', 1, '2026-07-24 06:17:18', '2026-07-24 06:17:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id_permiso` int(11) NOT NULL,
  `nombre_permiso` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `modulo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precios_membresias`
--

CREATE TABLE `precios_membresias` (
  `id_precio` int(11) NOT NULL,
  `tipo_membresia` varchar(50) NOT NULL,
  `nombre_display` varchar(100) NOT NULL COMMENT 'Nombre para mostrar',
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duracion_meses` int(11) NOT NULL DEFAULT 1,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `precios_membresias`
--

INSERT INTO `precios_membresias` (`id_precio`, `tipo_membresia`, `nombre_display`, `precio`, `duracion_meses`, `descripcion`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'BASICA', 'Mensual', 399.00, 1, 'Kit 1', 1, '2026-05-03 23:21:52', '2026-07-09 07:55:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `propietarios`
--

CREATE TABLE `propietarios` (
  `id_propietario` int(11) NOT NULL,
  `nombres` varchar(255) NOT NULL,
  `apellidos` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `propietarios`
--

INSERT INTO `propietarios` (`id_propietario`, `nombres`, `apellidos`, `email`, `telefono`, `direccion`, `estado`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'ADMIN', 'POMPLAY', 'admin@admin.com ', '999999999', 'Av. LOTE A MNZ 1 ', 1, '2026-05-01 23:39:39', '2026-05-01 23:39:39'),
(2, 'Ana María', 'López Beltrán', 'analopez@gmail.com', '987654321', 'Jr. A  LT 2 ', 1, '2026-05-01 23:39:39', '2026-05-01 23:39:39'),
(3, 'Edwin', 'RS', 'rsedwin06@gmail.com', '+51953880108', 'M. Grau', 1, '2026-06-15 00:59:38', '2026-06-15 00:59:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`, `estado`, `fecha_creacion`) VALUES
(1, 'ADMIN', 1, '2026-05-01 21:02:38'),
(2, 'DUEÑO', 1, '2026-05-01 21:02:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permiso`
--

CREATE TABLE `rol_permiso` (
  `id_rol` int(11) NOT NULL,
  `id_permiso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `usuario` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_propietario` int(11) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ultimo_acceso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `usuario`, `password`, `id_rol`, `id_propietario`, `estado`, `fecha_creacion`, `fecha_actualizacion`, `ultimo_acceso`) VALUES
(1, 'admin', '$2y$10$I3y0pRlFUn0Nsl1APnDY9ucC7zwijoA8KaQ8cpoMOod3wY8c4TJnC', 1, 1, 1, '2026-05-01 22:07:46', '2026-07-24 06:43:02', '2026-07-24 06:43:02'),
(2, 'prueba', '$2y$10$I3y0pRlFUn0Nsl1APnDY9ucC7zwijoA8KaQ8cpoMOod3wY8c4TJnC', 2, 2, 1, '2026-05-01 22:07:47', '2026-07-23 00:14:32', '2026-07-23 00:14:32'),
(3, 'root', '$2y$10$fMY3ZsBc/SOwfZ/CMm6Vu.kw/LWQuQr7EFknMMV.EpUxgPCvkOIge', 2, 3, 1, '2026-06-15 00:59:38', '2026-07-22 21:13:27', '2026-07-22 21:13:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `video`
--

CREATE TABLE `video` (
  `codigo_video` char(10) NOT NULL,
  `id_local` int(11) DEFAULT NULL,
  `fecha_partido` date NOT NULL,
  `descripcion` varchar(50) NOT NULL,
  `hora_partido` time NOT NULL,
  `codigo_cancha` char(10) NOT NULL,
  `id_camara` int(11) DEFAULT NULL COMMENT 'FK a camaras - NULL si es video sin cámara específica',
  `video_url` varchar(255) NOT NULL,
  `observacion` text DEFAULT NULL,
  `fecha_registro` date DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duracion` varchar(50) NOT NULL,
  `estado` tinyint(1) DEFAULT 1,
  `es_descargable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `video`
--

INSERT INTO `video` (`codigo_video`, `id_local`, `fecha_partido`, `descripcion`, `hora_partido`, `codigo_cancha`, `id_camara`, `video_url`, `observacion`, `fecha_registro`, `fecha_actualizacion`, `duracion`, `estado`, `es_descargable`) VALUES
('V010', 2, '2026-07-06', '06/07/2026 20:00', '20:00:00', 'C02', 1, 'https://cctv.pomplay.com.pe/videos/2/C02/06-07-2026_20-00-00.mp4', NULL, '2026-07-07', '2026-07-21 05:26:09', '42:55', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `video_pins`
--

CREATE TABLE `video_pins` (
  `id` int(11) NOT NULL,
  `codigo_video` char(10) NOT NULL,
  `pin_hash` varchar(255) NOT NULL COMMENT 'PIN hasheado con bcrypt',
  `pin_longitud` tinyint(3) NOT NULL DEFAULT 6 COMMENT 'Longitud del PIN: 4 o 6 dígitos',
  `expira_en` datetime NOT NULL COMMENT 'Fecha/hora de expiración del PIN',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `usado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=PIN ya fue utilizado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id_auditoria`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `camara`
--
ALTER TABLE `camara`
  ADD PRIMARY KEY (`id_camara`),
  ADD KEY `codigo_cancha` (`codigo_cancha`);

--
-- Indices de la tabla `camaras`
--
ALTER TABLE `camaras`
  ADD PRIMARY KEY (`id_camara`),
  ADD KEY `idx_camaras_cancha` (`codigo_cancha`),
  ADD KEY `idx_camaras_cancha_estado` (`codigo_cancha`,`estado`);

--
-- Indices de la tabla `cancha`
--
ALTER TABLE `cancha`
  ADD PRIMARY KEY (`id_cancha`),
  ADD UNIQUE KEY `uq_local_codigo` (`id_local`,`codigo_cancha`),
  ADD KEY `idx_cancha_categoria` (`id_categoria`),
  ADD KEY `idx_cancha_local` (`id_local`),
  ADD KEY `idx_cancha_local_estado` (`id_local`,`estado`),
  ADD KEY `idx_cancha_codigo_estado` (`codigo_cancha`,`estado`);

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre_categoria` (`nombre_categoria`);

--
-- Indices de la tabla `locales`
--
ALTER TABLE `locales`
  ADD PRIMARY KEY (`id_local`),
  ADD KEY `id_propietario` (`id_propietario`),
  ADD KEY `idx_locales_estado` (`estado`);

--
-- Indices de la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD PRIMARY KEY (`id_membresia`),
  ADD UNIQUE KEY `usuario_local` (`id_usuario`,`id_local`),
  ADD KEY `id_local` (`id_local`),
  ADD KEY `idx_membresias_usuario` (`id_usuario`),
  ADD KEY `idx_membresias_estado` (`estado`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD PRIMARY KEY (`id_permiso`),
  ADD UNIQUE KEY `nombre_permiso` (`nombre_permiso`);

--
-- Indices de la tabla `precios_membresias`
--
ALTER TABLE `precios_membresias`
  ADD PRIMARY KEY (`id_precio`),
  ADD UNIQUE KEY `tipo_membresia` (`tipo_membresia`);

--
-- Indices de la tabla `propietarios`
--
ALTER TABLE `propietarios`
  ADD PRIMARY KEY (`id_propietario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD PRIMARY KEY (`id_rol`,`id_permiso`),
  ADD KEY `id_permiso` (`id_permiso`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `id_propietario` (`id_propietario`),
  ADD KEY `idx_usuarios_rol` (`id_rol`),
  ADD KEY `idx_usuarios_estado` (`estado`);

--
-- Indices de la tabla `video`
--
ALTER TABLE `video`
  ADD PRIMARY KEY (`codigo_video`),
  ADD KEY `codigo_cancha` (`codigo_cancha`),
  ADD KEY `idx_video_local` (`id_local`),
  ADD KEY `idx_video_estado_fecha` (`estado`,`fecha_partido`),
  ADD KEY `idx_video_local_cancha` (`id_local`,`codigo_cancha`),
  ADD KEY `idx_video_cancha_fecha` (`codigo_cancha`,`fecha_partido`),
  ADD KEY `idx_video_completo` (`estado`,`id_local`,`codigo_cancha`,`fecha_partido`),
  ADD KEY `video_camara_fk` (`id_camara`);

--
-- Indices de la tabla `video_pins`
--
ALTER TABLE `video_pins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_video_pins_video` (`codigo_video`),
  ADD KEY `idx_video_pins_expira` (`expira_en`),
  ADD KEY `idx_video_pins_video_expira` (`codigo_video`,`expira_en`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id_auditoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `camara`
--
ALTER TABLE `camara`
  MODIFY `id_camara` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `camaras`
--
ALTER TABLE `camaras`
  MODIFY `id_camara` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cancha`
--
ALTER TABLE `cancha`
  MODIFY `id_cancha` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `locales`
--
ALTER TABLE `locales`
  MODIFY `id_local` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `membresias`
--
ALTER TABLE `membresias`
  MODIFY `id_membresia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `permisos`
--
ALTER TABLE `permisos`
  MODIFY `id_permiso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `precios_membresias`
--
ALTER TABLE `precios_membresias`
  MODIFY `id_precio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `propietarios`
--
ALTER TABLE `propietarios`
  MODIFY `id_propietario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `video_pins`
--
ALTER TABLE `video_pins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD CONSTRAINT `auditoria_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `camara`
--
ALTER TABLE `camara`
  ADD CONSTRAINT `camara_ibfk_1` FOREIGN KEY (`codigo_cancha`) REFERENCES `cancha` (`codigo_cancha`) ON DELETE CASCADE;

--
-- Filtros para la tabla `camaras`
--
ALTER TABLE `camaras`
  ADD CONSTRAINT `camaras_cancha_fk` FOREIGN KEY (`codigo_cancha`) REFERENCES `cancha` (`codigo_cancha`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cancha`
--
ALTER TABLE `cancha`
  ADD CONSTRAINT `cancha_categoria_fk` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `cancha_local_fk` FOREIGN KEY (`id_local`) REFERENCES `locales` (`id_local`) ON DELETE CASCADE;

--
-- Filtros para la tabla `locales`
--
ALTER TABLE `locales`
  ADD CONSTRAINT `locales_ibfk_1` FOREIGN KEY (`id_propietario`) REFERENCES `propietarios` (`id_propietario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD CONSTRAINT `membresias_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `membresias_ibfk_2` FOREIGN KEY (`id_local`) REFERENCES `locales` (`id_local`) ON DELETE CASCADE;

--
-- Filtros para la tabla `rol_permiso`
--
ALTER TABLE `rol_permiso`
  ADD CONSTRAINT `rol_permiso_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE,
  ADD CONSTRAINT `rol_permiso_ibfk_2` FOREIGN KEY (`id_permiso`) REFERENCES `permisos` (`id_permiso`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_propietario`) REFERENCES `propietarios` (`id_propietario`);

--
-- Filtros para la tabla `video`
--
ALTER TABLE `video`
  ADD CONSTRAINT `video_camara_fk` FOREIGN KEY (`id_camara`) REFERENCES `camara` (`id_camara`) ON DELETE SET NULL,
  ADD CONSTRAINT `video_ibfk_1` FOREIGN KEY (`codigo_cancha`) REFERENCES `cancha` (`codigo_cancha`),
  ADD CONSTRAINT `video_local_fk` FOREIGN KEY (`id_local`) REFERENCES `locales` (`id_local`) ON DELETE SET NULL;

--
-- Filtros para la tabla `video_pins`
--
ALTER TABLE `video_pins`
  ADD CONSTRAINT `video_pins_video_fk` FOREIGN KEY (`codigo_video`) REFERENCES `video` (`codigo_video`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

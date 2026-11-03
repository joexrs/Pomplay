# Auditoría técnica, arquitectura y seguridad — PomPlay

**Fecha:** 2026-11-03
**Alcance:** árbol de trabajo disponible en `C:\xampp\htdocs\PomPlay`.
**Tipo de revisión:** inspección estática del repositorio y revisión de seguridad delegada. No se modificó código de la aplicación.

## Resumen ejecutivo

La aplicación es un sistema PHP 8.0 híbrido: el flujo público usa un front controller y componentes organizados como controladores, repositorios, servicios y vistas; paralelamente, hay páginas y scripts PHP de acceso directo en varios directorios. La persistencia usa MySQL/PDO y existe integración con un VPS para almacenamiento y procesamiento de videos.

La revisión de seguridad reportó dos hallazgos sustentados:

1. **Alto:** un dump SQL con datos personales, hashes de contraseñas y URLs de videos reside dentro del árbol del sitio; las reglas Apache revisadas no bloquean archivos `.sql` ni el directorio `database`.
2. **Medio:** un script de limpieza accesible desde web utiliza un secreto hardcodeado como autorización y puede iniciar borrados de videos y registros.

Se recomienda tratar el dump como información expuesta potencialmente, retirarlo de cualquier raíz pública y evaluar la rotación/reset de credenciales afectadas. El script de limpieza debe deshabilitarse desde web o convertirse en una tarea CLI con secretos fuera del código, rotación del secreto y controles de autorización y auditoría.

La revisión no certifica el estado de producción. Varios puntos de la lista requieren inspección del entorno desplegado o evidencia específica que no estuvo disponible; se marcan explícitamente como no verificados.

## Arquitectura y estructura

### Stack y organización

- **Runtime:** PHP 8.0.
- **Dependencias:** Composer; `firebase/php-jwt` v6.11.1 según `composer.lock`.
- **Autoload:** PSR-4 `App\` → `app/`.
- **Servidor y datos:** PHP con MySQL/PDO; servicio remoto VPS para almacenamiento y operaciones con videos.
- **Cliente:** vistas PHP y recursos JavaScript/CSS.

### Componentes y patrones observables

- **Front controller / routing:** `public/index.php` resuelve rutas públicas y API, y deriva parte del tráfico a páginas administrativas o del propietario.
- **MVC para el flujo público:** controladores en `app/Controllers/`, vistas en `resources/views/` y acceso a datos en `app/Repositories/`.
- **Composición de dependencias:** `bootstrap/app.php` construye repositorios/controladores y los conecta manualmente.
- **Repository:** repositorios encapsulan consultas y acceso a datos.
- **Servicio de integración:** `app/Services/VpsStorageService.php` centraliza llamadas al almacenamiento remoto.
- **Núcleo compartido:** `app/Core/` contiene infraestructura como conexión de base de datos, vistas, autenticación, caché y rate limiting.
- **Convivencia con PHP procedural/legacy:** existen scripts y páginas PHP directos bajo `admin/`, `owner/`, `vps/`, `public/` y la raíz. No todas las rutas pasan necesariamente por el mismo ciclo de middleware.

### Dominios funcionales visibles

- Autenticación y roles.
- Administración de locales, canchas y membresías.
- Videos, estadísticas, clips y reproducción.
- APIs públicas relacionadas con canchas, locales, horarios y videos.
- Integración con cámaras y almacenamiento/procesamiento remoto.

### Observación técnica

La coexistencia del front controller con endpoints PHP directos puede producir diferencias en controles transversales (autenticación, autorización, validación, cabeceras, manejo de errores y registro). Como siguiente paso técnico conviene inventariar todas las entradas HTTP y aplicar controles consistentes, sin asumir que una protección del front controller cubre los scripts directos.

## Hallazgos de seguridad

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| 1 | 🟠 HIGH | `database/u462193081_pomplay.sql` | 4778-4780, 5745-5748, 5778-5781 | Dump SQL dentro del árbol del sitio con datos personales, hashes de contraseñas y URLs de videos. Las reglas Apache revisadas no bloquean `.sql` ni el directorio `database`; si el árbol se publica como raíz web, el archivo podría descargarse. | 9/10 |
| 2 | 🟡 MEDIUM | `cleanup_videos.php` | 23-28, 67-70, 116-150 | Secreto hardcodeado como autorización para un script web invocable por GET que puede solicitar borrados al VPS y eliminar registros de video. La divulgación del código revela el secreto. | 8/10 |

### Mitigaciones recomendadas

**Hallazgo 1 — dump SQL**

- Retirar los dumps de la raíz pública y del repositorio; mantener respaldos únicamente en almacenamiento restringido y con acceso auditado.
- Añadir una regla de denegación explícita para dumps y directorios de respaldo como defensa adicional, sin usarla como sustituto de retirar el archivo del web root.
- Tratar los datos del dump como potencialmente expuestos: evaluar el restablecimiento de contraseñas afectadas, la rotación de credenciales activas que aparezcan en él y la revocación de URLs/tokens sensibles.
- Confirmar en el servidor desplegado que la ruta no sea descargable y revisar logs de acceso históricos disponibles.

**Hallazgo 2 — script de limpieza**

- Deshabilitar el acceso HTTP al script o mover la operación a una tarea CLI restringida.
- Eliminar el secreto del código, almacenarlo en configuración protegida y rotarlo.
- No realizar operaciones destructivas por GET; exigir autenticación/autorización y protección CSRF apropiada si se mantiene un endpoint.
- Añadir validación de alcance, registro auditable, límites de operación y mecanismos de confirmación/recuperación antes de ejecutar borrados.

**Estado de remediación:** no se aplicaron cambios al código ni se rotaron credenciales durante esta auditoría.

## Checklist de seguridad y QA

Estados:

- **Hallazgo:** el revisor reportó evidencia concreta.
- **Parcial:** hay observación relacionada, pero no evidencia suficiente para certificar el control completo.
- **No verificado:** el resultado no permite declarar cumplimiento o incumplimiento.
- **Pendiente:** acción recomendada no ejecutada.

| Checklist | Estado | Resultado / evidencia disponible |
|---|---|---|
| 1.1 Almacenamiento de contraseñas con hash seguro | Parcial | El revisor observó hashes en el dump; no confirmó algoritmo, parámetros ni migración de hashes antiguos. |
| 1.2 Robustez general de autenticación | Parcial | La aplicación contiene flujos de autenticación/sesión; no se reportó una validación integral de todos los flujos y roles. |
| 1.3 Claves API fuera del cliente | No verificado | No hay resultado concluyente de búsqueda exhaustiva en recursos cliente, artefactos publicados e historial. |
| 1.4 Caducidad de enlaces de recuperación | No verificado | No se confirmó el flujo de recuperación ni la expiración de sus tokens/enlaces. |
| 1.5 Cookies Secure, HttpOnly y SameSite | No verificado | No se inspeccionaron respuestas HTTP del despliegue ni se reportó una conclusión completa sobre todas las cookies. |
| 1.6 Revocación/sustitución de credenciales expuestas | Pendiente | Recomendado como respuesta a los hallazgos; no se revocaron ni rotaron credenciales. |
| 2.1 Consultas parametrizadas | No verificado | No se confirmó exhaustivamente cada consulta, procedimiento almacenado y punto de acceso a datos. |
| 2.2 Seguridad de base de datos | No verificado | No se evaluaron permisos, firewall, backups operativos ni configuración de producción. El dump expuesto sí es un hallazgo. |
| 3.1 Forzado estricto de HTTPS | Parcial | Se reportaron directivas de HTTPS/HSTS comentadas; no se pudo determinar el forzado TLS en proxy o servidor productivo. |
| 3.2 CORS limitado a orígenes autorizados | No verificado | No hay inventario completo ni prueba de respuestas de todos los endpoints y orígenes. |
| 3.3 Cabeceras de seguridad | Parcial | Se reportaron HSTS y protección contra framing comentados; CSP y cabeceras efectivas del despliegue no quedaron verificadas. |
| 4.1 Validación de datos en servidor | No verificado | No se obtuvo una conclusión global para formularios y APIs. |
| 4.2 Precios y pagos calculados en servidor | No verificado | No se confirmó el flujo integral de cálculo y procesamiento de pagos. |
| 4.3 Validación y límites de archivos subidos | No verificado | No se confirmó integralmente MIME, tamaño máximo y límites en todos los flujos de carga. |
| 4.4 Restricción de rutas administrativas | No verificado | No se obtuvo conclusión completa sobre cada endpoint directo y sus controles de rol/propiedad. |
| 5.1 Prevención de XSS | No verificado | No hay resultado exhaustivo de escape contextual, saneamiento y contenido almacenado. |
| 5.2 Prevención de CSRF | No verificado | No se confirmó el uso general de tokens ni excepciones por endpoint. |
| 5.3 Rate limiting | No verificado | No se confirmó cobertura efectiva en login/APIs ni su comportamiento bajo concurrencia o múltiples nodos. |
| 6.1 Variables de entorno | No verificado | No se certificó que secretos y valores de despliegue sean apropiados y no accesibles desde web. |
| 6.2 Debug desactivado en producción | No verificado | El modo efectivo depende del entorno y no se probó en producción. |
| 6.3 Logging y monitoreo | No verificado | No se evaluaron alertas, retención, integridad ni vigilancia operativa. |
| 6.4 Dependencias vulnerables y sin uso | Parcial | El lockfile registra `firebase/php-jwt` v6.11.1; no se ejecutó escaneo de advisories ni se confirmó qué paquetes están sin uso. |
| 6.5 Fugas, archivos expuestos e historial Git | Hallazgo parcial | Dump SQL presente y reglas Apache sin bloqueo identificado. La búsqueda de secretos en todo el historial Git no quedó confirmada. |
| 7.1 Revisión profunda del sistema | Parcial | Revisión estática con dos hallazgos sustentados; no equivale a pentest ni cubre los puntos no verificados. |
| 7.2 Pruebas activas de protecciones | Parcial | La revisión reportó lint de sintaxis PHP satisfactorio para 109 archivos y no detectó suite automatizada. No se verificaron protecciones dinámicamente contra un despliegue. |
| 7.3 Informe de hallazgos y arreglos | Informe entregado | Este documento detalla hallazgos y mitigaciones; no se aplicaron arreglos durante la revisión. |

## Validación y limitaciones

- La revisión reportó que la sintaxis PHP pasó en 109 archivos.
- No se detectó una suite automatizada de pruebas.
- No se ejecutó un escaneo de advisories de dependencias.
- No se probó el sitio desplegado ni se verificaron configuración real de HTTPS, CORS, cookies, cabeceras, base de datos o VPS.
- El árbol de trabajo ya contenía cambios locales al inicio de la revisión. Esos cambios se preservaron y no se consideran modificaciones de esta auditoría.

## Prioridad de siguientes pasos

1. Retirar o aislar inmediatamente el dump SQL y comprobar que no esté servido públicamente.
2. Deshabilitar el script web de limpieza; retirar y rotar el secreto asociado.
3. Evaluar el impacto sobre cuentas, datos personales y URLs de video presentes en el dump.
4. Completar los puntos marcados **No verificado** con revisión de cada entrada HTTP y pruebas autorizadas en un entorno de staging.
5. Añadir pruebas de regresión para autenticación, autorización por rol/propiedad, cargas, APIs y controles de solicitudes; ejecutar escaneo de dependencias y revisión del historial de secretos.

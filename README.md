# Sistema de solicitudes y seguimiento de prácticas

Aplicación web académica para registrar empresas y estudiantes, publicar oportunidades de práctica, recibir postulaciones y dar seguimiento a sus fases. Usa PHP 8, MySQL/MariaDB, HTML semántico, CSS responsivo y JavaScript pequeño para mostrar u ocultar contraseñas.

## Requisitos

- PHP 8.0 o posterior con `pdo_mysql` y `mbstring` habilitados.
- MySQL 8 o MariaDB con soporte para InnoDB, vistas, procedimientos almacenados, triggers y JSON.
- Apache (XAMPP) o el servidor integrado de PHP.

## Instalación en XAMPP

1. Copia la carpeta del proyecto a `C:\xampp\htdocs\ProyectoFinalDesarrolloWeb`.
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. En phpMyAdmin, importa primero `database/database.sql` y después `database/demo.sql`. Cada archivo se importa una sola vez sobre una base nueva. Si ya tenías el esquema anterior instalado, ejecuta una vez `database/migration_usuario_login.sql` antes de iniciar sesión; conserva los usuarios existentes y deriva su nombre de usuario del correo.
4. Si tu MySQL no usa `root` sin contraseña, configura las variables indicadas en [api/core/.env.example](api/core/.env.example) en el entorno de Apache o en `api/core/.env` (este último archivo no se versiona).
5. Abre `http://localhost/ProyectoFinalDesarrolloWeb/index.php`. La primera pantalla es el inicio de sesión; después de autenticar se abre el catálogo según el rol.

La conexión se lee de `api/core/.env`: `DATABASE_URL` (host), `DATABASE_NAME`, `DATABASE_USER` y `DATABASE_PSSW` (contraseña; también acepta `DATABASE_PASSWORD`). Si MySQL muestra `Access denied`, corrige esos valores para que coincidan con un usuario MySQL autorizado para `sistema_practicas`; la página de inicio de sesión ya puede abrirse aunque la base de datos aún no conecte. Para otra ruta de instalación, abre la URL de la carpeta que copiaste; los enlaces se ajustan automáticamente.

También puedes ejecutarla desde la carpeta del proyecto con `php -S 127.0.0.1:8000` y visitar `http://127.0.0.1:8000`.

## Cuentas de demostración

| Perfil | Usuario | Contraseña |
| --- | --- | --- |
| Superadministrador | `superadmin` | `Admin2026!` |
| Estudiante | `andrea` | `Estudiante2026!` |
| Empresa de demostración | `innovatech` | `Empresa2026!` |

Las contraseñas de demostración se guardan en SQL como hashes generados con `password_hash()`. Cambia o elimina las cuentas de ejemplo antes de publicar la aplicación.

## Recorrido funcional

- **Estudiante:** crea una cuenta con usuario, correo, carnet, carrera y universidad; explora y filtra oportunidades publicadas; consulta el detalle; envía una postulación; revisa el estado, las observaciones y el historial; retira una solicitud recibida o en revisión.
- **Empresa:** completa el perfil, espera aprobación, registra oportunidades y consulta las postulaciones de sus propias publicaciones.
- **Administrador:** aprueba empresas, revisa las oportunidades propuestas, las publica o rechaza, cierra convocatorias y consulta el seguimiento general.
- **Superadministrador:** además de las funciones administrativas, asigna perfiles de administrador desde `roles.php`. El registro público no permite elegir roles.
- El usuario de demostración ya tiene una postulación en “Practicante de desarrollo web”; puede probar una postulación nueva en “Analista de datos en formación”.

## Métodos HTTP y seguridad

Los endpoints reciben `GET`, `POST`, `PUT` y `DELETE`. Los formularios HTML envían `POST` con el campo `_method` para solicitar `PUT` o `DELETE`, ya que HTML solo implementa formularios GET y POST. Los endpoints de lectura devuelven JSON; las acciones de formulario validan datos en el servidor y redirigen a la interfaz.

- `GET api/auth.php`: consulta si existe una sesión.
- `POST api/auth.php`: inicia sesión o registra estudiante (`action=register`).
- `DELETE api/auth.php`: cierra sesión.
- `GET api/oportunidades.php`: consulta y filtra oportunidades; admite `q`, `modalidad` e `id`.
- `POST api/oportunidades.php`: crea una propuesta de oportunidad como empresa autenticada.
- `PUT api/oportunidades.php`: permite a la empresa reenviar borradores/rechazos a revisión; coordinación aprueba, rechaza o cierra.
- `DELETE api/oportunidades.php`: baja lógica administrativa para preservar relaciones e historial.
- `GET api/postulaciones.php`: obtiene las solicitudes propias del estudiante o todas para administración.
- `POST`, `PUT`, `DELETE api/postulaciones.php`: crea postulaciones, actualiza su estado desde administración y registra retiros.
- `GET api/empresas.php`: consulta perfiles para revisión administrativa.
- `PUT api/empresas.php`: aprueba o rechaza una solicitud de empresa.

Las consultas usan PDO con parámetros y preparaciones nativas; los nombres de roles se resuelven con consultas `SELECT`, no se aceptan identificadores de rol desde el navegador. La contraseña se verifica con `password_verify()`. Los formularios usan tokens CSRF, cookies `HttpOnly` y `SameSite`, se regenera el identificador de sesión al autenticar y cada vista valida el rol en el servidor. El icono de ojo muestra u oculta la contraseña sin enviarla a otro servicio. La restricción única de estudiante y oportunidad impide duplicados incluso si dos solicitudes concurrentes intentan entrar. Los triggers registran inserciones y actualizaciones de las tablas operativas y de control como JSON con IP de origen; no registran hashes de contraseña ni tokens de sesión. La bitácora no se audita a sí misma para evitar escrituras recursivas. Coordinación ve las últimas 100 entradas dentro del panel.

Las relaciones propagan cambios de claves con `ON UPDATE CASCADE`. Las bajas físicas quedan restringidas para conservar postulaciones y auditoría; la aplicación usa bajas lógicas desde PHP para no borrar evidencia del proceso.

Los CV se almacenan fuera del directorio público, solo se admiten Word (.doc/.docx) o PDF de hasta 5 MB y la descarga verifica que la cuenta estudiante, la empresa propietaria o coordinación tenga permiso. Al cerrar una convocatoria, PHP prepara un resumen con nombre, correo y teléfono, adjunta los CV y lo envía a `empresas.correo_contacto` usando `mail()`; configura `MAIL_FROM` y el transporte del servidor en XAMPP para la entrega más sencilla. Si la entrega falla, el estado queda en `cola_notificaciones` como fallido.

## Video de demostración

El enunciado original pide un video del funcionamiento completo. Este repositorio incluye [docs/guion-video.md](docs/guion-video.md), una guía de grabación; todavía debes grabar la pantalla en tu equipo. No requiere mostrar una cuenta de correo real: puedes mostrar el panel y el correo de prueba si configuraste el transporte de PHP.

## Estructura

- `index.php`: catálogo, filtros, detalle, registro e inicio de sesión.
- `admin.php`: CRUD de oportunidades y panel de postulaciones.
- `seguimiento.php`: vista del estudiante e historial de estados.
- `api/`: acciones PHP, conexión PDO, sesión y validaciones compartidas.
- `database/database.sql`: esquema, relaciones, permisos, vistas, procedimientos y triggers.
- `database/demo.sql`: cuentas y datos suficientes para recorrer la aplicación.
- `css/styles.css`: interfaz adaptable a móvil y escritorio.

# Sistema de solicitudes y seguimiento de prácticas profesionales

Aplicación en español para coordinar prácticas profesionales entre estudiantes, empresas y coordinación académica. Incluye API REST en PHP, interfaz HTML sencilla y JavaScript sin frameworks y esquema MySQL/MariaDB.

## Requisitos

- PHP 8.1 o posterior con `pdo_mysql`, `fileinfo` y `mbstring` habilitados.
- MySQL 8.0 o MariaDB 10.5 o posterior con InnoDB, columnas generadas, vistas, JSON y triggers.
- Apache 2.4 con `mod_rewrite` y `AllowOverride All` para las rutas amigables.
- HTTPS en producción para que la cookie de sesión lleve el atributo `Secure`.

## Instalación en XAMPP

1. Inicia Apache y MySQL desde el panel de XAMPP.
2. Ejecuta `Ejecutar primero.exe` y acepta el aviso de permisos de administrador. El programa configura `SistemaPracticas` en Windows y Apache, reinicia Apache y copia la URL al portapapeles. Si Apache no está activo, inícialo y vuelve a ejecutar el `.exe`.
3. En phpMyAdmin importa `basededatos.sql` en una base limpia. El ejecutable configura el dominio, pero no instala ni modifica la base de datos.
4. Pega la URL copiada en el navegador. Si Apache usa un puerto distinto de 80, la URL incluirá ese puerto. El alias `SistemaPracticas` queda configurado en la PC donde se ejecutó el programa; repite el paso 2 en cada PC que aloje el sitio.

La configuración local usa `config/.env` si existe. En una descarga nueva, la aplicación carga `config/.env.example` como plantilla predeterminada. Edita una copia local de esa plantilla para cambiar credenciales o rutas; `.env` no se publica en GitHub. No uses las credenciales de demostración en producción. El usuario SQL `practicas_app` tiene permisos mínimos y no tiene `DELETE` ni DDL.

`.gitignore` excluye configuraciones personales, claves privadas, cookies, sesiones, registros, respaldos y archivos subidos por usuarios. La carpeta `config/` está bloqueada por `.htaccess`. Los archivos PHP de seguridad y las reglas `.htaccess` se mantienen en Git porque forman parte del funcionamiento del sistema.

### Instalación de la base

`database/basededatos.sql` es la fuente canónica de instalación. En la raíz también está `basededatos.sql`, una copia visible para importarla fácilmente, generada con `tools/build-configurador.ps1`. Incluye esquema, catálogos, triggers, vistas, datos de demostración, los cuatro usuarios principales y los permisos de la cuenta de aplicación. Importa el archivo raíz en una base vacía; no elimina tablas existentes.

Los códigos de cuenta, usuario y carné usan índices únicos sobre columnas generadas que devuelven `NULL` al marcar una fila como eliminada. Así se puede volver a registrar una dirección ya usada por una cuenta eliminada, mientras los registros activos siguen siendo únicos. Restaurar puede producir conflicto si el valor ya fue tomado por otra cuenta activa; en ese caso la operación se cancela con HTTP 409.

En el registro público, las cuentas nuevas se crean con rol de estudiante y las solicitudes de empresa quedan pendientes. Las empresas pueden iniciar sesión para consultar el estado de revisión; sus funciones se habilitan al aprobarlas. Admin y superadmin pueden revisar empresas.

## Cuentas de demostración

Las cuatro cuentas principales usan la contraseña `0123456789`:

| Perfil | Usuario | Contraseña |
|---|---|---|
| Superadministrador | `superadmin` | `0123456789` |
| Administrador | `admin` | `0123456789` |
| Empresa aprobada | `empresa` | `0123456789` |
| Estudiante | `estudiante` | `0123456789` |

El SQL conserva cuentas adicionales de demostración que se usan en los datos de ejemplo; las cuatro cuentas de la tabla son las indicadas para iniciar sesión. Cambia las contraseñas antes de publicar. Las rutas de CV son relativas y los archivos se sirven únicamente por endpoints que verifican permisos.
La demo incluye:

- Un superadministrador, dos administradores, tres empresas (aprobada, pendiente y rechazada) y seis estudiantes.
- Una práctica en cada estado del catálogo, una práctica cerrada y otra con una postulación aceptada que completa su cupo.
- Postulaciones en los cinco estados (`pendiente`, `aceptada`, `rechazada`, `retirada` y `cancelada`), con historial inicial.
- Seis archivos PDF de muestra bajo `uploads/demo`, para copiar al almacenamiento privado antes de probar descargas o postulaciones.

## Cierre automático

El cierre también se verifica al consultar oportunidades, prácticas propias y administrativas, postulaciones, dashboards y reportes, y antes de procesar cambios que puedan afectar postulaciones. El proceso es idempotente: solo toma prácticas publicadas que ya vencieron o alcanzaron el cupo, bloquea cada práctica y resuelve el cierre y el rechazo de pendientes dentro de una transacción.

### Programador de tareas de Windows

Usa las rutas reales de tu equipo. Si XAMPP o el proyecto están en otra carpeta, reemplaza las rutas de ejemplo por sus ubicaciones.

1. Abre **Programador de tareas** y elige **Crear tarea básica**.
2. Selecciona una repetición periódica (por ejemplo, cada 5 minutos).
3. Como acción, selecciona **Iniciar un programa** y usa `C:\xampp\php\php.exe` como programa.
4. En **Agregar argumentos**, coloca la ruta absoluta al script:

```powershell
C:\xampp\htdocs\ProyectoFinalDesarrolloWeb\cron\cerrar_practicas.php
```

5. En **Iniciar en**, coloca `C:\xampp\htdocs\ProyectoFinalDesarrolloWeb` y guarda la tarea con una cuenta que tenga acceso al PHP y al archivo privado de configuración de la BD.

El script solo permite ejecución por CLI. Registra la cantidad procesada y cualquier error en bitácora y en el log de PHP. Cada cierre cambia la práctica a `cerrada`, rechaza todas sus postulaciones pendientes con el comentario configurado y agrega el historial correspondiente. Si un retiro libera un cupo de una práctica cerrada automáticamente por cupo y su fecha sigue vigente, la práctica vuelve a publicarse; un cierre manual o por vencimiento se mantiene cerrado.

### Cron de Linux

Configura el cron con el usuario del servicio PHP y el entorno que contiene las variables privadas de conexión. Por ejemplo, cada cinco minutos:

```cron
*/5 * * * * /usr/bin/php /ruta/ProyectoFinalDesarrolloWeb/cron/cerrar_practicas.php >> /var/log/practicas-cierre.log 2>&1
```

## Pruebas por consola (curl)

Los ejemplos usan PowerShell y el host instalado en `/ProyectoFinalDesarrolloWeb`. La sesión va en `sesion.txt`; el token CSRF se copia de la respuesta JSON de login y se envía en cada mutación.

```powershell
# Obtener token inicial para formularios públicos
curl.exe -c sesion.txt -b sesion.txt http://SistemaPracticas/public/api/csrf

# Copia csrf_token de la primera respuesta en TOKEN_INICIAL.
curl.exe -c sesion.txt -b sesion.txt -H "Content-Type: application/json" -H "X-CSRF-Token: TOKEN_INICIAL" `
  -d '{"identificador":"bruno","contrasena":"ClaveDemo2026!"}' `
  http://SistemaPracticas/public/api/auth/login

# Consultar el catálogo público
curl.exe -b sesion.txt http://SistemaPracticas/public/api/practicas

# Actualizar el perfil de Bruno con PUT
curl.exe -X PUT -b sesion.txt -H "Content-Type: application/json" -H "X-CSRF-Token: COPIAR_TOKEN" `
  -d '{"correo":"bruno@practicas.local","nombres":"Bruno","apellidos":"Pérez","telefono":"55550018","carnet":"20260002","id_carrera":1,"universidad":"Universidad de Guatemala","semestre":"Quinto"}' `
  http://SistemaPracticas/public/api/perfil

# Crear una postulación; Bruno aún no está postulado a la práctica 8. Usa el csrf_token del login.
curl.exe -b sesion.txt -H "Content-Type: application/json" -H "X-CSRF-Token: COPIAR_TOKEN" `
  -d '{"id_practica":8,"carta_presentacion":"Me interesa esta oportunidad."}' `
  http://SistemaPracticas/public/api/postulaciones

# Cerrar sesión
curl.exe -X DELETE -b sesion.txt -H "X-CSRF-Token: COPIAR_TOKEN" `
  http://SistemaPracticas/public/api/auth/logout
```

Para `PUT`, usa `curl.exe -X PUT`, `Content-Type: application/json`, el encabezado CSRF y un cuerpo JSON. `DELETE` en esta API es baja lógica; no elimina filas físicamente. En cada caso la respuesta tiene la forma `{ "ok": true|false, "mensaje": "...", "datos": ..., "errores": {} }` y usa códigos HTTP apropiados. Al fallar la conexión o una consulta, se registra el detalle en el log PHP y la API responde un error genérico.

## Seguridad y bitácora

- Las consultas viven en repositorios de `includes/db`; los parámetros se preparan con PDO nativo (`ATTR_EMULATE_PREPARES=false`).
- `requerirRol` obtiene en cada solicitud protegida el usuario, el estado vigente y el rol desde la sesión persistida y MySQL. Los permisos se consultan en `permisos` y `rol_permiso`; se valida además el dueño de cada práctica/postulación.
- Los formularios protegidos usan cookie `HttpOnly`, `SameSite=Strict`, regeneración de sesión y CSRF mediante `X-CSRF-Token`. El bloqueo, timeout, política de contraseña y tamaño/formato de CV se leen de `configuracion`.
- `database/basededatos.sql` crea triggers de inserción y actualización, detecta eliminación/restauración lógica y protege las tablas contra `DELETE` físico. `bitacora` se protege contra UPDATE/DELETE. Los hashes de contraseña y tokens de sesión no se incluyen en los JSON auditados.
- PHP agrega eventos de login, bloqueo, acceso denegado, cierre automático, descarga y cambio de contraseña. El contexto de usuario e IP se establece en la conexión PDO.
- Para añadir una tabla: agrega sus columnas de autoría y baja lógica, claves/índices, triggers equivalentes en `database/basededatos.sql` (AFTER INSERT, AFTER UPDATE y BEFORE DELETE), vista de lectura si corresponde, repositorio y permisos. Después actualiza `database/basededatos.sql` y ejecuta `tools/build-configurador.ps1` para regenerar los archivos visibles de la raíz.

## Estructura principal

La guía para un programador junior está en [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md).
Las comprobaciones y cómo ejecutarlas están en [tests/README.md](tests/README.md).


- `index.php`: página de inicio principal en la raíz del proyecto.
- `public/`: recursos del cliente y el puente hacia la API; no contiene otro inicio.
- `API/index.php`: recibe la petición y selecciona la operación REST mediante `ControladorApi`. `API/controladores/` separa acceso, prácticas, postulaciones, perfiles y administración; `public/api/` es el puente recomendado.
- `config/`: bootstrap y plantilla de variables de entorno; `.env` queda local y `.env.example` sirve como configuración predeterminada de desarrollo.
- `includes/db/`: repositorios PDO y conexión; `includes/Seguridad/` e `includes/Servicios/` agrupan componentes reutilizables, e `includes/Http.php` reúne peticiones, respuestas y compatibilidad de rutas.
- `pages/`: esqueletos PHP de administración, empresa, perfil, postulaciones y consultas. `index.php` es la entrada pública principal y carga los datos desde la API.
- `Ejecutar primero.exe`: configurador de Windows, visible junto a este README.
- `basededatos.sql`: copia de importación rápida, regenerada desde `database/basededatos.sql`.
- `database/basededatos.sql`: fuente canónica de la instalación completa.
- `tools/`: código fuente, manifiesto, guía y script que genera el `.exe` y la copia SQL en la raíz.
- `public/assets/css/app.css`: estilos compartidos, ordenados en reglas legibles.
- `public/assets/js/app.js`: entrada de las pantallas. Cada módulo tiene su archivo (`perfil.js`, `practicas.js`, `postulaciones.js`, etc.) y comparte las funciones de `comun.js` y `ui.js`.
- Apache conserva los enlaces de páginas PHP antiguas mediante redirecciones; los archivos duplicados se retiraron.
- `uploads/demo/`: archivos de demostración para copiar al almacenamiento privado. Los CV operativos se guardan fuera del `DocumentRoot` en `PRIVATE_UPLOAD_DIR`; el acceso web directo a los archivos de demostración está bloqueado.
- `cron/`: tarea de cierre automático.
- `docs/API.md`: contrato detallado de endpoints.

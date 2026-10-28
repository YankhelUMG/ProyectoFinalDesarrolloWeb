# Sistema de solicitudes y seguimiento de prácticas profesionales

Aplicación en español para coordinar prácticas profesionales entre estudiantes, empresas y coordinación académica. Incluye API REST en PHP, interfaz HTML sencilla y JavaScript sin frameworks y esquema MySQL/MariaDB.

## Requisitos

- PHP 8.1 o posterior con `pdo_mysql`, `fileinfo` y `mbstring` habilitados.
- MySQL 8.0 o MariaDB 10.5 o posterior con InnoDB, columnas generadas, vistas, JSON y triggers.
- Apache 2.4 con `mod_rewrite` y `AllowOverride All` para las rutas amigables.
- HTTPS en producción para que la cookie de sesión lleve el atributo `Secure`.

## Instalación en XAMPP

1. Copia la carpeta a `C:\xampp\htdocs\ProyectoFinalDesarrolloWeb` e inicia Apache y MySQL.
2. En XAMPP puedes conservar el `DocumentRoot` predeterminado de `htdocs` y abrir `http://localhost/ProyectoFinalDesarrolloWeb/`. El inicio principal está en `index.php`, en la raíz del proyecto. Las reglas `.htaccess` niegan acceso a configuración, código interno, SQL, archivos de carga y APIs antiguas.
3. Exporta primero cualquier base existente que quieras conservar. Importa `database/instalacion_completa.sql` desde phpMyAdmin con una cuenta administradora y sobre una base vacía. El archivo crea el esquema, vistas, triggers, catálogos, datos de prueba, la cuenta de aplicación y sus permisos; no elimina tablas existentes y fallará si ya existen las tablas del sistema.
4. Antes de importarlo, reemplaza todas las apariciones de `CAMBIAR_POR_UNA_CLAVE_LARGA` dentro de `database/instalacion_completa.sql` por la misma clave larga. Guarda esa clave como `DATABASE_PASSWORD` en el archivo privado de conexión.
5. Copia `config/.env.example` a `C:\xampp\private\practicas.env` y configura allí `DATABASE_HOST`, `DATABASE_NAME`, `DATABASE_USER`, `DATABASE_PASSWORD`, `PRIVATE_UPLOAD_DIR` y `APP_TIMEZONE`. La conexión busca ese archivo externo por defecto; para otra ubicación define `APP_DATABASE_CONFIG` en el entorno de Apache y PHP. Crea `C:\xampp\private\cv\demo` y copia dentro los seis PDF de `uploads/demo`; asegúrate de que Apache pueda leerlos y escribir en esa carpeta privada.
6. En XAMPP abre `http://localhost/ProyectoFinalDesarrolloWeb/`. La interfaz carga sus recursos de `public/assets/` y se comunica con la API compatible en `public/api/index.php`. La API principal también se publica bajo `/API/`; por ejemplo, el login REST está en `/API/auth/login`.

Las credenciales de conexión no viven en MySQL porque el programa necesita abrir la primera conexión para consultarla. Se guardan fuera de `htdocs`, en `C:\xampp\private\practicas.env`; `config/.env.example` es solo una plantilla sin credenciales activas. El usuario `practicas_app` tiene permisos mínimos sobre las tablas de la aplicación, solo lectura e inserción para `bitacora`, y no tiene `DELETE` ni DDL. No uses `root` para ejecutar la aplicación.

### Instalación de la base

`database/instalacion_completa.sql` contiene la instalación completa en un solo archivo. Para preparar una instalación modular desde una base vacía, ejecuta en este orden `sql/database.sql`, `sql/triggers.sql`, `sql/vistas.sql`, `sql/datos_demo.sql` y `sql/usuario_aplicacion.sql`. Antes de ejecutar el último archivo, reemplaza todas las apariciones de `CAMBIAR_POR_UNA_CLAVE_LARGA` por la misma clave indicada en `DATABASE_PASSWORD`. El usuario de aplicación queda limitado a `localhost` y `127.0.0.1`, sin permisos `DELETE`, DDL ni escritura en la bitácora aparte de `INSERT`. Usa uno de los dos caminos; no importes la demo dos veces.

Los códigos de cuenta, usuario y carné usan índices únicos sobre columnas generadas que devuelven `NULL` al marcar una fila como eliminada. Así se puede volver a registrar una dirección ya usada por una cuenta eliminada, mientras los registros activos siguen siendo únicos. Restaurar puede producir conflicto si el valor ya fue tomado por otra cuenta activa; en ese caso la operación se cancela con HTTP 409.

En el registro público, las cuentas de usuario se crean con rol de estudiante y las solicitudes de empresa permanecen pendientes. Las empresas pueden iniciar sesión para consultar su perfil y el estado de revisión; sus funciones se habilitan al aprobarlas. Admin y superadmin pueden revisar empresas; al aprobar una solicitud, también se activa la cuenta asociada. Si la base ya estaba instalada antes de este cambio, ejecuta `database/migracion_aprobacion_empresa_admin.sql` una vez para dar al rol admin el permiso y las transiciones de revisión empresarial.

## Cuentas de demostración

Todas usan la contraseña `ClaveDemo2026!`:

| Perfil | Usuario |
|---|---|
| Superadministrador | `superadmin` |
| Administrador | `admin1` |
| Administrador | `admin2` |
| Empresa aprobada | `innovatech` |
| Empresa pendiente | `empresa_pendiente` |
| Empresa rechazada | `empresa_rechazada` |
| Estudiante | `andrea`, `bruno`, `camila`, `diego`, `elena`, `fabian` |

Cambia estas contraseñas antes de publicar. Sus hashes están guardados en SQL y fueron generados para `ClaveDemo2026!`; las rutas de CV son relativas, por ejemplo `demo/cv_andrea.pdf`. Copia los archivos de `uploads/demo` dentro de `PRIVATE_UPLOAD_DIR/demo` (por defecto `C:\xampp\private\cv\demo`). Los archivos se sirven únicamente por endpoints que verifican permisos.

La demo incluye:

- Un superadministrador, dos administradores, tres empresas (aprobada, pendiente y rechazada) y seis estudiantes.
- Una práctica en cada estado del catálogo, una práctica cerrada y otra con una postulación aceptada que completa su cupo.
- Postulaciones en los cinco estados (`pendiente`, `aceptada`, `rechazada`, `retirada` y `cancelada`), con historial inicial.
- Seis archivos PDF de muestra bajo `uploads/demo`, para copiar al almacenamiento privado antes de probar descargas o postulaciones.

## Cierre automático

El cierre también se verifica al consultar oportunidades, prácticas propias y administrativas, postulaciones, dashboards y reportes, y antes de procesar cambios que puedan afectar postulaciones. El proceso es idempotente: solo toma prácticas publicadas que ya vencieron o alcanzaron el cupo, bloquea cada práctica y resuelve el cierre y el rechazo de pendientes dentro de una transacción.

### Programador de tareas de Windows

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
curl.exe -c sesion.txt -b sesion.txt http://localhost/ProyectoFinalDesarrolloWeb/public/api/csrf

# Copia csrf_token de la primera respuesta en TOKEN_INICIAL.
curl.exe -c sesion.txt -b sesion.txt -H "Content-Type: application/json" -H "X-CSRF-Token: TOKEN_INICIAL" `
  -d '{"identificador":"bruno","contrasena":"ClaveDemo2026!"}' `
  http://localhost/ProyectoFinalDesarrolloWeb/public/api/auth/login

# Consultar el catálogo público
curl.exe -b sesion.txt http://localhost/ProyectoFinalDesarrolloWeb/public/api/practicas

# Actualizar el perfil de Bruno con PUT
curl.exe -X PUT -b sesion.txt -H "Content-Type: application/json" -H "X-CSRF-Token: COPIAR_TOKEN" `
  -d '{"correo":"bruno@practicas.local","nombres":"Bruno","apellidos":"Pérez","telefono":"55550018","carnet":"20260002","id_carrera":1,"universidad":"Universidad de Guatemala","semestre":"Quinto"}' `
  http://localhost/ProyectoFinalDesarrolloWeb/public/api/perfil

# Crear una postulación; Bruno aún no está postulado a la práctica 8. Usa el csrf_token del login.
curl.exe -b sesion.txt -H "Content-Type: application/json" -H "X-CSRF-Token: COPIAR_TOKEN" `
  -d '{"id_practica":8,"carta_presentacion":"Me interesa esta oportunidad."}' `
  http://localhost/ProyectoFinalDesarrolloWeb/public/api/postulaciones

# Cerrar sesión
curl.exe -X DELETE -b sesion.txt -H "X-CSRF-Token: COPIAR_TOKEN" `
  http://localhost/ProyectoFinalDesarrolloWeb/public/api/auth/logout
```

Para `PUT`, usa `curl.exe -X PUT`, `Content-Type: application/json`, el encabezado CSRF y un cuerpo JSON. `DELETE` en esta API es baja lógica; no elimina filas físicamente. En cada caso la respuesta tiene la forma `{ "ok": true|false, "mensaje": "...", "datos": ..., "errores": {} }` y usa códigos HTTP apropiados. Al fallar la conexión o una consulta, se registra el detalle en el log PHP y la API responde un error genérico.

## Seguridad y bitácora

- Las consultas viven en repositorios de `includes/db`; los parámetros se preparan con PDO nativo (`ATTR_EMULATE_PREPARES=false`).
- `requerirRol` obtiene en cada solicitud protegida el usuario, el estado vigente y el rol desde la sesión persistida y MySQL. Los permisos se consultan en `permisos` y `rol_permiso`; se valida además el dueño de cada práctica/postulación.
- Los formularios protegidos usan cookie `HttpOnly`, `SameSite=Strict`, regeneración de sesión y CSRF mediante `X-CSRF-Token`. El bloqueo, timeout, política de contraseña y tamaño/formato de CV se leen de `configuracion`.
- `database/instalacion_completa.sql` crea triggers de inserción y actualización, detecta eliminación/restauración lógica y protege las tablas contra `DELETE` físico. `bitacora` se protege contra UPDATE/DELETE. Los hashes de contraseña y tokens de sesión no se incluyen en los JSON auditados.
- PHP agrega eventos de login, bloqueo, acceso denegado, cierre automático, descarga y cambio de contraseña. El contexto de usuario e IP se establece en la conexión PDO.
- Para añadir una tabla: agrega sus columnas de autoría y baja lógica, claves/índices, triggers equivalentes en `database/instalacion_completa.sql` (AFTER INSERT, AFTER UPDATE y BEFORE DELETE), vista de lectura si corresponde, repositorio y permisos. Después actualiza el script de instalación.

## Módulos y plan de commits individual

Confirma cada módulo después de revisarlo. Estos mensajes mantienen el historial ordenado y descriptivo:

1. `feat(sql): define esquema, catálogos y auditoría`
2. `feat(seguridad): agrega conexión privada, sesiones y permisos`
3. `feat(api-admin): gestiona coordinación y catálogos`
4. `feat(api-empresa): gestiona prácticas y postulaciones`
5. `feat(api-estudiante): agrega registro, perfil y CV`
6. `feat(cron): cierra prácticas vencidas o completas`
7. `feat(interfaz): agrega pantallas responsivas por rol`
8. `docs(demo): documenta API e incluye datos de prueba`

No se generó guion de video.

## Estructura principal

- `index.php`: página de inicio principal en la raíz del proyecto.
- `public/`: recursos del cliente y el puente hacia la API; no contiene otro inicio.
- `API/`: punto de entrada REST y módulos de autenticación, administración y negocio; `public/api/` es el puente de acceso recomendado.
- `config/`: bootstrap y plantilla de variables de entorno; la conexión real se guarda fuera de `htdocs`.
- `includes/db/`: repositorios PDO y conexión; `includes/Seguridad/`, `includes/Http/` e `includes/Servicios/` agrupan componentes reutilizables.
- `pages/`: esqueletos PHP de administración, empresa, perfil, postulaciones y consultas. `index.php` es la entrada pública principal y carga los datos desde la API.
- `database/instalacion_completa.sql`: instalación completa de la base de datos en un solo archivo.
- `public/assets/css/app.css` y `public/assets/js/app.js`: estilos propios e interfaz vanilla que consume la API.
- `js/app.js`: compatibilidad con formularios PHP auxiliares heredados.
- `sql/`: esquema (`database.sql`), triggers, vistas, datos de demostración y permisos de la cuenta de aplicación para instalación modular.
- `uploads/demo/`: archivos de demostración para copiar al almacenamiento privado. Los CV operativos se guardan fuera del `DocumentRoot` en `PRIVATE_UPLOAD_DIR`; el acceso web directo a los archivos de demostración está bloqueado.
- `cron/`: tarea de cierre automático.
- `docs/API.md`: contrato detallado de endpoints.

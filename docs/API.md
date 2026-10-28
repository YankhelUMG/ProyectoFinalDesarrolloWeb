# API REST

La API es independiente de la interfaz. Prefijo recomendado en XAMPP:

```text
http://localhost/ProyectoFinalDesarrolloWeb/public/api
```

También se puede usar el punto de entrada `/API`. El puente `public/api/index.php` envía las solicitudes al mismo controlador. La mayoría de respuestas tienen esta forma:

```json
{"ok":true,"mensaje":"Solicitud completada.","datos":{},"errores":{}}
```

Las descargas de CV responden con `application/pdf`. Las mutaciones necesitan cookie de sesión y `X-CSRF-Token`; no se acepta un rol enviado por el cliente. El servidor valida el rol vigente, permisos y propiedad del registro en cada operación.

Códigos habituales: `200` correcto, `201` creado, `400` solicitud mal formada, `401` sin sesión o credenciales incorrectas, `403` sin permiso o CSRF inválido, `404` no encontrado, `405` método no permitido, `409` duplicado o conflicto, `422` validación de negocio, `429` bloqueo por intentos y `500` error interno genérico. Un `DELETE` siempre es lógico.

`OPTIONS /{ruta}` devuelve `204 No Content` y el encabezado `Allow: GET, POST, PUT, DELETE, OPTIONS`. No lleva cuerpo JSON.

## Sesión, registro y catálogos

| Método y ruta | Acceso | Parámetros y resultado |
|---|---|---|
| `GET /csrf` | Público | Inicia la cookie PHP y devuelve `datos.csrf_token`, necesario para la siguiente mutación pública. |
| `GET /sistema` | Público | Devuelve nombre del sistema, longitud mínima de contraseña y límite máximo del CV (`cv_tamano_maximo_bytes`) para la interfaz. |
| `POST /auth/login` | Público + CSRF | JSON `identificador`, `contrasena`; `tipo_cuenta` es opcional (`estudiante` o `empresa`). La respuesta incluye usuario y nuevo `csrf_token`. |
| `GET /auth/me` | Sesión | Identidad, rol y estado vigentes desde la base de datos, más el token CSRF actual. Permite consultar una cuenta empresarial pendiente para mostrar su estado. |
| `DELETE /auth/logout` | Sesión + CSRF | Revoca la sesión y la cookie. |
| `POST /auth/registro-estudiante` | Público + CSRF | `multipart/form-data`: `usuario`, `correo`, `contrasena`, `nombres`, `apellidos`, `telefono`, `carnet`, `id_carrera`, `universidad`, `semestre` opcional y archivo `cv` PDF obligatorio. Crea una cuenta de usuario con rol estudiante y abre sesión. |
| `POST /auth/registro-empresa` | Público + CSRF | JSON `usuario`, `correo`, `contrasena`, `nombres`, `apellidos`, `nombre_legal`, `nombre_comercial`, `identificacion_fiscal`, `sector`, `descripcion`, `correo_contacto`, `telefono`, `sitio_web` y `direccion`. Crea la cuenta y solicitud en estado pendiente; permite iniciar sesión para consultar el perfil y la revisión, mientras las funciones empresariales requieren aprobación de admin o superadmin. |
| `POST /auth/cambiar-clave` | Sesión + CSRF | JSON `contrasena_nueva` y `confirmacion`. Obligatorio antes de continuar si la contraseña es temporal. |
| `GET /catalogos` o `GET /catalogos?tipo=area` | Público | Devuelve catálogos activos. Tipos comunes: `rol`, `usuario`, `empresa`, `practica`, `postulacion`, `area`, `carrera` y `modalidad`. |
| `GET /catalogos/transiciones?entidad=practica&estado_codigo=pendiente_revision` | Sesión | Devuelve destinos permitidos para el rol actual y `requiere_comentario`. |

## Prácticas y postulaciones

| Método y ruta | Acceso | Parámetros y resultado |
|---|---|---|
| `GET /practicas` | Público | Filtros opcionales `q`, `area` (id), `modalidad` (id), `empresa`, `fecha_inicio_desde`, `fecha_inicio_hasta`, `pagina` y `por_pagina`. Solo devuelve prácticas publicadas y vigentes. |
| `GET /practicas/{id}` | Público | Detalle de práctica publicada y vigente. Una empresa solo puede consultar las propias; coordinación puede consultar cualquier estado con permiso. |
| `GET /practicas/mias` | Empresa | Lista de prácticas propias, estados y contadores; útil para revisar una cuenta pendiente. |
| `GET /practicas/admin?estado=pendiente_revision` | Admin o superadmin con permiso | Prácticas paginadas; filtro `estado` opcional. |
| `POST /practicas` | Empresa aprobada + CSRF | JSON `titulo`, `descripcion`, `requisitos`, `id_area`, `id_modalidad`, `ubicacion`, `fecha_inicio`, `cupo_maximo`, `fecha_limite_postulacion`. Se crea en revisión. |
| `PUT /practicas/{id}` | Empresa aprobada propietaria + CSRF | Mismos campos de creación. Devuelve la propuesta a revisión; no admite una práctica cerrada. |
| `PUT /practicas/{id}/revision` | Superadmin + CSRF | JSON `estado_codigo` (`publicada`, `cambios_solicitados` o `rechazada`) y `comentario`. Cambios solicitados y rechazo requieren comentario. |
| `POST /practicas/{id}/cerrar` | Empresa propietaria aprobada, admin o superadmin + CSRF | Cierra la práctica y rechaza sus solicitudes pendientes dentro de una transacción. JSON vacío. |
| `GET /practicas/{id}/postulaciones` | Empresa aprobada propietaria | Solicitudes de la práctica con datos del estudiante, contacto, carrera, fecha y nombre del CV. |
| `GET /postulaciones` | Sesión | Devuelve las propias para estudiante, las recibidas para empresa aprobada o la lista administrativa para coordinación. Admin admite `estado`, `pagina` y `por_pagina`. |
| `POST /postulaciones` | Estudiante + CSRF | JSON `id_practica` y `carta_presentacion` opcional. Requiere correo, teléfono y CV vigentes. Una solicitud retirada se reactiva en la misma fila. |
| `POST /practicas/{id}/postulaciones` | Estudiante + CSRF | Igual que el POST anterior; el id de práctica va en la ruta. Cuerpo opcional `carta_presentacion`. |
| `GET /postulaciones/{id}` | Estudiante propietario, empresa propietaria, admin o superadmin con permiso | Detalle de una solicitud autorizada. |
| `GET /postulaciones/{id}/historial` | Mismos permisos que el detalle | Línea de tiempo con estado anterior/nuevo, actor, fecha y comentario. |
| `PUT /postulaciones/{id}` | Empresa aprobada propietaria + CSRF | JSON `estado_codigo` (`aceptada` o `rechazada`) y `comentario` opcional. Rechazar exige el mínimo configurado; el cupo se bloquea en transacción. |
| `DELETE /postulaciones/{id}` | Estudiante propietario + CSRF | Retira una solicitud pendiente o aceptada y conserva su historial. |

## Perfiles y archivos CV

| Método y ruta | Acceso | Parámetros y resultado |
|---|---|---|
| `GET /perfil` | Cualquier cuenta autenticada (estudiante, empresa, admin o superadmin) | Devuelve únicamente el perfil propio. Admin y superadmin reciben una vista de solo lectura; estudiante y empresa reciben sus datos de perfil. |
| `PUT /perfil` | Cuenta autenticada propia + CSRF | Actualiza únicamente los datos de la cuenta autenticada. Estudiante: contacto y datos académicos; empresa: contacto y datos empresariales; admin y superadmin: `correo`, `nombres`, `apellidos` y `telefono`. |
| `GET /perfil/cv` | Estudiante + permiso de perfil + sesión | Descarga el CV actual en PDF. |
| `POST /perfil/cv` | Estudiante + CSRF | `multipart/form-data` con `cv` PDF; reemplaza la referencia en BD sin borrar el archivo anterior del disco. |
| `DELETE /perfil/cv` | Estudiante + CSRF | Marca el CV actual como eliminado en BD. |
| `GET /cv/{id_postulacion}` | Estudiante propietario, empresa propietaria aprobada, admin o superadmin con permiso | Descarga el snapshot PDF de esa solicitud y registra el evento en bitácora. |

## Panel de coordinación

| Método y ruta | Acceso | Parámetros y resultado |
|---|---|---|
| `GET /admin/usuarios?pagina=1&por_pagina=20&q=ana` | Admin o superadmin con permiso | Lista paginada y búsqueda por usuario, correo o nombre. `eliminados=1` lista cuentas dadas de baja. Admin no ve superadmins. |
| `GET /admin/usuarios/{id}` | Admin o superadmin con permiso | Consulta una cuenta visible; admin no puede consultar una cuenta superadmin. |
| `POST /admin/usuarios` | Admin o superadmin + CSRF | Crea cuenta con `rol_codigo`, datos personales y campos específicos de estudiante o empresa. Si es estudiante, usa multipart e incluye el CV PDF. El admin no puede crear superadmins. |
| `PUT /admin/usuarios/{id}` | Admin o superadmin + CSRF | JSON `correo`, `nombres`, `apellidos`, `telefono`. |
| `PUT /admin/usuarios/{id}/estado` | Admin o superadmin + CSRF | JSON `estado_codigo` y `comentario`; transición sujeta al catálogo. Desactivar cancela relaciones activas y revoca sesiones. |
| `PUT /admin/usuarios/{id}/rol` | Admin o superadmin + CSRF | JSON `rol_codigo`. Se conserva el rol existente para no perder datos de perfil; no permite escalar ni cambiar el rol de un superadmin. |
| `DELETE /admin/usuarios/{id}` | Admin o superadmin + CSRF | Baja lógica transaccional; cancela prácticas y solicitudes activas relacionadas. Admin no puede tocar superadmins. |
| `POST /admin/usuarios/{id}/restaurar` | Admin o superadmin + CSRF | Restaura la cuenta y su perfil lógico. No reactiva solicitudes que ya fueron canceladas. |
| `POST /admin/usuarios/{id}/restablecer-clave` | Solo superadmin + CSRF | Genera una contraseña temporal que se devuelve una sola vez; revoca sesiones y obliga al siguiente cambio de contraseña. |
| `GET /admin/empresas` | Admin o superadmin con permiso `empresas.revisar` | Lista empresas pendientes de decisión. |
| `PUT /admin/empresas/{id}/revision` | Admin o superadmin con permiso `empresas.revisar` + CSRF | JSON `estado_codigo` (`aprobada` o `rechazada`) y `comentario`; el rechazo requiere motivo. Aprobar cambia también la cuenta de usuario de pendiente a activa. |
| `GET /admin/catalogos/{catalogo}` | Admin o superadmin con permiso | Lee áreas, carreras, modalidades o mensajes. `?eliminados=1` muestra registros eliminados. Superadmin también puede leer estados y transiciones. |
| `POST /admin/catalogos/{catalogo}` | Admin o superadmin + CSRF | Crea un registro con los campos del catálogo. Roles, permisos y tipos de entidad son estructurales y de solo lectura. |
| `PUT /admin/catalogos/{catalogo}/{id}` | Admin o superadmin + CSRF | Actualiza campos permitidos del catálogo. Estados y transiciones requieren superadmin. |
| `DELETE /admin/catalogos/{catalogo}/{id}/eliminar` | Admin o superadmin + CSRF | Baja lógica del catálogo. |
| `POST /admin/catalogos/{catalogo}/{id}/restaurar` | Admin o superadmin + CSRF | Restaura el registro del catálogo. |
| `GET /admin/configuracion` | Admin o superadmin con permiso | Lista valores, tipos y descripciones. |
| `PUT /admin/configuracion/{clave}` | Admin o superadmin + CSRF | JSON `valor`; solo modifica claves editables. |
| `GET /admin/bitacora?tabla=postulaciones&accion=UPDATE&usuario_id=7&desde=2026-10-01&hasta=2026-10-31` | Admin o superadmin con permiso | Lista paginada; filtros opcionales `tabla`, `accion`, `usuario_id`, `desde` y `hasta`. |
| `GET /admin/reportes` o `GET /reportes` | Admin o superadmin con permiso | Contadores por estado de prácticas y postulaciones. |
| `GET /dashboard` | Estudiante, empresa aprobada, admin o superadmin | Contadores por estado, filtrados por cuenta/rol. |

## Ejemplos con curl

Los comandos siguientes usan Bash. Sustituye `CONTRASENA_DEMO_DEL_README` por una de las credenciales publicadas en el README. En PowerShell sustituye `curl` por `curl.exe` y el carácter de continuación `\` por el acento grave `` ` ``. Conserva el mismo archivo de cookies. El token CSRF inicial se usa para login y registro público; después del login usa `datos.csrf_token` de la respuesta autenticada.

```sh
BASE='http://localhost/ProyectoFinalDesarrolloWeb/public/api'

# Obtener cookie y token CSRF inicial
curl -c cookie.txt -b cookie.txt "$BASE/csrf"

# Login de estudiante o empresa
curl -c cookie.txt -b cookie.txt -H 'Content-Type: application/json' \
  -H 'X-CSRF-Token: TOKEN_INICIAL' \
  -d '{"identificador":"bruno","contrasena":"CONTRASENA_DEMO_DEL_README"}' \
  "$BASE/auth/login"

# GET: prácticas públicas
curl -b cookie.txt "$BASE/practicas?pagina=1&por_pagina=10&area=1"

# POST: postulación de estudiante (usa el CSRF devuelto por login)
curl -b cookie.txt -H 'Content-Type: application/json' -H 'X-CSRF-Token: TOKEN' \
  -d '{"id_practica":8,"carta_presentacion":"Me interesa esta oportunidad."}' \
  "$BASE/postulaciones"

# PUT: empresa acepta o rechaza una postulación
curl -X PUT -b cookie.txt -H 'Content-Type: application/json' -H 'X-CSRF-Token: TOKEN' \
  -d '{"estado_codigo":"rechazada","comentario":"El perfil no cumple los requisitos indicados."}' \
  "$BASE/postulaciones/2"

# DELETE lógico: estudiante retira su propia postulación
curl -X DELETE -b cookie.txt -H 'X-CSRF-Token: TOKEN' "$BASE/postulaciones/2"

# Logout
curl -X DELETE -b cookie.txt -H 'X-CSRF-Token: TOKEN' "$BASE/auth/logout"
```

Registro de estudiante: `POST /auth/registro-estudiante` recibe `multipart/form-data` con `cv=@mi_cv.pdf` y los campos requeridos indicados arriba. Registro empresarial: `POST /auth/registro-empresa` usa JSON y requiere CSRF inicial. Las cuentas administrativas se crean desde `POST /admin/usuarios`.

Ejemplo de práctica (`POST /practicas`):

```json
{"titulo":"Asistente de desarrollo","descripcion":"Apoyo al equipo de desarrollo.","requisitos":"Conocimientos de PHP.","id_area":1,"id_modalidad":1,"ubicacion":"Ciudad de Guatemala","fecha_inicio":"2027-02-15","cupo_maximo":2,"fecha_limite_postulacion":"2027-01-31"}
```

Los valores y transiciones válidos se consultan en `/catalogos` y `/catalogos/transiciones`; el servidor siempre vuelve a validarlos. Los errores detallados de base de datos quedan en el log PHP y no se envían al cliente.

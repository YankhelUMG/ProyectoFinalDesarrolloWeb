# Mapa del código (para exponer el proyecto)

Regla: **una clase por archivo**, con el mismo nombre que la clase. Cada método lleva un comentario de una línea.

## Recorrido de una petición
`public/assets/js/**/*.js` → `public/api/index.php` → `API/index.php` → `API/ControladorApi.php` → `API/controladores/*` → `includes/db/*` (repositorios) → MySQL.

## Backend (PHP)
| Carpeta | Clase / archivo | Para qué sirve |
|---|---|---|
| `config/` | `bootstrap.php` | Carga las clases, cabeceras de seguridad y sesión |
| `API/` | `index.php` | Entrada de la API y manejo de errores |
| `API/` | `ControladorApi.php` | Enrutador: elige el controlador según la ruta |
| `API/controladores/` | `ControladorBase` | Validaciones comunes (texto, correo, teléfono, carné, CV) |
| | `AccesoControlador` | Login, registros, cambio de clave, catálogos públicos |
| | `PracticasControlador` | Catálogo, crear, editar, revisar y cerrar prácticas |
| | `PostulacionesControlador` | Postularse, aceptar o rechazar, retirar, historial |
| | `PerfilControlador` | Perfiles, descarga de CV, reportes y dashboard |
| | `AdministracionControlador` | Catálogos, configuración y revisión de empresas |
| | `UsuariosControlador` | Gestión de cuentas |
| `includes/Http/` | `Peticion`, `Respuesta` | Leer la solicitud y devolver el JSON |
| `includes/Seguridad/` | `Sesion` | Login, bloqueo por intentos, CSRF, cookie |
| | `Acceso` | Comprobación de rol y permisos |
| `includes/Servicios/` | `ServicioCierre` | Cierre automático por fecha o cupo |
| | `ArchivosCv` | Guardado seguro de CV (carpeta privada) |
| `includes/db/` | `Conexion` | Conexión PDO y auditoría |
| | `UsuarioRepo`, `EstudianteRepo`, `EmpresaRepo` | Cuentas y perfiles |
| | `PracticaRepo`, `PostulacionRepo` | Prácticas y postulaciones |
| | `CatalogoRepo`, `ConfiguracionRepo`, `BitacoraRepo` | Catálogos, configuración y bitácora |
| `cron/` | `cerrar_practicas.php` | Tarea programada de cierre |
| `database/` | `instalacion_completa.sql` | Tablas, vistas, triggers y datos demo |

## Frontend (JavaScript, CSS y páginas)
| Archivo | Contenido |
|---|---|
| `pages/*.php` + `_layout.php` | Páginas de cada módulo (postulaciones, prácticas, perfil, administración, usuarios) |
| `public/login.html`, `registro-*.html` | Pantallas públicas |
| `js/app.js` | Entrada del portal: elige el módulo de la pantalla |
| `js/acceso.js` | Entrada de las pantallas públicas: login, registros, cambio de clave |
| `js/modulos/postulaciones.js` | Lista, seguimiento y acciones de postulaciones |
| `js/modulos/practicas.js` | Catálogo, detalle y formulario de prácticas |
| `js/modulos/perfil.js`, `usuarios.js`, `administracion.js` | Perfil, cuentas y panel admin |
| `js/nucleo/api.js`, `comun.js`, `sesion.js` | Peticiones HTTP, utilidades comunes y menú/sesión |
| `js/ui/ui.js`, `iconos.js` | Componentes (alertas, diálogos, validaciones) e iconos de botones |
| `css/app.css` | Índice de estilos (solo `@import`) |
| `css/partes/01..12-*.css` | Estilos por sección: base, encabezado, formularios, tarjetas, diálogos, paneles, componentes, acceso, móvil, tema y logo |

## Dónde está cada requisito de la rúbrica
- Seguridad: `includes/Seguridad/`, `ControladorBase`, `config/bootstrap.php`, repositorios (consultas preparadas).
- Funcionamiento: controladores de prácticas y postulaciones, `ServicioCierre`.
- Base de datos: `database/instalacion_completa.sql` (secciones numeradas 1-6 en los comentarios).
- Interfaz: `pages/_layout.php`, `public/assets/css/partes/` (índice en `app.css`).

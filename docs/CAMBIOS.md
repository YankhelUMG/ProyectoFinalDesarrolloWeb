# Cambios de revisión (11/11/2026)

- `public/assets/js/*.js`: se quitaron los `?v=` de los `import` internos. `comun.js` se cargaba tres veces con distinta versión (cada copia con su propio `estado`), por lo que la pantalla de postulaciones podía quedar sin usuario, catálogos ni recarga. El control de caché pasó a `public/.htaccess`.
- `public/assets/css/app.css`: paleta pizarra (#264653), paneles #e5ebee, títulos con serifa; bloque "Tema sencillo" al final.
- `pages/_layout.php` y `public/login.html`: encabezado de página en panel claro y bienvenida en el login.
- `tests/frontend.test.mjs`: prueba del catálogo alineada con el HTML actual.
- Carpetas: `Configurar-SistemaPracticas.ps1` pasó a `scripts/`; se eliminó `API/core/` (plantilla obsoleta); README y docs ya no citan `historial_sql.zip`.

- Interfaz tipo plataforma de empleo (LinkedIn/Coursera): fondo gris claro, barra superior fija con logotipo, navegación con subrayado, tarjetas con inicial de la empresa, botones en píldora, barra de filtros en tarjeta, login con bienvenida azul a la izquierda. Fuente de sistema (sin archivos externos). Solo CSS más `data-inicial` en las tarjetas (`practicas.js`, `postulaciones.js`).
- Logo de la Universidad Mariano Gálvez de Guatemala (`public/assets/img/logo-umg.webp`, optimizado a 96 px desde el logo publicado en umg.edu.gt) en la esquina superior izquierda de todas las pantallas (CSS en `header ... a::before`). Para usar el archivo original en alta resolución basta reemplazar ese archivo conservando el nombre, o ajustar la ruta en `app.css`.
- Recuadro azul del login rehecho (`.hero-acceso`): texto blanco, lista de pasos con iconos y el sello de la UMG como marca de agua.
- Iconos en botones y enlaces: `public/assets/js/iconos.js` (SVG en línea estilo Lucide, licencia ISC) los asigna por el texto del botón y se activa en `app.js` y `acceso.js`; no cambia la lógica de las pantallas.
- Orden del código: una clase por archivo (`includes/Http.php` se separó en `includes/Http/Peticion.php` y `Respuesta.php`; `ControladorApi` pasó a `API/ControladorApi.php`). Comentario breve de una línea en cada método PHP y en cada función JS, encabezado en cada módulo JS e índice al inicio de `app.css`.
- Nuevo `docs/MAPA_DEL_CODIGO.md`: dónde está cada clase, módulo y requisito de la rúbrica, pensado para la exposición.

## Reorganización por grupos
- `public/assets/js/` ahora tiene tres carpetas: `nucleo/` (api, sesion, comun), `ui/` (ui, iconos) y `modulos/` (practicas, postulaciones, perfil, usuarios, administracion). `app.js` y `acceso.js` siguen en la raíz como puntos de entrada. Solo cambiaron las rutas de los `import`.
- `public/assets/css/app.css` quedó como índice con `@import` y los estilos pasaron a `public/assets/css/partes/01..12-*.css`, en el mismo orden que antes (el resultado visual es idéntico). La ruta del logo en la parte 10 se ajustó a `../../img/`.
- `database/instalacion_completa.sql`: solo se agregaron comentarios que numeran las secciones (1 tablas, 2 llaves foráneas, 3 datos, 4 triggers, 5 vistas, 6 usuario y permisos).

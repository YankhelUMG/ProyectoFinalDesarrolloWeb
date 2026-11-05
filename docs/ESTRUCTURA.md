# Cómo leer y mantener el proyecto

Si empiezas desde cero, comienza por la [guía de estudio para principiantes](#guía-de-estudio-desde-cero), más abajo en este documento.

El proyecto usa PHP, JavaScript y CSS sin frameworks. Las páginas muestran la interfaz,
la API valida las operaciones y los repositorios consultan MySQL con PDO.

## Empieza por estos archivos

| Quiero entender o cambiar… | Archivo o carpeta |
| --- | --- |
| Inicio de la aplicación | `index.php` y `public/assets/js/app.js` |
| Inicio de sesión y registros | `public/assets/js/acceso.js` y `API/controladores/AccesoControlador.php` |
| Menú de cada perfil | `public/assets/js/sesion.js` |
| Qué pantalla se abre | `public/assets/js/app.js` |
| Colores, espacios y adaptación a pantallas | `public/assets/css/app.css` |
| Rutas de la API | `API/index.php` |
| Consultas del estudiante y su CV | `includes/db/EstudianteRepo.php` |
| Permisos y sesiones | `includes/Seguridad/Acceso.php` y `Sesion.php` |
| Configuración de la conexión | `includes/db/Conexion.php` |

## Recorrido de una petición

Ejemplo: consultar el perfil de un estudiante.

1. `pages/perfil.php` carga el HTML compartido de `pages/_layout.php`.
2. `public/assets/js/app.js` identifica el módulo y consulta la sesión vigente.
3. `perfil.js` construye el formulario con los datos recibidos de la API.
4. `api.js` envía `GET /public/api/perfil` y conserva la cookie de sesión.
5. `public/api/index.php` conduce la petición a `API/index.php`.
6. `ControladorApi`, dentro de `API/index.php`, selecciona `PerfilControlador`.
7. `PerfilControlador::perfilEstudiante()` consulta `EstudianteRepo`.
8. La API devuelve JSON y la pantalla muestra el resultado.

Para guardar cambios, el recorrido es el mismo con `PUT`. El servidor comprueba
el token CSRF, los permisos y los campos antes de escribir en la base.

## Organización de PHP

`API/index.php` recibe la petición, controla errores y usa `ControladorApi` para seleccionar el controlador. Cada controlador tiene
funciones para su operación: `crearPractica()`, `revisarPractica()`,
`crearPostulacion()`, `retirarPostulacion()`, `perfilEstudiante()`, etc.

| Controlador | Responsabilidad |
| --- | --- |
| `AccesoControlador` | Login, logout, registros, contraseña y catálogos públicos |
| `PracticasControlador` | Consulta, creación, edición, publicación y cierre de prácticas |
| `PostulacionesControlador` | Solicitudes, decisiones de empresa, retiros e historial |
| `PerfilControlador` | Perfiles, CV, reportes y dashboard |
| `AdministracionControlador` | Panel, aprobación de empresas, catálogos, configuración y bitácora |
| `UsuariosControlador` | Crear, editar, desactivar, eliminar lógicamente y restaurar cuentas |

Los controladores heredan de `ControladorBase`: comparten la petición, los
repositorios y validaciones como `texto()`, `correo()`, `telefono()` y `entero()`.
Las reglas y permisos específicos permanecen en la función que realiza la operación.

Los archivos de `includes/db/` contienen SQL preparado. Cada repositorio trabaja
con su entidad; `EstudianteRepo` ya no está mezclado con `EmpresaRepo`.
`includes/Servicios/` concentra el cierre de prácticas y el almacenamiento privado de CV.

## Organización de JavaScript

`public/assets/js/app.js` inicia la pantalla y elige el módulo. El código de cada
pantalla está en `practicas.js` (catálogo y empresa), `perfil.js`, `postulaciones.js`,
`usuarios.js` y `administracion.js`.

Las piezas compartidas son:

- `api.js`: solicitudes HTTP, errores, cookies y renovación del token CSRF.
- `sesion.js`: cuenta autenticada, menú y navegación según el rol.
- `comun.js`: estado, catálogos, recarga, creación de elementos, tarjetas, tablas y cambios de estado.
- `ui.js`: diálogos, confirmaciones, carga, errores y validación compartida del CV.

No escribas la misma solicitud HTTP o el mismo control visual en varias pantallas:
usa estas funciones compartidas. Tampoco coloques SQL en JavaScript ni confíes en
el menú para autorizar operaciones; los permisos se verifican en PHP.

## Enlaces anteriores

Las páginas están directamente en `pages/`: `practicas.php`, `empresa.php`,
`perfil.php`, `postulaciones.php`, `administracion.php`, `usuarios.php` y `consultas.php`.
`public/practicas.php` es el catálogo público. No hay una carpeta por pantalla.

Apache conserva los enlaces antiguos mediante las reglas de `.htaccess`.
Los archivos heredados que solo redirigían se retiraron. Las rutas `.php` anteriores
se normalizan en `Peticion::prepararRutaAnterior()` en `includes/Http.php` y usan las mismas validaciones de la API.
Las nuevas integraciones deben usar las rutas REST de `docs/API.md`.

La API tiene una carpeta de controladores; no hay carpetas por método HTTP.

## Base de datos y pruebas

La refactorización no requiere migraciones ni cambios de estructura en MySQL.
El único SQL vigente es `database/instalacion_completa.sql`. El contenido SQL duplicado
se retiró después de comprobar que tenía las mismas instrucciones. Las migraciones
y el guion de versiones previas se conservan en `database/historial_sql.zip`.

Consulta `tests/README.md` para ejecutar las pruebas. El recorrido completo se
prueba en una instancia temporal: acceso de los cuatro roles, propuestas,
publicación, postulaciones, perfil, CV, usuarios y aprobación de empresas.

## Resultado de la reorganización

La aplicación pasó de 100 a 53 archivos de código (PHP, JavaScript, CSS y HTML)
y de 56 a 19 carpetas con archivos. Las pruebas están aparte. Se retiraron páginas
antiguas duplicadas y carpetas por método HTTP, y se agruparon utilidades pequeñas.

Se verificaron 27 casos de integración, 7 pruebas de JavaScript y las referencias
entre métodos y rutas en PHP. También se comprobaron con Apache las redirecciones
anteriores y la protección de archivos internos. La base de trabajo no se modificó:
las operaciones completas se ejecutaron en una instancia temporal.

## Recorrido sugerido para demostrar el proyecto

1. Abre el catálogo público y filtra las prácticas por área o modalidad.
2. Entra como estudiante (`bruno`), muestra su perfil, su CV y sus postulaciones.
3. Entra como empresa (`innovatech`) y envía una práctica a revisión.
4. Entra como superadministrador (`superadmin`) y publica la propuesta.
5. Vuelve al estudiante y presenta una postulación a la práctica publicada.
6. Desde la empresa revisa la solicitud y acepta o rechaza al candidato.
7. Muestra el estado y el historial al estudiante, y el cierre de una convocatoria.

La contraseña inicial de las cuentas de demostración está indicada en el README.

## Por qué se agruparon algunos archivos

- `includes/Http.php` contiene `Peticion` y `Respuesta`: entrada y salida HTTP.
- `ConfiguracionRepo` consulta tanto parámetros como mensajes configurables mediante `mensaje()`.
- `API/index.php` reúne la entrada y el enrutador pequeño, evitando un archivo puente.
- Los controladores, repositorios principales y pantallas permanecen separados por responsabilidad.


# Guía de estudio desde cero

Esta guía describe la versión actual: PHP, JavaScript y MySQL, con cuatro perfiles.
Estudia las etapas en orden. En cada una lee, prueba algo pequeño y explícalo con tus
propias palabras. No necesitas memorizar todos los archivos para entender el programa.
Los ejemplos cortos de esta guía son didácticos; no sustituyen el código completo.

## 1. Entender qué problema resuelve

El programa coordina prácticas profesionales. Una empresa propone una práctica;
el superadministrador revisa si puede publicarse; un estudiante se postula y la
empresa decide sobre su candidatura. El sistema conserva estados e historiales.

| Perfil | Tarea principal |
| --- | --- |
| Estudiante, también llamado usuario | Completar su perfil y CV, buscar prácticas, postularse y consultar resultados |
| Empresa | Completar sus datos, esperar aprobación, proponer prácticas y evaluar candidatos |
| Administrador | Gestionar cuentas, revisar empresas y consultar herramientas según sus permisos |
| Superadministrador | Realizar funciones de mayor control, incluida la revisión para publicar prácticas |

Una visita sin sesión puede consultar el catálogo público; no es un quinto perfil.
El registro de una empresa y la publicación de una práctica son revisiones distintas:
primero se aprueba la empresa; después se revisa cada propuesta.

**Ejercicio:** dibuja en papel: empresa → propuesta → revisión → publicación → estudiante → postulación → decisión.
**Comprueba:** ¿quién propone?, ¿quién publica?, ¿quién decide sobre un candidato?

## 2. Entender navegador, servidor y base de datos

El navegador es la aplicación con la que visitas una web. Apache es el servidor
que recibe las peticiones. PHP ejecuta instrucciones en el servidor. MySQL guarda
los datos de manera persistente. XAMPP reúne estas herramientas para trabajar localmente.

`localhost` significa tu propia computadora. Una dirección web no siempre corresponde
a una carpeta o archivo: `/public/api/csrf` es una ruta que el programa interpreta.

El recorrido de una consulta es:

1. El navegador o Thunder Client envía una petición.
2. Apache la recibe y dirige a PHP.
3. PHP valida el acceso y, si hace falta, consulta MySQL.
4. PHP devuelve una respuesta, por ejemplo JSON.
5. JavaScript usa esa respuesta para actualizar lo que ves.

El frontend es la parte que funciona en el navegador. El backend es la parte que
procesa las solicitudes en el servidor. La base de datos es donde se conservan los registros.

**Ejercicio:** abre `http://localhost/ProyectoFinalDesarrolloWeb/` con Apache y MySQL iniciados.
**Comprueba:** ¿dónde se ejecuta PHP?, ¿dónde se guardan los usuarios?

## 3. HTML: la estructura de una pantalla

HTML usa etiquetas para describir contenido. No consulta por sí solo una base de datos.

```html
<section class="tarjeta">
  <h2>Práctica de desarrollo web</h2>
  <p>Empresa: Innovatech</p>
  <button type="button">Ver detalle</button>
</section>
```

`section` agrupa contenido; `h2` es un título; `p` es un párrafo; `button` es un botón.
`class="tarjeta"` permite aplicar estilos compartidos. `id` identifica un elemento concreto.
`header`, `nav`, `main` y `footer` señalan encabezado, navegación, contenido principal y pie.

Los formularios contienen campos. `name` es el nombre con el que se envía el dato.
`type="email"` indica un correo; `required` pide completar el campo. La validación
HTML ayuda al usuario; PHP vuelve a validar porque una petición puede enviarse sin usar la pantalla.

**Archivo para leer:** `public/login.html`.
**Ejercicio:** identifica el formulario, los campos de acceso y el botón de envío.
**Comprueba:** ¿cuál es la diferencia entre `class`, `id` y `name`?

## 4. CSS: cómo se presenta el contenido

CSS selecciona elementos HTML y les aplica reglas.

```css
.tarjeta {
  margin: 16px 0;
  padding: 20px;
  border: 1px solid #96c2db;
  border-radius: 8px;
}
```

`margin` deja espacio por fuera. `padding` deja espacio por dentro. `border` dibuja
el borde. `border-radius` redondea sus esquinas. `color` cambia el texto y `background`
el fondo. El punto de `.tarjeta` indica que se selecciona una clase.

Flexbox y las reglas `@media` ayudan a acomodar el contenido en distintos anchos.
Un diseño responsivo se adapta a la pantalla, no solo reduce el tamaño de sus letras.

**Archivo para leer:** `public/assets/css/app.css`.
**Ejercicio:** en una copia de prueba cambia el padding de una tarjeta y recarga la pantalla.
Después comprueba cómo se ve al reducir el ancho de la ventana.
**Comprueba:** ¿qué cambiarías para separar dos cuadros?, ¿y para separar el texto de su borde?

## 5. Programación básica: datos y decisiones

Una variable guarda un valor. Un valor puede ser texto, número, booleano (`true` o `false`)
o una colección. Una condición decide qué instrucciones ejecutar.

```php
$rol = 'estudiante';
if ($rol === 'estudiante') {
    echo 'Puedes preparar una postulación.';
}
```

En PHP las variables empiezan con `$`. `=` asigna un valor; `===` compara valor y tipo.
Un punto y coma termina una instrucción. Las llaves agrupan instrucciones.
`null` significa ausencia de valor. Un array agrupa varios valores.

```php
$cuenta = ['usuario' => 'bruno', 'rol' => 'estudiante'];
echo $cuenta['usuario'];
```

**Ejercicio:** cambia el rol del ejemplo y explica por qué se muestra o no el mensaje.
**Comprueba:** ¿qué diferencia hay entre asignar y comparar?

## 6. Funciones, clases y métodos

Una función agrupa instrucciones para usarlas varias veces. Recibe parámetros y
puede devolver un resultado con `return`.

```php
function saludar(string $nombre): string {
    return 'Hola, ' . $nombre;
}
echo saludar('José');
```

`$nombre` es el parámetro; `'José'` es el argumento utilizado en la llamada.
`string` indica texto. El punto `.` une textos. `echo` escribe contenido en la respuesta.

Una clase agrupa datos y funciones relacionadas. Una función dentro de una clase
se llama método. `new` crea un objeto a partir de una clase.

```php
$repositorio = new EstudianteRepo();
$perfil = $repositorio->estudiantePorUsuario(13);
```

Aquí se llama a un método de un objeto con `->`. En `Peticion::ruta()` se usa `::`
porque es un método estático: se llama directamente desde la clase. Dentro de un
objeto, `$this` se refiere a ese mismo objeto. `public` permite llamar un método desde
fuera; `private` lo reserva para la clase; `protected` también permite su uso en clases hijas.
`extends ControladorBase` permite compartir validaciones y repositorios con esa clase base.

`require_once` carga otro archivo PHP una sola vez. `__DIR__` contiene la carpeta
del archivo actual. Un archivo `.php` puede contener PHP y HTML; el servidor ejecuta
PHP y envía el contenido resultante al navegador.

**Archivos para leer:** `includes/db/EstudianteRepo.php` y `API/controladores/PerfilControlador.php`.
**Comprueba:** ¿qué recibe `estudiantePorUsuario`?, ¿qué tipo de información devuelve?

## 7. JavaScript: responder a las acciones del usuario

JavaScript escucha eventos, como un clic o el envío de un formulario. También puede
crear elementos y consultar la API. El DOM es la representación de la página que
JavaScript puede consultar y modificar.

```javascript
const boton = document.querySelector('#mi-boton');
boton.addEventListener('click', () => {
  boton.textContent = 'Seleccionado';
});
```

Este ejemplo supone que existe un botón con `id="mi-boton"`. `const` declara una
variable que no se reasigna. `querySelector` busca un elemento. `addEventListener`
registra una función que se ejecutará cuando ocurra el evento.

Una operación de red tarda un tiempo. `async` y `await` permiten esperar su respuesta.
`try` y `catch` permiten manejar errores. `import` y `export` comparten funciones entre módulos.
En este proyecto muchas tarjetas y formularios se construyen desde JavaScript;
por eso no todo el HTML de una pantalla está escrito directamente en su PHP.

**Archivos para leer:** `public/assets/js/app.js`, `perfil.js` y `comun.js`.
**Ejercicio:** localiza una función que atienda un formulario y busca dónde se llama a la API.
**Comprueba:** ¿qué parte crea el formulario y qué parte guarda sus datos?

## 8. API, HTTP y JSON

Una API ofrece operaciones mediante direcciones y métodos HTTP. Cada punto de
acceso se llama endpoint. No es necesario que exista un archivo con el nombre de la ruta.

| Método | Uso habitual en este proyecto |
| --- | --- |
| GET | Consultar datos |
| POST | Crear registros, iniciar sesión o ejecutar una acción |
| PUT | Actualizar información |
| DELETE | Retirar o dar de baja según la operación |

La petición incluye método, URL, encabezados, cookies y, cuando corresponde, un cuerpo.
La respuesta incluye un código HTTP y contenido. JSON es un formato de datos; no es una base de datos.

```json
{
  "ok": true,
  "mensaje": "Sesión iniciada.",
  "datos": { "usuario": "jose_estudiante", "rol_codigo": "estudiante" },
  "errores": {}
}
```

Esta respuesta está simplificada. `ok` indica el resultado, `mensaje` lo explica,
`datos` contiene la información y `errores` puede señalar campos incorrectos.

| Código HTTP | Qué indica |
| --- | --- |
| 200 / 201 | Operación correcta / recurso creado |
| 401 | No hay sesión válida o las credenciales fallaron |
| 403 | No se permite la operación, por ejemplo por permisos o CSRF |
| 404 | No se encontró la ruta o el registro |
| 409 / 422 | Conflicto de datos / datos o reglas de negocio no válidos |
| 429 | Demasiados intentos |
| 500 | Fallo interno del servidor; hay que revisar el registro de errores |

**Ejercicio:** consulta GET `/public/api/catalogos` y reconoce las áreas y modalidades.
**Comprueba:** ¿en qué se diferencian la petición y la respuesta?

## 9. Base de datos y SQL

Una tabla organiza registros. Cada fila representa un registro y cada columna un dato.
Una clave primaria identifica la fila. Una clave foránea relaciona una fila con otra tabla.

| Tabla | Qué conserva |
| --- | --- |
| `usuarios` | Datos de acceso y contacto de las cuentas |
| `roles` | Los perfiles disponibles |
| `estudiantes` | Perfil académico y referencia al CV, ligado a un usuario |
| `empresas` | Información empresarial y su aprobación |
| `practicas` | Propuestas de prácticas de una empresa |
| `postulaciones` | Relación entre estudiante y práctica |
| `historial_estados` | Cambios de estado |
| `bitacora` | Registro de operaciones del sistema |

Por eso crear un estudiante implica una cuenta en `usuarios` y un perfil relacionado
en `estudiantes`. El programa puede conocer la empresa de una postulación siguiendo
su práctica. No necesita repetir todos sus datos en cada registro.

SQL consulta y modifica esas tablas. Ejemplo de consulta:

```sql
SELECT usuario, nombres, apellidos
FROM usuarios
WHERE usuario = 'bruno';
```

`SELECT` elige columnas; `FROM` indica la tabla; `WHERE` filtra filas.
`INSERT` crea registros y `UPDATE` modifica los existentes. Las bajas del sistema
son lógicas: se marcan registros para conservar sus relaciones y trazabilidad.
Una transacción agrupa cambios que deben completarse juntos o deshacerse si fallan.

PDO permite ejecutar consultas desde PHP. Las consultas preparadas separan la
instrucción SQL de los valores recibidos para evitar que se interpreten como código SQL.

Hay dos cuentas distintas que debes reconocer: `practicas_app` es una cuenta de
MySQL utilizada por el servidor; `jose_estudiante` es una cuenta del portal. Sus
contraseñas y permisos pertenecen a sistemas diferentes.

**Archivos para leer:** `includes/db/Conexion.php`, `EstudianteRepo.php` y `database/instalacion_completa.sql`.
**Ejercicio:** identifica `id_usuario` en usuarios y en estudiantes y explica la relación.

## 10. Sesión, cookie, CSRF y permisos

La autenticación comprueba quién eres. La autorización comprueba qué puedes hacer.
El rol participa en la autorización, pero también se comprueba la propiedad del
registro; una empresa no puede decidir sobre postulaciones de otra.

La cookie de sesión transporta un identificador. El servidor usa ese identificador
para relacionar solicitudes con una sesión. La cookie no contiene la contraseña del
estudiante. El token CSRF es otro valor, asociado a la sesión, que se verifica en
solicitudes que cambian datos. No sustituye al usuario y la contraseña.

La contraseña del portal se guarda como hash. PHP usa `password_verify` para
comprobarla; no necesita recuperar la contraseña original desde el hash.
Ocultar un botón ayuda a presentar las acciones adecuadas, pero el permiso se
comprueba otra vez en el servidor.

**Archivos para leer:** `includes/Seguridad/Sesion.php` y `Acceso.php`.
**Comprueba:** ¿por qué una empresa que conoce una URL no obtiene por eso permiso para usarla?

## 11. Repetir el login que probaste en Thunder Client

Usa Apache y MySQL iniciados y una cuenta del portal que ya exista.

1. Envía GET a `http://localhost/ProyectoFinalDesarrolloWeb/public/api/csrf`, sin Body.
2. Conserva su cookie `PHPSESSID` y copia `datos.csrf_token`.
3. Envía POST a `http://localhost/ProyectoFinalDesarrolloWeb/public/api/auth/login`.
4. Usa `Content-Type: application/json` y `X-CSRF-Token` con el token copiado.
5. En Body JSON coloca `identificador`, `contrasena` y, si corresponde, `tipo_cuenta`.
6. Si responde 200 con “Sesión iniciada”, conserva la cookie actualizada.
7. Consulta GET `/public/api/perfil`, sin Body, usando esa cookie.
8. Para la siguiente operación que modifique datos usa el nuevo token que devolvió el login.

Si ya creaste el estudiante del ejemplo, el JSON del paso 5 es:

```json
{
  "identificador": "jose_estudiante",
  "contrasena": "Estudiante2026!",
  "tipo_cuenta": "estudiante"
}
```

El token tiene que corresponder a la misma cookie de sesión. Mantén `localhost`
en ambas solicitudes. Un error 500 requiere revisar el servidor; no se corrige
cambiando datos del estudiante si la conexión a MySQL no está configurada.

**Ejercicio:** explica por qué consultar `/csrf` no es lo mismo que iniciar sesión.

## 12. Seguir una operación de principio a fin

Para estudiar el perfil, abre estos archivos en este orden:

1. `pages/perfil.php`: declara qué módulo y roles corresponden a la pantalla.
2. `pages/_layout.php`: proporciona el HTML compartido y carga el JavaScript.
3. `public/assets/js/app.js`: consulta la sesión y selecciona el módulo.
4. `public/assets/js/perfil.js`: presenta los datos y atiende el envío del formulario.
5. `public/assets/js/api.js`: realiza la petición HTTP.
6. `public/api/index.php`: conduce la petición a la entrada de la API.
7. `API/index.php`: elige el controlador según la ruta.
8. `API/controladores/PerfilControlador.php`: valida y procesa la operación.
9. `includes/db/EstudianteRepo.php`: consulta o actualiza el perfil en MySQL.
10. `includes/Http.php`: contiene `Respuesta`, que devuelve el JSON.

`config/bootstrap.php` prepara las clases, la sesión, los encabezados y el contexto
de auditoría que necesitan las solicitudes. Es configuración compartida de arranque.

**Ejercicio:** encuentra el `GET` que carga el perfil y el `PUT` que guarda los cambios.
Después sigue una postulación haciendo el mismo recorrido con `postulaciones.js`,
`PostulacionesControlador.php` y `PostulacionRepo.php`.

## 13. Aprender a comprobar y localizar errores

Primero verifica qué ocurrió: URL, método, datos enviados, cookie, token, código HTTP
y respuesta. La consola del navegador muestra errores de JavaScript. La pestaña Red
muestra las solicitudes. El registro de PHP/Apache contiene los detalles de errores
internos que no se muestran al público.

Una prueba comprueba un resultado esperado. Las pruebas del proyecto están en
`tests/`; las instrucciones para ejecutarlas están en `tests/README.md`. Las pruebas
completas preparan una instancia temporal, para comprobar operaciones con datos ficticios.

**Ejercicio:** sin modificar archivos, explica en qué lado buscarías cada error:
un color equivocado, un botón sin respuesta, un 403 al enviar, un 500 al consultar,
y una práctica que no puede publicarse por estar vencida.

## Preguntas para comprobar que entendiste

1. ¿Qué función cumplen HTML, CSS, JavaScript, PHP y MySQL?
2. ¿Por qué se necesitan servidor y navegador?
3. ¿Qué es una función y qué es un método?
4. ¿Qué diferencia hay entre una página y un endpoint?
5. ¿Qué cambia entre GET y POST?
6. ¿Qué contiene un JSON de respuesta?
7. ¿Por qué un estudiante tiene registros relacionados en dos tablas?
8. ¿Qué diferencia hay entre usuario de MySQL y usuario del portal?
9. ¿Qué diferencia hay entre contraseña, cookie y token CSRF?
10. ¿Dónde se decide si una empresa puede publicar o revisar una solicitud?
11. ¿Por qué se conserva el historial al retirar una postulación?
12. ¿Qué archivos intervienen desde que guardas tu perfil hasta que ves el resultado?

Una buena forma de estudiar es escoger una sola acción, seguir sus datos desde la
pantalla hasta MySQL y luego explicar el recorrido sin mirar los archivos. Repite
con otra acción cuando ese primer recorrido esté claro.

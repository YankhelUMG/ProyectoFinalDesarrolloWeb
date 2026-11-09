# Comprobaciones de la aplicación

## JavaScript

Desde la raíz, con Node.js 24:

```powershell
node --test tests/frontend.test.mjs
```

Estas pruebas comprueban el enlazado de los módulos, renderizado como texto,
catálogo, envío de CSRF, renovación del token y recarga de la pantalla.
No necesitan instalar paquetes.

Las rutas, referencias entre métodos y generación de CSRF pueden comprobarse sin MySQL:

```powershell
C:\xampp\php\php.exe tests/backend.php
```

## PHP y MySQL/MariaDB

`integracion.py` hace solicitudes HTTP reales contra PHP y una instancia temporal
de MariaDB. Requiere Python 3, PHP con PDO MySQL y los ejecutables de MariaDB de XAMPP.
No instala paquetes ni usa las credenciales de `config/.env`.

Prepara una carpeta de pruebas **fuera de htdocs**. Ejemplo en PowerShell:

```powershell
$pruebas = Join-Path $env:TEMP 'practicas-refactor-pruebas'
New-Item -ItemType Directory -Path $pruebas -Force
C:\xampp\mysql\bin\mysql_install_db.exe --datadir="$pruebas\mysql" --port=3317 --silent

python tests/integracion.py --mysql-data "$pruebas\mysql" --private-dir "$pruebas\cv"
```

La inicialización se ejecuta una sola vez por carpeta nueva. El ejecutor inicia y
cierra esa instancia temporal, recrea únicamente `refactor_pruebas` desde `database/instalacion_completa.sql` y prepara
los PDF ficticios fuera del directorio publicado. Excluye el puerto 3306; no lo
dirijas a una instancia de trabajo. Usa el puerto HTTP 8097 para el servidor de pruebas.

Se comprueban los cuatro roles, CSRF, registros, aprobación de empresas, revisión y
publicación, consulta, postulación, duplicados, retiro, decisiones, CV, cambio de
contraseña y administración de cuentas. Los códigos HTTP y el formato JSON también
se verifican en cada solicitud.

Para revisar las pantallas con esos datos temporales, añade `--esperar`. El servidor
queda en `http://127.0.0.1:8097`. Para terminarlo, crea un archivo `terminar.txt`
dentro de la carpeta indicada en `--private-dir`. Los usuarios iniciales están
documentados en el README y los datos nuevos son ficticios.

El enrutador `tests/router.php` solo funciona con el servidor de pruebas de PHP.
Las reglas de Apache bloquean el acceso web a esta carpeta.

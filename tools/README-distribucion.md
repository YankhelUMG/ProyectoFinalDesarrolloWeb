# Instalación rápida

En la raíz del repositorio están `Ejecutar primero.exe`, `basededatos.sql` y este README principal. Conserva el repositorio completo: el ejecutable necesita encontrar el `index.php` del proyecto.

## Configuración

1. Descarga o extrae el repositorio completo.
2. Instala XAMPP e inicia **Apache** y **MySQL** desde el panel.
3. Ejecuta `Ejecutar primero.exe` como administrador. Si pregunta dónde está XAMPP, selecciona su carpeta, por ejemplo `C:\xampp`.
4. Pega en el navegador la URL que el programa copió. Normalmente será `http://SistemaPracticas/`; puede incluir un puerto si Apache usa uno distinto de 80.
5. Abre phpMyAdmin (`http://localhost/phpmyadmin`), elige **Importar**, selecciona `basededatos.sql` y pulsa **Continuar**. Importa en una base vacía; el archivo no actualiza ni reemplaza una instalación activa.
6. Inicia sesión con una cuenta principal:

| Perfil | Usuario | Contraseña |
|---|---|---|
| Estudiante | `estudiante` | `0123456789` |
| Empresa aprobada | `empresa` | `0123456789` |
| Administrador | `admin` | `0123456789` |
| Superadministrador | `superadmin` | `0123456789` |

Estas cuentas son para demostración. Cambia sus contraseñas antes de usar el sistema en un entorno real. El ejecutable configura Windows y Apache solo en la PC donde se ejecuta; repítelo en cada PC que aloje el sistema.

`database/basededatos.sql` es la fuente canónica. Para actualizar el ejecutable y la copia visible `basededatos.sql`, ejecuta `tools/build-configurador.ps1` desde la raíz del repositorio.
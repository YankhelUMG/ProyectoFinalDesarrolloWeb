<?php
declare(strict_types = 1);
// Envía las rutas antiguas a las pantallas ligeras que consumen la API.
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/pages/admin.php'));
$base = rtrim(dirname(dirname($scriptName)), '/');
$destinos = [
    'admin.php' => '/pages/administracion/index.php',
    'empresa.php' => '/pages/empresa/index.php',
    'perfil.php' => '/pages/perfil/index.php',
    'roles.php' => '/pages/empleados/index.php',
    'seguimiento.php' => '/pages/postulaciones/index.php',
];
$pagina = basename($scriptName);
if ($pagina === 'registro.php') {
    $tipo = ($_GET['tipo'] ?? '') === 'empresa' ? 'empresa' : 'estudiante';
    header('Location: ' . $base . '/?tipo_cuenta=' . $tipo . '&registro=1#acceso', true, 302);
    exit;
}
header('Location: ' . $base . ($destinos[$pagina] ?? '/'), true, 302);
exit;
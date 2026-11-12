<?php
// El acceso público es la pantalla de inicio del sistema.
// login.html comprueba si existe una sesión activa y redirige según el rol.
$rutaScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$baseAplicacion = preg_replace('#/index\.php$#i', '', $rutaScript) ?? '';
header('Location: ' . rtrim($baseAplicacion, '/') . '/public/login.html', true, 302);
exit;

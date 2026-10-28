<?php
$rutaScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$baseAplicacion = preg_replace('#/index\.php$#i', '', $rutaScript) ?? '';
$baseAplicacion = rtrim($baseAplicacion, '/');
$baseSeguro = htmlspecialchars($baseAplicacion, ENT_QUOTES, 'UTF-8');
$apiBaseSeguro = htmlspecialchars($baseAplicacion . '/public/api', ENT_QUOTES, 'UTF-8');
$loginSeguro = htmlspecialchars($baseAplicacion . '/public/login.html', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Sistema de solicitudes y seguimiento de prácticas profesionales">
  <meta name="app-base" content="<?= $baseSeguro ?>">
  <meta name="api-base" content="<?= $apiBaseSeguro ?>">
  <meta name="login-url" content="<?= $loginSeguro ?>">
  <title>Prácticas profesionales</title>
  <link rel="stylesheet" href="<?= $baseSeguro ?>/public/assets/css/app.css">
  <script type="module" src="<?= $baseSeguro ?>/public/assets/js/arranque.js"></script>
</head>
<body>
  <header>
    <h1><a href="<?= $baseSeguro ?>/">Prácticas profesionales</a></h1>
  </header>
  <main class="pantalla-acceso">
    <section class="panel-acceso" aria-labelledby="titulo-arranque">
      <h2 id="titulo-arranque">Abriendo tu espacio</h2>
      <p data-estado-arranque role="status" aria-live="polite">Consultando la sesión…</p>
      <a class="boton primario" data-enlace-login href="<?= $loginSeguro ?>">Ir al inicio de sesión</a>
    </section>
  </main>
  <footer><p>Sistema de solicitudes y seguimiento de prácticas profesionales</p></footer>
</body>
</html>
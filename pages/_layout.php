<?php
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}
$rutaPagina = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/pages/index.php'));
$baseAplicacion = preg_replace('#/pages/.*$#i', '', $rutaPagina) ?? '';
$baseAplicacion = rtrim($baseAplicacion, '/');
$baseSeguro = htmlspecialchars($baseAplicacion, ENT_QUOTES, 'UTF-8');
$apiBaseSeguro = htmlspecialchars($baseAplicacion . '/public/api', ENT_QUOTES, 'UTF-8');
$loginSeguro = htmlspecialchars($baseAplicacion . '/public/login.html', ENT_QUOTES, 'UTF-8');
$tituloSeguro = htmlspecialchars((string)$tituloModulo, ENT_QUOTES, 'UTF-8');
$descripcionSeguro = htmlspecialchars((string)$descripcionModulo, ENT_QUOTES, 'UTF-8');
$moduloSeguro = htmlspecialchars((string)$modulo, ENT_QUOTES, 'UTF-8');
$rolesSeguro = htmlspecialchars(implode(',', $rolesPermitidos ?? []), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="app-base" content="<?= $baseSeguro ?>">
  <meta name="api-base" content="<?= $apiBaseSeguro ?>">
  <meta name="login-url" content="<?= $loginSeguro ?>">
  <title><?= $tituloSeguro ?> · Prácticas profesionales</title>
  <link rel="stylesheet" href="<?= $baseSeguro ?>/public/assets/css/app.css?v=20261028-4">
  <script type="module" src="<?= $baseSeguro ?>/public/assets/js/app.js?v=20261028-13"></script>
</head>
<body data-module="<?= $moduloSeguro ?>" data-roles="<?= $rolesSeguro ?>">
  <header>
    <p><a href="<?= $baseSeguro ?>/">Prácticas profesionales</a></p>
    <nav id="nav-principal" aria-label="Navegación principal"></nav>
    <p id="estado-sesion" aria-live="polite">Consultando sesión…</p>
  </header>
  <main>
    <h1><?= $tituloSeguro ?></h1>
    <p><?= $descripcionSeguro ?></p>
    <div id="contenido-modulo" aria-live="polite"></div>
    <section id="mensaje" role="status" aria-live="polite" hidden></section>
  </main>
  <footer><p id="nombre-sistema">Prácticas profesionales</p></footer>
</body>
</html>

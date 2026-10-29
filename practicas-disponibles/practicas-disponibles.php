<?php
$rutaScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/practicas-disponibles/practicas-disponibles.php'));
$baseAplicacion = preg_replace('#/practicas-disponibles/practicas-disponibles\.php$#i', '', $rutaScript) ?? '';
$baseAplicacion = rtrim($baseAplicacion, '/');
$baseSeguro = htmlspecialchars($baseAplicacion, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Busca prácticas profesionales por modalidad, área y fecha de inicio.">
  <meta name="app-base" content="<?= $baseSeguro ?>">
  <title>Prácticas disponibles</title>
  <link rel="stylesheet" href="<?= $baseSeguro ?>/public/assets/css/app.css?v=20261028-4">
  <script type="module" src="<?= $baseSeguro ?>/public/assets/js/app.js?v=20261028-13"></script>
</head>
<body data-page="practicas-disponibles">
  <header>
    <div class="barra-izquierda">
      <h1><a href="<?= $baseSeguro ?>/">Prácticas profesionales</a></h1>
    </div>
    <div class="barra-derecha">
      <div id="botones-login" class="pestanas-cuenta">
        <a class="boton" href="<?= $baseSeguro ?>/public/login.html">Iniciar sesión</a>
        <a class="boton secundario" href="<?= $baseSeguro ?>/public/registro-estudiante.html">Crear cuenta estudiante</a>
      </div>
      <p id="estado-sesion" aria-live="polite" hidden></p>
    </div>
    <nav id="nav-principal" aria-label="Navegación de la cuenta"></nav>
  </header>

  <main id="contenido">
    <section id="oportunidades" aria-labelledby="titulo-oportunidades">
      <h2 id="titulo-oportunidades">Prácticas disponibles</h2>
      <p>Filtra las oportunidades y consulta diez resultados por página.</p>
      <form id="filtros">
        <fieldset>
          <legend>Filtrar prácticas</legend>
          <label>Palabra clave <input name="q" type="search" placeholder="Título o descripción"></label>
          <label>Empresa <input name="empresa" type="search" placeholder="Nombre de la empresa"></label>
          <label>Área
            <select name="area" data-catalogo="area"><option value="">Todas las áreas</option></select>
          </label>
          <label>Tipo o modalidad
            <select name="modalidad" data-catalogo="modalidad"><option value="">Todas las modalidades</option></select>
          </label>
          <label>Inicio desde <input type="date" name="fecha_inicio_desde"></label>
          <label>Inicio hasta <input type="date" name="fecha_inicio_hasta"></label>
          <button type="submit">Buscar</button>
          <button type="reset" class="boton secundario">Limpiar filtros</button>
        </fieldset>
      </form>
      <p id="estado-carga" role="status" aria-live="polite"></p>
      <div id="lista-practicas" aria-live="polite"></div>
      <nav class="paginacion" aria-label="Paginación de prácticas">
        <button id="pagina-anterior" type="button" disabled>Anterior</button>
        <span id="pagina-actual">Página 1</span>
        <button id="pagina-siguiente" type="button" disabled>Siguiente</button>
      </nav>
      <p>Se muestran hasta 10 prácticas por página.</p>
    </section>
    <section id="mensaje" role="status" aria-live="polite" hidden></section>
  </main>

  <footer><p id="nombre-sistema">Sistema de prácticas profesionales</p></footer>
</body>
</html>

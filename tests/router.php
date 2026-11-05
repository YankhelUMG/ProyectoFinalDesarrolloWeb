<?php
// Enrutador exclusivo para el servidor PHP de las pruebas locales.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#/(?:public/)?api(?:/|$)#i', $ruta)) {
    require dirname(__DIR__) . '/public/api/index.php';
    return true;
}
return false;
